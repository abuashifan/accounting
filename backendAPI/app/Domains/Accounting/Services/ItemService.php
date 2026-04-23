<?php

namespace App\Domains\Accounting\Services;

use App\Domains\Accounting\Models\Item;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\ValidationException;

class ItemService
{
    public function list(int $perPage = 50, bool $includeStock = false): LengthAwarePaginator
    {
        $query = Item::query()
            ->orderBy('code')
            ->when($includeStock, function (Builder $q): Builder {
                return $q->select('items.*')->selectSub(
                    fn ($sub) => $sub
                        ->from('stock_balances')
                        ->selectRaw('COALESCE(SUM(stock_balances.quantity), 0)')
                        ->whereColumn('stock_balances.item_id', 'items.id'),
                    'current_qty'
                );
            });

        return $query->paginate($perPage);
    }

    public function create(array $data): Item
    {
        $this->assertAverageCost($data['cost_method'] ?? null);

        /** @var Item $item */
        $item = Item::query()->create($data);

        return $item;
    }

    public function update(int $id, array $data): Item
    {
        $this->assertAverageCost($data['cost_method'] ?? null);

        /** @var Item $item */
        $item = Item::query()->findOrFail($id);
        $item->fill($data)->save();

        return $item->fresh();
    }

    public function find(int $id): Item
    {
        /** @var Item $item */
        $item = Item::query()->findOrFail($id);

        return $item;
    }

    private function assertAverageCost(?string $costMethod): void
    {
        if ($costMethod !== null && $costMethod !== 'average') {
            throw ValidationException::withMessages([
                'cost_method' => ['Only average cost is supported.'],
            ]);
        }
    }
}
