<?php

use App\Actions\Invitations\AcceptClubInvitation;
use App\Enums\ClubInvitationRole;
use App\Enums\StaffPermission;
use App\Enums\StaffRole;
use App\Livewire\Clubs\Staff;
use App\Livewire\GlobalSearch;
use App\Livewire\Members\Show as MemberShow;
use App\Mail\ClubInvitationMail;
use App\Models\Club;
use App\Models\ClubInvitation;
use App\Models\Ticket;
use App\Models\TicketMessage;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;

beforeEach(function () {
    $this->club = Club::factory()->create();
    $this->owner = User::factory()->create();
    $this->club->addStaff($this->owner, StaffRole::Owner);
});

function staffWith(Club $club, StaffPermission ...$permissions): User
{
    $staff = User::factory()->create();
    $club->addStaff($staff, StaffRole::Admin, $permissions);

    return $staff;
}

test('the owner invites a staff member with the ticked permissions', function () {
    Mail::fake();

    Livewire::actingAs($this->owner)
        ->test(Staff::class, ['club' => $this->club])
        ->call('startInvite')
        ->set('email', 'jan@example.com')
        ->call('applyPreset', 'finance')
        ->call('save')
        ->assertHasNoErrors();

    $invitation = ClubInvitation::query()->sole();

    expect($invitation->role)->toBe(ClubInvitationRole::Staff)
        ->and($invitation->permissions)->toBe(['members', 'billing']);
    Mail::assertSent(ClubInvitationMail::class, fn (ClubInvitationMail $mail) => $mail->hasTo('jan@example.com'));
});

test('a staff member needs at least one area', function () {
    Mail::fake();

    Livewire::actingAs($this->owner)
        ->test(Staff::class, ['club' => $this->club])
        ->set('email', 'jan@example.com')
        ->set('selectedPermissions', [])
        ->call('save')
        ->assertHasErrors(['selectedPermissions']);

    expect(ClubInvitation::query()->exists())->toBeFalse();
    Mail::assertNothingSent();
});

test('accepting a staff invitation gives exactly the invited permissions', function () {
    $invitation = ClubInvitation::factory()->create([
        'club_id' => $this->club->id,
        'role' => ClubInvitationRole::Staff,
        'permissions' => ['check_ins', 'lessons'],
    ]);
    $colleague = User::factory()->create();

    app(AcceptClubInvitation::class)->handle($invitation, $colleague);

    expect($colleague->staffAccessTo($this->club))->toBe([
        'role' => StaffRole::Admin,
        'permissions' => [StaffPermission::CheckIns, StaffPermission::Lessons],
    ])->and($colleague->isMemberOf($this->club))->toBeFalse();
});

test('accepting a staff invitation never downgrades an owner', function () {
    $invitation = ClubInvitation::factory()->create([
        'club_id' => $this->club->id,
        'email' => $this->owner->email,
        'role' => ClubInvitationRole::Staff,
        'permissions' => ['tickets'],
    ]);

    app(AcceptClubInvitation::class)->handle($invitation, $this->owner);

    expect($this->owner->fresh()->isStaffOf($this->club, StaffRole::Owner))->toBeTrue();
});

test('a staff member only opens the areas they were given', function (string $route, int $status) {
    $staff = staffWith($this->club, StaffPermission::Members, StaffPermission::CheckIns);

    $this->actingAs($staff)->get(route($route, $this->club))->assertStatus($status);
})->with([
    'members' => ['clubs.members', 200],
    'check-in desk' => ['clubs.check-ins', 200],
    'invoices' => ['clubs.invoices', 403],
    'lessons' => ['clubs.lessons', 403],
    'tickets' => ['clubs.tickets', 403],
    'forms' => ['clubs.forms', 403],
    'trainers' => ['clubs.trainers', 403],
    'club details' => ['clubs.settings.club', 403],
    'plan' => ['clubs.plan', 403],
    'staff' => ['clubs.staff', 403],
]);

