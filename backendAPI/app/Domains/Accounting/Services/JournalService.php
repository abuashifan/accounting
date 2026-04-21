<?php

namespace App\Domains\Accounting\Services;

use App\Domains\Accounting\Actions\CheckPeriodAction;
use App\Domains\Accounting\Actions\CreateJournalAction;
use App\Domains\Accounting\Actions\ValidateJournalAction;
use App\Domains\Accounting\DTOs\JournalData;
use App\Domains\Accounting\Models\AccountingPeriod;
use App\Domains\Accounting\Models\JournalEntry;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class JournalService
{
    public function __construct(
        private readonly ValidateJournalAction $validateJournalAction,
        private readonly CheckPeriodAction $checkPeriodAction,
        private readonly CreateJournalAction $createJournalAction,
    ) {
    }

    /**
     * @throws AuthorizationException
     * @throws ValidationException
     */
    public function create(JournalData $data): JournalEntry
    {
        return DB::transaction(function () use ($data): JournalEntry {
            $user = $this->resolveUser();
            $period = AccountingPeriod::query()->findOrFail($data->accounting_period_id);

            $this->validateJournalAction->execute($data);
            $this->checkPeriodAction->execute($period, $user);

            $journalEntry = $this->createJournalAction->execute($data, $user);

            $this->logAudit('journal.created', $journalEntry, $user);

            return $journalEntry;
        });
    }

    /**
     * @throws AuthorizationException
     * @throws ValidationException
     */
    public function update(int $id, JournalData $data): JournalEntry
    {
        return DB::transaction(function () use ($id, $data): JournalEntry {
            $user = $this->resolveUser();
            $journalEntry = JournalEntry::query()
                ->with('accountingPeriod')
                ->findOrFail($id);

            if ($journalEntry->status === 'void') {
                throw ValidationException::withMessages([
                    'status' => ['Voided journals cannot be updated.'],
                ]);
            }

            $currentPeriod = $journalEntry->accountingPeriod;
            $targetPeriod = AccountingPeriod::query()->findOrFail($data->accounting_period_id);

            $this->validateJournalAction->execute($data);
            $this->checkPeriodAction->execute($currentPeriod, $user);
            $this->checkPeriodAction->execute($targetPeriod, $user);

            $updatedJournal = $this->createJournalAction->execute($data, $user, $journalEntry);

            $this->logAudit('journal.updated', $updatedJournal, $user);

            return $updatedJournal;
        });
    }

    /**
     * @throws AuthorizationException
     */
    public function void(int $id): JournalEntry
    {
        return DB::transaction(function () use ($id): JournalEntry {
            $user = $this->resolveUser();
            $journalEntry = JournalEntry::query()
                ->with('accountingPeriod')
                ->findOrFail($id);

            $this->checkPeriodAction->execute($journalEntry->accountingPeriod, $user);

            $journalEntry->forceFill([
                'status' => 'void',
                'updated_by' => $user?->id,
            ])->save();

            $this->logAudit('journal.voided', $journalEntry, $user);

            return $journalEntry->fresh(['journalLines.account', 'accountingPeriod']);
        });
    }

    private function resolveUser(): ?User
    {
        $user = Auth::user();

        return $user instanceof User ? $user : null;
    }

    private function logAudit(string $event, JournalEntry $journalEntry, ?User $user = null): void
    {
        Log::info($event, [
            'journal_entry_id' => $journalEntry->id,
            'journal_no' => $journalEntry->journal_no,
            'status' => $journalEntry->status,
            'accounting_period_id' => $journalEntry->accounting_period_id,
            'user_id' => $user?->id,
        ]);
    }
}
