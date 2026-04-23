<?php

namespace Tests\Feature\Accounting;

use App\Domains\Accounting\Services\InvoiceService;
use App\Domains\Accounting\Services\SalesReturnService;
use App\Models\Account;
use App\Models\AccountingPeriod;
use App\Models\AppSetting;
use App\Models\Invoice;
use App\Models\JournalEntry;
use App\Models\Item;
use App\Models\Permission;
use App\Models\Role;
use App\Models\SalesReturn;
use App\Models\StockBalance;
use App\Models\StockMovement;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

class SalesReturnInventoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_sales_return_is_draft_and_posting_increases_stock_and_posts_journal(): void
    {
        $ctx = $this->bootstrapInventoryContext(includeJournalUpdate: true);

        // Create a posted sales invoice first (auto_post on).
        AppSetting::setBool('journals.auto_post', true);

        StockBalance::query()->create([
            'item_id' => $ctx['item']->id,
            'warehouse_id' => $ctx['warehouse']->id,
            'quantity' => 10,
            'avg_cost' => 100,
        ]);

        /** @var Invoice $invoice */
        $invoice = app(InvoiceService::class)->createSales([
            'invoice_no' => 'INV-S-RET-0001',
            'invoice_date' => '2026-04-23',
            'lines' => [[
                'item_id' => $ctx['item']->id,
                'warehouse_id' => $ctx['warehouse']->id,
                'quantity' => 3,
                'unit_price' => 200,
            ]],
        ]);

        $invoice->refresh();
        $this->assertNotNull($invoice->posted_at);

        /** @var StockBalance $balanceAfterSale */
        $balanceAfterSale = StockBalance::query()->where('item_id', $ctx['item']->id)->where('warehouse_id', $ctx['warehouse']->id)->firstOrFail();
        $this->assertSame(7.0, round((float) $balanceAfterSale->quantity, 4));

        // Now create sales return with auto_post off (draft).
        AppSetting::setBool('journals.auto_post', false);

        /** @var SalesReturn $sr */
        $sr = app(SalesReturnService::class)->create([
            'return_no' => 'SR-0001',
            'return_date' => '2026-04-23',
            'invoice_id' => $invoice->id,
            'lines' => [[
                'item_id' => $ctx['item']->id,
                'warehouse_id' => $ctx['warehouse']->id,
                'quantity' => 1,
                'unit_price' => 200,
            ]],
        ]);

        $sr->refresh()->load(['journalEntry']);

        $this->assertNull($sr->posted_at);
        $this->assertSame('draft', $sr->journalEntry->status);
        $this->assertSame(1, (int) StockMovement::query()->where('type', 'sale')->count());
        $this->assertSame(0, (int) StockMovement::query()->where('type', 'sales_return')->count());

        $posted = app(SalesReturnService::class)->post($sr->id);
        $posted->refresh()->load(['journalEntry.journalLines']);

        $this->assertNotNull($posted->posted_at);
        $this->assertSame('posted', $posted->journalEntry->status);

        /** @var StockBalance $balance */
        $balance = StockBalance::query()->where('item_id', $ctx['item']->id)->where('warehouse_id', $ctx['warehouse']->id)->firstOrFail();
        $this->assertSame(8.0, round((float) $balance->quantity, 4));

        $this->assertDatabaseHas('stock_movements', [
            'type' => 'sales_return',
            'reference_type' => 'sales_return',
            'reference_id' => $sr->id,
            'item_id' => $ctx['item']->id,
            'warehouse_id' => $ctx['warehouse']->id,
        ]);

        /** @var JournalEntry $j */
        $j = JournalEntry::query()->with('journalLines')->findOrFail($posted->journal_entry_id);
        $this->assertCount(4, $j->journalLines);

        $arId = (int) Account::query()->where('code', '1200')->value('id');
        $this->assertDatabaseHas('journal_lines', [
            'journal_entry_id' => $j->id,
            'account_id' => $ctx['revenue']->id,
            'debit' => 200,
            'credit' => 0,
        ]);
        $this->assertDatabaseHas('journal_lines', [
            'journal_entry_id' => $j->id,
            'account_id' => $arId,
            'debit' => 0,
            'credit' => 200,
        ]);
        $this->assertDatabaseHas('journal_lines', [
            'journal_entry_id' => $j->id,
            'account_id' => $ctx['inventory']->id,
            'debit' => 100,
            'credit' => 0,
        ]);
        $this->assertDatabaseHas('journal_lines', [
            'journal_entry_id' => $j->id,
            'account_id' => $ctx['cogs']->id,
            'debit' => 0,
            'credit' => 100,
        ]);
    }

    private function bootstrapInventoryContext(bool $includeJournalUpdate): array
    {
        $permissionCreate = Permission::query()->create(['name' => 'journal.create']);
        $permissionUpdate = Permission::query()->create(['name' => 'journal.update']);

        $role = Role::query()->create(['name' => 'sales_return_user']);
        $perms = [$permissionCreate->id];
        if ($includeJournalUpdate) {
            $perms[] = $permissionUpdate->id;
        }
        $role->permissions()->sync($perms);

        $user = User::factory()->create();
        $user->roles()->sync([$role->id]);
        Auth::login($user);

        AccountingPeriod::query()->create([
            'start_date' => '2026-01-01',
            'end_date' => '2026-12-31',
            'is_closed' => false,
            'locked_by' => null,
            'locked_at' => null,
        ]);

        $inventory = Account::query()->create(['code' => '1400', 'name' => 'Inventory', 'type' => 'asset', 'parent_id' => null, 'is_active' => true]);
        $cogs = Account::query()->create(['code' => '5100', 'name' => 'COGS', 'type' => 'expense', 'parent_id' => null, 'is_active' => true]);
        $revenue = Account::query()->create(['code' => '4000', 'name' => 'Sales', 'type' => 'revenue', 'parent_id' => null, 'is_active' => true]);
        Account::query()->create(['code' => '1200', 'name' => 'Accounts Receivable', 'type' => 'asset', 'parent_id' => null, 'is_active' => true]);
        Account::query()->create(['code' => '2100', 'name' => 'Accounts Payable', 'type' => 'liability', 'parent_id' => null, 'is_active' => true]);
        Account::query()->create(['code' => '1450', 'name' => 'Goods In Transit', 'type' => 'asset', 'parent_id' => null, 'is_active' => true]);
        Account::query()->create(['code' => '5200', 'name' => 'Inv Adj', 'type' => 'expense', 'parent_id' => null, 'is_active' => true]);

        $item = Item::query()->create([
            'code' => 'ITEM-SR',
            'name' => 'Sales Return Item',
            'type' => 'inventory',
            'unit' => 'pcs',
            'selling_price' => 0,
            'cost_method' => 'average',
            'inventory_account_id' => $inventory->id,
            'cogs_account_id' => $cogs->id,
            'revenue_account_id' => $revenue->id,
            'inventory_adjustment_account_id' => (int) Account::query()->where('code', '5200')->value('id'),
            'goods_in_transit_account_id' => (int) Account::query()->where('code', '1450')->value('id'),
            'is_active' => true,
        ]);

        $warehouse = Warehouse::query()->create([
            'code' => 'WH-01',
            'name' => 'Main',
        ]);

        return compact('user', 'inventory', 'cogs', 'revenue', 'item', 'warehouse');
    }
}

