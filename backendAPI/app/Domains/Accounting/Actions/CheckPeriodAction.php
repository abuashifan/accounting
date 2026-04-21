<?php

namespace App\Domains\Accounting\Actions;

use App\Domains\Accounting\Models\AccountingPeriod;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Gate;

class CheckPeriodAction
{
    /**
     * @throws AuthorizationException
     */
    public function execute(AccountingPeriod $period, ?User $user = null): void
    {
        if (! $period->is_closed) {
            return;
        }

        if ($user && Gate::forUser($user)->allows('journal.override_period')) {
            return;
        }

        throw new AuthorizationException('The selected accounting period is closed.');
    }
}
