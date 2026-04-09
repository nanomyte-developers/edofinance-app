<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class FinancialYearResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array|\Illuminate\Contracts\Support\Arrayable|\JsonSerializable
     */
    public function toArray($request)
    {
        return [
            // Map Model attributes to JSON keys
            'id' => $this->id,
            'name' => $this->name,
            'start_date' => $this->start_date,
            'end_date' => $this->end_date,
            'is_active' => $this->is_active,
            'created_at' => $this->created_at,
            // Include related models if needed, e.g., 'vouchers' => VoucherResource::collection($this->whenLoaded('vouchers'))
        ];
    }
}
