<template>
  <span :class="badgeClasses">
    <svg v-if="showIcon" :class="iconClasses" fill="currentColor" viewBox="0 0 8 8">
      <circle cx="4" cy="4" r="3" />
    </svg>
    {{ displayText }}
  </span>
</template>

<script setup>
import { computed } from 'vue'

// Props
const props = defineProps({
  status: {
    type: String,
    required: true,
  },
  showIcon: {
    type: Boolean,
    default: true,
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
    active: {
      text: 'Active',
      classes: 'bg-green-100 text-green-800',
      iconClasses: 'text-green-400',
    },
    trial: {
      text: 'Trial',
      classes: 'bg-blue-100 text-blue-800',
      iconClasses: 'text-blue-400',
    },
    cancelled: {
      text: 'Cancelled',
      classes: 'bg-yellow-100 text-yellow-800',
      iconClasses: 'text-yellow-400',
    },
    expired: {
      text: 'Expired',
      classes: 'bg-red-100 text-red-800',
      iconClasses: 'text-red-400',
    },
    past_due: {
      text: 'Past Due',
      classes: 'bg-orange-100 text-orange-800',
      iconClasses: 'text-orange-400',
    },
    pending: {
      text: 'Pending',
      classes: 'bg-gray-100 text-gray-800',
      iconClasses: 'text-gray-400',
    },
    paused: {
      text: 'Paused',
      classes: 'bg-purple-100 text-purple-800',
      iconClasses: 'text-purple-400',
    },
    // Usage status
    success: {
      text: 'Good',
      classes: 'bg-green-100 text-green-800',
      iconClasses: 'text-green-400',
    },
    warning: {
      text: 'Warning',
      classes: 'bg-yellow-100 text-yellow-800',
      iconClasses: 'text-yellow-400',
    },
    error: {
      text: 'Over Limit',
      classes: 'bg-red-100 text-red-800',
      iconClasses: 'text-red-400',
    },
    inactive: {
      text: 'Inactive',
      classes: 'bg-gray-100 text-gray-800',
      iconClasses: 'text-gray-400',
    },
  }
  
  return configs[props.status] || configs.inactive
})

const sizeClasses = computed(() => {
  const sizes = {
    sm: 'px-2 py-1 text-xs',
    md: 'px-2.5 py-0.5 text-sm',
    lg: 'px-3 py-1 text-base',
  }
  
  return sizes[props.size] || sizes.md
})

const iconSizeClasses = computed(() => {
  const sizes = {
    sm: 'h-1.5 w-1.5',
    md: 'h-2 w-2',
    lg: 'h-2.5 w-2.5',
  }
  
  return sizes[props.size] || sizes.md
})

const badgeClasses = computed(() => {
  return [
    'inline-flex items-center font-medium rounded-full',
    statusConfig.value.classes,
    sizeClasses.value,
  ].join(' ')
})

const iconClasses = computed(() => {
  return [
    'mr-1.5',
    statusConfig.value.iconClasses,
    iconSizeClasses.value,
  ].join(' ')
})

const displayText = computed(() => {
  return statusConfig.value.text
})
</script>
