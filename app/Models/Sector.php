<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Sector extends Model
{
    //

    protected $table = 'sectors';

    protected $fillable = [
        'mda_id',
        'name',
        'code',
        'initials',
        'location',
        'status',
    ];

    protected $casts = [
        'status' => 'integer',
    ];

    public function mda()
    {
        
        return $this->belongsTo(Mda::class);
    }
}
