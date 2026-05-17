<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\Auth\CurrentUserResolver;
use App\Support\Auth\PasswordSecurityService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

final class UserSecuritySettingsController extends Controller
{
    public function __construct(
        private readonly CurrentUserResolver $currentUserResolver,
        private readonly PasswordSecurityService $passwordSecurityService,
    ) {}

    public function show(string $id): JsonResponse
    {
        $user = User::query()->findOrFail((int) $id);

        return response()->json($this->payload($user));
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $data = $request->validate([
            'mustChangePassword' => ['sometimes', 'boolean'],
            'must_change_password' => ['sometimes', 'boolean'],
            'passwordNeverExpires' => ['sometimes', 'boolean'],
            'password_never_expires' => ['sometimes', 'boolean'],
            'mfaRequired' => ['sometimes', 'boolean'],
            'mfa_required' => ['sometimes', 'boolean'],
            'accountLocked' => ['sometimes', 'boolean'],
            'account_locked' => ['sometimes', 'boolean'],
            'lockDurationMinutes' => ['sometimes', 'integer', 'min:1', 'max:1440'],
            'lock_duration_minutes' => ['sometimes', 'integer', 'min:1', 'max:1440'],
            'version' => ['required', 'integer', 'min:1'],
        ]);

        $user = User::query()->findOrFail((int) $id);
        if ((int) $user->security_version !== (int) $data['version']) {
            return response()->json([
                'code' => 'SECURITY_VERSION_CONFLICT',
                'message' => 'Security settings were modified by another request.',
                'currentVersion' => (int) $user->security_version,
            ], 409);
        }

        $accountLocked = $data['accountLocked'] ?? $data['account_locked'] ?? null;
        $lockMinutes = (int) ($data['lockDurationMinutes'] ?? $data['lock_duration_minutes'] ?? 15);

        if (array_key_exists('mustChangePassword', $data) || array_key_exists('must_change_password', $data)) {
            $user->must_change_password = (bool) ($data['mustChangePassword'] ?? $data['must_change_password']);
        }
        if (array_key_exists('passwordNeverExpires', $data) || array_key_exists('password_never_expires', $data)) {
            $user->password_never_expires = (bool) ($data['passwordNeverExpires'] ?? $data['password_never_expires']);
        }
        if (array_key_exists('mfaRequired', $data) || array_key_exists('mfa_required', $data)) {
            $user->mfa_required = (bool) ($data['mfaRequired'] ?? $data['mfa_required']);
        }
        if ($accountLocked !== null) {
            $user->locked_until = $accountLocked ? Carbon::now('UTC')->addMinutes($lockMinutes) : null;
            if (! $accountLocked) {
                $user->failed_login_attempts = 0;
            }
        }

        $user->security_version = (int) $user->security_version + 1;
        $user->save();

        $actor = $this->currentUserResolver->fromRequest($request);
        $this->passwordSecurityService->audit(
            actorId: $actor !== null && ctype_digit($actor->id) ? (int) $actor->id : null,
            targetUserId: (int) $user->id,
            action: 'user.security_settings.update',
            request: $request,
        );

        return response()->json($this->payload($user));
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(User $user): array
    {
        return [
            'userId' => (string) $user->id,
            'mustChangePassword' => (bool) $user->must_change_password,
            'passwordNeverExpires' => (bool) $user->password_never_expires,
            'mfaRequired' => (bool) $user->mfa_required,
            'failedLoginAttempts' => (int) $user->failed_login_attempts,
            'lockedUntil' => $user->locked_until?->toIso8601ZuluString(),
            'passwordLastChangedAt' => $user->password_last_changed_at?->toIso8601ZuluString(),
            'passwordExpiresAt' => $user->password_expires_at?->toIso8601ZuluString(),
            'securityVersion' => (int) $user->security_version,
            'version' => (int) $user->security_version,
        ];
    }
}
