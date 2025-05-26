<?php

namespace RiaanZA\LaravelSubscription\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use RiaanZA\LaravelSubscription\Models\SubscriptionPlan;

class ProcessPaymentRequest extends FormRequest
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
            ],
            'payment_method_id' => 'string|nullable',
            'save_payment_method' => 'boolean',
            'start_trial' => 'boolean',
            
            // Billing information
            'billing_details' => 'required|array',
            'billing_details.name' => 'required|string|max:255',
            'billing_details.email' => 'required|email|max:255',
            'billing_details.phone' => 'string|nullable|max:20',
            
            // Billing address
            'billing_address' => 'required|array',
            'billing_address.line1' => 'required|string|max:255',
            'billing_address.line2' => 'string|nullable|max:255',
            'billing_address.city' => 'required|string|max:100',
            'billing_address.state' => 'string|nullable|max:100',
            'billing_address.postal_code' => 'required|string|max:20',
            'billing_address.country' => 'required|string|size:2', // ISO country code
            
            // Payment method details (for new payment methods)
            'payment_method' => 'array|nullable',
            'payment_method.type' => 'string|in:card,bank_account',
            'payment_method.card' => 'array|nullable|required_if:payment_method.type,card',
            'payment_method.card.number' => 'string|nullable',
            'payment_method.card.exp_month' => 'integer|between:1,12|nullable',
            'payment_method.card.exp_year' => 'integer|min:' . date('Y') . '|nullable',
            'payment_method.card.cvc' => 'string|size:3|nullable',
            'payment_method.card.name' => 'string|max:255|nullable',
            
            // Additional options
            'return_url' => 'url|nullable',
            'metadata' => 'array|nullable',
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
            'billing_details.name.required' => 'Billing name is required.',
            'billing_details.email.required' => 'Billing email is required.',
            'billing_details.email.email' => 'Please provide a valid email address.',
            'billing_address.line1.required' => 'Billing address is required.',
            'billing_address.city.required' => 'City is required.',
            'billing_address.postal_code.required' => 'Postal code is required.',
            'billing_address.country.required' => 'Country is required.',
            'billing_address.country.size' => 'Country must be a valid 2-letter country code.',
            'payment_method.card.exp_month.between' => 'Expiry month must be between 1 and 12.',
            'payment_method.card.exp_year.min' => 'Expiry year cannot be in the past.',
            'payment_method.card.cvc.size' => 'CVC must be 3 digits.',
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        // Set default values
        $this->merge([
            'save_payment_method' => $this->boolean('save_payment_method', false),
            'start_trial' => $this->boolean('start_trial', false),
        ]);

        // Ensure billing details include user info if not provided
        $user = $this->user();
        if ($user && !$this->has('billing_details.email')) {
            $billingDetails = $this->input('billing_details', []);
            $billingDetails['email'] = $billingDetails['email'] ?? $user->email;
            $billingDetails['name'] = $billingDetails['name'] ?? $user->name;
            
            $this->merge(['billing_details' => $billingDetails]);
        }
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            // Validate plan availability
            $plan = SubscriptionPlan::where('slug', $this->plan_slug)->first();
            if ($plan && !$plan->is_active) {
                $validator->errors()->add('plan_slug', 'The selected plan is not available.');
            }

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
            if ($this->boolean('start_trial') && $plan) {
                if (!$plan->hasTrialPeriod()) {
                    $validator->errors()->add('start_trial', 'This plan does not offer a trial period.');
                }

                // Check if user has already used trial
                if ($user) {
                    $hasUsedTrial = $user->subscriptions()
                        ->where('plan_id', $plan->id)
                        ->whereNotNull('trial_ends_at')
                        ->exists();

                    if ($hasUsedTrial) {
                        $validator->errors()->add('start_trial', 'You have already used the trial period for this plan.');
                    }
                }
            }

            // Validate payment method requirements
            if (!$this->payment_method_id && !$this->has('payment_method')) {
                $validator->errors()->add('payment_method', 'Please provide a payment method.');
            }

            // Validate card details if providing new card
            if ($this->has('payment_method.card')) {
                $card = $this->input('payment_method.card');
                
                if (empty($card['number'])) {
                    $validator->errors()->add('payment_method.card.number', 'Card number is required.');
                }
                
                if (empty($card['exp_month'])) {
                    $validator->errors()->add('payment_method.card.exp_month', 'Expiry month is required.');
                }
                
                if (empty($card['exp_year'])) {
                    $validator->errors()->add('payment_method.card.exp_year', 'Expiry year is required.');
                }
                
                if (empty($card['cvc'])) {
                    $validator->errors()->add('payment_method.card.cvc', 'CVC is required.');
                }
            }
        });
    }
}
