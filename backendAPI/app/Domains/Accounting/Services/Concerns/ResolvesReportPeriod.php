<?php

namespace App\Domains\Accounting\Services\Concerns;

use App\Domains\Accounting\Models\AccountingPeriod;
use Illuminate\Validation\ValidationException;

trait ResolvesReportPeriod
{
    /**
     * @return array{0: string|null, 1: string|null}
     */
    private function resolvePeriod(?int $periodId, ?string $dateFrom, ?string $dateTo): array
    {
        if ($periodId !== null) {
            $period = AccountingPeriod::query()->findOrFail($periodId);

            return [
                $period->start_date?->format('Y-m-d'),
                $period->end_date?->format('Y-m-d'),
            ];
        }

        if ($dateFrom !== null && $dateTo !== null && $dateFrom > $dateTo) {
            throw ValidationException::withMessages([
                'date_to' => ['The date_to must be greater than or equal to date_from.'],
            ]);
        }

        return [$dateFrom, $dateTo];
    }
}

