<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('journal_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('journal_entry_id')
                ->constrained()
                ->cascadeOnUpdate()
                ->cascadeOnDelete();
            $table->foreignId('account_id')
                ->constrained()
                ->cascadeOnUpdate()
                ->restrictOnDelete();
            $table->decimal('debit', 18, 2)->default(0);
            $table->decimal('credit', 18, 2)->default(0);
            $table->text('description')->nullable();
            $table->timestamps();
        });

        DB::statement('ALTER TABLE journal_lines ADD CONSTRAINT chk_journal_lines_debit_non_negative CHECK (debit >= 0)');
        DB::statement('ALTER TABLE journal_lines ADD CONSTRAINT chk_journal_lines_credit_non_negative CHECK (credit >= 0)');
        DB::statement('ALTER TABLE journal_lines ADD CONSTRAINT chk_journal_lines_single_sided CHECK ((debit > 0 AND credit = 0) OR (credit > 0 AND debit = 0))');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('journal_lines');
    }
};
