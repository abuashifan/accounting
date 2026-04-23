<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoice_lines', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('invoice_id')
                ->constrained('invoices')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();
            $table->foreignId('item_id')->constrained('items');
            $table->foreignId('warehouse_id')->constrained('warehouses');
            $table->decimal('quantity', 18, 4);
            $table->decimal('unit_price', 18, 2)->default(0);
            $table->decimal('line_total', 18, 2)->default(0);
            $table->timestamps();

            $table->index(['item_id', 'warehouse_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoice_lines');
    }
};

