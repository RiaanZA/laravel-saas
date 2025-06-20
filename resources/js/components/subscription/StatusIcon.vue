<template>
  <div :class="containerClasses">
    <svg :class="iconClasses" fill="currentColor" viewBox="0 0 20 20">
      <path v-if="status === 'success'" fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd" />
      <path v-else-if="status === 'error'" fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd" />
      <path v-else-if="status === 'warning'" fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd" />
      <path v-else-if="status === 'info'" fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd" />
      <circle v-else cx="10" cy="10" r="8" />
    </svg>
  </div>
</template>

<script setup>
import { computed } from 'vue'

// Props
const props = defineProps({
  status: {
    type: String,
    required: true,
    validator: (value) => ['success', 'error', 'warning', 'info', 'inactive'].includes(value),
  },
  size: {
    type: String,
    default: 'md',
    validator: (value) => ['sm', 'md', 'lg'].includes(value),
  },
})

// Computed
const statusConfig = computed(() => {
  const configs = {
    success: {
      containerClasses: 'text-green-500',
      iconClasses: '',
    },
    error: {
      containerClasses: 'text-red-500',
      iconClasses: '',
    },
    warning: {
      containerClasses: 'text-yellow-500',
      iconClasses: '',
    },
    info: {
      containerClasses: 'text-blue-500',
      iconClasses: '',
    },
    inactive: {
      containerClasses: 'text-gray-400',
      iconClasses: '',
    },
  }
  
  return configs[props.status] || configs.inactive
})

const sizeClasses = computed(() => {
  const sizes = {
    sm: 'h-4 w-4',
    md: 'h-5 w-5',
    lg: 'h-6 w-6',
  }
  
  return sizes[props.size] || sizes.md
})

const containerClasses = computed(() => {
  return [
    'flex-shrink-0',
    statusConfig.value.containerClasses,
  ].join(' ')
})

const iconClasses = computed(() => {
  return [
    sizeClasses.value,
    statusConfig.value.iconClasses,
  ].join(' ')
})
</script>
