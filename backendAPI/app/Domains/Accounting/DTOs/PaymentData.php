<?php

namespace App\Domains\Accounting\DTOs;

final readonly class PaymentData
{
    public function __construct(
        public string $payment_no,
        public int $invoice_id,
        public string $payment_date,
        public float $amount,
        public ?string $description = null,
    ) {}
}
