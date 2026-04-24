<?php
// database/seeders/VendorSeeder.php

namespace Database\Seeders;

use App\Domains\Accounting\Models\Vendor;
use Illuminate\Database\Seeder;

class VendorSeeder extends Seeder
{
    public function run(): void
    {
        $vendors = [
            [
                'code' => 'VEND-001',
                'name' => 'PT. Supplier Utama',
                'email' => 'sales@supplierutama.com',
                'phone' => '031-55544433',
                'address' => 'Jl. Pelabuhan No. 78',
                'city' => 'Surabaya',
                'province' => 'Jawa Timur',
                'postal_code' => '60112',
                'tax_id' => '02.345.678.9-001.000',
                'credit_limit' => 100000000,
            ],
            [
                'code' => 'VEND-002',
                'name' => 'CV. Bahan Baku Jaya',
                'email' => 'order@bahanbakujaya.co.id',
                'phone' => '061-77788899',
                'address' => 'Jl. Medan No. 12',
                'city' => 'Medan',
                'province' => 'Sumatera Utara',
                'postal_code' => '20112',
                'tax_id' => '02.876.543.2-002.000',
                'credit_limit' => 75000000,
            ],
        ];

        foreach ($vendors as $vendor) {
            Vendor::query()->updateOrCreate(
                ['code' => $vendor['code']],
                $vendor + ['is_active' => true]
            );
        }
    }
}