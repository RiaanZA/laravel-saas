<template>
  <div class="min-h-screen flex flex-col sm:justify-center items-center pt-6 sm:pt-0 bg-gray-100">
    <!-- Logo/Brand -->
    <div class="mb-6">
      <Link href="/" class="flex items-center">
        <div class="text-2xl font-bold text-gray-900">
          {{ appName }}
        </div>
      </Link>
    </div>

    <!-- Main Content Card -->
    <div class="w-full sm:max-w-md px-6 py-4 bg-white shadow-md overflow-hidden sm:rounded-lg">
      <!-- Status Messages -->
      <div v-if="status" class="mb-4 font-medium text-sm text-green-600">
        {{ status }}
      </div>

      <!-- Error Messages -->
      <div v-if="errors && Object.keys(errors).length > 0" class="mb-4">
        <div v-for="(error, field) in errors" :key="field" class="text-sm text-red-600">
          {{ error[0] }}
        </div>
      </div>

      <!-- Page Content -->
      <slot />
    </div>

    <!-- Footer Links -->
    <div class="mt-6 text-center">
      <slot name="footer" />
    </div>
  </div>
</template>

<script setup>
import { Link } from '@inertiajs/vue3'
import { computed } from 'vue'

const props = defineProps({
  status: String,
  errors: Object,
})

const appName = computed(() => {
  return window.Laravel?.appName || 'Laravel'
})
</script>
