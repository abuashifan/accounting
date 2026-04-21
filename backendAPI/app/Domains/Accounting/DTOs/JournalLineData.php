<?php

namespace App\Domains\Accounting\DTOs;

final readonly class JournalLineData
{
    public function __construct(
        public int $account_id,
        public float $debit,
        public float $credit,
    ) {
    }
}
