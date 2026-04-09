<?php

namespace App\Services;

use App\Models\Mda;
use Illuminate\Support\Arr;

class MdaService
{
    /**
     * Create a new MDA or update an existing one.
     * * @param array $data The validated data from the request.
     * @param Mda|null $mda The MDA model instance for updates, or null for creation.
     * @return Mda
     */
    public function saveOrUpdate(array $data, ?Mda $mda = null): Mda
    {
        // dd($mda);
        if ($mda) {
            // Update operation: Business logic for updates resides here
            $mda->update($data);
            return $mda;
        } else {
            // Create operation: Business logic for creation resides here
            return Mda::create($data);
        }
    }
}