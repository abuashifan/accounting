<?php

namespace App\Models;

use App\Domains\Accounting\Models\JournalEntry as DomainJournalEntry;
use Database\Factories\JournalEntryFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class JournalEntry extends DomainJournalEntry
{
    /** @use HasFactory<JournalEntryFactory> */
    use HasFactory;
}
