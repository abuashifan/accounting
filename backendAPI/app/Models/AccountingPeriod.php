<?php

namespace App\Models;

use App\Domains\Accounting\Models\AccountingPeriod as DomainAccountingPeriod;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class AccountingPeriod extends DomainAccountingPeriod
{
    use HasFactory;
}
