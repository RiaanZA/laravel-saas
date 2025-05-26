<template>
  <div class="subscription-dashboard">
    <!-- Header -->
    <div class="dashboard-header">
      <h1 class="text-3xl font-bold text-gray-900">Subscription Dashboard</h1>
      <p class="mt-2 text-gray-600">Manage your subscription and monitor usage</p>
    </div>

    <!-- Loading State -->
    <div v-if="loading" class="flex justify-center py-12">
      <div class="animate-spin rounded-full h-12 w-12 border-b-2 border-blue-600"></div>
    </div>

    <!-- Error State -->
    <div v-else-if="error" class="bg-red-50 border border-red-200 rounded-lg p-4 mb-6">
      <div class="flex">
        <div class="flex-shrink-0">
          <svg class="h-5 w-5 text-red-400" viewBox="0 0 20 20" fill="currentColor">
            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd" />
          </svg>
        </div>
        <div class="ml-3">
          <h3 class="text-sm font-medium text-red-800">Error loading subscription data</h3>
          <p class="mt-1 text-sm text-red-700">{{ error }}</p>
        </div>
      </div>
    </div>

    <!-- Dashboard Content -->
    <div v-else class="space-y-6">
      <!-- Subscription Status Card -->
      <SubscriptionStatusCard 
        :subscription="subscription" 
        @cancel="handleCancel"
        @resume="handleResume"
        @change-plan="showPlanSelector = true"
      />

      <!-- Usage Overview -->
      <UsageOverview 
        :features="usageData" 
        :loading="usageLoading"
        @refresh="loadUsageData"
      />

      <!-- Quick Actions -->
      <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
        <QuickActionCard
          title="Billing History"
          description="View your payment history and invoices"
          icon="document-text"
          @click="$inertia.visit(route('subscription.billing'))"
        />
        <QuickActionCard
          title="Payment Methods"
          description="Manage your payment methods"
          icon="credit-card"
          @click="showPaymentMethods = true"
        />
        <QuickActionCard
          title="Usage Analytics"
          description="Detailed usage reports and trends"
          icon="chart-bar"
          @click="$inertia.visit(route('subscription.analytics'))"
        />
      </div>

      <!-- Alerts -->
      <AlertsPanel :alerts="alerts" @dismiss="dismissAlert" />
    </div>

    <!-- Modals -->
    <PlanSelectorModal 
      v-if="showPlanSelector"
      :current-plan="subscription?.plan"
      @close="showPlanSelector = false"
      @plan-selected="handlePlanChange"
    />

    <PaymentMethodsModal
      v-if="showPaymentMethods"
      @close="showPaymentMethods = false"
      @updated="loadSubscriptionData"
    />

    <CancelSubscriptionModal
      v-if="showCancelModal"
      :subscription="subscription"
      @close="showCancelModal = false"
      @cancelled="handleCancelled"
    />
  </div>
</template>

<script setup>
import { ref, onMounted, computed } from 'vue'
import { router } from '@inertiajs/vue3'
import SubscriptionStatusCard from './SubscriptionStatusCard.vue'
import UsageOverview from './UsageOverview.vue'
import QuickActionCard from './QuickActionCard.vue'
import AlertsPanel from './AlertsPanel.vue'
import PlanSelectorModal from './PlanSelectorModal.vue'
import PaymentMethodsModal from './PaymentMethodsModal.vue'
import CancelSubscriptionModal from './CancelSubscriptionModal.vue'

// Props
const props = defineProps({
  initialSubscription: Object,
  initialUsage: Object,
  initialAlerts: Array,
})

// Reactive data
const loading = ref(false)
const usageLoading = ref(false)
const error = ref(null)
const subscription = ref(props.initialSubscription)
const usageData = ref(props.initialUsage)
const alerts = ref(props.initialAlerts || [])

// Modal states
const showPlanSelector = ref(false)
const showPaymentMethods = ref(false)
const showCancelModal = ref(false)

