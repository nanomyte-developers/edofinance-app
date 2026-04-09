<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RetirementVoucher extends Model
{
    protected $fillable = [
        'original_voucher_id',
        'schedule_id',
        'year_id',
        'mda_id',
        'bank_activity_id',
        'retirement_number',
        'status',
        'retirement_type',
        'original_voucher_amount',
        'retired_amount',
        'remaining_balance',
        'comments',
        'created_by',
        'approved_by',
        'approved_at',
    ];

    protected $casts = [
        'original_voucher_amount' => 'decimal:2',
        'retired_amount' => 'decimal:2',
        'remaining_balance' => 'decimal:2',
        'approved_at' => 'datetime',
    ];

    // Relationships
    public function originalVoucher()
    {
        return $this->belongsTo(Voucher::class, 'original_voucher_id');
    }

    public function schedule()
    {
        return $this->belongsTo(Schedule::class);
    }

    public function mda()
    {
        return $this->belongsTo(Mda::class);
    }

    public function year()
    {
        return $this->belongsTo(FinancialYear::class, 'year_id');
    }

    public function bankActivity()
    {
        return $this->belongsTo(BankActivity::class);
    }

    public function items()
    {
        return $this->hasMany(RetirementItem::class);
    }

    public function logs()
    {
        return $this->hasMany(RetirementLog::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function approver()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    // Scopes
    public function scopePending($query)
    {
        return $query->where('status', 'submitted');
    }

    public function scopeApproved($query)
    {
        return $query->where('status', 'approved');
    }

    public function scopeForMda($query, $mdaId)
    {
        return $query->where('mda_id', $mdaId);
    }

    // Helpers
    public function canBeApproved()
    {
        return $this->status === 'submitted';
    }

    public function canBeDeleted()
    {
        return $this->status === 'draft' || $this->status === 'submitted';
    }
}
