<template>
  <div class="interaction-container">
    <AppHeader title="互动列表" />
    
    <main class="interaction-main">
      <div class="interaction-section-title">记录</div>
      
      <div class="interaction-fullcard interaction-animate" @click="goTo('/userlist')" style="position:relative;">
        <span class="interaction-icon">💬</span>
        <span class="interaction-title">聊天</span>
        <span class="interaction-desc">我们的悄悄话</span>
        <span class="interaction-arrow">›</span>
        <span v-if="hasUnreadChat" class="interaction-exclaim">！</span>
      </div>
      
      <div class="interaction-fullcard interaction-animate" @click="goTo('/diary')">
        <span class="interaction-icon">📝</span>
        <span class="interaction-title">手记</span>
        <span class="interaction-desc">记录美好时光</span>
        <span class="interaction-arrow">›</span>
      </div>
      
      <div class="interaction-section-title">游戏</div>
      
      <div class="interaction-fullcard interaction-animate" @click="goTo('/gomoku')">
        <span class="interaction-icon">♟️</span>
        <span class="interaction-title">下棋</span>
        <span class="interaction-desc">实时对战，虐死你</span>
        <span class="interaction-arrow">›</span>
      </div>
      
      <div class="interaction-fullcard interaction-animate" @click="goTo('/gomoku-cracked')">
        <span class="interaction-icon">🎯</span>
        <span class="interaction-title">下棋</span>
        <span class="interaction-desc">破解版</span>
        <span class="interaction-arrow">›</span>
      </div>
      
      <div class="interaction-fullcard interaction-animate" @click="goTo('/draw-guess')">
        <span class="interaction-icon">🎨</span>
        <span class="interaction-title">画画</span>
        <span class="interaction-desc">你画我猜</span>
        <span class="interaction-arrow">›</span>
      </div>
      
      <div class="interaction-fullcard interaction-animate" @click="goTo('/shoot-guess')">
        <span class="interaction-icon">🏹</span>
        <span class="interaction-title">射履</span>
        <span class="interaction-desc">以为是射箭？</span>
        <span class="interaction-arrow">›</span>
      </div>
      
      <div class="interaction-fullcard interaction-animate" @click="goTo('/pindou')">
        <span class="interaction-icon">🧩</span>
        <span class="interaction-title">拼豆</span>
        <span class="interaction-desc">生成图纸</span>
        <span class="interaction-arrow">›</span>
      </div>
    </main>

  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import { useRouter } from 'vue-router';
import { APP_CONFIG, commonFetch } from '../utils/config';

const router = useRouter();
const hasUnreadChat = ref(false);

const goTo = (path) => {
  router.push(path);
};

onMounted(() => {
  checkUnreadChat();
});

const checkUnreadChat = async () => {
  const currentUserId = localStorage.getItem('user_id');
  if (!currentUserId) return;
  try {
    const data = await commonFetch(`${APP_CONFIG.API_BASE}/user_list.php?current_user_id=${currentUserId}`);
    if (data.success && Array.isArray(data.users)) {
      hasUnreadChat.value = data.users.some(user => user.unread_count > 0);
    }
  } catch (error) {
    console.error('Error checking unread chat:', error);
  }
};
</script>

<style scoped>
.interaction-container {
  min-height: 100vh;
}

.interaction-main {
  padding: 80px 8px 120px 8px; /* 顶部 80px 避开固定 header，底部 120px 避开导航栏 */
  min-height: 100vh;
  box-sizing: border-box;
}

/* Add any interaction specific styles here */
</style>
