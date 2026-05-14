<?php

namespace App\Http\Controllers\Api\Setup;

use App\Http\Controllers\Controller;
use App\Http\Requests\Setup\StoreTeamRequest;
use App\Http\Requests\Setup\UpdateTeamRequest;
use App\Http\Resources\SupportTeamResource;
use App\Models\SupportTeam;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class TeamController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        return SupportTeamResource::collection(SupportTeam::query()->orderBy('name')->get());
    }

    public function store(StoreTeamRequest $request): SupportTeamResource
    {
        return new SupportTeamResource(SupportTeam::query()->create($this->payload($request->validated())));
    }

    public function show(SupportTeam $team): SupportTeamResource
    {
        return new SupportTeamResource($team);
    }

    public function update(UpdateTeamRequest $request, SupportTeam $team): SupportTeamResource
    {
        $team->update($this->payload($request->validated(), false));

        return new SupportTeamResource($team);
    }

    public function destroy(SupportTeam $team): JsonResponse
    {
        $team->delete();

        return response()->json(['success' => true]);
    }

    private function payload(array $data, bool $includeId = true): array
    {
        return array_filter([
            'id' => $includeId ? ($data['id'] ?? null) : null,
            'name' => $data['name'] ?? null,
            'is_active' => $data['isActive'] ?? null,
        ], fn ($value): bool => $value !== null);
    }
}
