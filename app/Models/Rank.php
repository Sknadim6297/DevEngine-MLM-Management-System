<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Rank extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'required_full_team_business',
        'unlocked_levels',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'required_full_team_business' => 'decimal:4',
        'unlocked_levels' => 'integer',
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    public function members(): HasMany
    {
        return $this->hasMany(Member::class, 'rank_id');
    }
}
