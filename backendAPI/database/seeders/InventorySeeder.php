<?php

namespace Database\Seeders;

use App\Models\Account;
use App\Models\Item;
use App\Models\Warehouse;
use Illuminate\Database\Seeder;
use RuntimeException;

class InventorySeeder extends Seeder
{
    public function run(): void
    {
        $inventoryAccountId = $this->accountIdByCode('1400');
        $cogsAccountId = $this->accountIdByCode('5100');
        $revenueAccountId = $this->accountIdByCode('4000');
        $adjustmentAccountId = $this->accountIdByCode('5200');
        $gitAccountId = $this->accountIdByCode('1450');

        $warehouses = [
            ['code' => 'WH-01', 'name' => 'Main Warehouse'],
            ['code' => 'WH-02', 'name' => 'Secondary Warehouse'],
        ];

        foreach ($warehouses as $wh) {
            Warehouse::query()->updateOrCreate(
                ['code' => $wh['code']],
                ['name' => $wh['name']],
            );
        }

        $items = [
            [
                'code' => 'ITEM-001',
                'name' => 'Test Inventory Item A',
                'type' => 'inventory',
                'unit' => 'pcs',
                'selling_price' => 25000,
            ],
            [
                'code' => 'ITEM-002',
                'name' => 'Test Inventory Item B',
                'type' => 'inventory',
                'unit' => 'pcs',
                'selling_price' => 50000,
            ],
            [
                'code' => 'ITEM-003',
                'name' => 'Test Inventory Item C',
                'type' => 'inventory',
                'unit' => 'pcs',
                'selling_price' => 120000,
            ],
        ];

        foreach ($items as $it) {
            Item::query()->updateOrCreate(
                ['code' => $it['code']],
                [
                    'name' => $it['name'],
                    'type' => $it['type'],
                    'unit' => $it['unit'],
                    'selling_price' => $it['selling_price'],
                    'cost_method' => 'average',
                    'inventory_account_id' => $inventoryAccountId,
                    'cogs_account_id' => $cogsAccountId,
                    'revenue_account_id' => $revenueAccountId,
                    'inventory_adjustment_account_id' => $adjustmentAccountId,
                    'goods_in_transit_account_id' => $gitAccountId,
                    'is_active' => true,
                ],
            );
        }
    }

    private function accountIdByCode(string $code): int
    {
        $id = Account::query()->where('code', $code)->value('id');
        if ($id === null) {
            throw new RuntimeException("Missing account seed for code {$code}");
        }

        return (int) $id;
    }
}

