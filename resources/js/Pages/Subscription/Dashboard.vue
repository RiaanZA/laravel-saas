<template>
  <div class="min-h-screen bg-gray-50 py-8">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
      <!-- Header -->
      <div class="mb-8">
        <h1 class="text-3xl font-bold text-gray-900">Subscription Dashboard</h1>
        <p class="text-gray-600 mt-2">Manage your subscription and monitor your usage</p>
      </div>

      <!-- No Subscription State -->
      <div v-if="!hasSubscription" class="text-center py-12">
        <div class="bg-white rounded-lg shadow-md border border-gray-200 p-8 max-w-md mx-auto">
          <div class="text-gray-400 mb-4">
            <svg class="w-16 h-16 mx-auto" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z" />
            </svg>
          </div>
          <h3 class="text-lg font-semibold text-gray-900 mb-2">No Active Subscription</h3>
          <p class="text-gray-600 mb-6">
            You don't have an active subscription. Choose a plan to get started.
          </p>
          <button
            @click="goToPlans"
            class="w-full bg-blue-600 text-white py-2 px-4 rounded-md hover:bg-blue-700 transition-colors duration-200"
          >
            View Plans
          </button>
        </div>
      </div>

      <!-- Subscription Dashboard -->
      <div v-else class="grid grid-cols-1 lg:grid-cols-3 gap-8">
        <!-- Main Content -->
        <div class="lg:col-span-2 space-y-8">
          <!-- Subscription Status -->
          <SubscriptionStatus
            :subscription="subscription"
            @change-plan="showChangePlanModal = true"
            @cancel-subscription="showCancelModal = true"
            @resume-subscription="resumeSubscription"
            @view-billing="showBillingHistory = true"
          />

          <!-- Usage Metrics -->
          <UsageMetrics :usage-stats="usageStats" />

          <!-- Billing History -->
          <div class="bg-white rounded-lg shadow-md border border-gray-200 overflow-hidden">
            <div class="px-6 py-4 bg-gray-50 border-b border-gray-200 flex items-center justify-between">
              <h3 class="text-lg font-semibold text-gray-900">Recent Billing History</h3>
              <button
                @click="showBillingHistory = true"
                class="text-blue-600 hover:text-blue-700 text-sm font-medium"
              >
                View All
              </button>
            </div>
            
            <div class="p-6">
              <div v-if="billingHistory.length === 0" class="text-center py-4">
                <p class="text-gray-500">No billing history available</p>
              </div>
              
              <div v-else class="space-y-4">
                <div 
                  v-for="invoice in billingHistory.slice(0, 3)" 
                  :key="invoice.id"
                  class="flex items-center justify-between py-3 border-b border-gray-200 last:border-b-0"
                >
                  <div>
                    <p class="text-sm font-medium text-gray-900">{{ invoice.description }}</p>
                    <p class="text-sm text-gray-500">{{ formatDate(invoice.date) }}</p>
                  </div>
                  <div class="text-right">
                    <p class="text-sm font-semibold text-gray-900">{{ invoice.formatted_amount }}</p>
                    <span 
                      class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium"
                      :class="invoice.status === 'paid' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'"
                    >
                      {{ invoice.status }}
                    </span>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Sidebar -->
        <div class="space-y-8">
          <!-- Upcoming Invoice -->
          <div v-if="upcomingInvoice" class="bg-white rounded-lg shadow-md border border-gray-200 overflow-hidden">
            <div class="px-6 py-4 bg-gray-50 border-b border-gray-200">
              <h3 class="text-lg font-semibold text-gray-900">Upcoming Invoice</h3>
            </div>
            
            <div class="p-6">
              <div class="text-center">
                <p class="text-2xl font-bold text-gray-900">{{ upcomingInvoice.formatted_amount }}</p>
                <p class="text-sm text-gray-500 mb-4">Due {{ formatDate(upcomingInvoice.date) }}</p>
                <p class="text-sm text-gray-600">{{ upcomingInvoice.description }}</p>
              </div>
            </div>
          </div>

          <!-- Quick Actions -->
          <div class="bg-white rounded-lg shadow-md border border-gray-200 overflow-hidden">
            <div class="px-6 py-4 bg-gray-50 border-b border-gray-200">
              <h3 class="text-lg font-semibold text-gray-900">Quick Actions</h3>
            </div>
            
            <div class="p-6 space-y-3">
              <button
                @click="showChangePlanModal = true"
                class="w-full text-left px-4 py-2 text-sm text-gray-700 hover:bg-gray-50 rounded-md transition-colors duration-200"
              >
                Change Plan
              </button>
              
              <button
                @click="showPaymentMethodsModal = true"
                class="w-full text-left px-4 py-2 text-sm text-gray-700 hover:bg-gray-50 rounded-md transition-colors duration-200"
              >
                Update Payment Method
              </button>
              
              <button
                @click="downloadInvoices"
                class="w-full text-left px-4 py-2 text-sm text-gray-700 hover:bg-gray-50 rounded-md transition-colors duration-200"
              >
                Download Invoices
              </button>
              
              <button
                v-if="!subscription.is_cancelled"
                @click="showCancelModal = true"
                class="w-full text-left px-4 py-2 text-sm text-red-600 hover:bg-red-50 rounded-md transition-colors duration-200"
              >
                Cancel Subscription
              </button>
            </div>
          </div>

          <!-- Support -->
          <div class="bg-white rounded-lg shadow-md border border-gray-200 overflow-hidden">
            <div class="px-6 py-4 bg-gray-50 border-b border-gray-200">
              <h3 class="text-lg font-semibold text-gray-900">Need Help?</h3>
            </div>
            
            <div class="p-6">
              <p class="text-sm text-gray-600 mb-4">
                Have questions about your subscription? We're here to help.
              </p>
              
              <div class="space-y-2">
                <a 
                  href="#" 
                  class="block text-sm text-blue-600 hover:text-blue-700"
                >
                  Contact Support
                </a>
                <a 
                  href="#" 
                  class="block text-sm text-blue-600 hover:text-blue-700"
                >
                  View Documentation
                </a>
                <a 
                  href="#" 
                  class="block text-sm text-blue-600 hover:text-blue-700"
                >
                  FAQ
                </a>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Modals -->
    <CancelSubscriptionModal
      v-if="showCancelModal"
      :subscription="subscription"
      @close="showCancelModal = false"
      @cancelled="handleSubscriptionCancelled"
    />
  </div>
