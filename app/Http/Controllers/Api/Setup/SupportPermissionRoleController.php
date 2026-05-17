<?php

namespace App\Http\Controllers\Api\Setup;

use App\Http\Controllers\Controller;
use App\Http\Requests\Setup\UpsertPermissionRoleRequest;
use App\Http\Resources\SupportPermissionRoleResource;
use App\Models\SupportPermissionRole;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class SupportPermissionRoleController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        return SupportPermissionRoleResource::collection(SupportPermissionRole::query()->latest()->get());
    }

    public function store(UpsertPermissionRoleRequest $request): SupportPermissionRoleResource
    {
        return new SupportPermissionRoleResource(
            SupportPermissionRole::query()->create($this->payload($request->validated())),
        );
    }

    public function show(SupportPermissionRole $permissionRole): SupportPermissionRoleResource
    {
        return new SupportPermissionRoleResource($permissionRole);
    }

    public function update(UpsertPermissionRoleRequest $request, SupportPermissionRole $permissionRole): SupportPermissionRoleResource
    {
        $permissionRole->update($this->payload($request->validated()));

        return new SupportPermissionRoleResource($permissionRole);
    }

    public function destroy(SupportPermissionRole $permissionRole): JsonResponse
    {
        $permissionRole->delete();

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
            'user_email' => $data['user_email'] ?? null,
            'user_type' => $data['user_type'] ?? null,
            'role' => $data['name'] ?? null,
            'permissions' => $data['permissions'] ?? [],
            'user_ids' => $data['user_ids'] ?? [],
            'team_ids' => $data['team_ids'] ?? [],
            'customer_ids' => $data['customer_ids'] ?? [],
            'ticket_visibility' => $data['ticket_visibility'] ?? null,
            'is_active' => $data['is_active'] ?? true,
            'is_admin' => $data['is_admin'] ?? false,
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
