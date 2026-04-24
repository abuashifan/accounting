<?php
// app/Domains/Accounting/DTOs/VendorData.php

namespace App\Domains\Accounting\DTOs;

final readonly class VendorData
{
    public function __construct(
        public string $code,
        public string $name,
        public ?string $email = null,
        public ?string $phone = null,
        public ?string $address = null,
        public ?string $city = null,
        public ?string $province = null,
        public ?string $postal_code = null,
        public ?string $tax_id = null,
        public float $credit_limit = 0,
        public ?string $notes = null,
        public bool $is_active = true,
    ) {}
}