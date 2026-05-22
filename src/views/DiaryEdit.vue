<template>
  <div class="diary-edit-container">
    <AppHeader :title="isEditMode ? '编辑手记' : '写手记'" :showBack="true">
      <template #actions>
        <button class="app-header-btn primary" @click="saveDiary" :disabled="isSaving">{{ isSaving ? '保存中...' : '保存' }}</button>
      </template>
    </AppHeader>

    <main class="diary-main">
      <div class="diary-form">
        <div class="form-group">
          <label>标题</label>
          <input type="text" v-model="title" placeholder="给这篇手记起个标题吧~" maxlength="50" @input="saveDraft">
        </div>

        <div class="form-group">
          <label>今天的心情</label>
          <div class="mood-selector">
            <div class="mood-option" :class="{ selected: mood === m.emoji }" v-for="m in moods" :key="m.emoji" @click="mood = m.emoji; saveDraft()">
              <span class="mood-emoji">{{ m.emoji }}</span>
              <span class="mood-text">{{ m.text }}</span>
            </div>
          </div>
        </div>

        <div class="form-group">
          <label>内容</label>
          <textarea v-model="content" placeholder="写下今天的美好回忆..." rows="8" maxlength="2000" @input="saveDraft"></textarea>
          <div class="char-count" :style="{ color: content.length > 1800 ? '#ff6b6b' : content.length > 1500 ? '#ffa726' : '' }">
            <span>{{ content.length }}</span>/2000
          </div>
        </div>

        <div class="form-group">
          <div style="display: flex; align-items: center; justify-content: space-between;">
            <label>添加图片</label>
            <span style="font-size: 12px; color: var(--text-color-medium, #888);">点一下图片可以删除</span>
          </div>
          <div class="image-upload-area">
            <div class="image-preview">
              <img v-for="(img, idx) in selectedImages" :key="idx" :src="getImageUrl(img)" class="preview-image" @click="removeImage(idx)">
              <div class="upload-placeholder" v-if="selectedImages.length < 6" @click="triggerUpload">
                <span class="upload-icon">📷</span>
                <span class="upload-text">点击添加图片</span>
              </div>
            </div>
            <input type="file" ref="imageInput" accept="image/*" multiple style="display:none;" @change="handleImageUpload">
          </div>
        </div>

        <div class="form-group">
          <label>标签</label>
          <div class="tag-input-area">
            <div class="tag-list">
              <span class="diary-tag" v-for="(tag, idx) in tags" :key="idx">
                {{ tag }} <span class="tag-remove" @click="removeTag(idx)">×</span>
              </span>
            </div>
            <div class="tag-input-wrapper">
              <input type="text" v-model="tagInput" placeholder="添加标签..." maxlength="10" @keypress.enter="addTag">
              <button @click="addTag">+</button>
            </div>
          </div>
          <div class="tag-suggestions">
            <span class="tag-suggestion" v-for="s in suggestions" :key="s" @click="addSuggestion(s)">{{ s }}</span>
          </div>
        </div>
      </div>
    </main>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import { useRouter, useRoute } from 'vue-router';
import { APP_CONFIG, commonFetch } from '../utils/config';
import { customAlert, customConfirm } from '../utils/modal';

const router = useRouter();
const route = useRoute();

const currentUserId = ref(localStorage.getItem('user_id'));
const username = ref(localStorage.getItem('username') || '用户');

const isEditMode = ref(false);
const editDiaryId = ref(null);

const title = ref('');
const content = ref('');
const mood = ref('😊');
const moods = [
  { emoji: '😊', text: '开心' },
  { emoji: '🥰', text: '甜蜜' },
  { emoji: '😞', text: '不高兴' },
  { emoji: '🤔', text: '思考' },
  { emoji: '😢', text: '感动' },
  { emoji: '🎉', text: '庆祝' }
];

const selectedImages = ref([]); // Either base64 or server URL
const imageInput = ref(null);

const tags = ref([]);
const tagInput = ref('');
const suggestions = ['约会', '美食', '旅行', '电影', '礼物'];

const isSaving = ref(false);
let autoSaveTimer = null;

onMounted(() => {
  if (!currentUserId.value) {
    router.push('/');
    return;
  }
  
  if (route.query.edit === '1' && route.query.id) {
    isEditMode.value = true;
    editDiaryId.value = route.query.id;
    loadDiaryForEdit(editDiaryId.value);
  } else {
    loadDraft();
  }
});

const goBack = async () => {
  if (title.value.trim() || content.value.trim()) {
    if (await customConfirm('您有未保存的内容，确定要离开吗？')) {
      router.back();
    }
  } else {
    router.back();
  }
};

const getImageUrl = (img) => {
  if (img.startsWith('data:')) return img;
  if (img.startsWith('http')) return img;
  return `${APP_CONFIG.SERVER_BASE}/${img.replace(/^\//, '')}`;
};

const triggerUpload = () => {
  imageInput.value.click();
};

