<?php

namespace App\Http\Controllers\Debug;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;

class DebugPurchaseController extends Controller
{
    public function index(): View
    {
        return view('debug.purchases.index');
    }

    public function create(): View
    {
        return view('debug.purchases.create');
    }

    public function pay(int $id): View
    {
        return view('debug.purchases.pay', [
            'purchaseInvoiceId' => $id,
        ]);
    }
}

