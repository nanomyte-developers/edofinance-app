<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RemittanceResource extends JsonResource
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
            'treasury' => $this->treasury,
            'receipt_number' => $this->receipt_number,
            'transfer_date' => $this->transfer_date,
            'source_bank_id' => $this->source_bank_id,
            'destination_bank_id' => $this->destination_bank_id,
            'source_bank' => $this->source_bank,
            'destination_bank' => $this->destination_bank,
            'source_bank_details' => $this->sourceBank ? [
                'id' => $this->sourceBank->id,
                'tag' => $this->sourceBank->tag,
                'bank_name' => $this->sourceBank->bank_name,
                'title' => $this->sourceBank->title,
                'account_number' => $this->sourceBank->account_number,
            ] : null,
            'destination_bank_details' => $this->destinationBank ? [
                'id' => $this->destinationBank->id,
                'tag' => $this->destinationBank->tag,
                'bank_name' => $this->destinationBank->bank_name,
                'title' => $this->destinationBank->title,
                'account_number' => $this->destinationBank->account_number,
            ] : null,
            'amount' => (float) $this->amount,
            'amount_in_words' => $this->amount_in_words,
            'narration' => $this->narration,
            'status' => $this->status,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'formatted_amount' => '₦' . number_format($this->amount, 2),
            'formatted_date' => $this->transfer_date ? date('d M, Y', strtotime($this->transfer_date)) : 'N/A',
        ];
    }
}