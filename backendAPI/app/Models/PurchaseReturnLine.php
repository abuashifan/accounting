<?php

namespace App\Models;

use App\Domains\Accounting\Models\PurchaseReturnLine as DomainPurchaseReturnLine;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class PurchaseReturnLine extends DomainPurchaseReturnLine
{
    use HasFactory;
}

