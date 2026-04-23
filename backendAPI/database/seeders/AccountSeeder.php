<?php

namespace Database\Seeders;

use App\Models\Account;
use Illuminate\Database\Seeder;

class AccountSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $accounts = [
            ['code' => '1000', 'name' => 'Kas', 'type' => 'asset'],
            ['code' => '1100', 'name' => 'Bank', 'type' => 'asset'],
            ['code' => '1200', 'name' => 'Piutang Usaha', 'type' => 'asset'],
            ['code' => '1400', 'name' => 'Persediaan', 'type' => 'asset'],
            ['code' => '1450', 'name' => 'Goods In Transit', 'type' => 'asset'],
            ['code' => '2000', 'name' => 'Hutang', 'type' => 'liability'],
            ['code' => '2100', 'name' => 'Hutang Usaha', 'type' => 'liability'],
            ['code' => '3000', 'name' => 'Modal', 'type' => 'equity'],
            ['code' => '4000', 'name' => 'Pendapatan', 'type' => 'revenue'],
            ['code' => '5000', 'name' => 'Beban', 'type' => 'expense'],
            ['code' => '5100', 'name' => 'Harga Pokok Penjualan (HPP)', 'type' => 'expense'],
            ['code' => '5200', 'name' => 'Penyesuaian Persediaan', 'type' => 'expense'],
        ];

        foreach ($accounts as $account) {
            Account::query()->updateOrCreate(
                ['code' => $account['code']],
                [
                    'name' => $account['name'],
                    'type' => $account['type'],
                    'parent_id' => null,
                    'is_active' => true,
                ]
            );
        }
    }
}
