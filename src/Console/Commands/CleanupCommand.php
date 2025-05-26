<?php

namespace RiaanZA\LaravelSubscription\Console\Commands;

use Illuminate\Console\Command;
use RiaanZA\LaravelSubscription\Models\UserSubscription;
use RiaanZA\LaravelSubscription\Models\SubscriptionUsage;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class CleanupCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'subscription:cleanup 
                            {--expired : Clean up expired subscriptions}
                            {--usage : Clean up old usage records}
                            {--days=90 : Number of days to keep records}
                            {--dry-run : Show what would be cleaned without actually deleting}
                            {--force : Force cleanup without confirmation}';

    /**
     * The console command description.
     */
    protected $description = 'Clean up expired subscriptions and old usage data';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('🧹 Starting subscription cleanup...');
        $this->newLine();

        $dryRun = $this->option('dry-run');
        $days = (int) $this->option('days');
        $cutoffDate = Carbon::now()->subDays($days);

        if ($dryRun) {
            $this->warn('🔍 DRY RUN MODE - No data will be deleted');
            $this->newLine();
        }

        $cleanupExpired = $this->option('expired') || $this->confirm('Clean up expired subscriptions?');
        $cleanupUsage = $this->option('usage') || $this->confirm('Clean up old usage records?');

        if (!$cleanupExpired && !$cleanupUsage) {
            $this->info('No cleanup operations selected.');
            return Command::SUCCESS;
        }

        // Show what will be cleaned
        $this->showCleanupPreview($cutoffDate, $cleanupExpired, $cleanupUsage);

        if (!$dryRun && !$this->option('force')) {
            if (!$this->confirm('Proceed with cleanup?')) {
                $this->info('Cleanup cancelled.');
                return Command::SUCCESS;
            }
        }

        $totalCleaned = 0;

        try {
            DB::beginTransaction();

            if ($cleanupExpired) {
                $totalCleaned += $this->cleanupExpiredSubscriptions($cutoffDate, $dryRun);
            }

            if ($cleanupUsage) {
                $totalCleaned += $this->cleanupOldUsageRecords($cutoffDate, $dryRun);
            }

            if (!$dryRun) {
                DB::commit();
            }

            $this->displayCleanupSummary($totalCleaned, $dryRun);
            return Command::SUCCESS;

        } catch (\Exception $e) {
            if (!$dryRun) {
                DB::rollBack();
            }
            
            $this->error('❌ Cleanup failed: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }

    /**
     * Show cleanup preview.
     */
    protected function showCleanupPreview(Carbon $cutoffDate, bool $cleanupExpired, bool $cleanupUsage): void
    {
        $this->info("📅 Cleanup cutoff date: <comment>{$cutoffDate->toDateString()}</comment>");
        $this->newLine();

        if ($cleanupExpired) {
            $expiredCount = $this->getExpiredSubscriptionsCount($cutoffDate);
            $this->line("🗑️  Expired subscriptions to clean: <info>{$expiredCount}</info>");
        }

        if ($cleanupUsage) {
            $oldUsageCount = $this->getOldUsageRecordsCount($cutoffDate);
            $this->line("📊 Old usage records to clean: <info>{$oldUsageCount}</info>");
        }

        $this->newLine();
    }

    /**
     * Get count of expired subscriptions.
     */
    protected function getExpiredSubscriptionsCount(Carbon $cutoffDate): int
    {
        return UserSubscription::where('status', 'expired')
            ->where('updated_at', '<', $cutoffDate)
            ->count();
    }

    /**
     * Get count of old usage records.
     */
    protected function getOldUsageRecordsCount(Carbon $cutoffDate): int
    {
        return SubscriptionUsage::where('period_end', '<', $cutoffDate)
            ->count();
    }

    /**
     * Clean up expired subscriptions.
     */
    protected function cleanupExpiredSubscriptions(Carbon $cutoffDate, bool $dryRun): int
    {
        $this->info('🗑️  Cleaning up expired subscriptions...');

        $query = UserSubscription::where('status', 'expired')
            ->where('updated_at', '<', $cutoffDate);

        $count = $query->count();

        if ($count === 0) {
            $this->line('   No expired subscriptions to clean up.');
            return 0;
        }

        if (!$dryRun) {
            // First, clean up related usage records
            $subscriptionIds = $query->pluck('id');
            SubscriptionUsage::whereIn('subscription_id', $subscriptionIds)->delete();
            
            // Then delete the subscriptions
            $query->delete();
        }

        $this->line("   ✅ Cleaned up <info>{$count}</info> expired subscriptions");
        return $count;
    }

    /**
     * Clean up old usage records.
     */
    protected function cleanupOldUsageRecords(Carbon $cutoffDate, bool $dryRun): int
    {
        $this->info('📊 Cleaning up old usage records...');

        $query = SubscriptionUsage::where('period_end', '<', $cutoffDate);
        $count = $query->count();

        if ($count === 0) {
            $this->line('   No old usage records to clean up.');
            return 0;
        }

        if (!$dryRun) {
            $query->delete();
        }

        $this->line("   ✅ Cleaned up <info>{$count}</info> old usage records");
        return $count;
    }

    /**
     * Display cleanup summary.
     */
    protected function displayCleanupSummary(int $totalCleaned, bool $dryRun): void
    {
        $this->newLine();
        
        if ($dryRun) {
            $this->info("🔍 DRY RUN COMPLETE - Would have cleaned <info>{$totalCleaned}</info> records");
        } else {
            $this->info("✅ CLEANUP COMPLETE - Cleaned <info>{$totalCleaned}</info> records");
        }

        $this->newLine();
        $this->line('📋 <comment>Cleanup Statistics:</comment>');
        
        // Show current database stats
        $activeSubscriptions = UserSubscription::whereIn('status', ['active', 'trial'])->count();
        $totalSubscriptions = UserSubscription::count();
        $totalUsageRecords = SubscriptionUsage::count();

        $this->line("   Active subscriptions: <info>{$activeSubscriptions}</info>");
        $this->line("   Total subscriptions: <info>{$totalSubscriptions}</info>");
        $this->line("   Total usage records: <info>{$totalUsageRecords}</info>");

        $this->newLine();
        $this->line('💡 <comment>Tip:</comment> Run this command regularly to keep your database clean.');
        $this->line('   Consider adding it to your scheduled tasks:');
        $this->line('   <info>$schedule->command(\'subscription:cleanup --force\')->weekly();</info>');
    }
}
