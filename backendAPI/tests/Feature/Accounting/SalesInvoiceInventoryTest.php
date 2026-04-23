<?php

namespace Tests\Feature\Accounting;

use App\Domains\Accounting\Services\InvoiceService;
use App\Models\Account;
use App\Models\AccountingPeriod;
use App\Models\AppSetting;
use App\Models\Invoice;
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

class SalesInvoiceInventoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_sales_invoice_is_draft_when_auto_post_off_and_posts_reduce_stock_and_add_cogs_lines(): void
    {
        AppSetting::setBool('journals.auto_post', false);

        $ctx = $this->bootstrapInventoryContext(includeJournalUpdate: true);

        StockBalance::query()->create([
            'item_id' => $ctx['item']->id,
            'warehouse_id' => $ctx['warehouse']->id,
            'quantity' => 10,
            'avg_cost' => 100,
        ]);

        /** @var Invoice $invoice */
        $invoice = app(InvoiceService::class)->createSales([
            'invoice_no' => 'INV-S-0001',
            'invoice_date' => '2026-04-23',
            'description' => 'Sales inventory',
            'lines' => [[
                'item_id' => $ctx['item']->id,
                'warehouse_id' => $ctx['warehouse']->id,
                'quantity' => 3,
                'unit_price' => 200,
            ]],
        ]);

        $invoice->refresh()->load(['journalEntry', 'invoiceLines']);

        $this->assertNull($invoice->posted_at);
        $this->assertSame('draft', $invoice->journalEntry->status);
        $this->assertSame(0, StockMovement::query()->count());
        $this->assertSame(10.0, round((float) StockBalance::query()->firstOrFail()->quantity, 4));

        $posted = app(InvoiceService::class)->postSales($invoice->id);

        $posted->refresh()->load(['journalEntry.journalLines', 'invoiceLines']);
        $this->assertNotNull($posted->posted_at);
        $this->assertSame('posted', $posted->journalEntry->status);

        /** @var StockBalance $balance */
        $balance = StockBalance::query()->where('item_id', $ctx['item']->id)->where('warehouse_id', $ctx['warehouse']->id)->firstOrFail();
        $this->assertSame(7.0, round((float) $balance->quantity, 4));

        $this->assertDatabaseHas('stock_movements', [
            'type' => 'sale',
            'item_id' => $ctx['item']->id,
            'warehouse_id' => $ctx['warehouse']->id,
            'reference_type' => 'invoice',
            'reference_id' => $invoice->id,
        ]);

        /** @var JournalEntry $journal */
        $journal = JournalEntry::query()->with('journalLines')->findOrFail($posted->journal_entry_id);
        $this->assertCount(4, $journal->journalLines);

        $arId = Account::query()->where('code', '1200')->value('id');
        $this->assertDatabaseHas('journal_lines', [
            'journal_entry_id' => $journal->id,
            'account_id' => $arId,
            'debit' => 600,
            'credit' => 0,
        ]);

        $this->assertDatabaseHas('journal_lines', [
            'journal_entry_id' => $journal->id,
            'account_id' => $ctx['revenue']->id,
            'debit' => 0,
            'credit' => 600,
        ]);

        $this->assertDatabaseHas('journal_lines', [
            'journal_entry_id' => $journal->id,
            'account_id' => $ctx['cogs']->id,
            'debit' => 300,
            'credit' => 0,
        ]);

        $this->assertDatabaseHas('journal_lines', [
            'journal_entry_id' => $journal->id,
            'account_id' => $ctx['inventory']->id,
            'debit' => 0,
            'credit' => 300,
        ]);
    }

    public function test_sales_invoice_auto_posts_when_auto_post_on(): void
    {
        AppSetting::setBool('journals.auto_post', true);

        $ctx = $this->bootstrapInventoryContext(includeJournalUpdate: false); // auto-post should not require journal.update

        StockBalance::query()->create([
            'item_id' => $ctx['item']->id,
            'warehouse_id' => $ctx['warehouse']->id,
            'quantity' => 5,
            'avg_cost' => 50,
        ]);

        $invoice = app(InvoiceService::class)->createSales([
            'invoice_no' => 'INV-S-0002',
            'invoice_date' => '2026-04-23',
            'lines' => [[
                'item_id' => $ctx['item']->id,
                'warehouse_id' => $ctx['warehouse']->id,
                'quantity' => 2,
                'unit_price' => 80,
            ]],
        ]);

        $invoice->refresh()->load(['journalEntry', 'invoiceLines']);

        $this->assertNotNull($invoice->posted_at);
        $this->assertSame('posted', $invoice->journalEntry->status);
        $this->assertSame(1, StockMovement::query()->where('type', 'sale')->count());

        /** @var StockBalance $balance */
        $balance = StockBalance::query()->where('item_id', $ctx['item']->id)->where('warehouse_id', $ctx['warehouse']->id)->firstOrFail();
        $this->assertSame(3.0, round((float) $balance->quantity, 4));
    }

    private function bootstrapInventoryContext(bool $includeJournalUpdate): array
    {
        $permissionCreate = Permission::query()->create(['name' => 'journal.create']);
        $permissionUpdate = Permission::query()->create(['name' => 'journal.update']);

        $role = Role::query()->create(['name' => 'sales_user']);
        $perms = [$permissionCreate->id];
        if ($includeJournalUpdate) {
            $perms[] = $permissionUpdate->id;
        }
        $role->permissions()->sync($perms);

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
        $revenue = Account::query()->create(['code' => '4000', 'name' => 'Sales', 'type' => 'revenue', 'parent_id' => null, 'is_active' => true]);
        $adjustment = Account::query()->create(['code' => '5200', 'name' => 'Inventory Adjustment', 'type' => 'expense', 'parent_id' => null, 'is_active' => true]);
        $git = Account::query()->create(['code' => '1450', 'name' => 'Goods In Transit', 'type' => 'asset', 'parent_id' => null, 'is_active' => true]);
        Account::query()->create(['code' => '1200', 'name' => 'Accounts Receivable', 'type' => 'asset', 'parent_id' => null, 'is_active' => true]);

        $item = Item::query()->create([
            'code' => 'ITEM-INV',
            'name' => 'Inventory Item',
            'type' => 'inventory',
            'unit' => 'pcs',
            'selling_price' => 0,
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
            'name' => 'Main',
        ]);

        return compact('user', 'period', 'inventory', 'cogs', 'revenue', 'item', 'warehouse');
    }
}

