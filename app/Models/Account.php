<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Account extends Model
{
    use HasFactory;

    protected $fillable = [
        'family_id',
        'name',
        'type',
        'balance',
        'currency',
        'icon',
        'color',
        'is_active',
    ];

    protected $casts = [
        'balance' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    public function family(): BelongsTo
    {
        return $this->belongsTo(Family::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    public function transfersFrom(): HasMany
    {
        return $this->hasMany(Transaction::class, 'account_id')
            ->where('type', 'transfer');
    }

    public function transfersTo(): HasMany
    {
        return $this->hasMany(Transaction::class, 'to_account_id')
            ->where('type', 'transfer');
    }
}
