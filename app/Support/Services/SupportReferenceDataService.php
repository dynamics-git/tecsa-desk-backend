<?php

namespace App\Support\Services;

use App\Support\Repositories\SupportReferenceDataRepository;

final readonly class SupportReferenceDataService
{
    public function __construct(
        private SupportReferenceDataRepository $referenceData,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function all(): array
    {
        return $this->referenceData->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function teams(): array
    {
        return $this->referenceData->teams();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function categories(): array
    {
        return $this->referenceData->categories();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function agents(): array
    {
        return $this->referenceData->agents();
    }
}
