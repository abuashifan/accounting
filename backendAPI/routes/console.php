<?php

use App\Domains\Accounting\DTOs\JournalData;
use App\Domains\Accounting\DTOs\JournalLineData;
use App\Domains\Accounting\Services\JournalService;
use App\Models\Account;
use App\Models\AccountingPeriod;
use App\Models\JournalEntry;
use App\Models\User;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('accounting:simulate', function () {
    $this->info('== Simulasi Accounting Engine (JournalService) ==');

    $user = User::query()->where('email', 'admin@example.com')->first();
    if ($user) {
        Auth::login($user);
        $this->line('Actor: '.$user->email.' (id='.$user->id.')');
    } else {
        $this->warn('Actor: (tidak ada user admin@example.com) -> JournalService akan fallback ke user pertama.');
    }

    $period = AccountingPeriod::query()->where('is_closed', false)->orderBy('start_date')->first();
    if (! $period) {
        $this->error('Tidak ada accounting period OPEN. Jalankan seeder dulu: php artisan db:seed');

        return 1;
    }

    $kas = Account::query()->where('code', '1000')->first();
    $pendapatan = Account::query()->where('code', '4000')->first();
    $beban = Account::query()->where('code', '5000')->first();

    if (! $kas || ! $pendapatan || ! $beban) {
        $this->error('Account wajib belum ada (1000 Kas, 4000 Pendapatan, 5000 Beban). Jalankan seeder: php artisan db:seed');

        return 1;
    }

    /** @var JournalService $service */
    $service = app(JournalService::class);

    $this->newLine();
    $this->info('[1] Transaksi Pendapatan: Kas (D) / Pendapatan (K)');

    $journal1 = $service->create(new JournalData(
        date: now()->toDateString(),
        description: 'Simulasi pendapatan',
        accounting_period_id: $period->id,
        lines: [
            new JournalLineData(account_id: $kas->id, debit: 150000, credit: 0),
            new JournalLineData(account_id: $pendapatan->id, debit: 0, credit: 150000),
        ],
    ));

    $this->line('Created: '.$journal1->journal_no.' (id='.$journal1->id.', status='.$journal1->status.')');
    foreach ($journal1->journalLines as $line) {
        $this->line(sprintf(
            '- %s %s | D=%s K=%s',
            $line->account->code,
            $line->account->name,
            $line->debit,
            $line->credit
        ));
    }

    $this->newLine();
    $this->info('[2] Transaksi Beban: Beban (D) / Kas (K)');

    $journal2 = $service->create(new JournalData(
        date: now()->toDateString(),
        description: 'Simulasi beban',
        accounting_period_id: $period->id,
        lines: [
            new JournalLineData(account_id: $beban->id, debit: 50000, credit: 0),
            new JournalLineData(account_id: $kas->id, debit: 0, credit: 50000),
        ],
    ));

    $this->line('Created: '.$journal2->journal_no.' (id='.$journal2->id.', status='.$journal2->status.')');
    foreach ($journal2->journalLines as $line) {
        $this->line(sprintf(
            '- %s %s | D=%s K=%s',
            $line->account->code,
            $line->account->name,
            $line->debit,
            $line->credit
        ));
    }

    $this->newLine();
    $this->info('[3] Error Case: debit != credit (harus gagal)');
    try {
        $service->create(new JournalData(
            date: now()->toDateString(),
            description: 'Simulasi error unbalanced',
            accounting_period_id: $period->id,
            lines: [
                new JournalLineData(account_id: $kas->id, debit: 100, credit: 0),
                new JournalLineData(account_id: $pendapatan->id, debit: 0, credit: 90),
            ],
        ));

        $this->error('BUG: transaksi unbalanced berhasil tersimpan (seharusnya gagal).');
    } catch (ValidationException $e) {
        $this->line('OK: gagal sesuai harapan.');
        $this->line('Errors: '.json_encode($e->errors(), JSON_PRETTY_PRINT));
    }

    $this->newLine();
    $this->info('Ringkas DB:');
    $this->line('- journal_entries: '.JournalEntry::query()->count());

    return 0;
})->purpose('Simulasi transaksi accounting (double-entry) end-to-end');
