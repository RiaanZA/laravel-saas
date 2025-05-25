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
        Schema::create(config('laravel-subscription.table_names.plan_features', 'plan_features'), function (Blueprint $table) {
            $table->id();
            $table->foreignId('plan_id')->constrained(config('laravel-subscription.table_names.subscription_plans', 'subscription_plans'))->onDelete('cascade');
            $table->string('feature_key'); // e.g., 'max_users', 'storage_gb', 'api_calls'
            $table->string('feature_name'); // human-readable name
            $table->enum('feature_type', ['boolean', 'numeric', 'text']);
            $table->string('feature_limit')->nullable(); // stores limit value
            $table->boolean('is_unlimited')->default(false);
            $table->text('description')->nullable();
            $table->integer('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['plan_id', 'feature_key']);
            $table->index(['plan_id', 'sort_order']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists(config('laravel-subscription.table_names.plan_features', 'plan_features'));
    }
};