test('the sidebar only lists the areas a staff member may use', function () {
    $staff = staffWith($this->club, StaffPermission::Members);

    $this->actingAs($staff)->get(route('clubs.members', $this->club))
        ->assertOk()
        ->assertSeeHtml('href="'.route('clubs.members', $this->club).'"')
        ->assertDontSeeHtml('href="'.route('clubs.check-ins', $this->club).'"')
        ->assertDontSeeHtml('href="'.route('clubs.invoices', $this->club).'"')
        ->assertDontSeeHtml('href="'.route('clubs.settings', $this->club).'"');
});

test('tickets sit under the check-in desk in the members group', function () {
    $content = $this->actingAs($this->owner)->get(route('clubs.members', $this->club))->assertOk()->getContent();

    expect(strpos($content, 'href="'.route('clubs.check-ins', $this->club).'"'))
        ->toBeLessThan(strpos($content, 'href="'.route('clubs.tickets', $this->club).'"'))
        ->and(strpos($content, 'href="'.route('clubs.tickets', $this->club).'"'))
        ->toBeLessThan(strpos($content, 'data-sidebar-group="club-lessons"'));
});

test('member details hide finances and form answers without those permissions', function () {
    $staff = staffWith($this->club, StaffPermission::Members);
    $membership = $this->club->addMember(User::factory()->create());

    Livewire::actingAs($staff)
        ->withQueryParams(['tab' => 'payments'])
        ->test(MemberShow::class, ['club' => $this->club, 'membership' => $membership])
        ->assertSet('tab', 'membership')
        ->assertSet('visibleTabs', ['membership', 'credits', 'check-ins']);
});

test('the club dashboard hides revenue without the billing permission', function () {
    $staff = staffWith($this->club, StaffPermission::Members);

    $this->actingAs($staff)->get(route('clubs.dashboard', $this->club))
        ->assertOk()
        ->assertDontSee(__('Revenue per month'));
});

test('search only finds members in clubs where the staff member may see members', function () {
    $staff = staffWith($this->club, StaffPermission::Lessons);
    $this->club->addMember(User::factory()->create(['first_name' => 'Zoekbare', 'last_name' => 'Persoon', 'name' => 'Zoekbare Persoon']));

    Livewire::actingAs($staff)
        ->test(GlobalSearch::class)
        ->set('query', 'Zoekbare')
        ->assertDontSee('Zoekbare Persoon');
});

test('unread tickets only reach staff who handle support', function () {
    $member = User::factory()->create();
    $ticket = Ticket::factory()->create(['club_id' => $this->club->id, 'user_id' => $member->id]);
    TicketMessage::factory()->create(['ticket_id' => $ticket->id, 'user_id' => $member->id]);

    expect(Ticket::query()->unreadFor(staffWith($this->club, StaffPermission::Tickets))->exists())->toBeTrue()
        ->and(Ticket::query()->unreadFor(staffWith($this->club, StaffPermission::Members))->exists())->toBeFalse();
});

test('the owner changes permissions and removes staff members, but never an owner', function () {
    $staff = staffWith($this->club, StaffPermission::Members);
    $component = Livewire::actingAs($this->owner)->test(Staff::class, ['club' => $this->club]);

    $component->call('editStaff', $staff->id)
        ->set('selectedPermissions', ['tickets'])
        ->call('save')
        ->assertHasNoErrors();

    expect($staff->fresh()->staffAccessTo($this->club)['permissions'])->toBe([StaffPermission::Tickets]);

    $component->call('removeStaff', $staff->id);

    expect($staff->fresh()->isStaffOf($this->club))->toBeFalse();

    $component->call('removeStaff', $this->owner->id)->assertNotFound();
});

test('staff members cannot manage other staff', function () {
    $staff = staffWith(...[$this->club, ...StaffPermission::cases()]);

    Livewire::actingAs($staff)->test(Staff::class, ['club' => $this->club])->assertForbidden();
});
