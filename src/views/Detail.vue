<template>
  <div class="detail-container">
    <AppHeader title="动态详情" :showBack="true">
      <template #actions v-if="post && currentUserId === String(post.user_id)">
        <button class="app-header-btn" @click="editPost">编辑</button>
        <button class="app-header-btn" style="color: #ff3b30;" @click="deletePost">删除</button>
      </template>
    </AppHeader>

    <main id="detail-main" class="page-flip-in">
      <LoadingSpinner v-if="loading" text="加载详情..." />
      <div v-else-if="!post" style="text-align:center; padding: 40px;">动态不存在或无法加载</div>
      <template v-else>
        <div class="detail-card">
          <div class="detail-header">
            <img class="detail-avatar" :src="getAvatarUrl(post.avatar)" alt="头像">
            <div class="detail-userinfo">
              <div class="detail-username">{{ post.user }}</div>
              <div class="detail-time">{{ formatTime(post.time) }}</div>
            </div>
          </div>
          <div class="detail-text">{{ post.content }}</div>
          
          <div class="detail-media-container" v-if="mediaUrls.length > 0">
            <template v-for="(media, index) in mediaUrls" :key="index">
              <img v-if="media.type === 'image'" :src="media.url" class="detail-media-item" @click="previewImg(media.url)">
              <video v-else-if="media.type === 'video'" :src="media.url" controls class="detail-media-item"></video>
            </template>
          </div>
        </div>

        <div class="comment-section">
          <h2>宝宝说</h2>
          <div class="comment-input">
            <textarea v-model="commentText" placeholder="领导大人请发话~"></textarea>
            <button id="submit-comment-btn" @click="submitComment()">biu~</button>
          </div>
          
          <div id="comments-list">
            <div v-if="comments.length === 0" style="text-align: center; color: #888; padding: 20px;">还在等宝宝发评论呢~</div>
            <template v-else>
              <div class="comment-item" v-for="comment in rootComments" :key="comment.comment_id">
                <img class="comment-avatar" :src="getAvatarUrl(comment.avatar_url)" alt="头像">
                <div class="comment-main-content">
                  <div class="comment-author">{{ comment.user_name || '匿名用户' }}</div>
                  <p class="comment-text">{{ comment.content }}</p>
                  <div class="comment-time">{{ comment.timestamp }}</div>
                  <div class="comment-actions">
                    <button class="comment-action-btn reply-btn" @click="showReplyInput(comment.comment_id, comment.user_name)">回复</button>
                    <template v-if="currentUserId === String(comment.user_id)">
                      <button class="comment-action-btn edit-btn" @click="editComment(comment.comment_id, comment.content)">编辑</button>
                      <button class="comment-action-btn delete-btn" @click="deleteComment(comment.comment_id)">删除</button>
                    </template>
                  </div>
                  
                  <div class="comment-reply-box" v-if="replyingTo === comment.comment_id">
                    <textarea v-model="replyText" :placeholder="'回复@' + replyToName"></textarea>
                    <button @click="submitComment(comment.comment_id, replyToName)">发送</button>
                    <button type="button" style="background:#eee;color:#333;" @click="replyingTo = null">取消</button>
                  </div>
                  
                  <div class="comment-replies" v-if="comment.replies && comment.replies.length > 0">
                    <div class="comment-reply" v-for="reply in comment.replies" :key="reply.comment_id">
                      <img class="comment-avatar" :src="getAvatarUrl(reply.avatar_url)" alt="头像">
                      <div class="comment-reply-content">
                        <div class="comment-author">{{ reply.user_name || '匿名用户' }}</div>
                        <p class="comment-text">{{ reply.content }}</p>
                        <div class="comment-time">{{ reply.timestamp }}</div>
                        <div class="comment-reply-actions">
                          <button class="comment-action-btn reply-btn" @click="showReplyInput(comment.comment_id, reply.user_name)">回复</button>
                          <template v-if="currentUserId === String(reply.user_id)">
                            <button class="comment-action-btn edit-btn" @click="editComment(reply.comment_id, reply.content)">编辑</button>
                            <button class="comment-action-btn delete-btn" @click="deleteComment(reply.comment_id)">删除</button>
                          </template>
                        </div>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </template>
          </div>
        </div>
      </template>
    </main>

    <!-- 图片预览模态框 -->
    <div
      class="image-preview-modal"
      :class="{ show: showPreview }"
      @click.self="closePreview"
    >
      <span class="close-button" @click.stop="closePreview">&times;</span>
      <button
        v-if="showPreview && canPreviewPrev"
        type="button"
        class="preview-nav preview-nav-prev"
        aria-label="上一张"
        @click.stop="previewPrev"
      >
        ‹
      </button>
      <button
        v-if="showPreview && canPreviewNext"
        type="button"
        class="preview-nav preview-nav-next"
        aria-label="下一张"
        @click.stop="previewNext"
      >
        ›
      </button>
      <div class="preview-image-wrap" @click.stop>
        <Transition :name="previewTransition" mode="out-in">
          <img
            :key="previewUrl"
            class="modal-content-image"
            :src="previewUrl"
            alt=""
          />
        </Transition>
      </div>
      <div
        v-if="showPreview && previewImageTotal > 1"
        class="preview-counter"
      >
        {{ previewIndex + 1 }} / {{ previewImageTotal }}
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted, onUnmounted, computed, watch } from 'vue';
import { useRouter, useRoute } from 'vue-router';
import { APP_CONFIG, commonFetch, getAvatarUrl } from '../utils/config';
import { customAlert, customConfirm, customPrompt } from '../utils/modal';

