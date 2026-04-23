<?php

namespace App\Domains\Accounting\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InvoiceLine extends Model
{
    protected $table = 'invoice_lines';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'invoice_id',
        'item_id',
        'warehouse_id',
        'quantity',
        'unit_price',
        'line_total',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:4',
            'unit_price' => 'decimal:2',
            'line_total' => 'decimal:2',
        ];
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class, 'invoice_id');
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class, 'item_id');
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'warehouse_id');
    }
}

