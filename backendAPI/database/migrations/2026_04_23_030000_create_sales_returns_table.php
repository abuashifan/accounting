<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sales_returns', function (Blueprint $table): void {
            $table->id();
            $table->string('return_no')->unique();
            $table->date('return_date')->index();
            $table->text('description')->nullable();
            $table->decimal('amount', 18, 2);
            $table->timestamp('posted_at')->nullable()->index();

            $table->foreignId('invoice_id')
                ->constrained('invoices')
                ->cascadeOnUpdate()
                ->restrictOnDelete();

            $table->foreignId('journal_entry_id')
                ->constrained('journal_entries')
                ->cascadeOnUpdate()
                ->restrictOnDelete();

            $table->foreignId('created_by')
                ->constrained('users')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
            $table->foreignId('updated_by')
                ->nullable()
                ->constrained('users')
                ->cascadeOnUpdate()
                ->nullOnDelete();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sales_returns');
    }
};

