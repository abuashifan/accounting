<?php

namespace Tests\Feature\Accounting;

use App\Domains\Accounting\DTOs\InvoiceData;
use App\Domains\Accounting\DTOs\PaymentData;
use App\Domains\Accounting\Services\InvoiceService;
use App\Domains\Accounting\Services\PaymentService;
use App\Models\Account;
use App\Models\AccountingPeriod;
use App\Models\AppSetting;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AutoJournalEngineTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->createOpenPeriod();
        $this->createBaseAccounts();
        AppSetting::setBool('journals.auto_post', true);
    }

    public function test_invoice_service_creates_invoice_and_posted_auto_journal(): void
    {
        $user = $this->createUserWithJournalCreatePermission();
        $this->actingAs($user);

        $invoice = app(InvoiceService::class)->create(new InvoiceData(
            invoice_no: 'INV-2026-0001',
            invoice_date: '2026-04-21',
            amount: 1500,
            description: 'Invoice jasa April',
        ));

        $invoice->load('journalEntry.journalLines.account');

        $this->assertInstanceOf(Invoice::class, $invoice);
        $this->assertSame('unpaid', $invoice->status);
        $this->assertSame('posted', $invoice->journalEntry->status);
        $this->assertCount(2, $invoice->journalEntry->journalLines);
        $this->assertDatabaseHas('journal_lines', [
            'journal_entry_id' => $invoice->journal_entry_id,
            'account_id' => Account::query()->where('code', '1200')->value('id'),
            'debit' => 1500,
            'credit' => 0,
        ]);
        $this->assertDatabaseHas('journal_lines', [
            'journal_entry_id' => $invoice->journal_entry_id,
            'account_id' => Account::query()->where('code', '4000')->value('id'),
            'debit' => 0,
            'credit' => 1500,
        ]);
    }

    public function test_payment_service_records_payment_updates_invoice_and_posts_cash_vs_receivable_journal(): void
    {
        $user = $this->createUserWithJournalCreatePermission();
        $this->actingAs($user);

        $invoice = app(InvoiceService::class)->create(new InvoiceData(
            invoice_no: 'INV-2026-0002',
            invoice_date: '2026-04-21',
            amount: 2000,
            description: 'Invoice proyek',
        ));

        $payment = app(PaymentService::class)->record(new PaymentData(
            payment_no: 'PAY-2026-0001',
            invoice_id: $invoice->id,
            payment_date: '2026-04-22',
            amount: 2000,
            description: 'Pelunasan invoice proyek',
        ));

        $payment->load('invoice', 'journalEntry.journalLines.account');
        $invoice->refresh();

        $this->assertInstanceOf(Payment::class, $payment);
        $this->assertSame($invoice->id, $payment->invoice_id);
        $this->assertSame('posted', $payment->journalEntry->status);
        $this->assertSame('paid', $invoice->status);
        $this->assertSame('2000.00', $invoice->paid_amount);
        $this->assertDatabaseHas('journal_lines', [
            'journal_entry_id' => $payment->journal_entry_id,
            'account_id' => Account::query()->where('code', '1000')->value('id'),
            'debit' => 2000,
            'credit' => 0,
        ]);
        $this->assertDatabaseHas('journal_lines', [
            'journal_entry_id' => $payment->journal_entry_id,
            'account_id' => Account::query()->where('code', '1200')->value('id'),
            'debit' => 0,
            'credit' => 2000,
        ]);
    }

    private function createOpenPeriod(): AccountingPeriod
    {
        return AccountingPeriod::query()->create([
            'start_date' => '2026-01-01',
            'end_date' => '2026-12-31',
            'is_closed' => false,
            'locked_by' => null,
            'locked_at' => null,
        ]);
    }

    private function createBaseAccounts(): void
    {
        Account::query()->create([
            'code' => '1000',
            'name' => 'Kas',
            'type' => 'asset',
            'parent_id' => null,
            'is_active' => true,
        ]);

        Account::query()->create([
            'code' => '1200',
            'name' => 'Piutang Usaha',
            'type' => 'asset',
            'parent_id' => null,
            'is_active' => true,
        ]);

        Account::query()->create([
            'code' => '4000',
            'name' => 'Pendapatan',
            'type' => 'revenue',
            'parent_id' => null,
            'is_active' => true,
        ]);
    }

    private function createUserWithJournalCreatePermission(): User
    {
        $permissionCreate = Permission::query()->firstOrCreate(['name' => 'journal.create']);
        $role = Role::query()->firstOrCreate(['name' => 'auto_journal_user']);
        $role->permissions()->syncWithoutDetaching([$permissionCreate->id]);

        $user = User::factory()->create();
        $user->roles()->syncWithoutDetaching([$role->id]);

        return $user;
    }
}
