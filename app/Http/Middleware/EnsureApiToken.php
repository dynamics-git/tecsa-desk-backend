<?php

namespace App\Http\Middleware;

use App\Support\Auth\CurrentUserResolver;
use App\Support\Http\ApiErrorResponse;
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
            return ApiErrorResponse::unauthenticated($request);
        }

        return $next($request);
    }
}
