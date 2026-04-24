<?php
// database/migrations/2026_04_24_000300_add_customer_vendor_relations.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Add customer_id to invoices table
        Schema::table('invoices', function (Blueprint $table) {
            if (! Schema::hasColumn('invoices', 'customer_id')) {
                $table->foreignId('customer_id')
                    ->nullable()
                    ->constrained('customers')
                    ->cascadeOnUpdate()
                    ->nullOnDelete()
                    ->after('invoice_no');
            }
        });

        // Add vendor_id to purchase_invoices table
        Schema::table('purchase_invoices', function (Blueprint $table) {
            if (! Schema::hasColumn('purchase_invoices', 'vendor_id')) {
                $table->foreignId('vendor_id')
                    ->nullable()
                    ->constrained('vendors')
                    ->cascadeOnUpdate()
                    ->nullOnDelete()
                    ->after('invoice_no');
            }
        });

        // Add customer_id to sales_returns table
        Schema::table('sales_returns', function (Blueprint $table) {
            if (! Schema::hasColumn('sales_returns', 'customer_id')) {
                $table->foreignId('customer_id')
                    ->nullable()
                    ->constrained('customers')
                    ->cascadeOnUpdate()
                    ->nullOnDelete()
                    ->after('invoice_id');
            }
        });

        // Add vendor_id to purchase_returns table
        Schema::table('purchase_returns', function (Blueprint $table) {
            if (! Schema::hasColumn('purchase_returns', 'vendor_id')) {
                $table->foreignId('vendor_id')
                    ->nullable()
                    ->constrained('vendors')
                    ->cascadeOnUpdate()
                    ->nullOnDelete()
                    ->after('purchase_invoice_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropConstrainedForeignId('customer_id');
        });

        Schema::table('purchase_invoices', function (Blueprint $table) {
            $table->dropConstrainedForeignId('vendor_id');
        });

        Schema::table('sales_returns', function (Blueprint $table) {
            $table->dropConstrainedForeignId('customer_id');
        });

        Schema::table('purchase_returns', function (Blueprint $table) {
            $table->dropConstrainedForeignId('vendor_id');
        });
    }
};