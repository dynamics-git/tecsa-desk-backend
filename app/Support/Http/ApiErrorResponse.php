<?php

namespace App\Support\Http;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Throwable;

final class ApiErrorResponse
{
    /**
     * @param array<string, mixed> $details
     * @param array<string, mixed> $errors
     */
    public static function make(
        Request $request,
        int $status,
        string $code,
        string $message,
        array $details = [],
        array $errors = []
    ): JsonResponse {
        $requestId = self::requestId($request);
        $errorId = self::errorId();

        $payload = [
            'success' => false,
            'code' => $code,
            'message' => $message,
            'error' => [
                'id' => $errorId,
                'status' => $status,
                'code' => $code,
                'message' => $message,
            ],
            'meta' => [
                'requestId' => $requestId,
                'timestamp' => Carbon::now('UTC')->toIso8601ZuluString(),
                'path' => '/'.$request->path(),
                'method' => strtoupper($request->method()),
            ],
        ];

        if ($details !== []) {
            $payload['details'] = $details;
            $payload['error']['details'] = $details;
        }

        if ($errors !== []) {
            $payload['errors'] = $errors;
            $payload['error']['errors'] = $errors;
        }

        return response()->json($payload, $status, [
            'X-Request-Id' => $requestId,
            'X-Error-Id' => $errorId,
        ]);
    }

    /**
     * @param array<string, mixed> $errors
     */
    public static function validation(Request $request, string $message, array $errors): JsonResponse
    {
        return self::make(
            request: $request,
            status: 422,
            code: 'VALIDATION_ERROR',
            message: $message,
            errors: $errors,
        );
    }

    public static function unauthenticated(Request $request, string $message = 'Unauthenticated.'): JsonResponse
    {
        return self::make($request, 401, 'AUTH_UNAUTHENTICATED', $message);
    }

    public static function forbidden(Request $request, string $message = 'You do not have permission to perform this action.'): JsonResponse
    {
        return self::make($request, 403, 'AUTH_FORBIDDEN', $message);
    }

    public static function notFound(Request $request, string $message = 'Resource not found.'): JsonResponse
    {
        return self::make($request, 404, 'RESOURCE_NOT_FOUND', $message);
    }

    /**
     * @param array<string, mixed> $details
     */
    public static function serverError(
        Request $request,
        string $message = 'Unexpected server error.',
        array $details = []
    ): JsonResponse {
        return self::make($request, 500, 'SERVER_ERROR', $message, $details);
    }

    /**
     * @param array<string, mixed> $details
     */
    public static function fromThrowable(Request $request, Throwable $exception): JsonResponse
    {
        $details = [];

        if ((bool) config('app.debug')) {
            $details = [
                'exception' => $exception::class,
                'exceptionMessage' => $exception->getMessage(),
            ];
        }

        return self::serverError($request, 'Unexpected server error.', $details);
    }

    private static function requestId(Request $request): string
    {
        return (string) (
            $request->headers->get('X-Request-Id')
            ?? $request->headers->get('X-Correlation-Id')
            ?? Str::uuid()->toString()
        );
    }

    private static function errorId(): string
    {
        return 'err_'.Str::lower((string) Str::ulid());
    }
}