const compressImage = (file) => {
  return new Promise((resolve, reject) => {
    const reader = new FileReader();
    reader.onload = (e) => {
      const img = new Image();
      img.onload = () => {
        const canvas = document.createElement('canvas');
        let width = img.width;
        let height = img.height;
        const maxWidth = 1920;
        const maxHeight = 1920;
        
        if (width > maxWidth || height > maxHeight) {
          const ratio = Math.min(maxWidth / width, maxHeight / height);
          width *= ratio;
          height *= ratio;
        }
        
        canvas.width = width;
        canvas.height = height;
        const ctx = canvas.getContext('2d');
        ctx.drawImage(img, 0, 0, width, height);
        resolve(canvas.toDataURL('image/jpeg', 0.8));
      };
      img.onerror = reject;
      img.src = e.target.result;
    };
    reader.onerror = reject;
    reader.readAsDataURL(file);
  });
};

const handleImageUpload = async (event) => {
  const files = Array.from(event.target.files);
  if (!files.length) return;
  
  if (!isEditMode.value && selectedImages.value.length === 0) {
    // If not edit mode and no images yet, maybe we don't clear. 
    // In original code it clears on first select if not edit mode.
  }
  
  let remaining = 6 - selectedImages.value.length;
  const toProcess = files.slice(0, remaining);
  
  for (const file of toProcess) {
    if (file.size < 500 * 1024) {
      const reader = new FileReader();
      reader.onload = (e) => {
        selectedImages.value.push(e.target.result);
      };
      reader.readAsDataURL(file);
    } else {
      try {
        const compressed = await compressImage(file);
        selectedImages.value.push(compressed);
      } catch (e) {
        console.error(e);
      }
    }
  }
  
  if (files.length > remaining) {
    customAlert(`最多只能添加6张图片，已忽略多余的图片`);
  }
  
  event.target.value = '';
};

const removeImage = (idx) => {
  selectedImages.value.splice(idx, 1);
};

const addTag = () => {
  const t = tagInput.value.trim();
  if (t && t.length <= 10) {
    if (!tags.value.includes(t)) {
      tags.value.push(t);
      tagInput.value = '';
    } else {
      customAlert('标签已存在');
    }
  } else if (t.length > 10) {
    customAlert('标签不能超过10个字符');
  }
};

const addSuggestion = (t) => {
  if (!tags.value.includes(t)) {
    tags.value.push(t);
  } else {
    customAlert('标签已存在');
  }
};

const removeTag = (idx) => {
  tags.value.splice(idx, 1);
};

const saveDraft = () => {
  if (autoSaveTimer) clearTimeout(autoSaveTimer);
  autoSaveTimer = setTimeout(() => {
    if (title.value.trim() || content.value.trim()) {
      localStorage.setItem('diary_draft', JSON.stringify({
        title: title.value.trim(),
        content: content.value.trim(),
        timestamp: Date.now()
      }));
    }
  }, 2000); // Save draft every 2s after typing stops
};

const loadDraft = () => {
  const draftStr = localStorage.getItem('diary_draft');
  if (draftStr) {
    try {
      const draft = JSON.parse(draftStr);
      if (Date.now() - draft.timestamp < 24 * 60 * 60 * 1000) {
        title.value = draft.title || '';
        content.value = draft.content || '';
      }
    } catch(e) {}
  }
};

const clearDraft = () => {
  localStorage.removeItem('diary_draft');
};

const loadDiaryForEdit = async (id) => {
  try {
    const data = await commonFetch(`${APP_CONFIG.API_BASE}/diaries.php?user_id=${currentUserId.value}&diary_id=${id}`);
    if (data.success && data.diaries && data.diaries.length > 0) {
      const d = data.diaries[0];
      title.value = d.title || '';
      content.value = d.content || '';
      if (d.mood) mood.value = d.mood;
      if (d.tags) tags.value = [...d.tags];
      if (d.images) selectedImages.value = [...d.images];
    } else {
      customAlert('加载手记失败');
      router.push('/diary');
    }
  } catch(e) {
    customAlert('网络错误');
  }
};

const saveDiary = async () => {
  if (!title.value.trim()) {
    customAlert('请输入标题'); return;
  }
  if (!content.value.trim()) {
    customAlert('请输入内容'); return;
  }
  if (content.value.length > 2000) {
    customAlert('内容不能超过2000个字符'); return;
  }
  
  isSaving.value = true;
  
  const diaryData = {
    title: title.value.trim(),
    content: content.value.trim(),
    mood: mood.value,
    tags: tags.value,
    images: selectedImages.value,
    authorName: username.value,
    timestamp: Date.now()
  };
  
  const method = isEditMode.value ? 'PUT' : 'POST';
  const url = `${APP_CONFIG.API_BASE}/diaries.php`;
  
  if (isEditMode.value) {
    diaryData.id = editDiaryId.value;
  }
  
  try {
    const data = await commonFetch(url, {
      method,
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        user_id: currentUserId.value,
        ...diaryData
      })
    });
    
    if (data.success) {
      customAlert(isEditMode.value ? '更新成功' : '保存成功');
      clearDraft();
      router.push('/diary');
    } else {
      customAlert('保存失败: ' + (data.message || '未知错误'));
    }
  } catch(e) {
    customAlert('网络错误，保存失败');
  } finally {
    isSaving.value = false;
  }
};

