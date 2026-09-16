<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ActivationWalletTransaction extends Model
{
    use HasFactory;

    protected $fillable = [
        'member_id',
        'member_name',
        'amount',
        'type',
        'remarks',
        'reference',
        'created_at',
        'updated_at',
    ];

    protected $casts = [
        'amount' => 'decimal:4',
    ];

    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class, 'member_id', 'member_id');
    }
}
