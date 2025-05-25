<?php

namespace RiaanZA\LaravelSubscription\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Builder;

class SubscriptionPlan extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'description',
        'price',
        'billing_cycle',
        'trial_days',
        'is_active',
        'is_popular',
        'sort_order',
        'metadata',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'trial_days' => 'integer',
        'is_active' => 'boolean',
        'is_popular' => 'boolean',
        'sort_order' => 'integer',
        'metadata' => 'array',
    ];

    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);
        $this->setTable(config('laravel-subscription.table_names.subscription_plans', 'subscription_plans'));
    }

    /**
     * Get the features for this plan.
     */
    public function features(): HasMany
    {
        return $this->hasMany(PlanFeature::class, 'plan_id')->orderBy('sort_order');
    }

    /**
     * Get the subscriptions for this plan.
     */
    public function subscriptions(): HasMany
    {
        return $this->hasMany(UserSubscription::class, 'plan_id');
    }

    /**
     * Scope to get only active plans.
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope to get plans ordered by sort order.
     */
    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order')->orderBy('name');
    }

    /**
     * Check if the plan has a specific feature.
     */
    public function hasFeature(string $featureKey): bool
    {
        return $this->features()->where('feature_key', $featureKey)->exists();
    }

    /**
     * Get formatted price with currency symbol.
     */
    public function getFormattedPriceAttribute(): string
    {
        $symbol = config('laravel-subscription.ui.currency_symbol', 'R');
        return $symbol . number_format($this->price, 2);
    }

    /**
     * Get billing cycle in human readable format.
     */
    public function getBillingCycleHumanAttribute(): string
    {
        return match ($this->billing_cycle) {
            'monthly' => 'Monthly',
            'yearly' => 'Yearly',
            'quarterly' => 'Quarterly',
            'weekly' => 'Weekly',
            default => ucfirst($this->billing_cycle),
        };
    }

    /**
     * Check if plan has trial period.
     */
    public function hasTrialPeriod(): bool
    {
        return $this->trial_days > 0;
    }
}
