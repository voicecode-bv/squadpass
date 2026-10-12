<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\MembershipStatus;
use App\Enums\TicketCategory;
use App\Enums\TicketPriority;
use App\Enums\TicketStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\ClubResource;
use App\Http\Resources\Api\V1\TicketResource;
use App\Models\Club;
use App\Models\Ticket;
use App\Models\TicketMessage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class TicketController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        return TicketResource::collection(
            Ticket::query()
                ->where('user_id', $request->user()->id)
                ->with(['club', 'assignee'])
                ->latest('updated_at')
                ->get()
        );
    }

    /**
     * The choices the app must offer when opening a ticket: the member's own
     * clubs plus the categories and priorities, already labelled, so the app
     * never has to mirror these enums itself.
     */
    public function options(Request $request): JsonResponse
    {
        return response()->json([
            'clubs' => ClubResource::collection(
                $request->user()->memberClubs()
                    ->wherePivot('status', MembershipStatus::Active->value)
                    ->orderBy('name')
                    ->get()
            ),
            'categories' => $this->choices(TicketCategory::cases()),
            'priorities' => $this->choices(TicketPriority::cases()),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $memberClubIds = $request->user()->memberships()
            ->where('status', MembershipStatus::Active)
            ->pluck('club_id');

        $validated = $request->validate([
            'club_id' => ['required', 'integer', Rule::in($memberClubIds)],
            'category' => ['required', Rule::enum(TicketCategory::class)],
            'priority' => ['required', Rule::enum(TicketPriority::class)],
            'subject' => ['required', 'string', 'max:255'],
            'message' => ['required', 'string', 'max:5000'],
        ]);

        $club = Club::query()->findOrFail($validated['club_id']);

        $ticket = DB::transaction(function () use ($request, $club, $validated): Ticket {
            $ticket = Ticket::create([
                'club_id' => $club->id,
                'user_id' => $request->user()->id,
                'number' => $club->allocateTicketNumber(),
                'subject' => $validated['subject'],
                'category' => $validated['category'],
                'status' => TicketStatus::Open,
                'priority' => $validated['priority'],
            ]);

            TicketMessage::create([
                'ticket_id' => $ticket->id,
                'user_id' => $request->user()->id,
                'body' => $validated['message'],
            ]);

            return $ticket;
        });

        return (new TicketResource($this->loadForShow($ticket)))->response()->setStatusCode(201);
    }

    public function show(Request $request, int $ticketId): TicketResource
    {
        $ticket = Ticket::query()
            ->where('user_id', $request->user()->id)
            ->findOrFail($ticketId);

        return new TicketResource($this->loadForShow($ticket));
    }

    /**
     * @param  list<TicketCategory|TicketPriority>  $cases
     * @return list<array{value: string, label: string}>
     */
    protected function choices(array $cases): array
    {
        return array_map(fn (TicketCategory|TicketPriority $case): array => [
            'value' => $case->value,
            'label' => $case->label(),
        ], $cases);
    }

    protected function loadForShow(Ticket $ticket): Ticket
    {
        $ticket->load(['club', 'assignee', 'messages.user']);
        $ticket->messages->each->setRelation('ticket', $ticket);

        return $ticket;
    }
}
