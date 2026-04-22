<?php

namespace Tests\Feature\Accounting;

use App\Domains\Accounting\Services\StockService;
use App\Models\Account;
use App\Models\AccountingPeriod;
use App\Models\AppSetting;
use App\Models\JournalEntry;
use App\Models\JournalLine;
use App\Models\Item;
use App\Models\Permission;
use App\Models\Role;
use App\Models\StockBalance;
use App\Models\StockMovement;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

class InventoryEngineTest extends TestCase
{
    use RefreshDatabase;

    public function test_average_cost_calculation_is_correct_for_multiple_purchases(): void
    {
        $ctx = $this->bootstrapInventoryContext();

        $stock = app(StockService::class);

        $stock->purchase([
            'date' => '2026-02-01',
            'accounting_period_id' => $ctx['period']->id,
            'item_id' => $ctx['item']->id,
            'warehouse_id' => $ctx['warehouse']->id,
            'quantity' => 10,
            'unit_cost' => 100,
            'credit_account_id' => $ctx['ap']->id,
            'description' => 'Purchase 1',
        ]);

        $stock->purchase([
            'date' => '2026-02-02',
            'accounting_period_id' => $ctx['period']->id,
            'item_id' => $ctx['item']->id,
            'warehouse_id' => $ctx['warehouse']->id,
            'quantity' => 5,
            'unit_cost' => 160,
            'credit_account_id' => $ctx['ap']->id,
            'description' => 'Purchase 2',
        ]);

        /** @var StockBalance $balance */
        $balance = StockBalance::query()
            ->where('item_id', $ctx['item']->id)
            ->where('warehouse_id', $ctx['warehouse']->id)
            ->firstOrFail();

        // new_avg = (10*100 + 5*160) / 15 = 120
        $this->assertSame(15.0, round((float) $balance->quantity, 4));
        $this->assertSame(120.0, round((float) $balance->avg_cost, 2));
    }

    public function test_stock_increases_on_purchase_and_records_stock_movement_and_journal(): void
    {
        $ctx = $this->bootstrapInventoryContext();

        $result = app(StockService::class)->purchase([
            'date' => '2026-03-01',
            'accounting_period_id' => $ctx['period']->id,
            'item_id' => $ctx['item']->id,
            'warehouse_id' => $ctx['warehouse']->id,
            'quantity' => 3,
            'unit_cost' => 50,
            'credit_account_id' => $ctx['cash']->id,
            'description' => 'Cash purchase',
        ]);

        $this->assertSame(3.0, round((float) $result['stock_balance']->quantity, 4));
        $this->assertSame(50.0, round((float) $result['stock_balance']->avg_cost, 2));

        $this->assertDatabaseHas('stock_movements', [
            'id' => $result['movement_id'],
            'type' => 'purchase',
            'item_id' => $ctx['item']->id,
            'warehouse_id' => $ctx['warehouse']->id,
        ]);

        $this->assertDatabaseHas('journal_entries', [
            'id' => $result['journal_entry_id'],
            'status' => 'posted',
        ]);
    }

    public function test_stock_decreases_on_sale_and_creates_revenue_and_cogs_journal(): void
    {
        $ctx = $this->bootstrapInventoryContext();
        $stock = app(StockService::class);

        $stock->purchase([
            'date' => '2026-03-10',
            'accounting_period_id' => $ctx['period']->id,
            'item_id' => $ctx['item']->id,
            'warehouse_id' => $ctx['warehouse']->id,
            'quantity' => 10,
            'unit_cost' => 100,
            'credit_account_id' => $ctx['ap']->id,
            'description' => 'Purchase',
        ]);

        $result = $stock->sale([
            'date' => '2026-03-11',
            'accounting_period_id' => $ctx['period']->id,
            'item_id' => $ctx['item']->id,
            'warehouse_id' => $ctx['warehouse']->id,
            'quantity' => 4,
            'unit_price' => 200,
            'debit_account_id' => $ctx['ar']->id,
            'description' => 'Sale',
        ]);

        $this->assertSame(6.0, round((float) $result['stock_balance']->quantity, 4));
        $this->assertSame(100.0, round((float) $result['stock_balance']->avg_cost, 2));
        $this->assertSame(400.0, round((float) $result['cogs_total'], 2));
        $this->assertSame(800.0, round((float) $result['revenue_total'], 2));

        /** @var JournalEntry $journal */
        $journal = JournalEntry::query()->with('journalLines')->findOrFail($result['journal_entry_id']);
        $this->assertSame('posted', $journal->status);
        $this->assertCount(4, $journal->journalLines);

        $totalDebit = round($journal->journalLines->sum(fn ($l) => (float) $l->debit), 2);
        $totalCredit = round($journal->journalLines->sum(fn ($l) => (float) $l->credit), 2);
        $this->assertSame($totalDebit, $totalCredit);
    }

    public function test_stock_movement_is_always_recorded_for_purchase_and_sale(): void
    {
        $ctx = $this->bootstrapInventoryContext();
        $stock = app(StockService::class);

        $this->assertSame(0, StockMovement::query()->count());

        $stock->purchase([
            'date' => '2026-04-01',
            'accounting_period_id' => $ctx['period']->id,
            'item_id' => $ctx['item']->id,
            'warehouse_id' => $ctx['warehouse']->id,
            'quantity' => 2,
            'unit_cost' => 100,
            'credit_account_id' => $ctx['ap']->id,
            'description' => 'Purchase',
        ]);

        $stock->sale([
            'date' => '2026-04-02',
            'accounting_period_id' => $ctx['period']->id,
            'item_id' => $ctx['item']->id,
            'warehouse_id' => $ctx['warehouse']->id,
            'quantity' => 1,
            'unit_price' => 150,
            'debit_account_id' => $ctx['cash']->id,
            'description' => 'Sale',
        ]);

        $this->assertSame(2, StockMovement::query()->count());
        $this->assertSame(1, (int) StockMovement::query()->where('type', 'purchase')->count());
        $this->assertSame(1, (int) StockMovement::query()->where('type', 'sale')->count());
    }

