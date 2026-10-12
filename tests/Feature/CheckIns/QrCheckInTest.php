<?php

use App\Actions\CheckIns\CheckInWithCode;
use App\Enums\StaffRole;
use App\Events\CheckIns\CheckInRefused;
use App\Events\CheckIns\MemberCheckedIn;
use App\Exceptions\CheckInException;
use App\Livewire\CheckIns\Desk;
use App\Livewire\CheckIns\Kiosk;
use App\Models\CheckIn;
use App\Models\Club;
use App\Models\StripCard;
use App\Models\User;
use App\Services\CheckIns\CheckInCode;
use App\Services\CheckIns\QrCodeRenderer;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Event;
use Livewire\Livewire;

beforeEach(function () {
    $this->club = Club::factory()->create();
    $this->member = User::factory()->create();
    $this->club->addMember($this->member);

    $this->card = StripCard::factory()->create([
        'club_id' => $this->club->id,
        'user_id' => $this->member->id,
        'strips_remaining' => 5,
    ]);

    $this->codes = app(CheckInCode::class);
});

test('scanning the club code checks the member in as a visit without a lesson', function () {
    $checkIn = app(CheckInWithCode::class)->fromClubCode($this->member, $this->codes->forClub($this->club));

    expect($checkIn->club_id)->toBe($this->club->id)
        ->and($checkIn->user_id)->toBe($this->member->id)
        ->and($checkIn->lesson_occurrence_id)->toBeNull()
        ->and($checkIn->checked_in_by)->toBeNull()
        ->and($this->card->refresh()->strips_remaining)->toBe(4);
});

test('a code from the previous window is still accepted', function () {
    $code = $this->codes->forClub($this->club);

    $this->travel(CheckInCode::PERIOD_SECONDS)->seconds();

    expect(app(CheckInWithCode::class)->fromClubCode($this->member, $code)->id)->not->toBeNull();
});

test('a code from an older window is rejected', function () {
    $code = $this->codes->forClub($this->club);

    $this->travel(CheckInCode::PERIOD_SECONDS * 3)->seconds();

    expect(fn () => app(CheckInWithCode::class)->fromClubCode($this->member, $code))
        ->toThrow(CheckInException::class, __('This QR code is no longer valid. Show a fresh code and scan again.'));

    expect(CheckIn::query()->count())->toBe(0);
});

test('a tampered signature is rejected', function () {
    $code = $this->codes->forClub($this->club);
    $tampered = mb_substr($code, 0, -1).(mb_substr($code, -1) === 'A' ? 'B' : 'A');

    expect(fn () => app(CheckInWithCode::class)->fromClubCode($this->member, $tampered))
        ->toThrow(CheckInException::class);
});

test('a code minted for another club does not check in here', function () {
    $otherClub = Club::factory()->create();

    expect(fn () => app(CheckInWithCode::class)->fromClubCode($this->member, $this->codes->forClub($otherClub)))
        ->toThrow(CheckInException::class, __('An active membership at this club is required.'));
});

test('a member code cannot be used as a club code', function () {
    expect(fn () => app(CheckInWithCode::class)->fromClubCode($this->member, $this->codes->forMember($this->member)))
        ->toThrow(CheckInException::class);
});

test('a repeated scan within the duplicate window is rejected', function () {
    app(CheckInWithCode::class)->fromClubCode($this->member, $this->codes->forClub($this->club));

    $this->travel(CheckInWithCode::DUPLICATE_WINDOW_MINUTES - 1)->minutes();

    expect(fn () => app(CheckInWithCode::class)->fromClubCode($this->member, $this->codes->forClub($this->club)))
        ->toThrow(CheckInException::class, __('A check-in for this member was already registered a moment ago.'))
        ->and(CheckIn::query()->count())->toBe(1)
        ->and($this->card->refresh()->strips_remaining)->toBe(4);
});

test('a scan after the duplicate window is a new visit', function () {
    app(CheckInWithCode::class)->fromClubCode($this->member, $this->codes->forClub($this->club));

    $this->travel(CheckInWithCode::DUPLICATE_WINDOW_MINUTES + 1)->minutes();

    app(CheckInWithCode::class)->fromClubCode($this->member, $this->codes->forClub($this->club));

    expect(CheckIn::query()->count())->toBe(2)
        ->and($this->card->refresh()->strips_remaining)->toBe(3);
});

