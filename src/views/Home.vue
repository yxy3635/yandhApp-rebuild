<template>
  <div class="home-container">
    <header class="home-header">
      <div class="header-title">
        <span class="header-emoji">🌟</span>
        <span class="header-main">YandH 小窝</span>
      </div>
      <button id="refresh-btn" @click="refreshFeed">刷新动态</button>
      <button id="post-btn" @click="goPost">发布动态</button>
    </header>
    
    <main id="feed-list">
      <div v-if="loading" class="loading-message">正在加载动态...</div>
      <div v-else-if="feedData.length === 0" class="empty-message">暂无动态</div>
      <template v-else>
        <div class="feed-item" v-for="item in feedData" :key="item.id" @click="goDetail(item.id)">
          <img class="feed-avatar" :src="item.avatar || defaultAvatar" alt="头像" @click.stop="onAvatarClick(item.user_id)">
          <div class="feed-content">
            <div class="feed-header">
              <span class="feed-username" @click.stop="onUserClick(item.user_id)">{{ item.user }}</span>
              <div class="feed-actions" v-if="currentUserId === String(item.user_id)">
                <button @click.stop="editPost(item.id)">编辑</button>
                <button @click.stop="deletePost(item.id)">删除</button>
              </div>
            </div>
            <div class="feed-text">{{ item.content }}</div>
            
            <div class="feed-media" v-if="getMedia(item)">
              <img v-if="getMedia(item).type === 'image'" :src="getMedia(item).url" class="feed-media-item" loading="lazy">
              <video v-if="getMedia(item).type === 'video'" :src="getMedia(item).url" class="feed-media-item feed-video" controls playsinline muted preload="metadata"></video>
            </div>
            
            <span class="feed-time">{{ formatTime(item.time) }}</span>
          </div>
        </div>
      </template>

      <!-- 分页控件 -->
      <div id="pagination-controls" class="pagination-controls">
        <div class="pagination-info" v-if="feedData.length > 0">已加载 {{ feedData.length }} 条动态，当前第 {{ currentPage }} 页</div>
        <button class="load-more-btn" v-if="hasMoreData && !loadingMore" @click="loadMoreFeed">加载更多</button>
        <button class="load-more-btn loading" v-else-if="loadingMore" disabled>加载中...</button>
        <div class="no-more-data" v-else-if="feedData.length > 0">没有更多动态了</div>
      </div>
    </main>

    <!-- 用户信息卡片 -->
    <div class="user-info-card" :class="{ show: showUserInfo }" v-if="selectedUser" @click="closeUserInfo">
      <div class="user-info-overlay"></div>
      <div class="user-info-content" @click.stop>
        <div class="user-info-header">
          <div class="user-avatar-container">
            <img :src="getAvatarUrl(selectedUser.avatar_url)" alt="头像" class="user-info-avatar">
            <div class="online-status" :class="onlineStatus.class"></div>
          </div>
          <div class="user-basic-info">
            <h3 class="user-info-username">{{ selectedUser.username || '' }}</h3>
            <p class="user-info-signature">{{ selectedUser.signature || '这个人很神秘，还没有写签名~' }}</p>
          </div>
          <button class="close-btn" @click="closeUserInfo">×</button>
        </div>
        <div class="user-info-footer">
          <div class="last-online-info">
            <span class="last-online-icon">{{ onlineStatus.icon }}</span>
            <span class="last-online-text">{{ onlineStatus.text }}</span>
          </div>
          <div class="registration-info" v-if="selectedUser.created_at">
            <span class="registration-icon">📅</span>
            <span class="registration-text">注册于 {{ formatRegTime(selectedUser.created_at) }}</span>
          </div>
        </div>
      </div>
    </div>

    <nav id="bottom-nav">
      <button class="active" @click="goHome">主页</button>
      <button @click="goInteraction">互动</button>
      <button @click="goAnniversary">纪念日</button>
      <button @click="goProfile">我的</button>
      <button v-if="isAdmin" @click="goAdmin">管理</button>
    </nav>
  </div>
</template>

<script>
export default {
  name: 'Home'
}
</script>

<script setup>
import { ref, onMounted, onBeforeUnmount, onActivated, onDeactivated } from 'vue';
import { useRouter } from 'vue-router';
import { APP_CONFIG, commonFetch, getAvatarUrl } from '../utils/config';
import defaultAvatar from '../assets/img/default-avatar.png';
import { customAlert, customConfirm, showModal } from '../utils/modal';

const router = useRouter();

const currentUserId = ref(localStorage.getItem('user_id'));
const isAdmin = ref(localStorage.getItem('username') === 'admin');

