<?php

namespace App\Http\Controllers\Debug;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;

class DebugPurchaseInvoiceController extends Controller
{
    public function index(): View
    {
        return view('debug.purchase_invoices.index');
    }

    public function create(): View
    {
        return view('debug.purchase_invoices.create');
    }

    public function edit(int $id): View
    {
        return view('debug.purchase_invoices.edit', [
            'id' => $id,
        ]);
    }
}
