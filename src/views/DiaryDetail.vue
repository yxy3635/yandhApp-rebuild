<template>
  <div class="diary-detail-container">
    <AppHeader title="手记详情" :showBack="true" />

    <main class="diary-main">
      <LoadingSpinner v-if="loading" text="加载手记详情..." />
      <div v-else-if="!diary" style="text-align:center; padding: 40px;">手记不存在或无法加载</div>
      <div v-else class="diary-detail">
        <div class="diary-header-row">
          <span class="diary-date">{{ formatDate(diary.created_at) }}</span>
          <span class="diary-mood">{{ diary.mood }}</span>
        </div>
        <div class="diary-title">{{ diary.title }}</div>
        <div class="diary-content" v-html="formattedContent"></div>
        
        <div class="diary-images" v-if="diary.images && diary.images.length > 0">
          <img v-for="(img, index) in diary.images" :key="index" :src="getImageUrl(img)" class="diary-image" @click="showPreview(getImageUrl(img))">
        </div>
        
        <div class="diary-footer">
          <div class="diary-author">
            <img :src="getAvatarUrl(diary.author_avatar)" class="diary-author-avatar" alt="头像">
            <span>{{ diary.author_name }}</span>
          </div>
          <div class="diary-tags" v-if="diary.tags && diary.tags.length > 0">
            <span class="diary-tag" v-for="(tag, idx) in diary.tags" :key="idx">{{ tag }}</span>
          </div>
        </div>

        <div class="diary-detail-actions" v-if="currentUserId === String(diary.user_id)">
          <button class="diary-edit-btn" @click="editDiary">编辑</button>
          <button class="diary-delete-btn" @click="deleteDiary">删除</button>
        </div>
      </div>
    </main>

    <!-- 图片预览 -->
    <div class="image-preview-modal" :class="{ show: previewUrl }" @click="previewUrl = ''">
      <div class="image-preview-backdrop">
        <img v-if="previewUrl" :src="previewUrl" @click.stop>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted, computed } from 'vue';
import { useRouter, useRoute } from 'vue-router';
import { APP_CONFIG, commonFetch, getAvatarUrl } from '../utils/config';
import { customAlert, customConfirm } from '../utils/modal';

const router = useRouter();
const route = useRoute();
const currentUserId = ref(localStorage.getItem('user_id'));

const diaryId = ref(route.query.id);
const diary = ref(null);
const loading = ref(true);

const previewUrl = ref('');

onMounted(() => {
  if (!diaryId.value) {
    customAlert('无效的手记ID');
    router.push('/diary');
    return;
  }
  loadDiary();
});

const goBack = () => {
  router.back();
};

const formatDate = (timestamp) => {
  if (!timestamp) return '';
  const date = new Date(timestamp);
  return date.toLocaleString('zh-CN', { year: 'numeric', month: '2-digit', day: '2-digit', hour: '2-digit', minute: '2-digit' });
};

const getImageUrl = (img) => {
  if (!img) return '';
  if (img.startsWith('http')) return img;
  return `${APP_CONFIG.SERVER_BASE}/${img.replace(/^\//, '')}`;
};

const formattedContent = computed(() => {
  if (!diary.value || !diary.value.content) return '';
  return diary.value.content.replace(/\n/g, '<br>');
});

const loadDiary = async () => {
  loading.value = true;
  try {
    const data = await commonFetch(`${APP_CONFIG.API_BASE}/diaries.php?user_id=${currentUserId.value}&diary_id=${diaryId.value}`);
    if (data.success && data.diaries && data.diaries.length > 0) {
      diary.value = data.diaries[0];
    } else {
      customAlert('手记不存在或已被删除');
      router.push('/diary');
    }
  } catch (error) {
    console.error('加载详情失败', error);
  } finally {
    loading.value = false;
  }
};

const showPreview = (url) => {
  previewUrl.value = url;
};

const editDiary = () => {
  router.push(`/diary/edit?edit=1&id=${diaryId.value}`);
};

