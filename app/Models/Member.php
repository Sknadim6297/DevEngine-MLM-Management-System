<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Member extends Model
{
    use HasFactory;

    protected $fillable = [
        'member_id',
        'sponsor_id',
        'sponsor_name',
        'member_name',
        'wallet_address',
        'mobile_no',
        'pan_card_no',
        'email',
        'password',
        'status',
    ];

    public function sponsor(): BelongsTo
    {
        return $this->belongsTo(Member::class, 'sponsor_id', 'member_id');
    }

    public function sponsoredMembers(): HasMany
    {
        return $this->hasMany(Member::class, 'sponsor_id', 'member_id');
    }

    public function investments(): HasMany
    {
        return $this->hasMany(Investment::class, 'member_id', 'member_id');
    }
}