const feedData = ref([]);
const loading = ref(false);
const loadingMore = ref(false);
const currentPage = ref(1);
const pageSize = 5;
const hasMoreData = ref(true);

let onlineStatusInterval = null;
let homeUnreadDebounceTimer = null;

function scheduleHomeUnreadPrompts() {
  if (!currentUserId.value) return;
  if (homeUnreadDebounceTimer) clearTimeout(homeUnreadDebounceTimer);
  homeUnreadDebounceTimer = setTimeout(() => {
    homeUnreadDebounceTimer = null;
    if (router.currentRoute.value.path !== '/home') return;
    runHomeUnreadPrompts();
  }, 400);
}

onMounted(() => {
  if (!currentUserId.value) {
    router.push('/');
    return;
  }
  refreshFeed();
  startOnlineStatusUpdate();
  scheduleHomeUnreadPrompts();
});

/** keep-alive：从其它页返回主页时再检查；与 onMounted 合并防抖，避免首进连弹两次 */
onActivated(() => {
  scheduleHomeUnreadPrompts();
});

onDeactivated(() => {
  if (homeUnreadDebounceTimer) {
    clearTimeout(homeUnreadDebounceTimer);
    homeUnreadDebounceTimer = null;
  }
});

onBeforeUnmount(() => {
  if (onlineStatusInterval) clearInterval(onlineStatusInterval);
  if (lastOnlineUpdateTimer) clearInterval(lastOnlineUpdateTimer);
});

const refreshFeed = async () => {
  currentPage.value = 1;
  hasMoreData.value = true;
  loading.value = true;
  feedData.value = [];
  
  try {
    const result = await fetchFeedData(1, pageSize);
    if (result.feeds && result.feeds.length > 0) {
      feedData.value = result.feeds;
      hasMoreData.value = result.hasMore;
    }
  } catch (error) {
    console.error('刷新动态失败:', error);
  } finally {
    loading.value = false;
  }
};

const loadMoreFeed = async () => {
  if (loadingMore.value || !hasMoreData.value) return;
  
  loadingMore.value = true;
  try {
    const nextPage = currentPage.value + 1;
    const result = await fetchFeedData(nextPage, pageSize);
    
    if (result.feeds && result.feeds.length > 0) {
      feedData.value = feedData.value.concat(result.feeds);
      currentPage.value = nextPage;
      hasMoreData.value = result.hasMore;
    } else {
      hasMoreData.value = false;
    }
  } catch (error) {
    console.error('加载更多动态失败:', error);
  } finally {
    loadingMore.value = false;
  }
};

const userAvatarCache = new Map();

const fetchFeedData = async (page = 1, limit = pageSize) => {
  try {
    const data = await commonFetch(`${APP_CONFIG.API_BASE}/feed.php?page=${page}&limit=${limit}`);
    
    const enhanceFeed = async (feeds) => {
      return await Promise.all(feeds.map(async item => {
        // Format media URLs just in case they are relative
        if (item.media_urls && Array.isArray(item.media_urls)) {
          item.media_urls = item.media_urls.map(m => ({
            ...m,
            url: m.url.startsWith('http') ? m.url : `${APP_CONFIG.SERVER_BASE}/${m.url}`
          }));
        } else if (item.media_url) {
          item.media_url = item.media_url.startsWith('http') ? item.media_url : `${APP_CONFIG.SERVER_BASE}/${item.media_url}`;
        }

        if (item.user_id) {
          if (userAvatarCache.has(item.user_id)) {
            item.avatar = userAvatarCache.get(item.user_id);
          } else {
             try {
               const profileData = await commonFetch(`${APP_CONFIG.API_BASE}/profile.php?user_id=${item.user_id}`);
               if (profileData.success && profileData.user) {
                 const finalAvatar = getAvatarUrl(profileData.user.avatar_url);
                 item.avatar = finalAvatar;
                 userAvatarCache.set(item.user_id, finalAvatar);
               } else {
                 item.avatar = defaultAvatar;
                 userAvatarCache.set(item.user_id, defaultAvatar);
               }
             } catch(e) {
               item.avatar = defaultAvatar;
               userAvatarCache.set(item.user_id, defaultAvatar);
             }
          }
        } else if (item.avatar) {
           item.avatar = getAvatarUrl(item.avatar);
        }
        return item;
      }));
    };

    if (data.success && data.feeds && Array.isArray(data.feeds)) {
      const enhancedFeeds = await enhanceFeed(data.feeds);
      return {
        feeds: enhancedFeeds,
        pagination: data.pagination || {},
        hasMore: data.pagination ? data.pagination.page < data.pagination.pages : false
      };
    } else if (Array.isArray(data)) {
      const enhancedFeeds = await enhanceFeed(data);
      return {
        feeds: enhancedFeeds,
        pagination: {},
        hasMore: data.length === limit
      };
    }
    return { feeds: [], pagination: {}, hasMore: false };
  } catch (error) {
    console.error('获取动态数据失败:', error);
    return { feeds: [], pagination: {}, hasMore: false };
  }
};

