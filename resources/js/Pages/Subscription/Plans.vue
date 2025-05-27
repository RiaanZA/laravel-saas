<template>
  <div class="min-h-screen bg-gray-50 py-12">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
      <!-- Header -->
      <div class="text-center mb-12">
        <h1 class="text-4xl font-bold text-gray-900 mb-4">
          Choose Your Plan
        </h1>
        <p class="text-xl text-gray-600 max-w-3xl mx-auto">
          Select the perfect plan for your needs. All plans include our core features with different usage limits.
        </p>
      </div>

      <!-- Plans Grid -->
      <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8 mb-12">
        <PlanCard
          v-for="plan in plans"
          :key="plan.id"
          :plan="plan"
          :loading="loadingPlanId === plan.id"
          @select-plan="selectPlan"
        />
      </div>
    </div>

    <!-- Error Notification -->
    <ErrorNotification
      :show="showError"
      :title="'Plan Selection Error'"
      :message="getErrorMessage()"
      :errors="getErrorList()"
      @close="clearError"
    />
  </div>
</template>

<script setup>
import { ref } from 'vue'
import { router } from '@inertiajs/vue3'
import PlanCard from '../../Components/Subscription/PlanCard.vue'
import ErrorNotification from '../../Components/UI/ErrorNotification.vue'
import { useErrorHandling } from '../../composables/useErrorHandling.js'

const props = defineProps({
  plans: {
    type: Array,
    default: () => []
  }
})

const loadingPlanId = ref(null)
const { showError, handleInertiaError, clearError, getErrorMessage, getErrorList } = useErrorHandling()

const selectPlan = (plan) => {
  if (loadingPlanId.value) return

  // Validate plan data
  if (!plan || !plan.slug) {
    handleInertiaError('Invalid plan selected. Please try again.')
    return
  }

  loadingPlanId.value = plan.id
  clearError() // Clear any previous errors

  // Navigate to checkout page for the selected plan
  router.visit(route('subscription.checkout', { planSlug: plan.slug }), {
    onFinish: () => {
      loadingPlanId.value = null
    },
    onError: (errors) => {
      loadingPlanId.value = null

      // Handle different types of errors
      if (errors.message) {
        handleInertiaError(errors.message)
      } else if (errors.error) {
        handleInertiaError(errors.error)
      } else if (typeof errors === 'object' && Object.keys(errors).length > 0) {
        handleInertiaError(errors, 'Failed to navigate to checkout. Please try again.')
      } else {
        handleInertiaError('Unable to proceed to checkout. Please check your internet connection and try again.')
      }
    }
  })
}
</script>