const router = useRouter();
const route = useRoute();

const currentUserId = ref(localStorage.getItem('user_id'));
const currentUsername = ref(localStorage.getItem('username'));

const postId = ref(route.query.post_id);
const post = ref(null);
const loading = ref(true);

const mediaUrls = ref([]);
const comments = ref([]);
const commentText = ref('');

const replyingTo = ref(null);
const replyToName = ref('');
const replyText = ref('');

const showPreview = ref(false);
const previewUrl = ref('');
const previewIndex = ref(0);
/** 首次打开 fade-first；切换 slide-next / slide-prev */
const previewTransition = ref('fade-first');

const previewImages = computed(() =>
  mediaUrls.value.filter((m) => m.type === 'image')
);

const previewImageTotal = computed(() => previewImages.value.length);

const canPreviewPrev = computed(
  () => previewImageTotal.value > 1 && previewIndex.value > 0
);

const canPreviewNext = computed(
  () =>
    previewImageTotal.value > 1 &&
    previewIndex.value < previewImageTotal.value - 1
);

const onPreviewKeydown = (e) => {
  if (!showPreview.value) return;
  if (e.key === 'Escape') {
    closePreview();
    return;
  }
  if (e.key === 'ArrowLeft' && canPreviewPrev.value) {
    e.preventDefault();
    previewPrev();
  } else if (e.key === 'ArrowRight' && canPreviewNext.value) {
    e.preventDefault();
    previewNext();
  }
};

const onAppHardwareBack = (e) => {
  if (!showPreview.value) return;
  showPreview.value = false;
  e.preventDefault();
};

onMounted(() => {
  if (!postId.value) {
    customAlert('无效的动态ID');
    router.push('/home');
    return;
  }
  loadDetail();
  window.addEventListener('keydown', onPreviewKeydown);
  window.addEventListener('app-hardware-back', onAppHardwareBack);
});

onUnmounted(() => {
  window.removeEventListener('keydown', onPreviewKeydown);
  window.removeEventListener('app-hardware-back', onAppHardwareBack);
});

watch(showPreview, (open) => {
  if (typeof document === 'undefined') return;
  document.body.style.overflow = open ? 'hidden' : '';
});

