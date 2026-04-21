<?php

namespace App\Domains\Accounting\Services;

use App\Domains\Accounting\Actions\CheckPeriodAction;
use App\Domains\Accounting\Actions\CreateJournalAction;
use App\Domains\Accounting\Actions\ValidateJournalAction;
use App\Domains\Accounting\DTOs\JournalData;
use App\Domains\Accounting\DTOs\JournalLineData;
use App\Domains\Accounting\Models\JournalEntry;
use App\Models\AccountingPeriod;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AutoPostingJournalService
{
    public function __construct(
        private readonly ValidateJournalAction $validateJournalAction,
        private readonly CheckPeriodAction $checkPeriodAction,
        private readonly CreateJournalAction $createJournalAction,
    ) {}

    /**
     * @param  array<int, JournalLineData>  $lines
     */
    public function createPostedJournal(string $date, ?string $description, array $lines): JournalEntry
    {
        return DB::transaction(function () use ($date, $description, $lines): JournalEntry {
            $user = $this->resolveUserOrFail();
            $period = $this->resolvePeriodForDate($date);

            $data = new JournalData(
                date: $date,
                description: $description,
                accounting_period_id: $period->id,
                lines: $lines,
            );

            $this->validateJournalAction->execute($data);
            $this->checkPeriodAction->execute($period, $user);

            $journalEntry = $this->createJournalAction->execute($data, $user);

            $journalEntry->forceFill([
                'status' => 'posted',
                'updated_by' => $user->id,
            ])->save();

            return $journalEntry->fresh(['journalLines.account', 'accountingPeriod']);
        });
    }

    private function resolveUserOrFail(): User
    {
        $requestUser = request()?->user();

        if ($requestUser instanceof User) {
            return $requestUser;
        }

        $user = Auth::user();

        if (! $user instanceof User) {
            throw new AuthorizationException('Unauthenticated.');
        }

        return $user;
    }

    private function resolvePeriodForDate(string $date): AccountingPeriod
    {
        $period = AccountingPeriod::query()
            ->whereDate('start_date', '<=', $date)
            ->whereDate('end_date', '>=', $date)
            ->orderBy('start_date')
            ->first();

        if (! $period instanceof AccountingPeriod) {
            throw ValidationException::withMessages([
                'date' => ['No accounting period covers the given transaction date.'],
            ]);
        }

        return $period;
    }
}
