import { ref } from 'vue'

export const pageTransition = ref('')

export function navigateWithTransition(router, to, direction) {
  pageTransition.value = direction === 'right' ? 'slide-right' : 'slide-left'
  router.push(to)
}

export function resetPageTransition() {
  pageTransition.value = ''
}
