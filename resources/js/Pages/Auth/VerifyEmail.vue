<template>
  <AuthLayout :status="status" :errors="errors">
    <div class="mb-4 text-sm text-gray-600">
      Thanks for signing up! Before getting started, could you verify your email address by clicking on the link we just emailed to you? If you didn't receive the email, we will gladly send you another.
    </div>

    <div v-if="verificationLinkSent" class="mb-4 font-medium text-sm text-green-600">
      A new verification link has been sent to the email address you provided during registration.
    </div>

    <div class="mt-4 flex items-center justify-between">
      <form @submit.prevent="submit">
        <button
          type="submit"
          class="inline-flex items-center px-4 py-2 bg-gray-800 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700 focus:bg-gray-700 active:bg-gray-900 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 transition ease-in-out duration-150"
          :class="{ 'opacity-25': form.processing }"
          :disabled="form.processing"
        >
          <span v-if="form.processing" class="mr-2">
            <svg class="animate-spin h-4 w-4" fill="none" viewBox="0 0 24 24">
              <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
              <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
            </svg>
          </span>
          Resend Verification Email
        </button>
      </form>

      <form @submit.prevent="logout">
        <button
          type="submit"
          class="underline text-sm text-gray-600 hover:text-gray-900 rounded-md focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500"
        >
          Log Out
        </button>
      </form>
    </div>
  </AuthLayout>
</template>

<script setup>
import { computed } from 'vue'
import { useForm } from '@inertiajs/vue3'
import AuthLayout from '../../Components/Auth/AuthLayout.vue'

const props = defineProps({
  status: String,
  errors: Object,
})

const form = useForm({})

const verificationLinkSent = computed(() => props.status === 'verification-link-sent')

const submit = () => {
  form.post(route('verification.send'))
}

const logout = () => {
  form.post(route('logout'))
}
</script>
