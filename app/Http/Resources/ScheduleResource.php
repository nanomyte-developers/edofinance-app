<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ScheduleResource extends JsonResource
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
            'year_id' => $this->year_id,
            'mda_id' => $this->mda_id,
            'budget_code_id' => $this->budget_code_id,
            'schedule_number' => $this->schedule_number,
            'status' => $this->status,
            'total_amount' => $this->total_amount,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            
            // Relationships
            'financial_year' => $this->whenLoaded('financialYear'),
            'mda' => $this->whenLoaded('mda'),
            'budget_code' => $this->whenLoaded('budgetCode'),
            'items' => ScheduleItemResource::collection($this->whenLoaded('items')),
        ];
    }
}