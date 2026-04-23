<?php

namespace Tests\Feature\Accounting;

use App\Domains\Accounting\Services\PurchaseInvoiceService;
use App\Domains\Accounting\Services\PurchaseReturnService;
use App\Models\Account;
use App\Models\AccountingPeriod;
use App\Models\AppSetting;
use App\Models\JournalEntry;
use App\Models\Item;
use App\Models\Permission;
use App\Models\PurchaseInvoice;
use App\Models\PurchaseReturn;
use App\Models\Role;
use App\Models\StockBalance;
use App\Models\StockMovement;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

class PurchaseReturnInventoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_purchase_return_is_draft_and_posting_reduces_stock_and_posts_journal(): void
    {
        $ctx = $this->bootstrapInventoryContext(includeJournalUpdate: true);

        // Create a posted purchase invoice first (auto_post on).
        AppSetting::setBool('journals.auto_post', true);

        /** @var PurchaseInvoice $pi */
        $pi = app(PurchaseInvoiceService::class)->create([
            'invoice_no' => 'PI-RET-0001',
            'invoice_date' => '2026-04-23',
            'lines' => [[
                'item_id' => $ctx['item']->id,
                'warehouse_id' => $ctx['warehouse']->id,
                'quantity' => 5,
                'unit_cost' => 100,
            ]],
        ]);

        $pi->refresh();
        $this->assertNotNull($pi->posted_at);

        /** @var StockBalance $balanceAfterPurchase */
        $balanceAfterPurchase = StockBalance::query()->where('item_id', $ctx['item']->id)->where('warehouse_id', $ctx['warehouse']->id)->firstOrFail();
        $this->assertSame(5.0, round((float) $balanceAfterPurchase->quantity, 4));

        // Now create purchase return with auto_post off (draft).
        AppSetting::setBool('journals.auto_post', false);

        /** @var PurchaseReturn $pr */
        $pr = app(PurchaseReturnService::class)->create([
            'return_no' => 'PR-0001',
            'return_date' => '2026-04-23',
            'purchase_invoice_id' => $pi->id,
            'lines' => [[
                'item_id' => $ctx['item']->id,
                'warehouse_id' => $ctx['warehouse']->id,
                'quantity' => 2,
                'unit_cost' => 100,
            ]],
        ]);

        $pr->refresh()->load(['journalEntry']);

        $this->assertNull($pr->posted_at);
        $this->assertSame('draft', $pr->journalEntry->status);
        $this->assertSame(1, (int) StockMovement::query()->where('type', 'purchase')->count());
        $this->assertSame(0, (int) StockMovement::query()->where('type', 'purchase_return')->count());

        $posted = app(PurchaseReturnService::class)->post($pr->id);
        $posted->refresh()->load(['journalEntry.journalLines']);

        $this->assertNotNull($posted->posted_at);
        $this->assertSame('posted', $posted->journalEntry->status);

        /** @var StockBalance $balance */
        $balance = StockBalance::query()->where('item_id', $ctx['item']->id)->where('warehouse_id', $ctx['warehouse']->id)->firstOrFail();
        $this->assertSame(3.0, round((float) $balance->quantity, 4));

        $this->assertDatabaseHas('stock_movements', [
            'type' => 'purchase_return',
            'reference_type' => 'purchase_return',
            'reference_id' => $pr->id,
            'item_id' => $ctx['item']->id,
            'warehouse_id' => $ctx['warehouse']->id,
        ]);

        /** @var JournalEntry $j */
        $j = JournalEntry::query()->with('journalLines')->findOrFail($posted->journal_entry_id);
        $this->assertCount(2, $j->journalLines);

        $apId = (int) Account::query()->where('code', '2100')->value('id');
        $this->assertDatabaseHas('journal_lines', [
            'journal_entry_id' => $j->id,
            'account_id' => $apId,
            'debit' => 200,
            'credit' => 0,
        ]);
        $this->assertDatabaseHas('journal_lines', [
            'journal_entry_id' => $j->id,
            'account_id' => $ctx['inventory']->id,
            'debit' => 0,
            'credit' => 200,
        ]);
    }

    private function bootstrapInventoryContext(bool $includeJournalUpdate): array
    {
        $permissionCreate = Permission::query()->create(['name' => 'journal.create']);
        $permissionUpdate = Permission::query()->create(['name' => 'journal.update']);

        $role = Role::query()->create(['name' => 'purchase_return_user']);
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
        Account::query()->create(['code' => '1450', 'name' => 'Goods In Transit', 'type' => 'asset', 'parent_id' => null, 'is_active' => true]);
        Account::query()->create(['code' => '2100', 'name' => 'Accounts Payable', 'type' => 'liability', 'parent_id' => null, 'is_active' => true]);
        Account::query()->create(['code' => '4000', 'name' => 'Revenue', 'type' => 'revenue', 'parent_id' => null, 'is_active' => true]);
        Account::query()->create(['code' => '5100', 'name' => 'COGS', 'type' => 'expense', 'parent_id' => null, 'is_active' => true]);
        Account::query()->create(['code' => '5200', 'name' => 'Inv Adj', 'type' => 'expense', 'parent_id' => null, 'is_active' => true]);
        Account::query()->create(['code' => '1200', 'name' => 'Accounts Receivable', 'type' => 'asset', 'parent_id' => null, 'is_active' => true]);

        $item = Item::query()->create([
            'code' => 'ITEM-PR',
            'name' => 'Purchase Return Item',
            'type' => 'inventory',
            'unit' => 'pcs',
            'selling_price' => 0,
            'cost_method' => 'average',
            'inventory_account_id' => $inventory->id,
            'cogs_account_id' => (int) Account::query()->where('code', '5100')->value('id'),
            'revenue_account_id' => (int) Account::query()->where('code', '4000')->value('id'),
            'inventory_adjustment_account_id' => (int) Account::query()->where('code', '5200')->value('id'),
            'goods_in_transit_account_id' => (int) Account::query()->where('code', '1450')->value('id'),
            'is_active' => true,
        ]);

        $warehouse = Warehouse::query()->create(['code' => 'WH-01', 'name' => 'Main']);

        return compact('user', 'inventory', 'item', 'warehouse');
    }
}

