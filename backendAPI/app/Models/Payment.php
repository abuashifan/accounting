<?php

namespace App\Models;

use App\Domains\Accounting\Models\Payment as DomainPayment;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Payment extends DomainPayment
{
    use HasFactory;
}
