<?php

namespace Tests\Feature\Accounting;

use App\Domains\Accounting\Services\GeneralLedgerService;
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

    public function test_general_ledger_service_includes_opening_balance_and_running_balance_for_posted_journals_only(): void
    {
        $user = User::factory()->create();
        $period = $this->createOpenPeriod();
        $accounts = $this->createBaseAccounts();

        $this->createJournal(
            period: $period,
            user: $user,
            journalNo: 'JRN-2026-000001',
            date: '2026-01-05',
            status: 'posted',
            lines: [
                ['account_id' => $accounts['receivable']->id, 'debit' => 1000, 'credit' => 0],
                ['account_id' => $accounts['revenue']->id, 'debit' => 0, 'credit' => 1000],
            ],
        );

        $this->createJournal(
            period: $period,
            user: $user,
            journalNo: 'JRN-2026-000002',
            date: '2026-01-10',
            status: 'posted',
            lines: [
                ['account_id' => $accounts['cash']->id, 'debit' => 400, 'credit' => 0],
                ['account_id' => $accounts['receivable']->id, 'debit' => 0, 'credit' => 400],
            ],
        );

        $this->createJournal(
            period: $period,
            user: $user,
            journalNo: 'JRN-2026-000003',
            date: '2026-01-12',
            status: 'posted',
            lines: [
                ['account_id' => $accounts['receivable']->id, 'debit' => 200, 'credit' => 0],
                ['account_id' => $accounts['revenue']->id, 'debit' => 0, 'credit' => 200],
            ],
        );

        $this->createJournal(
            period: $period,
            user: $user,
            journalNo: 'JRN-2026-000004',
            date: '2026-01-20',
            status: 'draft',
            lines: [
                ['account_id' => $accounts['receivable']->id, 'debit' => 999, 'credit' => 0],
                ['account_id' => $accounts['revenue']->id, 'debit' => 0, 'credit' => 999],
            ],
        );

        $report = app(GeneralLedgerService::class)->getLedger(
            accountId: $accounts['receivable']->id,
            startDate: '2026-01-10',
            endDate: '2026-01-31',
        );

        $this->assertSame(1000.0, $report['opening_balance']);
        $this->assertSame(200.0, $report['total_debit']);
        $this->assertSame(400.0, $report['total_credit']);
        $this->assertSame(800.0, $report['closing_balance']);
        $this->assertCount(2, $report['entries']);
        $this->assertSame('JRN-2026-000002', $report['entries'][0]['journal_no']);
        $this->assertSame(600.0, $report['entries'][0]['running_balance']);
        $this->assertSame(800.0, $report['entries'][1]['running_balance']);
    }

    public function test_report_api_returns_general_ledger_and_trial_balance_payloads(): void
    {
        $user = User::factory()->create();
        $period = $this->createOpenPeriod();
        $accounts = $this->createBaseAccounts();

        Sanctum::actingAs($user);

        $this->createJournal(
            period: $period,
            user: $user,
            journalNo: 'JRN-2026-000010',
            date: '2026-01-08',
            status: 'posted',
            lines: [
                ['account_id' => $accounts['receivable']->id, 'debit' => 1500, 'credit' => 0],
                ['account_id' => $accounts['revenue']->id, 'debit' => 0, 'credit' => 1500],
            ],
        );

        $this->createJournal(
            period: $period,
            user: $user,
            journalNo: 'JRN-2026-000011',
            date: '2026-01-14',
            status: 'posted',
            lines: [
                ['account_id' => $accounts['cash']->id, 'debit' => 700, 'credit' => 0],
                ['account_id' => $accounts['receivable']->id, 'debit' => 0, 'credit' => 700],
            ],
        );

        $ledgerResponse = $this->getJson('/api/reports/general-ledger?account_id='.$accounts['receivable']->id.'&start_date=2026-01-10&end_date=2026-01-31');

        $ledgerResponse
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.account.code', '1200')
            ->assertJsonPath('data.opening_balance', 1500)
            ->assertJsonPath('data.closing_balance', 800);

        $trialBalanceResponse = $this->getJson('/api/reports/trial-balance?start_date=2026-01-01&end_date=2026-01-31');

        $trialBalanceResponse
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.total_debit', 2200)
            ->assertJsonPath('data.total_credit', 2200)
            ->assertJsonPath('data.is_balanced', true);
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
            'revenue' => Account::query()->create([
                'code' => '4000',
                'name' => 'Pendapatan',
                'type' => 'revenue',
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
