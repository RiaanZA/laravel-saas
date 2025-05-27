<template>
  <div class="min-h-screen bg-gray-50 flex items-center justify-center py-12 px-4 sm:px-6 lg:px-8">
    <div class="max-w-md w-full space-y-8">
      <div class="text-center">
        <!-- Success Icon -->
        <div class="mx-auto flex items-center justify-center h-16 w-16 rounded-full bg-green-100 mb-6">
          <svg class="h-10 w-10 text-green-600" fill="currentColor" viewBox="0 0 20 20">
            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
          </svg>
        </div>

        <!-- Success Message -->
        <h1 class="text-3xl font-bold text-gray-900 mb-4">
          {{ subscription ? (subscription.on_trial ? 'Trial Started!' : 'Welcome Aboard!') : 'Success!' }}
        </h1>

        <p class="text-lg text-gray-600 mb-8">
          {{ message || (subscription ?
            (subscription.on_trial
              ? `Your ${subscription.trial_days_remaining}-day trial has started successfully.`
              : `Your subscription to ${subscription.plan_name} is now active.`)
            : 'Your request has been processed successfully.')
          }}
        </p>

        <!-- Error Message (if any) -->
        <div v-if="error" class="bg-yellow-50 border border-yellow-200 rounded-lg p-4 mb-6">
          <div class="flex">
            <div class="flex-shrink-0">
              <svg class="h-5 w-5 text-yellow-400" viewBox="0 0 20 20" fill="currentColor">
                <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd" />
              </svg>
            </div>
            <div class="ml-3">
              <p class="text-sm text-yellow-700">{{ error }}</p>
            </div>
          </div>
        </div>

        <!-- Subscription Details -->
        <div v-if="subscription" class="bg-white rounded-lg shadow-md border border-gray-200 p-6 mb-8">
          <h2 class="text-lg font-semibold text-gray-900 mb-4">Subscription Details</h2>

          <div class="space-y-3 text-sm">
            <div class="flex justify-between">
              <span class="text-gray-600">Plan:</span>
              <span class="font-medium text-gray-900">{{ subscription.plan_name }}</span>
            </div>

            <div class="flex justify-between">
              <span class="text-gray-600">Status:</span>
              <span
                class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium"
                :class="subscription.on_trial ? 'bg-blue-100 text-blue-800' : 'bg-green-100 text-green-800'"
              >
                {{ subscription.status }}
              </span>
            </div>

            <div v-if="subscription.on_trial" class="flex justify-between">
              <span class="text-gray-600">Trial ends:</span>
              <span class="font-medium text-gray-900">
                {{ subscription.trial_days_remaining }} days remaining
              </span>
            </div>

            <div class="flex justify-between">
              <span class="text-gray-600">Next billing:</span>
              <span class="font-medium text-gray-900">{{ subscription.next_billing_date }}</span>
            </div>

            <div v-if="!subscription.on_trial" class="flex justify-between">
              <span class="text-gray-600">Amount:</span>
              <span class="font-medium text-gray-900">{{ subscription.amount }}</span>
            </div>
          </div>
        </div>

        <!-- Next Steps -->
        <div class="bg-blue-50 border border-blue-200 rounded-lg p-6 mb-8">
          <h3 class="text-lg font-semibold text-blue-900 mb-3">What's Next?</h3>
          <ul class="text-sm text-blue-800 space-y-2 text-left">
            <li class="flex items-start">
              <svg class="w-4 h-4 text-blue-600 mr-2 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd" />
              </svg>
              <span>Explore all the features included in your plan</span>
            </li>
            <li class="flex items-start">
              <svg class="w-4 h-4 text-blue-600 mr-2 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd" />
              </svg>
              <span>Set up your account and customize your preferences</span>
            </li>
            <li class="flex items-start">
              <svg class="w-4 h-4 text-blue-600 mr-2 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd" />
              </svg>
              <span>Check out our getting started guide</span>
            </li>
            <li v-if="subscription.on_trial" class="flex items-start">
              <svg class="w-4 h-4 text-blue-600 mr-2 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd" />
              </svg>
              <span>Add a payment method before your trial ends</span>
            </li>
          </ul>
        </div>

        <!-- Action Buttons -->
        <div class="space-y-3">
          <button
            @click="goToDashboard"
            class="w-full bg-blue-600 text-white py-3 px-4 rounded-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 transition-colors duration-200 font-medium"
          >
            Go to Dashboard
          </button>

          <button
            @click="goToApp"
            class="w-full bg-white text-gray-700 py-3 px-4 rounded-md border border-gray-300 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-gray-500 focus:ring-offset-2 transition-colors duration-200 font-medium"
          >
            Start Using the App
          </button>
        </div>

        <!-- Support -->
        <div class="mt-8 text-center">
          <p class="text-sm text-gray-600">
            Need help getting started?
            <a href="#" class="text-blue-600 hover:text-blue-700 font-medium">
              Contact our support team
            </a>
          </p>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { router } from '@inertiajs/vue3'

const props = defineProps({
  subscription: {
    type: Object,
    default: null
  },
  message: {
    type: String,
    default: null
  },
  error: {
    type: String,
    default: null
  },
  subscriptionId: {
    type: [String, Number],
    default: null
  }
})

const goToDashboard = () => {
  router.visit(route('subscription.dashboard'))
}

const goToApp = () => {
  // Redirect to main application dashboard
  router.visit('/dashboard')
}
</script>
