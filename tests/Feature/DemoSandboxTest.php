<?php

use App\Actions\Demo\StartDemoSandbox;
use App\Actions\Invitations\InviteToClub;
use App\Enums\ClubInvitationRole;
use App\Enums\ClubPlan;
use App\Enums\ClubSegment;
use App\Enums\StaffRole;
use App\Exceptions\ClubInvitationException;
use App\Livewire\Clubs\Plan;
use App\Livewire\Clubs\StartDemo;
use App\Livewire\Members\Import;
use App\Models\Club;
use App\Models\DemoRequest;
use App\Models\User;
use App\Services\SpamProtection;
use Database\Seeders\DemoClubSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Storage;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;

beforeEach(function () {
    Storage::fake('public');
});

/**
 * The demo modal with valid details, ready to submit.
 */
function demoForm(): Testable
{
    return Livewire::test(StartDemo::class)
        ->set('email', 'anna@example.com')
        ->set('club_type', ClubSegment::DanceSchool->value);
}

test('a visitor gets a private, filled demo club and lands in it as its owner', function () {
    $component = demoForm();
    $this->travel(SpamProtection::MINIMUM_SECONDS + 1)->seconds();

    $component->call('start')->assertHasNoErrors();

    $club = Club::query()->whereNotNull('demo_expires_at')->sole();
    $owner = auth()->user();

    $component->assertRedirect(route('clubs.dashboard', $club));
    expect($club->is_demo)->toBeTrue()
        ->and($club->demo_expires_at->isFuture())->toBeTrue()
        ->and($owner->isDemoSandbox())->toBeTrue()
        ->and($owner->isStaffOf($club, StaffRole::Owner))->toBeTrue()
        ->and($club->memberships()->count())->toBe(StartDemoSandbox::MEMBER_COUNT);
    Storage::disk('public')->assertExists($club->photo_path);

    // Every account in the sandbox is undeliverable and expires with the club.
    $accounts = User::query()->whereNotNull('demo_expires_at')->get();
    expect($accounts)->not->toBeEmpty()
        ->and($accounts->every(fn (User $user): bool => str_ends_with($user->email, '@'.DemoClubSeeder::SANDBOX_EMAIL_DOMAIN)))->toBeTrue();
});

test('a visitor can start only a few demos per hour', function () {
    foreach (range(1, StartDemo::MAX_PER_HOUR) as $attempt) {
        RateLimiter::hit('demo-sandbox:127.0.0.1', 3600);
    }

    $component = demoForm();
    $this->travel(SpamProtection::MINIMUM_SECONDS + 1)->seconds();

    $component->call('start')->assertHasErrors([SpamProtection::ERROR_KEY => __('You have started the maximum number of demos for this hour. Try again in :minutes minutes, or create your own club right away.', ['minutes' => 60])]);

    expect(Club::query()->count())->toBe(0);
});

test('no new demo starts once the cap of live sandboxes is reached', function () {
    Club::factory()->count(StartDemo::MAX_ACTIVE_SANDBOXES)->create(['demo_expires_at' => now()->addHour()]);

    $component = demoForm();
    $this->travel(SpamProtection::MINIMUM_SECONDS + 1)->seconds();

    $component->call('start')->assertHasErrors(SpamProtection::ERROR_KEY);

    expect(User::query()->whereNotNull('demo_expires_at')->exists())->toBeFalse();
});

