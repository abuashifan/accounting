<?php

namespace App\Models;

use App\Domains\Accounting\Models\StockMovement as DomainStockMovement;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class StockMovement extends DomainStockMovement
{
    use HasFactory;
}

