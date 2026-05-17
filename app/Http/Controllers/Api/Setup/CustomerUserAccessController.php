<?php

namespace App\Http\Controllers\Api\Setup;

use App\Http\Controllers\Controller;
use App\Http\Requests\Setup\UpsertCustomerUserAccessRequest;
use App\Http\Resources\CustomerUserAccessResource;
use App\Models\CustomerUserAccess;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class CustomerUserAccessController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        return CustomerUserAccessResource::collection(CustomerUserAccess::query()->latest()->get());
    }

    public function store(UpsertCustomerUserAccessRequest $request): CustomerUserAccessResource
    {
        return new CustomerUserAccessResource(
            CustomerUserAccess::query()->create($this->payload($request->validated())),
        );
    }

    public function show(CustomerUserAccess $customerUserAccess): CustomerUserAccessResource
    {
        return new CustomerUserAccessResource($customerUserAccess);
    }

    public function update(UpsertCustomerUserAccessRequest $request, CustomerUserAccess $customerUserAccess): CustomerUserAccessResource
    {
        $customerUserAccess->update($this->payload($request->validated()));

        return new CustomerUserAccessResource($customerUserAccess);
    }

    public function destroy(CustomerUserAccess $customerUserAccess): JsonResponse
    {
        $customerUserAccess->delete();

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
            'customer_id' => $data['customer_id'] ?? null,
            'customer_name' => $data['customer_name'] ?? null,
            'access_level' => $data['access_level'] ?? null,
            'can_create_ticket' => $data['can_create_ticket'] ?? false,
            'can_view_attachments' => $data['can_view_attachments'] ?? false,
            'can_reply' => $data['can_reply'] ?? false,
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
