<?php

namespace App\Domains\Accounting\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PurchaseInvoice extends Model
{
    protected $table = 'purchase_invoices';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'invoice_no',
        'invoice_date',
        'description',
        'amount',
        'paid_amount',
        'status',
        'posted_at',
        'journal_entry_id',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'invoice_date' => 'date',
            'amount' => 'decimal:2',
            'paid_amount' => 'decimal:2',
            'posted_at' => 'datetime',
        ];
    }

    public function journalEntry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class);
    }

    public function purchaseInvoiceLines(): HasMany
    {
        return $this->hasMany(PurchaseInvoiceLine::class, 'purchase_invoice_id');
    }

    public function purchasePayments(): HasMany
    {
        return $this->hasMany(PurchasePayment::class, 'purchase_invoice_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}

