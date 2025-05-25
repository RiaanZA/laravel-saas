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
        Schema::create(config('laravel-subscription.table_names.user_subscriptions', 'user_subscriptions'), function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('plan_id')->constrained(config('laravel-subscription.table_names.subscription_plans', 'subscription_plans'));
            $table->string('peach_subscription_id')->nullable();
            $table->enum('status', ['active', 'cancelled', 'expired', 'trial', 'pending', 'past_due', 'suspended']);
            $table->timestamp('trial_ends_at')->nullable();
            $table->timestamp('current_period_start');
            $table->timestamp('current_period_end');
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->decimal('amount', 10, 2);
            $table->string('currency', 3)->default('ZAR');
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status']);
            $table->index('status');
            $table->index('current_period_end');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists(config('laravel-subscription.table_names.user_subscriptions', 'user_subscriptions'));
    }
};