const goBack = () => {
  router.back();
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

const loadDetail = async () => {
  loading.value = true;
  try {
    const data = await commonFetch(`${APP_CONFIG.API_BASE}/feed.php?post_id=${postId.value}`);
    if (data) {
      post.value = data;
      
      // Resolve avatar
      if (data.user_id) {
        try {
          const profileData = await commonFetch(`${APP_CONFIG.API_BASE}/profile.php?user_id=${data.user_id}`);
          if (profileData.success && profileData.user) {
            post.value.avatar = profileData.user.avatar_url;
          }
        } catch(e) {}
      }

      // Resolve media
      if (data.media_urls) {
        let mUrls = Array.isArray(data.media_urls) ? data.media_urls : JSON.parse(data.media_urls);
        mediaUrls.value = mUrls.map(m => ({
          ...m,
          url: m.url.startsWith('http') ? m.url : `${APP_CONFIG.SERVER_BASE}/${m.url}`
        }));
      } else if (data.content && (data.type === 'image' || data.type === 'video')) {
        const url = data.content.startsWith('http') ? data.content : `${APP_CONFIG.SERVER_BASE}/${data.content}`;
        mediaUrls.value = [{ type: data.type, url }];
      }
      
      await loadComments();
    }
  } catch (error) {
    console.error('加载详情失败', error);
  } finally {
    loading.value = false;
  }
};

const loadComments = async () => {
  try {
    const data = await commonFetch(`${APP_CONFIG.API_BASE}/comments.php?post_id=${postId.value}`);
    if (data.success) {
      comments.value = data.comments;
    }
  } catch(e) {
    console.error('加载评论失败', e);
  }
};

const rootComments = computed(() => {
  const commentMap = {};
  comments.value.forEach(c => {
    commentMap[c.comment_id] = { ...c, replies: [] };
  });
  
  const roots = [];
  comments.value.forEach(c => {
    if (c.parent_comment_id && commentMap[c.parent_comment_id]) {
      commentMap[c.parent_comment_id].replies.push(commentMap[c.comment_id]);
    } else {
      roots.push(commentMap[c.comment_id]);
    }
  });
  return roots;
});

const previewImg = (url) => {
  const list = previewImages.value;
  const idx = list.findIndex((m) => m.url === url);
  previewIndex.value = idx >= 0 ? idx : 0;
  previewUrl.value = list[previewIndex.value]?.url ?? url;
  previewTransition.value = 'fade-first';
  showPreview.value = true;
};

const closePreview = () => {
  showPreview.value = false;
};

const previewNext = () => {
  if (!canPreviewNext.value) return;
  previewTransition.value = 'slide-next';
  previewIndex.value += 1;
  previewUrl.value = previewImages.value[previewIndex.value].url;
};

const previewPrev = () => {
  if (!canPreviewPrev.value) return;
  previewTransition.value = 'slide-prev';
  previewIndex.value -= 1;
  previewUrl.value = previewImages.value[previewIndex.value].url;
};

const editPost = () => {
  router.push(`/post?post_id=${postId.value}`);
};

const deletePost = async () => {
  if (!await customConfirm('确定删除这条动态吗？')) return;
  try {
    const data = await commonFetch(`${APP_CONFIG.API_BASE}/delete_post.php`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        post_id: postId.value,
        user_id: parseInt(currentUserId.value)
      })
    });
    if (data.success) {
      customAlert('删除成功');
      router.push('/home');
    } else {
      customAlert(data.message || '删除失败');
    }
  } catch(e) {
    customAlert('网络错误');
  }
};

const submitComment = async (parentCommentId = null, replyUserName = '') => {
  if (!currentUserId.value) {
    customAlert('请先登录');
    return;
  }
  
  let content = parentCommentId ? replyText.value : commentText.value;
  content = content.trim();
  if (!content) {
    customAlert('评论内容不能为空');
    return;
  }
  
  let finalContent = parentCommentId ? `回复@${replyUserName}：${content}` : content;
  
  try {
    const data = await commonFetch(`${APP_CONFIG.API_BASE}/submit_comment.php`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        post_id: postId.value,
        user_id: parseInt(currentUserId.value),
        user_name: currentUsername.value,
        content: finalContent,
        parent_comment_id: parentCommentId
      })
    });
    
    if (data.success) {
      customAlert('评论成功');
      commentText.value = '';
      replyText.value = '';
      replyingTo.value = null;
      await loadComments();
    } else {
      customAlert(data.message || '评论失败');
    }
  } catch(e) {
    customAlert('网络错误');
  }
};

