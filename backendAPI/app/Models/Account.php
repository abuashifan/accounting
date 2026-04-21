<?php

namespace App\Models;

use App\Domains\Accounting\Models\Account as DomainAccount;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Account extends DomainAccount
{
    use HasFactory;
}
