<?php

namespace App\Http\Controllers\Api\Support;

use App\Http\Controllers\Controller;
use App\Http\Requests\Support\MarkTicketActivitiesReadRequest;
use App\Models\SupportTicket;
use App\Models\User;
use App\Support\Auth\CurrentUser;
use App\Support\Auth\CurrentUserResolver;
use App\Support\Services\SupportConversationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

final class ActivityReadController extends Controller
{
    public function __construct(
        private readonly SupportConversationService $supportConversation,
        private readonly CurrentUserResolver $currentUserResolver,
    ) {}

    public function markRead(string $ticketId, MarkTicketActivitiesReadRequest $request): JsonResponse
    {
        $ticket = SupportTicket::query()->find($ticketId);

        if ($ticket === null) {
            return response()->json(['message' => 'Ticket not found.'], 404);
        }

        $currentUser = $this->currentUserResolver->fromRequestOrFallback($request);
        Gate::forUser($this->policyUser($currentUser))->authorize('view', $ticket);

        $updated = $this->supportConversation->markActivitiesRead(
            ticketId: $ticketId,
            activityIds: $request->validated('activityIds', []),
            currentUser: $currentUser,
        );

        return response()->json(['updated' => $updated]);
    }

    public function markReadAll(string $ticketId, Request $request): JsonResponse
    {
        $ticket = SupportTicket::query()->find($ticketId);

        if ($ticket === null) {
            return response()->json(['message' => 'Ticket not found.'], 404);
        }

        $currentUser = $this->currentUserResolver->fromRequestOrFallback($request);
        Gate::forUser($this->policyUser($currentUser))->authorize('view', $ticket);

        $updated = $this->supportConversation->markAllActivitiesRead($ticketId, $currentUser);

        return response()->json(['updated' => $updated]);
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
