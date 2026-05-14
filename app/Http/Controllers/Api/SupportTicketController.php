<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Support\BulkAssignTicketsRequest;
use App\Http\Requests\Support\BulkUpdatePriorityRequest;
use App\Http\Requests\Support\BulkUpdateStatusRequest;
use App\Http\Requests\Support\CreateTicketRequest;
use App\Http\Requests\Support\ForwardTicketRequest;
use App\Http\Requests\Support\ListAttachmentsRequest;
use App\Http\Requests\Support\ListTicketsRequest;
use App\Http\Requests\Support\ReplyToTicketRequest;
use App\Support\Auth\CurrentUserResolver;
use App\Support\Services\SupportTicketService;
use Illuminate\Http\JsonResponse;

final class SupportTicketController extends Controller
{
    public function __construct(
        private readonly SupportTicketService $supportTickets,
        private readonly CurrentUserResolver $currentUserResolver,
    ) {}

    /**
     * GET /api/support/tickets
     *
     * Returns a filtered, sorted, paginated ticket list for the support grid.
     *
     * @OA\Get(
     *   path="/api/support/tickets",
     *   tags={"Support Tickets"},
     *   summary="List support tickets"
     * )
     */
    public function index(ListTicketsRequest $request): JsonResponse
    {
        return response()->json($this->supportTickets->list($request->validated())->toArray());
    }

    /**
     * POST /api/support/tickets
     *
     * Creates a support ticket with an initial message and optional attachment references.
     */
    public function store(CreateTicketRequest $request): JsonResponse
    {
        return response()->json($this->supportTickets->create(
            payload: $request->validated(),
            currentUser: $this->currentUserResolver->fromRequestOrFallback($request),
        ), 201);
    }

    /**
     * GET /api/support/tickets/{id}
     *
     * Returns one ticket with SLA fields, activity history, and related items for the detail pane.
     *
     * @OA\Get(
     *   path="/api/support/tickets/{id}",
     *   tags={"Support Tickets"},
     *   summary="Get support ticket detail"
     * )
     */
    public function show(string $id): JsonResponse
    {
        $ticket = $this->supportTickets->detail($id);

        return $ticket === null
            ? response()->json(['message' => 'Ticket not found.'], 404)
            : response()->json($ticket->toArray());
    }

    /**
     * GET /api/support/tickets/{id}/linked-tasks
     *
     * Returns linked internal tasks created from this ticket.
     */
    public function linkedTasks(string $id): JsonResponse
    {
        $tasks = $this->supportTickets->linkedTasks($id);

        return $tasks === null
            ? response()->json(['message' => 'Ticket not found.'], 404)
            : response()->json($tasks);
    }

    /**
     * GET /api/support/tickets/{id}/attachments
     *
     * Returns attachment references scoped to the selected ticket.
     */
    public function attachments(string $id, ListAttachmentsRequest $request): JsonResponse
    {
        $attachments = $this->supportTickets->ticketAttachments($id, $request->validated());

        return $attachments === null
            ? response()->json(['message' => 'Ticket not found.'], 404)
            : response()->json($attachments);
    }

    /**
     * POST /api/support/tickets/bulk/assign
     *
     * Assigns selected tickets to an agent and returns how many known tickets were updated.
     *
     * @OA\Post(
     *   path="/api/support/tickets/bulk/assign",
     *   tags={"Support Tickets"},
     *   summary="Bulk assign support tickets"
     * )
     */
    public function bulkAssign(BulkAssignTicketsRequest $request): JsonResponse
    {
        $data = $request->validated();

        return response()->json($this->supportTickets->assign(
            ticketIds: $data['ticketIds'],
            agent: $data['agent'],
            currentUser: $this->currentUserResolver->fromRequestOrFallback($request),
        ));
    }

    /**
     * POST /api/support/tickets/bulk/status
     *
     * Updates status for selected tickets. Status is validated against TicketStatus enum.
     *
     * @OA\Post(
     *   path="/api/support/tickets/bulk/status",
     *   tags={"Support Tickets"},
     *   summary="Bulk update support ticket status"
     * )
     */
    public function bulkStatus(BulkUpdateStatusRequest $request): JsonResponse
    {
        $data = $request->validated();

        return response()->json($this->supportTickets->updateStatus(
            ticketIds: $data['ticketIds'],
            status: $data['status'],
            currentUser: $this->currentUserResolver->fromRequestOrFallback($request),
        ));
    }

    /**
     * POST /api/support/tickets/bulk/priority
     *
     * Updates priority for selected tickets. Priority is validated against TicketPriority enum.
     *
     * @OA\Post(
     *   path="/api/support/tickets/bulk/priority",
     *   tags={"Support Tickets"},
     *   summary="Bulk update support ticket priority"
     * )
     */
    public function bulkPriority(BulkUpdatePriorityRequest $request): JsonResponse
    {
        $data = $request->validated();

        return response()->json($this->supportTickets->updatePriority(
            ticketIds: $data['ticketIds'],
            priority: $data['priority'],
            currentUser: $this->currentUserResolver->fromRequestOrFallback($request),
        ));
    }

    /**
     * POST /api/support/tickets/{id}/reply
     *
     * Adds a public reply or internal note and returns the new activity id.
     *
     * @OA\Post(
     *   path="/api/support/tickets/{id}/reply",
     *   tags={"Support Tickets"},
     *   summary="Reply to a support ticket"
     * )
     */
    public function reply(string $id, ReplyToTicketRequest $request): JsonResponse
    {
        $data = $request->validated();
        $result = $this->supportTickets->reply(
            id: $id,
            message: $data['message'],
            isInternalNote: $data['isInternalNote'],
            attachmentIds: $data['attachmentIds'] ?? [],
            parentActivityId: $data['parentActivityId'] ?? null,
            mentions: $data['mentions'] ?? [],
            currentUser: $this->currentUserResolver->fromRequestOrFallback($request),
        );

        return $result === null
            ? response()->json(['message' => 'Ticket not found.'], 404)
            : response()->json($result);
    }

    /**
     * POST /api/support/tickets/{id}/forward
     *
     * Records a single-ticket forward action as an internal activity so the detail conversation reload can show it.
     *
     * @OA\Post(
     *   path="/api/support/tickets/{id}/forward",
     *   tags={"Support Tickets"},
     *   summary="Forward a support ticket"
     * )
     */
    public function forward(string $id, ForwardTicketRequest $request): JsonResponse
    {
        $result = $this->supportTickets->forward(
            id: $id,
            payload: $request->validated(),
            currentUser: $this->currentUserResolver->fromRequestOrFallback($request),
        );

        return $result === null
            ? response()->json(['message' => 'Ticket not found.'], 404)
            : response()->json($result);
    }
}
