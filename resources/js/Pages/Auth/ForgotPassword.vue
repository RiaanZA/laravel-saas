<template>
  <AuthLayout :status="status" :errors="errors">
    <div class="mb-4 text-sm text-gray-600">
      Forgot your password? No problem. Just let us know your email address and we will email you a password reset link that will allow you to choose a new one.
    </div>

    <form @submit.prevent="submit">
      <!-- Email Address -->
      <div>
        <label for="email" class="block font-medium text-sm text-gray-700">
          Email
        </label>
        <input
          id="email"
          v-model="form.email"
          type="email"
          class="block mt-1 w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm"
          required
          autofocus
        />
        <div v-if="form.errors.email" class="mt-2 text-sm text-red-600">
          {{ form.errors.email }}
        </div>
      </div>

      <!-- Actions -->
      <div class="flex items-center justify-between mt-4">
        <Link
          :href="route('login')"
          class="underline text-sm text-gray-600 hover:text-gray-900 rounded-md focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500"
        >
          Back to login
        </Link>

        <button
          type="submit"
          class="ml-3 inline-flex items-center px-4 py-2 bg-gray-800 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700 focus:bg-gray-700 active:bg-gray-900 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 transition ease-in-out duration-150"
          :class="{ 'opacity-25': form.processing }"
          :disabled="form.processing"
        >
          <span v-if="form.processing" class="mr-2">
            <svg class="animate-spin h-4 w-4" fill="none" viewBox="0 0 24 24">
              <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
              <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
            </svg>
          </span>
          Email Password Reset Link
        </button>
      </div>
    </form>
  </AuthLayout>
</template>

<script setup>
import { useForm, Link } from '@inertiajs/vue3'
import AuthLayout from '../../Components/Auth/AuthLayout.vue'

defineProps({
  status: String,
  errors: Object,
})

const form = useForm({
  email: '',
})

const submit = () => {
  form.post(route('password.email'))
}
</script>
