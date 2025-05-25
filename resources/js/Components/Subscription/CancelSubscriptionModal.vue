<template>
  <div class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50">
    <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white">
      <!-- Header -->
      <div class="mt-3">
        <div class="flex items-center justify-between mb-4">
          <h3 class="text-lg font-semibold text-gray-900">Cancel Subscription</h3>
          <button
            @click="$emit('close')"
            class="text-gray-400 hover:text-gray-600"
          >
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
            </svg>
          </button>
        </div>

        <!-- Warning -->
        <div class="mb-6 p-4 bg-yellow-50 border border-yellow-200 rounded-lg">
          <div class="flex">
            <svg class="w-5 h-5 text-yellow-400 mr-3 mt-0.5" fill="currentColor" viewBox="0 0 20 20">
              <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd" />
            </svg>
            <div>
              <h4 class="text-sm font-medium text-yellow-800">Are you sure?</h4>
              <p class="text-sm text-yellow-700 mt-1">
                This action will cancel your subscription. You can continue using the service until 
                {{ formatDate(subscription.current_period_end) }}.
              </p>
            </div>
          </div>
        </div>

        <!-- Cancellation Options -->
        <div class="mb-6">
          <label class="block text-sm font-medium text-gray-700 mb-3">
            When would you like to cancel?
          </label>
          
          <div class="space-y-3">
            <label class="flex items-center">
              <input
                v-model="cancelImmediately"
                :value="false"
                type="radio"
                class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300"
              />
              <span class="ml-3 text-sm text-gray-700">
                At the end of current billing period ({{ formatDate(subscription.current_period_end) }})
              </span>
            </label>
            
            <label class="flex items-center">
              <input
                v-model="cancelImmediately"
                :value="true"
                type="radio"
                class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300"
              />
              <span class="ml-3 text-sm text-gray-700">
                Immediately (you will lose access right away)
              </span>
            </label>
          </div>
        </div>

        <!-- Reason -->
        <div class="mb-6">
          <label class="block text-sm font-medium text-gray-700 mb-2">
            Reason for cancelling (optional)
          </label>
          <select
            v-model="cancellationReason"
            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
          >
            <option value="">Select a reason...</option>
            <option value="too_expensive">Too expensive</option>
            <option value="not_using">Not using enough</option>
            <option value="missing_features">Missing features</option>
            <option value="switching_provider">Switching to another provider</option>
            <option value="temporary">Temporary cancellation</option>
            <option value="other">Other</option>
          </select>
        </div>

        <!-- Additional Comments -->
        <div class="mb-6">
          <label class="block text-sm font-medium text-gray-700 mb-2">
            Additional comments (optional)
          </label>
          <textarea
            v-model="additionalComments"
            rows="3"
            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
            placeholder="Tell us more about your decision..."
          ></textarea>
        </div>

        <!-- Actions -->
        <div class="flex justify-end space-x-3">
          <button
            @click="$emit('close')"
            :disabled="loading"
            class="px-4 py-2 text-sm font-medium text-gray-700 bg-gray-100 border border-gray-300 rounded-md hover:bg-gray-200 focus:outline-none focus:ring-2 focus:ring-gray-500 disabled:opacity-50"
          >
            Keep Subscription
          </button>
          
          <button
            @click="confirmCancellation"
            :disabled="loading"
            class="px-4 py-2 text-sm font-medium text-white bg-red-600 border border-transparent rounded-md hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-red-500 disabled:opacity-50"
          >
            <span v-if="loading" class="flex items-center">
              <LoadingSpinner size="sm" class="mr-2" />
              Cancelling...
            </span>
            <span v-else>
              {{ cancelImmediately ? 'Cancel Now' : 'Cancel at Period End' }}
            </span>
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref } from 'vue'
import LoadingSpinner from '../UI/LoadingSpinner.vue'

const props = defineProps({
  subscription: {
    type: Object,
    required: true
  }
})

const emit = defineEmits(['close', 'cancelled'])

const loading = ref(false)
const cancelImmediately = ref(false)
const cancellationReason = ref('')
const additionalComments = ref('')

const confirmCancellation = () => {
  loading.value = true
  
  const data = {
    immediately: cancelImmediately.value,
    reason: cancellationReason.value || null,
    comments: additionalComments.value || null
  }
  
  // Emit the cancellation data
  emit('cancelled', data)
  
  // Reset loading state
  setTimeout(() => {
    loading.value = false
  }, 1000)
}

const formatDate = (dateString) => {
  return new Date(dateString).toLocaleDateString('en-ZA', {
    year: 'numeric',
    month: 'long',
    day: 'numeric'
  })
}
</script>
