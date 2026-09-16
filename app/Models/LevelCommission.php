<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LevelCommission extends Model
{
    use HasFactory;

    protected $fillable = [
        'level',
        'percentage',
        'is_active',
    ];

    protected $casts = [
        'level' => 'integer',
        'percentage' => 'decimal:4',
        'is_active' => 'boolean',
    ];

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
