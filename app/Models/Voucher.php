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

    const TYPE_CAPITAL = 'capital';

    const TYPE_RECURRENT = 'recurrent';

    const TYPE_PREPAYMENT = 'prepayment';

    const TYPE_SALARY = 'salary';

    const TYPE_GRATUITY = 'gratuity';

    const TYPE_PENSION = 'pension';

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
        'final_approved_at',
        'final_approved_by',
        'is_final_accounts',
        'ec_approved_by',
        'ec_approved_at',
        'ag_approved_by',
        'ag_approved_at',
        'mas_approved_by',
        'mas_approved_at',
        'closed_at',
        'payment_reference',
        'payment_comment',
        'payment_date',
        'forwarded_to_inspectorate_at',
        'forwarded_to_inspectorate_by',
        'i_approved_at', //inspectorate approval timestamp
        'i_approved_by', //inspectorate approval user id
        'tco_approved_at',
        'tco_approved_by',
        'assigned_to_user_id',
        'assigned_at',
        'assigned_by',
    ];

    protected $casts = [
        'voucher_date' => 'date',
        'total_amount' => 'decimal:2',
        'requires_retirement' => 'boolean',
        'retired_at' => 'datetime',
        'is_final_accounts' => 'boolean',
        'final_approved_at' => 'datetime',
        'ec_approved_at' => 'datetime',
        'ag_approved_at' => 'datetime',
        'mas_approved_at' => 'datetime',
        'closed_at' => 'datetime',
        'payment_date' => 'datetime',
        'forwarded_to_inspectorate_at' =>'datetime',
        'i_approved_at' => 'datetime',
        'tco_approved_at' => 'datetime',
        'assigned_at' => 'datetime',
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

    /**
     * Get the current workflow stage based on approvals
     */
    public function getCurrentWorkflowStageAttribute(): string
    {
        $maxStep = $this->approvals()->max('approval_step') ?? 0;
        
        $stages = [
            0 => 'Draft',
            1 => 'DFA Review',
            2 => 'Internal Audit',
            3 => 'Final Accounts',
            4 => 'Expenditure Control',
            5 => 'Accountant General',
            6 => 'Management Account Section',
        ];
        
        if ($this->voucher_type === 'salary') {
            $stages[4] = 'Inspectorate';
            $stages[5] = 'Treasury Cash Office';
        }
        
        return $stages[$maxStep] ?? 'Unknown';
    }

    /**
     * Get next approval role based on current step and voucher type
     */
    public function getNextApprovalRole(): ?string
    {
        $currentStep = $this->approvals()->max('approval_step') ?? 0;
        $nextStep = $currentStep + 1;
        
        $workflow = [
            1 => VoucherApproval::ROLE_DFA,
            2 => VoucherApproval::ROLE_IA,
            3 => VoucherApproval::ROLE_FA,
        ];
        
        if ($this->voucher_type === 'salary') {
            $workflow[4] = VoucherApproval::ROLE_INSPECTORATE;
            $workflow[5] = VoucherApproval::ROLE_TCO;
        } else {
            $workflow[4] = VoucherApproval::ROLE_EC;
            $workflow[5] = VoucherApproval::ROLE_AG;
            $workflow[6] = VoucherApproval::ROLE_MAS;
        }
        
        return $workflow[$nextStep] ?? null;
    }

    /**
     * Check if voucher is ready for Final Accounts
     */
    public function getIsReadyForFinalAccountsAttribute(): bool
    {
        // Voucher must be approved by Internal Audit (step 2)
        $iaApproval = $this->approvals()
            ->where('approval_step', 2)
            ->where('action', VoucherApproval::ACTION_APPROVED)
            ->exists();
        
        return $iaApproval && $this->status === 'audit_approved' && !$this->is_final_accounts;
    }

    /**
     * Check if voucher is a salary payment
     */
    public function getIsSalaryPaymentAttribute(): bool
    {
        return $this->voucher_type === 'salary';
    }

    /**
     * Check if voucher is a capital/DAT/recurrent payment
     */
    public function getIsOtherPaymentAttribute(): bool
    {
        return in_array($this->voucher_type, ['standard', 'prepayment']);
    }

    // Add relationship for final approver
    public function finalApprover(): BelongsTo
    {
        return $this->belongsTo(User::class, 'final_approved_by');
    }

    // Add relationships for expenditure control approver
    public function ecApprover(): BelongsTo
    {
        return $this->belongsTo(User::class, 'ec_approved_by');
    }

    // Add relationships for Accountant General approver
    public function agApprover(): BelongsTo
    {
        return $this->belongsTo(User::class, 'ag_approved_by');
    }

    // Add relationships for Management Account Section approver
    public function masApprover(): BelongsTo
    {
        return $this->belongsTo(User::class, 'mas_approved_by');
    }

    // Add relationship for Inspectorate approver
    public function inspectorateApprover(): BelongsTo
    {
        return $this->belongsTo(User::class, 'inspectorate_approved_by');
    }

    // Add relationship for TCO approver
    public function tcoApprover(): BelongsTo
    {
        return $this->belongsTo(User::class, 'tco_approved_by');
    }

    /**
     * Get the user this voucher is assigned to
     */
    public function assignedTo()
    {
        return $this->belongsTo(User::class, 'assigned_to_user_id');
    }

    /**
     * Get the user who assigned this voucher
     */
    public function assignedBy()
    {
        return $this->belongsTo(User::class, 'assigned_by');
    }
}
