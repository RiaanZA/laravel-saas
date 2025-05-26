<template>
  <AuthLayout :errors="errors">
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
          autocomplete="username"
        />
        <div v-if="form.errors.email" class="mt-2 text-sm text-red-600">
          {{ form.errors.email }}
        </div>
      </div>

      <!-- Password -->
      <div class="mt-4">
        <label for="password" class="block font-medium text-sm text-gray-700">
          Password
        </label>
        <input
          id="password"
          v-model="form.password"
          type="password"
          class="block mt-1 w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm"
          required
          autocomplete="new-password"
        />
        <div v-if="form.errors.password" class="mt-2 text-sm text-red-600">
          {{ form.errors.password }}
        </div>
      </div>

      <!-- Confirm Password -->
      <div class="mt-4">
        <label for="password_confirmation" class="block font-medium text-sm text-gray-700">
          Confirm Password
        </label>
        <input
          id="password_confirmation"
          v-model="form.password_confirmation"
          type="password"
          class="block mt-1 w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm"
          required
          autocomplete="new-password"
        />
        <div v-if="form.errors.password_confirmation" class="mt-2 text-sm text-red-600">
          {{ form.errors.password_confirmation }}
        </div>
      </div>

      <!-- Actions -->
      <div class="flex items-center justify-end mt-4">
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
          Reset Password
        </button>
      </div>
    </form>
  </AuthLayout>
</template>

<script setup>
import { useForm } from '@inertiajs/vue3'
import AuthLayout from '../../Components/Auth/AuthLayout.vue'

const props = defineProps({
  token: String,
  email: String,
  errors: Object,
})

const form = useForm({
  token: props.token,
  email: props.email,
  password: '',
  password_confirmation: '',
})

const submit = () => {
  form.post(route('password.store'), {
    onFinish: () => form.reset('password', 'password_confirmation'),
  })
}
</script>
