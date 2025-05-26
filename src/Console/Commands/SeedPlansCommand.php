<?php

namespace RiaanZA\LaravelSubscription\Console\Commands;

use Illuminate\Console\Command;
use RiaanZA\LaravelSubscription\Models\SubscriptionPlan;
use RiaanZA\LaravelSubscription\Models\PlanFeature;
use Illuminate\Support\Facades\DB;
use Exception;

class SeedPlansCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'subscription:seed-plans 
                            {--clear : Clear existing plans before seeding}
                            {--custom : Use custom plan configuration}
                            {--file= : Path to custom plans JSON file}';

    /**
     * The console command description.
     */
    protected $description = 'Seed sample subscription plans with features';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('🌱 Seeding subscription plans...');
        $this->newLine();

        // Clear existing plans if requested
        if ($this->option('clear')) {
            $this->clearExistingPlans();
        }

        // Check if plans already exist
        if (!$this->option('clear') && SubscriptionPlan::count() > 0) {
            if (!$this->confirm('Subscription plans already exist. Continue seeding?')) {
                $this->info('Seeding cancelled.');
                return Command::SUCCESS;
            }
        }

        try {
            DB::beginTransaction();

            if ($this->option('custom') || $this->option('file')) {
                $this->seedCustomPlans();
            } else {
                $this->seedDefaultPlans();
            }

            DB::commit();

            $this->displaySummary();
            return Command::SUCCESS;

        } catch (Exception $e) {
            DB::rollBack();
            $this->error('❌ Failed to seed plans: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }

    /**
     * Clear existing plans and features.
     */
    protected function clearExistingPlans(): void
    {
        $this->info('🗑️  Clearing existing plans...');

        $planCount = SubscriptionPlan::count();
        $featureCount = PlanFeature::count();

        // Delete in correct order to avoid foreign key constraints
        PlanFeature::truncate();
        SubscriptionPlan::truncate();

        $this->info("✅ Cleared {$planCount} plans and {$featureCount} features");
    }

    /**
     * Seed custom plans from file or interactive input.
     */
    protected function seedCustomPlans(): void
    {
        $filePath = $this->option('file');

        if ($filePath) {
            $this->seedFromFile($filePath);
        } else {
            $this->seedInteractively();
        }
    }

    /**
     * Seed plans from JSON file.
     */
    protected function seedFromFile(string $filePath): void
    {
        if (!file_exists($filePath)) {
            throw new Exception("File not found: {$filePath}");
        }

        $content = file_get_contents($filePath);
        $plansData = json_decode($content, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception('Invalid JSON in plans file: ' . json_last_error_msg());
        }

        if (!isset($plansData['plans']) || !is_array($plansData['plans'])) {
            throw new Exception('Plans file must contain a "plans" array');
        }

        $this->info("📁 Loading plans from: {$filePath}");

        foreach ($plansData['plans'] as $planData) {
            $this->createPlanFromData($planData);
        }
    }

    /**
     * Seed plans interactively.
     */
    protected function seedInteractively(): void
    {
        $this->info('🎯 Interactive plan creation');
        $this->newLine();

        do {
            $this->createPlanInteractively();
        } while ($this->confirm('Create another plan?'));
    }

    /**
     * Create a plan interactively.
     */
    protected function createPlanInteractively(): void
    {
        $name = $this->ask('Plan name');
        $slug = $this->ask('Plan slug', str()->slug($name));
        $description = $this->ask('Plan description (optional)');
        $price = (float) $this->ask('Plan price');
        $billingCycle = $this->choice('Billing cycle', ['monthly', 'yearly', 'quarterly', 'weekly'], 'monthly');
        $trialDays = (int) $this->ask('Trial days', '0');
        $isPopular = $this->confirm('Mark as popular?');

        $planData = [
            'name' => $name,
            'slug' => $slug,
            'description' => $description ?: null,
            'price' => $price,
            'billing_cycle' => $billingCycle,
            'trial_days' => $trialDays,
            'is_popular' => $isPopular,
            'features' => [],
        ];

        // Add features
        if ($this->confirm('Add features to this plan?')) {
            do {
                $feature = $this->createFeatureInteractively();
                $planData['features'][] = $feature;
            } while ($this->confirm('Add another feature?'));
        }

        $this->createPlanFromData($planData);
    }

    /**
     * Create a feature interactively.
     */
    protected function createFeatureInteractively(): array
    {
        $key = $this->ask('Feature key (e.g., max_users, api_calls)');
        $name = $this->ask('Feature name');
        $type = $this->choice('Feature type', ['boolean', 'numeric', 'text'], 'boolean');
        $description = $this->ask('Feature description (optional)');

        $feature = [
            'feature_key' => $key,
            'feature_name' => $name,
            'feature_type' => $type,
            'description' => $description ?: null,
        ];

        if ($type === 'boolean') {
            $feature['feature_limit'] = $this->confirm('Enable this feature?') ? '1' : '0';
            $feature['is_unlimited'] = false;
        } elseif ($type === 'numeric') {
            if ($this->confirm('Is this feature unlimited?')) {
                $feature['is_unlimited'] = true;
                $feature['feature_limit'] = null;
            } else {
                $feature['is_unlimited'] = false;
                $feature['feature_limit'] = $this->ask('Feature limit (number)');
            }
        } else {
            $feature['feature_limit'] = $this->ask('Feature value');
            $feature['is_unlimited'] = false;
        }

        return $feature;
    }

    /**
     * Seed default sample plans.
     */
    protected function seedDefaultPlans(): void
    {
        $this->info('📦 Creating default sample plans...');

        $plans = $this->getDefaultPlansData();

        foreach ($plans as $planData) {
            $this->createPlanFromData($planData);
        }
    }

    /**
     * Get default plans data.
     */
    protected function getDefaultPlansData(): array
    {
        return [
            [
                'name' => 'Starter',
                'slug' => 'starter',
                'description' => 'Perfect for individuals and small projects',
                'price' => 9.99,
                'billing_cycle' => 'monthly',
                'trial_days' => 14,
                'is_popular' => false,
                'sort_order' => 1,
                'features' => [
                    [
                        'feature_key' => 'max_users',
                        'feature_name' => 'Maximum Users',
                        'feature_type' => 'numeric',
                        'feature_limit' => '5',
                        'is_unlimited' => false,
                        'description' => 'Maximum number of users allowed',
                        'sort_order' => 1,
                    ],
                    [
                        'feature_key' => 'storage_gb',
                        'feature_name' => 'Storage Space',
                        'feature_type' => 'numeric',
                        'feature_limit' => '10',
                        'is_unlimited' => false,
                        'description' => 'Storage space in GB',
                        'sort_order' => 2,
                    ],
                    [
                        'feature_key' => 'api_calls',
                        'feature_name' => 'API Calls per Month',
                        'feature_type' => 'numeric',
                        'feature_limit' => '1000',
                        'is_unlimited' => false,
                        'description' => 'Monthly API call limit',
                        'sort_order' => 3,
                    ],
                    [
                        'feature_key' => 'email_support',
                        'feature_name' => 'Email Support',
                        'feature_type' => 'boolean',
                        'feature_limit' => '1',
                        'is_unlimited' => false,
                        'description' => 'Access to email support',
                        'sort_order' => 4,
                    ],
                    [
                        'feature_key' => 'basic_analytics',
                        'feature_name' => 'Basic Analytics',
                        'feature_type' => 'boolean',
                        'feature_limit' => '1',
                        'is_unlimited' => false,
                        'description' => 'Access to basic analytics dashboard',
                        'sort_order' => 5,
                    ],
                ],
            ],
            [
                'name' => 'Professional',
                'slug' => 'professional',
                'description' => 'Ideal for growing businesses and teams',
                'price' => 29.99,
                'billing_cycle' => 'monthly',
                'trial_days' => 14,
                'is_popular' => true,
                'sort_order' => 2,
                'features' => [
                    [
                        'feature_key' => 'max_users',
                        'feature_name' => 'Maximum Users',
                        'feature_type' => 'numeric',
                        'feature_limit' => '25',
                        'is_unlimited' => false,
                        'description' => 'Maximum number of users allowed',
                        'sort_order' => 1,
                    ],
                    [
                        'feature_key' => 'storage_gb',
                        'feature_name' => 'Storage Space',
                        'feature_type' => 'numeric',
                        'feature_limit' => '100',
                        'is_unlimited' => false,
                        'description' => 'Storage space in GB',
                        'sort_order' => 2,
                    ],
                    [
                        'feature_key' => 'api_calls',
                        'feature_name' => 'API Calls per Month',
                        'feature_type' => 'numeric',
                        'feature_limit' => '10000',
                        'is_unlimited' => false,
                        'description' => 'Monthly API call limit',
                        'sort_order' => 3,
                    ],
                    [
                        'feature_key' => 'email_support',
                        'feature_name' => 'Email Support',
                        'feature_type' => 'boolean',
                        'feature_limit' => '1',
                        'is_unlimited' => false,
                        'description' => 'Access to email support',
                        'sort_order' => 4,
                    ],
                    [
                        'feature_key' => 'priority_support',
                        'feature_name' => 'Priority Support',
                        'feature_type' => 'boolean',
                        'feature_limit' => '1',
                        'is_unlimited' => false,
                        'description' => 'Priority customer support',
                        'sort_order' => 5,
                    ],
                    [
                        'feature_key' => 'advanced_analytics',
                        'feature_name' => 'Advanced Analytics',
                        'feature_type' => 'boolean',
                        'feature_limit' => '1',
                        'is_unlimited' => false,
                        'description' => 'Access to advanced analytics and reporting',
                        'sort_order' => 6,
                    ],
                    [
                        'feature_key' => 'team_collaboration',
                        'feature_name' => 'Team Collaboration',
                        'feature_type' => 'boolean',
                        'feature_limit' => '1',
                        'is_unlimited' => false,
                        'description' => 'Team collaboration features',
                        'sort_order' => 7,
                    ],
                    [
                        'feature_key' => 'custom_integrations',
                        'feature_name' => 'Custom Integrations',
                        'feature_type' => 'numeric',
                        'feature_limit' => '5',
                        'is_unlimited' => false,
                        'description' => 'Number of custom integrations allowed',
                        'sort_order' => 8,
                    ],
                ],
            ],
            [
                'name' => 'Enterprise',
                'slug' => 'enterprise',
                'description' => 'For large organizations with advanced needs',
                'price' => 99.99,
                'billing_cycle' => 'monthly',
                'trial_days' => 30,
                'is_popular' => false,
                'sort_order' => 3,
                'features' => [
                    [
                        'feature_key' => 'max_users',
                        'feature_name' => 'Maximum Users',
                        'feature_type' => 'numeric',
                        'feature_limit' => null,
                        'is_unlimited' => true,
                        'description' => 'Unlimited users',
                        'sort_order' => 1,
                    ],
                    [
                        'feature_key' => 'storage_gb',
                        'feature_name' => 'Storage Space',
                        'feature_type' => 'numeric',
                        'feature_limit' => '1000',
                        'is_unlimited' => false,
                        'description' => 'Storage space in GB',
                        'sort_order' => 2,
                    ],
                    [
                        'feature_key' => 'api_calls',
                        'feature_name' => 'API Calls per Month',
                        'feature_type' => 'numeric',
                        'feature_limit' => null,
                        'is_unlimited' => true,
                        'description' => 'Unlimited API calls',
                        'sort_order' => 3,
                    ],
                    [
                        'feature_key' => 'email_support',
                        'feature_name' => 'Email Support',
                        'feature_type' => 'boolean',
                        'feature_limit' => '1',
                        'is_unlimited' => false,
                        'description' => 'Access to email support',
                        'sort_order' => 4,
                    ],
                    [
                        'feature_key' => 'priority_support',
                        'feature_name' => 'Priority Support',
                        'feature_type' => 'boolean',
                        'feature_limit' => '1',
                        'is_unlimited' => false,
                        'description' => 'Priority customer support',
                        'sort_order' => 5,
                    ],
                    [
                        'feature_key' => 'phone_support',
                        'feature_name' => 'Phone Support',
                        'feature_type' => 'boolean',
                        'feature_limit' => '1',
                        'is_unlimited' => false,
                        'description' => 'Dedicated phone support',
                        'sort_order' => 6,
                    ],
                    [
                        'feature_key' => 'advanced_analytics',
                        'feature_name' => 'Advanced Analytics',
                        'feature_type' => 'boolean',
                        'feature_limit' => '1',
                        'is_unlimited' => false,
                        'description' => 'Access to advanced analytics and reporting',
                        'sort_order' => 7,
                    ],
                    [
                        'feature_key' => 'team_collaboration',
                        'feature_name' => 'Team Collaboration',
                        'feature_type' => 'boolean',
                        'feature_limit' => '1',
                        'is_unlimited' => false,
                        'description' => 'Team collaboration features',
                        'sort_order' => 8,
                    ],
                    [
                        'feature_key' => 'custom_integrations',
                        'feature_name' => 'Custom Integrations',
                        'feature_type' => 'numeric',
                        'feature_limit' => null,
                        'is_unlimited' => true,
                        'description' => 'Unlimited custom integrations',
                        'sort_order' => 9,
                    ],
                    [
                        'feature_key' => 'sso_integration',
                        'feature_name' => 'SSO Integration',
                        'feature_type' => 'boolean',
                        'feature_limit' => '1',
                        'is_unlimited' => false,
                        'description' => 'Single Sign-On integration',
                        'sort_order' => 10,
                    ],
                    [
                        'feature_key' => 'custom_branding',
                        'feature_name' => 'Custom Branding',
                        'feature_type' => 'boolean',
                        'feature_limit' => '1',
                        'is_unlimited' => false,
                        'description' => 'Custom branding and white-label options',
                        'sort_order' => 11,
                    ],
                    [
                        'feature_key' => 'dedicated_manager',
                        'feature_name' => 'Dedicated Account Manager',
                        'feature_type' => 'boolean',
                        'feature_limit' => '1',
                        'is_unlimited' => false,
                        'description' => 'Dedicated account manager',
                        'sort_order' => 12,
                    ],
                ],
            ],
        ];
    }

    /**
     * Create a plan from data array.
     */
    protected function createPlanFromData(array $planData): void
    {
        // Validate required fields
        $required = ['name', 'slug', 'price', 'billing_cycle'];
        foreach ($required as $field) {
            if (!isset($planData[$field])) {
                throw new Exception("Missing required field: {$field}");
            }
        }

        // Create the plan
        $plan = SubscriptionPlan::create([
            'name' => $planData['name'],
            'slug' => $planData['slug'],
            'description' => $planData['description'] ?? null,
            'price' => $planData['price'],
            'billing_cycle' => $planData['billing_cycle'],
            'trial_days' => $planData['trial_days'] ?? 0,
            'is_active' => $planData['is_active'] ?? true,
            'is_popular' => $planData['is_popular'] ?? false,
            'sort_order' => $planData['sort_order'] ?? 0,
            'metadata' => $planData['metadata'] ?? null,
        ]);

        $this->line("✅ Created plan: <info>{$plan->name}</info> ({$plan->slug})");

        // Create features
        if (isset($planData['features']) && is_array($planData['features'])) {
            foreach ($planData['features'] as $featureData) {
                $this->createFeatureForPlan($plan, $featureData);
            }
        }
    }

    /**
     * Create a feature for a plan.
     */
    protected function createFeatureForPlan(SubscriptionPlan $plan, array $featureData): void
    {
        $required = ['feature_key', 'feature_name', 'feature_type'];
        foreach ($required as $field) {
            if (!isset($featureData[$field])) {
                throw new Exception("Missing required feature field: {$field}");
            }
        }

        $feature = PlanFeature::create([
            'plan_id' => $plan->id,
            'feature_key' => $featureData['feature_key'],
            'feature_name' => $featureData['feature_name'],
            'feature_type' => $featureData['feature_type'],
            'feature_limit' => $featureData['feature_limit'] ?? null,
            'is_unlimited' => $featureData['is_unlimited'] ?? false,
            'description' => $featureData['description'] ?? null,
            'sort_order' => $featureData['sort_order'] ?? 0,
        ]);

        $limitText = $feature->is_unlimited ? 'unlimited' : ($feature->feature_limit ?? 'N/A');
        $this->line("   ➤ Feature: <comment>{$feature->feature_name}</comment> ({$feature->feature_type}: {$limitText})");
    }

    /**
     * Display seeding summary.
     */
    protected function displaySummary(): void
    {
        $planCount = SubscriptionPlan::count();
        $featureCount = PlanFeature::count();

        $this->newLine();
        $this->info('🎉 Subscription plans seeded successfully!');
        $this->newLine();

        $this->line("📊 <comment>Summary:</comment>");
        $this->line("   Plans created: <info>{$planCount}</info>");
        $this->line("   Features created: <info>{$featureCount}</info>");
        $this->newLine();

        // Display created plans
        $plans = SubscriptionPlan::with('features')->orderBy('sort_order')->get();
        
        $this->line("📋 <comment>Created Plans:</comment>");
        foreach ($plans as $plan) {
            $popularBadge = $plan->is_popular ? ' <bg=yellow;fg=black> POPULAR </>' : '';
            $this->line("   • <info>{$plan->name}</info>{$popularBadge} - {$plan->formatted_price}/{$plan->billing_cycle}");
            $this->line("     Slug: {$plan->slug} | Trial: {$plan->trial_days} days | Features: {$plan->features->count()}");
        }

        $this->newLine();
        $this->line("🔗 <comment>Next Steps:</comment>");
        $this->line("   1. View plans: <info>php artisan subscription:list-plans</info>");
        $this->line("   2. Test subscription flow in your application");
        $this->line("   3. Customize plans as needed for your business");
        $this->newLine();

        $this->line("💡 <comment>Tip:</comment> You can create custom plans using:");
        $this->line("   <info>php artisan subscription:seed-plans --custom</info>");
    }
}
