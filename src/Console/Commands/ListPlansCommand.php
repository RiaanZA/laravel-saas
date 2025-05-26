<?php

namespace RiaanZA\LaravelSubscription\Console\Commands;

use Illuminate\Console\Command;
use RiaanZA\LaravelSubscription\Models\SubscriptionPlan;
use RiaanZA\LaravelSubscription\Models\UserSubscription;

class ListPlansCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'subscription:list-plans 
                            {--active : Show only active plans}
                            {--with-features : Include plan features}
                            {--with-stats : Include subscription statistics}
                            {--format=table : Output format (table, json)}';

    /**
     * The console command description.
     */
    protected $description = 'List all subscription plans with their details';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $query = SubscriptionPlan::query();

        // Apply filters
        if ($this->option('active')) {
            $query->active();
        }

        // Load relationships
        $with = [];
        if ($this->option('with-features')) {
            $with[] = 'features';
        }
        if ($this->option('with-stats')) {
            $with[] = 'subscriptions';
        }

        if (!empty($with)) {
            $query->with($with);
        }

        $plans = $query->ordered()->get();

        if ($plans->isEmpty()) {
            $this->warn('No subscription plans found.');
            $this->line('Run <info>php artisan subscription:seed-plans</info> to create sample plans.');
            return Command::SUCCESS;
        }

        $format = $this->option('format');

        if ($format === 'json') {
            $this->outputJson($plans);
        } else {
            $this->outputTable($plans);
        }

        return Command::SUCCESS;
    }

    /**
     * Output plans as a table.
     */
    protected function outputTable($plans): void
    {
        $this->info('📋 Subscription Plans');
        $this->newLine();

        // Basic plan information
        $headers = ['ID', 'Name', 'Slug', 'Price', 'Billing', 'Trial Days', 'Status', 'Popular'];
        $rows = [];

        foreach ($plans as $plan) {
            $rows[] = [
                $plan->id,
                $plan->name,
                $plan->slug,
                $plan->formatted_price,
                ucfirst($plan->billing_cycle),
                $plan->trial_days,
                $plan->is_active ? '✅ Active' : '❌ Inactive',
                $plan->is_popular ? '⭐ Yes' : 'No',
            ];
        }

        $this->table($headers, $rows);

        // Show features if requested
        if ($this->option('with-features')) {
            $this->newLine();
            $this->showPlanFeatures($plans);
        }

        // Show statistics if requested
        if ($this->option('with-stats')) {
            $this->newLine();
            $this->showPlanStatistics($plans);
        }

        $this->newLine();
        $this->line("Total plans: <info>{$plans->count()}</info>");
    }

    /**
     * Show plan features.
     */
    protected function showPlanFeatures($plans): void
    {
        $this->info('🎯 Plan Features');
        $this->newLine();

        foreach ($plans as $plan) {
            $this->line("<comment>{$plan->name}</comment> ({$plan->slug}):");
            
            if ($plan->features->isEmpty()) {
                $this->line('   No features defined');
            } else {
                foreach ($plan->features->sortBy('sort_order') as $feature) {
                    $limit = $feature->is_unlimited ? 'Unlimited' : $feature->human_limit;
                    $icon = match ($feature->feature_type) {
                        'boolean' => $feature->typed_limit ? '✅' : '❌',
                        'numeric' => '🔢',
                        'text' => '📝',
                        default => '•',
                    };
                    
                    $this->line("   {$icon} {$feature->feature_name}: <info>{$limit}</info>");
                    
                    if ($feature->description) {
                        $this->line("      <fg=gray>{$feature->description}</>");
                    }
                }
            }
            $this->newLine();
        }
    }

    /**
     * Show plan statistics.
     */
    protected function showPlanStatistics($plans): void
    {
        $this->info('📊 Plan Statistics');
        $this->newLine();

        $headers = ['Plan', 'Active Subs', 'Trial Subs', 'Cancelled', 'Total', 'Revenue (Monthly)'];
        $rows = [];
        $totalRevenue = 0;

        foreach ($plans as $plan) {
            $activeCount = $plan->subscriptions()->where('status', 'active')->count();
            $trialCount = $plan->subscriptions()->where('status', 'trial')->count();
            $cancelledCount = $plan->subscriptions()->where('status', 'cancelled')->count();
            $totalCount = $plan->subscriptions()->count();
            
            // Calculate monthly revenue (convert all to monthly for comparison)
            $monthlyPrice = $this->convertToMonthlyPrice($plan->price, $plan->billing_cycle);
            $planRevenue = $activeCount * $monthlyPrice;
            $totalRevenue += $planRevenue;

            $rows[] = [
                $plan->name,
                $activeCount,
                $trialCount,
                $cancelledCount,
                $totalCount,
                'R' . number_format($planRevenue, 2),
            ];
        }

        $this->table($headers, $rows);
        
        $this->newLine();
        $this->line("Total Monthly Revenue: <info>R" . number_format($totalRevenue, 2) . "</info>");
    }

    /**
     * Convert price to monthly equivalent.
     */
    protected function convertToMonthlyPrice(float $price, string $billingCycle): float
    {
        return match ($billingCycle) {
            'weekly' => $price * 4.33, // Average weeks per month
            'monthly' => $price,
            'quarterly' => $price / 3,
            'yearly' => $price / 12,
            default => $price,
        };
    }

    /**
     * Output plans as JSON.
     */
    protected function outputJson($plans): void
    {
        $data = $plans->map(function ($plan) {
            $planData = [
                'id' => $plan->id,
                'name' => $plan->name,
                'slug' => $plan->slug,
                'description' => $plan->description,
                'price' => $plan->price,
                'formatted_price' => $plan->formatted_price,
                'billing_cycle' => $plan->billing_cycle,
                'trial_days' => $plan->trial_days,
                'is_active' => $plan->is_active,
                'is_popular' => $plan->is_popular,
                'sort_order' => $plan->sort_order,
                'created_at' => $plan->created_at->toISOString(),
                'updated_at' => $plan->updated_at->toISOString(),
            ];

            if ($this->option('with-features') && $plan->relationLoaded('features')) {
                $planData['features'] = $plan->features->map(function ($feature) {
                    return [
                        'id' => $feature->id,
                        'feature_key' => $feature->feature_key,
                        'feature_name' => $feature->feature_name,
                        'feature_type' => $feature->feature_type,
                        'feature_limit' => $feature->feature_limit,
                        'typed_limit' => $feature->typed_limit,
                        'human_limit' => $feature->human_limit,
                        'is_unlimited' => $feature->is_unlimited,
                        'description' => $feature->description,
                        'sort_order' => $feature->sort_order,
                    ];
                });
            }

            if ($this->option('with-stats') && $plan->relationLoaded('subscriptions')) {
                $planData['statistics'] = [
                    'total_subscriptions' => $plan->subscriptions->count(),
                    'active_subscriptions' => $plan->subscriptions->where('status', 'active')->count(),
                    'trial_subscriptions' => $plan->subscriptions->where('status', 'trial')->count(),
                    'cancelled_subscriptions' => $plan->subscriptions->where('status', 'cancelled')->count(),
                ];
            }

            return $planData;
        });

        $this->line(json_encode([
            'plans' => $data,
            'total_count' => $plans->count(),
            'generated_at' => now()->toISOString(),
        ], JSON_PRETTY_PRINT));
    }
}
