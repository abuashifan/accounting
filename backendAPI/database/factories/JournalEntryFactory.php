<?php

namespace Database\Factories;

use App\Models\Account;
use App\Models\AccountingPeriod;
use App\Models\JournalEntry;
use App\Models\JournalLine;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;

/**
 * @extends Factory<JournalEntry>
 */
class JournalEntryFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $period = AccountingPeriod::query()->firstOrCreate(
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

        $user = User::query()->firstOrCreate(
            ['email' => 'admin@example.com'],
            [
                'name' => 'Admin',
                'password' => Hash::make('password'),
            ]
        );

        return [
            'journal_no' => sprintf('JRN-%04d-%06d', 2026, fake()->unique()->numberBetween(1, 999999)),
            'date' => fake()->dateTimeBetween(
                $period->start_date->startOfDay(),
                $period->end_date->endOfDay()
            )->format('Y-m-d'),
            'description' => fake()->sentence(),
            'status' => 'posted',
            'accounting_period_id' => $period->id,
            'created_by' => $user->id,
            'updated_by' => null,
        ];
    }

    public function withBalancedLines(): static
    {
        return $this->afterCreating(function (JournalEntry $journalEntry): void {
            $amount = fake()->numberBetween(10000, 1000000);

            $debitAccount = Account::query()
                ->whereIn('type', ['asset', 'expense'])
                ->inRandomOrder()
                ->first()
                ?? Account::query()->firstOrCreate(
                    ['code' => '1000'],
                    ['name' => 'Kas', 'type' => 'asset', 'parent_id' => null, 'is_active' => true]
                );

            $creditAccount = Account::query()
                ->whereIn('type', ['liability', 'equity', 'revenue'])
                ->inRandomOrder()
                ->first()
                ?? Account::query()->firstOrCreate(
                    ['code' => '4000'],
                    ['name' => 'Pendapatan', 'type' => 'revenue', 'parent_id' => null, 'is_active' => true]
                );

            JournalLine::query()->create([
                'journal_entry_id' => $journalEntry->id,
                'account_id' => $debitAccount->id,
                'debit' => $amount,
                'credit' => 0,
                'description' => 'Debit line for '.$journalEntry->journal_no,
            ]);

            JournalLine::query()->create([
                'journal_entry_id' => $journalEntry->id,
                'account_id' => $creditAccount->id,
                'debit' => 0,
                'credit' => $amount,
                'description' => 'Credit line for '.$journalEntry->journal_no,
            ]);
        });
    }
}
