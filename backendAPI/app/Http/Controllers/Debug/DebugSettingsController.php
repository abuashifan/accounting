<?php

namespace App\Http\Controllers\Debug;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;

class DebugSettingsController extends Controller
{
    public function journalSettings(): View
    {
        return view('debug.settings.journals');
    }
}

