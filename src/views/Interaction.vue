<template>
  <div class="interaction-container">
    <AppHeader title="互动列表" />
    
    <main class="interaction-main">
      <div class="interaction-section-title">记录</div>
      
      <div class="interaction-fullcard interaction-animate" @click="handleChatClick" style="position:relative;">
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

      <div class="interaction-fullcard interaction-animate" @click="goTo('/footprints')">
        <span class="interaction-icon">🗺️</span>
        <span class="interaction-title">足迹</span>
        <span class="interaction-desc">我们的旅行地图</span>
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

    <!-- 选择聊天用户弹窗 -->
    <Teleport to="body">
      <div class="chat-pick-overlay" v-if="showUserPick" @click.self="showUserPick = false">
        <div class="chat-pick-modal">
          <div class="chat-pick-title">选择聊天对象</div>
          <LoadingSpinner v-if="pickLoading" text="加载中..." />
          <div v-else class="chat-pick-list">
            <div v-for="user in pickUsers" :key="user.id" class="chat-pick-item" @click="pickUser(user)">
              <img class="chat-pick-avatar" :src="getAvatarUrl(user.avatar_url)" alt="头像" @error="e => e.target.src = defaultAvatar">
              <span class="chat-pick-name">{{ user.username }}</span>
              <span v-if="user.unread_count > 0" class="chat-pick-badge">{{ user.unread_count }}</span>
            </div>
          </div>
          <button class="chat-pick-close" @click="showUserPick = false">取消</button>
        </div>
      </div>
    </Teleport>

  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import { useRouter } from 'vue-router';
import { APP_CONFIG, commonFetch, getAvatarUrl } from '../utils/config';
import defaultAvatar from '../assets/img/default-avatar.png';
import LoadingSpinner from '../components/LoadingSpinner.vue';

const router = useRouter();
const hasUnreadChat = ref(false);

// 用户选择弹窗
const showUserPick = ref(false);
const pickUsers = ref([]);
const pickLoading = ref(false);

const goTo = (path) => {
  router.push(path);
};

// 聊天入口：优先使用已保存的用户，否则弹出选择
const handleChatClick = () => {
  const saved = getSavedChatUser();
  if (saved) {
    router.push({
      path: '/chat',
      query: { user_id: saved.id, username: saved.username, avatar: saved.avatar || '' }
    });
  } else {
    openUserPicker();
  }
};

const getSavedChatUser = () => {
  try {
    const raw = localStorage.getItem('saved_chat_user');
    if (raw) return JSON.parse(raw);
  } catch (_) {}
  return null;
};

const saveChatUser = (user) => {
  localStorage.setItem('saved_chat_user', JSON.stringify({
    id: user.id,
    username: user.username,
    avatar: getAvatarUrl(user.avatar_url)
  }));
};

// 打开用户选择器
const openUserPicker = async () => {
  showUserPick.value = true;
  pickLoading.value = true;
  const currentUserId = localStorage.getItem('user_id');
  try {
    const data = await commonFetch(`${APP_CONFIG.API_BASE}/user_list.php?current_user_id=${currentUserId}`);
    if (data.success && Array.isArray(data.users)) {
      pickUsers.value = data.users.filter(u => u.id != currentUserId);
    }
  } catch (e) {
    console.error(e);
  } finally {
    pickLoading.value = false;
  }
};

const pickUser = (user) => {
  saveChatUser(user);
  showUserPick.value = false;
  router.push({
    path: '/chat',
    query: { user_id: user.id, username: user.username, avatar: getAvatarUrl(user.avatar_url) }
  });
};

onMounted(() => {
  checkUnreadChat();
  // 预加载聊天组件，消除首次点击的延迟
  import('../views/Chat.vue').catch(() => {});
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

/* 聊天用户选择弹窗 */
.chat-pick-overlay {
  position: fixed;
  inset: 0;
  background: rgba(0,0,0,0.5);
  z-index: 2000;
  display: flex;
  align-items: center;
  justify-content: center;
  animation: fadeIn 0.2s ease;
}
@keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }

.chat-pick-modal {
  background: #fff;
  border-radius: 16px;
  width: 88vw;
  max-width: 400px;
  max-height: 70vh;
  overflow-y: auto;
  padding: 20px 0 12px 0;
  box-shadow: 0 8px 40px rgba(0,0,0,0.2);
}
body.dark-theme .chat-pick-modal {
  background: var(--bg-color-card, #3a404b);
}

.chat-pick-title {
  font-size: 18px;
  font-weight: 700;
  text-align: center;
  margin-bottom: 12px;
  color: #000;
}
body.dark-theme .chat-pick-title {
  color: var(--text-color-light, #e0e0e0);
}

.chat-pick-list {
  max-height: 50vh;
  overflow-y: auto;
}

.chat-pick-item {
  display: flex;
  align-items: center;
  padding: 14px 20px;
  cursor: pointer;
  transition: background 0.15s;
  gap: 12px;
}
.chat-pick-item:hover {
  background: #f5f5f5;
}
body.dark-theme .chat-pick-item:hover {
  background: rgba(255,255,255,0.05);
}

.chat-pick-avatar {
  width: 44px;
  height: 44px;
  border-radius: 50%;
  object-fit: cover;
}
.chat-pick-name {
  flex: 1;
  font-size: 16px;
  font-weight: 500;
  color: #000;
}
body.dark-theme .chat-pick-name {
  color: var(--text-color-light, #e0e0e0);
}
.chat-pick-badge {
  background: #ff3b30;
  color: #fff;
  border-radius: 10px;
  padding: 2px 8px;
  font-size: 12px;
  font-weight: 600;
}

.chat-pick-close {
  display: block;
  margin: 12px auto 0;
  background: #f0f0f0;
  border: none;
  border-radius: 8px;
  padding: 10px 40px;
  font-size: 15px;
  cursor: pointer;
  color: #666;
}
body.dark-theme .chat-pick-close {
  background: rgba(255,255,255,0.08);
  color: var(--text-color-medium, #bbb);
}
</style>
