<template>
  <div class="bg-white rounded-lg shadow-sm border border-gray-200">
    <!-- Header -->
    <div class="px-6 py-4 border-b border-gray-200">
      <div class="flex items-center justify-between">
        <h2 class="text-lg font-semibold text-gray-900">Usage Overview</h2>
        <div class="flex items-center space-x-2">
          <button
            @click="$emit('refresh')"
            :disabled="loading"
            class="inline-flex items-center px-3 py-1 border border-gray-300 shadow-sm text-xs font-medium rounded text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 disabled:opacity-50"
          >
            <svg v-if="loading" class="animate-spin -ml-1 mr-1 h-3 w-3" fill="none" viewBox="0 0 24 24">
              <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
              <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
            </svg>
            <svg v-else class="h-3 w-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
            </svg>
            Refresh
          </button>
          <button
            @click="showDetailed = !showDetailed"
            class="inline-flex items-center px-3 py-1 border border-gray-300 shadow-sm text-xs font-medium rounded text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500"
          >
            {{ showDetailed ? 'Simple View' : 'Detailed View' }}
          </button>
        </div>
      </div>
    </div>

    <!-- Content -->
    <div class="p-6">
      <!-- Loading State -->
      <div v-if="loading" class="flex justify-center py-8">
        <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-blue-600"></div>
      </div>

      <!-- No Features -->
      <div v-else-if="!features || features.length === 0" class="text-center py-8">
        <div class="mx-auto h-12 w-12 text-gray-400">
          <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
          </svg>
        </div>
        <h3 class="mt-2 text-sm font-medium text-gray-900">No Usage Data</h3>
        <p class="mt-1 text-sm text-gray-500">Start using features to see your usage statistics.</p>
      </div>

      <!-- Features Grid -->
      <div v-else class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
        <UsageCard
          v-for="feature in features"
          :key="feature.key"
          :feature="feature"
          :detailed="showDetailed"
          @view-details="viewFeatureDetails"
        />
      </div>

      <!-- Summary Stats -->
      <div v-if="features && features.length > 0" class="mt-8 pt-6 border-t border-gray-200">
        <div class="grid grid-cols-1 md:grid-cols-4 gap-6">
          <div class="text-center">
            <div class="text-2xl font-bold text-gray-900">{{ totalFeatures }}</div>
            <div class="text-sm text-gray-500">Total Features</div>
          </div>
          <div class="text-center">
            <div class="text-2xl font-bold text-green-600">{{ activeFeatures }}</div>
            <div class="text-sm text-gray-500">Active Features</div>
          </div>
          <div class="text-center">
            <div class="text-2xl font-bold text-yellow-600">{{ nearLimitFeatures }}</div>
            <div class="text-sm text-gray-500">Near Limit</div>
          </div>
          <div class="text-center">
            <div class="text-2xl font-bold text-red-600">{{ overLimitFeatures }}</div>
            <div class="text-sm text-gray-500">Over Limit</div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed } from 'vue'
import UsageCard from './UsageCard.vue'

// Props
const props = defineProps({
  features: Array,
  loading: {
    type: Boolean,
    default: false,
  },
})

// Emits
const emit = defineEmits(['refresh', 'view-details'])

// Reactive data
const showDetailed = ref(false)

// Computed
const totalFeatures = computed(() => {
  return props.features ? props.features.length : 0
})

const activeFeatures = computed(() => {
  if (!props.features) return 0
  return props.features.filter(feature => {
    if (feature.type === 'boolean') {
      return feature.is_enabled
    }
    return feature.current_usage > 0 || feature.is_unlimited
  }).length
})

const nearLimitFeatures = computed(() => {
  if (!props.features) return 0
  return props.features.filter(feature => feature.is_near_limit).length
})

const overLimitFeatures = computed(() => {
  if (!props.features) return 0
  return props.features.filter(feature => feature.is_over_limit).length
})

// Methods
const viewFeatureDetails = (feature) => {
  emit('view-details', feature)
}
</script>
