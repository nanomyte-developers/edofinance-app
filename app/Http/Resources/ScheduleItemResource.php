<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ScheduleItemResource extends JsonResource
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
            'schedule_id' => $this->schedule_id,
            'date' => $this->date,
            'serial_no' => $this->serial_no,
            'economy_code_id' => $this->economy_code_id,
            'economy_code_item_id' => $this->economy_code_item_id,
            'payee_name' => $this->payee_name,
            'amount' => $this->amount,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            
            // Relationships
            'economy_code' => $this->whenLoaded('economyCode'),
            'economy_code_item' => $this->whenLoaded('economyCodeItem'),
        ];
    }
}