<?php

namespace App\Http\Controllers\Accounting;

use App\Http\Controllers\Controller;
use App\Models\AppSetting;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class JournalSettingsController extends Controller
{
    private const KEY_AUTO_POST = 'journals.auto_post';

    /**
     * @throws AuthorizationException
     */
    public function show(Request $request): JsonResponse
    {
        $user = $request->user();
        if (! $user instanceof User) {
            throw new AuthorizationException('Unauthenticated.');
        }

        Gate::forUser($user)->authorize('settings.manage');

        return response()->json([
            'success' => true,
            'data' => [
                'auto_post' => AppSetting::getBool(self::KEY_AUTO_POST, false),
            ],
            'message' => 'OK',
        ]);
    }

    /**
     * @throws AuthorizationException
     */
    public function update(Request $request): JsonResponse
    {
        $user = $request->user();
        if (! $user instanceof User) {
            throw new AuthorizationException('Unauthenticated.');
        }

        Gate::forUser($user)->authorize('settings.manage');

        $validated = $request->validate([
            'auto_post' => ['required', 'boolean'],
        ]);

        AppSetting::setBool(self::KEY_AUTO_POST, (bool) $validated['auto_post']);

        return response()->json([
            'success' => true,
            'data' => [
                'auto_post' => AppSetting::getBool(self::KEY_AUTO_POST, false),
            ],
            'message' => 'Settings updated',
        ]);
    }
}

