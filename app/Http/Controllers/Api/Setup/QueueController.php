<?php

namespace App\Http\Controllers\Api\Setup;

use App\Http\Controllers\Controller;
use App\Http\Requests\Setup\StoreQueueRequest;
use App\Http\Requests\Setup\UpdateQueueRequest;
use App\Http\Resources\SupportQueueResource;
use App\Models\SupportQueue;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class QueueController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        return SupportQueueResource::collection(SupportQueue::query()->orderBy('name')->get());
    }

    public function store(StoreQueueRequest $request): SupportQueueResource
    {
        return new SupportQueueResource(SupportQueue::query()->create($this->payload($request->validated())));
    }

    public function show(SupportQueue $queue): SupportQueueResource
    {
        return new SupportQueueResource($queue);
    }

    public function update(UpdateQueueRequest $request, SupportQueue $queue): SupportQueueResource
    {
        $queue->update($this->payload($request->validated(), false));

        return new SupportQueueResource($queue);
    }

    public function destroy(SupportQueue $queue): JsonResponse
    {
        $queue->delete();

        return response()->json(['success' => true]);
    }

    private function payload(array $data, bool $includeId = true): array
    {
        return array_filter([
            'id' => $includeId ? ($data['id'] ?? null) : null,
            'name' => $data['name'] ?? null,
            'team_id' => $data['teamId'] ?? null,
            'is_active' => $data['isActive'] ?? null,
        ], fn ($value): bool => $value !== null);
    }
}
