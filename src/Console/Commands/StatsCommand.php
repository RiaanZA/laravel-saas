<?php

namespace RiaanZA\LaravelSubscription\Console\Commands;

use Illuminate\Console\Command;
use RiaanZA\LaravelSubscription\Models\SubscriptionPlan;
use RiaanZA\LaravelSubscription\Models\UserSubscription;
use RiaanZA\LaravelSubscription\Models\SubscriptionUsage;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class StatsCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'subscription:stats 
                            {--period=30 : Number of days to analyze}
                            {--detailed : Show detailed statistics}
                            {--export= : Export to file (csv, json)}';

    /**
     * The console command description.
     */
    protected $description = 'Display subscription statistics and analytics';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $period = (int) $this->option('period');
        $detailed = $this->option('detailed');
        $export = $this->option('export');

        $this->info("📊 Subscription Statistics (Last {$period} days)");
        $this->newLine();

        $stats = $this->gatherStatistics($period);

        if ($export) {
            $this->exportStatistics($stats, $export);
        } else {
            $this->displayStatistics($stats, $detailed);
        }

        return Command::SUCCESS;
    }

    /**
     * Gather comprehensive statistics.
     */
    protected function gatherStatistics(int $period): array
    {
        $startDate = Carbon::now()->subDays($period);
        $endDate = Carbon::now();

        return [
            'period' => [
                'days' => $period,
                'start_date' => $startDate->toDateString(),
                'end_date' => $endDate->toDateString(),
            ],
            'overview' => $this->getOverviewStats(),
            'subscriptions' => $this->getSubscriptionStats($startDate, $endDate),
            'plans' => $this->getPlanStats(),
            'revenue' => $this->getRevenueStats($startDate, $endDate),
            'usage' => $this->getUsageStats($startDate, $endDate),
            'trends' => $this->getTrendStats($period),
        ];
    }

    /**
     * Get overview statistics.
     */
    protected function getOverviewStats(): array
    {
        return [
            'total_plans' => SubscriptionPlan::count(),
            'active_plans' => SubscriptionPlan::active()->count(),
            'total_subscriptions' => UserSubscription::count(),
            'active_subscriptions' => UserSubscription::whereIn('status', ['active', 'trial'])->count(),
            'trial_subscriptions' => UserSubscription::where('status', 'trial')->count(),
            'cancelled_subscriptions' => UserSubscription::where('status', 'cancelled')->count(),
            'expired_subscriptions' => UserSubscription::where('status', 'expired')->count(),
        ];
    }

    /**
     * Get subscription statistics.
     */
    protected function getSubscriptionStats(Carbon $startDate, Carbon $endDate): array
    {
        return [
            'new_subscriptions' => UserSubscription::whereBetween('created_at', [$startDate, $endDate])->count(),
            'cancelled_in_period' => UserSubscription::whereBetween('cancelled_at', [$startDate, $endDate])->count(),
            'expired_in_period' => UserSubscription::where('status', 'expired')
                ->whereBetween('updated_at', [$startDate, $endDate])->count(),
            'trials_started' => UserSubscription::where('status', 'trial')
                ->whereBetween('created_at', [$startDate, $endDate])->count(),
            'trials_converted' => UserSubscription::where('status', 'active')
                ->whereNotNull('trial_ends_at')
                ->whereBetween('updated_at', [$startDate, $endDate])->count(),
        ];
    }

    /**
     * Get plan statistics.
     */
    protected function getPlanStats(): array
    {
        $plans = SubscriptionPlan::withCount([
            'subscriptions',
            'subscriptions as active_subscriptions_count' => function ($query) {
                $query->where('status', 'active');
            },
            'subscriptions as trial_subscriptions_count' => function ($query) {
                $query->where('status', 'trial');
            },
        ])->get();

        return $plans->map(function ($plan) {
            return [
                'name' => $plan->name,
                'slug' => $plan->slug,
                'price' => $plan->price,
                'billing_cycle' => $plan->billing_cycle,
                'total_subscriptions' => $plan->subscriptions_count,
                'active_subscriptions' => $plan->active_subscriptions_count,
                'trial_subscriptions' => $plan->trial_subscriptions_count,
                'is_popular' => $plan->is_popular,
            ];
        })->toArray();
    }

    /**
     * Get revenue statistics.
     */
    protected function getRevenueStats(Carbon $startDate, Carbon $endDate): array
    {
        // Calculate monthly recurring revenue (MRR)
        $activeSubscriptions = UserSubscription::with('plan')
            ->where('status', 'active')
            ->get();

        $mrr = 0;
        foreach ($activeSubscriptions as $subscription) {
            $monthlyPrice = $this->convertToMonthlyPrice($subscription->plan->price, $subscription->plan->billing_cycle);
            $mrr += $monthlyPrice;
        }

        // Calculate new revenue in period
        $newSubscriptions = UserSubscription::with('plan')
            ->whereBetween('created_at', [$startDate, $endDate])
            ->where('status', 'active')
            ->get();

        $newRevenue = 0;
        foreach ($newSubscriptions as $subscription) {
            $newRevenue += $subscription->plan->price;
        }

        return [
            'monthly_recurring_revenue' => round($mrr, 2),
            'annual_recurring_revenue' => round($mrr * 12, 2),
            'new_revenue_in_period' => round($newRevenue, 2),
            'average_revenue_per_user' => $activeSubscriptions->count() > 0 ? 
                round($mrr / $activeSubscriptions->count(), 2) : 0,
        ];
    }

    /**
     * Get usage statistics.
     */
    protected function getUsageStats(Carbon $startDate, Carbon $endDate): array
    {
        $totalUsageRecords = SubscriptionUsage::whereBetween('created_at', [$startDate, $endDate])->count();
        
        $topFeatures = SubscriptionUsage::select('feature_key', DB::raw('SUM(usage_count) as total_usage'))
            ->whereBetween('created_at', [$startDate, $endDate])
            ->groupBy('feature_key')
            ->orderByDesc('total_usage')
            ->limit(5)
            ->get();

        return [
            'total_usage_records' => $totalUsageRecords,
            'top_features' => $topFeatures->map(function ($feature) {
                return [
                    'feature_key' => $feature->feature_key,
                    'total_usage' => $feature->total_usage,
                ];
            })->toArray(),
        ];
    }

    /**
     * Get trend statistics.
     */
    protected function getTrendStats(int $period): array
    {
        $days = min($period, 30); // Limit to 30 days for trends
        $trends = [];

        for ($i = $days - 1; $i >= 0; $i--) {
            $date = Carbon::now()->subDays($i);
            $dayStart = $date->copy()->startOfDay();
            $dayEnd = $date->copy()->endOfDay();

            $trends[] = [
                'date' => $date->toDateString(),
                'new_subscriptions' => UserSubscription::whereBetween('created_at', [$dayStart, $dayEnd])->count(),
                'cancellations' => UserSubscription::whereBetween('cancelled_at', [$dayStart, $dayEnd])->count(),
                'trials_started' => UserSubscription::where('status', 'trial')
                    ->whereBetween('created_at', [$dayStart, $dayEnd])->count(),
            ];
        }

        return $trends;
    }

    /**
     * Display statistics.
     */
    protected function displayStatistics(array $stats, bool $detailed): void
    {
        // Overview
        $this->displayOverview($stats['overview']);
        
        // Subscription stats
        $this->displaySubscriptionStats($stats['subscriptions']);
        
        // Revenue stats
        $this->displayRevenueStats($stats['revenue']);
        
        // Plan performance
        $this->displayPlanStats($stats['plans']);

        if ($detailed) {
            // Usage stats
            $this->displayUsageStats($stats['usage']);
            
            // Trends
            $this->displayTrends($stats['trends']);
        }
    }

    /**
     * Display overview statistics.
     */
    protected function displayOverview(array $overview): void
    {
        $this->line('📋 <comment>Overview</comment>');
        $this->table(
            ['Metric', 'Count'],
            [
                ['Total Plans', $overview['total_plans']],
                ['Active Plans', $overview['active_plans']],
                ['Total Subscriptions', $overview['total_subscriptions']],
                ['Active Subscriptions', $overview['active_subscriptions']],
                ['Trial Subscriptions', $overview['trial_subscriptions']],
                ['Cancelled Subscriptions', $overview['cancelled_subscriptions']],
                ['Expired Subscriptions', $overview['expired_subscriptions']],
            ]
        );
        $this->newLine();
    }

    /**
     * Display subscription statistics.
     */
    protected function displaySubscriptionStats(array $subscriptions): void
    {
        $this->line('📈 <comment>Subscription Activity</comment>');
        $this->table(
            ['Metric', 'Count'],
            [
                ['New Subscriptions', $subscriptions['new_subscriptions']],
                ['Cancellations', $subscriptions['cancelled_in_period']],
                ['Expirations', $subscriptions['expired_in_period']],
                ['Trials Started', $subscriptions['trials_started']],
                ['Trials Converted', $subscriptions['trials_converted']],
            ]
        );
        $this->newLine();
    }

    /**
     * Display revenue statistics.
     */
    protected function displayRevenueStats(array $revenue): void
    {
        $this->line('💰 <comment>Revenue</comment>');
        $this->table(
            ['Metric', 'Amount'],
            [
                ['Monthly Recurring Revenue (MRR)', 'R' . number_format($revenue['monthly_recurring_revenue'], 2)],
                ['Annual Recurring Revenue (ARR)', 'R' . number_format($revenue['annual_recurring_revenue'], 2)],
                ['New Revenue (Period)', 'R' . number_format($revenue['new_revenue_in_period'], 2)],
                ['Average Revenue Per User', 'R' . number_format($revenue['average_revenue_per_user'], 2)],
            ]
        );
        $this->newLine();
    }

    /**
     * Display plan statistics.
     */
    protected function displayPlanStats(array $plans): void
    {
        $this->line('📊 <comment>Plan Performance</comment>');
        
        $headers = ['Plan', 'Price', 'Billing', 'Total Subs', 'Active', 'Trial', 'Popular'];
        $rows = [];

        foreach ($plans as $plan) {
            $rows[] = [
                $plan['name'],
                'R' . number_format($plan['price'], 2),
                ucfirst($plan['billing_cycle']),
                $plan['total_subscriptions'],
                $plan['active_subscriptions'],
                $plan['trial_subscriptions'],
                $plan['is_popular'] ? '⭐' : '',
            ];
        }

        $this->table($headers, $rows);
        $this->newLine();
    }

    /**
     * Display usage statistics.
     */
    protected function displayUsageStats(array $usage): void
    {
        $this->line('🎯 <comment>Feature Usage</comment>');
        
        $this->line("Total usage records: <info>{$usage['total_usage_records']}</info>");
        $this->newLine();

        if (!empty($usage['top_features'])) {
            $this->line('Top Features:');
            $headers = ['Feature', 'Total Usage'];
            $rows = [];

            foreach ($usage['top_features'] as $feature) {
                $rows[] = [$feature['feature_key'], number_format($feature['total_usage'])];
            }

            $this->table($headers, $rows);
        }
        $this->newLine();
    }

    /**
     * Display trend statistics.
     */
    protected function displayTrends(array $trends): void
    {
        $this->line('📈 <comment>Daily Trends (Last 7 days)</comment>');
        
        $headers = ['Date', 'New Subs', 'Cancellations', 'Trials'];
        $rows = [];

        // Show only last 7 days for readability
        $recentTrends = array_slice($trends, -7);

        foreach ($recentTrends as $trend) {
            $rows[] = [
                $trend['date'],
                $trend['new_subscriptions'],
                $trend['cancellations'],
                $trend['trials_started'],
            ];
        }

        $this->table($headers, $rows);
        $this->newLine();
    }

    /**
     * Export statistics to file.
     */
    protected function exportStatistics(array $stats, string $format): void
    {
        $filename = 'subscription_stats_' . date('Y-m-d_H-i-s') . '.' . $format;
        $path = storage_path('app/' . $filename);

        if ($format === 'json') {
            file_put_contents($path, json_encode($stats, JSON_PRETTY_PRINT));
        } elseif ($format === 'csv') {
            $this->exportToCsv($stats, $path);
        } else {
            $this->error("Unsupported export format: {$format}");
            return;
        }

        $this->info("📁 Statistics exported to: <info>{$path}</info>");
    }

    /**
     * Export statistics to CSV.
     */
    protected function exportToCsv(array $stats, string $path): void
    {
        $csv = fopen($path, 'w');
        
        // Write overview
        fputcsv($csv, ['Overview']);
        foreach ($stats['overview'] as $key => $value) {
            fputcsv($csv, [ucwords(str_replace('_', ' ', $key)), $value]);
        }
        
        fputcsv($csv, []); // Empty row
        
        // Write plan stats
        fputcsv($csv, ['Plan Performance']);
        fputcsv($csv, ['Plan', 'Price', 'Billing Cycle', 'Total Subscriptions', 'Active', 'Trial']);
        foreach ($stats['plans'] as $plan) {
            fputcsv($csv, [
                $plan['name'],
                $plan['price'],
                $plan['billing_cycle'],
                $plan['total_subscriptions'],
                $plan['active_subscriptions'],
                $plan['trial_subscriptions'],
            ]);
        }
        
        fclose($csv);
    }

    /**
     * Convert price to monthly equivalent.
     */
    protected function convertToMonthlyPrice(float $price, string $billingCycle): float
    {
        return match ($billingCycle) {
            'weekly' => $price * 4.33,
            'monthly' => $price,
            'quarterly' => $price / 3,
            'yearly' => $price / 12,
            default => $price,
        };
    }
}
