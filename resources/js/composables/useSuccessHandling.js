import { ref } from 'vue'

export function useSuccessHandling() {
  const successMessage = ref('')
  const showSuccess = ref(false)

  const handleSuccess = (message) => {
    successMessage.value = message
    showSuccess.value = true
  }

  const clearSuccess = () => {
    successMessage.value = ''
    showSuccess.value = false
  }

  return {
    successMessage,
    showSuccess,
    handleSuccess,
    clearSuccess
  }
}
