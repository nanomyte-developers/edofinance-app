<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class BankActivityResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'tag' => $this->tag,
            'bank_name' => $this->bank_name,
            'title' => $this->title,
            'account_number' => $this->account_number ?? 'N/A',
            'status' => $this->status,
            'created_at' => $this->created_at->format('Y-m-d H:i:s'),
        ];
    }
}
