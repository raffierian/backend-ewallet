<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Budget extends Model
{
    use HasFactory;

    protected $fillable = [
        'family_id',
        'category_id',
        'amount',
        'period_start',
        'period_end',
        'alert_threshold',
        'is_active',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'period_start' => 'date',
        'period_end' => 'date',
        'is_active' => 'boolean',
    ];

    public function family(): BelongsTo
    {
        return $this->belongsTo(Family::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function getSpentAttribute()
    {
        return Transaction::where('family_id', $this->family_id)
            ->where('category_id', $this->category_id)
            ->where('type', 'expense')
            ->whereBetween('date', [$this->period_start, $this->period_end])
            ->sum('amount');
    }

    public function getProgressAttribute()
    {
        if ($this->amount == 0) return 0;
        return ($this->spent / $this->amount) * 100;
    }
}
