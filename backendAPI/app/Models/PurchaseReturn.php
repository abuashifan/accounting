<?php

namespace App\Models;

use App\Domains\Accounting\Models\PurchaseReturn as DomainPurchaseReturn;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class PurchaseReturn extends DomainPurchaseReturn
{
    use HasFactory;
}

