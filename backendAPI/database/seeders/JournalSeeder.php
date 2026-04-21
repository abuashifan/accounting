<?php

namespace Database\Seeders;

use App\Models\JournalEntry;
use Illuminate\Database\Seeder;

class JournalSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        if (JournalEntry::query()->exists()) {
            return;
        }

        JournalEntry::factory()
            ->count(30)
            ->withBalancedLines()
            ->create();
    }
}
