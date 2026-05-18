<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\Auth\CurrentUserResolver;
use App\Support\Auth\PasswordSecurityService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

final class AuthPasswordController extends Controller
{
    public function __construct(
        private readonly PasswordSecurityService $passwordSecurityService,
        private readonly CurrentUserResolver $currentUserResolver,
    ) {}

    public function passwordPolicy(): JsonResponse
    {
        return response()->json($this->passwordSecurityService->policy());
    }

    public function updatePasswordPolicy(Request $request): JsonResponse
    {
        $data = $request->validate([
            'minLength' => ['sometimes', 'integer', 'min:8', 'max:128'],
            'requireUppercase' => ['sometimes', 'boolean'],
            'requireLowercase' => ['sometimes', 'boolean'],
            'requireNumber' => ['sometimes', 'boolean'],
            'requireSymbol' => ['sometimes', 'boolean'],
            'disallowCommonPasswords' => ['sometimes', 'boolean'],
            'historyCount' => ['sometimes', 'integer', 'min:0', 'max:24'],
            'maxAgeDays' => ['sometimes', 'integer', 'min:0', 'max:3650'],
            'lockoutThreshold' => ['sometimes', 'integer', 'min:1', 'max:20'],
            'lockoutDurationMinutes' => ['sometimes', 'integer', 'min:1', 'max:1440'],
            'allowPasswordGenerate' => ['sometimes', 'boolean'],
            'allowManualPasswordSet' => ['sometimes', 'boolean'],
            'forceChangeOnFirstLoginDefault' => ['sometimes', 'boolean'],
        ]);

        $actor = $this->currentUserResolver->fromRequest($request);
        $updated = $this->passwordSecurityService->updatePolicy($data, $actor !== null && ctype_digit($actor->id) ? (int) $actor->id : null);

        return response()->json($updated);
    }

    public function generatePassword(Request $request): JsonResponse
    {
        $data = $request->validate([
            'userId' => ['sometimes', 'string'],
            'purpose' => ['sometimes', 'string', 'in:create,reset'],
        ]);

        $password = $this->passwordSecurityService->generatePassword();

        return response()->json([
            'generatedPassword' => $password,
            'policySnapshot' => $this->passwordSecurityService->policy(),
            'expiresAt' => null,
            'purpose' => $data['purpose'] ?? 'create',
        ]);
    }

    public function setByAdmin(Request $request): JsonResponse
    {
        $data = $request->validate([
            'userId' => ['required', 'string'],
            'mode' => ['required', 'string', 'in:manual,generated'],
            'password' => ['nullable', 'string'],
            'forceChangeOnNextLogin' => ['sometimes', 'boolean'],
            'notifyUser' => ['sometimes', 'boolean'],
            'reason' => ['nullable', 'string', 'max:255'],
        ]);

        $userId = $data['userId'];
        $user = User::query()->findOrFail((int) $userId);
        $actor = $this->currentUserResolver->fromRequest($request);
        $actorId = $actor !== null && ctype_digit($actor->id) ? (int) $actor->id : null;
        $forceChange = (bool) ($data['forceChangeOnNextLogin'] ?? true);

        $temporaryPassword = null;
        if ($data['mode'] === 'generated') {
            $temporaryPassword = $this->passwordSecurityService->generatePassword();
            $password = $temporaryPassword;
        } else {
            $password = (string) ($data['password'] ?? '');
            if ($password === '') {
                return response()->json([
                    'code' => 'PASSWORD_POLICY_VIOLATION',
                    'message' => 'Password is required when mode is manual.',
                ], 422);
            }
        }

        $validationError = $this->passwordSecurityService->validatePassword($password, $user, true);
        if ($validationError !== null) {
            return response()->json([
                'code' => $validationError,
                'message' => 'Password does not meet policy requirements.',
            ], 422);
        }

        DB::transaction(function () use ($user, $password, $actorId, $forceChange): void {
            $this->passwordSecurityService->applyPassword($user, $password, $actorId, 'admin', $forceChange);
        });

        $auditId = $this->passwordSecurityService->audit(
            actorId: $actorId,
            targetUserId: (int) $user->id,
            action: 'password.set_by_admin',
            request: $request,
            reason: $data['reason'] ?? null,
            metadata: [
                'mode' => $data['mode'],
                'notifyUser' => (bool) ($data['notifyUser'] ?? false),
            ],
        );

        return response()->json([
            'userId' => (string) $user->id,
            'mustChangePassword' => (bool) $user->must_change_password,
            'passwordLastChangedAt' => $user->password_last_changed_at?->toIso8601ZuluString(),
            'temporaryPassword' => $temporaryPassword,
            'auditId' => (string) $auditId,
        ]);
    }

