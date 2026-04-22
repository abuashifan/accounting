<?php

namespace App\Models;

use App\Domains\Accounting\Models\Item as DomainItem;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Item extends DomainItem
{
    use HasFactory;
}

