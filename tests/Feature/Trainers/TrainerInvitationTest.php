<?php

use App\Enums\StaffRole;
use App\Livewire\Invitations\Accept;
use App\Livewire\Trainers\Index;
use App\Mail\ClubInvitationMail;
use App\Models\Club;
use App\Models\ClubInvitation;
use App\Models\Trainer;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;

beforeEach(function () {
    Mail::fake();

    $this->club = Club::factory()->create();
    $this->staff = User::factory()->create();
    $this->club->addStaff($this->staff, StaffRole::Admin);
});

test('staff can invite a trainer by email', function () {
    Livewire::actingAs($this->staff)
        ->test(Index::class, ['club' => $this->club])
        ->set('email', 'trainer@example.com')
        ->call('inviteTrainer')
        ->assertHasNoErrors();

    $invitation = ClubInvitation::query()->firstOrFail();

    expect($invitation->email)->toBe('trainer@example.com')
        ->and($invitation->club_id)->toBe($this->club->id)
        ->and($invitation->isPending())->toBeTrue();

    Mail::assertSent(ClubInvitationMail::class, fn (ClubInvitationMail $mail) => $mail->hasTo('trainer@example.com'));
});

test('someone who is already a trainer cannot be invited again', function () {
    $trainer = Trainer::factory()->for($this->club)->create();

    Livewire::actingAs($this->staff)
        ->test(Index::class, ['club' => $this->club])
        ->set('email', $trainer->user->email)
        ->call('inviteTrainer')
        ->assertHasErrors(['email']);

    expect(ClubInvitation::query()->count())->toBe(0);
    Mail::assertNothingSent();
});

test('re-inviting refreshes the token and resends the email', function () {
    $invitation = ClubInvitation::factory()->expired()->create([
        'club_id' => $this->club->id,
        'email' => 'trainer@example.com',
    ]);
    $oldToken = $invitation->token;

    Livewire::actingAs($this->staff)
        ->test(Index::class, ['club' => $this->club])
        ->call('resendInvitation', $invitation->id);

    $invitation->refresh();

    expect($invitation->token)->not->toBe($oldToken)
        ->and($invitation->isPending())->toBeTrue()
        ->and(ClubInvitation::query()->count())->toBe(1);

    Mail::assertSent(ClubInvitationMail::class);
});

test('a guest sees login and register options on the accept page', function () {
    $invitation = ClubInvitation::factory()->create(['club_id' => $this->club->id]);

    $this->get(route('invitations.accept', $invitation->token))
        ->assertOk()
        ->assertSee(__('Log in'))
        ->assertSee(__('Create an account'))
        ->assertSee($this->club->name);
});

test('an authenticated user can accept an invitation', function () {
    $user = User::factory()->create(['email' => 'trainer@example.com']);
    $invitation = ClubInvitation::factory()->create([
        'club_id' => $this->club->id,
        'email' => 'trainer@example.com',
    ]);

    Livewire::actingAs($user)
        ->test(Accept::class, ['token' => $invitation->token])
        ->call('accept')
        ->assertRedirect(route('dashboard'));

    expect(Trainer::query()->where('club_id', $this->club->id)->where('user_id', $user->id)->exists())->toBeTrue()
        ->and($invitation->refresh()->isAccepted())->toBeTrue();
});

test('the invitation link also works for an account with a different email', function () {
    $user = User::factory()->create(['email' => 'anders@example.com']);
    $invitation = ClubInvitation::factory()->create([
        'club_id' => $this->club->id,
        'email' => 'trainer@example.com',
    ]);

    Livewire::actingAs($user)
        ->test(Accept::class, ['token' => $invitation->token])
        ->call('accept');

    expect(Trainer::query()->where('user_id', $user->id)->exists())->toBeTrue();
});

test('an expired invitation cannot be accepted', function () {
    $user = User::factory()->create();
    $invitation = ClubInvitation::factory()->expired()->create(['club_id' => $this->club->id]);

    Livewire::actingAs($user)
        ->test(Accept::class, ['token' => $invitation->token])
        ->call('accept');

    expect(Trainer::query()->count())->toBe(0)
        ->and($invitation->refresh()->isAccepted())->toBeFalse();
});

test('an accepted invitation cannot be used twice', function () {
    $first = User::factory()->create();
    $second = User::factory()->create();
    $invitation = ClubInvitation::factory()->create(['club_id' => $this->club->id]);

    Livewire::actingAs($first)
        ->test(Accept::class, ['token' => $invitation->token])
        ->call('accept');

    Livewire::actingAs($second)
        ->test(Accept::class, ['token' => $invitation->token])
        ->call('accept');

    expect(Trainer::query()->count())->toBe(1)
        ->and(Trainer::query()->firstOrFail()->user_id)->toBe($first->id);
});

test('a revoked invitation gives a 404 on the accept page', function () {
    $invitation = ClubInvitation::factory()->create(['club_id' => $this->club->id]);
    $token = $invitation->token;

    Livewire::actingAs($this->staff)
        ->test(Index::class, ['club' => $this->club])
        ->call('revokeInvitation', $invitation->id);

    expect(ClubInvitation::query()->count())->toBe(0);

    $this->get(route('invitations.accept', $token))->assertNotFound();
});

test('a trainer can work for multiple clubs', function () {
    $user = User::factory()->create();
    $otherClub = Club::factory()->create();

    $first = ClubInvitation::factory()->create(['club_id' => $this->club->id]);
    $second = ClubInvitation::factory()->create(['club_id' => $otherClub->id]);

    Livewire::actingAs($user)->test(Accept::class, ['token' => $first->token])->call('accept');
    Livewire::actingAs($user)->test(Accept::class, ['token' => $second->token])->call('accept');

    expect(Trainer::query()->where('user_id', $user->id)->count())->toBe(2);
});

test('after logging in via the accept page the user returns to the invitation', function () {
    $user = User::factory()->create(['email' => 'trainer@example.com', 'password' => 'password']);
    $invitation = ClubInvitation::factory()->create([
        'club_id' => $this->club->id,
        'email' => 'trainer@example.com',
    ]);

    Livewire::test(Accept::class, ['token' => $invitation->token])
        ->call('goToLogin')
        ->assertRedirect(route('login'));

    $this->post(route('login.store'), [
        'email' => 'trainer@example.com',
        'password' => 'password',
    ])->assertRedirect(route('invitations.accept', $invitation->token));
});

test('non-staff cannot invite trainers', function () {
    Livewire::actingAs(User::factory()->create())
        ->test(Index::class, ['club' => $this->club])
        ->assertForbidden();
});
