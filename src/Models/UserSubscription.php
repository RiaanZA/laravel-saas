<?php

namespace RiaanZA\LaravelSubscription\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Builder;
use Carbon\Carbon;

class UserSubscription extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'plan_id',
        'peach_subscription_id',
        'status',
        'trial_ends_at',
        'current_period_start',
        'current_period_end',
        'cancelled_at',
        'ends_at',
        'amount',
        'currency',
        'metadata',
    ];

    protected $casts = [
        'user_id' => 'integer',
        'plan_id' => 'integer',
        'trial_ends_at' => 'datetime',
        'current_period_start' => 'datetime',
        'current_period_end' => 'datetime',
        'cancelled_at' => 'datetime',
        'ends_at' => 'datetime',
        'amount' => 'decimal:2',
        'metadata' => 'array',
    ];

    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);
        $this->setTable(config('laravel-subscription.table_names.user_subscriptions', 'user_subscriptions'));
    }

    /**
     * Get the user that owns this subscription.
     */
    public function user(): BelongsTo
    {
        $userModel = config('laravel-subscription.models.user', 'App\Models\User');
        return $this->belongsTo($userModel);
    }

    /**
     * Get the plan for this subscription.
     */
    public function plan(): BelongsTo
    {
        return $this->belongsTo(SubscriptionPlan::class, 'plan_id');
    }

    /**
     * Get the usage records for this subscription.
     */
    public function usage(): HasMany
    {
        return $this->hasMany(SubscriptionUsage::class, 'subscription_id');
    }

    /**
     * Scope to get active subscriptions.
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 'active');
    }

    /**
     * Scope to get trial subscriptions.
     */
    public function scopeOnTrial(Builder $query): Builder
    {
        return $query->where('status', 'trial');
    }

    /**
     * Scope to get cancelled subscriptions.
     */
    public function scopeCancelled(Builder $query): Builder
    {
        return $query->where('status', 'cancelled');
    }

    /**
     * Check if subscription is active.
     */
    public function isActive(): bool
    {
        return $this->status === 'active' && !$this->isExpired();
    }

    /**
     * Check if subscription is on trial.
     */
    public function onTrial(): bool
    {
        return $this->status === 'trial' && 
               $this->trial_ends_at && 
               $this->trial_ends_at->isFuture();
    }

    /**
     * Check if subscription is cancelled.
     */
    public function isCancelled(): bool
    {
        return $this->status === 'cancelled';
    }

    /**
     * Check if subscription is past due.
     */
    public function isPastDue(): bool
    {
        return $this->status === 'past_due';
    }

    /**
     * Check if subscription is expired.
     */
    public function isExpired(): bool
    {
        return $this->ends_at && $this->ends_at->isPast();
    }

    /**
     * Check if subscription has a specific feature.
     */
    public function hasFeature(string $featureKey): bool
    {
        return $this->plan->hasFeature($featureKey);
    }

    /**
     * Get the limit for a specific feature.
     */
    public function getFeatureLimit(string $featureKey): mixed
    {
        $feature = $this->plan->features()->where('feature_key', $featureKey)->first();
        return $feature ? $feature->typed_limit : null;
    }

    /**
     * Get current usage for a feature.
     */
    public function getCurrentUsage(string $featureKey): int
    {
        $usage = $this->usage()
            ->where('feature_key', $featureKey)
            ->where('period_start', '<=', now())
            ->where('period_end', '>=', now())
            ->first();

        return $usage ? $usage->usage_count : 0;
    }

    /**
     * Get formatted amount with currency symbol.
     */
    public function getFormattedAmountAttribute(): string
    {
        $symbol = config('laravel-subscription.ui.currency_symbol', 'R');
        return $symbol . number_format($this->amount, 2);
    }

    /**
     * Get next billing date.
     */
    public function getNextBillingDateAttribute(): Carbon
    {
        return $this->current_period_end;
    }

    /**
     * Get days remaining in current period.
     */
    public function getDaysRemainingAttribute(): int
    {
        return max(0, now()->diffInDays($this->current_period_end, false));
    }

    /**
     * Get trial days remaining.
     */
    public function getTrialDaysRemainingAttribute(): int
    {
        if (!$this->trial_ends_at) {
            return 0;
        }

        return max(0, now()->diffInDays($this->trial_ends_at, false));
    }

    /**
     * Check if subscription is ending soon.
     */
    public function isEndingSoon(int $days = 7): bool
    {
        if (!$this->ends_at) {
            return false;
        }

        return $this->ends_at->diffInDays(now()) <= $days;
    }

    /**
     * Check if trial is ending soon.
     */
    public function isTrialEndingSoon(int $days = 3): bool
    {
        if (!$this->trial_ends_at) {
            return false;
        }

        return $this->trial_ends_at->diffInDays(now()) <= $days;
    }
}
