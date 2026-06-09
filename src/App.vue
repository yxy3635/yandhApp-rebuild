<template>
  <router-view v-slot="{ Component, route: r }">
    <transition :name="pageTransition" mode="out-in" @after-leave="resetPageTransition">
      <keep-alive include="Home,AiPage">
        <component :is="Component" :key="r.path" />
      </keep-alive>
    </transition>
  </router-view>
  <BottomNav />
</template>

<script setup>
import { onMounted, onUnmounted } from 'vue'
import { useRouter } from 'vue-router'
import BottomNav from './components/BottomNav.vue'
import { pageTransition, resetPageTransition } from './utils/navigation'

const router = useRouter()

function handlePushClick(e) {
  const payload = e.detail
  if (!payload) return

  // 根据推送 payload 跳转到对应页面
  if (payload.type === 'chat' && payload.user_id) {
    router.push({ name: 'Chat', query: { user_id: payload.user_id, username: payload.username || '' } })
  } else if (payload.type === 'interaction') {
    router.push({ name: 'Interaction' })
  } else if (payload.type === 'anniversary') {
    router.push({ name: 'Anniversary' })
  } else if (payload.type === 'home') {
    router.push({ name: 'Home' })
  }
}

onMounted(() => {
  const savedTheme = localStorage.getItem('app-theme')
  if (savedTheme === 'dark') {
    document.documentElement.classList.add('dark-theme')
    document.body.classList.add('dark-theme')
  } else {
    document.documentElement.classList.remove('dark-theme')
    document.body.classList.remove('dark-theme')
  }

  window.addEventListener('push-click', handlePushClick)
})

onUnmounted(() => {
  window.removeEventListener('push-click', handlePushClick)
})
</script>

<style>
/* Route transition animations */
.slide-right-enter-active,
.slide-right-leave-active,
.slide-left-enter-active,
.slide-left-leave-active {
  transition: all 0.35s cubic-bezier(0.25, 0.1, 0.25, 1);
}

/* slide-right: profile enters from left, home exits to right (swipe right on home) */
.slide-right-enter-from {
  opacity: 0;
  transform: translateX(-60px);
}
.slide-right-leave-to {
  opacity: 0;
  transform: translateX(60px);
}

/* slide-left: home enters from right, profile exits to left (swipe left on profile) */
.slide-left-enter-from {
  opacity: 0;
  transform: translateX(60px);
}
.slide-left-leave-to {
  opacity: 0;
  transform: translateX(-60px);
}
</style>
