<?php

namespace App\Http\Middleware;

use App\Support\Auth\CurrentUserResolver;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureApiToken
{
    public function __construct(
        private readonly CurrentUserResolver $currentUserResolver,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        if ($this->currentUserResolver->fromRequest($request) === null) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        return $next($request);
    }
}
