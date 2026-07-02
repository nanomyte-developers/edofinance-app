<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class UserCategory extends Model
{

    protected $table = 'user_categories';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $protected = ['id']; // Alternatively use fillable layout below:
    protected $fillable = [
        'name', 
        'slug', 
        'description',
        'status',
        'can_be_signatory',
        'requires_signature'
        ];

    // Get all users assigned to this category
    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }
}
