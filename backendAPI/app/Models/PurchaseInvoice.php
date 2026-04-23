<?php

namespace App\Models;

use App\Domains\Accounting\Models\PurchaseInvoice as DomainPurchaseInvoice;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class PurchaseInvoice extends DomainPurchaseInvoice
{
    use HasFactory;
}

