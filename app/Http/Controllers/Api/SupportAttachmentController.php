<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Support\ListAttachmentsRequest;
use App\Http\Requests\Support\UploadAttachmentRequest;
use App\Models\SupportTicket;
use App\Models\User;
use App\Support\Auth\CurrentUser;
use App\Support\Auth\CurrentUserResolver;
use App\Support\Services\SupportTicketService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

final class SupportAttachmentController extends Controller
{
    public function __construct(
        private readonly SupportTicketService $supportTickets,
        private readonly CurrentUserResolver $currentUserResolver,
    ) {}

    /**
     * GET /api/support/attachments
     *
     * Returns a global searchable attachment reference list for picker UIs.
     */
    public function index(ListAttachmentsRequest $request): JsonResponse
    {
        return response()->json($this->supportTickets->attachments($request->validated()));
    }

    /**
     * POST /api/support/attachments/upload
     *
     * Stores one uploaded file and returns an attachment reference id for create, reply, and forward flows.
     */
    public function upload(UploadAttachmentRequest $request): JsonResponse
    {
        $currentUser = $this->currentUserResolver->fromRequestOrFallback($request);
        $ticketId = $request->input('ticketId');

        if (is_string($ticketId) && $ticketId !== '') {
            $ticket = SupportTicket::query()->find($ticketId);

            if ($ticket === null) {
                return response()->json(['message' => 'Ticket not found.'], 404);
            }

            Gate::forUser($this->policyUser($currentUser))->authorize('uploadAttachment', $ticket);
        }

        return response()->json($this->supportTickets->uploadAttachment(
            file: $request->file('file'),
            payload: $request->validated(),
            currentUser: $currentUser,
        ), 201);
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
