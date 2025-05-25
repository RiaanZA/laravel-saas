<template>
  <div class="bg-white rounded-lg shadow-md border border-gray-200 overflow-hidden">
    <!-- Header -->
    <div class="px-6 py-4 bg-gray-50 border-b border-gray-200">
      <h3 class="text-lg font-semibold text-gray-900">Usage & Limits</h3>
    </div>

    <div class="p-6">
      <!-- Usage Overview -->
      <div v-if="Object.keys(usageStats).length === 0" class="text-center py-8">
        <div class="text-gray-400 mb-2">
          <svg class="w-12 h-12 mx-auto" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
          </svg>
        </div>
        <p class="text-gray-500">No usage data available</p>
      </div>

      <div v-else class="space-y-6">
        <!-- Feature Usage Items -->
        <div 
          v-for="(stat, featureKey) in usageStats" 
          :key="featureKey"
          class="border border-gray-200 rounded-lg p-4"
        >
          <div class="flex items-center justify-between mb-3">
            <div>
              <h4 class="text-sm font-medium text-gray-900">{{ stat.feature_name }}</h4>
              <p class="text-xs text-gray-500">{{ featureKey }}</p>
            </div>
            <div class="text-right">
              <p class="text-sm font-semibold text-gray-900">
                {{ formatUsage(stat.current_usage, stat.feature_type) }}
                <span v-if="!stat.is_unlimited" class="text-gray-500">
                  / {{ formatLimit(stat.limit, stat.feature_type) }}
                </span>
              </p>
              <p v-if="stat.is_unlimited" class="text-xs text-green-600 font-medium">
                Unlimited
              </p>
            </div>
          </div>

          <!-- Progress Bar for Numeric Features -->
          <div v-if="stat.feature_type === 'numeric' && !stat.is_unlimited">
            <div class="w-full bg-gray-200 rounded-full h-2 mb-2">
              <div 
                class="h-2 rounded-full transition-all duration-300"
                :class="getProgressBarColor(stat.percentage_used)"
                :style="{ width: Math.min(stat.percentage_used, 100) + '%' }"
              ></div>
            </div>
            <div class="flex justify-between text-xs text-gray-500">
              <span>{{ stat.percentage_used.toFixed(1) }}% used</span>
              <span v-if="stat.remaining !== 'unlimited'">
                {{ stat.remaining }} remaining
              </span>
            </div>
          </div>

          <!-- Boolean Feature Status -->
          <div v-else-if="stat.feature_type === 'boolean'" class="flex items-center">
            <div 
              class="w-2 h-2 rounded-full mr-2"
              :class="stat.current_usage ? 'bg-green-500' : 'bg-gray-300'"
            ></div>
            <span class="text-sm text-gray-600">
              {{ stat.current_usage ? 'Enabled' : 'Disabled' }}
            </span>
          </div>

          <!-- Warning Messages -->
          <div v-if="stat.is_over_limit" class="mt-3 p-2 bg-red-50 border border-red-200 rounded">
            <div class="flex items-center">
              <svg class="w-4 h-4 text-red-500 mr-2" fill="currentColor" viewBox="0 0 20 20">
                <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd" />
              </svg>
              <span class="text-sm text-red-700 font-medium">Limit exceeded</span>
            </div>
          </div>
          
          <div v-else-if="stat.is_approaching_limit" class="mt-3 p-2 bg-yellow-50 border border-yellow-200 rounded">
            <div class="flex items-center">
              <svg class="w-4 h-4 text-yellow-500 mr-2" fill="currentColor" viewBox="0 0 20 20">
                <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd" />
              </svg>
              <span class="text-sm text-yellow-700 font-medium">Approaching limit</span>
            </div>
          </div>
        </div>

        <!-- Usage Summary -->
        <div class="border-t border-gray-200 pt-4">
          <div class="grid grid-cols-2 md:grid-cols-4 gap-4 text-center">
            <div>
              <p class="text-2xl font-bold text-gray-900">{{ totalFeatures }}</p>
              <p class="text-xs text-gray-500">Total Features</p>
            </div>
            <div>
              <p class="text-2xl font-bold text-green-600">{{ activeFeatures }}</p>
              <p class="text-xs text-gray-500">Active</p>
            </div>
            <div>
              <p class="text-2xl font-bold text-yellow-600">{{ approachingLimitFeatures }}</p>
              <p class="text-xs text-gray-500">Near Limit</p>
            </div>
            <div>
              <p class="text-2xl font-bold text-red-600">{{ overLimitFeatures }}</p>
              <p class="text-xs text-gray-500">Over Limit</p>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed } from 'vue'

const props = defineProps({
  usageStats: {
    type: Object,
    default: () => ({})
  }
})

const totalFeatures = computed(() => Object.keys(props.usageStats).length)

const activeFeatures = computed(() => {
  return Object.values(props.usageStats).filter(stat => 
    stat.current_usage > 0 || stat.feature_type === 'boolean'
  ).length
})

const approachingLimitFeatures = computed(() => {
  return Object.values(props.usageStats).filter(stat => 
    stat.is_approaching_limit && !stat.is_over_limit
  ).length
})

const overLimitFeatures = computed(() => {
  return Object.values(props.usageStats).filter(stat => stat.is_over_limit).length
})

const formatUsage = (usage, type) => {
  switch (type) {
    case 'numeric':
      return new Intl.NumberFormat().format(usage)
    case 'boolean':
      return usage ? 'Yes' : 'No'
    default:
      return usage
  }
}

const formatLimit = (limit, type) => {
  if (limit === 'unlimited') {
    return 'Unlimited'
  }
  
  switch (type) {
    case 'numeric':
      return new Intl.NumberFormat().format(limit)
    case 'boolean':
      return limit ? 'Yes' : 'No'
    default:
      return limit
  }
}

const getProgressBarColor = (percentage) => {
  if (percentage >= 100) {
    return 'bg-red-500'
  } else if (percentage >= 80) {
    return 'bg-yellow-500'
  } else if (percentage >= 60) {
    return 'bg-blue-500'
  } else {
    return 'bg-green-500'
  }
}
</script>
