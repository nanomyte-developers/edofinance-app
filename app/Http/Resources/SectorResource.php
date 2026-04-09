<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SectorResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'mda_id' => (int) $this->mda_id, // Ensure mda_id is an integer for Vue prop consistency
            'name' => $this->name,
            'code' => $this->code,
            'initials' => $this->initials,
            // Include the associated MDA details only if the relationship was loaded
            'mda' => $this->whenLoaded('mda', function () {
                // Return a simplified structure for the MDA
                return [
                    'id' => $this->mda->id,
                    'initials' => $this->mda->initials,
                    'name' => $this->mda->name,
                ];
            }),
            'location' => $this->location,
            'status' => $this->status,
            'created_at' => $this->created_at?->toDateTimeString(),
            'updated_at' => $this->updated_at?->toDateTimeString(),
        ];
    }
}