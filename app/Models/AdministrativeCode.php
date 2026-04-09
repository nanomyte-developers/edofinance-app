<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AdministrativeCode extends Model
{
    protected $table = 'administrative_codes';

    protected $fillable = [
        'name',
        'code',
        'status',
    ];

    /**
     * Scope for active administrative codes
     */
    public function scopeActive($query)
    {
        return $query->where('status', 1);
    }

    /**
     * Relationship with schedules
     */
    public function schedules(): HasMany
    {
        return $this->hasMany(Schedule::class, 'mda_id');
    }

    /**
     * Relationship with administrative sector codes
     */
    public function sectorCodes(): HasMany
    {
        return $this->hasMany(AdministrativeSectorCode::class, 'administrative_code_id');
    }

     /**
     * Relationship with MDA (One-to-One)
     */
    public function mda(): HasOne
    {
        return $this->hasOne(Mda::class, 'administrative_code_id');
    }

    /**
     * Relationship with MDA (Alternative: One-to-Many if needed)
     */
    public function mdas(): HasMany
    {
        return $this->hasMany(Mda::class, 'administrative_code_id');
    }


    
}