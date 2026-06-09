<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Journal extends Model
{
    protected $fillable = [
        'journal_number',
        'journal_date',
        'description',
        'total_amount',
        'total_debit',
        'total_credit',
        'status',
        'created_by',
        'approved_by',
        'approved_at',
        'posting_date',
        'financial_year',
        'batch_number',
        'journal_type',
        'remarks',
        'reference_number',
        'source_document',
        'is_recurring',
        'recurring_frequency',
        'next_recurring_date',
        'gl_category_id',
        'department_id',
        'administrative_sector_code_id',
        'administrative_code_id',
        'mda_id'
    ];

    protected $casts = [
        'journal_date' => 'date',
        'posting_date' => 'date',
        'next_recurring_date' => 'date',
        'approved_at' => 'datetime',
        'is_recurring' => 'boolean',
        'total_amount' => 'decimal:2',
        'total_debit' => 'decimal:2',
        'total_credit' => 'decimal:2',
    ];

    /**
     * Get the journal entries
     */
    public function entries(): HasMany
    {
        return $this->hasMany(JournalEntry::class);
    }

    /**
     * Get the created by user
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get the approver user
     */
    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /**
     * Get the GL category
     */
    public function glCategory(): BelongsTo
    {
        return $this->belongsTo(GlCategory::class);
    }

    /**
     * Get the department
     */
    // public function department(): BelongsTo
    // {
    //     return $this->belongsTo(Department::class);
    // }

    /**
     * Scope for active journals
     */
    public function scopeActive($query)
    {
        return $query->whereIn('status', ['draft', 'pending', 'approved']);
    }

    /**
     * Scope for pending approval
     */
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    /**
     * Scope for approved journals
     */
    public function scopeApproved($query)
    {
        return $query->where('status', 'approved');
    }

    /**
     * Scope for draft journals
     */
    public function scopeDraft($query)
    {
        return $query->where('status', 'draft');
    }

    /**
     * Check if journal is balanced (total debit = total credit)
     */
    public function isBalanced(): bool
    {
        return $this->total_debit == $this->total_credit;
    }

    /**
     * Check if journal can be edited
     */
    public function canEdit(): bool
    {
        $status = strtolower($this->status);
        $editableStatuses = ['draft', 'saved', 'sent back', 'returned', 'declined', 'rejected'];

        return in_array($status, $editableStatuses);
    }

    /**
     * Check if journal can be deleted
     */
    public function canDelete(): bool
    {
        $status = strtolower($this->status);
        $deletableStatuses = ['draft', 'saved'];

        return in_array($status, $deletableStatuses);
    }

    /**
     * Generate journal number
     */
    public static function generateJournalNumber_Old(): string
    {
        $year = date('Y');
        $month = date('m');
        $prefix = 'JNL';

        $lastJournal = self::where('journal_number', 'like', "$prefix-$year$month-%")
            ->orderBy('id', 'desc')
            ->first();

        if ($lastJournal) {
            $lastNumber = (int) substr($lastJournal->journal_number, -4);
            $newNumber = str_pad($lastNumber + 1, 4, '0', STR_PAD_LEFT);
        } else {
            $newNumber = '0001';
        }


        return "$prefix-$year$month-$newNumber";
    }

    public static function generateJournalNumber(): string
{
    $year = date('Y');
    $month = date('m');
    $prefix = 'JNL';
    $base = "$prefix-$year$month-";

    // 1. Get the latest number as a starting point
    $lastJournal = self::where('journal_number', 'like', "$base%")
        ->orderBy('id', 'desc')
        ->first();

    $counter = $lastJournal 
        ? (int) substr($lastJournal->journal_number, -4) + 1 
        : 1;

    // 2. Loop until a unique number is found
    do {
        $newNumber = str_pad($counter, 4, '0', STR_PAD_LEFT);
        $journalNumber = $base . $newNumber;
        
        $counter++;
    } while (self::where('journal_number', $journalNumber)->exists());

    return $journalNumber;
}


    // Add relationships
    public function mda()
    {
        return $this->belongsTo(Mda::class, 'mda_id');
    }

    public function economicCode()
    {
        return $this->belongsTo(EconomyCode::class, 'economic_code_id');
    }

    public function administrativeCode()
    {
        return $this->belongsTo(AdministrativeSectorCode::class, 'id', 'administrative_code_id');
    }

    public function administrativeSectorCode()
    {
        return $this->belongsTo(AdministrativeCode::class,  'administrative_sector_code_id');
    }
}
