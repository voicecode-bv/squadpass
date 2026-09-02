<?php

use App\Models\Club;
use App\Models\Ticket;
use App\Models\TicketMessage;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->club = Club::factory()->create();
    $this->member = User::factory()->create();
    $this->club->addMember($this->member);

    Sanctum::actingAs($this->member);
});

test('tickets are scoped to the member', function () {
    Ticket::factory()->for($this->club)->create(['user_id' => $this->member->id, 'subject' => 'Eigen ticket']);
    Ticket::factory()->for($this->club)->create();

    $this->getJson('/api/v1/tickets')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.subject', 'Eigen ticket');
});

test('a member can create a ticket via the api', function () {
    $this->postJson('/api/v1/tickets', [
        'club_id' => $this->club->id,
        'category' => 'billing',
        'priority' => 'high',
        'subject' => 'Factuur klopt niet',
        'message' => 'Het bedrag wijkt af.',
    ])
        ->assertCreated()
        ->assertJsonPath('data.reference', 'TKT-1001')
        ->assertJsonPath('data.messages.0.body', 'Het bedrag wijkt af.');
});

test('a member cannot create a ticket for a club they are not a member of', function () {
    $otherClub = Club::factory()->create();

    $this->postJson('/api/v1/tickets', [
        'club_id' => $otherClub->id,
        'category' => 'other',
        'priority' => 'normal',
        'subject' => 'Vraag',
        'message' => 'Tekst',
    ])->assertStatus(422)->assertJsonValidationErrors('club_id');
});

test('viewing someone elses ticket gives 404', function () {
    $ticket = Ticket::factory()->for($this->club)->create();

    $this->getJson('/api/v1/tickets/'.$ticket->id)->assertNotFound();
});

test('a member can reply on their own open ticket but not on a closed one', function () {
    $open = Ticket::factory()->for($this->club)->create(['user_id' => $this->member->id]);
    $closed = Ticket::factory()->for($this->club)->closed()->create(['user_id' => $this->member->id]);

    $this->postJson('/api/v1/tickets/'.$open->id.'/messages', ['body' => 'Is er nieuws?'])
        ->assertCreated()
        ->assertJsonPath('data.from_member', true);

    $this->postJson('/api/v1/tickets/'.$closed->id.'/messages', ['body' => 'Nog iets'])
        ->assertStatus(422);

    expect(TicketMessage::query()->where('ticket_id', $closed->id)->count())->toBe(0);
});
