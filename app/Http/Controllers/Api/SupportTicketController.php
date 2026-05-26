<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Support\BulkAssignTicketsRequest;
use App\Http\Requests\Support\BulkUpdatePriorityRequest;
use App\Http\Requests\Support\BulkUpdateStatusRequest;
use App\Http\Requests\Support\CreateTicketRequest;
use App\Http\Requests\Support\DispatchTicketNotificationsRequest;
use App\Http\Requests\Support\DownloadAllTicketAttachmentsRequest;
use App\Http\Requests\Support\ForwardTicketRequest;
use App\Http\Requests\Support\ListAttachmentsRequest;
use App\Http\Requests\Support\ListTicketsRequest;
use App\Http\Requests\Support\ReplyToTicketRequest;
use App\Http\Requests\Support\SendTicketEmailRequest;
use App\Models\SupportTicket;
use App\Models\User;
use App\Support\Auth\CurrentUser;
use App\Support\Auth\CurrentUserResolver;
use App\Support\Auth\SupportAccessResolver;
use App\Support\Http\ApiErrorResponse;
use App\Support\Services\SupportConversationService;
use App\Support\Services\SupportTicketService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

final class SupportTicketController extends Controller
{
    public function __construct(
        private readonly SupportTicketService $supportTickets,
        private readonly SupportConversationService $supportConversation,
        private readonly CurrentUserResolver $currentUserResolver,
        private readonly SupportAccessResolver $supportAccessResolver,
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
        $currentUser = $this->currentUserResolver->fromRequestOrFallback($request);
        Gate::forUser($this->policyUser($currentUser))->authorize('viewAny', SupportTicket::class);

        return response()->json($this->supportTickets->list($request->validated(), $currentUser)->toArray());
    }

