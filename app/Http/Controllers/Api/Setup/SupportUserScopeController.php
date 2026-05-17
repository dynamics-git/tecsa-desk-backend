<?php

namespace App\Http\Controllers\Api\Setup;

use App\Http\Controllers\Controller;
use App\Http\Requests\Setup\UpsertSupportUserScopeRequest;
use App\Http\Resources\SupportUserScopeResource;
use App\Models\SupportUserScope;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class SupportUserScopeController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        return SupportUserScopeResource::collection(SupportUserScope::query()->latest()->get());
    }

    public function store(UpsertSupportUserScopeRequest $request): SupportUserScopeResource
    {
        return new SupportUserScopeResource(
            SupportUserScope::query()->create($this->payload($request->validated())),
        );
    }

    public function show(SupportUserScope $supportUserScope): SupportUserScopeResource
    {
        return new SupportUserScopeResource($supportUserScope);
    }

    public function update(UpsertSupportUserScopeRequest $request, SupportUserScope $supportUserScope): SupportUserScopeResource
    {
        $supportUserScope->update($this->payload($request->validated()));

        return new SupportUserScopeResource($supportUserScope);
    }

    public function destroy(SupportUserScope $supportUserScope): JsonResponse
    {
        $supportUserScope->delete();

        return response()->json(['success' => true]);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function payload(array $data): array
    {
        return array_filter([
            'user_id' => $this->normalizeUserId($data['user_id'] ?? null),
            'user_name' => $data['user_name'] ?? null,
            'user_email' => $data['user_email'] ?? null,
            'visibility_mode' => $data['visibility_mode'] ?? null,
            'team_ids' => $data['team_ids'] ?? [],
            'queue_ids' => $data['queue_ids'] ?? [],
            'customer_ids' => $data['customer_ids'] ?? [],
            'is_active' => $data['is_active'] ?? true,
        ], fn ($value): bool => $value !== null);
    }

    private function normalizeUserId(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_int($value)) {
            return $value;
        }

        if (is_string($value) && ctype_digit($value)) {
            return (int) $value;
        }

        return null;
    }
}
