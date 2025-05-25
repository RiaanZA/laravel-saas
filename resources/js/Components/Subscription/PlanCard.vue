<template>
  <div 
    class="relative bg-white rounded-2xl shadow-lg border border-gray-200 overflow-hidden transition-all duration-300 hover:shadow-xl hover:scale-105"
    :class="{
      'ring-2 ring-blue-500 ring-offset-2': plan.is_popular,
      'border-blue-200': plan.is_popular
    }"
  >
    <!-- Popular Badge -->
    <div 
      v-if="plan.is_popular" 
      class="absolute top-0 right-0 bg-gradient-to-r from-blue-500 to-purple-600 text-white px-4 py-1 text-sm font-semibold rounded-bl-lg"
    >
      Most Popular
    </div>

    <div class="p-8">
      <!-- Plan Header -->
      <div class="text-center mb-8">
        <h3 class="text-2xl font-bold text-gray-900 mb-2">{{ plan.name }}</h3>
        <p class="text-gray-600 text-sm leading-relaxed">{{ plan.description }}</p>
      </div>

      <!-- Pricing -->
      <div class="text-center mb-8">
        <div class="flex items-baseline justify-center">
          <span class="text-5xl font-extrabold text-gray-900">{{ plan.formatted_price }}</span>
          <span class="text-xl text-gray-500 ml-1">/{{ plan.billing_cycle }}</span>
        </div>

        <div v-if="plan.has_trial_period" class="mt-3">
          <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-green-100 text-green-800">
            {{ plan.trial_days }} days free trial
          </span>
        </div>
      </div>

      <!-- Features -->
      <div class="mb-8">
        <h4 class="text-sm font-semibold text-gray-900 uppercase tracking-wide mb-4">What's included</h4>
        <ul class="space-y-3">
          <li 
            v-for="feature in plan.features" 
            :key="feature.key"
            class="flex items-start"
          >
            <svg class="flex-shrink-0 h-5 w-5 text-green-500 mt-0.5" fill="currentColor" viewBox="0 0 20 20">
              <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd" />
            </svg>
            <div class="ml-3">
              <span class="text-sm text-gray-700">{{ feature.name }}</span>
              <div class="text-xs text-gray-500">
                <span v-if="feature.is_unlimited">Unlimited</span>
                <span v-else>{{ feature.human_limit }}</span>
              </div>
            </div>
          </li>
        </ul>
      </div>

      <!-- CTA Button -->
      <div class="text-center">
        <button
          @click="$emit('select-plan', plan)"
          :disabled="loading"
          class="w-full py-3 px-6 rounded-lg font-semibold text-sm transition-all duration-200"
          :class="plan.is_popular 
            ? 'bg-gradient-to-r from-blue-500 to-purple-600 text-white hover:from-blue-600 hover:to-purple-700 shadow-lg hover:shadow-xl'
            : 'bg-gray-900 text-white hover:bg-gray-800 shadow-md hover:shadow-lg'"
        >
          <span v-if="loading">Processing...</span>
          <span v-else>
            {{ plan.has_trial_period ? `Start ${plan.trial_days} Day Trial` : 'Get Started' }}
          </span>
        </button>

        <p v-if="plan.has_trial_period" class="text-xs text-gray-500 mt-2">
          No credit card required for trial
        </p>
      </div>
    </div>
  </div>
</template>

<script setup>
const props = defineProps({
  plan: {
    type: Object,
    required: true
  },
  loading: {
    type: Boolean,
    default: false
  }
})

const emit = defineEmits(['select-plan'])
</script>