test('the prune deletes expired sandboxes and nothing else', function () {
    [$expiredOwner, $expiredClub] = app(StartDemoSandbox::class)->handle();
    $this->travel(StartDemoSandbox::LIFETIME_HOURS - 1)->hours();
    [$liveOwner, $liveClub] = app(StartDemoSandbox::class)->handle();
    $curatedDemo = Club::factory()->demo()->create();
    $realUser = User::factory()->create();

    $this->travel(2)->hours();
    $this->artisan('demo:prune')->assertSuccessful();

    expect(Club::query()->find($expiredClub->id))->toBeNull()
        ->and(User::query()->find($expiredOwner->id))->toBeNull()
        ->and(Club::query()->find($liveClub->id))->not->toBeNull()
        ->and(User::query()->find($liveOwner->id))->not->toBeNull()
        ->and(Club::query()->find($curatedDemo->id))->not->toBeNull()
        ->and(User::query()->find($realUser->id))->not->toBeNull()
        ->and(User::query()->where('demo_expires_at', '<=', now())->exists())->toBeFalse();
    Storage::disk('public')->assertMissing($expiredClub->photo_path);
    Storage::disk('public')->assertExists($liveClub->photo_path);
});

test('mail to a demo account is never sent', function () {
    config()->set('mail.default', 'array');

    Mail::raw('Hallo', fn ($message) => $message->to('anna.x1y2z3@'.DemoClubSeeder::SANDBOX_EMAIL_DOMAIN));
    Mail::raw('Hallo', fn ($message) => $message->to('anna@example.com'));

    $sent = app('mailer')->getSymfonyTransport()->messages();

    expect($sent)->toHaveCount(1)
        ->and($sent->first()->getEnvelope()->getRecipients()[0]->getAddress())->toBe('anna@example.com');
});

test('a demo club cannot invite real people', function () {
    Mail::fake();
    [$owner, $club] = app(StartDemoSandbox::class)->handle();

    expect(fn () => app(InviteToClub::class)->handle($club, 'someone@example.com', ClubInvitationRole::Member, $owner))
        ->toThrow(ClubInvitationException::class);

    Mail::assertNothingOutgoing();
});

test('a demo club cannot import a member list', function () {
    [$owner, $club] = app(StartDemoSandbox::class)->handle();
    $csv = UploadedFile::fake()->createWithContent('leden.csv', "Naam;E-mail\nAnna de Vries;anna@example.com\n");

    Livewire::actingAs($owner)
        ->test(Import::class, ['club' => $club])
        ->set('file', $csv)
        ->call('start')
        ->assertHasErrors('file');

    expect(User::query()->where('email', 'anna@example.com')->exists())->toBeFalse();
});

test('a demo club cannot choose a paid plan', function () {
    [$owner, $club] = app(StartDemoSandbox::class)->handle();

    Livewire::actingAs($owner)
        ->test(Plan::class, ['club' => $club])
        ->assertSee(__('Not available in the demo'))
        ->call('subscribe', ClubPlan::Starter->value)
        ->assertForbidden();
});

test('a demo account keeps its undeliverable address', function () {
    [$owner] = app(StartDemoSandbox::class)->handle();
    $address = $owner->email;

    $owner->update(['email' => 'real.person@example.com']);

    expect($owner->fresh()->email)->toBe($address);
});

test('leaving the demo logs the visitor out and opens the signup form', function () {
    [$owner] = app(StartDemoSandbox::class)->handle();

    $this->actingAs($owner)
        ->post(route('demo.leave'))
        ->assertRedirect(route('home').'#signup');

    $this->assertGuest();
});

test('the landing pages offer the demo to guests', function () {
    $this->get(route('home'))->assertSeeLivewire(StartDemo::class);
    $this->get(route('segments.show', 'dansschool'))->assertSeeLivewire(StartDemo::class);
});

test('starting the demo right after the page loads explains why it did not work', function () {
    demoForm()
        ->call('start')
        ->assertHasErrors([SpamProtection::ERROR_KEY => __('That was a little too fast. Wait a moment and try again.')]);

    expect(Club::query()->count())->toBe(0);
});

test('starting a demo remembers the sandbox in a cookie', function () {
    $component = demoForm();
    $this->travel(SpamProtection::MINIMUM_SECONDS + 1)->seconds();

    $component->call('start');

    $cookie = collect(Cookie::getQueuedCookies())->firstWhere(fn ($cookie) => $cookie->getName() === StartDemo::COOKIE);
    expect($cookie->getValue())->toBe((string) auth()->id());
});

