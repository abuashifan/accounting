<?php

namespace App\Domains\Accounting\DTOs;

final readonly class InvoiceData
{
    public function __construct(
        public string $invoice_no,
        public string $invoice_date,
        public float $amount,
        public ?int $customer_id = null,
        public ?string $description = null,
    ) {}
}
