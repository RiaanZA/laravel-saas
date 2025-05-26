<template>
  <div 
    :class="[
      'bg-white rounded-lg border-2 p-4 transition-all duration-200 hover:shadow-md cursor-pointer',
      borderColor
    ]"
    @click="$emit('view-details', feature)"
  >
    <!-- Header -->
    <div class="flex items-center justify-between mb-3">
      <h3 class="text-sm font-medium text-gray-900 truncate">{{ feature.name }}</h3>
      <div class="flex items-center space-x-1">
        <StatusIcon :status="featureStatus" />
        <button
          v-if="detailed"
          class="text-gray-400 hover:text-gray-600"
          @click.stop="$emit('view-details', feature)"
        >
          <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
          </svg>
        </button>
      </div>
    </div>

    <!-- Usage Display -->
    <div class="space-y-3">
      <!-- Boolean Feature -->
      <div v-if="feature.type === 'boolean'" class="flex items-center justify-between">
        <span class="text-sm text-gray-600">Status</span>
        <span :class="['text-sm font-medium', feature.is_enabled ? 'text-green-600' : 'text-gray-400']">
          {{ feature.is_enabled ? 'Enabled' : 'Disabled' }}
        </span>
      </div>

      <!-- Numeric Feature -->
      <div v-else-if="feature.type === 'numeric'" class="space-y-2">
        <!-- Usage Numbers -->
        <div class="flex items-center justify-between">
          <span class="text-sm text-gray-600">Usage</span>
          <span class="text-sm font-medium text-gray-900">
            {{ formatNumber(feature.current_usage) }}
            <span v-if="!feature.is_unlimited" class="text-gray-500">
              / {{ feature.is_unlimited ? '∞' : formatNumber(feature.limit) }}
            </span>
          </span>
        </div>

        <!-- Progress Bar -->
        <div v-if="!feature.is_unlimited" class="space-y-1">
          <div class="w-full bg-gray-200 rounded-full h-2">
            <div 
              :class="['h-2 rounded-full transition-all duration-300', progressBarColor]"
              :style="{ width: `${Math.min(feature.percentage_used || 0, 100)}%` }"
            ></div>
          </div>
          <div class="flex justify-between text-xs text-gray-500">
            <span>{{ Math.round(feature.percentage_used || 0) }}% used</span>
            <span v-if="feature.remaining !== 'unlimited'">
              {{ formatNumber(feature.remaining) }} remaining
            </span>
          </div>
        </div>

        <!-- Unlimited Badge -->
        <div v-else class="flex justify-center">
          <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-green-100 text-green-800">
            Unlimited
          </span>
        </div>
      </div>

      <!-- Text Feature -->
      <div v-else class="flex items-center justify-between">
        <span class="text-sm text-gray-600">Value</span>
        <span class="text-sm font-medium text-gray-900 truncate max-w-24">
          {{ feature.limit || 'N/A' }}
        </span>
      </div>
    </div>

    <!-- Description -->
    <div v-if="feature.description && detailed" class="mt-3 pt-3 border-t border-gray-100">
      <p class="text-xs text-gray-500">{{ feature.description }}</p>
    </div>

    <!-- Alert Messages -->
    <div v-if="feature.is_over_limit" class="mt-3 p-2 bg-red-50 border border-red-200 rounded">
      <div class="flex items-center">
        <svg class="h-4 w-4 text-red-400 mr-2" fill="currentColor" viewBox="0 0 20 20">
          <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd" />
        </svg>
        <span class="text-xs text-red-700 font-medium">Limit exceeded</span>
      </div>
    </div>

    <div v-else-if="feature.is_near_limit" class="mt-3 p-2 bg-yellow-50 border border-yellow-200 rounded">
      <div class="flex items-center">
        <svg class="h-4 w-4 text-yellow-400 mr-2" fill="currentColor" viewBox="0 0 20 20">
          <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd" />
        </svg>
        <span class="text-xs text-yellow-700 font-medium">Approaching limit</span>
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed } from 'vue'
import StatusIcon from './StatusIcon.vue'

// Props
const props = defineProps({
  feature: {
    type: Object,
    required: true,
  },
  detailed: {
    type: Boolean,
    default: false,
  },
})

// Emits
const emit = defineEmits(['view-details'])

// Computed
const featureStatus = computed(() => {
  if (props.feature.is_over_limit) return 'error'
  if (props.feature.is_near_limit) return 'warning'
  if (props.feature.type === 'boolean') {
    return props.feature.is_enabled ? 'success' : 'inactive'
  }
  if (props.feature.type === 'numeric') {
    return props.feature.current_usage > 0 ? 'success' : 'inactive'
  }
  return 'success'
})

const borderColor = computed(() => {
  switch (featureStatus.value) {
    case 'error':
      return 'border-red-300'
    case 'warning':
      return 'border-yellow-300'
    case 'success':
      return 'border-green-300'
    default:
      return 'border-gray-200'
  }
})

const progressBarColor = computed(() => {
  const percentage = props.feature.percentage_used || 0
  
  if (percentage >= 100) return 'bg-red-500'
  if (percentage >= 80) return 'bg-yellow-500'
  if (percentage >= 60) return 'bg-blue-500'
  return 'bg-green-500'
})

// Methods
const formatNumber = (number) => {
  if (number === null || number === undefined) return '0'
  
  if (number >= 1000000) {
    return (number / 1000000).toFixed(1) + 'M'
  }
  if (number >= 1000) {
    return (number / 1000).toFixed(1) + 'K'
  }
  return number.toString()
}
</script>