test('the desk checks a member in by scanning their code', function () {
    $staff = User::factory()->create();
    $this->club->addStaff($staff, StaffRole::Admin);

    Livewire::actingAs($staff)
        ->test(Desk::class, ['club' => $this->club])
        ->call('scanMemberCode', $this->codes->forMember($this->member));

    $checkIn = CheckIn::query()->firstOrFail();

    expect($checkIn->user_id)->toBe($this->member->id)
        ->and($checkIn->checked_in_by)->toBe($staff->id)
        ->and($checkIn->lesson_occurrence_id)->toBeNull();
});

test('the desk cannot check in a member of another club', function () {
    $staff = User::factory()->create();
    $this->club->addStaff($staff, StaffRole::Admin);

    $stranger = User::factory()->create();

    Livewire::actingAs($staff)
        ->test(Desk::class, ['club' => $this->club])
        ->call('scanMemberCode', $this->codes->forMember($stranger));

    expect(CheckIn::query()->count())->toBe(0);
});

test('a scan at the kiosk is confirmed on the club broadcast channel', function () {
    Event::fake([MemberCheckedIn::class]);

    $checkIn = app(CheckInWithCode::class)->fromClubCode($this->member, $this->codes->forClub($this->club));

    Event::assertDispatched(MemberCheckedIn::class, function (MemberCheckedIn $event) use ($checkIn): bool {
        return $event->checkIn->is($checkIn)
            && $event->broadcastOn()->name === "private-clubs.{$this->club->id}.check-ins"
            && $event->broadcastAs() === 'member.checked-in'
            && $event->broadcastWith() === ['name' => $this->member->name, 'coverage' => __('Strip card')];
    });
});

test('a refused scan is reported on the club screen as well', function () {
    Event::fake([MemberCheckedIn::class, CheckInRefused::class]);

    $this->card->delete();

    expect(fn () => app(CheckInWithCode::class)->fromClubCode($this->member, $this->codes->forClub($this->club)))
        ->toThrow(CheckInException::class, __('No coverage: no unlimited subscription and no usable strip card.'));

    Event::assertNotDispatched(MemberCheckedIn::class);

    Event::assertDispatched(CheckInRefused::class, function (CheckInRefused $event): bool {
        return $event->club->is($this->club)
            && $event->member->is($this->member)
            && $event->broadcastOn()->name === "private-clubs.{$this->club->id}.check-ins"
            && $event->broadcastAs() === 'check-in.refused'
            && $event->broadcastWith() === [
                'name' => $this->member->name,
                'reason' => __('No coverage: no unlimited subscription and no usable strip card.'),
            ];
    });
});

test('a repeated scan is reported too', function () {
    app(CheckInWithCode::class)->fromClubCode($this->member, $this->codes->forClub($this->club));

    Event::fake([CheckInRefused::class]);

    expect(fn () => app(CheckInWithCode::class)->fromClubCode($this->member, $this->codes->forClub($this->club)))
        ->toThrow(CheckInException::class);

    Event::assertDispatched(CheckInRefused::class, fn (CheckInRefused $event): bool => $event->reason === __('A check-in for this member was already registered a moment ago.'));
});

test('a code that does not verify names no club, so nothing is reported', function () {
    Event::fake([CheckInRefused::class]);

    expect(fn () => app(CheckInWithCode::class)->fromClubCode($this->member, 'C1.ZZZ.0000000000000000'))
        ->toThrow(CheckInException::class);

    Event::assertNotDispatched(CheckInRefused::class);
});

test('a broadcaster that is down does not cost the member their check-in', function () {
    // `queue()` is what the dispatcher hands a ShouldBroadcastNow event to;
    // `once()` keeps this test from passing on a path that no longer runs.
    Broadcast::shouldReceive('queue')->once()->andThrow(new RuntimeException('Reverb is down'));

    $checkIn = app(CheckInWithCode::class)->fromClubCode($this->member, $this->codes->forClub($this->club));

    expect($checkIn->exists)->toBeTrue()
        ->and($this->card->refresh()->strips_remaining)->toBe(4);
});

