<template>
  <div class="min-h-screen bg-gray-50 py-12">
    <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
      <!-- Header -->
      <div class="text-center mb-8">
        <h1 class="text-3xl font-bold text-gray-900 mb-2">Complete Your Subscription</h1>
        <p class="text-gray-600">You're just one step away from getting started</p>
      </div>

      <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
        <!-- Order Summary -->
        <div class="bg-white rounded-lg shadow-md border border-gray-200 p-6 h-fit">
          <h2 class="text-xl font-semibold text-gray-900 mb-6">Order Summary</h2>

          <!-- Plan Details -->
          <div class="border border-gray-200 rounded-lg p-4 mb-6">
            <div class="flex items-center justify-between mb-4">
              <div>
                <h3 class="text-lg font-semibold text-gray-900">{{ plan.name }}</h3>
                <p class="text-sm text-gray-600">{{ plan.billing_cycle_human }} billing</p>
              </div>
              <div class="text-right">
                <p class="text-xl font-bold text-gray-900">{{ plan.formatted_price }}</p>
                <p class="text-sm text-gray-500">/{{ plan.billing_cycle }}</p>
              </div>
            </div>

            <p class="text-sm text-gray-600 mb-4">{{ plan.description }}</p>

            <!-- Trial Information -->
            <div v-if="plan.has_trial_period && startTrial" class="bg-green-50 border border-green-200 rounded-lg p-3">
              <div class="flex items-center">
                <svg class="w-5 h-5 text-green-500 mr-2" fill="currentColor" viewBox="0 0 20 20">
                  <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                </svg>
                <div>
                  <p class="text-sm font-medium text-green-900">{{ plan.trial_days }} Day Free Trial</p>
                  <p class="text-xs text-green-700">No payment required until trial ends</p>
                </div>
              </div>
            </div>
          </div>

          <!-- Features -->
          <div class="mb-6">
            <h4 class="text-sm font-semibold text-gray-900 mb-3">What's included:</h4>
            <ul class="space-y-2">
              <li
                v-for="feature in plan.features"
                :key="feature.name"
                class="flex items-center text-sm text-gray-600"
              >
                <svg class="w-4 h-4 text-green-500 mr-2 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                  <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd" />
                </svg>
                <span>{{ feature.name }}: {{ feature.limit }}</span>
              </li>
            </ul>
          </div>

          <!-- Pricing Breakdown -->
          <div class="border-t border-gray-200 pt-4">
            <div class="flex justify-between text-sm text-gray-600 mb-2">
              <span>Subtotal</span>
              <span>{{ plan.formatted_price }}</span>
            </div>
            <div v-if="plan.has_trial_period && startTrial" class="flex justify-between text-sm text-green-600 mb-2">
              <span>Trial discount</span>
              <span>-{{ plan.formatted_price }}</span>
            </div>
            <div class="flex justify-between text-lg font-semibold text-gray-900 border-t border-gray-200 pt-2">
              <span>Total due today</span>
              <span>{{ plan.has_trial_period && startTrial ? 'R0.00' : plan.formatted_price }}</span>
            </div>
          </div>
        </div>

        <!-- Payment Form -->
        <div class="bg-white rounded-lg shadow-md border border-gray-200 p-6">
          <h2 class="text-xl font-semibold text-gray-900 mb-6">
            {{ plan.has_trial_period && startTrial ? 'Start Your Trial' : 'Payment Information' }}
          </h2>

          <form @submit.prevent="submitForm">
            <!-- Trial Toggle -->
            <div v-if="plan.has_trial_period" class="mb-6">
              <label class="flex items-center">
                <input
                  v-model="startTrial"
                  type="checkbox"
                  class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded"
                />
                <span class="ml-3 text-sm text-gray-700">
                  Start with {{ plan.trial_days }} day free trial
                </span>
              </label>
            </div>

            <!-- Payment Method (only if not starting trial) -->
            <div v-if="!startTrial">
              <PaymentForm
                :amount="plan.price"
                :submit-text="`Subscribe for ${plan.formatted_price}`"
                :user="user"
                @submit="handlePaymentSubmit"
              />
            </div>

            <!-- Trial Form -->
            <div v-else class="space-y-4">
              <!-- Customer Information -->
              <div>
                <h3 class="text-lg font-medium text-gray-900 mb-4">Account Information</h3>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                  <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">
                      First Name
                    </label>
                    <input
                      v-model="trialForm.first_name"
                      type="text"
                      class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                    />
                  </div>

                  <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">
                      Last Name
                    </label>
                    <input
                      v-model="trialForm.last_name"
                      type="text"
                      class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                    />
                  </div>
                </div>

                <div class="mt-4">
                  <label class="block text-sm font-medium text-gray-700 mb-1">
                    Email Address
                  </label>
                  <input
                    v-model="trialForm.email"
                    type="email"
                    class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                  />
                </div>
              </div>

              <!-- Terms and Conditions -->
              <div class="mb-6">
                <label class="flex items-start">
                  <input
                    v-model="acceptTerms"
                    type="checkbox"
                    class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded mt-0.5"
                  />
                  <span class="ml-3 text-sm text-gray-700">
                    I agree to the
                    <a href="#" class="text-blue-600 hover:text-blue-700">Terms of Service</a>
                    and
                    <a href="#" class="text-blue-600 hover:text-blue-700">Privacy Policy</a>
                  </span>
                </label>
              </div>

              <!-- Submit Button -->
              <button
                type="submit"
                :disabled="loading || !acceptTerms"
                class="w-full bg-blue-600 text-white py-3 px-4 rounded-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 disabled:opacity-50 disabled:cursor-not-allowed transition-colors duration-200"
              >
                <span v-if="loading" class="flex items-center justify-center">
                  <LoadingSpinner size="sm" class="mr-2" />
                  Starting Trial...
                </span>
                <span v-else>
                  Start {{ plan.trial_days }} Day Trial
                </span>
              </button>
            </div>
          </form>
        </div>
      </div>
    </div>

    <!-- Error Notification -->
    <ErrorNotification
      :show="showError"
      :title="'Subscription Error'"
      :message="getErrorMessage()"
      :errors="getErrorList()"
      @close="clearError"
    />

    <!-- Success Notification -->
    <SuccessNotification
      :show="showSuccess"
      :title="'Success'"
      :message="successMessage"
      @close="clearSuccess"
    />
  </div>
