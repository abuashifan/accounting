<?php

namespace App\Models;

use App\Domains\Accounting\Models\Customer as DomainCustomer;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Customer extends DomainCustomer
{
    use HasFactory;
}
