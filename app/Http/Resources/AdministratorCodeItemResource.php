<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AdministratorCodeItemResource extends JsonResource
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
            'administrative_code_id' => $this->administrative_code_id,
            'administrative_code_name' => $this->whenLoaded('administrativeCode', function () {
                // Assuming the parent model is named 'EconomyCode' and has a 'name' attribute
                return $this->administrativeCode->name ?? 'N/A';
            }),
            'name' => $this->name,
            'code' => $this->code,
            'status' => $this->status,
            // Convert 'active'/'inactive' to a boolean-like integer (1 or 0) for frontend convenience
            // 'status' => $this->status === 'active' ? 1 : 0,
            'created_at' => $this->created_at->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at->format('Y-m-d H:i:s'),
        ];
    }
}
