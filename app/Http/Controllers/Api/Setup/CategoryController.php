<?php

namespace App\Http\Controllers\Api\Setup;

use App\Http\Controllers\Controller;
use App\Http\Requests\Setup\StoreCategoryRequest;
use App\Http\Requests\Setup\UpdateCategoryRequest;
use App\Http\Resources\SupportCategoryResource;
use App\Models\SupportCategory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class CategoryController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        return SupportCategoryResource::collection(SupportCategory::query()->orderBy('name')->get());
    }

    public function store(StoreCategoryRequest $request): SupportCategoryResource
    {
        return new SupportCategoryResource(SupportCategory::query()->create($this->payload($request->validated())));
    }

    public function show(SupportCategory $category): SupportCategoryResource
    {
        return new SupportCategoryResource($category);
    }

    public function update(UpdateCategoryRequest $request, SupportCategory $category): SupportCategoryResource
    {
        $category->update($this->payload($request->validated(), false));

        return new SupportCategoryResource($category);
    }

    public function destroy(SupportCategory $category): JsonResponse
    {
        $category->delete();

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
