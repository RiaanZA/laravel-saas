<template>
  <div class="bg-white rounded-lg shadow-md border border-gray-200 p-6">
    <h2 class="text-xl font-semibold text-gray-900 mb-6">Payment Information</h2>

    <form @submit.prevent="submitPayment">
      <!-- Card Information -->
      <div class="space-y-4 mb-6">
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">
            Card Number
          </label>
          <input
            v-model="form.card.number"
            type="text"
            placeholder="1234 5678 9012 3456"
            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
            :class="{ 'border-red-300': errors.card?.number }"
            @input="formatCardNumber"
          />
          <p v-if="errors.card?.number" class="text-red-600 text-xs mt-1">
            {{ errors.card.number }}
          </p>
        </div>

        <div class="grid grid-cols-2 gap-4">
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">
              Expiry Date
            </label>
            <input
              v-model="form.card.expiry"
              type="text"
              placeholder="MM/YY"
              class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
              :class="{ 'border-red-300': errors.card?.expiry }"
              @input="formatExpiry"
            />
            <p v-if="errors.card?.expiry" class="text-red-600 text-xs mt-1">
              {{ errors.card.expiry }}
            </p>
          </div>

          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">
              CVV
            </label>
            <input
              v-model="form.card.cvv"
              type="text"
              placeholder="123"
              maxlength="4"
              class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
              :class="{ 'border-red-300': errors.card?.cvv }"
            />
            <p v-if="errors.card?.cvv" class="text-red-600 text-xs mt-1">
              {{ errors.card.cvv }}
            </p>
          </div>
        </div>

        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">
            Cardholder Name
          </label>
          <input
            v-model="form.card.holder_name"
            type="text"
            placeholder="John Doe"
            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
            :class="{ 'border-red-300': errors.card?.holder_name }"
          />
          <p v-if="errors.card?.holder_name" class="text-red-600 text-xs mt-1">
            {{ errors.card.holder_name }}
          </p>
        </div>
      </div>

      <!-- Billing Address -->
      <div class="mb-6">
        <h3 class="text-lg font-medium text-gray-900 mb-4">Billing Address</h3>
        
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">
              First Name
            </label>
            <input
              v-model="form.billing.first_name"
              type="text"
              class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
              :class="{ 'border-red-300': errors.billing?.first_name }"
            />
            <p v-if="errors.billing?.first_name" class="text-red-600 text-xs mt-1">
              {{ errors.billing.first_name }}
            </p>
          </div>

          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">
              Last Name
            </label>
            <input
              v-model="form.billing.last_name"
              type="text"
              class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
              :class="{ 'border-red-300': errors.billing?.last_name }"
            />
            <p v-if="errors.billing?.last_name" class="text-red-600 text-xs mt-1">
              {{ errors.billing.last_name }}
            </p>
          </div>
        </div>

        <div class="mb-4">
          <label class="block text-sm font-medium text-gray-700 mb-1">
            Email Address
          </label>
          <input
            v-model="form.billing.email"
            type="email"
            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
            :class="{ 'border-red-300': errors.billing?.email }"
          />
          <p v-if="errors.billing?.email" class="text-red-600 text-xs mt-1">
            {{ errors.billing.email }}
          </p>
        </div>

        <div class="mb-4">
          <label class="block text-sm font-medium text-gray-700 mb-1">
            Address Line 1
          </label>
          <input
            v-model="form.billing.address.line1"
            type="text"
            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
          />
        </div>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">
              City
            </label>
            <input
              v-model="form.billing.address.city"
              type="text"
              class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
            />
          </div>

          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">
              Province
            </label>
            <input
              v-model="form.billing.address.state"
              type="text"
              class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
            />
          </div>

          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">
              Postal Code
            </label>
            <input
              v-model="form.billing.address.postal_code"
              type="text"
              class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
            />
          </div>
        </div>
      </div>

      <!-- Submit Button -->
      <button
        type="submit"
        :disabled="loading"
        class="w-full bg-blue-600 text-white py-3 px-4 rounded-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 disabled:opacity-50 disabled:cursor-not-allowed transition-colors duration-200"
      >
        <span v-if="loading" class="flex items-center justify-center">
          <LoadingSpinner size="sm" class="mr-2" />
          Processing Payment...
        </span>
        <span v-else>
          {{ submitText }}
        </span>
      </button>

      <!-- Security Notice -->
      <div class="mt-4 flex items-center justify-center text-xs text-gray-500">
        <svg class="w-4 h-4 mr-1" fill="currentColor" viewBox="0 0 20 20">
          <path fill-rule="evenodd" d="M5 9V7a5 5 0 0110 0v2a2 2 0 012 2v5a2 2 0 01-2 2H5a2 2 0 01-2-2v-5a2 2 0 012-2zm8-2v2H7V7a3 3 0 016 0z" clip-rule="evenodd" />
        </svg>
        <span>Your payment information is secure and encrypted</span>
      </div>
    </form>
  </div>
</template>

<script setup>
import { reactive, ref } from 'vue'
import LoadingSpinner from '../UI/LoadingSpinner.vue'

const props = defineProps({
  amount: {
    type: Number,
    required: true
  },
  submitText: {
    type: String,
    default: 'Process Payment'
  },
  user: {
    type: Object,
    default: () => ({})
  }
})

const emit = defineEmits(['submit'])

const loading = ref(false)
const errors = ref({})

const form = reactive({
  card: {
    number: '',
    expiry: '',
    cvv: '',
    holder_name: ''
  },
  billing: {
    first_name: props.user.first_name || '',
    last_name: props.user.last_name || '',
    email: props.user.email || '',
    address: {
      line1: '',
      line2: '',
      city: '',
      state: '',
      postal_code: '',
      country: 'ZA'
    }
  }
})

const formatCardNumber = (event) => {
  let value = event.target.value.replace(/\s/g, '').replace(/[^0-9]/gi, '')
  let formattedValue = value.match(/.{1,4}/g)?.join(' ') || value
  form.card.number = formattedValue
}

const formatExpiry = (event) => {
  let value = event.target.value.replace(/\D/g, '')
  if (value.length >= 2) {
    value = value.substring(0, 2) + '/' + value.substring(2, 4)
  }
  form.card.expiry = value
}

const submitPayment = () => {
  loading.value = true
  errors.value = {}

  // Parse expiry date
  if (form.card.expiry) {
    const [month, year] = form.card.expiry.split('/')
    form.card.expiry_month = month?.padStart(2, '0')
    form.card.expiry_year = year ? `20${year}` : ''
  }

  // Clean card number
  form.card.number = form.card.number.replace(/\s/g, '')

  emit('submit', {
    ...form,
    amount: props.amount
  })

  // Reset loading state (parent should handle this)
  setTimeout(() => {
    loading.value = false
  }, 1000)
}
</script>
