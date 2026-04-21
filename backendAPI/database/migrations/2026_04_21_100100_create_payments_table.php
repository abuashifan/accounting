<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->string('payment_no')->unique();
            $table->foreignId('invoice_id')
                ->constrained('invoices')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
            $table->date('payment_date')->index();
            $table->text('description')->nullable();
            $table->decimal('amount', 18, 2);
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
        Schema::dropIfExists('payments');
    }
};
