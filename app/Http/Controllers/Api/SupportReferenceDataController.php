<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Support\Services\SupportReferenceDataService;
use Illuminate\Http\JsonResponse;

final class SupportReferenceDataController extends Controller
{
    public function __construct(
        private readonly SupportReferenceDataService $referenceData,
    ) {}

    /**
     * GET /api/support/reference-data
     *
     * Returns all support master data needed by dropdowns, filters, and assignment controls.
     */
    public function index(): JsonResponse
    {
        return response()->json($this->referenceData->all());
    }

    public function teams(): JsonResponse
    {
        return response()->json($this->referenceData->teams());
    }

    public function categories(): JsonResponse
    {
        return response()->json($this->referenceData->categories());
    }

    public function agents(): JsonResponse
    {
        return response()->json($this->referenceData->agents());
    }
}
