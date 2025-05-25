<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create(config('laravel-subscription.table_names.subscription_usage', 'subscription_usage'), function (Blueprint $table) {
            $table->id();
            $table->foreignId('subscription_id')->constrained(config('laravel-subscription.table_names.user_subscriptions', 'user_subscriptions'))->onDelete('cascade');
            $table->string('feature_key'); // e.g., 'max_users', 'storage_gb', 'api_calls'
            $table->integer('usage_count')->default(0);
            $table->timestamp('period_start');
            $table->timestamp('period_end');
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['subscription_id', 'feature_key', 'period_start']);
            $table->index(['subscription_id', 'feature_key']);
            $table->index(['period_start', 'period_end']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists(config('laravel-subscription.table_names.subscription_usage', 'subscription_usage'));
    }
};
