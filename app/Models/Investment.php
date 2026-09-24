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
        'closing_amount',
        'status',
        'closed_at',
        'created_at',
        'updated_at',
    ];

    protected $casts = [
        'amount' => 'decimal:4',
        'closing_amount' => 'decimal:4',
        'closed_at' => 'datetime',
    ];

    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class, 'member_id', 'member_id');
    }

    public function roiTransactions(): HasMany
    {
        return $this->hasMany(RoiTransaction::class, 'investment_id', 'investment_id');
    }

    public function withdrawals(): HasMany
    {
        return $this->hasMany(InvestmentWithdrawal::class, 'investment_id', 'investment_id');
    }
}
