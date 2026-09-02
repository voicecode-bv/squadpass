<?php

use App\Enums\StaffRole;
use App\Enums\TicketPriority;
use App\Enums\TicketStatus;
use App\Livewire\Tickets\Index;
use App\Livewire\Tickets\Show;
use App\Models\Club;
use App\Models\Ticket;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    $this->club = Club::factory()->create();
    $this->owner = User::factory()->create();
    $this->club->addStaff($this->owner, StaffRole::Owner);
});

test('staff see only tickets of their club', function () {
    Ticket::factory()->for($this->club)->create(['subject' => 'Ticket van eigen club']);
    Ticket::factory()->create(['subject' => 'Ticket van andere club']);

    Livewire::actingAs($this->owner)
        ->test(Index::class, ['club' => $this->club])
        ->assertSee('Ticket van eigen club')
        ->assertDontSee('Ticket van andere club');
});

test('non-staff cannot open the club ticket overview', function () {
    $member = User::factory()->create();
    $this->club->addMember($member);

    $this->actingAs($member)
        ->get(route('clubs.tickets', $this->club))
        ->assertForbidden();
});

test('a ticket of another club gives a 404 on the club route', function () {
    $foreignTicket = Ticket::factory()->create();

    $this->actingAs($this->owner)
        ->get(route('clubs.tickets.show', [$this->club, $foreignTicket]))
        ->assertNotFound();
});

test('a staff reply assigns the ticket and moves it to in progress', function () {
    $ticket = Ticket::factory()->for($this->club)->create();

    Livewire::actingAs($this->owner)
        ->test(Show::class, ['club' => $this->club, 'ticket' => $ticket])
        ->set('reply', 'We pakken dit op.')
        ->call('sendMessage');

    $ticket->refresh();

    expect($ticket->assigned_to)->toBe($this->owner->id)
        ->and($ticket->status)->toBe(TicketStatus::InProgress)
        ->and($ticket->messages()->count())->toBe(1);
});

test('staff can update status, priority and assignee', function () {
    $ticket = Ticket::factory()->for($this->club)->create();

    Livewire::actingAs($this->owner)
        ->test(Show::class, ['club' => $this->club, 'ticket' => $ticket])
        ->set('status', TicketStatus::Closed->value)
        ->set('priority', TicketPriority::High->value)
        ->set('assignedTo', (string) $this->owner->id);

    $ticket->refresh();

    expect($ticket->status)->toBe(TicketStatus::Closed)
        ->and($ticket->priority)->toBe(TicketPriority::High)
        ->and($ticket->assigned_to)->toBe($this->owner->id);
});

test('the assignee must be a staff member of the club', function () {
    $ticket = Ticket::factory()->for($this->club)->create();
    $outsider = User::factory()->create();

    Livewire::actingAs($this->owner)
        ->test(Show::class, ['club' => $this->club, 'ticket' => $ticket])
        ->set('assignedTo', (string) $outsider->id)
        ->assertStatus(422);

    expect($ticket->refresh()->assigned_to)->toBeNull();
});
