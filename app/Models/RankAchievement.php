<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RankAchievement extends Model
{
    use HasFactory;

    protected $fillable = [
        'member_id',
        'member_name',
        'rank_id',
        'qualifying_business_amount',
        'achieved_at',
    ];

    protected $casts = [
        'qualifying_business_amount' => 'decimal:4',
        'achieved_at' => 'datetime',
    ];

    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class, 'member_id', 'member_id');
    }

    public function rank(): BelongsTo
    {
        return $this->belongsTo(Rank::class);
    }
}
