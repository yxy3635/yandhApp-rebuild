<template>
  <router-view v-slot="{ Component }">
    <keep-alive include="Home">
      <component :is="Component" />
    </keep-alive>
  </router-view>
  <BottomNav />
</template>

<script setup>
import { onMounted } from 'vue'
import BottomNav from './components/BottomNav.vue'

onMounted(() => {
  const savedTheme = localStorage.getItem('app-theme')
  if (savedTheme === 'dark') {
    document.documentElement.classList.add('dark-theme')
    document.body.classList.add('dark-theme')
  } else {
    document.documentElement.classList.remove('dark-theme')
    document.body.classList.remove('dark-theme')
  }
})
</script>
