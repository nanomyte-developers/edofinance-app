<?php

namespace App\Services;

use App\Models\EconomyCodeItem;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

class EconomyCodeItemService
{
    /**
     * Get a paginated list of all EconomyCodeItems.
     *
     * @param int $perPage
     * @return LengthAwarePaginator
     */
    public function getPaginatedItems(int $perPage = 10): LengthAwarePaginator
    {
        return EconomyCodeItem::with('economyCode')
            ->latest()
            ->paginate($perPage);
    }

    /**
     * Find a specific EconomyCodeItem by ID.
     *
     * @param int $id
     * @return EconomyCodeItem|null
     */
    public function findItem(int $id): ?EconomyCodeItem
    {
        return EconomyCodeItem::with('economyCode')->find($id);
    }

    /**
     * Create a new EconomyCodeItem.
     *
     * @param array $data
     * @return EconomyCodeItem
     */
    public function createItem(array $data): EconomyCodeItem
    {
        return EconomyCodeItem::create($data);
    }

    /**
     * Update an existing EconomyCodeItem.
     *
     * @param EconomyCodeItem $item
     * @param array $data
     * @return EconomyCodeItem
     */
    public function updateItem(EconomyCodeItem $item, array $data): EconomyCodeItem
    { 
        // dd($item);
        $item->update($data);
        return $item;
    }

    /**
     * Delete an EconomyCodeItem.
     *
     * @param EconomyCodeItem $item
     * @return bool|null
     */
    public function deleteItem(EconomyCodeItem $item): ?bool
    {
        return $item->delete();
    }
}
