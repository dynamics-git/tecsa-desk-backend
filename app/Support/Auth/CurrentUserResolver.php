<?php

namespace App\Support\Auth;

use App\Models\ApiToken;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

final class CurrentUserResolver
{
    public function fromRequest(Request $request): ?CurrentUser
    {
        $plainToken = $request->bearerToken();

        if ($plainToken === null || $plainToken === '') {
            return null;
        }

        $apiToken = ApiToken::query()
            ->with('user')
            ->where('token_hash', hash('sha256', $plainToken))
            ->first();

        if ($apiToken === null || ($apiToken->expires_at !== null && $apiToken->expires_at->isPast())) {
            return null;
        }

        $apiToken->forceFill(['last_used_at' => Carbon::now('UTC')])->save();

        return new CurrentUser(
            id: (string) $apiToken->user->id,
            name: $apiToken->user->name,
            email: $apiToken->user->email,
        );
    }

    public function fallback(): CurrentUser
    {
        return new CurrentUser('amit', 'Amit', 'amit@example.com');
    }

    public function fromRequestOrFallback(Request $request): CurrentUser
    {
        return $this->fromRequest($request) ?? $this->fallback();
    }
}
