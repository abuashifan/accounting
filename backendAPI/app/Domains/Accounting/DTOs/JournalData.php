<?php

namespace App\Domains\Accounting\DTOs;

final readonly class JournalData
{
    /**
     * @param  array<int, JournalLineData>  $lines
     */
    public function __construct(
       public string $date,
        public string $description,
        public int $accounting_period_id,
        public array $lines,
        public ?string $entity_type = null,  // Tambahkan
        public ?int $entity_id = null, 
    ) {
    }
}
