<?php

namespace RiaanZA\LaravelSubscription\Console\Commands;

use Illuminate\Console\Command;
use RiaanZA\LaravelSubscription\Models\SubscriptionPlan;

class DiagnoseSubscriptionCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'subscription:diagnose {email}';

    /**
     * The console command description.
     */
    protected $description = 'Diagnose subscription issues for a specific user';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $email = $this->argument('email');
        $userModel = config('laravel-subscription.models.user', 'App\Models\User');
        
        $this->info("Diagnosing subscription issues for: {$email}");
        $this->line('');
        
        // Check if user exists
        $user = $userModel::where('email', $email)->first();
        
        if (!$user) {
            $this->error("❌ User not found with email: {$email}");
            return 1;
        }
        
        $this->info("✅ User found:");
        $this->line("   ID: {$user->id}");
        $this->line("   Email: {$user->email}");
        $this->line("   Name: " . ($user->name ?? 'N/A'));
        $this->line('');
        
        // Check if user has HasSubscriptions trait
        $traits = class_uses_recursive($user);
        $hasSubscriptionsTrait = in_array('RiaanZA\LaravelSubscription\Traits\HasSubscriptions', $traits);
        
        if ($hasSubscriptionsTrait) {
            $this->info("✅ User model has HasSubscriptions trait");
        } else {
            $this->error("❌ User model missing HasSubscriptions trait");
            $this->line("   Add this to your User model:");
            $this->line("   use RiaanZA\\LaravelSubscription\\Traits\\HasSubscriptions;");
            $this->line('');
        }
        
        // Check subscriptions method
        if (method_exists($user, 'subscriptions')) {
            $this->info("✅ User has subscriptions() method");
            
            // Get all subscriptions
            $subscriptions = $user->subscriptions()->get();
            $this->line("   Total subscriptions: {$subscriptions->count()}");
            
            foreach ($subscriptions as $subscription) {
                $this->line("   - ID: {$subscription->id}, Status: {$subscription->status}, Plan: {$subscription->plan->name}");
            }
            
            // Check active subscriptions
            $activeSubscriptions = $user->subscriptions()
                ->whereIn('status', ['active', 'trial'])
                ->get();
                
            $this->line("   Active subscriptions: {$activeSubscriptions->count()}");
            
        } else {
            $this->error("❌ User missing subscriptions() method");
        }
        
        $this->line('');
        
        // Check available plans
        $this->info("📋 Available Plans:");
        $plans = SubscriptionPlan::where('is_active', true)->get();
        
        if ($plans->isEmpty()) {
            $this->error("❌ No active plans found");
            $this->line("   Run: php artisan subscription:seed-plans");
        } else {
            foreach ($plans as $plan) {
                $trialInfo = $plan->trial_days > 0 ? " (Trial: {$plan->trial_days} days)" : " (No trial)";
                $this->line("   - {$plan->slug}: {$plan->name}{$trialInfo}");
            }
        }
        
        $this->line('');
        
        // Check enterprise plan specifically
        $enterprisePlan = SubscriptionPlan::where('slug', 'enterprise')->first();
        
        if ($enterprisePlan) {
            $this->info("✅ Enterprise plan found:");
            $this->line("   ID: {$enterprisePlan->id}");
            $this->line("   Name: {$enterprisePlan->name}");
            $this->line("   Active: " . ($enterprisePlan->is_active ? 'Yes' : 'No'));
            $this->line("   Trial Days: {$enterprisePlan->trial_days}");
            $this->line("   Price: {$enterprisePlan->formatted_price}");
        } else {
            $this->error("❌ Enterprise plan not found");
            $this->line("   Run: php artisan subscription:seed-plans");
        }
        
        $this->line('');
        
        // Test authorization
        if ($hasSubscriptionsTrait && method_exists($user, 'hasActiveSubscription')) {
            $hasActive = $user->hasActiveSubscription();
            $this->line("🔐 Authorization Check:");
            $this->line("   Has active subscription: " . ($hasActive ? 'Yes' : 'No'));
            
            if (!$hasActive) {
                $this->info("✅ User should be able to create new subscription");
            } else {
                $this->warning("⚠️  User already has active subscription - cannot create new one");
            }
        }
        
        $this->line('');
        $this->info("🔍 Diagnosis complete!");
        
        return 0;
    }
}
