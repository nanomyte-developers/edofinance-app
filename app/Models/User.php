<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Spatie\Permission\Traits\HasRoles;
use Illuminate\Notifications\Notifiable;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable, HasRoles, TwoFactorAuthenticatable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'two_factor_secret',
        'two_factor_recovery_codes',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'two_factor_confirmed_at' => 'datetime',
        ];
    }

    /**
     * Relationship with voucher approvals
     */
    public function voucherApprovals()
    {
        return $this->hasMany(VoucherApproval::class, 'user_id');
    }

    /**
     * Relationship with next approvals
     */
    public function nextVoucherApprovals()
    {
        return $this->hasMany(VoucherApproval::class, 'next_approval_user_id');
    }

    // public function mdas(): BelongsToMany
    // {
    //     // Adjust pivot table name if necessary
    //     return $this->belongsToMany(Mda::class);
    // }

    public function mdas()
    {
        // 🔑 Laravel's belongsToMany method automatically assumes:
        // 1. The pivot table is 'mda_user' (alphabetical combination of model names).
        // 2. The foreign keys are 'user_id' and 'mda_id'.
        
        return $this->belongsToMany(Mda::class)
                    // We add this to access the extra columns in the pivot table
                    ->withPivot('is_primary', 'effective_date') 
                    ->withTimestamps();
    }

    // Optional: Add accessor for role
    public function getRoleAttribute()
    {
        return $this->roles->first()?->name;
    }

    // Optional: Add accessor for roles list
    public function getRolesListAttribute()
    {
        return $this->roles->pluck('name')->toArray();
    }
}