const formatTime = (timestamp) => {
  if (!timestamp) return '';
  const now = new Date();
  const postDate = new Date(timestamp);
  const diff = now.getTime() - postDate.getTime();
  
  const seconds = Math.floor(diff / 1000);
  const minutes = Math.floor(seconds / 60);
  const hours = Math.floor(minutes / 60);
  const days = Math.floor(hours / 24);
  
  const year = postDate.getFullYear();
  const month = String(postDate.getMonth() + 1).padStart(2, '0');
  const day = String(postDate.getDate()).padStart(2, '0');
  const hour = String(postDate.getHours()).padStart(2, '0');
  const minute = String(postDate.getMinutes()).padStart(2, '0');
  const second = String(postDate.getSeconds()).padStart(2, '0');
  const fullTimeStr = `${year}-${month}-${day} ${hour}:${minute}:${second}`;
  
  if (seconds < 60) return `刚刚 (${fullTimeStr})`;
  if (minutes < 60) return `${minutes}分钟前 (${fullTimeStr})`;
  if (hours < 24) return `${hours}小时前 (${fullTimeStr})`;
  if (days < 7) return `${days}天前 (${fullTimeStr})`;
  if (days < 30) return `${Math.floor(days / 7)}周前 (${fullTimeStr})`;
  if (days < 365) return `${Math.floor(days / 30)}个月前 (${fullTimeStr})`;
  return `${Math.floor(days / 365)}年前 (${fullTimeStr})`;
};

const getMedia = (item) => {
  if (item.media_urls && Array.isArray(item.media_urls) && item.media_urls.length > 0) {
    return item.media_urls[0];
  } else if (item.media_url) {
    return { type: item.type, url: item.media_url };
  }
  return null;
};

const deletePost = async (postId) => {
  if (!await customConfirm('确定要删除这条动态吗？此操作不可撤销！')) return;
  
  try {
    const data = await commonFetch(`${APP_CONFIG.API_BASE}/delete_post.php`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ post_id: postId, user_id: parseInt(currentUserId.value) })
    });
    
    if (data.success) {
      customAlert('删除成功');
      refreshFeed();
    } else {
      customAlert(data.message || '删除失败');
    }
  } catch (error) {
    customAlert('删除动态失败');
  }
};

const goDetail = (id) => router.push(`/detail?post_id=${id}`);
const editPost = (id) => router.push(`/post?post_id=${id}`);
const goPost = () => router.push('/post');
const goHome = () => router.push('/home');
const goInteraction = () => router.push('/interaction');
const goAnniversary = () => router.push('/anniversary');
const goProfile = () => router.push('/profile');
const goAdmin = () => router.push('/admin');

const showUserInfo = ref(false);
const selectedUser = ref(null);
const onlineStatus = ref({ class: 'offline', text: '从未在线', icon: '⭕' });

let lastOnlineUpdateTimer = null;

const formatRegTime = (dateStr) => {
  if (!dateStr) return '';
  const d = new Date(dateStr);
  return `${d.getFullYear()}-${String(d.getMonth()+1).padStart(2,'0')}-${String(d.getDate()).padStart(2,'0')} ${String(d.getHours()).padStart(2,'0')}:${String(d.getMinutes()).padStart(2,'0')}:${String(d.getSeconds()).padStart(2,'0')}`;
};

