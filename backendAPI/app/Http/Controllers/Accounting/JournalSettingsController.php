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
    private const KEY_ALLOW_ADMIN_EDIT_DELETE_POSTED = 'transactions.allow_admin_edit_delete_posted';

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
                'allow_admin_edit_delete_posted' => AppSetting::getBool(self::KEY_ALLOW_ADMIN_EDIT_DELETE_POSTED, false),
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
            'auto_post' => ['sometimes', 'boolean'],
            'allow_admin_edit_delete_posted' => ['sometimes', 'boolean'],
        ]);

        if (! array_key_exists('auto_post', $validated) && ! array_key_exists('allow_admin_edit_delete_posted', $validated)) {
            $request->validate([
                'auto_post' => ['required_without:allow_admin_edit_delete_posted', 'boolean'],
                'allow_admin_edit_delete_posted' => ['required_without:auto_post', 'boolean'],
            ]);
        }

        if (array_key_exists('auto_post', $validated)) {
            AppSetting::setBool(self::KEY_AUTO_POST, (bool) $validated['auto_post']);
        }
        if (array_key_exists('allow_admin_edit_delete_posted', $validated)) {
            AppSetting::setBool(self::KEY_ALLOW_ADMIN_EDIT_DELETE_POSTED, (bool) $validated['allow_admin_edit_delete_posted']);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'auto_post' => AppSetting::getBool(self::KEY_AUTO_POST, false),
                'allow_admin_edit_delete_posted' => AppSetting::getBool(self::KEY_ALLOW_ADMIN_EDIT_DELETE_POSTED, false),
            ],
            'message' => 'Settings updated',
        ]);
    }
}
