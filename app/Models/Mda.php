<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Mda extends Model
{
    use HasFactory;

    // CRITICAL FIX: Explicitly define the table name to override Laravel's incorrect pluralization guess
    protected $table = 'mdas';

    protected $fillable = [
        'name',
        'oracle_name',
        'code',
        'type',
        'initials',
        'location',
        'status',
        'administrative_code_id', // Add this to fillable
    ];

    protected $casts = [
        'status' => 'integer',
    ];

    public function scopeActive($query)
    {
        return $query->where('status', 1);
    }

    /**
     * Relationship with AdministrativeCode
     * Assuming MDA belongs to an AdministrativeCode
     */
    public function administrativeCode(): BelongsTo
    {
        return $this->belongsTo(AdministrativeCode::class, 'administrative_code_id');
    }


    /**
     * Get the Sectors associated with the MDA (One MDA has Many Sectors).
     */
    // public function sectors(): HasMany
    // {
    //     // Assuming the foreign key on the 'sectors' table is 'mda_id'
    //     return $this->hasMany(Sector::class, 'mda_id');
    // }

    /**
     * Get the Sectors associated with the MDA
     * Through AdministrativeSectorCode relationship
     */
    public function sectors(): HasMany
    {
        // Get sectors through administrative sector codes
        return $this->hasMany(AdministrativeSectorCode::class, 'administrative_code_id', 'administrative_code_id');
    }

    public function users()
    {
        // 🔑 Laravel automatically assumes the same pivot table ('mda_user').
        return $this->belongsToMany(User::class)
            // Still need to specify the extra pivot columns
            ->withPivot('is_primary', 'effective_date')
            ->withTimestamps();
    }

    /**
     * Alternative: If you want direct access to the administrative code's sectors
     */
    public function administrativeSectors()
    {
        return $this->hasManyThrough(
            AdministrativeSectorCode::class,
            AdministrativeCode::class,
            'id', // Foreign key on AdministrativeCode table
            'administrative_code_id', // Foreign key on AdministrativeSectorCode table
            'administrative_code_id', // Local key on Mda table
            'id' // Local key on AdministrativeCode table
        );
    }

    /**
     * Get the AdministrativeSectorCodes through AdministrativeCode
     */
    public function administrativeSectorCodes()
    {
        return $this->hasMany(AdministrativeSectorCode::class, 'administrative_code_id', 'administrative_code_id');
    }

    /**
     * Get all sectors for this MDA (through administrative code)
     */
    public function getSectorsAttribute()
    {
        if ($this->administrativeCode) {
            return $this->administrativeCode->sectorCodes;
        }
        return collect();
    }
}
