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
  </div>
</template>

<script setup>
import { ref } from 'vue'
import PlanCard from '../../Components/Subscription/PlanCard.vue'

const props = defineProps({
  plans: {
    type: Array,
    default: () => []
  }
})

const loadingPlanId = ref(null)

const selectPlan = (plan) => {
  if (loadingPlanId.value) return
  
  loadingPlanId.value = plan.id
  
  // Handle plan selection - redirect to checkout or subscription creation
  console.log('Selected plan:', plan)
  
  // Reset loading state after a delay (replace with actual navigation)
  setTimeout(() => {
    loadingPlanId.value = null
  }, 2000)
}
</script>
