<?php

use App\Enums\StaffRole;
use App\Http\Middleware\EnsureNotImpersonating;
use App\Models\Club;
use App\Models\User;
use App\Services\Impersonation\Impersonation;
use Livewire\Livewire;

beforeEach(function () {
    $this->club = Club::factory()->create();
    $this->owner = User::factory()->create();
    $this->club->addStaff($this->owner, StaffRole::Owner);

    $this->member = User::factory()->create(['name' => 'Impersonated Lid']);
    $this->membership = $this->club->addMember($this->member);
});

test('an owner can view the app as one of their members', function () {
    $this->actingAs($this->owner)
        ->post(route('clubs.members.impersonate', [$this->club, $this->membership]))
        ->assertRedirect(route('dashboard'));

    expect(auth()->id())->toBe($this->member->id)
        ->and(session(Impersonation::SESSION_KEY))->toBe($this->owner->id);
});

test('the banner shows who is being impersonated and stopping restores the owner', function () {
    $this->actingAs($this->owner)
        ->post(route('clubs.members.impersonate', [$this->club, $this->membership]));

    $this->get(route('dashboard'))
        ->assertOk()
        ->assertSee(__('You are viewing the app as :name.', ['name' => 'Impersonated Lid']));

    $this->post(route('impersonate.stop'))
        ->assertRedirect(route('clubs.members.show', [$this->club, $this->membership]));

    expect(auth()->id())->toBe($this->owner->id)
        ->and(session()->has(Impersonation::SESSION_KEY))->toBeFalse();
});

test('a club admin cannot impersonate, only the owner can', function () {
    $admin = User::factory()->create();
    $this->club->addStaff($admin, StaffRole::Admin);

    $this->actingAs($admin)
        ->post(route('clubs.members.impersonate', [$this->club, $this->membership]))
        ->assertForbidden();

    expect(auth()->id())->toBe($admin->id);
});

test('an outsider cannot impersonate a member of another club', function () {
    $this->actingAs(User::factory()->create())
        ->post(route('clubs.members.impersonate', [$this->club, $this->membership]))
        ->assertForbidden();
});

test('a membership of another club is not found on this club route', function () {
    $otherClub = Club::factory()->create();
    $otherMembership = $otherClub->addMember(User::factory()->create());

    $this->actingAs($this->owner)
        ->post(route('clubs.members.impersonate', [$this->club, $otherMembership]))
        ->assertNotFound();
});

test('staff of the club and superadmins cannot be impersonated', function () {
    $coOwner = User::factory()->create();
    $this->club->addStaff($coOwner, StaffRole::Owner);
    $coOwnerMembership = $this->club->addMember($coOwner);

    $this->actingAs($this->owner)
        ->post(route('clubs.members.impersonate', [$this->club, $coOwnerMembership]))
        ->assertForbidden();

    $superadmin = User::factory()->create(['is_superadmin' => true]);
    $superadminMembership = $this->club->addMember($superadmin);

    $this->actingAs($this->owner)
        ->post(route('clubs.members.impersonate', [$this->club, $superadminMembership]))
        ->assertForbidden();
});

test('impersonation cannot be nested', function () {
    $otherMembership = $this->club->addMember(User::factory()->create());

    $this->actingAs($this->owner)
        ->post(route('clubs.members.impersonate', [$this->club, $this->membership]));

    $this->post(route('clubs.members.impersonate', [$this->club, $otherMembership]))
        ->assertForbidden();

    expect(auth()->id())->toBe($this->member->id);
});

test('account settings are blocked while impersonating', function () {
    $this->actingAs($this->owner)
        ->post(route('clubs.members.impersonate', [$this->club, $this->membership]));

    $this->get(route('profile.edit'))->assertForbidden();
    $this->get(route('security.edit'))->assertForbidden();
});

test('credential endpoints are blocked while impersonating', function () {
    $this->actingAs($this->owner)
        ->post(route('clubs.members.impersonate', [$this->club, $this->membership]));

    $this->delete('/user/two-factor-authentication')->assertForbidden();
    $this->post('/user/passkeys')->assertForbidden();
    $this->get(route('password.confirm'))->assertForbidden();
});

test('the impersonation guard is re-applied to livewire component updates', function () {
    expect(Livewire::getPersistentMiddleware())->toContain(EnsureNotImpersonating::class);
});

test('settings stay reachable when not impersonating', function () {
    $this->actingAs($this->member)
        ->get(route('profile.edit'))
        ->assertOk();
});

test('the member show page offers the impersonate action to owners only', function () {
    $admin = User::factory()->create();
    $this->club->addStaff($admin, StaffRole::Admin);

    $this->actingAs($this->owner)
        ->get(route('clubs.members.show', [$this->club, $this->membership]))
        ->assertOk()
        ->assertSee(__('View as member'));

    $this->actingAs($admin)
        ->get(route('clubs.members.show', [$this->club, $this->membership]))
        ->assertOk()
        ->assertDontSee(__('View as member'));
});

test('a superadmin can view the app as a club owner and return to the clubs overview', function () {
    $superadmin = User::factory()->superadmin()->create();

    $this->actingAs($superadmin)
        ->post(route('clubs.owners.impersonate', [$this->club, $this->owner]))
        ->assertRedirect(route('clubs.dashboard', $this->club));

    expect(auth()->id())->toBe($this->owner->id)
        ->and(session(Impersonation::SESSION_KEY))->toBe($superadmin->id);

    $this->post(route('impersonate.stop'))
        ->assertRedirect(route('clubs.index'));

    expect(auth()->id())->toBe($superadmin->id);
});

test('only superadmins can impersonate club owners', function () {
    $coOwner = User::factory()->create();
    $this->club->addStaff($coOwner, StaffRole::Owner);

    $this->actingAs($coOwner)
        ->post(route('clubs.owners.impersonate', [$this->club, $this->owner]))
        ->assertForbidden();

    expect(auth()->id())->toBe($coOwner->id);
});

test('a superadmin can only impersonate owners of the club in the route', function () {
    $superadmin = User::factory()->superadmin()->create();
    $admin = User::factory()->create();
    $this->club->addStaff($admin, StaffRole::Admin);

    $this->actingAs($superadmin)
        ->post(route('clubs.owners.impersonate', [$this->club, $admin]))
        ->assertNotFound();

    $this->post(route('clubs.owners.impersonate', [Club::factory()->create(), $this->owner]))
        ->assertNotFound();
});

test('a superadmin cannot impersonate an owner who is a superadmin', function () {
    $superadmin = User::factory()->superadmin()->create();
    $superadminOwner = User::factory()->superadmin()->create();
    $this->club->addStaff($superadminOwner, StaffRole::Owner);

    $this->actingAs($superadmin)
        ->post(route('clubs.owners.impersonate', [$this->club, $superadminOwner]))
        ->assertForbidden();
});

test('the clubs overview offers superadmins to view the app as each owner', function () {
    $this->owner->update(['name' => 'Clubeigenaar Kees']);

    $this->actingAs(User::factory()->superadmin()->create())
        ->get(route('clubs.index'))
        ->assertOk()
        ->assertSee(__('View as :name', ['name' => 'Clubeigenaar Kees']));

    $this->actingAs($this->owner)
        ->get(route('clubs.index'))
        ->assertOk()
        ->assertDontSee(__('View as :name', ['name' => 'Clubeigenaar Kees']));
});
