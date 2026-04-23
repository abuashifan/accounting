<?php

namespace App\Models;

use App\Domains\Accounting\Models\PurchasePayment as DomainPurchasePayment;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class PurchasePayment extends DomainPurchasePayment
{
    use HasFactory;
}

