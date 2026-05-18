<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Models\ApiToken;
use App\Models\User;
use App\Support\Auth\CurrentUserResolver;
use App\Support\Auth\PasswordSecurityService;
use App\Support\Auth\SupportAccessResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

final class AuthController extends Controller
{
    public function __construct(
        private readonly CurrentUserResolver $currentUserResolver,
        private readonly SupportAccessResolver $supportAccessResolver,
        private readonly PasswordSecurityService $passwordSecurityService,
    ) {}

    public function register(RegisterRequest $request): JsonResponse
    {
        $data = $request->validated();

        $user = User::query()->create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => $data['password'],
        ]);

        return response()->json($this->issueToken($user, $data['deviceName'] ?? 'frontend'), 201);
    }

    public function login(LoginRequest $request): JsonResponse
    {
        $data = $request->validated();
        $user = User::query()->where('email', $data['email'])->first();

        if ($user !== null && ! (bool) $user->is_active) {
            return response()->json([
                'code' => 'ACCOUNT_INACTIVE',
                'message' => 'Account is inactive.',
                'nextAction' => 'contact_admin',
            ], 403);
        }

        if ($user !== null && $user->locked_until !== null && $user->locked_until->isFuture()) {
            return response()->json([
                'code' => 'ACCOUNT_LOCKED',
                'message' => 'Account is locked.',
                'lockedUntil' => $user->locked_until->toIso8601ZuluString(),
                'nextAction' => 'contact_admin',
            ], 423);
        }

        if ($user === null || ! Hash::check($data['password'], $user->password)) {
            if ($user !== null) {
                $policy = $this->passwordSecurityService->policy();
                $threshold = (int) ($policy['lockoutThreshold'] ?? 5);
                $durationMinutes = (int) ($policy['lockoutDurationMinutes'] ?? 15);

                $user->failed_login_attempts = (int) $user->failed_login_attempts + 1;
                if ($user->failed_login_attempts >= $threshold) {
                    $user->locked_until = Carbon::now('UTC')->addMinutes($durationMinutes);
                }
                $user->save();
            }

            return response()->json([
                'message' => 'Invalid credentials.',
                'errors' => [
                    'email' => ['The provided credentials are invalid.'],
                ],
            ], 422);
        }

        $user->forceFill([
            'failed_login_attempts' => 0,
            'locked_until' => null,
        ])->save();

        return response()->json($this->issueToken($user, $data['deviceName'] ?? 'frontend'));
    }

    public function me(Request $request): JsonResponse
    {
        $currentUser = $this->currentUserResolver->fromRequest($request);

        if ($currentUser === null) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        $user = ctype_digit($currentUser->id) ? User::query()->find((int) $currentUser->id) : null;
        $passwordExpired = $user !== null ? $this->passwordSecurityService->passwordExpired($user) : false;
        $nextAction = $this->nextActionFor($user, $passwordExpired);

        $payload = $this->supportAccessResolver->authPayload($currentUser);
        $payload['mustChangePassword'] = (bool) ($user?->must_change_password ?? false);
        $payload['passwordExpired'] = $passwordExpired;
        $payload['lockedUntil'] = $user?->locked_until?->toIso8601ZuluString();
        $payload['mfaRequired'] = (bool) ($user?->mfa_required ?? false);
        $payload['nextAction'] = $nextAction;

        return response()->json(['user' => $payload]);
    }

    public function logout(Request $request): JsonResponse
    {
        $plainToken = $request->bearerToken();

        if ($plainToken !== null && $plainToken !== '') {
            ApiToken::query()->where('token_hash', hash('sha256', $plainToken))->delete();
        }

        return response()->json(['success' => true]);
    }

    /**
     * @return array{token: string, tokenType: string, expiresAt: string, user: array{id: string, name: string, email: string|null}}
     */
    private function issueToken(User $user, string $deviceName): array
    {
        $plainToken = Str::random(80);
        $expiresAt = Carbon::now('UTC')->addDays(30);
        $passwordExpired = $this->passwordSecurityService->passwordExpired($user);
        $nextAction = $this->nextActionFor($user, $passwordExpired);

        ApiToken::query()->create([
            'user_id' => $user->id,
            'name' => $deviceName,
            'token_hash' => hash('sha256', $plainToken),
            'expires_at' => $expiresAt,
        ]);

        return [
            'token' => $plainToken,
            'tokenType' => 'Bearer',
            'expiresAt' => $expiresAt->toIso8601ZuluString(),
            'mustChangePassword' => (bool) $user->must_change_password,
            'passwordExpired' => $passwordExpired,
            'lockedUntil' => $user->locked_until?->toIso8601ZuluString(),
            'mfaRequired' => (bool) $user->mfa_required,
            'nextAction' => $nextAction,
            'user' => [
                'id' => (string) $user->id,
                'name' => $user->name,
                'email' => $user->email,
            ],
        ];
    }

    private function nextActionFor(?User $user, bool $passwordExpired): string
    {
        if ($user === null) {
            return 'none';
        }

        if ($user->locked_until !== null && $user->locked_until->isFuture()) {
            return 'contact_admin';
        }

        if ((bool) $user->must_change_password || $passwordExpired) {
            return 'change_password';
        }

        if ((bool) $user->mfa_required) {
            return 'setup_mfa';
        }

        return 'none';
    }
}
