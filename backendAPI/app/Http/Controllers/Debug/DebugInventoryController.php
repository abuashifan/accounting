<?php

namespace App\Http\Controllers\Debug;

use App\Http\Controllers\Controller;
use App\Models\AccountingPeriod;
use Illuminate\Contracts\View\View;

class DebugInventoryController extends Controller
{
    public function items(): View
    {
        return view('debug.inventory.items.index');
    }

    public function itemsCreate(): View
    {
        return view('debug.inventory.items.create');
    }

    public function itemsEdit(int $id): View
    {
        return view('debug.inventory.items.edit', [
            'id' => $id,
        ]);
    }

    public function warehouses(): View
    {
        return view('debug.inventory.warehouses.index');
    }

    public function warehousesCreate(): View
    {
        return view('debug.inventory.warehouses.create');
    }

    public function warehousesEdit(int $id): View
    {
        return view('debug.inventory.warehouses.edit', [
            'id' => $id,
        ]);
    }

    public function stockCard(): View
    {
        return view('debug.inventory.stock-card');
    }

    public function adjustment(): View
    {
        return view('debug.inventory.adjustment', [
            'periods' => AccountingPeriod::query()
                ->orderByDesc('start_date')
                ->limit(24)
                ->get(['id', 'start_date', 'end_date', 'is_closed']),
        ]);
    }

    public function transfer(): View
    {
        return view('debug.inventory.transfer');
    }
}
