<template>
  <div class="min-h-screen bg-gray-50 flex flex-col justify-center py-12 sm:px-6 lg:px-8">
    <div class="sm:mx-auto sm:w-full sm:max-w-md">
      <div class="bg-white py-8 px-4 shadow sm:rounded-lg sm:px-10">
        <div class="text-center">
          <!-- Warning Icon -->
          <div class="mx-auto flex items-center justify-center h-12 w-12 rounded-full bg-yellow-100">
            <svg class="h-6 w-6 text-yellow-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.732-.833-2.5 0L4.268 16.5c-.77.833.192 2.5 1.732 2.5z" />
            </svg>
          </div>
          
          <h2 class="mt-6 text-2xl font-bold text-gray-900">
            Payment Cancelled
          </h2>
          
          <p class="mt-2 text-sm text-gray-600">
            {{ message }}
          </p>
          
          <div class="mt-8 space-y-4">
            <button
              v-if="planSlug"
              @click="retryPayment"
              class="w-full flex justify-center py-2 px-4 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500"
            >
              Try Again
            </button>
            
            <button
              @click="goToPlans"
              class="w-full flex justify-center py-2 px-4 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500"
            >
              View All Plans
            </button>
            
            <button
              @click="goToDashboard"
              class="w-full flex justify-center py-2 px-4 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500"
            >
              Go to Dashboard
            </button>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { router } from '@inertiajs/vue3'

const props = defineProps({
  message: {
    type: String,
    default: 'Payment was cancelled. You can try again anytime.'
  },
  planSlug: {
    type: String,
    default: null
  }
})

const retryPayment = () => {
  if (props.planSlug) {
    router.visit(route('subscription.checkout', { planSlug: props.planSlug }))
  } else {
    goToPlans()
  }
}

const goToPlans = () => {
  router.visit(route('subscription.plans.index'))
}

const goToDashboard = () => {
  router.visit(route('subscription.dashboard'))
}
</script>
