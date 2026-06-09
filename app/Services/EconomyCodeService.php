<?php

namespace App\Services;

use App\Models\EconomyCode;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * Handles all business logic related to Economy Codes.
 */
class EconomyCodeService
{
    /**
     * Fetch all economy codes with pagination and filter/search logic.
     *
     * @param string|null $search
     * @param int $perPage
     * @return LengthAwarePaginator
     */
    public function getPaginatedCodes(?string $search, int $perPage = 10): LengthAwarePaginator
    {
        $query = EconomyCode::query();

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('code', 'like', "%{$search}%");
            });
        }

        // Apply default sorting (e.g., newest first or by code)
        $query->orderBy('code', 'asc');

        return $query->paginate($perPage);
    }

    /**
     * Create a new EconomyCode record.
     *
     * @param array<string, mixed> $data
     * @return EconomyCode
     */
    public function createCode(array $data): EconomyCode
    {
        // Convert status integer back to enum string before saving
        $data['status'] = 1; //$data['status'] == 1 ? 'active' : 'inactive';

        return EconomyCode::create($data);
    }

    /**
     * Update an existing EconomyCode record.
     *
     * @param EconomyCode $code
     * @param array<string, mixed> $data
     * @return EconomyCode
     */
    public function updateCode(EconomyCode $code, array $data): EconomyCode
    {
        // dd($data);
        if (isset($data['status'])) {
            // Convert status integer back to enum string before saving
            $data['status'] = ($data['status'] == 1 || $data['status'] == '1') ? 'active' : 'inactive';
            }
            
            // dd($data);
            $code->update($data);
        return $code;
    }

    /**
     * Delete an EconomyCode record.
     *
     * @param EconomyCode $code
     * @return bool|null
     */
    public function deleteCode(EconomyCode $code): ?bool
    {
        return $code->delete();
    }

    public function countAll(): int
    {
        return EconomyCode::count();
    }

    public function countActive(): int
    {
        return EconomyCode::where('status', 'active')->count();
    }

    public function countInactive(): int
    {
        return EconomyCode::where('status', 'inactive')->count();
    }
}
