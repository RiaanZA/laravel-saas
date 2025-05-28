<?php

namespace RiaanZA\LaravelSubscription\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SubscriptionUsage extends Model
{
    use HasFactory;

    protected $fillable = [
        'subscription_id',
        'feature_key',
        'usage_count',
        'period_start',
        'period_end',
        'metadata',
    ];

    protected $casts = [
        'subscription_id' => 'integer',
        'usage_count' => 'integer',
        'period_start' => 'datetime',
        'period_end' => 'datetime',
        'metadata' => 'array',
    ];

    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);
        $this->setTable(config('laravel-subscription.table_names.subscription_usage', 'subscription_usage'));
    }

    /**
     * Get the subscription that owns this usage record.
     */
    public function subscription(): BelongsTo
    {
        return $this->belongsTo(UserSubscription::class, 'subscription_id');
    }

    /**
     * Get human readable period.
     */
    public function getPeriodHumanAttribute(): string
    {
        return $this->period_start->format('M Y');
    }

    /**
     * Increment usage count.
     */
    public function incrementUsageCount(int $amount = 1): bool
    {
        return $this->update(['usage_count' => $this->usage_count + $amount]);
    }

    /**
     * Decrement usage count.
     */
    public function decrementUsageCount(int $amount = 1): bool
    {
        $newCount = max(0, $this->usage_count - $amount);
        return $this->update(['usage_count' => $newCount]);
    }

    /**
     * Reset usage count.
     */
    public function reset(): bool
    {
        return $this->update(['usage_count' => 0]);
    }
}
