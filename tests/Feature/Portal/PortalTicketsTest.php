<?php

use App\Enums\TicketStatus;
use App\Livewire\Portal\Tickets;
use App\Livewire\Portal\TicketShow;
use App\Models\Club;
use App\Models\Ticket;
use App\Models\TicketMessage;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Livewire\Livewire;

beforeEach(function () {
    $this->club = Club::factory()->create();
    $this->member = User::factory()->create();
    $this->club->addMember($this->member);
});

test('a member can create a ticket with a sequential number and first message', function () {
    Livewire::actingAs($this->member)
        ->test(Tickets::class)
        ->set('club_id', (string) $this->club->id)
        ->set('category', 'billing')
        ->set('priority', 'high')
        ->set('subject', 'Kan mijn factuur niet downloaden')
        ->set('message', 'De pdf blijft leeg.')
        ->call('createTicket')
        ->assertRedirect();

    $ticket = Ticket::query()->where('user_id', $this->member->id)->firstOrFail();

    expect($ticket->number)->toBe(1001)
        ->and($ticket->status)->toBe(TicketStatus::Open)
        ->and($ticket->reference())->toBe('TKT-1001')
        ->and($ticket->messages()->count())->toBe(1)
        ->and($ticket->messages()->first()->body)->toBe('De pdf blijft leeg.');
});

test('the only member club is preselected so a ticket can be created directly', function () {
    Livewire::actingAs($this->member)
        ->test(Tickets::class)
        ->set('subject', 'Vraag over mijn les')
        ->set('message', 'Wanneer begint de les?')
        ->call('createTicket')
        ->assertHasNoErrors()
        ->assertRedirect();

    expect(Ticket::query()->where('user_id', $this->member->id)->where('club_id', $this->club->id)->exists())->toBeTrue();
});

test('a member cannot create a ticket for a club they are not a member of', function () {
    $otherClub = Club::factory()->create();

    Livewire::actingAs($this->member)
        ->test(Tickets::class)
        ->set('club_id', (string) $otherClub->id)
        ->set('subject', 'Vraag')
        ->set('message', 'Tekst')
        ->call('createTicket')
        ->assertHasErrors(['club_id']);

    expect(Ticket::query()->count())->toBe(0);
});

test('a member sees only their own tickets', function () {
    Ticket::factory()->for($this->club)->create(['user_id' => $this->member->id, 'subject' => 'Eigen ticket']);
    Ticket::factory()->for($this->club)->create(['subject' => 'Andermans ticket']);

    Livewire::actingAs($this->member)
        ->test(Tickets::class)
        ->assertSee('Eigen ticket')
        ->assertDontSee('Andermans ticket');
});

test('a member can reply on their own ticket', function () {
    $ticket = Ticket::factory()->for($this->club)->create(['user_id' => $this->member->id]);

    Livewire::actingAs($this->member)
        ->test(TicketShow::class, ['ticket' => $ticket])
        ->set('reply', 'Is er al nieuws?')
        ->call('sendMessage');

    expect(TicketMessage::query()->where('ticket_id', $ticket->id)->where('body', 'Is er al nieuws?')->exists())->toBeTrue();
});

test('a member cannot reply on a closed ticket', function () {
    $ticket = Ticket::factory()->for($this->club)->closed()->create(['user_id' => $this->member->id]);

    Livewire::actingAs($this->member)
        ->test(TicketShow::class, ['ticket' => $ticket])
        ->set('reply', 'Nog een vraag')
        ->call('sendMessage');

    expect(TicketMessage::query()->where('ticket_id', $ticket->id)->count())->toBe(0);
});

test('a member cannot view someone elses ticket', function () {
    $ticket = Ticket::factory()->for($this->club)->create();

    expect(fn () => Livewire::actingAs($this->member)
        ->test(TicketShow::class, ['ticket' => $ticket])
    )->toThrow(ModelNotFoundException::class);
});