const updateLastOnlineText = (lastOnlineTime) => {
  if (!lastOnlineTime) {
    onlineStatus.value = { class: 'offline', text: '从未在线', icon: '⭕' };
    return;
  }
  
  const lastOnlineDate = new Date(lastOnlineTime);
  const now = new Date();
  const diffSeconds = Math.floor((now - lastOnlineDate) / 1000);
  
  const fullTimeStr = formatRegTime(lastOnlineTime);
  
  let sClass = 'offline';
  let sText = '';
  let sIcon = '⭕';
  
  if (diffSeconds < 60) {
    sClass = 'online';
    sText = `${diffSeconds}秒前在线 (${fullTimeStr})`;
    sIcon = '🟢';
  } else if (diffSeconds < 3600) {
    sClass = 'online';
    const minutes = Math.floor(diffSeconds / 60);
    const seconds = diffSeconds % 60;
    sText = `${minutes}分${seconds}秒前在线 (${fullTimeStr})`;
    sIcon = '🟢';
  } else if (diffSeconds < 86400) {
    sClass = 'recently';
    const hours = Math.floor(diffSeconds / 3600);
    const minutes = Math.floor((diffSeconds % 3600) / 60);
    sText = `${hours}小时${minutes}分前在线 (${fullTimeStr})`;
    sIcon = '🟡';
  } else {
    const days = Math.floor(diffSeconds / 86400);
    if (days < 7) {
      const hours = Math.floor((diffSeconds % 86400) / 3600);
      sText = `${days}天${hours}小时前在线 (${fullTimeStr})`;
    } else {
      sText = `${fullTimeStr}`;
    }
  }
  
  onlineStatus.value = { class: sClass, text: sText, icon: sIcon };
};

const onAvatarClick = async (id) => {
  try {
    const response = await commonFetch(`${APP_CONFIG.API_BASE}/profile.php?user_id=${id}`);
    if (response.success && response.user) {
      selectedUser.value = response.user;
      setTimeout(() => {
        showUserInfo.value = true;
      }, 10);
      updateLastOnlineText(response.user.last_online);
      
      if (lastOnlineUpdateTimer) clearInterval(lastOnlineUpdateTimer);
      if (response.user.last_online) {
        lastOnlineUpdateTimer = setInterval(() => {
          updateLastOnlineText(response.user.last_online);
        }, 1000);
      }
    } else {
      customAlert('获取用户信息失败');
    }
  } catch(e) {
    customAlert('网络错误，无法获取用户信息');
  }
};
const onUserClick = (id) => onAvatarClick(id);

const closeUserInfo = () => {
  showUserInfo.value = false;
  setTimeout(() => {
    selectedUser.value = null;
    if (lastOnlineUpdateTimer) {
      clearInterval(lastOnlineUpdateTimer);
      lastOnlineUpdateTimer = null;
    }
  }, 300);
};

const startOnlineStatusUpdate = () => {
  if (!currentUserId.value) return;
  updateOnlineStatus();
  onlineStatusInterval = setInterval(updateOnlineStatus, 5 * 60 * 1000);
};

const updateOnlineStatus = async () => {
  try {
    await fetch(`${APP_CONFIG.API_BASE}/update_online_status.php`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: `user_id=${currentUserId.value}`
    });
  } catch (error) {}
};

/** 未读评论/回复等（notifications 表） */
async function checkUnreadNotifications() {
  try {
    const data = await commonFetch(
      `${APP_CONFIG.API_BASE}/get_notifications.php?user_id=${currentUserId.value}`
    );
    if (!data.success || !data.notifications || data.notifications.length === 0) {
      return;
    }
    const latest = data.notifications[0];
    const go = await showModal(
      latest.content,
      'confirm',
      '新消息提醒',
      '去查看',
      '稍后再看'
    );
    if (go) {
      const postId = latest.post_id;
      if (postId != null && postId !== '') {
        router.push({ path: '/detail', query: { post_id: String(postId) } });
      } else {
        router.push('/interaction');
      }
      commonFetch(`${APP_CONFIG.API_BASE}/mark_notification_read.php?id=${latest.id}`);
    }
  } catch (e) {
    console.error('通知检查失败', e);
  }
}

/** 未读私信：与聊天列表 / 互动页一致，使用 user_list.php 汇总 unread_count（get_unread_messages 与之前不一致时会导致不弹窗） */
async function checkUnreadChatHomePopup() {
  try {
    const data = await commonFetch(
      `${APP_CONFIG.API_BASE}/user_list.php?current_user_id=${currentUserId.value}`
    );
    if (!data.success || !Array.isArray(data.users)) {
      return;
    }
    let n = 0;
    for (const u of data.users) {
      n += Number(u.unread_count) || 0;
    }
    if (n <= 0) {
      return;
    }
    const go = await showModal(
      `你有 ${n} 条未读私信，是否前往聊天列表查看？`,
      'confirm',
      '未读消息',
      '去查看',
      '稍后再说'
    );
    if (go) {
      router.push('/userlist');
    }
  } catch (e) {
    console.error('未读私信检查失败', e);
  }
}

async function runHomeUnreadPrompts() {
  await checkUnreadNotifications();
  await checkUnreadChatHomePopup();
}
</script>

<style scoped>
.home-container {
  min-height: 100vh;
  padding-top: 70px;
  padding-bottom: 90px;
}
</style>