    /**
     * POST /api/support/tickets
     *
     * Creates a support ticket with an initial message and optional attachment references.
     */
    public function store(CreateTicketRequest $request): JsonResponse
    {
        $currentUser = $this->currentUserResolver->fromRequestOrFallback($request);

        if (! $this->supportAccessResolver->canCreateTicket($currentUser, (string) $request->input('customer'))) {
            return ApiErrorResponse::forbidden(
                request: $request,
                message: 'Customer scope does not allow ticket creation for this account.',
            );
        }

        return response()->json($this->supportTickets->create(
            payload: $request->validated(),
            currentUser: $currentUser,
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
    public function show(Request $request, string $id): JsonResponse
    {
        $record = SupportTicket::query()->find($id);

        if ($record === null) {
            return ApiErrorResponse::notFound($request, 'Ticket not found.');
        }

        $currentUser = $this->currentUserResolver->fromRequestOrFallback($request);
        Gate::forUser($this->policyUser($currentUser))->authorize('view', $record);
        $ticket = $this->supportTickets->detail($id, $currentUser);

        return $ticket === null
            ? ApiErrorResponse::notFound($request, 'Ticket not found.')
            : response()->json($ticket->toArray());
    }

    /**
     * GET /api/support/tickets/{id}/linked-tasks
     *
     * Returns linked internal tasks created from this ticket.
     */
    public function linkedTasks(Request $request, string $id): JsonResponse
    {
        $record = SupportTicket::query()->find($id);

        if ($record === null) {
            return ApiErrorResponse::notFound($request, 'Ticket not found.');
        }

        $currentUser = $this->currentUserResolver->fromRequestOrFallback($request);
        Gate::forUser($this->policyUser($currentUser))->authorize('view', $record);
        $tasks = $this->supportTickets->linkedTasks($id);

        return $tasks === null
            ? ApiErrorResponse::notFound($request, 'Ticket not found.')
            : response()->json($tasks);
    }

    /**
     * GET /api/support/tickets/{id}/attachments
     *
     * Returns attachment references scoped to the selected ticket.
     */
    public function attachments(string $id, ListAttachmentsRequest $request): JsonResponse
    {
        $record = SupportTicket::query()->find($id);

        if ($record === null) {
            return ApiErrorResponse::notFound($request, 'Ticket not found.');
        }

        $currentUser = $this->currentUserResolver->fromRequestOrFallback($request);
        Gate::forUser($this->policyUser($currentUser))->authorize('view', $record);
        $attachments = $this->supportConversation->ticketAttachments($id, $request->validated());

        return $attachments === null
            ? ApiErrorResponse::notFound($request, 'Ticket not found.')
            : response()->json($attachments);
    }

    /**
     * GET /api/support/tickets/{id}/activities
     *
     * Returns conversation activities including attachments and email delivery metadata.
     */
    public function activities(Request $request, string $id): JsonResponse
    {
        $record = SupportTicket::query()->find($id);

        if ($record === null) {
            return ApiErrorResponse::notFound($request, 'Ticket not found.');
        }

        $currentUser = $this->currentUserResolver->fromRequestOrFallback($request);
        Gate::forUser($this->policyUser($currentUser))->authorize('view', $record);

        $activities = $this->supportConversation->activities($id, $currentUser);

        return $activities === null
            ? ApiErrorResponse::notFound($request, 'Ticket not found.')
            : response()->json($activities);
    }

    /**
     * POST /api/support/tickets/{id}/email-send
     *
     * Queues an outbound email and records delivery tracking metadata.
     */
    public function emailSend(string $id, SendTicketEmailRequest $request): JsonResponse
    {
        $record = SupportTicket::query()->find($id);

        if ($record === null) {
            return ApiErrorResponse::notFound($request, 'Ticket not found.');
        }

        $currentUser = $this->currentUserResolver->fromRequestOrFallback($request);
        Gate::forUser($this->policyUser($currentUser))->authorize('reply', $record);

        $result = $this->supportConversation->sendEmail($id, $request->validated(), $currentUser);

        return $result === null
            ? ApiErrorResponse::notFound($request, 'Ticket not found.')
            : response()->json($result);
    }

    /**
     * POST /api/support/tickets/{id}/attachments/download-all
     *
     * Returns a signed URL for downloading selected or all ticket attachments as a zip.
     */
    public function downloadAllAttachments(string $id, DownloadAllTicketAttachmentsRequest $request): JsonResponse
    {
        $record = SupportTicket::query()->find($id);

        if ($record === null) {
            return ApiErrorResponse::notFound($request, 'Ticket not found.');
        }

        $currentUser = $this->currentUserResolver->fromRequestOrFallback($request);
        Gate::forUser($this->policyUser($currentUser))->authorize('view', $record);

        $result = $this->supportConversation->downloadAllAttachmentBundle($id, $request->validated(), $currentUser);

        return $result === null
            ? ApiErrorResponse::notFound($request, 'No attachments available for export.')
            : response()->json($result);
    }

    /**
     * POST /api/support/tickets/{id}/notifications/dispatch
     *
     * Queues notification dispatch jobs for conversation events.
     */
    public function dispatchNotifications(string $id, DispatchTicketNotificationsRequest $request): JsonResponse
    {
        $record = SupportTicket::query()->find($id);

        if ($record === null) {
            return ApiErrorResponse::notFound($request, 'Ticket not found.');
        }

        $currentUser = $this->currentUserResolver->fromRequestOrFallback($request);
        $policyUser = $this->policyUser($currentUser);
        $eventTypes = array_values(array_unique($request->validated('eventTypes', [$request->validated('event')])));

        foreach ($eventTypes as $eventType) {
            match ($eventType) {
                'reply', 'email' => Gate::forUser($policyUser)->authorize('reply', $record),
                'forward' => Gate::forUser($policyUser)->authorize('forward', $record),
                'internal_mention' => Gate::forUser($policyUser)->authorize('internalNote', $record),
                default => Gate::forUser($policyUser)->authorize('view', $record),
            };
        }

        $result = $this->supportConversation->dispatchNotifications($id, [
            ...$request->validated(),
            'eventTypes' => $eventTypes,
        ]);

        return $result === null
            ? ApiErrorResponse::notFound($request, 'Ticket not found.')
            : response()->json($result);
    }

    public function notificationRecipients(Request $request, string $id): JsonResponse
    {
        $record = SupportTicket::query()->find($id);

        if ($record === null) {
            return ApiErrorResponse::notFound($request, 'Ticket not found.');
        }

        $currentUser = $this->currentUserResolver->fromRequestOrFallback($request);
        Gate::forUser($this->policyUser($currentUser))->authorize('view', $record);

        $result = $this->supportConversation->notificationRecipients(
            ticketId: $id,
            activityId: $request->query('activityId') !== null ? (string) $request->query('activityId') : null,
        );

        return $result === null
            ? ApiErrorResponse::notFound($request, 'Ticket not found.')
            : response()->json($result);
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
        $currentUser = $this->currentUserResolver->fromRequestOrFallback($request);
        $policyUser = $this->policyUser($currentUser);
        Gate::forUser($policyUser)->authorize('bulkUpdate', SupportTicket::class);

        $tickets = SupportTicket::query()->whereIn('id', array_unique($data['ticketIds']))->get();

        foreach ($tickets as $ticket) {
            Gate::forUser($policyUser)->authorize('assign', $ticket);
        }

        return response()->json($this->supportTickets->assign(
            ticketIds: $data['ticketIds'],
            agent: $data['agent'],
            currentUser: $currentUser,
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
        $currentUser = $this->currentUserResolver->fromRequestOrFallback($request);
        $policyUser = $this->policyUser($currentUser);
        Gate::forUser($policyUser)->authorize('bulkUpdate', SupportTicket::class);

        $tickets = SupportTicket::query()->whereIn('id', array_unique($data['ticketIds']))->get();

        foreach ($tickets as $ticket) {
            Gate::forUser($policyUser)->authorize('changeStatus', $ticket);
        }

        return response()->json($this->supportTickets->updateStatus(
            ticketIds: $data['ticketIds'],
            status: $data['status'],
            currentUser: $currentUser,
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
        $currentUser = $this->currentUserResolver->fromRequestOrFallback($request);
        $policyUser = $this->policyUser($currentUser);
        Gate::forUser($policyUser)->authorize('bulkUpdate', SupportTicket::class);

        $tickets = SupportTicket::query()->whereIn('id', array_unique($data['ticketIds']))->get();

        foreach ($tickets as $ticket) {
            Gate::forUser($policyUser)->authorize('changePriority', $ticket);
        }

        return response()->json($this->supportTickets->updatePriority(
            ticketIds: $data['ticketIds'],
            priority: $data['priority'],
            currentUser: $currentUser,
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
        $record = SupportTicket::query()->find($id);

        if ($record === null) {
            return response()->json(['message' => 'Ticket not found.'], 404);
        }

        $currentUser = $this->currentUserResolver->fromRequestOrFallback($request);
        Gate::forUser($this->policyUser($currentUser))->authorize(
            $request->boolean('isInternalNote') ? 'internalNote' : 'reply',
            $record,
        );

        $data = $request->validated();
        $message = $data['message'] ?? trim(strip_tags((string) ($data['htmlBody'] ?? '')));
        $result = $this->supportTickets->reply(
            id: $id,
            message: (string) $message,
            htmlBody: $data['htmlBody'] ?? null,
            isInternalNote: $data['isInternalNote'],
            attachmentIds: $data['attachmentIds'] ?? [],
            parentActivityId: $data['parentActivityId'] ?? null,
            mentions: $data['mentions'] ?? [],
            currentUser: $currentUser,
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
        $record = SupportTicket::query()->find($id);

        if ($record === null) {
            return response()->json(['message' => 'Ticket not found.'], 404);
        }

        $currentUser = $this->currentUserResolver->fromRequestOrFallback($request);
        Gate::forUser($this->policyUser($currentUser))->authorize('forward', $record);

        $result = $this->supportTickets->forward(
            id: $id,
            payload: $request->validated(),
            currentUser: $currentUser,
        );

        return $result === null
            ? response()->json(['message' => 'Ticket not found.'], 404)
            : response()->json($result);
    }

    private function policyUser(CurrentUser $currentUser): User
    {
        $user = null;

        if (ctype_digit($currentUser->id)) {
            $user = User::query()->find((int) $currentUser->id);
        }

        if ($user === null && $currentUser->email !== null) {
            $user = User::query()->where('email', $currentUser->email)->first();
        }

        if ($user !== null) {
            return $user;
        }

        $fallback = new User();
        $fallback->name = $currentUser->name;
        $fallback->email = $currentUser->email;

        if (ctype_digit($currentUser->id)) {
            $fallback->id = (int) $currentUser->id;
        }

        return $fallback;
    }
}
