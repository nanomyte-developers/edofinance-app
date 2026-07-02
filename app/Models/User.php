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
        'user_category_id',
        'can_be_signatory',
        'signatory_id',
        'signature',
        'passport',
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
            'can_be_signatory' => 'boolean',
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

    // public function mdas()
    // {
    //     // 🔑 Laravel's belongsToMany method automatically assumes:
    //     // 1. The pivot table is 'mda_user' (alphabetical combination of model names).
    //     // 2. The foreign keys are 'user_id' and 'mda_id'.
        
    //     return $this->belongsToMany(Mda::class)
    //                 // We add this to access the extra columns in the pivot table
    //                 ->withPivot('is_primary', 'effective_date') 
    //                 ->withTimestamps();
        
    // }

     // ✅ Keep this relationship - it's properly configured
    public function mdas()
    {
        return $this->belongsToMany(Mda::class, 'mda_user', 'user_id', 'mda_id')
                    ->withPivot('assigned_by_id', 'status', 'is_primary', 'effective_date')
                    ->withTimestamps();
    }

    // ✅ Remove the duplicate mda() method or keep it with different name
    // If you need both, keep them but make sure they're consistent
    public function mda()
    {
        return $this->belongsToMany(Mda::class, 'mda_user', 'user_id', 'mda_id')
                    ->withPivot('assigned_by_id', 'status', 'is_primary', 'effective_date')
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

    // User Category Model
    public function userCategory()
    {
        return $this->belongsTo(UserCategory::class, 'user_category_id', 'id');
    }

    // public function mda()
    // {
    //     // Pass 'mda_users' as the second parameter to explicitly override Laravel's naming default
    //     return $this->belongsToMany(Mda::class, 'mda_user', 'user_id', 'mda_id')
    //                 ->withPivot('assigned_by_id','status','is_primary', 'effective_date')
    //                 ->withTimestamps();
    // }

    public function signatory()
    {
        return $this->belongsTo(User::class, 'signatory_id');
    }

    // Accessors for full URLs
    public function getSignatureUrlAttribute()
    {
        if ($this->signature) {
            return asset('storage/' . $this->signature);
        }
        return null;
    }

    public function getPassportUrlAttribute()
    {
        if ($this->passport) {
            return asset('storage/' . $this->passport);
        }
        return null;
    }

    // Check if user can be a signatory
    public function canBeSignatory()
    {
        return $this->category && $this->category->can_be_signatory;
    }

    // Check if user requires a signatory
    public function requiresSignatory()
    {
        return $this->category && $this->category->requires_signature;
    }

}
