<template>
  <div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
    <!-- Header -->
    <div class="px-6 py-4 border-b border-gray-200">
      <div class="flex items-center justify-between">
        <h2 class="text-lg font-semibold text-gray-900">Current Subscription</h2>
        <StatusBadge :status="subscription?.status" />
      </div>
    </div>

    <!-- Content -->
    <div class="px-6 py-6">
      <div v-if="!subscription" class="text-center py-8">
        <div class="mx-auto h-12 w-12 text-gray-400">
          <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6" />
          </svg>
        </div>
        <h3 class="mt-2 text-sm font-medium text-gray-900">No Active Subscription</h3>
        <p class="mt-1 text-sm text-gray-500">Get started by choosing a plan that fits your needs.</p>
        <div class="mt-6">
          <button
            @click="$inertia.visit(route('subscription.plans.index'))"
            class="inline-flex items-center px-4 py-2 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500"
          >
            View Plans
          </button>
        </div>
      </div>

      <div v-else class="space-y-6">
        <!-- Plan Information -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
          <div>
            <h3 class="text-sm font-medium text-gray-500 uppercase tracking-wide">Current Plan</h3>
            <div class="mt-2 flex items-center">
              <span class="text-2xl font-bold text-gray-900">{{ subscription.plan.name }}</span>
              <span v-if="subscription.plan.is_popular" class="ml-2 inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800">
                Popular
              </span>
            </div>
            <p v-if="subscription.plan.description" class="mt-1 text-sm text-gray-600">
              {{ subscription.plan.description }}
            </p>
          </div>

          <div>
            <h3 class="text-sm font-medium text-gray-500 uppercase tracking-wide">Billing</h3>
            <div class="mt-2">
              <span class="text-2xl font-bold text-gray-900">{{ subscription.formatted_amount }}</span>
              <span class="text-sm text-gray-500">/ {{ subscription.plan.billing_cycle }}</span>
            </div>
          </div>
        </div>

        <!-- Status Information -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
          <div v-if="subscription.on_trial">
            <h3 class="text-sm font-medium text-gray-500 uppercase tracking-wide">Trial Period</h3>
            <div class="mt-2">
              <span class="text-lg font-semibold text-blue-600">{{ subscription.trial_days_remaining }} days left</span>
            </div>
            <p class="mt-1 text-xs text-gray-500">Trial ends {{ formatDate(subscription.trial_ends_at) }}</p>
          </div>

          <div>
            <h3 class="text-sm font-medium text-gray-500 uppercase tracking-wide">Next Billing</h3>
            <div class="mt-2">
              <span class="text-lg font-semibold text-gray-900">{{ formatDate(subscription.next_billing_date) }}</span>
            </div>
            <p v-if="subscription.days_remaining" class="mt-1 text-xs text-gray-500">
              {{ subscription.days_remaining }} days remaining
            </p>
          </div>

          <div v-if="subscription.is_cancelled">
            <h3 class="text-sm font-medium text-gray-500 uppercase tracking-wide">Cancellation</h3>
            <div class="mt-2">
              <span class="text-lg font-semibold text-red-600">{{ formatDate(subscription.ends_at) }}</span>
            </div>
            <p class="mt-1 text-xs text-gray-500">Access ends on this date</p>
          </div>
        </div>

        <!-- Progress Bar for Trial/Billing Period -->
        <div v-if="showProgressBar" class="space-y-2">
          <div class="flex justify-between text-sm">
            <span class="text-gray-500">{{ progressLabel }}</span>
            <span class="text-gray-900">{{ progressPercentage }}%</span>
          </div>
          <div class="w-full bg-gray-200 rounded-full h-2">
            <div 
              class="h-2 rounded-full transition-all duration-300"
              :class="progressBarClass"
              :style="{ width: `${progressPercentage}%` }"
            ></div>
          </div>
        </div>

        <!-- Actions -->
        <div class="flex flex-wrap gap-3 pt-4 border-t border-gray-200">
          <button
            v-if="canChangePlan"
            @click="$emit('change-plan')"
            class="inline-flex items-center px-3 py-2 border border-gray-300 shadow-sm text-sm leading-4 font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500"
          >
            Change Plan
          </button>

          <button
            v-if="canCancel"
            @click="$emit('cancel')"
            class="inline-flex items-center px-3 py-2 border border-red-300 shadow-sm text-sm leading-4 font-medium rounded-md text-red-700 bg-white hover:bg-red-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500"
          >
            Cancel Subscription
          </button>

          <button
            v-if="canResume"
            @click="$emit('resume')"
            class="inline-flex items-center px-3 py-2 border border-transparent shadow-sm text-sm leading-4 font-medium rounded-md text-white bg-green-600 hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500"
          >
            Resume Subscription
          </button>

          <button
            @click="$inertia.visit(route('subscription.billing'))"
            class="inline-flex items-center px-3 py-2 border border-gray-300 shadow-sm text-sm leading-4 font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500"
          >
            View Billing
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed } from 'vue'
import StatusBadge from './StatusBadge.vue'

// Props
const props = defineProps({
  subscription: Object,
})

// Emits
const emit = defineEmits(['cancel', 'resume', 'change-plan'])

// Computed
const canChangePlan = computed(() => {
  return props.subscription && ['active', 'trial'].includes(props.subscription.status)
})

const canCancel = computed(() => {
  return props.subscription && ['active', 'trial'].includes(props.subscription.status) && !props.subscription.is_cancelled
})

const canResume = computed(() => {
  return props.subscription && props.subscription.status === 'cancelled' && !props.subscription.is_expired
})

const showProgressBar = computed(() => {
  return props.subscription && (props.subscription.on_trial || props.subscription.days_remaining)
})

const progressLabel = computed(() => {
  if (!props.subscription) return ''
  
  if (props.subscription.on_trial) {
    return 'Trial Progress'
  }
  
  return 'Billing Period'
})

const progressPercentage = computed(() => {
  if (!props.subscription) return 0
  
  if (props.subscription.on_trial) {
    const totalDays = props.subscription.plan.trial_days
    const remainingDays = props.subscription.trial_days_remaining
    return Math.round(((totalDays - remainingDays) / totalDays) * 100)
  }
  
  // Calculate billing period progress
  const start = new Date(props.subscription.current_period_start)
  const end = new Date(props.subscription.current_period_end)
  const now = new Date()
  
  const total = end.getTime() - start.getTime()
  const elapsed = now.getTime() - start.getTime()
  
  return Math.round((elapsed / total) * 100)
})

const progressBarClass = computed(() => {
  if (!props.subscription) return 'bg-gray-400'
  
  if (props.subscription.on_trial) {
    return 'bg-blue-600'
  }
  
  if (props.subscription.is_cancelled) {
    return 'bg-red-600'
  }
  
  return 'bg-green-600'
})

// Methods
const formatDate = (dateString) => {
  if (!dateString) return 'N/A'
  
  const date = new Date(dateString)
  return date.toLocaleDateString('en-US', {
    year: 'numeric',
    month: 'long',
    day: 'numeric'
  })
}
</script>
