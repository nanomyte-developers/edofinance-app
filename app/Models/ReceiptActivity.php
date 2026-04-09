<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ReceiptActivity extends Model
{
    protected $table = 'receipt_activities';
    protected $fillable = [
        'name',
        'status',
    ];
}
