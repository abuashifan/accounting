<?php

namespace App\Models;

use App\Domains\Accounting\Models\Vendor as DomainVendor;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Vendor extends DomainVendor
{
    use HasFactory;
}
