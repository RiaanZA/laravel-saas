<?php

namespace RiaanZA\LaravelSubscription\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use RiaanZA\LaravelSubscription\Models\SubscriptionPlan;

class UpdateSubscriptionRequest extends FormRequest
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
            'prorate' => 'boolean',
            'effective_date' => 'date|after_or_equal:today',
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'plan_slug.required' => 'Please select a new subscription plan.',
            'plan_slug.exists' => 'The selected plan does not exist.',
            'effective_date.after_or_equal' => 'The effective date must be today or in the future.',
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        // Set default values
        $this->merge([
            'prorate' => $this->boolean('prorate', true),
        ]);
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $subscription = $this->route('subscription');
            
            if ($subscription) {
                // Check if subscription can be updated
                if (!in_array($subscription->status, ['active', 'trial'])) {
                    $validator->errors()->add('subscription', 'Only active or trial subscriptions can be updated.');
                }

                // Check if trying to change to the same plan
                $newPlan = SubscriptionPlan::where('slug', $this->plan_slug)->first();
                if ($newPlan && $subscription->plan_id === $newPlan->id) {
                    $validator->errors()->add('plan_slug', 'You are already subscribed to this plan.');
                }

                // Check if user owns the subscription
                if ($subscription->user_id !== $this->user()->id) {
                    $validator->errors()->add('subscription', 'You can only update your own subscription.');
                }
            }
        });
    }
}
