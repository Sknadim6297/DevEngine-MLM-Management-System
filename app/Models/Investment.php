<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Investment extends Model
{
    use HasFactory;

    protected $fillable = [
        'investment_id',
        'member_id',
        'member_name',
        'amount',
        'status',
    ];

    protected $casts = [
        'amount' => 'decimal:4',
    ];

    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class, 'member_id', 'member_id');
    }

    public function roiTransactions(): HasMany
    {
        return $this->hasMany(RoiTransaction::class, 'investment_id', 'investment_id');
    }
}
