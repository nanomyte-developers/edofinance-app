<?php

namespace App\Services;

use App\Models\Sector;
use Illuminate\Support\Arr;

class SectorService
{
    /**
     * Creates a new Sector or updates an existing one.
     *
     * @param array $data The validated data from the request.
     * @param Sector|null $sector The sector instance to update, or null for creation.
     * @return Sector The created or updated Sector model.
     */
    public function saveOrUpdate(array $data, ?Sector $sector = null): Sector
    {
        // Include the new fields: initials, location, status
        $attributes = Arr::only($data, ['name', 'code', 'mda_id', 'initials', 'location', 'status']);

        if ($sector) {
            // Update existing sector
            $sector->update($attributes);
            return $sector;
        }

        // Create new sector
        return Sector::create($attributes);
    }
}