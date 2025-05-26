<template>
  <div v-if="alerts && alerts.length > 0" class="space-y-4">
    <div
      v-for="alert in alerts"
      :key="alert.id"
      :class="[
        'rounded-md p-4 border',
        alertClasses(alert.type)
      ]"
    >
      <div class="flex">
        <div class="flex-shrink-0">
          <component :is="alertIcon(alert.type)" :class="iconClasses(alert.type)" />
        </div>
        <div class="ml-3 flex-1">
          <h3 :class="['text-sm font-medium', titleClasses(alert.type)]">
            {{ alert.title }}
          </h3>
          <div :class="['mt-1 text-sm', messageClasses(alert.type)]">
            <p>{{ alert.message }}</p>
          </div>
          <div v-if="alert.actions" class="mt-3 flex space-x-3">
            <button
              v-for="action in alert.actions"
              :key="action.label"
              @click="handleAction(action, alert)"
              :class="[
                'text-sm font-medium underline',
                actionClasses(alert.type)
              ]"
            >
              {{ action.label }}
            </button>
          </div>
        </div>
        <div class="ml-auto pl-3">
          <div class="-mx-1.5 -my-1.5">
            <button
              @click="$emit('dismiss', alert.id)"
              :class="[
                'inline-flex rounded-md p-1.5 focus:outline-none focus:ring-2 focus:ring-offset-2',
                dismissClasses(alert.type)
              ]"
            >
              <span class="sr-only">Dismiss</span>
              <svg class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd" />
              </svg>
            </button>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed } from 'vue'

// Props
const props = defineProps({
  alerts: {
    type: Array,
    default: () => [],
  },
})

// Emits
const emit = defineEmits(['dismiss', 'action'])

// Methods
const alertClasses = (type) => {
  const classes = {
    error: 'bg-red-50 border-red-200',
    warning: 'bg-yellow-50 border-yellow-200',
    info: 'bg-blue-50 border-blue-200',
    success: 'bg-green-50 border-green-200',
  }
  return classes[type] || classes.info
}

const iconClasses = (type) => {
  const classes = {
    error: 'h-5 w-5 text-red-400',
    warning: 'h-5 w-5 text-yellow-400',
    info: 'h-5 w-5 text-blue-400',
    success: 'h-5 w-5 text-green-400',
  }
  return classes[type] || classes.info
}

const titleClasses = (type) => {
  const classes = {
    error: 'text-red-800',
    warning: 'text-yellow-800',
    info: 'text-blue-800',
    success: 'text-green-800',
  }
  return classes[type] || classes.info
}

const messageClasses = (type) => {
  const classes = {
    error: 'text-red-700',
    warning: 'text-yellow-700',
    info: 'text-blue-700',
    success: 'text-green-700',
  }
  return classes[type] || classes.info
}

const actionClasses = (type) => {
  const classes = {
    error: 'text-red-800 hover:text-red-900',
    warning: 'text-yellow-800 hover:text-yellow-900',
    info: 'text-blue-800 hover:text-blue-900',
    success: 'text-green-800 hover:text-green-900',
  }
  return classes[type] || classes.info
}

const dismissClasses = (type) => {
  const classes = {
    error: 'text-red-400 hover:text-red-500 hover:bg-red-100 focus:ring-red-600 focus:ring-offset-red-50',
    warning: 'text-yellow-400 hover:text-yellow-500 hover:bg-yellow-100 focus:ring-yellow-600 focus:ring-offset-yellow-50',
    info: 'text-blue-400 hover:text-blue-500 hover:bg-blue-100 focus:ring-blue-600 focus:ring-offset-blue-50',
    success: 'text-green-400 hover:text-green-500 hover:bg-green-100 focus:ring-green-600 focus:ring-offset-green-50',
  }
  return classes[type] || classes.info
}

const alertIcon = (type) => {
  const icons = {
    error: {
      template: `
        <svg fill="currentColor" viewBox="0 0 20 20">
          <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd" />
        </svg>
      `
    },
    warning: {
      template: `
        <svg fill="currentColor" viewBox="0 0 20 20">
          <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd" />
        </svg>
      `
    },
    info: {
      template: `
        <svg fill="currentColor" viewBox="0 0 20 20">
          <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd" />
        </svg>
      `
    },
    success: {
      template: `
        <svg fill="currentColor" viewBox="0 0 20 20">
          <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
        </svg>
      `
    },
  }
  
  return icons[type] || icons.info
}

const handleAction = (action, alert) => {
  emit('action', { action, alert })
  
  if (action.handler) {
    action.handler(alert)
  }
}
</script>
