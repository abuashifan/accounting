<?php

namespace App\Models;

use App\Domains\Accounting\Models\Invoice as DomainInvoice;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Invoice extends DomainInvoice
{
    use HasFactory;
}
