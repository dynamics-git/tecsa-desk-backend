<?php

namespace App\Http\Controllers\Api\Setup;

use App\Http\Controllers\Controller;
use App\Http\Requests\Setup\StoreMacroRequest;
use App\Http\Requests\Setup\UpdateMacroRequest;
use App\Http\Resources\SupportMacroResource;
use App\Models\SupportMacro;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class MacroController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        return SupportMacroResource::collection(SupportMacro::query()->orderBy('title')->get());
    }

    public function store(StoreMacroRequest $request): SupportMacroResource
    {
        return new SupportMacroResource(SupportMacro::query()->create($this->payload($request->validated())));
    }

    public function show(SupportMacro $macro): SupportMacroResource
    {
        return new SupportMacroResource($macro);
    }

    public function update(UpdateMacroRequest $request, SupportMacro $macro): SupportMacroResource
    {
        $macro->update($this->payload($request->validated(), false));

        return new SupportMacroResource($macro);
    }

    public function destroy(SupportMacro $macro): JsonResponse
    {
        $macro->delete();

        return response()->json(['success' => true]);
    }

    private function payload(array $data, bool $includeId = true): array
    {
        return array_filter([
            'id' => $includeId ? ($data['id'] ?? null) : null,
            'title' => $data['title'] ?? null,
            'body' => $data['body'] ?? null,
            'visibility' => $data['visibility'] ?? null,
            'is_active' => $data['isActive'] ?? null,
        ], fn ($value): bool => $value !== null);
    }
}
