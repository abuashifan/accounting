<?php

namespace App\Models;

use App\Domains\Accounting\Models\Warehouse as DomainWarehouse;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Warehouse extends DomainWarehouse
{
    use HasFactory;
}

