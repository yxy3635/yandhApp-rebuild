<template>
  <div class="wechat-chat" :class="{ 'theme-dark': isDarkMode }" @click="handleBodyClick">
    <!-- 微信风格顶栏 -->
    <header class="wechat-header">
      <button class="wechat-back" @click="goBack">
        <svg width="22" height="22" viewBox="0 0 24 24" fill="none">
          <path d="M15 18L9 12L15 6" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/>
        </svg>
      </button>
      <span class="wechat-title">{{ peerUsername }}</span>
      <button class="wechat-more" @click.stop="showPeerInfo = true">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor">
          <circle cx="5" cy="12" r="2"/><circle cx="12" cy="12" r="2"/><circle cx="19" cy="12" r="2"/>
        </svg>
      </button>
    </header>

    <!-- 消息列表 -->
    <main id="chat-list" class="wechat-body" @scroll="onScroll" ref="chatListRef" @click.stop="handleMessageClick">
      <!-- 顶部加载指示器 -->
      <div class="chat-top-loader" :class="{ show: isLoadingMore }">
        <div class="dots"><span class="dot"></span><span class="dot"></span><span class="dot"></span></div>
      </div>

      <template v-for="(msg, idx) in chatData" :key="msg.id">
        <!-- 时间分隔符：与前一条消息时间不同时显示 -->
        <div v-if="idx === 0 || msg.time !== chatData[idx - 1].time" class="wechat-time-sep">
          <span>{{ msg.time }}</span>
        </div>

        <div
          class="wechat-row"
          :class="{ 'wechat-row-self': msg.isMyMessage }"
          @touchstart="handleTouchStart($event, msg)"
          @touchend="handleTouchEnd"
          @mousedown="handleMouseDown($event, msg)"
          @mouseup="handleMouseUp"
          @mouseleave="handleMouseLeave"
        >
          <!-- 对方消息：头像在左 -->
          <img
            v-if="!msg.isMyMessage"
            class="wechat-avatar"
            :src="msg.avatar"
            alt="头像"
            @error="handleMsgAvatarError"
          />

          <div class="wechat-bubble-wrap">
            <!-- 发送中 loading 指示器 -->
            <span v-if="msg.isMyMessage && msg.sending" class="wechat-sending-spinner"></span>
            <!-- 发送失败重试按钮 -->
            <span v-if="msg.isMyMessage && msg.failed" class="wechat-send-failed" @click.stop="retrySend(msg)" title="重新发送">⚠️</span>
            <div
              class="wechat-bubble"
              :class="{
                'wechat-bubble-self': msg.isMyMessage,
                'wechat-bubble-image': msg.type === 'image' || isGifContent(msg.content),
                'wechat-bubble-sending': msg.sending,
                'wechat-bubble-failed': msg.failed
              }"
              v-html="formatMessageContent(msg)"
              ref="bubbles"
              :data-id="msg.id"
            ></div>
          </div>

          <!-- 自己消息：头像在右 -->
          <img
            v-if="msg.isMyMessage"
            class="wechat-avatar"
            :src="msg.avatar"
            alt="头像"
            @error="handleMsgAvatarError"
          />
        </div>
      </template>
    </main>

    <!-- 图片/GIF 放大预览 -->
    <Teleport to="body">
      <div class="image-preview-overlay" v-if="showPreview" @click="closePreview">
        <img :src="previewSrc" class="image-preview-zoom" @click.stop alt="预览" />
        <button class="image-preview-close" @click="closePreview">✕</button>
      </div>
    </Teleport>

    <!-- 长按菜单 -->
    <div id="long-press-menu" class="long-press-menu" v-show="showMenu" :style="{ left: menuX + 'px', top: menuY + 'px' }">
      <div class="menu-item" @click.stop="copyMessage">复制</div>
      <div class="menu-item" v-if="currentMsg && currentMsg.isMyMessage && !currentMsg.sending && !currentMsg.failed" @click.stop="recallMessage">撤回</div>
    </div>

    <!-- 对方信息弹窗 -->
    <Teleport to="body">
      <div class="peer-info-overlay" :class="{ 'theme-dark': isDarkMode }" v-if="showPeerInfo" @click.self="showPeerInfo = false">
        <div class="peer-info-card">
          <img class="peer-info-avatar" :src="peerAvatarResolved" alt="头像" @error="e => e.target.src = defaultAvatar" />
          <div class="peer-info-name">{{ peerUsername }}</div>
          <div class="peer-info-sig">{{ peerSignature || '这个人很懒，什么都没写~' }}</div>
          <button class="peer-switch-btn" @click="openSwitchUser">切换聊天对象</button>
          <button class="peer-info-close" @click="showPeerInfo = false">关闭</button>
        </div>
      </div>
    </Teleport>

    <!-- 切换聊天用户弹窗 -->
    <Teleport to="body">
      <div class="chat-pick-overlay" :class="{ 'theme-dark': isDarkMode }" v-if="showSwitchUser" @click.self="showSwitchUser = false">
        <div class="chat-pick-modal">
          <div class="chat-pick-title">选择聊天对象</div>
          <div v-if="switchLoading" style="text-align:center;padding:20px;color:#999;">加载中...</div>
          <div v-else class="chat-pick-list">
            <div v-for="user in switchUsers" :key="user.id" class="chat-pick-item" @click="switchUser(user)">
              <img class="chat-pick-avatar" :src="getAvatarUrl(user.avatar_url)" alt="头像" @error="e => e.target.src = defaultAvatar" />
              <span class="chat-pick-name">{{ user.username }}</span>
            </div>
          </div>
          <button class="chat-pick-close" @click="showSwitchUser = false">取消</button>
        </div>
      </div>
    </Teleport>

    <!-- 底部输入栏 - 微信风格 -->
    <footer class="wechat-footer" @click.stop>
      <!-- Emoji 面板 -->
      <div class="wechat-panel" v-show="showEmojiPanel">
        <div class="wechat-emoji-grid">
          <span v-for="emoji in emojis" :key="emoji" @click="insertEmoji(emoji)">{{ emoji }}</span>
        </div>
      </div>

      <!-- 更多功能面板 -->
      <div class="wechat-panel wechat-panel-more" v-show="showMorePanel">
        <div class="wechat-more-grid">
          <button class="wechat-more-item" @click="toggleGifPanelAndCloseMore">
            <span class="wechat-more-icon">🎭</span>
            <span>表情</span>
          </button>
          <button class="wechat-more-item" @click="triggerImageUpload">
            <span class="wechat-more-icon">🖼️</span>
            <span>图片</span>
          </button>
        </div>
      </div>

      <!-- GIF 面板 -->
      <div class="wechat-panel wechat-panel-gif" v-show="showGifPanel" @click.stop>
        <div class="gif-panel-header">
          <span style="font-weight:600;font-size:15px;">GIF 表情</span>
          <button class="gif-panel-close" @click="showGifPanel = false">✕</button>
        </div>
        <input
          type="text"
          class="gif-search-input"
          v-model="gifSearchQuery"
          placeholder="搜索表情..."
        />
        <!-- 上传表情区域 -->
        <div class="upload-emoji-area">
          <div class="upload-form">
            <input type="text" v-model="emojiName" placeholder="给表情起个名字吧~" maxlength="20" class="upload-name-input" />
            <label for="emoji-upload" class="upload-emoji-btn">📤 选择表情文件</label>
            <input type="file" id="emoji-upload" accept="image/*" style="display: none;" @change="uploadEmoji" />
          </div>
        </div>
        <div class="gif-grid">
          <div v-if="filteredGifs.length === 0" style="text-align:center;color:#999;padding:20px;">没有找到相关表情</div>
          <img
            v-for="(gif, idx) in filteredGifs"
            :key="idx"
            :src="gif.url"
            :alt="gif.label"
            :title="gif.label"
            class="gif-thumb"
            @click="sendGifMsg(gif.url)"
          />
        </div>
      </div>

      <!-- 输入栏主体 -->
      <div class="wechat-input-bar" @click="showMenu = false">
        <button class="wechat-input-btn" @click="toggleMorePanel" title="更多">
          <svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="16"/><line x1="8" y1="12" x2="16" y2="12"/>
          </svg>
        </button>
        <div class="wechat-input-wrap">
          <input
            type="text"
            id="chat-input"
            ref="chatInputRef"
            class="wechat-text-input"
            v-model="inputText"
            placeholder=""
            @keypress.enter="sendMsg"
            @focus="onInputFocus"
          />
        </div>
        <button class="wechat-input-btn" @click="toggleEmojiPanel" title="表情">
          <svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <circle cx="12" cy="12" r="10"/><path d="M8 14s1.5 2 4 2 4-2 4-2"/><line x1="9" y1="9" x2="9.01" y2="9"/><line x1="15" y1="9" x2="15.01" y2="9"/>
          </svg>
        </button>
        <button
          v-if="inputText.trim()"
          class="wechat-send-btn"
          @click="sendMsg"
        >发送</button>
      </div>
    </footer>
  </div>
