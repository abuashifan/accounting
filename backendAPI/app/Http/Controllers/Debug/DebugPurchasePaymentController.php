<?php

namespace App\Http\Controllers\Debug;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;

class DebugPurchasePaymentController extends Controller
{
    public function index(): View
    {
        return view('debug.purchase_payments.index');
    }

    public function create(): View
    {
        return view('debug.purchase_payments.create');
    }
}

