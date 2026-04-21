<?php

namespace App\Domains\Accounting\Services;

use App\Domains\Accounting\Actions\CheckPeriodAction;
use App\Domains\Accounting\Actions\CreateJournalAction;
use App\Domains\Accounting\Actions\ValidateJournalAction;
use App\Domains\Accounting\DTOs\JournalData;
use App\Domains\Accounting\Models\AccountingPeriod;
use App\Domains\Accounting\Models\JournalEntry;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class JournalService
{
    public function __construct(
        private readonly ValidateJournalAction $validateJournalAction,
        private readonly CheckPeriodAction $checkPeriodAction,
        private readonly CreateJournalAction $createJournalAction,
    ) {}

    /**
     * @throws AuthorizationException
     * @throws ValidationException
     */
    public function create(JournalData $data, ?string $reason = null): JournalEntry
    {
        return DB::transaction(function () use ($data, $reason): JournalEntry {
            $user = $this->resolveUserOrFail();
            Gate::forUser($user)->authorize('journal.create');
            $period = AccountingPeriod::query()->findOrFail($data->accounting_period_id);

            $this->validateJournalAction->execute($data);
            $this->checkPeriodAction->execute($period, $user);

            $journalEntry = $this->createJournalAction->execute($data, $user);

            $this->logAudit('journal.created', $journalEntry, $user, null, $this->snapshot($journalEntry), $reason);

            return $journalEntry;
        });
    }

    /**
     * @throws AuthorizationException
     * @throws ValidationException
     */
    public function update(int $id, JournalData $data, ?string $reason = null): JournalEntry
    {
        return DB::transaction(function () use ($id, $data, $reason): JournalEntry {
            $user = $this->resolveUserOrFail();
            Gate::forUser($user)->authorize('journal.update');
            $journalEntry = JournalEntry::query()
                ->with(['accountingPeriod', 'journalLines'])
                ->findOrFail($id);

            if ($journalEntry->status !== 'draft') {
                throw ValidationException::withMessages([
                    'status' => ['Only draft journals can be updated.'],
                ]);
            }

            $before = $this->snapshot($journalEntry);

            $currentPeriod = $journalEntry->accountingPeriod;
            $targetPeriod = AccountingPeriod::query()->findOrFail($data->accounting_period_id);

            $this->validateJournalAction->execute($data);
            $this->checkPeriodAction->execute($currentPeriod, $user);
            $this->checkPeriodAction->execute($targetPeriod, $user);

            $updatedJournal = $this->createJournalAction->execute($data, $user, $journalEntry);

            $this->logAudit('journal.updated', $updatedJournal, $user, $before, $this->snapshot($updatedJournal), $reason);

            return $updatedJournal;
        });
    }

    /**
     * @throws AuthorizationException
     */
    public function void(int $id, ?string $reason = null): JournalEntry
    {
        return DB::transaction(function () use ($id, $reason): JournalEntry {
            $user = $this->resolveUserOrFail();
            Gate::forUser($user)->authorize('journal.void');
            $journalEntry = JournalEntry::query()
                ->with('accountingPeriod')
                ->findOrFail($id);

            $this->checkPeriodAction->execute($journalEntry->accountingPeriod, $user);

            $before = $this->snapshot($journalEntry);

            $journalEntry->forceFill([
                'status' => 'void',
                'updated_by' => $user?->id,
            ])->save();

            $after = $this->snapshot($journalEntry->fresh(['journalLines.account', 'accountingPeriod']));
            $this->logAudit('journal.voided', $journalEntry, $user, $before, $after, $reason);

            return $journalEntry->fresh(['journalLines.account', 'accountingPeriod']);
        });
    }

    /**
     * @throws AuthorizationException
     * @throws ValidationException
     */
    public function post(int $id, ?string $reason = null): JournalEntry
    {
        return DB::transaction(function () use ($id, $reason): JournalEntry {
            $user = $this->resolveUserOrFail();
            Gate::forUser($user)->authorize('journal.update');

            $journalEntry = JournalEntry::query()
                ->with(['accountingPeriod', 'journalLines'])
                ->findOrFail($id);

            if ($journalEntry->status !== 'draft') {
                throw ValidationException::withMessages([
                    'status' => ['Only draft journals can be posted.'],
                ]);
            }

            if ($journalEntry->accountingPeriod->is_closed) {
                throw new AuthorizationException('Cannot post to a closed accounting period.');
            }

            $this->assertBalancedFromDb($journalEntry);

            $before = $this->snapshot($journalEntry);

            $journalEntry->forceFill([
                'status' => 'posted',
                'updated_by' => $user->id,
            ])->save();

            $journalEntry = $journalEntry->fresh(['journalLines.account', 'accountingPeriod']);
            $this->logAudit('journal.posted', $journalEntry, $user, $before, $this->snapshot($journalEntry), $reason);

            return $journalEntry;
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

    /**
     * @param  array<string, mixed>|null  $oldValues
     * @param  array<string, mixed>|null  $newValues
     */
    private function logAudit(
        string $event,
        JournalEntry $journalEntry,
        User $user,
        ?array $oldValues = null,
        ?array $newValues = null,
        ?string $reason = null,
    ): void {
        /** @var Request|null $request */
        $request = request();

        AuditLog::query()->create([
            'user_id' => $user->id,
            'action' => $event,
            'entity_type' => $journalEntry::class,
            'entity_id' => $journalEntry->id,
            'before' => $oldValues,
            'after' => $newValues,
            'reason' => $reason,
            'ip_address' => $request?->ip(),
            'user_agent' => $request?->userAgent(),
            'created_at' => now(),
        ]);

        Log::info($event, [
            'journal_entry_id' => $journalEntry->id,
            'journal_no' => $journalEntry->journal_no,
            'status' => $journalEntry->status,
            'accounting_period_id' => $journalEntry->accounting_period_id,
            'user_id' => $user->id,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function snapshot(JournalEntry $journalEntry): array
    {
        $journalEntry->loadMissing(['journalLines', 'accountingPeriod']);

        return [
            'id' => $journalEntry->id,
            'journal_no' => $journalEntry->journal_no,
            'date' => $journalEntry->date?->format('Y-m-d'),
            'description' => $journalEntry->description,
            'status' => $journalEntry->status,
            'accounting_period_id' => $journalEntry->accounting_period_id,
            'lines' => $journalEntry->journalLines
                ->map(fn ($line) => [
                    'account_id' => $line->account_id,
                    'debit' => (string) $line->debit,
                    'credit' => (string) $line->credit,
                    'description' => $line->description,
                ])
                ->values()
                ->all(),
        ];
    }

    private function assertBalancedFromDb(JournalEntry $journalEntry): void
    {
        $journalEntry->loadMissing('journalLines');

        $totalDebit = $journalEntry->journalLines->sum(fn ($line) => (float) $line->debit);
        $totalCredit = $journalEntry->journalLines->sum(fn ($line) => (float) $line->credit);

        if (round($totalDebit, 2) !== round($totalCredit, 2)) {
            throw ValidationException::withMessages([
                'balance' => ['Total debit must equal total credit.'],
            ]);
        }
    }
}
