<?php

use App\Actions\CheckIns\CheckInWithCode;
use App\Models\CheckIn;
use App\Models\Club;
use App\Models\StripCard;
use App\Models\User;
use App\Services\CheckIns\CheckInCode;
use Laravel\Sanctum\Sanctum;

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

    Sanctum::actingAs($this->member);
});

test('scanning a club code checks the member in', function () {
    $this->postJson('/api/v1/check-ins', ['code' => $this->codes->forClub($this->club)])
        ->assertCreated()
        ->assertJsonPath('data.coverage', __('Strip card'))
        ->assertJsonPath('data.lesson_name', null)
        ->assertJsonPath('data.club.id', $this->club->id);

    expect(CheckIn::query()->where('user_id', $this->member->id)->count())->toBe(1)
        ->and($this->card->refresh()->strips_remaining)->toBe(4);
});

test('an expired code is refused', function () {
    $code = $this->codes->forClub($this->club);

    $this->travel(CheckInCode::PERIOD_SECONDS * 3)->seconds();

    $this->postJson('/api/v1/check-ins', ['code' => $code])
        ->assertStatus(422)
        ->assertJsonPath('message', __('This QR code is no longer valid. Show a fresh code and scan again.'));

    expect(CheckIn::query()->count())->toBe(0);
});

test('a code from a club the member does not belong to is refused', function () {
    $this->postJson('/api/v1/check-ins', ['code' => $this->codes->forClub(Club::factory()->create())])
        ->assertStatus(422)
        ->assertJsonPath('message', __('An active membership at this club is required.'));
});

test('a member without coverage is refused', function () {
    $this->card->delete();

    $this->postJson('/api/v1/check-ins', ['code' => $this->codes->forClub($this->club)])
        ->assertStatus(422)
        ->assertJsonPath('message', __('No coverage: no unlimited subscription and no usable strip card.'));
});

test('a repeated scan is refused instead of redeeming a second strip', function () {
    app(CheckInWithCode::class)->fromClubCode($this->member, $this->codes->forClub($this->club));

    $this->postJson('/api/v1/check-ins', ['code' => $this->codes->forClub($this->club)])
        ->assertStatus(422)
        ->assertJsonPath('message', __('A check-in for this member was already registered a moment ago.'));

    expect($this->card->refresh()->strips_remaining)->toBe(4);
});

test('the code endpoint returns a code that resolves to the member', function () {
    $response = $this->getJson('/api/v1/check-in-code')
        ->assertOk()
        ->assertJsonStructure(['code', 'expires_in', 'image_url']);

    expect($this->codes->memberFrom($response->json('code'))->id)->toBe($this->member->id)
        ->and($response->json('expires_in'))->toBeGreaterThan(0)
        ->and($response->json('image_url'))->toBe(route('check-ins.code-image', ['code' => $response->json('code')]));
});

test('the code image renders a PNG for a live member code and 404s otherwise', function () {
    $response = $this->get(route('check-ins.code-image', ['code' => $this->codes->forMember($this->member)]))
        ->assertOk()
        ->assertHeader('Content-Type', 'image/png');

    expect(mb_substr($response->getContent(), 1, 3))->toBe('PNG');

    $this->get(route('check-ins.code-image', ['code' => 'M1.ZZZ.0000000000000000']))->assertNotFound();
    $this->get(route('check-ins.code-image', ['code' => $this->codes->forClub($this->club)]))->assertNotFound();
});
