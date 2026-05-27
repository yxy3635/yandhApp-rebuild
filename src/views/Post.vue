<template>
  <div class="post-container">
    <AppHeader :title="isEditing ? '编辑动态' : '发布动态'" :showBack="true">
      <template #actions>
        <button class="app-header-btn primary" @click="submitPost" :disabled="isSubmitting">发布</button>
      </template>
    </AppHeader>
    
    <main class="page-bounce-in">
      <div class="post-user-info">
        <img id="user-avatar" :src="userAvatar" alt="头像">
        <span class="post-username">{{ currentUsername }}</span>
      </div>
      <div id="upload-progress" v-if="isSubmitting" style="text-align:center; margin-top: 10px; font-size: 14px; color: #555;">
        {{ uploadProgressText }}
      </div>
      <textarea id="post-text" v-model="postContent" placeholder="说点什么吧..." maxlength="500"></textarea>
      
      <div class="media-upload">
        <input type="file" ref="imgUploadInput" accept="image/*" multiple style="display:none;" @change="previewImages">
        <button @click="triggerImgUpload" :disabled="isVideoSelected || isSubmitting">添加图片</button>
        <input type="file" ref="videoUploadInput" accept="video/*" style="display:none;" @change="previewVideo">
        <button @click="triggerVideoUpload" :disabled="isImageSelected || isSubmitting">添加视频</button>
      </div>
      
      <div id="media-preview">
        <template v-if="mediaType === 'image'">
          <div v-for="(url, index) in mediaUrls" :key="index" style="position:relative; display:inline-block;">
            <img :src="url" class="preview-img">
            <button class="media-delete" @click="removeMedia(index)" v-if="!isSubmitting">×</button>
          </div>
        </template>
        <template v-else-if="mediaType === 'video'">
          <div style="position:relative; display:inline-block;">
            <video :src="mediaUrls[0]" controls class="preview-video"></video>
            <button class="media-delete" @click="removeMedia(0)" v-if="!isSubmitting">×</button>
          </div>
        </template>
      </div>
      
      <p class="hint-message">视频和图片只能二选一，我还没搞明白怎么一起</p>
      <p class="hint-message">视频一次只能传一个，图片可以传多个</p>
    </main>
  </div>
</template>

<script setup>
import { ref, onMounted, computed } from 'vue';
import { useRouter, useRoute } from 'vue-router';
import { APP_CONFIG, commonFetch, getAvatarUrl } from '../utils/config';
import defaultAvatar from '../assets/img/default-avatar.png';
import { customAlert } from '../utils/modal';

const router = useRouter();
const route = useRoute();

const currentUserId = ref(localStorage.getItem('user_id'));
const currentUsername = ref(localStorage.getItem('username'));
const userAvatar = ref(defaultAvatar);

const isEditing = ref(false);
const editingPostId = ref(null);
const isSubmitting = ref(false);
const uploadProgressText = ref('');

const postContent = ref('');
const mediaUrls = ref([]);
const mediaFiles = ref([]); // File objects
const mediaType = ref(''); // 'image' or 'video'

const imgUploadInput = ref(null);
const videoUploadInput = ref(null);

const isImageSelected = computed(() => mediaType.value === 'image');
const isVideoSelected = computed(() => mediaType.value === 'video');

onMounted(() => {
  if (!currentUserId.value) {
    customAlert('请先登录');
    router.push('/');
    return;
  }
  
  loadUserAvatar();
  
  const postId = route.query.post_id;
  if (postId) {
    editingPostId.value = postId;
    isEditing.value = true;
    loadPostForEdit(postId);
  }
});

const goBack = () => {
  router.back();
};

const loadUserAvatar = async () => {
  try {
    const data = await commonFetch(`${APP_CONFIG.API_BASE}/profile.php?user_id=${currentUserId.value}`);
    if (data.success && data.user && data.user.avatar_url) {
      userAvatar.value = getAvatarUrl(data.user.avatar_url);
    }
  } catch(e) {}
};

const loadPostForEdit = async (id) => {
  try {
    const data = await commonFetch(`${APP_CONFIG.API_BASE}/feed.php?post_id=${id}`);
    if (data) {
      postContent.value = data.content || '';
      
      let mUrls = [];
      if (data.media_urls) {
        mUrls = Array.isArray(data.media_urls) ? data.media_urls : JSON.parse(data.media_urls);
      }
      
      if (mUrls && mUrls.length > 0) {
        mediaType.value = mUrls[0].type;
        mediaUrls.value = mUrls.map(m => m.url.startsWith('http') ? m.url : `${APP_CONFIG.SERVER_BASE}/${m.url}`);
      } else if (data.content && (data.type === 'image' || data.type === 'video')) {
        mediaType.value = data.type;
        const url = data.content.startsWith('http') ? data.content : `${APP_CONFIG.SERVER_BASE}/${data.content}`;
        mediaUrls.value = [url];
      }
    } else {
      customAlert('加载动态失败');
      router.push('/home');
    }
  } catch(e) {
    customAlert('网络错误');
    router.push('/home');
  }
};