</template>

<script setup>
import { ref, onMounted, onBeforeUnmount, computed, nextTick, watch } from 'vue';
import { useRouter, useRoute } from 'vue-router';
import { APP_CONFIG, commonFetch, getAvatarUrl } from '../utils/config';
import defaultAvatar from '../assets/img/default-avatar.png';
import { customAlert } from '../utils/modal';

const router = useRouter();
const route = useRoute();

// 暗黑模式检测
const isDarkMode = ref(document.body.classList.contains('dark-theme'));
let themeObserver = null;

const myId = localStorage.getItem('user_id');
const myUsername = localStorage.getItem('username') || '我';
const myAvatar = localStorage.getItem('avatar') || '';

const peerId = ref(route.query.user_id);
const peerUsername = ref(decodeURIComponent(route.query.username || '私聊'));
const peerAvatar = ref(decodeURIComponent(route.query.avatar || ''));

const chatData = ref([]);
const chatListRef = ref(null);
const chatInputRef = ref(null);

const inputText = ref('');
const showEmojiPanel = ref(false);
const showGifPanel = ref(false);
const showMorePanel = ref(false);

const showMenu = ref(false);
const menuX = ref(0);
const menuY = ref(0);
const currentMsg = ref(null);

// 图片预览
const showPreview = ref(false);
const previewSrc = ref('');

// 对方信息弹窗
const showPeerInfo = ref(false);
const peerSignature = ref('');

// 切换聊天用户弹窗
const showSwitchUser = ref(false);
const switchUsers = ref([]);
const switchLoading = ref(false);

const isLoadingMore = ref(false);
const hasMoreHistory = ref(true);
let oldestMessageId = null;
const PAGE_LIMIT = 100;
const MAX_MESSAGES = 300;
let lastScrollTop = 0;
let allowLoadMore = false;
let pollingTimer = null;
let userScrollingUp = false;
let scrollTimeout = null;

const emojiName = ref('');
const gifSearchQuery = ref('');
const userEmojis = ref([]);
const emojis = [
  '😀', '😃', '😄', '😁', '😆', '😅', '🤣', '😂', '😊', '😇',
  '🙂', '🙃', '😉', '😌', '😍', '🥰', '😘', '😗', '😙', '😚',
  '😋', '😛', '😝', '😜', '🤪', '🤨', '🧐', '🤓', '😎', '🤩',
  '🥳', '😏', '😒', '😞', '😔', '😟', '😕', '🙁', '☹️', '😣',
  '😖', '😫', '😩', '🥺', '😢', '😭', '😤', '😠', '😡', '🤬',
  '😈', '👿', '💀', '☠️', '💩', '🤡', '👻', '💋', '❤️', '💔'
];

const localGifs = [
  {file: 'smile.gif', label: '微笑'},
  {file: 'clap.gif', label: '鼓掌'},
  {file: 'clap2.gif', label: '鼓掌2'},
  {file: 'cheer.gif', label: '加油'},
  {file: 'angry.gif', label: '生气'},
  {file: 'cry.gif', label: '哭泣'},
  {file: 'cry2.gif', label: '哭泣2'},
  {file: 'cry3.gif', label: '哭泣3'},
  {file: 'happy.gif', label: '开心'},
  {file: 'bye.gif', label: '拜拜'},
  {file: 'like.gif', label: '赞'},
  {file: 'heart.gif', label: '比心'},
  {file: 'haha.gif', label: '哈哈'},
  {file: 'wow.gif', label: '惊讶'},
  {file: 'wronged.gif', label: '委屈'},
  {file: 'cold.jpg', label: '冷漠'},
  {file: 'question.gif', label: '疑问'},
  {file: 'buguan.gif', label: '不管'}
];

const SERVER_GIF_BASE = APP_CONFIG.GIF_BASE || `${APP_CONFIG.SERVER_BASE}/img/gif/`;
const SERVER_USER_EMOJI_BASE = APP_CONFIG.USER_EMOJI_BASE || `${APP_CONFIG.SERVER_BASE}/uploads/emojis/`;

const filteredGifs = computed(() => {
  let allGifs = localGifs.map(g => ({ url: SERVER_GIF_BASE + g.file, label: g.label }));
  userEmojis.value.forEach(e => {
    allGifs.push({ url: SERVER_USER_EMOJI_BASE + e.file, label: e.label });
  });
  if (gifSearchQuery.value) {
    return allGifs.filter(g => g.label.includes(gifSearchQuery.value));
  }
  return allGifs;
});

const peerAvatarResolved = computed(() => {
  return getCachedAvatarFor(peerId.value, peerAvatar.value);
});

// 原始时间戳（从 API 返回的 created_at）
function formatMessageTimeRaw(createdAt) {
  if (!createdAt) return '';
  const messageDate = new Date(createdAt);
  const now = new Date();
  const isSameDay = messageDate.toDateString() === now.toDateString();
  const yesterday = new Date(now);
  yesterday.setDate(yesterday.getDate() - 1);
  const isYesterday = messageDate.toDateString() === yesterday.toDateString();

  if (isSameDay) {
    return messageDate.toLocaleTimeString('zh-CN', { hour: '2-digit', minute: '2-digit', hour12: false });
  } else if (isYesterday) {
    return '昨天 ' + messageDate.toLocaleTimeString('zh-CN', { hour: '2-digit', minute: '2-digit', hour12: false });
  } else {
    return messageDate.toLocaleDateString('zh-CN', { month: '2-digit', day: '2-digit' }) +
           ' ' + messageDate.toLocaleTimeString('zh-CN', { hour: '2-digit', minute: '2-digit', hour12: false });
  }
}

