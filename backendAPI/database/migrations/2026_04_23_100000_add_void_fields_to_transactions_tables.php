<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * @var list<string>
     */
    private array $tables = [
        'invoices',
        'payments',
        'purchase_invoices',
        'purchase_payments',
        'sales_returns',
        'purchase_returns',
    ];

    public function up(): void
    {
        foreach ($this->tables as $tableName) {
            Schema::table($tableName, function (Blueprint $table) use ($tableName): void {
                if (! Schema::hasColumn($tableName, 'voided_at')) {
                    $table->timestamp('voided_at')->nullable()->index();
                }
                if (! Schema::hasColumn($tableName, 'void_reason')) {
                    $table->text('void_reason')->nullable();
                }
                if (! Schema::hasColumn($tableName, 'voided_by')) {
                    $table->foreignId('voided_by')
                        ->nullable()
                        ->constrained('users')
                        ->cascadeOnUpdate()
                        ->nullOnDelete();
                }
            });
        }
    }

    public function down(): void
    {
        foreach ($this->tables as $tableName) {
            Schema::table($tableName, function (Blueprint $table) use ($tableName): void {
                if (Schema::hasColumn($tableName, 'voided_by')) {
                    $table->dropConstrainedForeignId('voided_by');
                }
                if (Schema::hasColumn($tableName, 'void_reason')) {
                    $table->dropColumn('void_reason');
                }
                if (Schema::hasColumn($tableName, 'voided_at')) {
                    $table->dropColumn('voided_at');
                }
            });
        }
    }
};

