<template>
  <div class="diary-container">
    <AppHeader title="我们的手记" :showBack="true" />

    <main class="diary-main">
      <div class="diary-stats">
        <div class="stat-item">
          <span class="stat-number">{{ stats.total }}</span>
          <span class="stat-label">总手记</span>
        </div>
        <div class="stat-item">
          <span class="stat-number">{{ stats.month }}</span>
          <span class="stat-label">本月</span>
        </div>
        <div class="stat-item">
          <span class="stat-number">{{ stats.week }}</span>
          <span class="stat-label">本周</span>
        </div>
      </div>

      <div class="new-diary-btn" @click="goNewDiary">
        <span class="new-diary-icon">✏️</span>
        <span class="new-diary-text">写手记</span>
      </div>

      <LoadingSpinner v-if="loading" text="加载手记..." />
      <div v-else-if="allDiaries.length === 0" class="diary-empty">
        <div class="empty-icon">📝</div>
        <div class="empty-title">还没有手记</div>
        <div class="empty-desc">记录下你们的美好时光吧~</div>
        <button class="empty-btn" @click="goNewDiary">写第一篇手记</button>
      </div>
      <div v-else class="diary-list">
        <div class="diary-group" v-for="group in groupedDiaries" :key="group.key">
          <div class="diary-group-title" @click="group.expanded = !group.expanded">
            <span class="diary-group-arrow">{{ group.expanded ? '▼' : '▶' }}</span> {{ group.key }}
          </div>
          <div class="diary-group-content" v-show="group.expanded">
            <div class="diary-item" v-for="diary in group.items" :key="diary.id" @click="viewDiary(diary.id)">
              <div class="diary-header-row">
                <span class="diary-date">{{ formatDate(diary.created_at) }}</span>
                <span class="diary-mood">{{ diary.mood }}</span>
              </div>
              <div class="diary-title">{{ diary.title }}</div>
              <div class="diary-content">{{ truncateText(diary.content, 50) }}</div>
              
              <div class="diary-images" v-if="diary.images && diary.images.length > 0">
                <img v-for="(img, idx) in diary.images.slice(0, 3)" :key="idx" :src="getImageUrl(img)" class="diary-image" loading="lazy">
                <div class="diary-image-more" v-if="diary.images.length > 3">+{{ diary.images.length - 3 }}</div>
              </div>
              
              <div class="diary-footer">
                <div class="diary-author">
                  <img :src="getAvatarUrl(diary.author_avatar)" class="diary-author-avatar" alt="头像">
                  <span>{{ diary.author_name }}</span>
                </div>
                <div class="diary-tags" v-if="diary.tags && diary.tags.length > 0">
                  <span class="diary-tag" v-for="(tag, tidx) in diary.tags.slice(0, 3)" :key="tidx">{{ tag }}</span>
                  <span class="diary-tag" v-if="diary.tags.length > 3">+{{ diary.tags.length - 3 }}</span>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </main>
  </div>
</template>

<script setup>
import { ref, onMounted, computed } from 'vue';
import { useRouter } from 'vue-router';
import { APP_CONFIG, commonFetch, getAvatarUrl } from '../utils/config';

const router = useRouter();
const currentUserId = ref(localStorage.getItem('user_id'));
const allDiaries = ref([]);
const loading = ref(true);

const stats = ref({ total: 0, month: 0, week: 0 });
const groupedDiaries = ref([]);

onMounted(() => {
  if (!currentUserId.value) {
    router.push('/');
    return;
  }
  loadDiaries();
});

const goBack = () => {
  router.push('/home'); // Or router.back() based on preference
};

const goNewDiary = () => {
  router.push('/diary/edit');
};

const viewDiary = (id) => {
  router.push(`/diary/detail?id=${id}`);
};

const getImageUrl = (img) => {
  if (!img) return '';
  if (img.startsWith('http')) return img;
  return `${APP_CONFIG.SERVER_BASE}/${img.replace(/^\//, '')}`;
};

const truncateText = (text, length) => {
  if (!text) return '';
  return text.length > length ? text.substring(0, length) + '...' : text;
};