</script>

<style scoped>
.diary-edit-container {
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

.diary-subtitle {
  font-size: 14px;
  opacity: 0.9;
  margin-top: 2px;
}

#save-btn {
  background: #ff6b9d;
  color: white;
  border: 2px solid white;
  border-radius: 20px;
  padding: 6px 14px;
  font-size: 14px;
  font-weight: bold;
  cursor: pointer;
  transition: opacity 0.2s;
}

:global(body.dark-theme) #save-btn {
  background: #ab4266;
}

#save-btn:disabled {
  opacity: 0.6;
}

.diary-main {
  padding: 80px 16px 40px 16px;
  max-width: 600px;
  margin: 0 auto;
}

.diary-form {
  background: var(--bg-color-card, #fff);
  border-radius: 16px;
  padding: 20px;
  box-shadow: 0 4px 16px var(--shadow-color, rgba(0,0,0,0.05));
}

.form-group {
  margin-bottom: 24px;
}

.form-group label {
  display: block;
  font-size: 16px;
  font-weight: bold;
  color: var(--text-color-light, #333);
  margin-bottom: 8px;
}

.form-group input[type="text"],
.form-group textarea {
  width: 100%;
  border: 2px solid var(--border-color, #eee);
  border-radius: 12px;
  padding: 12px 16px;
  font-size: 16px;
  color: var(--text-color-light, #333);
  background: var(--bg-color-light, #f9f9f9);
  box-sizing: border-box;
}

.form-group input[type="text"]:focus,
.form-group textarea:focus {
  outline: none;
  border-color: var(--nav-btn-active-color, #ff6b9d);
}

.char-count {
  text-align: right;
  font-size: 14px;
  color: var(--text-color-medium, #888);
  margin-top: 4px;
}

.mood-selector {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 12px;
}

.mood-option {
  background: var(--bg-color-light, #f9f9f9);
  border: 2px solid var(--border-color, #eee);
  border-radius: 12px;
  padding: 16px 12px;
  text-align: center;
  cursor: pointer;
  transition: all 0.2s;
}

.mood-option.selected {
  border-color: var(--nav-btn-active-color, #ff6b9d);
  background: var(--bg-color-light, rgba(255, 107, 157, 0.1));
}

.mood-emoji {
  display: block;
  font-size: 24px;
  margin-bottom: 4px;
}

.mood-text {
  font-size: 14px;
  color: var(--text-color-medium, #888);
}

.image-upload-area {
  border: 1px solid var(--border-color, #eee);
  border-radius: 12px;
  padding: 15px;
  background: var(--bg-color-card, #fff);
}

.image-preview {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(80px, 1fr));
  gap: 8px;
}

.preview-image {
  width: 100%;
  height: 80px;
  object-fit: cover;
  border-radius: 8px;
  cursor: pointer;
  border: 2px solid var(--border-color, #eee);
}

.upload-placeholder {
  cursor: pointer;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  border: 2px dashed var(--border-color, #ddd);
  border-radius: 8px;
  height: 80px;
  background: var(--bg-color-light, rgba(255, 107, 157, 0.02));
}

.upload-icon {
  font-size: 24px;
  opacity: 0.6;
}

.upload-text {
  font-size: 12px;
  color: var(--text-color-medium, #888);
  margin-top: 4px;
}

.tag-input-area {
  margin-bottom: 12px;
}

.tag-list {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
  margin-bottom: 12px;
}

.diary-tag {
  background: var(--bg-color-light, rgba(255, 107, 157, 0.1));
  color: #ff6b9d;
  padding: 4px 12px;
  border-radius: 16px;
  font-size: 14px;
  display: flex;
  align-items: center;
}

.tag-remove {
  margin-left: 6px;
  cursor: pointer;
  font-weight: bold;
}

.tag-input-wrapper {
  display: flex;
  gap: 8px;
}

.tag-input-wrapper input {
  flex: 1;
}

.tag-input-wrapper button {
  background: var(--nav-btn-active-color, #ff6b9d);
  color: white;
  border: none;
  border-radius: 8px;
  width: 40px;
  height: 40px;
  font-size: 18px;
  cursor: pointer;
}

.tag-suggestions {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
}

.tag-suggestion {
  background: var(--bg-color-light, rgba(255, 107, 157, 0.1));
  color: #ff6b9d;
  padding: 6px 12px;
  border-radius: 16px;
  font-size: 14px;
  cursor: pointer;
}
</style>