function isGifContent(content) {
  if (!content) return false;
  const trimmed = content.trim();
  // 匹配任何 <img ...> 标签（包容不同引号、属性顺序、自闭合写法）
  return /^<img\b[^>]*\bsrc=['"](https?:\/\/[^'"]*\.gif|.*uploads\/emojis\/[^'"]*)['"][^>]*\/?\s*>$/i.test(trimmed);
}

onMounted(async () => {
  if (!myId || !peerId.value) {
    router.push('/interaction');
    return;
  }

  if (peerAvatar.value && peerAvatar.value !== 'img/default-avatar.png') {
    localStorage.setItem(`user_avatar_${peerId.value}`, peerAvatar.value);
  }

  const cached = getCachedAvatarFor(peerId.value, '');
  if (!cached || cached === defaultAvatar) {
    try {
      const profile = await commonFetch(`${APP_CONFIG.API_BASE}/profile.php?user_id=${peerId.value}`);
      if (profile.success && profile.user && profile.user.avatar_url) {
        const avatarUrl = getAvatarUrl(profile.user.avatar_url);
        localStorage.setItem(`user_avatar_${peerId.value}`, avatarUrl);
      }
    } catch (_) { /* 静默失败 */ }
  }

  // 复用个人中心页面的头像获取方式：请求 profile.php 获取自己的头像
  const myCached = getCachedAvatarFor(myId, myAvatar);
  if (!myCached || myCached === defaultAvatar) {
    try {
      const myProfile = await commonFetch(`${APP_CONFIG.API_BASE}/profile.php?user_id=${myId}`);
      if (myProfile.success && myProfile.user && myProfile.user.avatar_url) {
        const avatarUrl = getAvatarUrl(myProfile.user.avatar_url);
        localStorage.setItem(`user_avatar_${myId}`, avatarUrl);
        localStorage.setItem('avatar', avatarUrl);
      }
    } catch (_) { /* 静默失败 */ }
  }

  // 监听对方信息弹窗打开，自动加载资料
  watch(showPeerInfo, (val) => {
    if (val) loadPeerProfile();
  });

  fetchUserEmojis();
  fetchChatHistory(true);
  pollingTimer = setInterval(() => fetchChatHistory(false), 3000);

  // 监听 body 的 class 变化以同步暗黑主题
  themeObserver = new MutationObserver(() => {
    isDarkMode.value = document.body.classList.contains('dark-theme');
  });
  themeObserver.observe(document.body, { attributes: true, attributeFilter: ['class'] });

  if (window.visualViewport) {
    window.visualViewport.addEventListener('resize', handleViewportResize);
    window.visualViewport.addEventListener('scroll', handleViewportResize);
  }
  window.addEventListener('resize', handleWindowResize);
  chatInputRef.value?.addEventListener('focus', onInputFocus);
});

onBeforeUnmount(() => {
  if (themeObserver) themeObserver.disconnect();
  if (pollingTimer) clearInterval(pollingTimer);
  if (window.visualViewport) {
    window.visualViewport.removeEventListener('resize', handleViewportResize);
    window.visualViewport.removeEventListener('scroll', handleViewportResize);
  }
  window.removeEventListener('resize', handleWindowResize);
  chatInputRef.value?.removeEventListener('focus', onInputFocus);
});

const goBack = () => router.back();

// 加载对方资料（签名等）
const loadPeerProfile = async () => {
  try {
    const profile = await commonFetch(`${APP_CONFIG.API_BASE}/profile.php?user_id=${peerId.value}`);
    if (profile.success && profile.user) {
      peerSignature.value = profile.user.signature || '';
    }
  } catch (_) { /* 静默失败 */ }
};

// 打开切换用户弹窗
const openSwitchUser = async () => {
  showPeerInfo.value = false;
  showSwitchUser.value = true;
  switchLoading.value = true;
  const currentUserId = localStorage.getItem('user_id');
  try {
    const data = await commonFetch(`${APP_CONFIG.API_BASE}/user_list.php?current_user_id=${currentUserId}`);
    if (data.success && Array.isArray(data.users)) {
      switchUsers.value = data.users.filter(u => u.id != currentUserId && u.id != peerId.value);
    }
  } catch (e) {
    console.error(e);
  } finally {
    switchLoading.value = false;
  }
};

// 执行切换用户
const switchUser = (user) => {
  // 保存到 localStorage
  localStorage.setItem('saved_chat_user', JSON.stringify({
    id: user.id,
    username: user.username,
    avatar: getAvatarUrl(user.avatar_url)
  }));
  showSwitchUser.value = false;
  // 替换当前页面的路由参数并刷新
  router.replace({
    path: '/chat',
    query: { user_id: user.id, username: user.username, avatar: getAvatarUrl(user.avatar_url) }
  }).then(() => {
    window.location.reload();
  });
};

const handleMsgAvatarError = (e) => {
  if (e.target.src !== defaultAvatar) {
    e.target.src = defaultAvatar;
  }
};

function getCachedAvatarFor(userId, fallback) {
  if (!userId) return getAvatarUrl(fallback);
  const keys = [`user_avatar_${userId}`, `avatar_${userId}`, `user_${userId}_avatar`, `profile_${userId}`];
  for (const key of keys) {
    const val = localStorage.getItem(key);
    if (!val) continue;
    try {
      const obj = JSON.parse(val);
      if (obj && (obj.avatar || obj.avatar_url)) return getAvatarUrl(obj.avatar || obj.avatar_url);
    } catch (_) {
      return getAvatarUrl(val);
    }
  }
  return getAvatarUrl(fallback);
}

// 键盘适配
const initialViewportHeight = ref(window.visualViewport ? window.visualViewport.height : window.innerHeight);

const handleViewportResize = () => {
  if (!window.visualViewport) return;
  const viewport = window.visualViewport;
  const keyboardHeight = initialViewportHeight.value - viewport.height - 40;
  const chatList = chatListRef.value;
  if (keyboardHeight > 100) {
    if (chatList) {
      chatList.style.paddingBottom = keyboardHeight + 'px';
      requestAnimationFrame(() => {
        chatList.scrollTop = chatList.scrollHeight - viewport.height - 120;
      });
    }
  } else {
    if (chatList) chatList.style.paddingBottom = '80px';
    initialViewportHeight.value = viewport.height;
  }
};

const handleWindowResize = () => {
  if (!window.visualViewport) {
    initialViewportHeight.value = window.innerHeight;
    handleViewportResize();
  }
};

const onInputFocus = () => {
  nextTick(() => {
    requestAnimationFrame(() => scrollToBottom());
  });
};

