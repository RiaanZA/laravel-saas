<?php

namespace RiaanZA\LaravelSubscription\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use RiaanZA\LaravelSubscription\Models\SubscriptionPlan;

class CreateSubscriptionRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'plan_slug' => [
                'required',
                'string',
                'exists:' . config('laravel-subscription.table_names.subscription_plans', 'subscription_plans') . ',slug',
                function ($attribute, $value, $fail) {
                    $plan = SubscriptionPlan::where('slug', $value)->first();
                    if (!$plan || !$plan->is_active) {
                        $fail('The selected plan is not available.');
                    }
                },
            ],
            'start_trial' => 'boolean',
            'payment_data' => 'array',
            'payment_data.payment_method_id' => 'string|nullable',
            'payment_data.billing_address' => 'array|nullable',
            'payment_data.billing_address.line1' => 'string|nullable',
            'payment_data.billing_address.line2' => 'string|nullable',
            'payment_data.billing_address.city' => 'string|nullable',
            'payment_data.billing_address.state' => 'string|nullable',
            'payment_data.billing_address.postal_code' => 'string|nullable',
            'payment_data.billing_address.country' => 'string|nullable',
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'plan_slug.required' => 'Please select a subscription plan.',
            'plan_slug.exists' => 'The selected plan does not exist.',
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        // Ensure start_trial is boolean
        if ($this->has('start_trial')) {
            $this->merge([
                'start_trial' => filter_var($this->start_trial, FILTER_VALIDATE_BOOLEAN),
            ]);
        }
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            // Check if user already has an active subscription
            $user = $this->user();
            if ($user) {
                $existingSubscription = $user->subscriptions()
                    ->whereIn('status', ['active', 'trial'])
                    ->exists();

                if ($existingSubscription) {
                    $validator->errors()->add('subscription', 'You already have an active subscription.');
                }
            }

            // Validate trial eligibility
            if ($this->boolean('start_trial')) {
                $plan = SubscriptionPlan::where('slug', $this->plan_slug)->first();
                if ($plan && !$plan->hasTrialPeriod()) {
                    $validator->errors()->add('start_trial', 'This plan does not offer a trial period.');
                }

                // Check if user has already used trial for this plan
                if ($user && $plan) {
                    $hasUsedTrial = $user->subscriptions()
                        ->where('plan_id', $plan->id)
                        ->whereNotNull('trial_ends_at')
                        ->exists();

                    if ($hasUsedTrial) {
                        $validator->errors()->add('start_trial', 'You have already used the trial period for this plan.');
                    }
                }
            }
        });
    }
}
