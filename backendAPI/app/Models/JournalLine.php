<?php

namespace App\Models;

use App\Domains\Accounting\Models\JournalLine as DomainJournalLine;
use Database\Factories\JournalLineFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class JournalLine extends DomainJournalLine
{
    /** @use HasFactory<JournalLineFactory> */
    use HasFactory;
}