const formatMessageContent = (msg) => {
  if (msg.type === 'image') {
    return `<img class="chat-image-inline" src="${msg.content}" alt="图片" loading="lazy">`;
  }
  if (isGifContent(msg.content)) {
    return msg.content.replace(/class='chat-gif'/g, "class='chat-gif-inline'");
  }
  // 兜底：任意 <img> 标签注入 chat-image-inline class，确保可点击放大
  let html = msg.content;
  if (/<img\b[^>]*>/i.test(html)) {
    html = html.replace(/<img\b([^>]*)>/gi, (match, attrs) => {
      if (/class=/i.test(attrs)) {
        return match.replace(/class=['"]([^'"]*)['"]/i, "class='$1 chat-image-inline'");
      }
      return `<img class="chat-image-inline"${attrs}>`;
    });
  }
  // Emoji 放大渲染
  return html.replace(
    /(\ud83c[\udf00-\udfff]|\ud83d[\udc00-\udfff]|\ud83e[\udc00-\udfff])/g,
    '<span class="chat-emoji-lg">$&</span>'
  );
};

const isAtBottom = () => {
  if (!chatListRef.value) return false;
  return chatListRef.value.scrollHeight - chatListRef.value.scrollTop - chatListRef.value.clientHeight < 80;
};

const scrollToBottom = () => {
  const list = chatListRef.value;
  if (list) {
    requestAnimationFrame(() => {
      list.scrollTop = list.scrollHeight;
    });
  }
};

const fetchChatHistory = async (isFirstLoad = false) => {
  // 用户上滑查看历史 或 正在加载历史 时不轮询，避免滚动跳动
  if (!isFirstLoad && (userScrollingUp || isLoadingMore.value)) return;
  try {
    const res = await commonFetch(`${APP_CONFIG.API_BASE}/get_messages.php?user_id=${myId}&peer_id=${peerId.value}&limit=${PAGE_LIMIT}`);
    if (res.success && Array.isArray(res.messages)) {
      const newChatData = res.messages.map(msg => ({
        id: msg.id,
        from: msg.from_user_id == myId ? myUsername : peerUsername.value,
        avatar: msg.from_user_id == myId ? getCachedAvatarFor(myId, myAvatar) : peerAvatarResolved.value,
        type: msg.content.startsWith('data:image/') || /^https?:\/\/.*\.(png|jpg|jpeg|webp|gif)$/i.test(msg.content) && !msg.content.includes('<img') ? 'image' : 'text',
        content: msg.content,
        time: formatMessageTimeRaw(msg.created_at),
        _rawTime: msg.created_at,
        unread: msg.unread == 1 || msg.unread === true,
        isMyMessage: msg.from_user_id == myId
      }));

      hasMoreHistory.value = !!res.has_more;
      if (newChatData.length > 0) {
        oldestMessageId = newChatData[0].id;
      }

      const existingIds = new Set(chatData.value.map(m => m.id));
      let added = false;
      newChatData.forEach(m => {
        if (!existingIds.has(m.id)) {
          chatData.value.push(m);
          added = true;
        }
      });

      if (chatData.value.length > MAX_MESSAGES) {
        chatData.value = chatData.value.slice(chatData.value.length - MAX_MESSAGES);
        oldestMessageId = chatData.value[0]?.id || null;
      }

      chatData.value.sort((a, b) => a.id - b.id);

      if (isFirstLoad) {
        nextTick(() => {
          requestAnimationFrame(() => scrollToBottom());
          setTimeout(() => { allowLoadMore = true; }, 300);
        });
      } else if (added) {
        const atBottom = isAtBottom();
        nextTick(() => {
          if (atBottom) requestAnimationFrame(() => scrollToBottom());
        });
      }
      markMessagesRead();
    }
  } catch (e) {
    console.error(e);
  }
};

const loadMoreHistory = async () => {
  if (isLoadingMore.value || !hasMoreHistory.value || !oldestMessageId || !allowLoadMore) return;

  isLoadingMore.value = true;
  // 保持 userScrollingUp 为 true，阻止轮询干扰
  userScrollingUp = true;
  if (scrollTimeout) { clearTimeout(scrollTimeout); scrollTimeout = null; }

  const list = chatListRef.value;
  const prevScrollHeight = list.scrollHeight;
  const prevScrollTop = list.scrollTop;

  try {
    const res = await commonFetch(`${APP_CONFIG.API_BASE}/get_messages.php?user_id=${myId}&peer_id=${peerId.value}&limit=${PAGE_LIMIT}&before_id=${oldestMessageId}`);
    if (res.success && Array.isArray(res.messages) && res.messages.length > 0) {
      const moreData = res.messages.map(msg => ({
        id: msg.id,
        from: msg.from_user_id == myId ? myUsername : peerUsername.value,
        avatar: msg.from_user_id == myId ? getCachedAvatarFor(myId, myAvatar) : peerAvatarResolved.value,
        type: msg.content.startsWith('data:image/') || /^https?:\/\/.*\.(png|jpg|jpeg|webp|gif)$/i.test(msg.content) && !msg.content.includes('<img') ? 'image' : 'text',
        content: msg.content,
        time: formatMessageTimeRaw(msg.created_at),
        _rawTime: msg.created_at,
        unread: msg.unread == 1 || msg.unread === true,
        isMyMessage: msg.from_user_id == myId
      }));

      hasMoreHistory.value = !!res.has_more;
      oldestMessageId = moreData[0].id;

      const existingIds = new Set(chatData.value.map(m => m.id));
      const toPrepend = moreData.filter(m => !existingIds.has(m.id));

      if (toPrepend.length > 0) {
        // 构建新数组（避免两次触发响应式）
        const merged = [...toPrepend, ...chatData.value];
        merged.sort((a, b) => a.id - b.id);
        chatData.value = merged;

        // 同步恢复滚动位置——先等 Vue 更新 DOM
        await nextTick();
        // 直接在当前帧恢复，不等到下一帧
        list.scrollTop = prevScrollTop + (list.scrollHeight - prevScrollHeight);
        // 更新 lastScrollTop 防止 onScroll 误判
        lastScrollTop = list.scrollTop;
      }
    } else {
      hasMoreHistory.value = false;
    }
  } catch (e) {
    console.error(e);
  } finally {
    isLoadingMore.value = false;
    // 延长 userScrollingUp 保持时间，给用户继续滚动的时间
    if (scrollTimeout) clearTimeout(scrollTimeout);
    scrollTimeout = setTimeout(() => { userScrollingUp = false; }, 3000);
  }
};

const onScroll = () => {
  if (!chatListRef.value) return;
  const st = chatListRef.value.scrollTop;

  // 正在加载历史时不触发新的加载，防止连环调用
  if (!isLoadingMore.value && st < lastScrollTop && st <= 30) {
    loadMoreHistory();
  }

  userScrollingUp = (st < lastScrollTop) && !isAtBottom();
  if (scrollTimeout) clearTimeout(scrollTimeout);
  if (userScrollingUp) {
    // 用户正在主动滚动时延长保持时间
    scrollTimeout = setTimeout(() => { userScrollingUp = false; }, 3000);
  } else {
    userScrollingUp = false;
  }

  lastScrollTop = st;
};

const markMessagesRead = () => {
  commonFetch(`${APP_CONFIG.API_BASE}/mark_messages_read.php`, {
    method: 'POST',
    headers: {'Content-Type': 'application/json'},
    body: JSON.stringify({user_id: myId, peer_id: peerId.value})
  }).catch(() => {});
};

let tempIdCounter = 0; // 临时消息 ID 生成器（使用超大正数确保排序在底部）

