<?php

namespace Database\Factories;

use App\Models\Account;
use App\Models\Item;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Item>
 */
class ItemFactory extends Factory
{
    protected $model = Item::class;

    public function definition(): array
    {
        /** @var Account $inventory */
        $inventory = Account::factory()->create(['type' => 'asset']);
        /** @var Account $cogs */
        $cogs = Account::factory()->create(['type' => 'expense']);
        /** @var Account $revenue */
        $revenue = Account::factory()->create(['type' => 'revenue']);
        /** @var Account $adjustment */
        $adjustment = Account::factory()->create(['type' => 'expense']);
        /** @var Account $git */
        $git = Account::factory()->create(['type' => 'asset']);

        return [
            'code' => $this->faker->unique()->numerify('ITEM-#####'),
            'name' => $this->faker->words(3, true),
            'type' => 'inventory',
            'unit' => 'pcs',
            'selling_price' => 0,
            'cost_method' => 'average',
            'inventory_account_id' => $inventory->id,
            'cogs_account_id' => $cogs->id,
            'revenue_account_id' => $revenue->id,
            'inventory_adjustment_account_id' => $adjustment->id,
            'goods_in_transit_account_id' => $git->id,
            'is_active' => true,
        ];
    }
}

