<template>
  <div class="userlist-container">
    <AppHeader title="聊天列表" :showBack="true" />
    
    <div class="userlist-wave-top" style="margin-top: 64px;">
      <svg viewBox="0 0 500 60" preserveAspectRatio="none" style="width:100%;height:60px;display:block;">
        <path d="M0,30 Q125,60 250,30 T500,30 V60 H0 Z" fill="#e6f0ff" opacity="0.85">
          <animate attributeName="d" dur="4s" repeatCount="indefinite"
            values="M0,30 Q125,60 250,30 T500,30 V60 H0 Z;
                    M0,35 Q125,20 250,35 T500,30 V60 H0 Z;
                    M0,30 Q125,60 250,30 T500,30 V60 H0 Z" />
        </path>
      </svg>
    </div>
    
    <main class="userlist-main">
      <div class="userlist-title">用户列表</div>
      
      <LoadingSpinner v-if="loading" text="加载用户列表..." />
      
      <div v-else-if="users.length === 0" class="userlist-empty">
        暂无其他用户
      </div>
      
      <div v-else class="userlist-list">
        <div v-for="user in users" :key="user.id" class="userlist-item" @click="goChat(user)">
          <img class="userlist-avatar" :src="getAvatarUrl(user.avatar_url)" alt="头像">
          <span class="userlist-username">{{ user.username }}</span>
          <template v-if="user.unread_count > 0">
            <span class="userlist-unread-dot"></span>
            <span class="userlist-unread-text">宝宝有新消息拉~</span>
          </template>
        </div>
      </div>
    </main>

  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import { useRouter } from 'vue-router';
import { APP_CONFIG, commonFetch, getAvatarUrl } from '../utils/config';

const router = useRouter();
const users = ref([]);
const loading = ref(true);

onMounted(() => {
  loadUsers();
});

const loadUsers = async () => {
  const currentUserId = localStorage.getItem('user_id');
  if (!currentUserId) {
    router.push('/');
    return;
  }
  try {
    const data = await commonFetch(`${APP_CONFIG.API_BASE}/user_list.php?current_user_id=${currentUserId}`);
    if (data.success && Array.isArray(data.users)) {
      users.value = data.users.filter(u => u.id != currentUserId);
    }
  } catch (error) {
    console.error('Failed to load users:', error);
  } finally {
    loading.value = false;
  }
};

const goChat = (user) => {
  router.push({
    path: '/chat',
    query: {
      user_id: user.id,
      username: user.username,
      avatar: getAvatarUrl(user.avatar_url)
    }
  });
};

const goBack = () => {
  router.back();
};

</script>

