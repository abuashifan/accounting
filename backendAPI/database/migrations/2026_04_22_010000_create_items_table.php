<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('items', function (Blueprint $table): void {
            $table->id();
            $table->string('code')->unique();
            $table->string('name');
            $table->string('type'); // inventory / service / non-inventory
            $table->string('unit');
            $table->decimal('selling_price', 18, 2)->default(0);
            $table->string('cost_method'); // average / fifo / lifo

            $table->foreignId('inventory_account_id')->constrained('accounts');
            $table->foreignId('cogs_account_id')->constrained('accounts');
            $table->foreignId('revenue_account_id')->constrained('accounts');
            $table->foreignId('inventory_adjustment_account_id')->constrained('accounts');
            $table->foreignId('goods_in_transit_account_id')->constrained('accounts');

            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('items');
    }
};

