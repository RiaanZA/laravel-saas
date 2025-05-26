import { createApp } from 'vue'
import { createInertiaApp } from '@inertiajs/vue3'

// Import subscription components
import SubscriptionDashboard from './components/subscription/SubscriptionDashboard.vue'
import SubscriptionStatusCard from './components/subscription/SubscriptionStatusCard.vue'
import PlanSelector from './components/subscription/PlanSelector.vue'
import PlanCard from './components/subscription/PlanCard.vue'
import PlanSelectorModal from './components/subscription/PlanSelectorModal.vue'
import UsageOverview from './components/subscription/UsageOverview.vue'
import UsageCard from './components/subscription/UsageCard.vue'
import StatusBadge from './components/subscription/StatusBadge.vue'
import StatusIcon from './components/subscription/StatusIcon.vue'

// Import utility components
import QuickActionCard from './components/subscription/QuickActionCard.vue'
import AlertsPanel from './components/subscription/AlertsPanel.vue'
import FeatureComparison from './components/subscription/FeatureComparison.vue'
import PaymentMethodsModal from './components/subscription/PaymentMethodsModal.vue'
import CancelSubscriptionModal from './components/subscription/CancelSubscriptionModal.vue'

// Global component registration function
export function registerSubscriptionComponents(app) {
    // Main components
    app.component('SubscriptionDashboard', SubscriptionDashboard)
    app.component('SubscriptionStatusCard', SubscriptionStatusCard)
    app.component('PlanSelector', PlanSelector)
    app.component('PlanCard', PlanCard)
    app.component('PlanSelectorModal', PlanSelectorModal)
    
    // Usage components
    app.component('UsageOverview', UsageOverview)
    app.component('UsageCard', UsageCard)
    
    // Utility components
    app.component('StatusBadge', StatusBadge)
    app.component('StatusIcon', StatusIcon)
    app.component('QuickActionCard', QuickActionCard)
    app.component('AlertsPanel', AlertsPanel)
    app.component('FeatureComparison', FeatureComparison)
    
    // Modal components
    app.component('PaymentMethodsModal', PaymentMethodsModal)
    app.component('CancelSubscriptionModal', CancelSubscriptionModal)
}

// Standalone initialization for non-Inertia apps
export function initializeSubscriptionComponents() {
    // Initialize dashboard component
    const dashboardElement = document.getElementById('subscription-dashboard')
    if (dashboardElement) {
        const app = createApp({
            components: {
                SubscriptionDashboard
            }
        })
        
        registerSubscriptionComponents(app)
        app.mount('#subscription-dashboard')
    }
    
    // Initialize plan selector component
    const planSelectorElement = document.getElementById('plan-selector')
    if (planSelectorElement) {
        const app = createApp({
            components: {
                PlanSelector
            }
        })
        
        registerSubscriptionComponents(app)
        app.mount('#plan-selector')
    }
    
    // Initialize other standalone components
    const usageOverviewElement = document.getElementById('usage-overview')
    if (usageOverviewElement) {
        const app = createApp({
            components: {
                UsageOverview
            }
        })
        
        registerSubscriptionComponents(app)
        app.mount('#usage-overview')
    }
}

// Auto-initialize if DOM is ready
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initializeSubscriptionComponents)
} else {
    initializeSubscriptionComponents()
}

// Export components for manual usage
export {
    SubscriptionDashboard,
    SubscriptionStatusCard,
    PlanSelector,
    PlanCard,
    PlanSelectorModal,
    UsageOverview,
    UsageCard,
    StatusBadge,
    StatusIcon,
    QuickActionCard,
    AlertsPanel,
    FeatureComparison,
    PaymentMethodsModal,
    CancelSubscriptionModal,
}

// Subscription API utilities
export class SubscriptionAPI {
    constructor(baseURL = '/api/subscription') {
        this.baseURL = baseURL
        this.csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content')
    }
    
    async request(endpoint, options = {}) {
        const url = `${this.baseURL}${endpoint}`
        const config = {
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                ...(this.csrfToken && { 'X-CSRF-TOKEN': this.csrfToken }),
                ...options.headers,
            },
            ...options,
        }
        
        if (config.body && typeof config.body === 'object') {
            config.body = JSON.stringify(config.body)
        }
        
        const response = await fetch(url, config)
        const data = await response.json()
        
        if (!response.ok) {
            throw new Error(data.message || `HTTP ${response.status}`)
        }
        
        return data
    }
    
    // Subscription methods
    async getCurrentSubscription() {
        return this.request('/current')
    }
    
    async createSubscription(planSlug, options = {}) {
        return this.request('/subscription', {
            method: 'POST',
            body: {
                plan_slug: planSlug,
                ...options,
            },
        })
    }
    
    async updateSubscription(subscriptionId, data) {
        return this.request(`/subscription/${subscriptionId}`, {
            method: 'PUT',
            body: data,
        })
    }
    
    async cancelSubscription(subscriptionId) {
        return this.request(`/subscription/${subscriptionId}/cancel`, {
            method: 'POST',
        })
    }
    
    async resumeSubscription(subscriptionId) {
        return this.request(`/subscription/${subscriptionId}/resume`, {
            method: 'POST',
        })
    }
    
    // Plan methods
    async getPlans() {
        return this.request('/public/plans')
    }
    
    async getPlan(planId) {
        return this.request(`/public/plans/${planId}`)
    }
    
    // Usage methods
    async getUsage() {
        return this.request('/usage/detailed')
    }
    
    async getFeatureUsage(featureKey) {
        return this.request(`/usage/${featureKey}`)
    }
    
    async incrementUsage(featureKey, increment = 1) {
        return this.request('/usage/increment', {
            method: 'POST',
            body: {
                feature_key: featureKey,
                increment,
            },
        })
    }
    
    async decrementUsage(featureKey, decrement = 1) {
        return this.request('/usage/decrement', {
            method: 'POST',
            body: {
                feature_key: featureKey,
                decrement,
            },
        })
    }
    
    async getUsageAlerts() {
        return this.request('/usage/alerts')
    }
    
    async getUsageStatistics() {
        return this.request('/usage/statistics')
    }
}

// Create global API instance
window.SubscriptionAPI = new SubscriptionAPI()

// Utility functions
export const formatPrice = (price, currency = 'USD') => {
    return new Intl.NumberFormat('en-US', {
        style: 'currency',
        currency,
        minimumFractionDigits: 0,
        maximumFractionDigits: 2,
    }).format(price)
}

export const formatNumber = (number) => {
    if (number >= 1000000) {
        return (number / 1000000).toFixed(1) + 'M'
    }
    if (number >= 1000) {
        return (number / 1000).toFixed(1) + 'K'
    }
    return number.toString()
}

export const formatDate = (dateString, options = {}) => {
    if (!dateString) return 'N/A'
    
    const date = new Date(dateString)
    const defaultOptions = {
        year: 'numeric',
        month: 'long',
        day: 'numeric',
    }
    
    return date.toLocaleDateString('en-US', { ...defaultOptions, ...options })
}

export const getStatusColor = (status) => {
    const colors = {
        active: 'green',
        trial: 'blue',
        cancelled: 'yellow',
        expired: 'red',
        past_due: 'orange',
        pending: 'gray',
        paused: 'purple',
    }
    
    return colors[status] || 'gray'
}

// Make utilities globally available
window.SubscriptionUtils = {
    formatPrice,
    formatNumber,
    formatDate,
    getStatusColor,
}
