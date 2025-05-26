<?php

namespace RiaanZA\LaravelSubscription\Services;

use Illuminate\Database\Eloquent\Model;
use RiaanZA\LaravelSubscription\Models\UserSubscription;
use RiaanZA\LaravelSubscription\Models\SubscriptionUsage;
use RiaanZA\LaravelSubscription\Models\PlanFeature;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;
use Exception;

class UsageService
{
    /**
     * Increment usage for a specific feature.
     */
    public function incrementUsage(Model $user, string $featureKey, int $increment = 1): bool
    {
        $subscription = $this->getActiveSubscription($user);
        
        if (!$subscription) {
            throw new Exception('No active subscription found for user');
        }

        return $this->incrementSubscriptionUsage($subscription, $featureKey, $increment);
    }

    /**
     * Increment usage for a subscription.
     */
    public function incrementSubscriptionUsage(UserSubscription $subscription, string $featureKey, int $increment = 1): bool
    {
        $feature = $this->getFeature($subscription, $featureKey);
        
        if (!$feature) {
            throw new Exception("Feature '{$featureKey}' not found in subscription plan");
        }

        if ($feature->feature_type !== 'numeric') {
            throw new Exception("Cannot increment usage for non-numeric feature '{$featureKey}'");
        }

        // Skip increment for unlimited features
        if ($feature->is_unlimited) {
            return true;
        }

        try {
            DB::beginTransaction();

            $usage = $this->getOrCreateUsageRecord($subscription, $featureKey);
            
            // Check if increment would exceed limit
            if (($usage->usage_count + $increment) > $feature->typed_limit) {
                DB::rollBack();
                throw new Exception("Usage increment would exceed limit for feature '{$featureKey}'");
            }

            $usage->increment('usage_count', $increment);

            DB::commit();

            Log::info('Usage incremented', [
                'subscription_id' => $subscription->id,
                'feature_key' => $featureKey,
                'increment' => $increment,
                'new_usage' => $usage->usage_count,
                'limit' => $feature->typed_limit,
            ]);

            return true;

        } catch (Exception $e) {
            DB::rollBack();
            Log::error('Failed to increment usage', [
                'subscription_id' => $subscription->id,
                'feature_key' => $featureKey,
                'increment' => $increment,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Decrement usage for a specific feature.
     */
    public function decrementUsage(Model $user, string $featureKey, int $decrement = 1): bool
    {
        $subscription = $this->getActiveSubscription($user);
        
        if (!$subscription) {
            throw new Exception('No active subscription found for user');
        }

        return $this->decrementSubscriptionUsage($subscription, $featureKey, $decrement);
    }

    /**
     * Decrement usage for a subscription.
     */
    public function decrementSubscriptionUsage(UserSubscription $subscription, string $featureKey, int $decrement = 1): bool
    {
        $feature = $this->getFeature($subscription, $featureKey);
        
        if (!$feature) {
            throw new Exception("Feature '{$featureKey}' not found in subscription plan");
        }

        if ($feature->feature_type !== 'numeric') {
            throw new Exception("Cannot decrement usage for non-numeric feature '{$featureKey}'");
        }

        try {
            DB::beginTransaction();

            $usage = $this->getOrCreateUsageRecord($subscription, $featureKey);
            
            // Ensure we don't go below zero
            $newUsage = max(0, $usage->usage_count - $decrement);
            $actualDecrement = $usage->usage_count - $newUsage;
            
            $usage->update(['usage_count' => $newUsage]);

            DB::commit();

            Log::info('Usage decremented', [
                'subscription_id' => $subscription->id,
                'feature_key' => $featureKey,
                'decrement' => $actualDecrement,
                'new_usage' => $newUsage,
            ]);

            return true;

        } catch (Exception $e) {
            DB::rollBack();
            Log::error('Failed to decrement usage', [
                'subscription_id' => $subscription->id,
                'feature_key' => $featureKey,
                'decrement' => $decrement,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Set usage for a specific feature.
     */
    public function setUsage(Model $user, string $featureKey, int $usage): bool
    {
        $subscription = $this->getActiveSubscription($user);
        
        if (!$subscription) {
            throw new Exception('No active subscription found for user');
        }

        return $this->setSubscriptionUsage($subscription, $featureKey, $usage);
    }

    /**
     * Set usage for a subscription.
     */
    public function setSubscriptionUsage(UserSubscription $subscription, string $featureKey, int $usage): bool
    {
        $feature = $this->getFeature($subscription, $featureKey);
        
        if (!$feature) {
            throw new Exception("Feature '{$featureKey}' not found in subscription plan");
        }

        if ($feature->feature_type !== 'numeric') {
            throw new Exception("Cannot set usage for non-numeric feature '{$featureKey}'");
        }

        // Check if usage exceeds limit (unless unlimited)
        if (!$feature->is_unlimited && $usage > $feature->typed_limit) {
            throw new Exception("Usage value exceeds limit for feature '{$featureKey}'");
        }

        try {
            DB::beginTransaction();

            $usageRecord = $this->getOrCreateUsageRecord($subscription, $featureKey);
            $oldUsage = $usageRecord->usage_count;
            
            $usageRecord->update(['usage_count' => $usage]);

            DB::commit();

            Log::info('Usage set', [
                'subscription_id' => $subscription->id,
                'feature_key' => $featureKey,
                'old_usage' => $oldUsage,
                'new_usage' => $usage,
            ]);

            return true;

        } catch (Exception $e) {
            DB::rollBack();
            Log::error('Failed to set usage', [
                'subscription_id' => $subscription->id,
                'feature_key' => $featureKey,
                'usage' => $usage,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Get current usage for a feature.
     */
    public function getCurrentUsage(UserSubscription $subscription, string $featureKey): int
    {
        $usage = $subscription->usage()
            ->where('feature_key', $featureKey)
            ->where('period_start', '<=', now())
            ->where('period_end', '>=', now())
            ->first();

        return $usage ? $usage->usage_count : 0;
    }

    /**
     * Get usage for a specific period.
     */
    public function getUsageForPeriod(UserSubscription $subscription, string $featureKey, Carbon $start, Carbon $end): int
    {
        return $subscription->usage()
            ->where('feature_key', $featureKey)
            ->where('period_start', '>=', $start)
            ->where('period_end', '<=', $end)
            ->sum('usage_count');
    }

    /**
     * Get usage history for a feature.
     */
    public function getUsageHistory(UserSubscription $subscription, string $featureKey, int $periods = 12): array
    {
        $usage = $subscription->usage()
            ->where('feature_key', $featureKey)
            ->orderBy('period_start', 'desc')
            ->limit($periods)
            ->get();

        return $usage->map(function ($record) {
            return [
                'period_start' => $record->period_start->toDateString(),
                'period_end' => $record->period_end->toDateString(),
                'usage_count' => $record->usage_count,
            ];
        })->toArray();
    }

    /**
     * Get all usage for a subscription in current period.
     */
    public function getCurrentPeriodUsage(UserSubscription $subscription): array
    {
        $usage = $subscription->usage()
            ->where('period_start', '<=', now())
            ->where('period_end', '>=', now())
            ->get();

        return $usage->map(function ($record) {
            return [
                'feature_key' => $record->feature_key,
                'usage_count' => $record->usage_count,
                'period_start' => $record->period_start->toDateString(),
                'period_end' => $record->period_end->toDateString(),
            ];
        })->toArray();
    }

    /**
     * Reset usage for a feature.
     */
    public function resetUsage(UserSubscription $subscription, string $featureKey): bool
    {
        try {
            $usage = $this->getOrCreateUsageRecord($subscription, $featureKey);
            $oldUsage = $usage->usage_count;
            
            $usage->update(['usage_count' => 0]);

            Log::info('Usage reset', [
                'subscription_id' => $subscription->id,
                'feature_key' => $featureKey,
                'old_usage' => $oldUsage,
            ]);

            return true;

        } catch (Exception $e) {
            Log::error('Failed to reset usage', [
                'subscription_id' => $subscription->id,
                'feature_key' => $featureKey,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Reset all usage for a subscription.
     */
    public function resetAllUsage(UserSubscription $subscription): bool
    {
        try {
            DB::beginTransaction();

            $affectedRows = $subscription->usage()
                ->where('period_start', '<=', now())
                ->where('period_end', '>=', now())
                ->update(['usage_count' => 0]);

            DB::commit();

            Log::info('All usage reset', [
                'subscription_id' => $subscription->id,
                'affected_rows' => $affectedRows,
            ]);

            return true;

        } catch (Exception $e) {
            DB::rollBack();
            Log::error('Failed to reset all usage', [
                'subscription_id' => $subscription->id,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Initialize usage tracking for a new billing period.
     */
    public function initializeNewPeriod(UserSubscription $subscription): void
    {
        $features = $subscription->plan->features()
            ->where('feature_type', 'numeric')
            ->get();

        foreach ($features as $feature) {
            $this->createUsageRecord($subscription, $feature->feature_key);
        }

        Log::info('Usage tracking initialized for new period', [
            'subscription_id' => $subscription->id,
            'period_start' => $subscription->current_period_start->toDateString(),
            'period_end' => $subscription->current_period_end->toDateString(),
            'features_count' => $features->count(),
        ]);
    }

    /**
     * Check if usage is over limit for any feature.
     */
    public function hasOverLimitUsage(UserSubscription $subscription): bool
    {
        $features = $subscription->plan->features()
            ->where('feature_type', 'numeric')
            ->where('is_unlimited', false)
            ->get();

        foreach ($features as $feature) {
            $currentUsage = $this->getCurrentUsage($subscription, $feature->feature_key);
            if ($currentUsage > $feature->typed_limit) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get features that are over their limits.
     */
    public function getOverLimitFeatures(UserSubscription $subscription): array
    {
        $features = $subscription->plan->features()
            ->where('feature_type', 'numeric')
            ->where('is_unlimited', false)
            ->get();

        $overLimitFeatures = [];

        foreach ($features as $feature) {
            $currentUsage = $this->getCurrentUsage($subscription, $feature->feature_key);
            if ($currentUsage > $feature->typed_limit) {
                $overLimitFeatures[] = [
                    'feature_key' => $feature->feature_key,
                    'feature_name' => $feature->feature_name,
                    'current_usage' => $currentUsage,
                    'limit' => $feature->typed_limit,
                    'overage' => $currentUsage - $feature->typed_limit,
                ];
            }
        }

        return $overLimitFeatures;
    }

    /**
     * Calculate usage percentage for a feature.
     */
    public function getUsagePercentage(UserSubscription $subscription, string $featureKey): float
    {
        $feature = $this->getFeature($subscription, $featureKey);
        
        if (!$feature || $feature->is_unlimited || $feature->typed_limit <= 0) {
            return 0.0;
        }

        $currentUsage = $this->getCurrentUsage($subscription, $featureKey);
        return ($currentUsage / $feature->typed_limit) * 100;
    }

    /**
     * Get remaining usage for a feature.
     */
    public function getRemainingUsage(UserSubscription $subscription, string $featureKey): int|string
    {
        $feature = $this->getFeature($subscription, $featureKey);
        
        if (!$feature) {
            return 0;
        }

        if ($feature->is_unlimited) {
            return 'unlimited';
        }

        $currentUsage = $this->getCurrentUsage($subscription, $featureKey);
        return max(0, $feature->typed_limit - $currentUsage);
    }

    /**
     * Get or create usage record for current period.
     */
    protected function getOrCreateUsageRecord(UserSubscription $subscription, string $featureKey): SubscriptionUsage
    {
        $usage = $subscription->usage()
            ->where('feature_key', $featureKey)
            ->where('period_start', '<=', now())
            ->where('period_end', '>=', now())
            ->first();

        if (!$usage) {
            $usage = $this->createUsageRecord($subscription, $featureKey);
        }

        return $usage;
    }

    /**
     * Create a new usage record.
     */
    protected function createUsageRecord(UserSubscription $subscription, string $featureKey): SubscriptionUsage
    {
        return $subscription->usage()->create([
            'feature_key' => $featureKey,
            'usage_count' => 0,
            'period_start' => $subscription->current_period_start,
            'period_end' => $subscription->current_period_end,
        ]);
    }

    /**
     * Get user's active subscription.
     */
    protected function getActiveSubscription(Model $user): ?UserSubscription
    {
        return $user->subscriptions()
            ->with(['plan.features'])
            ->whereIn('status', ['active', 'trial'])
            ->latest()
            ->first();
    }

    /**
     * Get a specific feature for a subscription.
     */
    protected function getFeature(UserSubscription $subscription, string $featureKey): ?PlanFeature
    {
        return $subscription->plan->features()
            ->where('feature_key', $featureKey)
            ->first();
    }
}