const formatDate = (timestamp) => {
  if (!timestamp) return '';
  const date = new Date(timestamp);
  const now = new Date();
  
  const dateY = date.getFullYear(), dateM = date.getMonth(), dateD = date.getDate();
  const nowY = now.getFullYear(), nowM = now.getMonth(), nowD = now.getDate();
  
  const timeStr = String(date.getHours()).padStart(2, '0') + ':' + String(date.getMinutes()).padStart(2, '0');
  
  if (dateY === nowY && dateM === nowM && dateD === nowD) {
    return '今天 ' + timeStr;
  }
  
  const yesterday = new Date(nowY, nowM, nowD - 1);
  if (dateY === yesterday.getFullYear() && dateM === yesterday.getMonth() && dateD === yesterday.getDate()) {
    return '昨天 ' + timeStr;
  }
  
  return `${dateY}年${dateM + 1}月${dateD}日 ${timeStr}`;
};

const loadDiaries = async () => {
  loading.value = true;
  try {
    const data = await commonFetch(`${APP_CONFIG.API_BASE}/diaries.php?user_id=${currentUserId.value}`);
    if (data.success && data.diaries) {
      allDiaries.value = data.diaries.sort((a, b) => b.timestamp - a.timestamp);
      processDiaries();
    }
  } catch (error) {
    console.error(error);
  } finally {
    loading.value = false;
  }
};

const processDiaries = () => {
  const diaries = allDiaries.value;
  
  const now = new Date();
  const thisMonth = new Date(now.getFullYear(), now.getMonth(), 1);
  const dayOfWeek = now.getDay();
  const daysFromMonday = dayOfWeek === 0 ? 6 : dayOfWeek - 1;
  const thisWeekStart = new Date(now.getFullYear(), now.getMonth(), now.getDate() - daysFromMonday);
  
  stats.value.total = diaries.length;
  stats.value.month = diaries.filter(d => new Date(d.created_at) >= thisMonth).length;
  stats.value.week = diaries.filter(d => new Date(d.created_at) >= thisWeekStart).length;
  
  const groupMap = {};
  diaries.forEach(diary => {
    const date = new Date(diary.created_at);
    const groupKey = `${date.getFullYear()}年${String(date.getMonth() + 1).padStart(2, '0')}月`;
    if (!groupMap[groupKey]) groupMap[groupKey] = [];
    groupMap[groupKey].push(diary);
  });
  
  const currentGroupKey = `${now.getFullYear()}年${String(now.getMonth() + 1).padStart(2, '0')}月`;
  
  groupedDiaries.value = Object.keys(groupMap).sort((a, b) => b.localeCompare(a)).map(key => ({
    key,
    items: groupMap[key],
    expanded: key === currentGroupKey
  }));
  
  if (groupedDiaries.value.length > 0 && !groupedDiaries.value.some(g => g.expanded)) {
    groupedDiaries.value[0].expanded = true;
  }
};

</script>

