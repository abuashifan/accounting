<?php

namespace App\Models;

use App\Domains\Accounting\Models\StockBalance as DomainStockBalance;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class StockBalance extends DomainStockBalance
{
    use HasFactory;
}

