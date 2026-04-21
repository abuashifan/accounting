<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->string('invoice_no')->unique();
            $table->date('invoice_date')->index();
            $table->text('description')->nullable();
            $table->decimal('amount', 18, 2);
            $table->decimal('paid_amount', 18, 2)->default(0);
            $table->enum('status', ['unpaid', 'partial', 'paid'])->default('unpaid')->index();
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
        Schema::dropIfExists('invoices');
    }
};
