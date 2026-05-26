<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Support\ProcessInboundEmailRequest;
use App\Support\Services\SupportInboundEmailProcessor;
use Illuminate\Http\JsonResponse;

final class SupportInboundEmailController extends Controller
{
    public function store(ProcessInboundEmailRequest $request, SupportInboundEmailProcessor $processor): JsonResponse
    {
        $result = $processor->process($request->validated());

        return response()->json($result, ($result['matched'] ?? true) === false ? 202 : 201);
    }
}
