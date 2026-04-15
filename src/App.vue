<template>
  <router-view v-slot="{ Component }">
    <keep-alive include="Home">
      <component :is="Component" />
    </keep-alive>
  </router-view>
</template>

<script setup>
import { onMounted } from 'vue';

onMounted(() => {
  const savedTheme = localStorage.getItem('app-theme');
  if (savedTheme === 'dark') {
    document.body.classList.add('dark-theme');
  } else {
    document.body.classList.remove('dark-theme');
  }
});
</script>

<style>
/* 可以在此定义全局样式或引入原有的通用CSS */
</style>