</template>

<script setup>
import { ref, reactive } from 'vue'
import { router } from '@inertiajs/vue3'
import PaymentForm from '../../Components/Subscription/PaymentForm.vue'
import LoadingSpinner from '../../Components/UI/LoadingSpinner.vue'
import ErrorNotification from '../../Components/UI/ErrorNotification.vue'
import SuccessNotification from '../../Components/UI/SuccessNotification.vue'
import { useErrorHandling } from '../../composables/useErrorHandling.js'
import { useSuccessHandling } from '../../composables/useSuccessHandling.js'

const props = defineProps({
  plan: {
    type: Object,
    required: true
  },
  user: {
    type: Object,
    required: true
  },
  existingSubscription: {
    type: Object,
    default: null
  }
})

const loading = ref(false)
const startTrial = ref(props.plan.has_trial_period)
const acceptTerms = ref(false)

// Error and success handling
const { showError, handleInertiaError, clearError, getErrorMessage, getErrorList } = useErrorHandling()
const { successMessage, showSuccess, handleSuccess, clearSuccess } = useSuccessHandling()

const trialForm = reactive({
  first_name: props.user.first_name || '',
  last_name: props.user.last_name || '',
  email: props.user.email || ''
})

const submitForm = () => {
  if (startTrial.value) {
    handleTrialSubmit()
  }
}

const handleTrialSubmit = () => {
  // Clear any previous errors
  clearError()
  clearSuccess()

  // Validation
  if (!acceptTerms.value) {
    handleInertiaError('Please accept the terms and conditions to continue.')
    return
  }

  // Validate required fields
  if (!trialForm.first_name.trim()) {
    handleInertiaError('First name is required.')
    return
  }

  if (!trialForm.last_name.trim()) {
    handleInertiaError('Last name is required.')
    return
  }

  if (!trialForm.email.trim()) {
    handleInertiaError('Email address is required.')
    return
  }

  // Basic email validation
  const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/
  if (!emailRegex.test(trialForm.email)) {
    handleInertiaError('Please enter a valid email address.')
    return
  }

  loading.value = true

  const data = {
    plan_slug: props.plan.slug,
    start_trial: true,
    customer: {
      first_name: trialForm.first_name.trim(),
      last_name: trialForm.last_name.trim(),
      email: trialForm.email.trim()
    }
  }

  // Make actual API call to create subscription
  router.post(route('subscription.store'), data, {
    onSuccess: (response) => {
      handleSuccess('Trial started successfully! Redirecting to your dashboard...')

      // Delay redirect to show success message
      setTimeout(() => {
        router.visit(route('subscription.dashboard'))
      }, 1500)
    },
    onError: (errors) => {
      console.error('Trial creation failed:', errors)
      handleInertiaError(errors, 'Failed to start trial. Please try again.')
    },
    onFinish: () => {
      loading.value = false
    }
  })
}

const handlePaymentSubmit = (paymentData) => {
  // Clear any previous errors
  clearError()
  clearSuccess()

  // Validate payment data
  if (!paymentData || typeof paymentData !== 'object') {
    handleInertiaError('Invalid payment information. Please try again.')
    return
  }

  loading.value = true

  const data = {
    plan_slug: props.plan.slug,
    start_trial: false,
    payment_data: paymentData
  }

  // Make actual API call to process payment
  router.post(route('subscription.payment.process'), data, {
    onSuccess: (response) => {
      // Handle successful payment response
      if (response.payment_url) {
        handleSuccess('Redirecting to payment gateway...')
        // Small delay to show success message before redirect
        setTimeout(() => {
          window.location.href = response.payment_url
        }, 1000)
      } else if (response.redirect_url) {
        handleSuccess('Payment processed successfully! Redirecting...')
        setTimeout(() => {
          router.visit(response.redirect_url)
        }, 1500)
      } else {
        handleSuccess('Payment processed successfully! Redirecting to dashboard...')
        setTimeout(() => {
          router.visit(route('subscription.dashboard'))
        }, 1500)
      }
    },
    onError: (errors) => {
      console.error('Payment processing failed:', errors)
      handleInertiaError(errors, 'Payment processing failed. Please check your payment details and try again.')
    },
    onFinish: () => {
      loading.value = false
    }
  })
}
</script>
