<?php

namespace RiaanZA\LaravelSubscription\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlanFeature extends Model
{
    use HasFactory;

    protected $fillable = [
        'plan_id',
        'feature_key',
        'feature_name',
        'feature_type',
        'feature_limit',
        'is_unlimited',
        'description',
        'sort_order',
    ];

    protected $casts = [
        'plan_id' => 'integer',
        'is_unlimited' => 'boolean',
        'sort_order' => 'integer',
    ];

    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);
        $this->setTable(config('laravel-subscription.table_names.plan_features', 'plan_features'));
    }

    /**
     * Get the plan that owns this feature.
     */
    public function plan(): BelongsTo
    {
        return $this->belongsTo(SubscriptionPlan::class, 'plan_id');
    }

    /**
     * Get the typed value of the feature limit.
     */
    public function getTypedLimitAttribute(): mixed
    {
        if ($this->is_unlimited) {
            return 'unlimited';
        }

        return match ($this->feature_type) {
            'numeric' => (int) $this->feature_limit,
            'boolean' => (bool) $this->feature_limit,
            default => $this->feature_limit,
        };
    }

    /**
     * Get human readable limit.
     */
    public function getHumanLimitAttribute(): string
    {
        if ($this->is_unlimited) {
            return 'Unlimited';
        }

        return match ($this->feature_type) {
            'boolean' => $this->feature_limit ? 'Yes' : 'No',
            'numeric' => number_format($this->feature_limit),
            default => $this->feature_limit,
        };
    }
}