    public function changePassword(Request $request): JsonResponse
    {
        $data = $request->validate([
            'currentPassword' => ['required', 'string'],
            'newPassword' => ['required', 'string'],
        ]);

        $currentUser = $this->currentUserResolver->fromRequest($request);
        if ($currentUser === null || ! ctype_digit($currentUser->id)) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        $user = User::query()->findOrFail((int) $currentUser->id);
        $currentPassword = (string) $data['currentPassword'];
        $newPassword = (string) $data['newPassword'];

        if (! Hash::check($currentPassword, $user->password)) {
            return response()->json([
                'code' => 'CURRENT_PASSWORD_INVALID',
                'message' => 'Current password is invalid.',
            ], 422);
        }

        $validationError = $this->passwordSecurityService->validatePassword($newPassword, $user, true);
        if ($validationError !== null) {
            return response()->json([
                'code' => $validationError,
                'message' => 'New password does not meet security requirements.',
            ], 422);
        }

        DB::transaction(function () use ($user, $newPassword): void {
            // No actor id for self flow beyond target user itself.
            $this->passwordSecurityService->applyPassword($user, $newPassword, (int) $user->id, 'self', false);
        });

        $this->passwordSecurityService->audit(
            actorId: (int) $user->id,
            targetUserId: (int) $user->id,
            action: 'password.change',
            request: $request,
        );

        return response()->json([
            'changedAt' => $user->password_last_changed_at?->toIso8601ZuluString(),
            'mustChangePassword' => false,
            'sessionRefreshToken' => null,
        ]);
    }

    public function forgotPassword(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email:rfc'],
            'channel' => ['sometimes', 'string', 'max:40'],
        ]);

        $user = User::query()->where('email', $data['email'])->first();

        if ($user !== null) {
            DB::table('password_reset_tokens')->updateOrInsert(
                ['email' => $user->email],
                [
                    'token' => hash('sha256', Str::random(64)),
                    'created_at' => now(),
                ],
            );
        }

        return response()->json([
            'message' => 'If account exists, reset instructions sent',
        ]);
    }

    public function resetPassword(Request $request): JsonResponse
    {
        $data = $request->validate([
            'token' => ['required', 'string'],
            'newPassword' => ['required', 'string'],
        ]);

        $hashedToken = hash('sha256', $data['token']);
        $record = DB::table('password_reset_tokens')->where('token', $hashedToken)->first();

        if ($record === null) {
            return response()->json(['code' => 'INVALID_RESET_TOKEN', 'message' => 'Invalid reset token.'], 422);
        }

        if ($record->created_at !== null && now()->subHours(2)->gt(Carbon::parse((string) $record->created_at))) {
            return response()->json(['code' => 'RESET_TOKEN_EXPIRED', 'message' => 'Reset token has expired.'], 422);
        }

        $user = User::query()->where('email', $record->email)->first();
        if ($user === null) {
            return response()->json(['code' => 'INVALID_RESET_TOKEN', 'message' => 'Invalid reset token.'], 422);
        }

        $newPassword = (string) $data['newPassword'];
        $validationError = $this->passwordSecurityService->validatePassword($newPassword, $user, true);
        if ($validationError !== null) {
            return response()->json(['code' => $validationError, 'message' => 'New password does not meet policy requirements.'], 422);
        }

        $this->passwordSecurityService->applyPassword($user, $newPassword, (int) $user->id, 'reset', false);
        DB::table('password_reset_tokens')->where('email', $user->email)->delete();

        return response()->json([
            'changedAt' => $user->password_last_changed_at?->toIso8601ZuluString(),
            'mustChangePassword' => false,
        ]);
    }
}