const triggerImgUpload = () => {
  if (!isVideoSelected.value) {
    imgUploadInput.value.click();
  }
};

const triggerVideoUpload = () => {
  if (!isImageSelected.value) {
    videoUploadInput.value.click();
  }
};

const previewImages = (event) => {
  const files = Array.from(event.target.files);
  if (!files.length) return;
  
  mediaType.value = 'image';
  mediaFiles.value = [...mediaFiles.value, ...files];
  
  files.forEach(file => {
    const url = URL.createObjectURL(file);
    mediaUrls.value.push(url);
  });
};

const previewVideo = (event) => {
  const files = Array.from(event.target.files);
  if (!files.length) return;
  if (files.length > 1) {
    customAlert('一次只能上传一个视频');
    return;
  }
  
  mediaType.value = 'video';
  mediaFiles.value = [files[0]];
  mediaUrls.value = [URL.createObjectURL(files[0])];
};

const removeMedia = (index) => {
  mediaUrls.value.splice(index, 1);
  mediaFiles.value.splice(index, 1);
  if (mediaUrls.value.length === 0) {
    mediaType.value = '';
    if (imgUploadInput.value) imgUploadInput.value.value = '';
    if (videoUploadInput.value) videoUploadInput.value.value = '';
  }
};

const submitPost = () => {
  if (isSubmitting.value) return;
  
  if (!postContent.value.trim() && mediaFiles.value.length === 0 && mediaUrls.value.length === 0) {
    customAlert('请填写内容或添加图片/视频');
    return;
  }

  isSubmitting.value = true;
  uploadProgressText.value = '上传中... 0%';
  
  const formData = new FormData();
  formData.append('content', postContent.value.trim());
  formData.append('user_id', currentUserId.value);
  formData.append('username', currentUsername.value);
  
  let mediaSent = false;
  if (mediaFiles.value.length > 0) {
    mediaFiles.value.forEach(f => {
      formData.append('media[]', f);
    });
    mediaSent = true;
  }
  
  if (isEditing.value && !mediaSent && mediaUrls.value.length === 0) {
    formData.append('clear_media', 'true');
  }
  
  let apiUrl = isEditing.value ? `${APP_CONFIG.API_BASE}/edit_post.php` : `${APP_CONFIG.API_BASE}/upload_post.php`;
  if (isEditing.value) {
    formData.append('post_id', editingPostId.value);
  }
  
  const xhr = new XMLHttpRequest();
  xhr.open('POST', apiUrl, true);
  
  xhr.upload.onprogress = (event) => {
    if (event.lengthComputable) {
      const percent = Math.round((event.loaded / event.total) * 100);
      uploadProgressText.value = `上传中... ${percent}%`;
    }
  };
  
  xhr.onload = () => {
    isSubmitting.value = false;
    if (xhr.status === 200) {
      try {
        const data = JSON.parse(xhr.responseText);
        if (data.success) {
          customAlert(data.message);
          router.push('/home');
        } else {
          customAlert(data.message || '操作失败');
        }
      } catch(e) {
        customAlert('解析响应失败');
      }
    } else {
      customAlert('请求失败');
    }
  };
  
  xhr.onerror = () => {
    isSubmitting.value = false;
    customAlert('网络错误');
  };
  
  xhr.onabort = () => {
    isSubmitting.value = false;
    customAlert('上传取消');
  };
  
  xhr.send(formData);
};

</script>

<style scoped>
.post-container {
  min-height: 100vh;
  background: var(--bg-color-light);
}

.post-user-info {
  display: flex;
  align-items: center;
  margin-bottom: 16px;
  padding: 0 4px;
}

