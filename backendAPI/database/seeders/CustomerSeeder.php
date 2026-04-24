<?php
// database/seeders/CustomerSeeder.php

namespace Database\Seeders;

use App\Domains\Accounting\Models\Customer;
use Illuminate\Database\Seeder;

class CustomerSeeder extends Seeder
{
    public function run(): void
    {
        $customers = [
            [
                'code' => 'CUST-001',
                'name' => 'PT. Maju Jaya',
                'email' => 'info@majujaya.com',
                'phone' => '021-12345678',
                'address' => 'Jl. Sudirman No. 123',
                'city' => 'Jakarta',
                'province' => 'DKI Jakarta',
                'postal_code' => '10110',
                'tax_id' => '01.234.567.8-001.000',
                'credit_limit' => 50000000,
            ],
            [
                'code' => 'CUST-002',
                'name' => 'CV. Sukses Abadi',
                'email' => 'admin@suksesabadi.co.id',
                'phone' => '022-98765432',
                'address' => 'Jl. Raya Industri No. 45',
                'city' => 'Bandung',
                'province' => 'Jawa Barat',
                'postal_code' => '40115',
                'tax_id' => '01.987.654.3-002.000',
                'credit_limit' => 25000000,
            ],
        ];

        foreach ($customers as $customer) {
            Customer::query()->updateOrCreate(
                ['code' => $customer['code']],
                $customer + ['is_active' => true]
            );
        }
    }
}