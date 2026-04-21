<?php

namespace App\Domains\Accounting\Actions;

use App\Domains\Accounting\DTOs\JournalData;
use App\Domains\Accounting\Models\JournalEntry;
use App\Domains\Accounting\Models\JournalLine;
use App\Models\User;
use RuntimeException;

class CreateJournalAction
{
    public function execute(
        JournalData $data,
        ?User $user = null,
        ?JournalEntry $journalEntry = null,
    ): JournalEntry {
        $actorId = $user?->id ?? User::query()->value('id');

        if ($actorId === null) {
            throw new RuntimeException('An acting user is required to persist a journal entry.');
        }

        $journalEntry ??= new JournalEntry();

        $journalEntry->fill([
            'journal_no' => $journalEntry->exists ? $journalEntry->journal_no : $this->generateJournalNumber(),
            'date' => $data->date,
            'description' => $data->description,
            'status' => $journalEntry->exists ? $journalEntry->status : 'active',
            'accounting_period_id' => $data->accounting_period_id,
            'created_by' => $journalEntry->exists ? $journalEntry->created_by : $actorId,
            'updated_by' => $actorId,
        ]);
        $journalEntry->save();

        if ($journalEntry->wasRecentlyCreated === false) {
            $journalEntry->journalLines()->delete();
        }

        $linePayload = [];

        foreach ($data->lines as $line) {
            $linePayload[] = new JournalLine([
                'account_id' => $line->account_id,
                'debit' => $line->debit,
                'credit' => $line->credit,
                'description' => $data->description,
            ]);
        }

        $journalEntry->journalLines()->saveMany($linePayload);

        return $journalEntry->load(['journalLines.account', 'accountingPeriod']);
    }

    private function generateJournalNumber(): string
    {
        $lastId = (int) JournalEntry::query()->max('id') + 1;

        return sprintf('JRN-%06d', $lastId);
    }
}
