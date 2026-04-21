<?php

namespace Database\Factories;

use App\Models\Account;
use App\Models\JournalEntry;
use App\Models\JournalLine;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<JournalLine>
 */
class JournalLineFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $amount = fake()->numberBetween(10000, 1000000);
        $isDebit = fake()->boolean();
        $account = Account::query()->inRandomOrder()->first()
            ?? Account::query()->firstOrCreate(
                ['code' => '1000'],
                ['name' => 'Kas', 'type' => 'asset', 'parent_id' => null, 'is_active' => true]
            );

        return [
            'journal_entry_id' => JournalEntry::query()->first()?->id ?? JournalEntry::factory(),
            'account_id' => $account->id,
            'debit' => $isDebit ? $amount : 0,
            'credit' => $isDebit ? 0 : $amount,
            'description' => fake()->sentence(),
        ];
    }
}
