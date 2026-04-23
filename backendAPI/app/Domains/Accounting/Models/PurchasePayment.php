<?php

namespace App\Domains\Accounting\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PurchasePayment extends Model
{
    protected $table = 'purchase_payments';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'payment_no',
        'purchase_invoice_id',
        'payment_date',
        'description',
        'amount',
        'voided_at',
        'void_reason',
        'voided_by',
        'journal_entry_id',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'payment_date' => 'date',
            'amount' => 'decimal:2',
            'voided_at' => 'datetime',
        ];
    }

    public function purchaseInvoice(): BelongsTo
    {
        return $this->belongsTo(PurchaseInvoice::class, 'purchase_invoice_id');
    }

    public function journalEntry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class);
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