function nextTempId() {
  return 9007199254700000 + (tempIdCounter++);
}

const sendMsg = async () => {
  const text = inputText.value.trim();
  if (!text) return;
  const content = text;
  inputText.value = '';
  showEmojiPanel.value = false;
  showGifPanel.value = false;
  showMorePanel.value = false;

  // 乐观更新：立即在聊天列表中显示消息
  const tempId = nextTempId();
  const tempMsg = {
    id: tempId,
    from: myUsername,
    avatar: getCachedAvatarFor(myId, myAvatar),
    type: 'text',
    content: content,
    time: formatMessageTimeRaw(new Date().toISOString()),
    _rawTime: new Date().toISOString(),
    isMyMessage: true,
    sending: true,
    failed: false
  };
  chatData.value.push(tempMsg);
  nextTick(() => scrollToBottom());

  // 后台发送
  try {
    const res = await commonFetch(`${APP_CONFIG.API_BASE}/send_message.php`, {
      method: 'POST',
      headers: {'Content-Type': 'application/json'},
      body: JSON.stringify({ from_user_id: myId, to_user_id: peerId.value, content })
    });
    if (res.success) {
      // 发送成功：用服务器返回的真实消息替换临时消息
      await fetchChatHistory(false);
      // 清理可能残留的临时消息
      chatData.value = chatData.value.filter(m => m.id !== tempId);
    } else {
      // 发送失败：标记失败
      const msg = chatData.value.find(m => m.id === tempId);
      if (msg) {
        msg.sending = false;
        msg.failed = true;
      }
    }
  } catch (e) {
    console.error(e);
    const msg = chatData.value.find(m => m.id === tempId);
    if (msg) {
      msg.sending = false;
      msg.failed = true;
    }
  }
};

const retrySend = async (failedMsg) => {
  // 重置为发送中状态
  failedMsg.failed = false;
  failedMsg.sending = true;
  try {
    const res = await commonFetch(`${APP_CONFIG.API_BASE}/send_message.php`, {
      method: 'POST',
      headers: {'Content-Type': 'application/json'},
      body: JSON.stringify({ from_user_id: myId, to_user_id: peerId.value, content: failedMsg.content })
    });
    if (res.success) {
      await fetchChatHistory(false);
      chatData.value = chatData.value.filter(m => m.id !== failedMsg.id);
    } else {
      failedMsg.sending = false;
      failedMsg.failed = true;
    }
  } catch (e) {
    console.error(e);
    failedMsg.sending = false;
    failedMsg.failed = true;
  }
};

const sendGifMsg = async (url) => {
  if (!url) return;
  const content = `<img src='${url}' class='chat-gif'>`;
  showGifPanel.value = false;
  showMorePanel.value = false;

  // 乐观更新
  const tempId = nextTempId();
  const tempMsg = {
    id: tempId,
    from: myUsername,
    avatar: getCachedAvatarFor(myId, myAvatar),
    type: 'text',
    content: content,
    time: formatMessageTimeRaw(new Date().toISOString()),
    _rawTime: new Date().toISOString(),
    isMyMessage: true,
    sending: true,
    failed: false
  };
  chatData.value.push(tempMsg);
  nextTick(() => scrollToBottom());

  // 后台发送
  try {
    const res = await commonFetch(`${APP_CONFIG.API_BASE}/send_message.php`, {
      method: 'POST',
      headers: {'Content-Type': 'application/json'},
      body: JSON.stringify({ from_user_id: myId, to_user_id: peerId.value, content })
    });
    if (res.success) {
      await fetchChatHistory(false);
      chatData.value = chatData.value.filter(m => m.id !== tempId);
    } else {
      const msg = chatData.value.find(m => m.id === tempId);
      if (msg) { msg.sending = false; msg.failed = true; }
    }
  } catch (e) {
    console.error(e);
    const msg = chatData.value.find(m => m.id === tempId);
    if (msg) { msg.sending = false; msg.failed = true; }
  }
};

const fetchUserEmojis = async () => {
  try {
    const data = await commonFetch(`${APP_CONFIG.API_BASE}/get_user_emojis.php`);
    if (data.success) {
      userEmojis.value = data.emojis || [];
    }
  } catch (e) { console.error(e); }
};

const uploadEmoji = async (e) => {
  const file = e.target.files[0];
  if (!file) return;
  if (!emojiName.value.trim()) {
    customAlert('请先给表情起个名字');
    return;
  }
  if (file.size > 5 * 1024 * 1024) {
    customAlert('文件大小不能超过5MB');
    return;
  }
  try {
    const reader = new FileReader();
    reader.readAsDataURL(file);
    reader.onload = async () => {
      const base64Data = reader.result;
      const formData = `name=${encodeURIComponent(emojiName.value.trim())}&data=${encodeURIComponent(base64Data)}`;
      const data = await commonFetch(`${APP_CONFIG.API_BASE}/upload_emoji_simple.php`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: formData
      });
      if (data.success) {
        customAlert('上传成功');
        emojiName.value = '';
        await fetchUserEmojis();
      } else {
        customAlert('上传失败: ' + data.message);
      }
    };
  } catch (err) {
    customAlert('上传失败');
  }
  e.target.value = '';
};

const toggleEmojiPanel = () => {
  showEmojiPanel.value = !showEmojiPanel.value;
  showGifPanel.value = false;
  showMorePanel.value = false;
  if (showEmojiPanel.value && chatInputRef.value) chatInputRef.value.focus();
};

const toggleGifPanel = () => {
  showGifPanel.value = !showGifPanel.value;
  showEmojiPanel.value = false;
  showMorePanel.value = false;
  if (showGifPanel.value && chatInputRef.value) chatInputRef.value.focus();
};

const toggleGifPanelAndCloseMore = () => {
  showMorePanel.value = false;
  toggleGifPanel();
};

const toggleMorePanel = () => {
  showMorePanel.value = !showMorePanel.value;
  showEmojiPanel.value = false;
  showGifPanel.value = false;
  if (showMorePanel.value && chatInputRef.value) chatInputRef.value.focus();
};

const insertEmoji = (emoji) => {
  inputText.value += emoji;
};

const triggerImageUpload = () => {
  showMorePanel.value = false;
  customAlert('图片功能开发中');
};

const handleBodyClick = () => {
  showEmojiPanel.value = false;
  showGifPanel.value = false;
  showMorePanel.value = false;
  showMenu.value = false;
};

// 点击消息区域：检测图片/GIF → 放大预览；否则关闭长按菜单
const handleMessageClick = (e) => {
  const target = e.target;
  // 只要点击的是气泡内的 img 就放大，不管有没有 class
  if (target.tagName === 'IMG' && target.closest('.wechat-bubble')) {
    previewSrc.value = target.src;
    showPreview.value = true;
    return;
  }
  // 点击其他地方关闭长按菜单
  showMenu.value = false;
};

const closePreview = () => {
  showPreview.value = false;
  previewSrc.value = '';
};

// 长按菜单
let longPressTimer = null;

const handleTouchStart = (e, msg) => {
  clearTimeout(longPressTimer);
  longPressTimer = setTimeout(() => {
    openMenu(e.touches[0].clientX, e.touches[0].clientY, msg);
  }, 500);
};