</template>

<script setup>
import { ref } from 'vue'
import SubscriptionStatus from '../../Components/Subscription/SubscriptionStatus.vue'
import UsageMetrics from '../../Components/Subscription/UsageMetrics.vue'
import CancelSubscriptionModal from '../../Components/Subscription/CancelSubscriptionModal.vue'

const props = defineProps({
  hasSubscription: {
    type: Boolean,
    default: false
  },
  subscription: {
    type: Object,
    default: null
  },
  usageStats: {
    type: Object,
    default: () => ({})
  },
  billingHistory: {
    type: Array,
    default: () => []
  },
  upcomingInvoice: {
    type: Object,
    default: null
  }
})

const showCancelModal = ref(false)
const showChangePlanModal = ref(false)
const showPaymentMethodsModal = ref(false)
const showBillingHistory = ref(false)

const goToPlans = () => {
  // Navigate to plans page
  window.location.href = '/subscription/plans'
}

const resumeSubscription = () => {
  // Handle subscription resumption
  console.log('Resume subscription')
}

const handleSubscriptionCancelled = (data) => {
  showCancelModal.value = false
  console.log('Subscription cancelled:', data)
  // Handle cancellation logic
}

const downloadInvoices = () => {
  // Implementation for downloading invoices
  console.log('Download invoices')
}

const formatDate = (dateString) => {
  return new Date(dateString).toLocaleDateString('en-ZA', {
    year: 'numeric',
    month: 'long',
    day: 'numeric'
  })
}
</script>
