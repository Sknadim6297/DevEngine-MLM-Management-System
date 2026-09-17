<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InvestmentWithdrawal extends Model
{
    use HasFactory;

    protected $fillable = [
        'withdrawal_id',
        'member_id',
        'member_name',
        'investment_id',
        'investment_amount',
        'withdrawal_amount',
        'status',
        'withdrawn_at',
    ];

    protected $casts = [
        'investment_amount' => 'decimal:4',
        'withdrawal_amount' => 'decimal:4',
        'withdrawn_at' => 'datetime',
    ];

    public function investment(): BelongsTo
    {
        return $this->belongsTo(Investment::class, 'investment_id', 'investment_id');
    }

    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class, 'member_id', 'member_id');
    }
}
