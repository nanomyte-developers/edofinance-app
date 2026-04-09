<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RetirementVoucherResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'retirement_number' => $this->retirement_number,
            'status' => $this->status,
            'retirement_type' => $this->retirement_type,
            'original_voucher_amount' => (float) $this->original_voucher_amount,
            'retired_amount' => (float) $this->retired_amount,
            'remaining_balance' => (float) $this->remaining_balance,
            'comments' => $this->comments,

            // Relationships
            'original_voucher' => $this->whenLoaded('originalVoucher', function () {
                return [
                    'id' => $this->originalVoucher->id,
                    'voucher_number' => $this->originalVoucher->voucher_number,
                    'total_amount' => (float) $this->originalVoucher->total_amount,
                    'payee_name' => $this->originalVoucher->payee_name,
                ];
            }),

            'mda' => $this->whenLoaded('mda', function () {
                return [
                    'id' => $this->mda->id,
                    'name' => $this->mda->name,
                    'code' => $this->mda->code,
                ];
            }),

            'creator' => $this->whenLoaded('creator', function () {
                return [
                    'id' => $this->creator->id,
                    'name' => $this->creator->name,
                    'email' => $this->creator->email,
                ];
            }),

            'approver' => $this->whenLoaded('approver', function () {
                return [
                    'id' => $this->approver->id,
                    'name' => $this->approver->name,
                ];
            }),

            'items' => $this->whenLoaded('items', function () {
                return $this->items->map(function ($item) {
                    return [
                        'id' => $item->id,
                        'description' => $item->description,
                        'economic_code' => $item->economicCode ? [
                            'id' => $item->economicCode->id,
                            'code' => $item->economicCode->code,
                            'name' => $item->economicCode->name,
                        ] : null,
                        'economic_code_item' => $item->codeItem ? [
                            'id' => $item->codeItem->id,
                            'code' => $item->codeItem->code,
                            'name' => $item->codeItem->name,
                        ] : null,
                        'quantity' => (float) $item->quantity,
                        'unit_price' => (float) $item->unit_price,
                        'sub_total' => (float) $item->sub_total,
                        'comments' => $item->comments,
                    ];
                });
            }),

            'logs' => $this->whenLoaded('logs', function () {
                return $this->logs->map(function ($log) {
                    return [
                        'id' => $log->id,
                        'action' => $log->action,
                        'comment' => $log->comment,
                        'user' => $log->user ? [
                            'id' => $log->user->id,
                            'name' => $log->user->name,
                        ] : null,
                        'created_at' => $log->created_at->format('Y-m-d H:i:s'),
                    ];
                });
            }),

            // Dates
            'created_at' => $this->created_at->format('Y-m-d H:i:s'),
            'approved_at' => $this->approved_at?->format('Y-m-d H:i:s'),
        ];
    }
}
