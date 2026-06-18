<?php
// app/Models/VoucherApproval.php

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

    // Workflow Actions
    const ACTION_SAVED = 'Saved';
    const ACTION_SUBMITTED = 'Submitted';
    const ACTION_APPROVED = 'Approved';
    const ACTION_DECLINED = 'Declined';
    const ACTION_SENT_BACK = 'Sent Back';
    const ACTION_FORWARDED = 'Forwarded';
    const ACTION_CLOSED = 'Closed';
    const ACTION_FINAL_REJECTED = 'Final Rejected';
    
    // Workflow Roles
    const ROLE_CREATOR = 'Creator';
    const ROLE_DFA = 'Director of Finance';
    const ROLE_IA = 'Internal Audit';
    const ROLE_FA = 'Final Accounts';
    const ROLE_EC = 'Expenditure Control';
    const ROLE_INSPECTORATE = 'Inspectorate';
    const ROLE_TCO = 'Treasury Cash Office';
    const ROLE_AG = 'Accountant General';
    const ROLE_MAS = 'Management Account Section';
    
    // Statuses
    const STATUS_DRAFT = 'draft';
    const STATUS_PENDING = 'pending';
    const STATUS_APPROVED = 'approved';
    const STATUS_REJECTED = 'rejected';
    const STATUS_FORWARDED = 'forwarded';
    const STATUS_SENT_BACK = 'sent_back';
    const STATUS_CLOSED = 'closed';
    const STATUS_FINAL_REJECTED = 'final_rejected';

    /**
     * Relationship with Voucher
     */
    public function voucher(): BelongsTo
    {
        return $this->belongsTo(Voucher::class);
    }

    /**
     * Relationship with Approver User
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
     * Get the workflow stage based on approval step
     */
    public function getStageAttribute(): string
    {
        $stages = [
            1 => 'DFA',
            2 => 'Internal Audit',
            3 => 'Final Accounts',
            4 => 'Expenditure Control',
            5 => 'Accountant General',
            6 => 'Management Account Section',
        ];
        
        // For salary/pension/gratuity, different flow after EC
        if ($this->voucher && $this->voucher->voucher_type === 'salary') {
            $stages[4] = 'Inspectorate';
            $stages[5] = 'Treasury Cash Office';
        }
        
        return $stages[$this->approval_step] ?? 'Unknown';
    }

    /**
     * Check if this is the final approval
     */
    public function getIsFinalAttribute(): bool
    {
        if ($this->voucher && $this->voucher->voucher_type === 'salary') {
            return $this->approval_step >= 5;
        }
        return $this->approval_step >= 6;
    }

    /**
     * Scopes
     */
    public function scopePending($query)
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    public function scopeApproved($query)
    {
        return $query->where('action', self::ACTION_APPROVED);
    }

    public function scopeDeclined($query)
    {
        return $query->where('action', self::ACTION_DECLINED);
    }

    public function scopeSentBack($query)
    {
        return $query->where('action', self::ACTION_SENT_BACK);
    }

    public function scopeForwarded($query)
    {
        return $query->where('action', self::ACTION_FORWARDED);
    }

    public function scopeByRole($query, $role)
    {
        return $query->where('approval_role', $role);
    }

    public function scopeByStep($query, $step)
    {
        return $query->where('approval_step', $step);
    }

    /**
     * Check if approval is pending
     */
    public function getIsPendingAttribute(): bool
    {
        return $this->status === self::STATUS_PENDING;
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
     * Auto-set timestamps and workflow based on action
     */
    public static function boot()
    {
        parent::boot();

        static::saving(function ($model) {
            $now = now();
            
            if ($model->isDirty('action')) {
                $model->action_at = $now;
            }

            if ($model->isDirty('action') && $model->action === self::ACTION_APPROVED) {
                $model->approved_at = $now;
            }

            if ($model->isDirty('action') && $model->action === self::ACTION_DECLINED) {
                $model->rejected_at = $now;
            }

            // Update status based on action
            if ($model->isDirty('action')) {
                switch ($model->action) {
                    case self::ACTION_APPROVED:
                        $model->status = self::STATUS_APPROVED;
                        break;
                    case self::ACTION_DECLINED:
                        $model->status = self::STATUS_REJECTED;
                        break;
                    case self::ACTION_SENT_BACK:
                        $model->status = self::STATUS_SENT_BACK;
                        break;
                    case self::ACTION_FORWARDED:
                        $model->status = self::STATUS_FORWARDED;
                        break;
                    case self::ACTION_CLOSED:
                        $model->status = self::STATUS_CLOSED;
                        break;
                    default:
                        $model->status = self::STATUS_PENDING;
                        break;
                }
            }
        });
    }
}