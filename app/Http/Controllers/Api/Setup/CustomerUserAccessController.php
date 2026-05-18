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
            'user_id' => $this->normalizeUserId($data['userId'] ?? null),
            'user_name' => $data['userName'] ?? null,
            'user_email' => $data['userEmail'] ?? null,
            'customer_id' => $data['customerId'] ?? null,
            'customer_name' => $data['customerName'] ?? null,
            'access_level' => $data['accessLevel'] ?? null,
            'can_create_ticket' => $data['canCreateTicket'] ?? false,
            'can_view_attachments' => $data['canViewAttachments'] ?? false,
            'can_reply' => $data['canReply'] ?? false,
            'is_active' => $data['isActive'] ?? true,
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
