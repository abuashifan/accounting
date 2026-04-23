<?php

namespace App\Models;

use App\Domains\Accounting\Models\PurchaseInvoiceLine as DomainPurchaseInvoiceLine;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class PurchaseInvoiceLine extends DomainPurchaseInvoiceLine
{
    use HasFactory;
}

