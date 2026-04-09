<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class VoucherApprovalResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'voucher_id' => $this->voucher_id,
            'user_id' => $this->user_id,
            'approval_role' => $this->approval_role,
            'approval_step' => $this->approval_step,
            'action' => $this->action,
            'status' => $this->status,
            'comment' => $this->comment,
            'action_at' => $this->action_at?->format('Y-m-d H:i:s'),
            'approved_at' => $this->approved_at?->format('Y-m-d H:i:s'),
            'rejected_at' => $this->rejected_at?->format('Y-m-d H:i:s'),
            'approval_level' => $this->approval_level,
            'next_approval_user_id' => $this->next_approval_user_id,
            
            // User data
            'user' => $this->whenLoaded('user', function () {
                return [
                    'id' => $this->user->id,
                    'name' => $this->user->name,
                    'email' => $this->user->email,
                ];
            }),
            
            // Next approver data
            'next_approver' => $this->whenLoaded('nextApprover', function () {
                return [
                    'id' => $this->nextApprover->id,
                    'name' => $this->nextApprover->name,
                    'email' => $this->nextApprover->email,
                ];
            }),
            
            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at?->format('Y-m-d H:i:s'),
        ];
    }
}