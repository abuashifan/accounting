<?php

namespace Tests\Feature\Accounting;

use App\Domains\Accounting\Services\PurchaseInvoiceService;
use App\Models\Account;
use App\Models\AccountingPeriod;
use App\Models\AppSetting;
use App\Models\JournalEntry;
use App\Models\Item;
use App\Models\Permission;
use App\Models\PurchaseInvoice;
use App\Models\Role;
use App\Models\StockBalance;
use App\Models\StockMovement;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

class PurchaseInvoiceInventoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_purchase_invoice_is_draft_when_auto_post_off_and_posting_increases_stock_and_posts_journal(): void
    {
        AppSetting::setBool('journals.auto_post', false);

        $ctx = $this->bootstrapInventoryContext(includeJournalUpdate: true);

        $service = app(PurchaseInvoiceService::class);

        /** @var PurchaseInvoice $pi */
        $pi = $service->create([
            'invoice_no' => 'PI-0001',
            'invoice_date' => '2026-04-23',
            'lines' => [[
                'item_id' => $ctx['item']->id,
                'warehouse_id' => $ctx['warehouse']->id,
                'quantity' => 5,
                'unit_cost' => 100,
            ]],
        ]);

        $pi->refresh()->load('journalEntry');
        $this->assertNull($pi->posted_at);
        $this->assertSame('draft', $pi->journalEntry->status);
        $this->assertSame(0, StockMovement::query()->count());

        $posted = $service->post($pi->id);
        $posted->refresh()->load('journalEntry.journalLines');

        $this->assertNotNull($posted->posted_at);
        $this->assertSame('posted', $posted->journalEntry->status);

        /** @var StockBalance $balance */
        $balance = StockBalance::query()->where('item_id', $ctx['item']->id)->where('warehouse_id', $ctx['warehouse']->id)->firstOrFail();
        $this->assertSame(5.0, round((float) $balance->quantity, 4));
        $this->assertSame(100.0, round((float) $balance->avg_cost, 2));

        $this->assertDatabaseHas('stock_movements', [
            'type' => 'purchase',
            'reference_type' => 'purchase_invoice',
            'reference_id' => $pi->id,
        ]);

        /** @var JournalEntry $j */
        $j = JournalEntry::query()->with('journalLines')->findOrFail($posted->journal_entry_id);
        $this->assertCount(2, $j->journalLines);

        $this->assertDatabaseHas('journal_lines', [
            'journal_entry_id' => $j->id,
            'account_id' => $ctx['inventory']->id,
            'debit' => 500,
            'credit' => 0,
        ]);

        $apId = Account::query()->where('code', '2100')->value('id');
        $this->assertDatabaseHas('journal_lines', [
            'journal_entry_id' => $j->id,
            'account_id' => $apId,
            'debit' => 0,
            'credit' => 500,
        ]);
    }

    public function test_purchase_invoice_auto_posts_when_auto_post_on(): void
    {
        AppSetting::setBool('journals.auto_post', true);

        $ctx = $this->bootstrapInventoryContext(includeJournalUpdate: false);

        $pi = app(PurchaseInvoiceService::class)->create([
            'invoice_no' => 'PI-0002',
            'invoice_date' => '2026-04-23',
            'lines' => [[
                'item_id' => $ctx['item']->id,
                'warehouse_id' => $ctx['warehouse']->id,
                'quantity' => 2,
                'unit_cost' => 80,
            ]],
        ]);

        $pi->refresh()->load('journalEntry');
        $this->assertNotNull($pi->posted_at);
        $this->assertSame('posted', $pi->journalEntry->status);
        $this->assertSame(1, (int) StockMovement::query()->where('type', 'purchase')->count());
    }

    private function bootstrapInventoryContext(bool $includeJournalUpdate): array
    {
        $permissionCreate = Permission::query()->create(['name' => 'journal.create']);
        $permissionUpdate = Permission::query()->create(['name' => 'journal.update']);

        $role = Role::query()->create(['name' => 'purchase_user']);
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

        $item = Item::query()->create([
            'code' => 'ITEM-P',
            'name' => 'Purchase Test Item',
            'type' => 'inventory',
            'unit' => 'pcs',
            'selling_price' => 0,
            'cost_method' => 'average',
            'inventory_account_id' => $inventory->id,
            'cogs_account_id' => Account::query()->where('code', '5100')->value('id'),
            'revenue_account_id' => Account::query()->where('code', '4000')->value('id'),
            'inventory_adjustment_account_id' => Account::query()->where('code', '5200')->value('id'),
            'goods_in_transit_account_id' => Account::query()->where('code', '1450')->value('id'),
            'is_active' => true,
        ]);

        $warehouse = Warehouse::query()->create(['code' => 'WH-01', 'name' => 'Main']);

        return compact('user', 'inventory', 'item', 'warehouse');
    }
}

