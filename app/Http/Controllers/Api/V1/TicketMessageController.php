<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\TicketMessageResource;
use App\Models\Ticket;
use App\Models\TicketMessage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TicketMessageController extends Controller
{
    public function store(Request $request, int $ticketId): JsonResponse
    {
        $ticket = Ticket::query()
            ->where('user_id', $request->user()->id)
            ->findOrFail($ticketId);

        if ($ticket->isClosed()) {
            return response()->json(['message' => __('This ticket is closed.')], 422);
        }

        $validated = $request->validate([
            'body' => ['required', 'string', 'max:5000'],
        ]);

        $message = TicketMessage::create([
            'ticket_id' => $ticket->id,
            'user_id' => $request->user()->id,
            'body' => $validated['body'],
        ]);

        $ticket->touch();

        $message->setRelation('ticket', $ticket);

        return (new TicketMessageResource($message->load('user')))->response()->setStatusCode(201);
    }
}
