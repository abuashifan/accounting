<?php

namespace App\Http\Controllers\Debug;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;

class DebugPurchaseReturnController extends Controller
{
    public function index(): View
    {
        return view('debug.purchase_returns.index');
    }

    public function create(): View
    {
        return view('debug.purchase_returns.create');
    }

    public function edit(int $id): View
    {
        return view('debug.purchase_returns.edit', [
            'id' => $id,
        ]);
    }
}

