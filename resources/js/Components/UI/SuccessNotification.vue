<template>
  <div
    v-if="show"
    class="fixed top-4 right-4 z-50 max-w-md w-full bg-green-50 border border-green-200 rounded-lg shadow-lg"
    :class="{ 'animate-slide-in': show }"
  >
    <div class="p-4">
      <div class="flex items-start">
        <div class="flex-shrink-0">
          <svg class="h-5 w-5 text-green-400" viewBox="0 0 20 20" fill="currentColor">
            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
          </svg>
        </div>
        <div class="ml-3 flex-1">
          <h3 class="text-sm font-medium text-green-800">
            {{ title }}
          </h3>
          <div class="mt-1 text-sm text-green-700">
            <p>{{ message }}</p>
          </div>
        </div>
        <div class="ml-4 flex-shrink-0">
          <button
            @click="close"
            class="inline-flex text-green-400 hover:text-green-600 focus:outline-none focus:text-green-600"
          >
            <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor">
              <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd" />
            </svg>
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, watch } from 'vue'

const props = defineProps({
  show: {
    type: Boolean,
    default: false
  },
  title: {
    type: String,
    default: 'Success'
  },
  message: {
    type: String,
    required: true
  },
  autoClose: {
    type: Boolean,
    default: true
  },
  autoCloseDelay: {
    type: Number,
    default: 4000
  }
})

const emit = defineEmits(['close'])

let autoCloseTimer = null

const close = () => {
  if (autoCloseTimer) {
    clearTimeout(autoCloseTimer)
    autoCloseTimer = null
  }
  emit('close')
}

watch(() => props.show, (newValue) => {
  if (newValue && props.autoClose) {
    autoCloseTimer = setTimeout(() => {
      close()
    }, props.autoCloseDelay)
  }
})
</script>


