<?php

namespace Database\Seeders;

use App\Models\AccountingPeriod;
use Illuminate\Database\Seeder;

class AccountingPeriodSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        AccountingPeriod::query()->updateOrCreate(
            [
                'start_date' => '2026-01-01',
                'end_date' => '2026-12-31',
            ],
            [
                'is_closed' => false,
                'locked_by' => null,
                'locked_at' => null,
            ]
        );
    }
}
