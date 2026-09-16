<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LevelCommissionTransaction extends Model
{
    use HasFactory;

    protected $fillable = [
        'reference',
        'investment_id',
        'member_id',
        'member_name',
        'from_member_id',
        'from_member_name',
        'level',
        'on_amount',
        'rate_percentage',
        'income_amount',
    ];

    protected $casts = [
        'level' => 'integer',
        'on_amount' => 'decimal:4',
        'rate_percentage' => 'decimal:3',
        'income_amount' => 'decimal:4',
    ];

    public function beneficiary(): BelongsTo
    {
        return $this->belongsTo(Member::class, 'member_id', 'member_id');
    }

    public function fromMember(): BelongsTo
    {
        return $this->belongsTo(Member::class, 'from_member_id', 'member_id');
    }

    public function investment(): BelongsTo
    {
        return $this->belongsTo(Investment::class, 'investment_id', 'investment_id');
    }
}