test('a broadcaster that is down still reports the refusal to the member', function () {
    Broadcast::shouldReceive('queue')->once()->andThrow(new RuntimeException('Reverb is down'));

    $this->card->delete();

    expect(fn () => app(CheckInWithCode::class)->fromClubCode($this->member, $this->codes->forClub($this->club)))
        ->toThrow(CheckInException::class, __('No coverage: no unlimited subscription and no usable strip card.'));
});

test('the desk scanning a member does not light up the kiosk screen', function () {
    $staff = User::factory()->create();
    $this->club->addStaff($staff, StaffRole::Admin);

    Event::fake([MemberCheckedIn::class]);

    Livewire::actingAs($staff)
        ->test(Desk::class, ['club' => $this->club])
        ->call('scanMemberCode', $this->codes->forMember($this->member));

    Event::assertNotDispatched(MemberCheckedIn::class);
});

test('only staff of the club may listen on its check-in channel', function () {
    // The suite broadcasts over the null driver, which authorizes nothing at
    // all. Swap in the real driver and re-register the channels on it — they
    // are registered per driver instance, at boot, on whatever drove then.
    config([
        'broadcasting.default' => 'reverb',
        'broadcasting.connections.reverb.key' => 'test-key',
        'broadcasting.connections.reverb.secret' => 'test-secret',
        'broadcasting.connections.reverb.app_id' => 'test-app',
    ]);

    require base_path('routes/channels.php');

    $staff = User::factory()->create();
    $this->club->addStaff($staff, StaffRole::Admin);

    $payload = ['channel_name' => "private-clubs.{$this->club->id}.check-ins", 'socket_id' => '1234.5678'];

    $this->actingAs($staff)->postJson('/broadcasting/auth', $payload)->assertOk();
    $this->actingAs($this->member)->postJson('/broadcasting/auth', $payload)->assertForbidden();
    $this->postJson('/broadcasting/auth', $payload)->assertForbidden();
});

test('staff can open the kiosk screen and members cannot', function () {
    $staff = User::factory()->create();
    $this->club->addStaff($staff, StaffRole::Admin);

    Livewire::actingAs($staff)
        ->test(Kiosk::class, ['club' => $this->club])
        ->assertSee('<svg', escape: false)
        ->assertSee('images/logos/squadpass-mark.png')
        // The screen confirms over the channel instead of listing who came in.
        ->assertDontSee($this->member->name);

    Livewire::actingAs($this->member)
        ->test(Kiosk::class, ['club' => $this->club])
        ->assertForbidden();
});

test('the code renders as a scaling SVG with the mark stamped into the PNG', function () {
    $renderer = app(QrCodeRenderer::class);
    $code = $this->codes->forMember($this->member);

    $svg = $renderer->svg($code);

    // Inline SVG has to scale with its container, so no prolog and no fixed
    // size on the root element (the background rect keeps its own size).
    $root = mb_substr($svg, 0, mb_strpos($svg, '>') + 1);

    expect($svg)->toStartWith('<svg')
        ->and($root)->toContain('viewBox="0 0 400 400"')
        ->and($root)->not->toContain('width=')
        ->and($root)->not->toContain('height=');

    // The middle of the PNG carries the mark, so that area holds brand blue
    // instead of only black and white modules.
    $image = imagecreatefromstring($renderer->png($code));
    $size = imagesx($image);
    $from = (int) ($size * (0.5 - QrCodeRenderer::LOGO_RATIO / 2));
    $to = (int) ($size * (0.5 + QrCodeRenderer::LOGO_RATIO / 2));
    $brandBlue = 0;

    for ($y = $from; $y < $to; $y++) {
        for ($x = $from; $x < $to; $x++) {
            $rgb = imagecolorat($image, $x, $y);
            $red = ($rgb >> 16) & 0xFF;
            $blue = $rgb & 0xFF;

            if ($blue > 180 && $red < 120) {
                $brandBlue++;
            }
        }
    }

    expect($brandBlue)->toBeGreaterThan(0);
});
