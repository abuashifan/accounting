<?php

namespace App\Domains\Accounting\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockMovement extends Model
{
    protected $table = 'stock_movements';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'date',
        'item_id',
        'warehouse_id',
        'type',
        'qty_in',
        'qty_out',
        'unit_cost',
        'total_cost',
        'reference_type',
        'reference_id',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'qty_in' => 'decimal:4',
            'qty_out' => 'decimal:4',
            'unit_cost' => 'decimal:6',
            'total_cost' => 'decimal:2',
        ];
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }
}

