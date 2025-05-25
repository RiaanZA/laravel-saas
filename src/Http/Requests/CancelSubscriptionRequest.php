<?php

namespace RiaanZA\LaravelSubscription\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CancelSubscriptionRequest extends FormRequest
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
            'immediately' => 'boolean',
            'cancellation_reason' => 'string|nullable|max:500',
            'feedback' => 'string|nullable|max:1000',
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'cancellation_reason.max' => 'The cancellation reason must not exceed 500 characters.',
            'feedback.max' => 'The feedback must not exceed 1000 characters.',
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        // Set default values
        $this->merge([
            'immediately' => $this->boolean('immediately', false),
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
                // Check if subscription can be cancelled
                if (!in_array($subscription->status, ['active', 'trial'])) {
                    $validator->errors()->add('subscription', 'Only active or trial subscriptions can be cancelled.');
                }

                // Check if user owns the subscription
                if ($subscription->user_id !== $this->user()->id) {
                    $validator->errors()->add('subscription', 'You can only cancel your own subscription.');
                }

                // Check if subscription is already cancelled
                if ($subscription->isCancelled()) {
                    $validator->errors()->add('subscription', 'This subscription is already cancelled.');
                }
            }
        });
    }
}
