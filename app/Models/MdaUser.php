<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;

class MdaUser extends Pivot
{
    protected $table = 'mda_user';

    protected $fillable = [
        'user_id',
        'mda_id',
        'assigned_by_id',
        'status',
        'is_primary',
        'effective_date',
    ];

    protected $casts = [
        'is_primary' => 'boolean',
        'effective_date' => 'date',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function mda()
    {
        return $this->belongsTo(Mda::class);
    }

    public function assignedBy()
    {
        return $this->belongsTo(User::class, 'assigned_by_id');
    }
}