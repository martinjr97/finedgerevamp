<?php

namespace App\Services\Loans\DTOs;

use Carbon\Carbon;

class ManualDisbursementDTO
{
    public function __construct(
        public readonly string $sourceType,
        public readonly int $sourceId,
        public readonly string $referenceNumber,
        public readonly Carbon $disbursementDate,
        public readonly ?string $description = null,
    ) {}
}
