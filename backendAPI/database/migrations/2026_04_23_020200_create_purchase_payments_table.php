<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('purchase_payments', function (Blueprint $table): void {
            $table->id();
            $table->string('payment_no')->unique();
            $table->foreignId('purchase_invoice_id')
                ->constrained('purchase_invoices')
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

            $table->index(['purchase_invoice_id', 'payment_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_payments');
    }
};

