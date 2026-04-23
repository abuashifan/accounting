<?php

namespace App\Http\Controllers\Debug;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;

class DebugSalesReturnController extends Controller
{
    public function index(): View
    {
        return view('debug.sales_returns.index');
    }

    public function create(): View
    {
        return view('debug.sales_returns.create');
    }

    public function edit(int $id): View
    {
        return view('debug.sales_returns.edit', [
            'id' => $id,
        ]);
    }
}

