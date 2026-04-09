<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Bank extends Model
{
     protected $table = 'banks';

    protected $fillable = [
        'name',
        'code',
        'initials',
        'status',
    ];

    protected $casts = [
        'status' => 'integer',
    ];

    /**
     * Get the Sectors associated with the Bank (One Bank has Many Account).
     */

}
