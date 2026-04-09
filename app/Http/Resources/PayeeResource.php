<?php


namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class PayeeResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'status' => (bool)$this->status,
            'created_at' => $this->created_at->format('d M Y'),
        ];
    }
}
