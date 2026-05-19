<?php

use App\Http\Middleware\EnsureApiToken;
use App\Support\Http\ApiErrorResponse;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'api.token' => EnsureApiToken::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (ValidationException $exception, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            return ApiErrorResponse::validation(
                request: $request,
                message: $exception->getMessage() !== '' ? $exception->getMessage() : 'The given data was invalid.',
                errors: $exception->errors(),
            );
        });

        $exceptions->render(function (AuthenticationException $exception, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            return ApiErrorResponse::unauthenticated($request, $exception->getMessage() ?: 'Unauthenticated.');
        });

        $exceptions->render(function (AuthorizationException $exception, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            return ApiErrorResponse::forbidden($request, $exception->getMessage() ?: 'Forbidden.');
        });

        $exceptions->render(function (ModelNotFoundException $exception, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            return ApiErrorResponse::notFound($request);
        });

        $exceptions->render(function (NotFoundHttpException $exception, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            return ApiErrorResponse::notFound($request);
        });

        $exceptions->render(function (MethodNotAllowedHttpException $exception, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            return ApiErrorResponse::make(
                request: $request,
                status: 405,
                code: 'METHOD_NOT_ALLOWED',
                message: 'Method not allowed for this endpoint.',
            );
        });

        $exceptions->render(function (TooManyRequestsHttpException $exception, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            $retryAfter = $exception->getHeaders()['Retry-After'] ?? null;

            return ApiErrorResponse::make(
                request: $request,
                status: 429,
                code: 'TOO_MANY_REQUESTS',
                message: 'Too many requests. Please try again later.',
                details: $retryAfter !== null ? ['retryAfter' => (string) $retryAfter] : [],
            );
        });

        $exceptions->render(function (QueryException $exception, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            return ApiErrorResponse::make(
                request: $request,
                status: 500,
                code: 'DATABASE_ERROR',
                message: 'A database error occurred while processing the request.',
                details: (bool) config('app.debug')
                    ? ['exceptionMessage' => $exception->getMessage()]
                    : [],
            );
        });

        $exceptions->render(function (\Throwable $exception, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            return ApiErrorResponse::fromThrowable($request, $exception);
        });
    })->create();