.post-username {
  font-size: 16px;
  font-weight: 600;
  color: var(--text-color-light, #333);
}

:global(body.dark-theme) .post-username {
  color: var(--text-color-light, #e0e0e0);
}

.home-header {
  background: var(--header-bg, rgba(255, 255, 255, 0.85));
  backdrop-filter: blur(10px);
  -webkit-backdrop-filter: blur(10px);
  color: var(--text-color-light, #333);
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 14px 12px;
  font-size: 20px;
  box-shadow: 0 4px 20px var(--header-shadow, rgba(0, 0, 0, 0.08));
  border-bottom-left-radius: 18px;
  border-bottom-right-radius: 18px;
  position: fixed;
  top: 0; left: 0; right: 0;
  z-index: 100;
  transition: background 0.3s, box-shadow 0.3s;
}

#back-btn, #submit-btn {
  background: #ffffff;
  color: #007aff;
  border: none;
  border-radius: 24px;
  padding: 8px 22px;
  font-size: 16px;
  cursor: pointer;
  box-shadow: 0 2px 8px rgba(0,122,255,0.08);
  transition: transform 0.1s;
  width: auto;
}

:global(body.dark-theme) #back-btn, :global(body.dark-theme) #submit-btn {
  background: linear-gradient(90deg, #333, #555);
  color: #e0e0e0;
  box-shadow: 0 2px 8px rgba(0,0,0,0.3);
}

#back-btn:active, #submit-btn:active { transform: scale(0.96); }

#submit-btn:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}

header span { flex: 1; text-align: center; font-weight: bold; font-size: 20px; }

#user-avatar {
  width: 36px;
  height: 36px;
  border-radius: 50%;
  object-fit: cover;
  margin-right: 10px;
  border: 2px solid rgba(255, 255, 255, 0.8);
  box-shadow: 0 1px 4px rgba(0,0,0,0.1);
}

:global(body.dark-theme) #user-avatar {
  border: 2px solid rgba(255, 255, 255, 0.2);
  box-shadow: 0 1px 4px rgba(0,0,0,0.4);
}

main { padding: 80px 10px 80px 10px; }

textarea#post-text {
  width: 100%;
  min-height: 120px;
  font-size: 17px;
  border-radius: 18px;
  border: 1.5px solid #ddd;
  padding: 14px;
  resize: vertical;
  box-sizing: border-box;
  margin-bottom: 16px;
  box-shadow: 0 2px 8px rgba(0,0,0,0.04);
  transition: border 0.2s;
}

:global(body.dark-theme) textarea#post-text {
  background: #1f1f1f;
  color: #e0e0e0;
  border: 1.5px solid #333;
  box-shadow: 0 2px 8px rgba(0,0,0,0.2);
}

textarea#post-text:focus {
  border: 1.5px solid #007aff;
  outline: none;
  background: #f0f8ff;
}

:global(body.dark-theme) textarea#post-text:focus {
  background: #2a2a2a;
}

.media-upload { display: flex; gap: 10px; margin-bottom: 12px; }
.media-upload button {
  flex: 1;
  background: linear-gradient(90deg,#fff,#e6f0ff);
  color: #007aff;
  border: 1.5px solid #007aff;
  border-radius: 18px;
  padding: 8px 0;
  font-size: 16px;
  cursor: pointer;
  transition: background 0.15s;
}

:global(body.dark-theme) .media-upload button {
  background: linear-gradient(90deg, #333, #555);
  color: #e0e0e0;
  border: 1.5px solid #444;
}

.media-upload button:active { background: #e6f0ff; }
:global(body.dark-theme) .media-upload button:active { background: #4a4a4a; }

.media-upload button:disabled {
  opacity: 0.5;
  cursor: not-allowed;
  border-color: #ccc;
  color: #999;
}

#media-preview { display: flex; flex-wrap: wrap; gap: 12px; margin-bottom: 16px; }
.preview-img, .preview-video {
  box-shadow: 0 2px 8px rgba(0,122,255,0.10);
  border-radius: 12px;
  border: 1.5px solid #eee;
}

:global(body.dark-theme) .preview-img, :global(body.dark-theme) .preview-video {
  box-shadow: 0 2px 8px rgba(0,0,0,0.2);
  border: 1.5px solid #333;
}

.preview-img { width: 90px; height: 90px; object-fit: cover; }
.preview-video { width: 160px; height: 90px; }

.media-delete {
  position: absolute;
  top: 2px; right: 2px;
  background: rgba(0,0,0,0.5);
  color: #fff;
  border: none;
  border-radius: 50%;
  width: 22px; height: 22px;
  font-size: 16px;
  cursor: pointer;
  display: flex; align-items: center; justify-content: center;
  z-index: 2;
}

:global(body.dark-theme) .media-delete {
  background: rgba(255,255,255,0.2);
}

.hint-message {
  text-align: center;
  color: #555;
  font-size: 14px;
  font-weight: 500;
  margin: 10px auto 0 auto;
  padding: 10px 15px;
  background-color: #eaf6ff;
  border-radius: 12px;
  box-shadow: 0 4px 12px rgba(0, 122, 255, 0.08);
  max-width: 320px;
  line-height: 1.5;
}

:global(body.dark-theme) .hint-message {
  color: #b0b0b0;
  background-color: #2a2a2a;
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.4);
}

@keyframes bounceIn {
  0% { opacity: 0; transform: translateY(80px) scale(0.9);}
  60% { opacity: 1; transform: translateY(-12px) scale(1.05);}
  80% { transform: translateY(4px) scale(0.98);}
  100% { opacity: 1; transform: translateY(0) scale(1);}
}
.page-bounce-in {
  animation: bounceIn 0.8s cubic-bezier(.23,1.02,.53,.97);
}
</style>
