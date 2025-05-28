<?php

namespace RiaanZA\LaravelSubscription\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use RiaanZA\LaravelSubscription\Models\SubscriptionPlan;

class CreatePublicSubscriptionRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        // Public endpoint - no authentication required
        return true;
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
            'customer' => 'required|array',
            'customer.first_name' => 'required|string|max:255',
            'customer.last_name' => 'required|string|max:255',
            'customer.email' => 'required|email|max:255|unique:users,email',
            'customer.password' => 'nullable|string|min:8|confirmed',
            'payment_data' => 'array|nullable',
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
            'customer.required' => 'Customer information is required.',
            'customer.first_name.required' => 'First name is required.',
            'customer.last_name.required' => 'Last name is required.',
            'customer.email.required' => 'Email address is required.',
            'customer.email.email' => 'Please provide a valid email address.',
            'customer.email.unique' => 'An account with this email address already exists.',
            'customer.password.min' => 'Password must be at least 8 characters.',
            'customer.password.confirmed' => 'Password confirmation does not match.',
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

        // If no password provided, generate a random one
        if (!$this->has('customer.password') || empty($this->input('customer.password'))) {
            $randomPassword = Str::random(16);
            $this->merge([
                'customer' => array_merge($this->input('customer', []), [
                    'password' => $randomPassword,
                    'password_confirmation' => $randomPassword,
                ])
            ]);
        }
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            // Validate trial eligibility
            if ($this->boolean('start_trial')) {
                $plan = SubscriptionPlan::where('slug', $this->plan_slug)->first();
                if ($plan && !$plan->hasTrialPeriod()) {
                    $validator->errors()->add('start_trial', 'This plan does not offer a trial period.');
                }

                // Check if user with this email has already used trial for this plan
                $customerEmail = $this->input('customer.email');
                if ($customerEmail && $plan) {
                    $userModel = config('laravel-subscription.models.user', 'App\Models\User');
                    $existingUser = $userModel::where('email', $customerEmail)->first();

                    if ($existingUser) {
                        $hasUsedTrial = $existingUser->subscriptions()
                            ->where('plan_id', $plan->id)
                            ->whereNotNull('trial_ends_at')
                            ->exists();

                        if ($hasUsedTrial) {
                            $validator->errors()->add('start_trial', 'You have already used the trial period for this plan.');
                        }

                        // Check if user already has an active subscription
                        $existingSubscription = $existingUser->subscriptions()
                            ->whereIn('status', ['active', 'trial'])
                            ->exists();

                        if ($existingSubscription) {
                            $validator->errors()->add('customer.email', 'This email address already has an active subscription.');
                        }
                    }
                }
            }
        });
    }
}
