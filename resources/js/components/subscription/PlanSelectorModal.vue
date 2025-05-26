<template>
  <TransitionRoot as="template" :show="true">
    <Dialog as="div" class="relative z-50" @close="$emit('close')">
      <TransitionChild
        as="template"
        enter="ease-out duration-300"
        enter-from="opacity-0"
        enter-to="opacity-100"
        leave="ease-in duration-200"
        leave-from="opacity-100"
        leave-to="opacity-0"
      >
        <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" />
      </TransitionChild>

      <div class="fixed inset-0 z-10 overflow-y-auto">
        <div class="flex min-h-full items-end justify-center p-4 text-center sm:items-center sm:p-0">
          <TransitionChild
            as="template"
            enter="ease-out duration-300"
            enter-from="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
            enter-to="opacity-100 translate-y-0 sm:scale-100"
            leave="ease-in duration-200"
            leave-from="opacity-100 translate-y-0 sm:scale-100"
            leave-to="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
          >
            <DialogPanel class="relative transform overflow-hidden rounded-lg bg-white px-4 pb-4 pt-5 text-left shadow-xl transition-all sm:my-8 sm:w-full sm:max-w-4xl sm:p-6">
              <!-- Header -->
              <div class="flex items-center justify-between mb-6">
                <div>
                  <DialogTitle as="h3" class="text-lg font-semibold leading-6 text-gray-900">
                    Change Subscription Plan
                  </DialogTitle>
                  <p class="mt-1 text-sm text-gray-500">
                    Select a new plan for your subscription
                  </p>
                </div>
                <button
                  @click="$emit('close')"
                  class="rounded-md bg-white text-gray-400 hover:text-gray-500 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2"
                >
                  <span class="sr-only">Close</span>
                  <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                  </svg>
                </button>
              </div>

              <!-- Current Plan Info -->
              <div v-if="currentPlan" class="mb-6 p-4 bg-blue-50 border border-blue-200 rounded-lg">
                <div class="flex items-center">
                  <svg class="h-5 w-5 text-blue-400 mr-2" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd" />
                  </svg>
                  <span class="text-sm text-blue-800">
                    Currently on <strong>{{ currentPlan.name }}</strong> plan ({{ formatPrice(currentPlan.price) }}/{{ currentPlan.billing_cycle }})
                  </span>
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
              <div v-else class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4 max-h-96 overflow-y-auto">
                <div
                  v-for="plan in availablePlans"
                  :key="plan.id"
                  :class="[
                    'relative border-2 rounded-lg p-4 cursor-pointer transition-all duration-200',
                    selectedPlan?.id === plan.id 
                      ? 'border-blue-500 bg-blue-50' 
                      : 'border-gray-200 hover:border-gray-300',
                    plan.is_popular ? 'ring-2 ring-blue-500' : ''
                  ]"
                  @click="selectPlan(plan)"
                >
                  <!-- Popular Badge -->
                  <div v-if="plan.is_popular" class="absolute -top-2 left-1/2 transform -translate-x-1/2">
                    <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-blue-600 text-white">
                      Popular
                    </span>
                  </div>

                  <!-- Plan Content -->
                  <div class="text-center">
                    <h4 class="text-lg font-semibold text-gray-900">{{ plan.name }}</h4>
                    <div class="mt-2">
                      <span class="text-2xl font-bold text-gray-900">{{ formatPrice(plan.price) }}</span>
                      <span class="text-sm text-gray-500">/ {{ plan.billing_cycle }}</span>
                    </div>
                    
                    <!-- Change Type Badge -->
                    <div class="mt-2">
                      <span 
                        v-if="getChangeType(plan) === 'upgrade'"
                        class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-green-100 text-green-800"
                      >
                        Upgrade
                      </span>
                      <span 
                        v-else-if="getChangeType(plan) === 'downgrade'"
                        class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800"
                      >
                        Downgrade
                      </span>
                    </div>

                    <!-- Key Features -->
                    <div class="mt-3 text-left">
                      <ul class="text-xs text-gray-600 space-y-1">
                        <li v-for="feature in plan.key_features" :key="feature" class="flex items-center">
                          <svg class="h-3 w-3 text-green-500 mr-1" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd" />
                          </svg>
                          {{ feature }}
                        </li>
                      </ul>
                    </div>
                  </div>

                  <!-- Selection Indicator -->
                  <div v-if="selectedPlan?.id === plan.id" class="absolute top-2 right-2">
                    <svg class="h-5 w-5 text-blue-600" fill="currentColor" viewBox="0 0 20 20">
                      <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                    </svg>
                  </div>
                </div>
              </div>

              <!-- Proration Notice -->
              <div v-if="selectedPlan && showProrationNotice" class="mt-6 p-4 bg-yellow-50 border border-yellow-200 rounded-lg">
                <div class="flex">
                  <svg class="h-5 w-5 text-yellow-400 mr-2" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd" />
                  </svg>
                  <div class="text-sm text-yellow-800">
                    <p class="font-medium">Billing Adjustment</p>
                    <p class="mt-1">
                      {{ prorationMessage }}
                    </p>
                  </div>
                </div>
              </div>

              <!-- Actions -->
              <div class="mt-6 flex justify-end space-x-3">
                <button
                  @click="$emit('close')"
                  class="inline-flex items-center px-4 py-2 border border-gray-300 shadow-sm text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500"
                >
                  Cancel
                </button>
                <button
                  @click="confirmPlanChange"
                  :disabled="!selectedPlan || processing"
                  class="inline-flex items-center px-4 py-2 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 disabled:opacity-50 disabled:cursor-not-allowed"
                >
                  <span v-if="processing" class="flex items-center">
                    <svg class="animate-spin -ml-1 mr-2 h-4 w-4" fill="none" viewBox="0 0 24 24">
                      <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                      <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                    Processing...
                  </span>
                  <span v-else>
                    {{ selectedPlan ? `Change to ${selectedPlan.name}` : 'Select a Plan' }}
                  </span>
                </button>
              </div>
            </DialogPanel>
          </TransitionChild>
        </div>
      </div>
    </Dialog>
  </TransitionRoot>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { Dialog, DialogPanel, DialogTitle, TransitionChild, TransitionRoot } from '@headlessui/vue'

