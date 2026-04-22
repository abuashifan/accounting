<?php

namespace App\Http\Controllers\Accounting;

use App\Domains\Accounting\Services\AccountService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Accounting\StoreAccountRequest;
use App\Http\Requests\Accounting\UpdateAccountRequest;
use App\Models\Account;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AccountController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $accounts = Account::query()
            ->when(
                ! $request->boolean('include_inactive', false),
                fn ($q) => $q->where('is_active', true),
            )
            ->orderBy('code')
            ->get(['id', 'code', 'name', 'type', 'is_active']);

        return response()->json([
            'success' => true,
            'data' => $accounts,
            'message' => 'OK',
        ]);
    }

    public function store(StoreAccountRequest $request, AccountService $service): JsonResponse
    {
        $account = $service->create($request->validated());

        return response()->json([
            'success' => true,
            'data' => $account,
            'message' => 'Account created',
        ], 201);
    }

    public function update(int $id, UpdateAccountRequest $request, AccountService $service): JsonResponse
    {
        $account = $service->update($id, $request->validated());

        return response()->json([
            'success' => true,
            'data' => $account,
            'message' => 'Account updated',
        ]);
    }
}
