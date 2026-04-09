<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class VoucherResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'year_id' => $this->year_id,
            'mda_id' => $this->mda_id,
            'created_by_user_id' => $this->created_by_user_id,
            'voucher_number' => $this->voucher_number,
            'voucher_date' => $this->voucher_date,
            'total_amount' => (float) $this->total_amount,
            'narration' => $this->narration,
            'status' => $this->status,
            'voucher_type' => $this->voucher_type,
            'rejection_reason' => $this->rejection_reason,
            'schedule_id' => $this->schedule_id, // ADDED
            'requires_retirement' => $this->requires_retirement, // ADDED
            'retired_at' => $this->retired_at?->format('Y-m-d H:i:s'), // ADDED
            'is_retired' => $this->is_retired, // ADDED
            'can_retire' => $this->can_retire, // ADDED

            // Nested Relationships
            'items' => VoucherItemResource::collection($this->whenLoaded('items')),
            'documents' => VoucherDocumentResource::collection($this->whenLoaded('documents')),
            'current_approval' => new VoucherApprovalResource($this->whenLoaded('currentApproval')),
            'approvals' => VoucherApprovalResource::collection($this->whenLoaded('approvals')),
            'schedule' => $this->whenLoaded('schedule'), // ADDED schedule relationship
            'retirement_voucher' => new VoucherResource($this->whenLoaded('retirementVoucher')), // ADDED
            
            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at?->format('Y-m-d H:i:s'),
        ];
    }
}