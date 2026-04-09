<?php

namespace App\Models;

use App\Traits\LogsActivity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Spatie\Activitylog\LogOptions;

class Voucher extends Model
{
    use LogsActivity;

    const ACTION_SAVED = 'Saved';

    const ACTION_APPROVED = 'Approved';

    const ACTION_DECLINED = 'Declined';

    const ACTION_SENT_BACK = 'Sent Back';

    const ACTION_FORWARDED = 'Forwarded';

    const ACTION_CLOSED = 'Closed';

    const ACTION_DECLINE_AND_CLOSE = 'Decline and Close';

    const STATUS_DRAFT = 'draft';

    const STATUS_PENDING = 'pending';

    const STATUS_APPROVED = 'approved';

    const STATUS_SUBMITTED = 'submitted';

    const STATUS_REJECTED = 'rejected';

    const STATUS_CLOSED = 'closed';

    const STATUS_RETIRED = 'retired';

    const TYPE_STANDARD = 'standard';

    const TYPE_PREPAYMENT = 'prepayment';

    protected $fillable = [
        'voucher_number',
        'year_id',
        'mda_id',
        'created_by_user_id',
        'voucher_date',
        'narration',
        'total_amount',
        'status',
        'voucher_type',
        'rejection_reason',
        'schedule_id',
        'requires_retirement',
        'retired_at',
        'retirement_voucher_id',
        'payee_name',
        'bank_activity_id',
    ];

    protected $casts = [
        'voucher_date' => 'date',
        'total_amount' => 'decimal:2',
        'requires_retirement' => 'boolean',
        'retired_at' => 'datetime',
    ];

    protected $logAttributes = [
        'voucher_number',
        'amount',
        'payee_name',
        'status',
        'bank_details',
        'payment_date',
        'checked_by',
        'approved_by',
        'remarks',
    ];

    protected $logOnlyDirty = true;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly($this->logAttributes)
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->useLogName('voucher')
            ->setDescriptionForEvent(fn (string $eventName) => "Voucher {$this->voucher_number} was {$eventName}");
    }

    /**
     * Relationship with FinancialYear
     */
    public function financialYear(): BelongsTo
    {
        return $this->belongsTo(FinancialYear::class, 'year_id');
    }

    /**
     * Relationship with MDA - UPDATED to use AdministrativeCode like Schedule
     */
    public function mda(): BelongsTo
    {
        return $this->belongsTo(Mda::class, 'mda_id');
    }

    /**
     * Relationship with source Schedule
     */
    public function schedule(): BelongsTo
    {
        return $this->belongsTo(Schedule::class, 'schedule_id');
    }

    /**
     * Relationship with line items
     */
    public function items(): HasMany
    {
        return $this->hasMany(VoucherItem::class);
    }

    /**
     * Relationship with documents
     */
    public function documents(): HasMany
    {
        return $this->hasMany(VoucherDocument::class);
    }

    /**
     * Relationship with approvals
     */
    public function approvals(): HasMany
    {
        return $this->hasMany(VoucherApproval::class);
    }

    /**
     * Relationship with creator
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    /**
     * Relationship with retirement voucher (for prepayment vouchers)
     */
    public function retirementVoucher(): BelongsTo
    {
        return $this->belongsTo(Voucher::class, 'retirement_voucher_id');
    }

    /**
     * Calculate total amount from items
     */
    public function calculateTotalAmount(): float
    {
        return $this->items->sum('sub_total');
    }

    /**
     * Check if voucher can be edited
     */
    public function getCanEditAttribute(): bool
    {
        return in_array($this->status, ['Draft', 'Pending']);
    }

    /**
     * Check if voucher is approved
     */
    public function getIsApprovedAttribute(): bool
    {
        return $this->status === 'Approved';
    }

    /**
     * Check if voucher is prepayment type - FIXED: Use direct comparison
     */
    public function getIsPrepaymentAttribute(): bool
    {
        return $this->voucher_type === self::TYPE_PREPAYMENT;
    }

    /**
     * Check if voucher requires retirement - FIXED: Safe accessor
     */
    public function getRequiresRetirementAttribute(): bool
    {
        // Check if the attribute exists and is not null
        if (array_key_exists('requires_retirement', $this->attributes)) {
            return (bool) $this->attributes['requires_retirement'];
        }

        // Fallback: determine based on voucher type
        return $this->voucher_type === self::TYPE_PREPAYMENT;
    }

    /**
     * Check if voucher is retired
     */
    public function getIsRetiredAttribute(): bool
    {
        return ! is_null($this->retired_at);
    }

    /**
     * Check if voucher can be retired
     */
    public function getCanRetireAttribute(): bool
    {
        return $this->requires_retirement &&
            $this->is_approved &&
            ! $this->is_retired &&
            is_null($this->retirement_voucher_id);
    }

    /**
     * Auto-set retirement requirement for prepayment vouchers
     */
    public static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            // Auto-set retirement requirement for prepayment vouchers
            if ($model->voucher_type === self::TYPE_PREPAYMENT) {
                $model->requires_retirement = true;
            } else {
                $model->requires_retirement = false;
            }
        });

        static::saving(function ($model) {
            if ($model->isDirty('voucher_type')) {
                // Update retirement requirement when voucher type changes
                $model->requires_retirement = $model->voucher_type === self::TYPE_PREPAYMENT;
            }
        });
    }

    /**
     * Get current approval step
     */
    public function getCurrentApprovalStepAttribute()
    {
        return $this->approvals()->max('approval_step') ?? 1;
    }

    /**
     * Get current approval (latest for current step)
     */
    public function currentApproval(): BelongsTo
    {
        return $this->belongsTo(VoucherApproval::class, 'id', 'voucher_id')
            ->where('approval_step', $this->current_approval_step)
            ->latestOfMany();
    }

    /**
     * Get all approvals ordered by step and creation time
     */
    public function orderedApprovals(): HasMany
    {
        return $this->approvals()
            ->orderBy('approval_step')
            ->orderBy('created_at');
    }

    /**
     * Scope for prepayment vouchers
     */
    public function scopePrepayment($query)
    {
        return $query->where('voucher_type', self::TYPE_PREPAYMENT);
    }

    /**
     * Scope for standard vouchers
     */
    public function scopeStandard($query)
    {
        return $query->where('voucher_type', self::TYPE_STANDARD);
    }

    /**
     * Scope for vouchers requiring retirement
     */
    public function scopeRequiresRetirement($query)
    {
        return $query->where('requires_retirement', true);
    }

    /**
     * Scope for retired vouchers
     */
    public function scopeRetired($query)
    {
        return $query->whereNotNull('retired_at');
    }

    /**
     * Scope for not retired vouchers
     */
    public function scopeNotRetired($query)
    {
        return $query->whereNull('retired_at');
    }

    /**
     * Get the BankActivity associated with the Voucher
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasOne
     */
    public function bankActivity(): HasOne
    {
        return $this->hasOne(BankActivity::class, 'id', 'bank_activity_id');
    }
}
