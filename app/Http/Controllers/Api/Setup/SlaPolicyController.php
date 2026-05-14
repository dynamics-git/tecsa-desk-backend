<?php

namespace App\Http\Controllers\Api\Setup;

use App\Http\Controllers\Controller;
use App\Http\Requests\Setup\StoreSlaPolicyRequest;
use App\Http\Requests\Setup\UpdateSlaPolicyRequest;
use App\Http\Resources\SupportSlaPolicyResource;
use App\Models\SupportSlaPolicy;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class SlaPolicyController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        return SupportSlaPolicyResource::collection(SupportSlaPolicy::query()->orderBy('name')->get());
    }

    public function store(StoreSlaPolicyRequest $request): SupportSlaPolicyResource
    {
        return new SupportSlaPolicyResource(SupportSlaPolicy::query()->create($this->payload($request->validated())));
    }

    public function show(SupportSlaPolicy $slaPolicy): SupportSlaPolicyResource
    {
        return new SupportSlaPolicyResource($slaPolicy);
    }

    public function update(UpdateSlaPolicyRequest $request, SupportSlaPolicy $slaPolicy): SupportSlaPolicyResource
    {
        $slaPolicy->update($this->payload($request->validated(), false));

        return new SupportSlaPolicyResource($slaPolicy);
    }

    public function destroy(SupportSlaPolicy $slaPolicy): JsonResponse
    {
        $slaPolicy->delete();

        return response()->json(['success' => true]);
    }

    private function payload(array $data, bool $includeId = true): array
    {
        return array_filter([
            'id' => $includeId ? ($data['id'] ?? null) : null,
            'name' => $data['name'] ?? null,
            'priority' => $data['priority'] ?? null,
            'first_response_minutes' => $data['firstResponseMinutes'] ?? null,
            'resolution_minutes' => $data['resolutionMinutes'] ?? null,
            'is_active' => $data['isActive'] ?? null,
        ], fn ($value): bool => $value !== null);
    }
}