const deleteDiary = async () => {
  if (!await customConfirm('确定要删除这篇手记吗？')) return;
  
  try {
    const data = await commonFetch(`${APP_CONFIG.API_BASE}/diaries.php`, {
      method: 'DELETE',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ id: diaryId.value, user_id: currentUserId.value })
    });
    
    if (data.success) {
      customAlert('删除成功');
      router.push('/diary');
    } else {
      customAlert('删除失败: ' + data.message);
    }
  } catch(e) {
    customAlert('网络错误，删除失败');
  }
};

</script>

<style scoped>
.diary-detail-container {
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

:global(body.dark-theme) .diary-header {
  background: linear-gradient(135deg, #8c2a4f 0%, #ab4266 100%);
}

#back-btn {
  background: transparent;
  color: white;
  border: none;
  font-size: 16px;
  cursor: pointer;
  padding: 8px;
}

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

.diary-main {
  padding: 80px 16px 40px 16px;
  max-width: 800px;
  margin: 0 auto;
}

.diary-detail {
  background: var(--bg-color-card, #fff);
  border-radius: 12px;
  padding: 20px;
  margin-bottom: 20px;
  box-shadow: 0 2px 8px var(--shadow-color, rgba(0, 0, 0, 0.1));
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
}

.diary-mood {
  font-size: 24px;
}

.diary-title {
  font-size: 20px;
  font-weight: bold;
  color: var(--text-color-light, #333);
  margin-bottom: 12px;
}

.diary-content {
  font-size: 16px;
  color: var(--text-color-medium, #555);
  line-height: 1.6;
  margin-bottom: 16px;
  word-break: break-all;
}

.diary-images {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
  gap: 12px;
  margin: 16px 0;
}

.diary-image {
  width: 100%;
  height: 150px;
  object-fit: cover;
  border-radius: 8px;
  cursor: pointer;
  transition: transform 0.3s ease;
  border: 1px solid var(--border-color, #eee);
}

.diary-image:active {
  transform: scale(0.95);
}

.diary-footer {
  margin-top: 16px;
  padding-top: 16px;
  border-top: 1px solid var(--border-color, #eee);
}

.diary-author {
  display: flex;
  align-items: center;
  gap: 8px;
}

.diary-author-avatar {
  width: 32px;
  height: 32px;
  border-radius: 50%;
  object-fit: cover;
}

.diary-author span {
  font-size: 14px;
  color: var(--text-color-medium, #666);
  font-weight: 500;
}

.diary-tags {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
  margin-top: 12px;
}

.diary-tag {
  background: var(--bg-color-light, rgba(255, 107, 157, 0.1));
  color: #ff6b9d;
  padding: 4px 12px;
  border-radius: 16px;
  font-size: 14px;
}

.diary-detail-actions {
  display: flex;
  gap: 12px;
  margin-top: 20px;
  padding-top: 20px;
  border-top: 1px solid var(--border-color, #eee);
}

.diary-edit-btn,
.diary-delete-btn {
  flex: 1;
  padding: 12px 20px;
  border: none;
  border-radius: 8px;
  font-size: 16px;
  font-weight: 500;
  cursor: pointer;
  transition: opacity 0.2s;
}

.diary-edit-btn {
  background: var(--nav-btn-active-color, #ff8fab);
  color: #fff;
  box-shadow: 0 2px 8px var(--shadow-color, rgba(255,143,171,0.2));
}

.diary-delete-btn {
  background: var(--text-color-danger, #ff6b6b);
  color: white;
}

.diary-edit-btn:active, .diary-delete-btn:active {
  opacity: 0.8;
}

.image-preview-modal {
  display: none;
  position: fixed;
  z-index: 9999;
  top: 0; left: 0; width: 100vw; height: 100vh;
  background: rgba(0,0,0,0.8);
  align-items: center;
  justify-content: center;
}

.image-preview-modal.show {
  display: flex;
}

.image-preview-backdrop img {
  max-width: 90vw;
  max-height: 90vh;
  border-radius: 12px;
  box-shadow: 0 4px 32px #000;
}
</style>
