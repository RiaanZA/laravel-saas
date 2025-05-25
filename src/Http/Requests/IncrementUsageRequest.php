<?php

namespace RiaanZA\LaravelSubscription\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use RiaanZA\LaravelSubscription\Models\UserSubscription;

class IncrementUsageRequest extends FormRequest
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
            'feature_key' => 'required|string|max:255',
            'increment' => 'integer|min:1|max:1000',
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'feature_key.required' => 'Feature key is required.',
            'feature_key.string' => 'Feature key must be a string.',
            'increment.integer' => 'Increment must be an integer.',
            'increment.min' => 'Increment must be at least 1.',
            'increment.max' => 'Increment cannot exceed 1000.',
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        // Set default increment if not provided
        if (!$this->has('increment')) {
            $this->merge(['increment' => 1]);
        }
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $user = $this->user();
            
            if (!$user) {
                return;
            }

            // Check if user has an active subscription
            $subscription = $user->subscriptions()
                ->whereIn('status', ['active', 'trial'])
                ->latest()
                ->first();

            if (!$subscription) {
                $validator->errors()->add('subscription', 'No active subscription found.');
                return;
            }

            // Check if feature exists in the plan
            $featureExists = $subscription->plan->features()
                ->where('feature_key', $this->feature_key)
                ->exists();

            if (!$featureExists) {
                $validator->errors()->add('feature_key', 'Feature not available in your subscription plan.');
                return;
            }

            // Check if feature is numeric (can be incremented)
            $feature = $subscription->plan->features()
                ->where('feature_key', $this->feature_key)
                ->first();

            if ($feature && $feature->feature_type !== 'numeric') {
                $validator->errors()->add('feature_key', 'This feature type cannot be incremented.');
            }
        });
    }
}
