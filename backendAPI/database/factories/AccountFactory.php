<?php

namespace Database\Factories;

use App\Models\Account;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Account>
 */
class AccountFactory extends Factory
{
    protected $model = Account::class;

    public function definition(): array
    {
        return [
            'code' => $this->faker->unique()->numerify('A####'),
            'name' => $this->faker->words(2, true),
            'type' => 'asset',
            'parent_id' => null,
            'is_active' => true,
        ];
    }
}