test('the demo modal records the email address and type of club', function () {
    $component = Livewire::test(StartDemo::class)
        ->assertSee(__('Try the demo'))
        ->set('email', 'Anna@Example.com')
        ->set('club_type', ClubSegment::YogaStudio->value);
    $this->travel(SpamProtection::MINIMUM_SECONDS + 1)->seconds();

    $component->call('start')->assertHasNoErrors();

    $request = DemoRequest::query()->sole();
    expect($request->email)->toBe('anna@example.com')
        ->and($request->club_segment)->toBe(ClubSegment::YogaStudio)
        ->and($request->other_club_type)->toBeNull();
});

test('a visitor who chooses something else describes their club', function () {
    $component = Livewire::test(StartDemo::class)
        ->set('email', 'anna@example.com')
        ->set('club_type', DemoRequest::OTHER_CLUB_TYPE)
        ->assertSee(__('What kind of club is it?'));
    $this->travel(SpamProtection::MINIMUM_SECONDS + 1)->seconds();

    $component->call('start')->assertHasErrors(['other_club_type' => 'required_if']);
    expect(Club::query()->count())->toBe(0);

    $component->set('other_club_type', 'Zwemschool')->call('start')->assertHasNoErrors();

    $request = DemoRequest::query()->sole();
    expect($request->club_segment)->toBeNull()
        ->and($request->other_club_type)->toBe('Zwemschool');
});

test('the demo needs an email address and a type of club', function (array $details, array $errors) {
    $component = Livewire::test(StartDemo::class);
    $this->travel(SpamProtection::MINIMUM_SECONDS + 1)->seconds();

    foreach ($details as $property => $value) {
        $component->set($property, $value);
    }

    $component->call('start')->assertHasErrors($errors);

    expect(Club::query()->count())->toBe(0)
        ->and(DemoRequest::query()->count())->toBe(0);
})->with([
    'nothing filled in' => [[], ['email' => 'required', 'club_type' => 'required']],
    'invalid email address' => [['email' => 'anna', 'club_type' => 'dansschool'], ['email' => 'email']],
    'unknown type of club' => [['email' => 'anna@example.com', 'club_type' => 'zwemschool'], ['club_type' => 'in']],
]);

test('a segment page preselects its type of club in the demo modal', function () {
    Livewire::test(StartDemo::class, ['segment' => ClubSegment::PadelClub])
        ->assertSet('club_type', ClubSegment::PadelClub->value);

    $this->get(route('segments.show', ClubSegment::PadelClub))
        ->assertOk()
        ->assertSee('"club_type":"'.ClubSegment::PadelClub->value.'"');
});

test('a visitor with a live sandbox returns to it instead of starting a new one', function () {
    [$owner, $club] = app(StartDemoSandbox::class)->handle();

    Livewire::withCookie(StartDemo::COOKIE, (string) $owner->id)
        ->test(StartDemo::class)
        ->assertSet('canResume', true)
        ->assertSee(__('Continue your demo'))
        ->assertDontSee(__('Type of club'))
        ->call('start')
        ->assertHasNoErrors()
        ->assertRedirect(route('clubs.dashboard', $club));

    expect(auth()->id())->toBe($owner->id)
        ->and(Club::query()->count())->toBe(1);
});

test('the demo cookie never logs in to an expired sandbox or a real account', function (string $owner) {
    $ownerId = match ($owner) {
        'expired' => tap(app(StartDemoSandbox::class)->handle()[0], fn (User $user) => $this->travel(StartDemoSandbox::LIFETIME_HOURS + 1)->hours())->id,
        'real' => User::factory()->create()->id,
    };

    Livewire::withCookie(StartDemo::COOKIE, (string) $ownerId)
        ->test(StartDemo::class)
        ->assertSet('canResume', false)
        ->call('start');

    expect(auth()->id())->not->toBe($ownerId);
})->with(['expired', 'real']);
