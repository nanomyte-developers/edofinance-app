<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class BankResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id'       => $this->id,
            'name'     => $this->name,
            'code'     => $this->code,
            'initials' => $this->initials,
            'status'   => (int) $this->status,
        ];
    }
}
