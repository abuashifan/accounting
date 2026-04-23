<?php

namespace App\Models;

use App\Domains\Accounting\Models\SalesReturn as DomainSalesReturn;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class SalesReturn extends DomainSalesReturn
{
    use HasFactory;
}

