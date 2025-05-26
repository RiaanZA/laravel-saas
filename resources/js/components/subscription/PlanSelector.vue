<template>
  <div class="plan-selector">
    <!-- Header -->
    <div class="text-center mb-12">
      <h2 class="text-3xl font-bold text-gray-900">Choose Your Plan</h2>
      <p class="mt-4 text-lg text-gray-600">Select the perfect plan for your needs</p>
      
      <!-- Billing Toggle -->
      <div class="mt-8 flex justify-center">
        <div class="relative bg-gray-100 rounded-lg p-1">
          <button
            @click="billingCycle = 'monthly'"
            :class="[
              'relative px-4 py-2 text-sm font-medium rounded-md transition-all duration-200',
              billingCycle === 'monthly' 
                ? 'bg-white text-gray-900 shadow-sm' 
                : 'text-gray-500 hover:text-gray-700'
            ]"
          >
            Monthly
          </button>
          <button
            @click="billingCycle = 'yearly'"
            :class="[
              'relative px-4 py-2 text-sm font-medium rounded-md transition-all duration-200',
              billingCycle === 'yearly' 
                ? 'bg-white text-gray-900 shadow-sm' 
                : 'text-gray-500 hover:text-gray-700'
            ]"
          >
            Yearly
            <span class="ml-1 inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
              Save 20%
            </span>
          </button>
        </div>
      </div>
    </div>

    <!-- Loading State -->
    <div v-if="loading" class="flex justify-center py-12">
      <div class="animate-spin rounded-full h-12 w-12 border-b-2 border-blue-600"></div>
    </div>

    <!-- Error State -->
    <div v-else-if="error" class="text-center py-12">
      <div class="text-red-600 mb-4">{{ error }}</div>
      <button
        @click="loadPlans"
        class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700"
      >
        Try Again
      </button>
    </div>

    <!-- Plans Grid -->
    <div v-else class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
      <PlanCard
        v-for="plan in filteredPlans"
        :key="plan.id"
        :plan="plan"
        :current-plan="currentPlan"
        :billing-cycle="billingCycle"
        :loading="selectedPlanId === plan.id && processing"
        @select="handlePlanSelect"
      />
    </div>

    <!-- Features Comparison -->
    <div v-if="showComparison" class="mt-16">
      <div class="text-center mb-8">
        <h3 class="text-2xl font-bold text-gray-900">Compare Features</h3>
        <p class="mt-2 text-gray-600">See what's included in each plan</p>
      </div>
      
      <FeatureComparison :plans="filteredPlans" />
    </div>

    <!-- FAQ Section -->
    <div class="mt-16">
      <div class="text-center mb-8">
        <h3 class="text-2xl font-bold text-gray-900">Frequently Asked Questions</h3>
      </div>
      
      <div class="max-w-3xl mx-auto">
        <div class="space-y-6">
          <div v-for="faq in faqs" :key="faq.id" class="border-b border-gray-200 pb-6">
            <button
              @click="toggleFaq(faq.id)"
              class="flex w-full justify-between items-center text-left"
            >
              <span class="text-lg font-medium text-gray-900">{{ faq.question }}</span>
              <svg
                :class="['h-5 w-5 text-gray-500 transition-transform', { 'rotate-180': openFaqs.includes(faq.id) }]"
                fill="none"
                stroke="currentColor"
                viewBox="0 0 24 24"
              >
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
              </svg>
            </button>
            <div v-show="openFaqs.includes(faq.id)" class="mt-4 text-gray-600">
              {{ faq.answer }}
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import PlanCard from './PlanCard.vue'
import FeatureComparison from './FeatureComparison.vue'

// Props
const props = defineProps({
  initialPlans: Array,
  currentPlan: Object,
  showComparison: {
    type: Boolean,
    default: true,
  },
})

// Emits
const emit = defineEmits(['plan-selected'])

// Reactive data
const loading = ref(false)
const error = ref(null)
const processing = ref(false)
const selectedPlanId = ref(null)
const plans = ref(props.initialPlans || [])
const billingCycle = ref('monthly')
const openFaqs = ref([])

// Computed
const filteredPlans = computed(() => {
  return plans.value.filter(plan => plan.billing_cycle === billingCycle.value)
})

// FAQ data
const faqs = ref([
  {
    id: 1,
    question: 'Can I change my plan at any time?',
    answer: 'Yes, you can upgrade or downgrade your plan at any time. Changes take effect immediately and we\'ll prorate the billing accordingly.'
  },
  {
    id: 2,
    question: 'What happens if I exceed my usage limits?',
    answer: 'We\'ll notify you when you\'re approaching your limits. If you exceed them, some features may be temporarily restricted until you upgrade or your billing period resets.'
  },
  {
    id: 3,
    question: 'Do you offer refunds?',
    answer: 'We offer a 30-day money-back guarantee for all new subscriptions. If you\'re not satisfied, contact our support team for a full refund.'
  },
  {
    id: 4,
    question: 'Can I cancel my subscription?',
    answer: 'Yes, you can cancel your subscription at any time. You\'ll continue to have access to your plan features until the end of your current billing period.'
  },
  {
    id: 5,
    question: 'Is there a free trial?',
    answer: 'Yes, most of our plans come with a free trial period. You can explore all features without any commitment during the trial.'
  }
])

// Methods
const loadPlans = async () => {
  if (props.initialPlans) return
  
  loading.value = true
  error.value = null
  
  try {
    const response = await fetch('/api/subscription/public/plans')
    const data = await response.json()
    
    if (!response.ok) {
      throw new Error(data.message || 'Failed to load plans')
    }
    
    plans.value = data.plans || data
  } catch (err) {
    error.value = err.message
  } finally {
    loading.value = false
  }
}

const handlePlanSelect = async (plan) => {
  selectedPlanId.value = plan.id
  processing.value = true
  
  try {
    emit('plan-selected', plan)
  } finally {
    processing.value = false
    selectedPlanId.value = null
  }
}

const toggleFaq = (faqId) => {
  const index = openFaqs.value.indexOf(faqId)
  if (index > -1) {
    openFaqs.value.splice(index, 1)
  } else {
    openFaqs.value.push(faqId)
  }
}

// Lifecycle
onMounted(() => {
  if (!props.initialPlans) {
    loadPlans()
  }
})
</script>

<style scoped>
.plan-selector {
  @apply max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-12;
}
</style>
