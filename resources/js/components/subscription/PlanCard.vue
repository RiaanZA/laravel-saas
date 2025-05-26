<template>
  <div 
    :class="[
      'relative rounded-lg border-2 transition-all duration-200 hover:shadow-lg',
      isCurrentPlan ? 'border-blue-500 bg-blue-50' : 'border-gray-200 bg-white',
      plan.is_popular ? 'ring-2 ring-blue-500' : ''
    ]"
  >
    <!-- Popular Badge -->
    <div v-if="plan.is_popular" class="absolute -top-3 left-1/2 transform -translate-x-1/2">
      <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-blue-600 text-white">
        Most Popular
      </span>
    </div>

    <!-- Current Plan Badge -->
    <div v-if="isCurrentPlan" class="absolute -top-3 right-4">
      <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-green-600 text-white">
        Current Plan
      </span>
    </div>

    <div class="p-6">
      <!-- Plan Header -->
      <div class="text-center">
        <h3 class="text-2xl font-bold text-gray-900">{{ plan.name }}</h3>
        <p v-if="plan.description" class="mt-2 text-gray-600">{{ plan.description }}</p>
        
        <!-- Pricing -->
        <div class="mt-6">
          <div class="flex items-center justify-center">
            <span class="text-4xl font-bold text-gray-900">{{ formatPrice(plan.price) }}</span>
            <span class="text-lg text-gray-500 ml-1">/ {{ plan.billing_cycle }}</span>
          </div>
          
          <!-- Yearly Savings -->
          <div v-if="billingCycle === 'yearly' && yearlyDiscount" class="mt-2">
            <span class="text-sm text-green-600 font-medium">
              Save {{ formatPrice(yearlyDiscount) }} per year
            </span>
          </div>
          
          <!-- Trial Info -->
          <div v-if="plan.trial_days > 0" class="mt-2">
            <span class="text-sm text-blue-600 font-medium">
              {{ plan.trial_days }}-day free trial
            </span>
          </div>
        </div>
      </div>

      <!-- Features List -->
      <div class="mt-8">
        <h4 class="text-sm font-medium text-gray-900 uppercase tracking-wide">What's included</h4>
        <ul class="mt-4 space-y-3">
          <li v-for="feature in plan.features" :key="feature.id" class="flex items-start">
            <svg class="flex-shrink-0 h-5 w-5 text-green-500 mt-0.5" fill="currentColor" viewBox="0 0 20 20">
              <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd" />
            </svg>
            <div class="ml-3">
              <span class="text-sm font-medium text-gray-900">{{ feature.feature_name }}</span>
              <div class="text-sm text-gray-500">
                <span v-if="feature.is_unlimited">Unlimited</span>
                <span v-else-if="feature.feature_type === 'boolean' && feature.typed_limit">Included</span>
                <span v-else-if="feature.feature_type === 'numeric'">{{ feature.human_limit }}</span>
                <span v-else>{{ feature.human_limit }}</span>
              </div>
              <p v-if="feature.description" class="text-xs text-gray-400 mt-1">
                {{ feature.description }}
              </p>
            </div>
          </li>
        </ul>
      </div>

      <!-- Action Button -->
      <div class="mt-8">
        <button
          @click="handleSelect"
          :disabled="loading || isCurrentPlan"
          :class="[
            'w-full flex justify-center items-center px-4 py-3 border border-transparent text-sm font-medium rounded-md transition-colors duration-200',
            isCurrentPlan 
              ? 'bg-gray-100 text-gray-500 cursor-not-allowed'
              : plan.is_popular
                ? 'bg-blue-600 text-white hover:bg-blue-700 focus:ring-blue-500'
                : 'bg-gray-900 text-white hover:bg-gray-800 focus:ring-gray-500',
            'focus:outline-none focus:ring-2 focus:ring-offset-2'
          ]"
        >
          <span v-if="loading" class="flex items-center">
            <svg class="animate-spin -ml-1 mr-2 h-4 w-4 text-current" fill="none" viewBox="0 0 24 24">
              <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
              <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
            </svg>
            Processing...
          </span>
          <span v-else-if="isCurrentPlan">Current Plan</span>
          <span v-else-if="isUpgrade">Upgrade to {{ plan.name }}</span>
          <span v-else-if="isDowngrade">Downgrade to {{ plan.name }}</span>
          <span v-else>
            {{ plan.trial_days > 0 ? `Start ${plan.trial_days}-day trial` : `Choose ${plan.name}` }}
          </span>
        </button>
      </div>

      <!-- Additional Info -->
      <div class="mt-4 text-center">
        <p class="text-xs text-gray-500">
          <span v-if="plan.trial_days > 0">No credit card required for trial. </span>
          Cancel anytime.
        </p>
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed } from 'vue'

// Props
const props = defineProps({
  plan: {
    type: Object,
    required: true,
  },
  currentPlan: Object,
  billingCycle: {
    type: String,
    default: 'monthly',
  },
  loading: {
    type: Boolean,
    default: false,
  },
})

// Emits
const emit = defineEmits(['select'])

// Computed
const isCurrentPlan = computed(() => {
  return props.currentPlan && props.currentPlan.id === props.plan.id
})

const isUpgrade = computed(() => {
  if (!props.currentPlan || isCurrentPlan.value) return false
  return props.plan.price > props.currentPlan.price
})

const isDowngrade = computed(() => {
  if (!props.currentPlan || isCurrentPlan.value) return false
  return props.plan.price < props.currentPlan.price
})

const yearlyDiscount = computed(() => {
  if (props.billingCycle !== 'yearly') return 0
  
  // Calculate savings compared to monthly billing
  const monthlyPrice = props.plan.price
  const yearlyPrice = monthlyPrice * 12
  const discountedYearlyPrice = monthlyPrice * 10 // 20% discount
  
  return yearlyPrice - discountedYearlyPrice
})

// Methods
const formatPrice = (price) => {
  return new Intl.NumberFormat('en-US', {
    style: 'currency',
    currency: 'USD',
    minimumFractionDigits: 0,
    maximumFractionDigits: 2,
  }).format(price)
}

const handleSelect = () => {
  if (!props.loading && !isCurrentPlan.value) {
    emit('select', props.plan)
  }
}
</script>
