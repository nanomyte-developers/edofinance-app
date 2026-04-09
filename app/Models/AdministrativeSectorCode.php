<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AdministrativeSectorCode extends Model
{
    protected $table = 'administrative_sector_codes';

    protected $fillable = [
        'code',
        'name',
        'administrative_code_id',
        'initials',
        'status',
    
    ];

    /**
     * Scope for active sectors
     */
    public function scopeActive($query)
    {
        return $query->where('status', 1);
    }

    /**
     * Relationship with administrative code
     */
    public function administrativeCode(): BelongsTo
    {
        return $this->belongsTo(AdministrativeCode::class, 'administrative_code_id');
    }

    /**
     * Relationship with schedules
     */
    public function schedules(): HasMany
    {
        return $this->hasMany(Schedule::class, 'budget_code_id');
    }
}