    public function test_stock_value_equals_gl_inventory_balance_after_transactions(): void
    {
        $ctx = $this->bootstrapInventoryContext();
        $stock = app(StockService::class);

        // Purchase: 10 @ 100 (Inventory +1000)
        $stock->purchase([
            'date' => '2026-04-10',
            'accounting_period_id' => $ctx['period']->id,
            'item_id' => $ctx['item']->id,
            'warehouse_id' => $ctx['warehouse']->id,
            'quantity' => 10,
            'unit_cost' => 100,
            'credit_account_id' => $ctx['ap']->id,
            'description' => 'Purchase',
        ]);

        // Sale: 4 units (Inventory -400)
        $stock->sale([
            'date' => '2026-04-11',
            'accounting_period_id' => $ctx['period']->id,
            'item_id' => $ctx['item']->id,
            'warehouse_id' => $ctx['warehouse']->id,
            'quantity' => 4,
            'unit_price' => 250,
            'debit_account_id' => $ctx['ar']->id,
            'description' => 'Sale',
        ]);

        $stockValue = StockBalance::query()
            ->where('item_id', $ctx['item']->id)
            ->where('warehouse_id', $ctx['warehouse']->id)
            ->get()
            ->sum(fn (StockBalance $b) => round((float) $b->quantity, 4) * round((float) $b->avg_cost, 6));

        $stockValue = round((float) $stockValue, 2); // remaining 6 * 100 = 600

        $glInventory = JournalLine::query()
            ->join('journal_entries', 'journal_entries.id', '=', 'journal_lines.journal_entry_id')
            ->where('journal_entries.status', 'posted')
            ->where('journal_lines.account_id', $ctx['inventory']->id)
            ->selectRaw('COALESCE(SUM(journal_lines.debit), 0) as total_debit')
            ->selectRaw('COALESCE(SUM(journal_lines.credit), 0) as total_credit')
            ->first();

        $glBalance = round(((float) ($glInventory?->total_debit ?? 0)) - ((float) ($glInventory?->total_credit ?? 0)), 2);

        $this->assertSame(600.0, $stockValue);
        $this->assertSame($stockValue, $glBalance);
    }

    /**
     * @return array{
     *   user: User,
     *   period: AccountingPeriod,
     *   inventory: Account,
     *   cogs: Account,
     *   revenue: Account,
     *   adjustment: Account,
     *   git: Account,
     *   cash: Account,
     *   ap: Account,
     *   ar: Account,
     *   item: Item,
     *   warehouse: Warehouse
     * }
     */
    private function bootstrapInventoryContext(): array
    {
        AppSetting::setBool('journals.auto_post', true);

        $permissionCreate = Permission::query()->create(['name' => 'journal.create']);
        $permissionUpdate = Permission::query()->create(['name' => 'journal.update']);
        $role = Role::query()->create(['name' => 'inventory_user']);
        $role->permissions()->sync([$permissionCreate->id, $permissionUpdate->id]);

        $user = User::factory()->create();
        $user->roles()->sync([$role->id]);

        Auth::login($user);

        $period = AccountingPeriod::query()->create([
            'start_date' => '2026-01-01',
            'end_date' => '2026-12-31',
            'is_closed' => false,
            'locked_by' => null,
            'locked_at' => null,
        ]);

        $inventory = Account::query()->create(['code' => '1400', 'name' => 'Inventory', 'type' => 'asset', 'parent_id' => null, 'is_active' => true]);
        $cogs = Account::query()->create(['code' => '5100', 'name' => 'COGS', 'type' => 'expense', 'parent_id' => null, 'is_active' => true]);
        $revenue = Account::query()->create(['code' => '4100', 'name' => 'Sales', 'type' => 'revenue', 'parent_id' => null, 'is_active' => true]);
        $adjustment = Account::query()->create(['code' => '5200', 'name' => 'Inventory Adjustment', 'type' => 'expense', 'parent_id' => null, 'is_active' => true]);
        $git = Account::query()->create(['code' => '1450', 'name' => 'Goods In Transit', 'type' => 'asset', 'parent_id' => null, 'is_active' => true]);

        $cash = Account::query()->create(['code' => '1000', 'name' => 'Cash', 'type' => 'asset', 'parent_id' => null, 'is_active' => true]);
        $ap = Account::query()->create(['code' => '2000', 'name' => 'Accounts Payable', 'type' => 'liability', 'parent_id' => null, 'is_active' => true]);
        $ar = Account::query()->create(['code' => '1200', 'name' => 'Accounts Receivable', 'type' => 'asset', 'parent_id' => null, 'is_active' => true]);

        $item = Item::query()->create([
            'code' => 'ITEM-001',
            'name' => 'Test Item',
            'type' => 'inventory',
            'unit' => 'pcs',
            'selling_price' => 200,
            'cost_method' => 'average',
            'inventory_account_id' => $inventory->id,
            'cogs_account_id' => $cogs->id,
            'revenue_account_id' => $revenue->id,
            'inventory_adjustment_account_id' => $adjustment->id,
            'goods_in_transit_account_id' => $git->id,
            'is_active' => true,
        ]);

        $warehouse = Warehouse::query()->create([
            'code' => 'WH-01',
            'name' => 'Main Warehouse',
        ]);

        return compact('user', 'period', 'inventory', 'cogs', 'revenue', 'adjustment', 'git', 'cash', 'ap', 'ar', 'item', 'warehouse');
    }
}
