<?php

namespace App\Http\Controllers\Debug;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;

class DebugAccountController extends Controller
{
    public function index(): View
    {
        return view('debug.accounts.index');
    }

    public function create(): View
    {
        return view('debug.accounts.create');
    }

    public function edit(int $id): View
    {
        return view('debug.accounts.edit', [
            'id' => $id,
        ]);
    }
}
