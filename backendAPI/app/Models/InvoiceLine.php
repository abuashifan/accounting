<?php

namespace App\Models;

use App\Domains\Accounting\Models\InvoiceLine as DomainInvoiceLine;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class InvoiceLine extends DomainInvoiceLine
{
    use HasFactory;
}

