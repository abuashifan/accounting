<?php

namespace Tests\Feature\Accounting;

use App\Domains\Accounting\Services\BalanceSheetService;
use App\Domains\Accounting\Services\CashFlowService;
use App\Domains\Accounting\Services\GeneralLedgerService;
use App\Domains\Accounting\Services\ProfitLossService;
use App\Domains\Accounting\Services\TrialBalanceService;
use App\Models\Account;
use App\Models\AccountingPeriod;
use App\Models\JournalEntry;
use App\Models\JournalLine;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ReportingEngineTest extends TestCase
{
    use RefreshDatabase;

    public function test_trial_balance_is_balanced_for_posted_journals_only(): void
    {
        $user = User::factory()->create();
        $period = $this->createOpenPeriod();
        $accounts = $this->createBaseAccounts();

        $this->createJournal(
            period: $period,
            user: $user,
            journalNo: 'JRN-2026-000101',
            date: '2026-01-05',
            status: 'posted',
            lines: [
                ['account_id' => $accounts['cash']->id, 'debit' => 1000, 'credit' => 0],
                ['account_id' => $accounts['equity']->id, 'debit' => 0, 'credit' => 1000],
            ],
        );

        // Draft journal must be ignored even if it would break the balance.
        $this->createJournal(
            period: $period,
            user: $user,
            journalNo: 'JRN-2026-000102',
            date: '2026-01-06',
            status: 'draft',
            lines: [
                ['account_id' => $accounts['cash']->id, 'debit' => 999, 'credit' => 0],
                ['account_id' => $accounts['equity']->id, 'debit' => 0, 'credit' => 100],
            ],
        );

        $report = app(TrialBalanceService::class)->getTrialBalance(
            periodId: null,
            dateFrom: '2026-01-01',
            dateTo: '2026-01-31',
        );

        $this->assertSame(1000.0, $report['total_debit']);
        $this->assertSame(1000.0, $report['total_credit']);
        $this->assertCount(2, $report['accounts']);
    }

    public function test_general_ledger_running_balance_is_correct_and_ignores_non_posted_journals(): void
    {
        $user = User::factory()->create();
        $period = $this->createOpenPeriod();
        $accounts = $this->createBaseAccounts();

        $this->createJournal(
            period: $period,
            user: $user,
            journalNo: 'JRN-2026-000201',
            date: '2026-01-05',
            status: 'posted',
            lines: [
                ['account_id' => $accounts['cash']->id, 'debit' => 100, 'credit' => 0],
                ['account_id' => $accounts['revenue']->id, 'debit' => 0, 'credit' => 100],
            ],
        );

        $this->createJournal(
            period: $period,
            user: $user,
            journalNo: 'JRN-2026-000202',
            date: '2026-01-10',
            status: 'posted',
            lines: [
                ['account_id' => $accounts['expense']->id, 'debit' => 30, 'credit' => 0],
                ['account_id' => $accounts['cash']->id, 'debit' => 0, 'credit' => 30],
            ],
        );

        $this->createJournal(
            period: $period,
            user: $user,
            journalNo: 'JRN-2026-000203',
            date: '2026-01-12',
            status: 'void',
            lines: [
                ['account_id' => $accounts['cash']->id, 'debit' => 999, 'credit' => 0],
                ['account_id' => $accounts['revenue']->id, 'debit' => 0, 'credit' => 999],
            ],
        );

        $entries = app(GeneralLedgerService::class)->getLedger(
            accountId: $accounts['cash']->id,
            dateFrom: '2026-01-01',
            dateTo: '2026-01-31',
        );

        $this->assertCount(2, $entries);
        $this->assertSame('2026-01-05', $entries[0]['date']);
        $this->assertSame(100.0, $entries[0]['balance']);
        $this->assertSame('2026-01-10', $entries[1]['date']);
        $this->assertSame(70.0, $entries[1]['balance']);
    }

    public function test_profit_and_loss_returns_correct_values(): void
    {
        $user = User::factory()->create();
        $period = $this->createOpenPeriod();
        $accounts = $this->createBaseAccounts();

        $this->createJournal(
            period: $period,
            user: $user,
            journalNo: 'JRN-2026-000301',
            date: '2026-01-07',
            status: 'posted',
            lines: [
                ['account_id' => $accounts['cash']->id, 'debit' => 1000, 'credit' => 0],
                ['account_id' => $accounts['revenue']->id, 'debit' => 0, 'credit' => 1000],
            ],
        );

        $this->createJournal(
            period: $period,
            user: $user,
            journalNo: 'JRN-2026-000302',
            date: '2026-01-09',
            status: 'posted',
            lines: [
                ['account_id' => $accounts['expense']->id, 'debit' => 200, 'credit' => 0],
                ['account_id' => $accounts['cash']->id, 'debit' => 0, 'credit' => 200],
            ],
        );

        // Void journal must be ignored.
        $this->createJournal(
            period: $period,
            user: $user,
            journalNo: 'JRN-2026-000303',
            date: '2026-01-10',
            status: 'void',
            lines: [
                ['account_id' => $accounts['revenue']->id, 'debit' => 0, 'credit' => 999],
                ['account_id' => $accounts['cash']->id, 'debit' => 999, 'credit' => 0],
            ],
        );

        $report = app(ProfitLossService::class)->getProfitLoss(
            periodId: null,
            dateFrom: '2026-01-01',
            dateTo: '2026-01-31',
        );

        $this->assertSame(1000.0, $report['total_revenue']);
        $this->assertSame(200.0, $report['total_expense']);
        $this->assertSame(800.0, $report['net_profit']);
    }

    public function test_balance_sheet_balances_assets_liabilities_and_equity(): void
    {
        $user = User::factory()->create();
        $period = $this->createOpenPeriod();
        $accounts = $this->createBaseAccounts();

        $equipment = Account::query()->create([
            'code' => '1300',
            'name' => 'Peralatan',
            'type' => 'asset',
            'parent_id' => null,
            'is_active' => true,
        ]);

        $loan = Account::query()->create([
            'code' => '2100',
            'name' => 'Hutang Bank',
            'type' => 'liability',
            'parent_id' => null,
            'is_active' => true,
        ]);

        // Owner investment: Cash 1000 / Equity 1000
        $this->createJournal(
            period: $period,
            user: $user,
            journalNo: 'JRN-2026-000401',
            date: '2026-01-02',
            status: 'posted',
            lines: [
                ['account_id' => $accounts['cash']->id, 'debit' => 1000, 'credit' => 0],
                ['account_id' => $accounts['equity']->id, 'debit' => 0, 'credit' => 1000],
            ],
        );

        // Buy equipment with loan: Equipment 200 / Loan 200
        $this->createJournal(
            period: $period,
            user: $user,
            journalNo: 'JRN-2026-000402',
            date: '2026-01-03',
            status: 'posted',
            lines: [
                ['account_id' => $equipment->id, 'debit' => 200, 'credit' => 0],
                ['account_id' => $loan->id, 'debit' => 0, 'credit' => 200],
            ],
        );

        $sheet = app(BalanceSheetService::class)->getBalanceSheet(
            periodId: null,
            dateFrom: '2026-01-01',
            dateTo: '2026-01-31',
        );

        $totalAssets = array_sum(array_map(fn (array $a) => (float) $a['balance'], $sheet['assets']));
        $totalLiabilities = array_sum(array_map(fn (array $a) => (float) $a['balance'], $sheet['liabilities']));
        $totalEquity = array_sum(array_map(fn (array $a) => (float) $a['balance'], $sheet['equity']));

        $this->assertSame(1200.0, round($totalAssets, 2));
        $this->assertSame(200.0, round($totalLiabilities, 2));
        $this->assertSame(1000.0, round($totalEquity, 2));
        $this->assertSame(round($totalAssets, 2), round($totalLiabilities + $totalEquity, 2));
    }

    public function test_cash_flow_does_not_miscalculate_inflow_and_outflow(): void
    {
        $user = User::factory()->create();
        $period = $this->createOpenPeriod();
        $accounts = $this->createBaseAccounts();

        // Cash inflow (posted)
        $this->createJournal(
            period: $period,
            user: $user,
            journalNo: 'JRN-2026-000501',
            date: '2026-01-04',
            status: 'posted',
            lines: [
                ['account_id' => $accounts['cash']->id, 'debit' => 500, 'credit' => 0],
                ['account_id' => $accounts['revenue']->id, 'debit' => 0, 'credit' => 500],
            ],
        );

        // Cash outflow (posted)
        $this->createJournal(
            period: $period,
            user: $user,
            journalNo: 'JRN-2026-000502',
            date: '2026-01-08',
            status: 'posted',
            lines: [
                ['account_id' => $accounts['expense']->id, 'debit' => 200, 'credit' => 0],
                ['account_id' => $accounts['cash']->id, 'debit' => 0, 'credit' => 200],
            ],
        );

        // Non-cash activity should not affect cash flow totals.
        $this->createJournal(
            period: $period,
            user: $user,
            journalNo: 'JRN-2026-000503',
            date: '2026-01-09',
            status: 'posted',
            lines: [
                ['account_id' => $accounts['receivable']->id, 'debit' => 300, 'credit' => 0],
                ['account_id' => $accounts['revenue']->id, 'debit' => 0, 'credit' => 300],
            ],
        );

        // Draft cash activity must be ignored.
        $this->createJournal(
            period: $period,
            user: $user,
            journalNo: 'JRN-2026-000504',
            date: '2026-01-10',
            status: 'draft',
            lines: [
                ['account_id' => $accounts['cash']->id, 'debit' => 999, 'credit' => 0],
                ['account_id' => $accounts['revenue']->id, 'debit' => 0, 'credit' => 999],
            ],
        );

        $report = app(CashFlowService::class)->getCashFlow(
            periodId: null,
            dateFrom: '2026-01-01',
            dateTo: '2026-01-31',
        );

        $this->assertSame(500.0, $report['total_inflow']);
        $this->assertSame(200.0, $report['total_outflow']);
        $this->assertSame(300.0, $report['net_cash_flow']);
    }

    public function test_report_api_endpoints_use_consistent_data_wrapper(): void
    {
        $user = User::factory()->create();
        $period = $this->createOpenPeriod();
        $accounts = $this->createBaseAccounts();

        Sanctum::actingAs($user);

        $this->createJournal(
            period: $period,
            user: $user,
            journalNo: 'JRN-2026-000601',
            date: '2026-01-11',
            status: 'posted',
            lines: [
                ['account_id' => $accounts['cash']->id, 'debit' => 100, 'credit' => 0],
                ['account_id' => $accounts['revenue']->id, 'debit' => 0, 'credit' => 100],
            ],
        );

        $this->getJson('/api/reports/general-ledger?account_id='.$accounts['cash']->id.'&date_from=2026-01-01&date_to=2026-01-31')
            ->assertOk()
            ->assertJsonStructure(['data']);

        $this->getJson('/api/reports/trial-balance?date_from=2026-01-01&date_to=2026-01-31')
            ->assertOk()
            ->assertJsonStructure(['data' => ['accounts', 'total_debit', 'total_credit']]);

        $this->getJson('/api/reports/profit-loss?date_from=2026-01-01&date_to=2026-01-31')
            ->assertOk()
            ->assertJsonStructure(['data' => ['total_revenue', 'total_expense', 'net_profit']]);

        // Balance sheet requires asset/liability/equity to balance; seed minimal to satisfy it.
        $this->createJournal(
            period: $period,
            user: $user,
            journalNo: 'JRN-2026-000602',
            date: '2026-01-12',
            status: 'posted',
            lines: [
                ['account_id' => $accounts['cash']->id, 'debit' => 50, 'credit' => 0],
                ['account_id' => $accounts['equity']->id, 'debit' => 0, 'credit' => 50],
            ],
        );

        $this->getJson('/api/reports/balance-sheet?date_from=2026-01-01&date_to=2026-01-31')
            ->assertOk()
            ->assertJsonStructure(['data' => ['assets', 'liabilities', 'equity']]);

        $this->getJson('/api/reports/cash-flow?date_from=2026-01-01&date_to=2026-01-31')
            ->assertOk()
            ->assertJsonStructure(['data' => ['total_inflow', 'total_outflow', 'net_cash_flow']]);
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

    /**
     * @return array<string, Account>
     */
    private function createBaseAccounts(): array
    {
        return [
            'cash' => Account::query()->create([
                'code' => '1000',
                'name' => 'Kas',
                'type' => 'asset',
                'parent_id' => null,
                'is_active' => true,
            ]),
            'receivable' => Account::query()->create([
                'code' => '1200',
                'name' => 'Piutang Usaha',
                'type' => 'asset',
                'parent_id' => null,
                'is_active' => true,
            ]),
            'equity' => Account::query()->create([
                'code' => '3000',
                'name' => 'Modal',
                'type' => 'equity',
                'parent_id' => null,
                'is_active' => true,
            ]),
            'revenue' => Account::query()->create([
                'code' => '4000',
                'name' => 'Pendapatan',
                'type' => 'revenue',
                'parent_id' => null,
                'is_active' => true,
            ]),
            'expense' => Account::query()->create([
                'code' => '5000',
                'name' => 'Beban',
                'type' => 'expense',
                'parent_id' => null,
                'is_active' => true,
            ]),
        ];
    }

    /**
     * @param  array<int, array<string, int|float>>  $lines
     */
    private function createJournal(
        AccountingPeriod $period,
        User $user,
        string $journalNo,
        string $date,
        string $status,
        array $lines,
    ): JournalEntry {
        $journalEntry = JournalEntry::query()->create([
            'journal_no' => $journalNo,
            'date' => $date,
            'description' => 'Seeded journal '.$journalNo,
            'status' => $status,
            'accounting_period_id' => $period->id,
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]);

        foreach ($lines as $line) {
            JournalLine::query()->create([
                'journal_entry_id' => $journalEntry->id,
                'account_id' => (int) $line['account_id'],
                'debit' => (float) $line['debit'],
                'credit' => (float) $line['credit'],
                'description' => 'Line for '.$journalNo,
            ]);
        }

        return $journalEntry;
    }
}