// Computed
const hasActiveSubscription = computed(() => {
  return subscription.value && ['active', 'trial'].includes(subscription.value.status)
})

// Methods
const loadSubscriptionData = async () => {
  loading.value = true
  error.value = null
  
  try {
    const response = await fetch('/api/subscription/current')
    const data = await response.json()
    
    if (!response.ok) {
      throw new Error(data.message || 'Failed to load subscription data')
    }
    
    subscription.value = data.subscription
  } catch (err) {
    error.value = err.message
  } finally {
    loading.value = false
  }
}

const loadUsageData = async () => {
  if (!hasActiveSubscription.value) return
  
  usageLoading.value = true
  
  try {
    const response = await fetch('/api/subscription/usage/detailed')
    const data = await response.json()
    
    if (response.ok) {
      usageData.value = data.features
    }
  } catch (err) {
    console.error('Failed to load usage data:', err)
  } finally {
    usageLoading.value = false
  }
}

const loadAlerts = async () => {
  if (!hasActiveSubscription.value) return
  
  try {
    const response = await fetch('/api/subscription/usage/alerts')
    const data = await response.json()
    
    if (response.ok) {
      alerts.value = [
        ...data.over_limit_features.map(feature => ({
          id: `over_limit_${feature.key}`,
          type: 'error',
          title: 'Usage Limit Exceeded',
          message: `You've exceeded the limit for ${feature.name}`,
          feature: feature.key,
        })),
        ...data.near_limit_features.map(feature => ({
          id: `near_limit_${feature.key}`,
          type: 'warning',
          title: 'Approaching Usage Limit',
          message: `You're using ${Math.round(feature.percentage_used)}% of your ${feature.name} limit`,
          feature: feature.key,
        }))
      ]
    }
  } catch (err) {
    console.error('Failed to load alerts:', err)
  }
}

const handleCancel = () => {
  showCancelModal.value = true
}

const handleResume = async () => {
  if (!subscription.value) return
  
  try {
    const response = await fetch(`/api/subscription/subscription/${subscription.value.id}/resume`, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
      },
    })
    
    const data = await response.json()
    
    if (response.ok) {
      subscription.value = data.subscription
      showNotification('Subscription resumed successfully!', 'success')
    } else {
      showNotification(data.message || 'Failed to resume subscription', 'error')
    }
  } catch (err) {
    showNotification('Failed to resume subscription', 'error')
  }
}

const handlePlanChange = async (newPlan) => {
  showPlanSelector.value = false
  
  if (!subscription.value) return
  
  try {
    const response = await fetch(`/api/subscription/subscription/${subscription.value.id}`, {
      method: 'PUT',
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
      },
      body: JSON.stringify({
        plan_slug: newPlan.slug,
        prorate: true,
      }),
    })
    
    const data = await response.json()
    
    if (response.ok) {
      subscription.value = data.subscription
      showNotification('Plan changed successfully!', 'success')
      await loadUsageData()
    } else {
      showNotification(data.message || 'Failed to change plan', 'error')
    }
  } catch (err) {
    showNotification('Failed to change plan', 'error')
  }
}

const handleCancelled = (cancelledSubscription) => {
  showCancelModal.value = false
  subscription.value = cancelledSubscription
  showNotification('Subscription cancelled successfully', 'success')
}

const dismissAlert = (alertId) => {
  alerts.value = alerts.value.filter(alert => alert.id !== alertId)
}

const showNotification = (message, type = 'info') => {
  // Implement your notification system here
  // This could be a toast, alert, or any other notification method
  console.log(`${type.toUpperCase()}: ${message}`)
}

// Lifecycle
onMounted(() => {
  if (!props.initialSubscription) {
    loadSubscriptionData()
  }
  
  if (!props.initialUsage && hasActiveSubscription.value) {
    loadUsageData()
  }
  
  if (!props.initialAlerts && hasActiveSubscription.value) {
    loadAlerts()
  }
})
</script>

<style scoped>
.subscription-dashboard {
  @apply max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8;
}

.dashboard-header {
  @apply mb-8;
}
</style>
