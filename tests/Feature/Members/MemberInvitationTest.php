<?php

use App\Enums\ClubInvitationRole;
use App\Enums\StaffRole;
use App\Livewire\Invitations\Accept;
use App\Livewire\Members\Index;
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

test('adding an unknown email sends a member invitation', function () {
    Livewire::actingAs($this->staff)
        ->test(Index::class, ['club' => $this->club])
        ->set('email', 'nieuw@example.com')
        ->call('addMember')
        ->assertHasNoErrors();

    $invitation = ClubInvitation::query()->firstOrFail();

    expect($invitation->role)->toBe(ClubInvitationRole::Member)
        ->and($invitation->email)->toBe('nieuw@example.com')
        ->and($this->club->memberships()->count())->toBe(0);

    Mail::assertSent(ClubInvitationMail::class, fn (ClubInvitationMail $mail) => $mail->hasTo('nieuw@example.com'));
});

test('adding an existing account still links it directly', function () {
    $user = User::factory()->create(['email' => 'lid@example.com']);

    Livewire::actingAs($this->staff)
        ->test(Index::class, ['club' => $this->club])
        ->set('email', 'lid@example.com')
        ->call('addMember')
        ->assertHasNoErrors();

    expect($user->isMemberOf($this->club))->toBeTrue()
        ->and(ClubInvitation::query()->count())->toBe(0);

    Mail::assertNothingSent();
});

test('an existing member cannot be added again', function () {
    $user = User::factory()->create(['email' => 'lid@example.com']);
    $this->club->addMember($user);

    Livewire::actingAs($this->staff)
        ->test(Index::class, ['club' => $this->club])
        ->set('email', 'lid@example.com')
        ->call('addMember')
        ->assertHasErrors(['email']);
});

test('accepting a member invitation creates an active membership', function () {
    $invitation = ClubInvitation::factory()->member()->create(['club_id' => $this->club->id]);
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test(Accept::class, ['token' => $invitation->token])
        ->call('accept')
        ->assertRedirect(route('dashboard'));

    expect($user->isMemberOf($this->club))->toBeTrue()
        ->and($invitation->refresh()->isAccepted())->toBeTrue();
});

test('a member invitation does not make someone a trainer', function () {
    $invitation = ClubInvitation::factory()->member()->create(['club_id' => $this->club->id]);
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test(Accept::class, ['token' => $invitation->token])
        ->call('accept');

    expect(Trainer::query()->count())->toBe(0);
});

test('trainer and member invitations for the same email can coexist', function () {
    Livewire::actingAs($this->staff)
        ->test(Index::class, ['club' => $this->club])
        ->set('email', 'beide@example.com')
        ->call('addMember');

    Livewire::actingAs($this->staff)
        ->test(App\Livewire\Trainers\Index::class, ['club' => $this->club])
        ->set('email', 'beide@example.com')
        ->call('inviteTrainer')
        ->assertHasNoErrors();

    expect(ClubInvitation::query()->count())->toBe(2);
});

test('staff can resend and revoke member invitations', function () {
    $invitation = ClubInvitation::factory()->member()->expired()->create(['club_id' => $this->club->id]);
    $oldToken = $invitation->token;

    Livewire::actingAs($this->staff)
        ->test(Index::class, ['club' => $this->club])
        ->call('resendInvitation', $invitation->id);

    expect($invitation->refresh()->token)->not->toBe($oldToken)
        ->and($invitation->isPending())->toBeTrue();

    Livewire::actingAs($this->staff)
        ->test(Index::class, ['club' => $this->club])
        ->call('revokeInvitation', $invitation->id);

    expect(ClubInvitation::query()->count())->toBe(0);
});