const handleTouchEnd = () => clearTimeout(longPressTimer);
const handleMouseDown = (e, msg) => {
  clearTimeout(longPressTimer);
  longPressTimer = setTimeout(() => {
    openMenu(e.clientX, e.clientY, msg);
  }, 500);
};
const handleMouseUp = () => clearTimeout(longPressTimer);
const handleMouseLeave = () => clearTimeout(longPressTimer);

const openMenu = (x, y, msg) => {
  currentMsg.value = msg;
  menuX.value = x;
  menuY.value = y;
  showMenu.value = true;
  nextTick(() => {
    const ww = window.innerWidth;
    const wh = window.innerHeight;
    if (x + 100 > ww) menuX.value = ww - 110;
    if (y + 100 > wh) menuY.value = wh - 110;
  });
};

const copyMessage = () => {
  if (!currentMsg.value) return;
  let text = '';
  if (currentMsg.value.type === 'image' || currentMsg.value.content.includes('<img')) {
    text = '[图片/表情]';
  } else {
    const div = document.createElement('div');
    div.innerHTML = currentMsg.value.content;
    text = div.textContent || div.innerText;
  }
  if (navigator.clipboard) {
    navigator.clipboard.writeText(text).then(() => customAlert('复制成功')).catch(() => fallbackCopy(text));
  } else {
    fallbackCopy(text);
  }
  showMenu.value = false;
};

const fallbackCopy = (text) => {
  const ta = document.createElement('textarea');
  ta.value = text;
  document.body.appendChild(ta);
  ta.select();
  try { document.execCommand('copy'); customAlert('复制成功'); } catch (e) { customAlert('复制失败'); }
  document.body.removeChild(ta);
};

const recallMessage = async () => {
  if (!currentMsg.value || !currentMsg.value.isMyMessage) return;
  try {
    const data = await commonFetch(`${APP_CONFIG.API_BASE}/recall_message.php`, {
      method: 'POST',
      headers: {'Content-Type': 'application/json'},
      body: JSON.stringify({ message_id: currentMsg.value.id, user_id: myId })
    });
    if (data.success) {
      chatData.value = chatData.value.filter(m => m.id !== currentMsg.value.id);
    } else {
      customAlert(data.message || '撤回失败');
    }
  } catch (e) {
    customAlert('网络错误');
  }
  showMenu.value = false;
};
</script>

<style scoped>
/* ====== 整体容器 ====== */
.wechat-chat {
  display: flex;
  flex-direction: column;
  height: 100vh;
  width: 100%;
  max-width: 500px;
  margin: 0 auto;
  background: #EDEDED;
  position: relative;
  overflow: hidden;
}

.theme-dark.wechat-chat {
  background: transparent;
}

/* ====== 微信顶栏 ====== */
.wechat-header {
  display: flex;
  align-items: center;
  height: 52px;
  padding: 0 12px;
  background: #EDEDED;
  flex-shrink: 0;
  z-index: 10;
  border-bottom: 0.5px solid rgba(0,0,0,0.1);
}

.theme-dark .wechat-header {
  background: rgba(26, 30, 36, 0.85);
  backdrop-filter: blur(20px);
  -webkit-backdrop-filter: blur(20px);
  border-bottom-color: rgba(255,255,255,0.06);
}

.wechat-back {
  background: none;
  border: none;
  color: #000;
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
  width: 36px;
  height: 36px;
  padding: 0;
  flex-shrink: 0;
}