<style scoped>
.diary-container {
  min-height: 100vh;
  background: var(--bg-color-light, #f6f8fa);
}

.diary-header {
  background: linear-gradient(135deg, #ff6b9d 0%, #ff8fab 100%);
  color: white;
  border-bottom-left-radius: 20px;
  border-bottom-right-radius: 20px;
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 14px 16px;
  position: fixed;
  top: 0; left: 0; right: 0;
  z-index: 100;
  box-shadow: 0 4px 12px var(--shadow-color, rgba(255, 107, 157, 0.3));
}

#back-btn {
  background: transparent;
  color: white;
  border: none;
  font-size: 16px;
  cursor: pointer;
  padding: 8px;
  width: auto;
}
/* #back-btn::before {
  content: '返回';
} */

.diary-header-content {
  display: flex;
  flex-direction: column;
  align-items: center;
  flex: 1;
}

.header-main {
  font-size: 20px;
  font-weight: bold;
}

.diary-subtitle {
  font-size: 14px;
  opacity: 0.9;
  margin-top: 2px;
}

.diary-main {
  padding: 80px 16px 40px 16px;
  max-width: 600px;
  margin: 0 auto;
}

.diary-stats {
  display: flex;
  justify-content: space-around;
  background: var(--bg-color-card, #fff);
  padding: 20px;
  border-radius: 16px;
  margin-bottom: 20px;
  box-shadow: 0 4px 16px var(--shadow-color, rgba(0,0,0,0.05));
}

.stat-item {
  display: flex;
  flex-direction: column;
  align-items: center;
  text-align: center;
}

.stat-number {
  font-size: 28px;
  font-weight: bold;
  color: var(--nav-btn-active-color, #ff6b9d); /* use accent color for dark theme if wanted, but pink is fine, we can use a variable or keep pink */
  margin-bottom: 4px;
}

.stat-label {
  font-size: 14px;
  color: var(--text-color-medium, #888);
}

.new-diary-btn {
  background: linear-gradient(135deg, #ff6b9d 0%, #ff8fab 100%);
  color: white;
  border-radius: 24px;
  padding: 16px 24px;
  font-size: 18px;
  font-weight: bold;
  cursor: pointer;
  box-shadow: 0 6px 20px var(--shadow-color, rgba(255, 107, 157, 0.3));
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
  margin-bottom: 24px;
  transition: transform 0.2s;
}

.new-diary-btn:active {
  transform: scale(0.98);
}

.diary-group-title {
  font-size: 16px;
  font-weight: bold;
  margin: 18px 0 8px 0;
  color: #ff6b9d;
  cursor: pointer;
  user-select: none;
  display: flex;
  align-items: center;
  gap: 6px;
}

.diary-item {
  background: var(--bg-color-card, #fff);
  border-radius: 16px;
  padding: 20px;
  box-shadow: 0 4px 16px var(--shadow-color, rgba(0,0,0,0.05));
  margin-bottom: 16px;
  cursor: pointer;
  border-left: 4px solid #ff6b9d;
  transition: transform 0.2s;
}

.diary-item:active {
  transform: scale(0.98);
}

.diary-header-row {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 12px;
}

.diary-date {
  font-size: 14px;
  color: var(--text-color-medium, #888);
  font-weight: 500;
}

.diary-mood {
  font-size: 20px;
}

.diary-title {
  font-size: 18px;
  font-weight: bold;
  color: var(--text-color-light, #333);
  margin-bottom: 8px;
}

.diary-content {
  font-size: 16px;
  color: var(--text-color-medium, #666);
  line-height: 1.6;
  margin-bottom: 12px;
  word-break: break-all;
}

.diary-images {
  display: flex;
  gap: 8px;
  margin-bottom: 12px;
  overflow-x: auto;
}

.diary-image {
  width: 80px;
  height: 80px;
  border-radius: 8px;
  object-fit: cover;
  flex-shrink: 0;
  border: 2px solid var(--border-color, #eee);
}

.diary-image-more {
  width: 80px;
  height: 80px;
  border-radius: 8px;
  background: var(--bg-color-light, rgba(255, 107, 157, 0.1));
  color: #ff6b9d;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 16px;
  font-weight: bold;
  border: 2px solid var(--border-color, #eee);
}

.diary-footer {
  display: flex;
  justify-content: space-between;
  align-items: center;
  font-size: 14px;
}

.diary-author {
  display: flex;
  align-items: center;
  gap: 6px;
  color: var(--text-color-medium, #666);
}

.diary-author-avatar {
  width: 24px;
  height: 24px;
  border-radius: 50%;
  object-fit: cover;
}

.diary-tags {
  display: flex;
  gap: 6px;
  flex-wrap: wrap;
}

.diary-tag {
  background: var(--bg-color-light, rgba(255, 107, 157, 0.1));
  color: #ff6b9d;
  padding: 4px 8px;
  border-radius: 12px;
  font-size: 12px;
}

.diary-empty {
  text-align: center;
  padding: 60px 20px;
}

.empty-icon {
  font-size: 64px;
  margin-bottom: 20px;
  opacity: 0.6;
}

.empty-title {
  font-size: 24px;
  font-weight: bold;
  color: var(--text-color-light, #333);
  margin-bottom: 12px;
}

.empty-desc {
  font-size: 16px;
  color: var(--text-color-medium, #888);
  margin-bottom: 32px;
}

.empty-btn {
  background: linear-gradient(135deg, #ff6b9d 0%, #ff8fab 100%);
  color: white;
  border: none;
  border-radius: 24px;
  padding: 14px 28px;
  font-size: 16px;
  font-weight: bold;
  cursor: pointer;
  box-shadow: 0 4px 16px var(--shadow-color, rgba(255, 107, 157, 0.3));
}

.diary-loading {
  text-align: center;
  padding: 40px;
  color: var(--text-color-medium, #888);
}
</style>
