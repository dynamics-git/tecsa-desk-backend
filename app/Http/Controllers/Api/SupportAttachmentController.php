<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Support\ListAttachmentsRequest;
use App\Http\Requests\Support\UploadAttachmentRequest;
use App\Support\Auth\CurrentUserResolver;
use App\Support\Services\SupportTicketService;
use Illuminate\Http\JsonResponse;

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
        return response()->json($this->supportTickets->uploadAttachment(
            file: $request->file('file'),
            payload: $request->validated(),
            currentUser: $this->currentUserResolver->fromRequestOrFallback($request),
        ), 201);
    }
}
