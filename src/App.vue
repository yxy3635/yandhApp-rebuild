<template>
  <!-- SVG 噪声滤镜 — 在所有平台模拟毛玻璃纹理，弥补 backdrop-filter 在 Android WebView 不可用的问题 -->
  <svg style="display:none" aria-hidden="true">
    <filter id="frosted-glass" x="0%" y="0%" width="100%" height="100%">
      <feTurbulence type="fractalNoise" baseFrequency="0.65" numOctaves="3" stitchTiles="stitch" result="noise" />
      <feColorMatrix type="saturate" values="0" in="noise" result="gray" />
      <feComponentTransfer in="gray" result="softNoise">
        <feFuncA type="linear" slope="0.07" />
      </feComponentTransfer>
      <feBlend in="SourceGraphic" in2="softNoise" mode="screen" />
    </filter>
  </svg>

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
    document.body.classList.add('dark-theme')
  } else {
    document.body.classList.remove('dark-theme')
  }
})
</script>
