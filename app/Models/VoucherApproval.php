<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VoucherApproval extends Model
{
    protected $fillable = [
        'voucher_id',
        'user_id',
        'approval_role',
        'approval_step',
        'action',
        'status',
        'comment',
        'action_at',
        'approved_at',
        'rejected_at',
        'approval_level',
        'next_approval_user_id',
    ];

    protected $casts = [
        'action_at' => 'datetime',
        'approved_at' => 'datetime',
        'rejected_at' => 'datetime',
        'approval_step' => 'integer',
        'approval_level' => 'integer',
    ];

    // Define the possible actions
    const ACTION_APPROVED = 'Approved';
    const ACTION_DECLINED = 'Declined';
    const ACTION_SENT_BACK = 'Sent Back';
    const ACTION_FORWARDED = 'Forwarded';

    /**
     * Relationship with Voucher
     */
    public function voucher(): BelongsTo
    {
        return $this->belongsTo(Voucher::class);
    }

    /**
     * Relationship with Approver User - FIXED: Add this relationship
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Relationship with Approver User (alias)
     */
    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Relationship with Next Approver
     */
    public function nextApprover(): BelongsTo
    {
        return $this->belongsTo(User::class, 'next_approval_user_id');
    }

    /**
     * Scope for pending approvals
     */
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    /**
     * Scope for approved records
     */
    public function scopeApproved($query)
    {
        return $query->where('action', self::ACTION_APPROVED);
    }

    /**
     * Scope for declined records
     */
    public function scopeDeclined($query)
    {
        return $query->where('action', self::ACTION_DECLINED);
    }

    /**
     * Scope for sent back records
     */
    public function scopeSentBack($query)
    {
        return $query->where('action', self::ACTION_SENT_BACK);
    }

    /**
     * Scope for forwarded records
     */
    public function scopeForwarded($query)
    {
        return $query->where('action', self::ACTION_FORWARDED);
    }

    /**
     * Scope for current step approvals
     */
    public function scopeCurrentStep($query, $step)
    {
        return $query->where('approval_step', $step);
    }

    /**
     * Check if approval is pending
     */
    public function getIsPendingAttribute(): bool
    {
        return $this->status === 'pending';
    }

    /**
     * Check if approval is approved
     */
    public function getIsApprovedAttribute(): bool
    {
        return $this->action === self::ACTION_APPROVED;
    }

    /**
     * Check if approval is declined
     */
    public function getIsDeclinedAttribute(): bool
    {
        return $this->action === self::ACTION_DECLINED;
    }

    /**
     * Check if approval is sent back
     */
    public function getIsSentBackAttribute(): bool
    {
        return $this->action === self::ACTION_SENT_BACK;
    }

    /**
     * Check if approval is forwarded
     */
    public function getIsForwardedAttribute(): bool
    {
        return $this->action === self::ACTION_FORWARDED;
    }

    /**
     * Auto-set timestamps based on action
     */
    public static function boot()
    {
        parent::boot();

        static::saving(function ($model) {
            $now = now();
            
            // Set action_at when action changes
            if ($model->isDirty('action')) {
                $model->action_at = $now;
            }

            // Set approved_at when approved
            if ($model->isDirty('action') && $model->action === self::ACTION_APPROVED) {
                $model->approved_at = $now;
            }

            // Set rejected_at when declined
            if ($model->isDirty('action') && $model->action === self::ACTION_DECLINED) {
                $model->rejected_at = $now;
            }

            // Update status based on action
            if ($model->isDirty('action')) {
                switch ($model->action) {
                    case self::ACTION_APPROVED:
                        $model->status = 'approved';
                        break;
                    case self::ACTION_DECLINED:
                        $model->status = 'rejected';
                        break;
                    case self::ACTION_SENT_BACK:
                        $model->status = 'sent_back';
                        break;
                    case self::ACTION_FORWARDED:
                        $model->status = 'forwarded';
                        break;
                    default:
                        $model->status = 'pending';
                        break;
                }
            }
        });
    }
}