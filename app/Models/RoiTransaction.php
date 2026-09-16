<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RoiTransaction extends Model
{
    use HasFactory;

    protected $fillable = [
        'reference',
        'investment_id',
        'member_id',
        'member_name',
        'on_amount',
        'rate_percentage',
        'income_amount',
        'roi_date',
        'status',
        'withdrawable_on',
    ];

    protected $casts = [
        'on_amount' => 'decimal:4',
        'rate_percentage' => 'decimal:3',
        'income_amount' => 'decimal:4',
        'roi_date' => 'date',
        'withdrawable_on' => 'date',
    ];

    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class, 'member_id', 'member_id');
    }

    public function investment(): BelongsTo
    {
        return $this->belongsTo(Investment::class, 'investment_id', 'investment_id');
    }
}
