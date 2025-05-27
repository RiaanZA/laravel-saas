import { ref, reactive } from 'vue'

export function useErrorHandling() {
  const showError = ref(false)
  const errorData = reactive({
    message: '',
    errors: []
  })

  const handleInertiaError = (errors, defaultMessage = 'An error occurred') => {
    showError.value = true
    
    if (typeof errors === 'string') {
      // Simple string error
      errorData.message = errors
      errorData.errors = []
    } else if (typeof errors === 'object' && errors !== null) {
      // Inertia error object or validation errors
      if (errors.message) {
        errorData.message = errors.message
      } else {
        errorData.message = defaultMessage
      }
      
      // Handle validation errors
      if (errors.errors) {
        errorData.errors = Object.values(errors.errors).flat()
      } else if (Array.isArray(errors)) {
        errorData.errors = errors
      } else {
        errorData.errors = []
      }
    } else {
      errorData.message = defaultMessage
      errorData.errors = []
    }
  }

  const clearError = () => {
    showError.value = false
    errorData.message = ''
    errorData.errors = []
  }

  const getErrorMessage = () => {
    return errorData.message
  }

  const getErrorList = () => {
    return errorData.errors
  }

  return {
    showError,
    handleInertiaError,
    clearError,
    getErrorMessage,
    getErrorList
  }
}
