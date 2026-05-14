<?php

namespace App\Http\Controllers\Api\Setup;

use App\Http\Controllers\Controller;
use App\Http\Requests\Setup\StoreCustomerRequest;
use App\Http\Requests\Setup\UpdateCustomerRequest;
use App\Http\Resources\SupportCustomerResource;
use App\Models\SupportCustomer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class CustomerController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        return SupportCustomerResource::collection(SupportCustomer::query()->orderBy('name')->get());
    }

    public function store(StoreCustomerRequest $request): SupportCustomerResource
    {
        $data = $request->validated();

        return new SupportCustomerResource(SupportCustomer::query()->create($this->payload($data)));
    }

    public function show(SupportCustomer $customer): SupportCustomerResource
    {
        return new SupportCustomerResource($customer);
    }

    public function update(UpdateCustomerRequest $request, SupportCustomer $customer): SupportCustomerResource
    {
        $customer->update($this->payload($request->validated(), includeId: false));

        return new SupportCustomerResource($customer);
    }

    public function destroy(SupportCustomer $customer): JsonResponse
    {
        $customer->delete();

        return response()->json(['success' => true]);
    }

    private function payload(array $data, bool $includeId = true): array
    {
        return array_filter([
            'id' => $includeId ? ($data['id'] ?? null) : null,
            'name' => $data['name'] ?? null,
            'email' => $data['email'] ?? null,
            'phone' => $data['phone'] ?? null,
            'is_active' => $data['isActive'] ?? null,
        ], fn ($value): bool => $value !== null);
    }
}