// Props
const props = defineProps({
  currentPlan: Object,
})

// Emits
const emit = defineEmits(['close', 'plan-selected'])

// Reactive data
const loading = ref(false)
const processing = ref(false)
const error = ref(null)
const plans = ref([])
const selectedPlan = ref(null)

// Computed
const availablePlans = computed(() => {
  return plans.value.filter(plan => {
    return plan.is_active && (!props.currentPlan || plan.id !== props.currentPlan.id)
  })
})

const showProrationNotice = computed(() => {
  return selectedPlan.value && props.currentPlan
})

const prorationMessage = computed(() => {
  if (!selectedPlan.value || !props.currentPlan) return ''
  
  const changeType = getChangeType(selectedPlan.value)
  
  if (changeType === 'upgrade') {
    return 'You will be charged the prorated amount for the upgrade immediately. Your next billing date will remain the same.'
  } else if (changeType === 'downgrade') {
    return 'You will receive a prorated credit for the downgrade. The change will take effect immediately.'
  }
  
  return 'Your billing will be adjusted based on the plan change.'
})

// Methods
const loadPlans = async () => {
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

const selectPlan = (plan) => {
  selectedPlan.value = plan
}

const getChangeType = (plan) => {
  if (!props.currentPlan) return null
  
  if (plan.price > props.currentPlan.price) return 'upgrade'
  if (plan.price < props.currentPlan.price) return 'downgrade'
  return 'same'
}

const confirmPlanChange = () => {
  if (selectedPlan.value) {
    processing.value = true
    emit('plan-selected', selectedPlan.value)
  }
}

const formatPrice = (price) => {
  return new Intl.NumberFormat('en-US', {
    style: 'currency',
    currency: 'USD',
    minimumFractionDigits: 0,
    maximumFractionDigits: 2,
  }).format(price)
}

// Lifecycle
onMounted(() => {
  loadPlans()
})
</script>
