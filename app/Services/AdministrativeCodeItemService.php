<?php

namespace App\Services;

use App\Models\AdministrativeSectorCode;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

class AdministrativeCodeItemService
{
    /**
     * Get a paginated list of all AdministrativeSectorCode.
     *
     * @param int $perPage
     * @return LengthAwarePaginator
     */
    public function getPaginatedItems(int $perPage = 10): LengthAwarePaginator
    {
        return AdministrativeSectorCode::with('administrativeCode')
            ->latest()
            ->paginate($perPage);
    }

    /**
     * Find a specific AdministrativeSectorCode by ID.
     *
     * @param int $id
     * @return AdministrativeSectorCode|null
     */
    public function findItem(int $id): ?AdministrativeSectorCode
    {
        return AdministrativeSectorCode::with('administrativeCode')->find($id);
    }

    /**
     * Create a new AdministrativeSectorCode.
     *
     * @param array $data
     * @return AdministrativeSectorCode
     */
    public function createItem(array $data): AdministrativeSectorCode
    {
        return AdministrativeSectorCode::create($data);
    }

    /**
     * Update an existing AdministrativeSectorCode.
     *
     * @param AdministrativeSectorCode $item
     * @param array $data
     * @return AdministrativeSectorCode
     */
    public function updateItem(AdministrativeSectorCode $item, array $data): AdministrativeSectorCode
    {
        // dd($data, $item);
    
        $item->update($data);

        // dd($item);
        return $item;
    }

    /**
     * Delete an AdministrativeSectorCode.
     *
     * @param AdministrativeSectorCode $item
     * @return bool|null
     */
    public function deleteItem(AdministrativeSectorCode $item): ?bool
    {
        return $item->delete();
    }
}
