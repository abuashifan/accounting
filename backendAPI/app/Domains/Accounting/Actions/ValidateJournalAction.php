<?php

namespace App\Domains\Accounting\Actions;

use App\Domains\Accounting\DTOs\JournalData;
use App\Domains\Accounting\DTOs\JournalLineData;
use Illuminate\Validation\ValidationException;

class ValidateJournalAction
{
    public function execute(JournalData $data): void
    {
        $errors = [];

        if (count($data->lines) < 2) {
            $errors['lines'][] = 'A journal entry must contain at least two lines.';
        }

        $totalDebit = 0.0;
        $totalCredit = 0.0;

        foreach ($data->lines as $index => $line) {
            if (! $line instanceof JournalLineData) {
                $errors["lines.{$index}"][] = 'Each line must be a JournalLineData instance.';

                continue;
            }

            if ($line->debit < 0) {
                $errors["lines.{$index}.debit"][] = 'Debit must be greater than or equal to zero.';
            }

            if ($line->credit < 0) {
                $errors["lines.{$index}.credit"][] = 'Credit must be greater than or equal to zero.';
            }

            $hasDebit = $line->debit > 0;
            $hasCredit = $line->credit > 0;

            if ($hasDebit === $hasCredit) {
                $errors["lines.{$index}"][] = 'Each line must have either debit or credit, but not both.';
            }

            $totalDebit += $line->debit;
            $totalCredit += $line->credit;
        }

        if (round($totalDebit, 2) !== round($totalCredit, 2)) {
            $errors['balance'][] = 'Total debit must equal total credit.';
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }
}