const showReplyInput = (parentId, replyName) => {
  replyingTo.value = parentId;
  replyToName.value = replyName;
  replyText.value = '';
};

const editComment = async (commentId, oldContent) => {
  const newContent = await customPrompt('请输入新的评论内容', oldContent);
  if (newContent === null || !newContent.trim()) return;
  
  try {
    const data = await commonFetch(`${APP_CONFIG.API_BASE}/edit_comment.php`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        comment_id: commentId,
        user_id: parseInt(currentUserId.value),
        content: newContent.trim()
      })
    });
    
    if (data.success) {
      customAlert('编辑成功');
      await loadComments();
    } else {
      customAlert(data.message || '编辑失败');
    }
  } catch(e) {
    customAlert('网络错误');
  }
};

const deleteComment = async (commentId) => {
  if (!await customConfirm('确定删除这条评论吗？')) return;
  try {
    const data = await commonFetch(`${APP_CONFIG.API_BASE}/delete_comment.php`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        comment_id: commentId,
        user_id: parseInt(currentUserId.value)
      })
    });
    if (data.success) {
      customAlert('删除成功');
      await loadComments();
    } else {
      customAlert(data.message || '删除失败');
    }
  } catch(e) {
    customAlert('网络错误');
  }
};

</script>

<style scoped>
.detail-container {
  min-height: 100vh;
  background: var(--bg-color-light, #f6f8fa);
}

.home-header {
  background: var(--header-bg, rgba(255, 255, 255, 0.85));
  backdrop-filter: blur(10px);
  color: var(--text-color-light, #333);
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 14px 16px;
  font-size: 20px;
  box-shadow: 0 4px 20px var(--shadow-color, rgba(0,0,0,0.05));
  border-bottom-left-radius: 24px;
  border-bottom-right-radius: 24px;
  position: fixed;
  top: 0; left: 0; right: 0;
  z-index: 100;
}

.home-header span {
  flex: 1;
  text-align: center;
  font-weight: bold;
}

#back-btn, #edit-post-btn, #delete-post-btn {
  background: var(--bg-color-card, #fff);
  color: var(--nav-btn-active-color, #007aff);
  border: 1.5px solid var(--border-color, #e0e0e0);
  border-radius: 18px;
  padding: 6px 14px;
  font-size: 15px;
  font-weight: 500;
  cursor: pointer;
  box-shadow: 0 2px 8px var(--shadow-color, rgba(0,122,255,0.08));
  transition: background 0.2s, color 0.2s, border 0.2s;
  margin-right: 8px;
  width: auto;
}

#delete-post-btn {
  color: #dc3545;
  border-color: #f5c6cb;
}
:global(body.dark-theme) #delete-post-btn {
  color: #ff6b6b;
  border-color: #552b2b;
}

#back-btn:active, #edit-post-btn:active, #delete-post-btn:active {
  transform: scale(0.96);
}

main {
  padding: 80px 10px 40px 10px;
  max-width: 540px;
  margin: 0 auto;
  display: flex;
  flex-direction: column;
  gap: 20px;
}

.detail-card, .comment-section {
  background: var(--bg-color-card, #fff);
  border-radius: 22px;
  box-shadow: 0 6px 24px var(--shadow-color, rgba(0,0,0,0.10));
  padding: 24px;
}

.detail-header { display: flex; align-items: center; margin-bottom: 18px; }
.detail-avatar { 
  width: 64px; height: 64px; border-radius: 50%; object-fit: cover; 
  margin-right: 18px; box-shadow: 0 2px 8px var(--shadow-color, rgba(0,122,255,0.10)); border: 2px solid var(--border-color, #fff); 
}

.detail-username { font-weight: bold; color: var(--text-color-light, #222); font-size: 20px; margin-bottom: 4px; }
.detail-time { color: var(--text-color-medium, #aaa); font-size: 14px; }
.detail-text { font-size: 17px; color: var(--text-color-light, #333); margin-bottom: 12px; word-break: break-all; }

/* 九宫格式铺满宽度，避免小图挤在左侧、右侧大块留白 */
.detail-media-container {
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  gap: 8px;
  width: 100%;
  box-sizing: border-box;
}
.detail-media-item {
  border-radius: 12px;
  box-shadow: 0 2px 8px var(--shadow-color, rgba(0,122,255,0.1));
  min-width: 0;
}
img.detail-media-item {
  width: 100%;
  height: auto;
  aspect-ratio: 1 / 1;
  object-fit: cover;
  cursor: pointer;
  display: block;
}
/* 单张图：占满一行，高度自适应，避免只占一格过小 */
.detail-media-container:has(> :only-child) {
  grid-template-columns: 1fr;
}
.detail-media-container:has(> :only-child) > img.detail-media-item {
  aspect-ratio: auto;
  max-height: min(70vh, 520px);
  width: 100%;
  object-fit: contain;
  background: var(--bg-color-light, rgba(0, 0, 0, 0.04));
}
/* 恰好两张图：两列铺满 */
.detail-media-container:has(> :nth-child(2):last-child) {
  grid-template-columns: repeat(2, minmax(0, 1fr));
}
.detail-media-container:has(> :nth-child(2):last-child) > img.detail-media-item {
  aspect-ratio: 1 / 1;
  object-fit: cover;
}
/* 视频单独占满一行 */
video.detail-media-item {
  grid-column: 1 / -1;
  width: 100%;
  max-height: min(50vh, 280px);
  background: #000;
  object-fit: contain;
}

.comment-section h2 {
  font-size: 18px;
  text-align: center;
  margin-top: 0;
  color: var(--text-color-light, #333);
}

.comment-input {
  display: flex;
  gap: 10px;
  margin-bottom: 16px;
}
#comment-text, .comment-reply-box textarea {
  flex: 1;
  border: 1px solid var(--border-color, #ddd);
  border-radius: 12px;
  padding: 10px;
  font-size: 15px;
  min-height: 40px;
  resize: vertical;
  background: var(--bg-color-light, #fff);
  color: var(--text-color-light, #333);
}

#submit-comment-btn, .comment-reply-box button {
  background: var(--nav-btn-active-color, #007aff);
  color: #fff;
  border: none;
  border-radius: 12px;
  padding: 0 20px;
  cursor: pointer;
  font-size: 16px;
}

.comment-item {
  background: var(--bg-color-light, #fafdff);
  border-radius: 16px;
  padding: 16px;
  margin-bottom: 16px;
  display: flex;
  gap: 12px;
}

.comment-avatar {
  width: 36px; height: 36px; border-radius: 50%; object-fit: cover;
}

.comment-main-content {
  flex: 1;
}

.comment-author { font-weight: bold; font-size: 15px; color: var(--text-color-light, #222); }
.comment-text { margin: 4px 0; font-size: 15px; color: var(--text-color-medium, #444); word-break: break-all; }
.comment-time { font-size: 12px; color: var(--text-color-medium, #aaa); }

.comment-actions {
  display: flex; justify-content: flex-end; gap: 8px; margin-top: 6px;
}
.comment-action-btn {
  background: var(--bg-color-card, #f0f0f0); color: var(--text-color-medium, #555); border: 1px solid var(--border-color, transparent); border-radius: 6px; padding: 4px 10px; font-size: 12px; cursor: pointer;
}

.edit-btn { background: var(--nav-btn-active-color, #007aff); color: #fff; }

.delete-btn { background: #dc3545; color: #fff; }
:global(body.dark-theme) .delete-btn { background: #a02030; color: #fff; }

.comment-replies {
  margin-top: 12px; display: flex; flex-direction: column; gap: 10px;
}
.comment-reply {
  display: flex; gap: 8px; background: var(--bg-color-card, #f1f7ff); border-radius: 12px; padding: 10px;
}
.comment-reply-content { flex: 1; }

.comment-reply-box {
  display: flex; gap: 8px; margin-top: 10px;
}

.image-preview-modal {
  display: none;
  position: fixed;
  z-index: 1000;
  left: 0;
  top: 0;
  width: 100%;
  height: 100%;
  background: rgba(0, 0, 0, 0.92);
  align-items: center;
  justify-content: center;
  flex-direction: column;
  padding: 56px 12px 32px;
  box-sizing: border-box;
}
.image-preview-modal.show {
  display: flex;
}

.preview-image-wrap {
  position: relative;
  display: flex;
  align-items: center;
  justify-content: center;
  max-width: min(92vw, 900px);
  max-height: 82vh;
  min-height: 120px;
  flex: 1;
  width: 100%;
}

.modal-content-image {
  max-width: 100%;
  max-height: 82vh;
  width: auto;
  height: auto;
  object-fit: contain;
  display: block;
  margin: 0 auto;
  user-select: none;
  -webkit-user-drag: none;
}

.preview-nav {
  position: absolute;
  top: 50%;
  transform: translateY(-50%);
  z-index: 1002;
  display: flex;
  align-items: center;
  justify-content: center;
  width: 44px;
  height: 88px;
  padding: 0;
  margin: 0;
  border: none;
  border-radius: 10px;
  background: rgba(255, 255, 255, 0.12);
  color: #fff;
  font-size: 40px;
  line-height: 1;
  cursor: pointer;
  transition: background 0.2s, transform 0.15s;
  backdrop-filter: blur(6px);
  -webkit-backdrop-filter: blur(6px);
}
.preview-nav:hover {
  background: rgba(255, 255, 255, 0.22);
}
.preview-nav:active {
  transform: translateY(-50%) scale(0.96);
}
.preview-nav-prev {
  left: max(8px, env(safe-area-inset-left));
}
.preview-nav-next {
  right: max(8px, env(safe-area-inset-right));
}

.preview-counter {
  position: absolute;
  bottom: max(20px, env(safe-area-inset-bottom));
  left: 50%;
  transform: translateX(-50%);
  z-index: 1001;
  padding: 6px 14px;
  border-radius: 999px;
  font-size: 14px;
  color: rgba(255, 255, 255, 0.95);
  background: rgba(0, 0, 0, 0.45);
  pointer-events: none;
}

.close-button {
  position: absolute;
  top: max(16px, env(safe-area-inset-top));
  right: max(20px, env(safe-area-inset-right));
  z-index: 1003;
  color: #fff;
  font-size: 40px;
  line-height: 1;
  cursor: pointer;
  padding: 4px 8px;
}

/* 首次打开：轻微放大 + 淡入 */
.fade-first-enter-active {
  transition:
    opacity 0.38s cubic-bezier(0.25, 0.8, 0.25, 1),
    transform 0.38s cubic-bezier(0.25, 0.8, 0.25, 1);
}
.fade-first-enter-from {
  opacity: 0;
  transform: scale(0.94);
}

/* 下一张：旧图向左淡出，新图从右进入 */
.slide-next-enter-active,
.slide-next-leave-active {
  transition:
    opacity 0.34s cubic-bezier(0.25, 0.8, 0.25, 1),
    transform 0.34s cubic-bezier(0.25, 0.8, 0.25, 1);
}
.slide-next-enter-from {
  opacity: 0;
  transform: translateX(36px);
}
.slide-next-leave-to {
  opacity: 0;
  transform: translateX(-36px);
}

/* 上一张：旧图向右淡出，新图从左进入 */
.slide-prev-enter-active,
.slide-prev-leave-active {
  transition:
    opacity 0.34s cubic-bezier(0.25, 0.8, 0.25, 1),
    transform 0.34s cubic-bezier(0.25, 0.8, 0.25, 1);
}
.slide-prev-enter-from {
  opacity: 0;
  transform: translateX(-36px);
}
.slide-prev-leave-to {
  opacity: 0;
  transform: translateX(36px);
}

@keyframes flipInY {
  0% { opacity: 0; transform: perspective(600px) rotateY(90deg);}
  60% { opacity: 1; transform: perspective(600px) rotateY(-10deg);}
  80% { transform: perspective(600px) rotateY(10deg);}
  100% { opacity: 1; transform: perspective(600px) rotateY(0);}
}
.page-flip-in { animation: flipInY 0.7s cubic-bezier(.23,1.02,.53,.97); backface-visibility: hidden; }
</style>
