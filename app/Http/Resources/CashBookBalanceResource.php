<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class CashBookBalanceResource extends JsonResource
{
    // public function toArray($request): array
    // {
    //     return [
    //         'id' => $this->id,
    //         'bank_activity_id' => $this->bank_activity_id,
    //         'amount' => (float) $this->amount,
    //         'status' => (int) $this->status,
    //         'created_at' => $this->created_at->format('Y-m-d'),
    //     ];
    // }


    public function toArray($request): array
{
    return [
        'id' => $this->id,
        'financial_year' => $this->financial_year,
        'bank_activity_id' => $this->bank_activity_id,
        'amount' => (float) $this->amount,
        'status' => (int) $this->status,
        // Map fields from the relation
        'tag' => $this->bankActivity->tag ?? 'N/A',
        'bank_name' => $this->bankActivity->bank_name ?? 'N/A',
        'title' => $this->bankActivity->title ?? 'N/A',
        'account_number' => $this->bankActivity->account_number ?? 'N/A',
    ];
}
}