.theme-dark .wechat-back {
  color: var(--text-color-light, #e0e0e0);
}

.wechat-title {
  flex: 1;
  text-align: center;
  font-size: 18px;
  font-weight: 600;
  color: #000;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
  padding: 0 8px;
}

.theme-dark .wechat-title {
  color: var(--text-color-light, #e0e0e0);
}

.wechat-more {
  background: none;
  border: none;
  color: #000;
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
  width: 36px;
  height: 36px;
  padding: 0;
  flex-shrink: 0;
}

.theme-dark .wechat-more {
  color: var(--text-color-light, #e0e0e0);
}

/* ====== 消息列表 ====== */
.wechat-body {
  flex: 1;
  overflow-y: auto;
  padding: 8px 12px 8px 12px;
  -webkit-overflow-scrolling: touch;
}

/* 顶部加载 */
.chat-top-loader {
  display: none;
  align-items: center;
  justify-content: center;
  height: 32px;
}
.chat-top-loader.show { display: flex; }
.dots { display: inline-flex; gap: 6px; }
.dot {
  width: 5px; height: 5px;
  border-radius: 50%;
  background: #b0b0b0;
  animation: dotPulse 1.2s ease-in-out infinite;
}
.theme-dark .dot {
  background: #666;
}
.dot:nth-child(2) { animation-delay: 0.15s; }
.dot:nth-child(3) { animation-delay: 0.30s; }
@keyframes dotPulse {
  0%, 100% { transform: translateY(0); opacity: 0.5; }
  50% { transform: translateY(-4px); opacity: 1; }
}

/* ====== 时间分隔符 ====== */
.wechat-time-sep {
  text-align: center;
  margin: 12px 0;
}
.wechat-time-sep span {
  display: inline-block;
  font-size: 12px;
  color: #b0b0b0;
  background: #dcdcdc;
  border-radius: 3px;
  padding: 2px 8px;
}
.theme-dark .wechat-time-sep span {
  color: var(--text-color-medium, #bbbbbb);
  background: var(--bg-color-card, #3a404b);
}

/* ====== 消息行 ====== */
.wechat-row {
  display: flex;
  align-items: flex-start;
  margin-bottom: 6px;
  padding: 0 4px;
}
/* 自己消息靠右 */
.wechat-row-self {
  justify-content: flex-end;
}

/* 头像 */
.wechat-avatar {
  width: 38px;
  height: 38px;
  border-radius: 4px;
  object-fit: cover;
  flex-shrink: 0;
  margin-top: 2px;
}
/* 对方头像与气泡间距 */
.wechat-row:not(.wechat-row-self) .wechat-avatar {
  margin-right: 6px;
}
/* 自己头像与气泡间距 */
.wechat-row-self .wechat-avatar {
  margin-left: 6px;
}

/* ====== 气泡包裹层 ====== */
.wechat-bubble-wrap {
  max-width: 64%;
  min-width: 0;           /* 关键：允许 flex 子元素收缩到内容以下 */
  overflow: hidden;        /* 裁剪溢出内容 */
}
/* 对方消息：气泡有左边距（头像已占位）*/
.wechat-row:not(.wechat-row-self) .wechat-bubble-wrap {
  margin-right: 8px;
}
/* 自己消息：气泡有右边距（头像在右侧）*/
.wechat-row-self .wechat-bubble-wrap {
  margin-left: 8px;
  display: flex;
  align-items: center;
  gap: 6px;
}

/* ====== 发送状态指示器 ====== */
.wechat-sending-spinner {
  width: 16px;
  height: 16px;
  border: 2px solid #c0c0c0;
  border-top-color: #888;
  border-radius: 50%;
  flex-shrink: 0;
  animation: spin 0.8s linear infinite;
  align-self: center;
}
@keyframes spin {
  to { transform: rotate(360deg); }
}

.wechat-send-failed {
  font-size: 16px;
  cursor: pointer;
  flex-shrink: 0;
  align-self: center;
  opacity: 0.7;
}
.wechat-send-failed:hover {
  opacity: 1;
}

/* 发送中的气泡微微降低不透明度 */
.wechat-bubble-sending {
  opacity: 0.85;
}
/* 发送失败的气泡带红色边框 */
.wechat-bubble-failed {
  border: 1px solid #ff3b30;
}

/* ====== 气泡 ====== */
.wechat-bubble {
  position: relative;
  padding: 10px 13px;
  font-size: 16px;
  line-height: 1.45;
  word-break: break-word;
  border-radius: 4px;
  background: #fff;
  color: #000;
  box-shadow: 0 1px 1px rgba(0,0,0,0.04);
  overflow: hidden;        /* 裁剪超出的图片/GIF */
  min-width: 0;            /* 允许收缩 */
}

.theme-dark .wechat-bubble {
  background: var(--bg-color-card, #3a404b);
  color: var(--text-color-light, #e0e0e0);
}

/* 对方气泡小三角（左侧） */
.wechat-bubble:not(.wechat-bubble-self)::before {
  content: '';
  position: absolute;
  left: -6px;
  top: 12px;
  border-style: solid;
  border-width: 6px 7px 6px 0;
  border-color: transparent #fff transparent transparent;
}

.theme-dark .wechat-bubble:not(.wechat-bubble-self)::before {
  border-right-color: var(--bg-color-card, #3a404b);
}

/* 自己气泡 */
.wechat-bubble-self {
  background: #A8D8F0;
  color: #000;
  box-shadow: 0 1px 1px rgba(0,0,0,0.06);
}

.theme-dark .wechat-bubble-self {
  background: #2B5170;
  color: #e0e0e0;
}

/* 自己气泡小三角（右侧） */
.wechat-bubble-self::after {
  content: '';
  position: absolute;
  right: -6px;
  top: 12px;
  border-style: solid;
  border-width: 6px 0 6px 7px;
  border-color: transparent transparent transparent #A8D8F0;
}

.theme-dark .wechat-bubble-self::after {
  border-left-color: #2B5170;
}

/* 图片气泡不设内边距 */
.wechat-bubble-image {
  padding: 4px !important;
  background: transparent !important;
  box-shadow: none !important;
  overflow: hidden;
  max-width: 100%;
}
.wechat-bubble-image::before,
.wechat-bubble-image::after {
  display: none;
}

/* ====== 气泡内图片/GIF ====== */
/* 兜底：气泡内所有 img 不可超出 */
:deep(.wechat-bubble img) {
  max-width: 100% !important;
  width: auto !important;
  height: auto !important;
  display: block;
}
:deep(.chat-image-inline) {
  max-width: 100% !important;
  width: 100% !important;
  height: auto !important;
  max-height: 260px;
  border-radius: 4px;
  display: block;
  object-fit: contain;
}
:deep(.chat-gif-inline) {
  max-width: 100% !important;
  width: 100% !important;
  height: auto !important;
  max-height: 180px;
  border-radius: 4px;
  display: block;
  object-fit: contain;
}
:deep(.chat-emoji-lg) {
  font-size: 26px;
  vertical-align: middle;
}

/* ====== 底部栏 ====== */
.wechat-footer {
  flex-shrink: 0;
  background: #f7f7f7;
  border-top: 0.5px solid rgba(0,0,0,0.1);
  z-index: 10;
  padding-bottom: env(safe-area-inset-bottom);
}

.theme-dark .wechat-footer {
  background: var(--bg-color-card, #3a404b);
  border-top-color: var(--border-color, #555);
}

/* ====== 面板 ====== */
.wechat-panel {
  background: #f7f7f7;
  border-bottom: 0.5px solid rgba(0,0,0,0.06);
  max-height: 240px;
  overflow-y: auto;
  overflow-x: hidden;
  padding: 12px;
  box-sizing: border-box;
}

.theme-dark .wechat-panel {
  background: var(--bg-color-card, #3a404b);
  border-bottom-color: var(--border-color, #555);
}

.wechat-emoji-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(36px, 1fr));
  gap: 4px;
  justify-items: center;
}
.wechat-emoji-grid span {
  font-size: 26px;
  cursor: pointer;
  padding: 4px;
  text-align: center;
  border-radius: 4px;
  transition: background 0.15s;
  line-height: 1;
}
.wechat-emoji-grid span:hover {
  background: rgba(0,0,0,0.06);
}
.theme-dark .wechat-emoji-grid span:hover {
  background: rgba(255,255,255,0.08);
}

/* 更多面板 */
.wechat-more-grid {
  display: flex;
  gap: 24px;
  padding: 8px 16px;
}
.wechat-more-item {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 6px;
  background: none;
  border: none;
  cursor: pointer;
  font-size: 12px;
  color: #666;
  padding: 8px;
}
.theme-dark .wechat-more-item {
  color: var(--text-color-medium, #bbbbbb);
}
.wechat-more-icon {
  font-size: 32px;
  width: 56px;
  height: 56px;
  display: flex;
  align-items: center;
  justify-content: center;
  background: #fff;
  border-radius: 12px;
  border: 0.5px solid rgba(0,0,0,0.08);
}
.theme-dark .wechat-more-icon {
  background: var(--bg-color-light, #282c34);
  border-color: var(--border-color, #555);
}

/* GIF 面板 */
.wechat-panel-gif {
  max-height: 320px;
}
.gif-panel-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 8px;
  color: #000;
}
.theme-dark .gif-panel-header {
  color: var(--text-color-light, #e0e0e0);
}
.gif-panel-close {
  background: none;
  border: none;
  font-size: 18px;
  cursor: pointer;
  color: #999;
}
.theme-dark .gif-panel-close {
  color: var(--text-color-medium, #bbbbbb);
}
.gif-search-input {
  width: 100%;
  box-sizing: border-box;
  padding: 8px 12px;
  border-radius: 6px;
  border: 1px solid #ddd;
  font-size: 14px;
  margin-bottom: 10px;
  background: #fff;
  color: #000;
}
.theme-dark .gif-search-input {
  background: var(--bg-color-light, #282c34);
  color: var(--text-color-light, #e0e0e0);
  border-color: var(--border-color, #555);
}
.gif-grid {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
  padding: 4px 0;
}
.gif-thumb {
  width: 68px;
  height: 68px;
  object-fit: cover;
  border-radius: 6px;
  cursor: pointer;
  border: 2px solid transparent;
  transition: border-color 0.15s;
}
.gif-thumb:hover {
  border-color: #07C160;
}

/* 上传表情区域 */
.upload-emoji-area {
  padding: 8px;
  margin-bottom: 10px;
  border: 1px dashed #ddd;
  border-radius: 6px;
}
.theme-dark .upload-emoji-area {
  border-color: var(--border-color, #555);
}
.upload-form {
  display: flex;
  gap: 8px;
  align-items: center;
}
.upload-name-input {
  flex: 1;
  padding: 6px 10px;
  border-radius: 6px;
  border: 1px solid #ddd;
  font-size: 14px;
  background: #fff;
  color: #000;
}
.theme-dark .upload-name-input {
  background: var(--bg-color-light, #282c34);
  color: var(--text-color-light, #e0e0e0);
  border-color: var(--border-color, #555);
}
.upload-emoji-btn {
  background: #07C160;
  color: #fff;
  padding: 7px 14px;
  border-radius: 6px;
  font-size: 13px;
  cursor: pointer;
  white-space: nowrap;
}

/* ====== 微信输入栏 ====== */
.wechat-input-bar {
  display: flex;
  align-items: center;
  padding: 6px 10px;
  gap: 6px;
  min-height: 48px;
}

.wechat-input-btn {
  background: none;
  border: none;
  color: #666;
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
  width: 36px;
  height: 36px;
  flex-shrink: 0;
  padding: 0;
}

.theme-dark .wechat-input-btn {
  color: var(--text-color-medium, #bbbbbb);
}

.wechat-input-wrap {
  flex: 1;
}

.wechat-text-input {
  width: 100%;
  box-sizing: border-box;
  padding: 8px 12px;
  font-size: 16px;
  border: none;
  border-radius: 6px;
  background: #fff;
  color: #000;
  outline: none;
  line-height: 1.4;
}

.theme-dark .wechat-text-input {
  background: var(--bg-color-light, #282c34);
  color: var(--text-color-light, #e0e0e0);
}

.wechat-text-input::placeholder {
  color: #bbb;
}
.theme-dark .wechat-text-input::placeholder {
  color: var(--text-color-medium, #bbbbbb);
}

/* 发送按钮 */
.wechat-send-btn {
  background: #07C160;
  color: #fff;
  border: none;
  border-radius: 6px;
  padding: 7px 16px;
  font-size: 15px;
  font-weight: 500;
  cursor: pointer;
  flex-shrink: 0;
  white-space: nowrap;
}
.wechat-send-btn:active {
  background: #06AD56;
}

/* ====== 长按菜单 ====== */
.long-press-menu {
  position: fixed;
  background: #fff;
  border-radius: 6px;
  box-shadow: 0 2px 16px rgba(0,0,0,0.15);
  z-index: 1000;
  padding: 2px 0;
  min-width: 100px;
  overflow: hidden;
}
.theme-dark .long-press-menu {
  background: var(--bg-color-card, #3a404b);
  box-shadow: 0 2px 16px rgba(0,0,0,0.5);
}
.menu-item {
  padding: 12px 20px;
  cursor: pointer;
  font-size: 14px;
  color: #000;
  text-align: center;
}
.theme-dark .menu-item {
  color: var(--text-color-light, #e0e0e0);
}
.menu-item:hover {
  background: #f0f0f0;
}
.theme-dark .menu-item:hover {
  background: var(--bg-color-light, #282c34);
}
.menu-item:active {
  background: #e5e5e5;
}
.theme-dark .menu-item:active {
  background: var(--border-color, #555);
}

/* ====== 图片/GIF 放大预览 ====== */
.image-preview-overlay {
  position: fixed;
  inset: 0;
  background: rgba(0, 0, 0, 0.88);
  z-index: 9999;
  display: flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
  animation: previewFadeIn 0.2s ease;
}
@keyframes previewFadeIn {
  from { opacity: 0; }
  to   { opacity: 1; }
}

.image-preview-zoom {
  max-width: 92vw;
  max-height: 85vh;
  object-fit: contain;
  border-radius: 8px;
  cursor: default;
  box-shadow: 0 8px 40px rgba(0,0,0,0.5);
}

.image-preview-close {
  position: absolute;
  top: 16px;
  right: 20px;
  width: 36px;
  height: 36px;
  border-radius: 50%;
  background: rgba(255,255,255,0.15);
  color: #fff;
  border: none;
  font-size: 18px;
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
  transition: background 0.2s;
}
.image-preview-close:hover {
  background: rgba(255,255,255,0.3);
}

/* ====== 对方信息弹窗 ====== */
.peer-info-overlay {
  position: fixed;
  inset: 0;
  background: rgba(0,0,0,0.5);
  z-index: 9999;
  display: flex;
  align-items: center;
  justify-content: center;
  animation: fadeIn 0.2s ease;
}
@keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }

.peer-info-card {
  background: #fff;
  border-radius: 16px;
  width: 82vw;
  max-width: 340px;
  padding: 28px 24px 20px;
  text-align: center;
  box-shadow: 0 8px 40px rgba(0,0,0,0.2);
}
.peer-info-avatar {
  width: 72px;
  height: 72px;
  border-radius: 50%;
  object-fit: cover;
  margin-bottom: 12px;
  border: 3px solid #f0f0f0;
}
.peer-info-name {
  font-size: 20px;
  font-weight: 700;
  color: #000;
  margin-bottom: 6px;
}
.peer-info-sig {
  font-size: 14px;
  color: #999;
  margin-bottom: 20px;
  min-height: 20px;
}
.peer-switch-btn {
  display: block;
  width: 100%;
  padding: 11px 0;
  border: none;
  border-radius: 8px;
  background: #07C160;
  color: #fff;
  font-size: 15px;
  font-weight: 500;
  cursor: pointer;
  margin-bottom: 10px;
}
.peer-switch-btn:active {
  background: #06AD56;
}
.peer-info-close {
  display: block;
  width: 100%;
  padding: 10px 0;
  border: none;
  border-radius: 8px;
  background: #f0f0f0;
  color: #666;
  font-size: 15px;
  cursor: pointer;
}

/* ====== 切换用户弹窗（复用 Interaction 的样式） ====== */
.chat-pick-overlay {
  position: fixed;
  inset: 0;
  background: rgba(0,0,0,0.5);
  z-index: 10000;
  display: flex;
  align-items: center;
  justify-content: center;
  animation: fadeIn 0.2s ease;
}
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
.chat-pick-title {
  font-size: 18px;
  font-weight: 700;
  text-align: center;
  margin-bottom: 12px;
  color: #000;
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

/* ====== 深色主题适配 ====== */
.theme-dark .peer-info-card,
.theme-dark .chat-pick-modal {
  background: var(--bg-color-card, #3a404b);
}
.theme-dark .peer-info-name,
.theme-dark .chat-pick-title,
.theme-dark .chat-pick-name {
  color: var(--text-color-light, #e0e0e0);
}
.theme-dark .peer-info-close,
.theme-dark .chat-pick-close {
  background: rgba(255,255,255,0.08);
  color: var(--text-color-medium, #bbb);
}
.theme-dark .chat-pick-item:hover {
  background: rgba(255,255,255,0.05);
}

/* ====== 响应式 ====== */
@media (max-width: 500px) {
  .wechat-header { height: 48px; padding: 0 8px; }
  .wechat-title { font-size: 17px; }
  .wechat-avatar { width: 34px; height: 34px; }
  .wechat-bubble { font-size: 15px; padding: 8px 11px; }
  .wechat-bubble-wrap { max-width: 72%; }
  .wechat-text-input { font-size: 15px; }
  .wechat-row { margin-bottom: 4px; }
  .wechat-emoji-grid span { font-size: 22px; padding: 3px; }
  .wechat-emoji-grid { grid-template-columns: repeat(auto-fill, minmax(30px, 1fr)); gap: 2px; }
  .wechat-panel { padding: 8px; max-height: 180px; }
}
</style>
