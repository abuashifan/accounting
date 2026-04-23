<?php

namespace App\Domains\Accounting\DTOs;

final readonly class PurchasePaymentData
{
    public function __construct(
        public string $payment_no,
        public int $purchase_invoice_id,
        public string $payment_date,
        public float $amount,
        public int $credit_account_id,
        public ?string $description = null,
    ) {}
}