<style scoped>
.userlist-container {
  min-height: 100vh;
  background: var(--bg-color-light, #f6f8fa);
  padding-top: 60px;
  padding-bottom: 100px;
}

.userlist-header {
  background: var(--header-bg, #ffffff);
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 14px 16px;
  position: fixed;
  top: 0; left: 0; right: 0;
  z-index: 100;
  box-shadow: 0 2px 12px var(--shadow-color, rgba(0,0,0,0.05));
}

.header-back-btn {
  background: transparent;
  color: var(--nav-btn-active-color, #4f8cff);
  border: none;
  font-size: 24px;
  font-weight: bold;
  cursor: pointer;
  width: auto;
  text-align: left;
}

.header-main {
  font-size: 18px;
  font-weight: bold;
  color: var(--text-color-light, #333);
  flex: 1;
  text-align: center;
}

.userlist-wave-top {
  position: absolute;
  top: 60px;
  left: 0;
  width: 100%;
  height: 60px;
  z-index: 10;
  margin: 0;
  pointer-events: none;
  user-select: none;
  filter: drop-shadow(0 6px 16px var(--shadow-color, rgba(0,0,0,0.1)));
  animation: waveFadeIn 1.2s cubic-bezier(.4,0,.2,1);
}

.userlist-wave-top svg path {
  fill: var(--bg-color-card, #e6f0ff);
}

@keyframes waveFadeIn {
  from { opacity: 0; transform: translateY(-30px) scaleY(0.7); }
  to   { opacity: 1; transform: none; }
}

.userlist-main {
  position: relative;
  z-index: 20;
  margin-top: 30px;
  background: var(--bg-color-card, #f8fbff);
  border-radius: 24px;
  box-shadow: 0 8px 32px 0 var(--shadow-color, rgba(0,0,0,0.1)), 0 1.5px 8px 0 var(--shadow-color, rgba(0,0,0,0.04));
  min-height: 70vh;
  max-width: 480px;
  margin-left: auto;
  margin-right: auto;
  padding: 20px 0 100px 0;
  animation: userlistPageFadeIn 0.7s cubic-bezier(.4,0,.2,1);
}

.userlist-title {
  font-size: 22px;
  font-weight: bold;
  color: var(--nav-btn-active-color, #2196f3);
  letter-spacing: 1px;
  margin: 0 0 18px 24px;
  padding-top: 10px;
  text-shadow: 0 2px 8px var(--shadow-color, #e3f0ff);
}

.userlist-list {
  border-radius: 18px;
  overflow: hidden;
  background: var(--bg-color-card, #fff);
  box-shadow: 0 2px 12px var(--shadow-color, rgba(0,0,0,0.05));
}

.userlist-item {
  display: flex;
  align-items: center;
  padding: 16px 22px;
  border-bottom: 1px solid var(--border-color, #f0f4fa);
  cursor: pointer;
  background: transparent;
  transition: background 0.25s, box-shadow 0.25s, transform 0.18s;
  animation: userlistFadeIn 0.5s cubic-bezier(.4,0,.2,1);
  position: relative;
  overflow: hidden;
}

.userlist-item:last-child { border-bottom: none; }
.userlist-item:hover {
  background: var(--bg-color-light, #e3f0ff);
  transform: translateY(-2px) scale(1.02);
  box-shadow: 0 4px 16px var(--shadow-color, rgba(0,0,0,0.1));
}

.userlist-item:active {
  background: var(--bg-color-light, #e6f0ff);
  transform: scale(0.98);
}

.userlist-avatar {
  width: 52px;
  height: 52px;
  border-radius: 50%;
  object-fit: cover;
  margin-right: 18px;
  border: 2.5px solid var(--border-color, #e3f0ff);
  box-shadow: 0 2px 8px var(--shadow-color, rgba(0,0,0,0.1));
  transition: transform 0.22s cubic-bezier(.4,0,.2,1), box-shadow 0.22s;
}

.userlist-item:hover .userlist-avatar {
  transform: scale(1.10) rotate(-2deg);
  box-shadow: 0 4px 16px var(--shadow-color, rgba(0,0,0,0.1));
}

.userlist-username {
  font-size: 19px;
  color: var(--text-color-light, #222);
  font-weight: 600;
  letter-spacing: 0.5px;
  transition: color 0.2s;
  text-shadow: 0 1px 4px var(--shadow-color, rgba(0,0,0,0.1));
}

.userlist-empty {
  text-align: center;
  color: var(--text-color-medium, #bbb);
  font-size: 17px;
  margin: 40px 0 0 0;
  letter-spacing: 1px;
}

.userlist-unread-dot {
  display: inline-block;
  width: 10px;
  height: 10px;
  background: #ff3b30;
  border-radius: 50%;
  margin-left: 6px;
  vertical-align: middle;
}

.userlist-unread-text {
  display: block;
  color: #ff3b30;
  font-size: 13px;
  margin-left: 22px;
  margin-top: 2px;
  font-weight: 500;
  letter-spacing: 1px;
}

.loading-message {
  text-align: center;
  padding: 40px 20px;
  color: var(--text-color-medium);
}

@keyframes userlistFadeIn {
  from { opacity: 0; transform: translateY(30px) scale(0.96); }
  to   { opacity: 1; transform: none; }
}

@keyframes userlistPageFadeIn {
  from { opacity: 0; transform: translateY(40px) scale(0.98); }
  to   { opacity: 1; transform: none; }
}
</style>
