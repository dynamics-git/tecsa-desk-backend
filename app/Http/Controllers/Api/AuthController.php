<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Models\ApiToken;
use App\Models\User;
use App\Support\Auth\CurrentUserResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

final class AuthController extends Controller
{
    public function __construct(
        private readonly CurrentUserResolver $currentUserResolver,
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

        if ($user === null || ! Hash::check($data['password'], $user->password)) {
            return response()->json([
                'message' => 'Invalid credentials.',
                'errors' => [
                    'email' => ['The provided credentials are invalid.'],
                ],
            ], 422);
        }

        return response()->json($this->issueToken($user, $data['deviceName'] ?? 'frontend'));
    }

    public function me(Request $request): JsonResponse
    {
        $currentUser = $this->currentUserResolver->fromRequest($request);

        return $currentUser === null
            ? response()->json(['message' => 'Unauthenticated.'], 401)
            : response()->json(['user' => $currentUser->toArray()]);
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
            'user' => [
                'id' => (string) $user->id,
                'name' => $user->name,
                'email' => $user->email,
            ],
        ];
    }
}
