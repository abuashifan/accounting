<?php

namespace App\Http\Controllers\Debug;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;

class DebugAuthController extends Controller
{
    public function login(): View
    {
        return view('debug.login');
    }
}

