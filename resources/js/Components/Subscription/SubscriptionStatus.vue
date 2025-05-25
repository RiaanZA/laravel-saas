<template>
  <div class="bg-white rounded-lg shadow-md border border-gray-200 overflow-hidden">
    <!-- Header -->
    <div class="px-6 py-4 bg-gray-50 border-b border-gray-200">
      <h3 class="text-lg font-semibold text-gray-900">Subscription Status</h3>
    </div>

    <div class="p-6">
      <!-- Status Badge -->
      <div class="flex items-center justify-between mb-6">
        <div class="flex items-center">
          <div 
            class="w-3 h-3 rounded-full mr-3"
            :class="statusColor"
          ></div>
          <span class="text-sm font-medium text-gray-900">{{ statusText }}</span>
        </div>
        <span 
          class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium"
          :class="statusBadgeClasses"
        >
          {{ subscription.status }}
        </span>
      </div>

      <!-- Plan Information -->
      <div class="mb-6">
        <h4 class="text-sm font-medium text-gray-900 mb-2">Current Plan</h4>
        <div class="flex items-center justify-between">
          <div>
            <p class="text-lg font-semibold text-gray-900">{{ subscription.plan.name }}</p>
            <p class="text-sm text-gray-500">{{ subscription.formatted_amount }}/{{ subscription.plan.billing_cycle }}</p>
          </div>
          <button
            @click="$emit('change-plan')"
            class="text-blue-600 hover:text-blue-700 text-sm font-medium"
          >
            Change Plan
          </button>
        </div>
      </div>

      <!-- Trial Information -->
      <div v-if="subscription.on_trial" class="mb-6 p-4 bg-blue-50 rounded-lg border border-blue-200">
        <div class="flex items-center">
          <svg class="w-5 h-5 text-blue-500 mr-2" fill="currentColor" viewBox="0 0 20 20">
            <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd" />
          </svg>
          <div>
            <p class="text-sm font-medium text-blue-900">Trial Period Active</p>
            <p class="text-sm text-blue-700">
              {{ subscription.trial_days_remaining }} days remaining in your trial
            </p>
          </div>
        </div>
      </div>

      <!-- Billing Information -->
      <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
        <div>
          <h5 class="text-sm font-medium text-gray-900 mb-1">Next Billing Date</h5>
          <p class="text-sm text-gray-600">{{ formatDate(subscription.next_billing_date) }}</p>
        </div>
        <div>
          <h5 class="text-sm font-medium text-gray-900 mb-1">Days Remaining</h5>
          <p class="text-sm text-gray-600">{{ subscription.days_remaining }} days</p>
        </div>
      </div>

      <!-- Cancellation Information -->
      <div v-if="subscription.is_cancelled" class="mb-6 p-4 bg-yellow-50 rounded-lg border border-yellow-200">
        <div class="flex items-center">
          <svg class="w-5 h-5 text-yellow-500 mr-2" fill="currentColor" viewBox="0 0 20 20">
            <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd" />
          </svg>
          <div>
            <p class="text-sm font-medium text-yellow-900">Subscription Cancelled</p>
            <p class="text-sm text-yellow-700">
              Your subscription will end on {{ formatDate(subscription.ends_at) }}
            </p>
          </div>
        </div>
      </div>

      <!-- Action Buttons -->
      <div class="flex flex-col sm:flex-row gap-3">
        <button
          v-if="!subscription.is_cancelled"
          @click="$emit('cancel-subscription')"
          class="flex-1 px-4 py-2 border border-red-300 text-red-700 rounded-md hover:bg-red-50 transition-colors duration-200 text-sm font-medium"
        >
          Cancel Subscription
        </button>
        
        <button
          v-if="subscription.is_cancelled"
          @click="$emit('resume-subscription')"
          class="flex-1 px-4 py-2 bg-green-600 text-white rounded-md hover:bg-green-700 transition-colors duration-200 text-sm font-medium"
        >
          Resume Subscription
        </button>

        <button
          @click="$emit('view-billing')"
          class="flex-1 px-4 py-2 border border-gray-300 text-gray-700 rounded-md hover:bg-gray-50 transition-colors duration-200 text-sm font-medium"
        >
          View Billing History
        </button>
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed } from 'vue'

const props = defineProps({
  subscription: {
    type: Object,
    required: true
  }
})

const emit = defineEmits(['change-plan', 'cancel-subscription', 'resume-subscription', 'view-billing'])

const statusColor = computed(() => {
  switch (props.subscription.status) {
    case 'active':
      return 'bg-green-500'
    case 'trial':
      return 'bg-blue-500'
    case 'cancelled':
      return 'bg-yellow-500'
    case 'past_due':
      return 'bg-red-500'
    default:
      return 'bg-gray-500'
  }
})

const statusText = computed(() => {
  switch (props.subscription.status) {
    case 'active':
      return 'Active Subscription'
    case 'trial':
      return 'Trial Period'
    case 'cancelled':
      return 'Cancelled'
    case 'past_due':
      return 'Payment Past Due'
    default:
      return 'Unknown Status'
  }
})

const statusBadgeClasses = computed(() => {
  switch (props.subscription.status) {
    case 'active':
      return 'bg-green-100 text-green-800'
    case 'trial':
      return 'bg-blue-100 text-blue-800'
    case 'cancelled':
      return 'bg-yellow-100 text-yellow-800'
    case 'past_due':
      return 'bg-red-100 text-red-800'
    default:
      return 'bg-gray-100 text-gray-800'
  }
})

const formatDate = (dateString) => {
  return new Date(dateString).toLocaleDateString('en-ZA', {
    year: 'numeric',
    month: 'long',
    day: 'numeric'
  })
}
</script>
