<?php

namespace App\Domains\Accounting\Services;

use App\Domains\Accounting\Models\Account;
use Illuminate\Validation\ValidationException;

class AccountService
{
    public function create(array $data): Account
    {
        $this->assertValidParent(null, $data['parent_id'] ?? null);

        /** @var Account $account */
        $account = Account::query()->create($data);

        return $account;
    }

    public function update(int $id, array $data): Account
    {
        /** @var Account $account */
        $account = Account::query()->findOrFail($id);

        $this->assertValidParent($account->id, $data['parent_id'] ?? null);

        $account->fill($data)->save();

        return $account->fresh();
    }

    private function assertValidParent(?int $selfId, mixed $parentId): void
    {
        if ($parentId === null) {
            return;
        }

        $parentId = (int) $parentId;

        if ($selfId !== null && $parentId === $selfId) {
            throw ValidationException::withMessages([
                'parent_id' => ['parent_id cannot be the same as the account id.'],
            ]);
        }
    }
}

