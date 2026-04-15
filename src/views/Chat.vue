<template>
  <div class="chat-container" @click="handleBodyClick">
    <header class="home-header chat-header">
      <button id="back-btn" @click="goBack"></button>
      <img id="chat-peer-avatar" class="chat-peer-avatar" :src="peerAvatarResolved" alt="头像" @error="handleAvatarError">
      <span id="chat-peer-username">{{ peerUsername }}</span>
    </header>

    <main id="chat-list" class="chat-list-main" @scroll="onScroll" ref="chatListRef">
      <div class="chat-top-loader" :class="{ show: isLoadingMore }">
        <div class="dots"><span class="dot"></span><span class="dot"></span><span class="dot"></span></div>
      </div>

      <div class="chat-row" v-for="msg in chatData" :key="msg.id" :class="{ me: msg.isMyMessage, 'other': !msg.isMyMessage }"
           @touchstart="handleTouchStart($event, msg)"
           @touchend="handleTouchEnd"
           @mousedown="handleMouseDown($event, msg)"
           @mouseup="handleMouseUp"
           @mouseleave="handleMouseLeave">
        <img class="chat-avatar" :src="msg.avatar" alt="头像" @error="handleMsgAvatarError">
        <div>
          <div class="chat-bubble" v-html="formatMessageContent(msg)" ref="bubbles" :data-id="msg.id"></div>
          <div class="chat-meta">{{ msg.from }} · {{ msg.time }}</div>
        </div>
      </div>
    </main>

    <!-- 长按菜单 -->
    <div id="long-press-menu" class="long-press-menu" v-show="showMenu" :style="{ left: menuX + 'px', top: menuY + 'px' }">
      <div class="menu-item" @click.stop="copyMessage">复制</div>
      <div class="menu-item" v-if="currentMsg && currentMsg.isMyMessage" @click.stop="recallMessage">撤回</div>
    </div>

    <!-- Emoji panel -->
    <div id="emoji-panel" v-show="showEmojiPanel" @click.stop>
      <div class="emoji-grid">
        <span v-for="emoji in emojis" :key="emoji" @click="insertEmoji(emoji)">{{ emoji }}</span>
      </div>
    </div>

    <!-- GIF panel -->
    <div id="gif-panel" v-show="showGifPanel" @click.stop>
      <input type="text" id="gif-search" v-model="gifSearchQuery" placeholder="搜索GIF" style="width:90%;margin:8px auto;display:block;">
      
      <!-- 上传表情包区域 -->
      <div class="upload-emoji-area">
        <div class="upload-form">
          <input type="text" v-model="emojiName" placeholder="给表情起个名字吧~" maxlength="20">
          <label for="emoji-upload" class="upload-emoji-btn">
            📤 选择表情文件
          </label>
          <input type="file" id="emoji-upload" accept="image/*" style="display: none;" @change="uploadEmoji">
        </div>
        <div class="upload-tip">宝宝可以上传自己喜欢的表情包噢~</div>
      </div>
      
      <div class="gif-grid">
        <div v-if="filteredGifs.length === 0" style="text-align:center;color:#aaa;">没有找到相关表情</div>
        <img v-for="(gif, idx) in filteredGifs" :key="idx" 
             :src="gif.url" 
             :alt="gif.label" 
             :title="gif.label"
             class="gif-thumb"
             @click="sendGifMsg(gif.url)">
      </div>
    </div>

    <footer @click.stop>
      <div class="input-area">
        <button id="emoji-btn" @click="toggleEmojiPanel">😀</button>
        <button id="gif-btn" @click="toggleGifPanel">GIF</button>
        <input type="text" id="chat-input" v-model="inputText" placeholder="输入消息..." @keypress.enter="sendMsg" ref="chatInputRef">
        <button id="send-btn" @click="sendMsg">发送</button>
      </div>
    </footer>
  </div>
</template>

<script setup>
import { ref, onMounted, onBeforeUnmount, computed, nextTick } from 'vue';
import { useRouter, useRoute } from 'vue-router';
import { APP_CONFIG, commonFetch, getAvatarUrl } from '../utils/config';
import defaultAvatar from '../assets/img/default-avatar.png';
import { customAlert } from '../utils/modal';

const router = useRouter();
const route = useRoute();

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

const showMenu = ref(false);
const menuX = ref(0);
const menuY = ref(0);
const currentMsg = ref(null);

const isLoadingMore = ref(false);
const hasMoreHistory = ref(true);
let oldestMessageId = null;
const PAGE_LIMIT = 100;
let lastScrollTop = 0;
let allowLoadMore = false;
let pollingTimer = null;

const emojiName = ref('');
const gifSearchQuery = ref('');
const userEmojis = ref([]);
const emojis = [
  '😀', '😃', '😄', '😁', '😆', '😅', '🤣', '😂', '😊', '😇',
  '🙂', '🙃', '😉', '😌', '😍', '🥰', '😘', '😗', '😙', '😚',
  '😋', '😛', '😝', '😜', '🤪', '🤨', '🧐', '🤓', '😎', '🤩',
  '🥳', '😏', '😒', '😞', '😔', '😟', '😕', '🙁', '☹️', '😣'
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

onMounted(() => {
  if (!myId || !peerId.value) {
    router.push('/interaction');
    return;
  }
  
  if (peerAvatar.value && peerAvatar.value !== 'img/default-avatar.png') {
    localStorage.setItem(`user_avatar_${peerId.value}`, peerAvatar.value);
  }
  
  fetchUserEmojis();
  fetchChatHistory(true);
  pollingTimer = setInterval(() => fetchChatHistory(false), 1000); // 1s polling
});

onBeforeUnmount(() => {
  if (pollingTimer) clearInterval(pollingTimer);
});

const goBack = () => router.back();

const handleAvatarError = (e) => { e.target.src = defaultAvatar; };
const handleMsgAvatarError = (e) => { e.target.src = defaultAvatar; };

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

const formatMessageTime = (createdAt) => {
  if (!createdAt) return '';
  const messageDate = new Date(createdAt);
  const now = new Date();
  
  const isSameDay = messageDate.toDateString() === now.toDateString();
  
  if (isSameDay) {
    return messageDate.toLocaleTimeString('zh-CN', { hour: '2-digit', minute: '2-digit', hour12: false });
  } else {
    return messageDate.toLocaleDateString('zh-CN', { year: 'numeric', month: '2-digit', day: '2-digit' }) + 
           ' ' + messageDate.toLocaleTimeString('zh-CN', { hour: '2-digit', minute: '2-digit', hour12: false });
  }
};

const formatMessageContent = (msg) => {
  if (msg.type === 'image') {
    return `<img class="chat-image" src="${msg.content}" alt="图片">`;
  }
  if (/^<img src=['"]https?:.*\.gif['"][^>]*>$/i.test(msg.content.trim()) || 
      /^<img src=['"].*uploads\/emojis\/.*['"][^>]*>$/i.test(msg.content.trim())) {
    return msg.content;
  }
  return msg.content.replace(/(\ud83c[\udf00-\udfff]|\ud83d[\udc00-\udfff]|\ud83e[\udc00-\udfff])/g, '<span class="chat-emoji">$&</span>');
};

const isAtBottom = () => {
  if (!chatListRef.value) return false;
  return chatListRef.value.scrollHeight - chatListRef.value.scrollTop - chatListRef.value.clientHeight < 50;
};

const scrollToBottom = () => {
  if (chatListRef.value) {
    chatListRef.value.scrollTop = chatListRef.value.scrollHeight;
  }
};

const fetchChatHistory = async (isFirstLoad = false) => {
  try {
    const res = await commonFetch(`${APP_CONFIG.API_BASE}/get_messages.php?user_id=${myId}&peer_id=${peerId.value}&limit=${PAGE_LIMIT}`);
    if (res.success && Array.isArray(res.messages)) {
      const newChatData = res.messages.map(msg => ({
        id: msg.id,
        from: msg.from_user_id == myId ? myUsername : peerUsername.value,
        avatar: msg.from_user_id == myId ? getCachedAvatarFor(myId, myAvatar) : peerAvatarResolved.value,
        type: msg.content.startsWith('data:image/') || /^https?:\/\/.*\.(png|jpg|jpeg|webp|gif)$/i.test(msg.content) && !msg.content.includes('<img') ? 'image' : 'text',
        content: msg.content,
        time: formatMessageTime(msg.created_at),
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
      
      chatData.value.sort((a, b) => a.id - b.id);

      if (isFirstLoad) {
        nextTick(() => {
          scrollToBottom();
          setTimeout(() => { allowLoadMore = true; }, 200);
        });
      } else if (added) {
        const atBottom = isAtBottom();
        nextTick(() => {
          if (atBottom) scrollToBottom();
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
        time: formatMessageTime(msg.created_at),
        unread: msg.unread == 1 || msg.unread === true,
        isMyMessage: msg.from_user_id == myId
      }));

      hasMoreHistory.value = !!res.has_more;
      oldestMessageId = moreData[0].id;

      const existingIds = new Set(chatData.value.map(m => m.id));
      const toPrepend = moreData.filter(m => !existingIds.has(m.id));
      chatData.value = [...toPrepend, ...chatData.value];
      
      chatData.value.sort((a, b) => a.id - b.id);

      nextTick(() => {
        list.scrollTop = prevScrollTop + (list.scrollHeight - prevScrollHeight);
      });
    } else {
      hasMoreHistory.value = false;
    }
  } catch (e) {
    console.error(e);
  } finally {
    isLoadingMore.value = false;
  }
};

const onScroll = () => {
  if (!chatListRef.value) return;
  const st = chatListRef.value.scrollTop;
  if (st < lastScrollTop && st <= 10) {
    loadMoreHistory();
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

const sendMsg = async () => {
  const text = inputText.value.trim();
  if (!text) return;
  
  await commonFetch(`${APP_CONFIG.API_BASE}/send_message.php`, {
    method: 'POST',
    headers: {'Content-Type': 'application/json'},
    body: JSON.stringify({
      from_user_id: myId,
      to_user_id: peerId.value,
      content: text
    })
  });
  inputText.value = '';
  await fetchChatHistory(false);
  nextTick(() => scrollToBottom());
  showEmojiPanel.value = false;
  showGifPanel.value = false;
};

const sendGifMsg = async (url) => {
  if (!url) return;
  await commonFetch(`${APP_CONFIG.API_BASE}/send_message.php`, {
    method: 'POST',
    headers: {'Content-Type': 'application/json'},
    body: JSON.stringify({
      from_user_id: myId,
      to_user_id: peerId.value,
      content: `<img src='${url}' class='chat-gif'>`
    })
  });
  await fetchChatHistory(false);
  nextTick(() => scrollToBottom());
  showGifPanel.value = false;
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
  if (showEmojiPanel.value && chatInputRef.value) chatInputRef.value.focus();
};

const toggleGifPanel = () => {
  showGifPanel.value = !showGifPanel.value;
  showEmojiPanel.value = false;
  if (showGifPanel.value && chatInputRef.value) chatInputRef.value.focus();
};

const insertEmoji = (emoji) => {
  inputText.value += emoji;
};

const handleBodyClick = () => {
  showEmojiPanel.value = false;
  showGifPanel.value = false;
  showMenu.value = false;
};

const hidePanels = () => {
  showEmojiPanel.value = false;
  showGifPanel.value = false;
};

// Long press logic
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
    // Strip HTML tags for emoji spans
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
  try {
    document.execCommand('copy');
    customAlert('复制成功');
  } catch (e) {
    customAlert('复制失败');
  }
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
.chat-container {
  display: flex;
  flex-direction: column;
  height: 100vh;
  background: var(--bg-color-light, #f6f8fa);
}

:global(body.dark-theme) .chat-container {
  background: #282c34;
}

.chat-header {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 10px 12px;
  height: 60px;
  box-sizing: border-box;
  background: var(--header-bg, #ffffff);
  box-shadow: 0 2px 12px var(--shadow-color, rgba(0,0,0,0.08));
  position: fixed;
  top: 0; left: 0; right: 0;
  z-index: 100;
}

#back-btn {
  background: var(--bg-color-light, #f0f6ff);
  color: var(--nav-btn-active-color, #2196f3);
  border: none;
  border-radius: 50%;
  width: 38px;
  height: 38px;
  font-size: 20px;
  font-weight: bold;
  display: flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
}
#back-btn::before { content: '←'; font-size: 22px; }

.chat-peer-avatar {
  width: 38px; height: 38px; border-radius: 50%; object-fit: cover;
  border: 2px solid var(--border-color, #ddd);
}

#chat-peer-username {
  font-size: 18px; font-weight: bold; flex: 1; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
  color: var(--text-color-light, #222);
}

.chat-list-main {
  flex: 1;
  padding: 80px 8px 90px 8px;
  overflow-y: auto;
  scroll-behavior: smooth;
  margin-top: 60px;
}

.chat-top-loader {
  display: none; align-items: center; justify-content: center; height: 36px;
}
.chat-top-loader.show { display: flex; }
.dots { display: inline-flex; gap: 6px; }
.dot { width: 6px; height: 6px; border-radius: 50%; background: #8ea6ff; animation: dotPulse 1.2s ease-in-out infinite; }
@keyframes dotPulse { 0%, 100% { transform: translateY(0); opacity: 0.5; } 50% { transform: translateY(-4px); opacity: 1; } }

.chat-row { display: flex; align-items: flex-end; margin-bottom: 16px; }
.chat-row.me { flex-direction: row-reverse; }

.chat-avatar {
  width: 38px; height: 38px; border-radius: 50%; object-fit: cover; margin: 0 8px;
}

.chat-bubble {
  max-width: 70vw; padding: 14px 18px; border-radius: 22px 22px 8px 22px; font-size: 16px;
  word-break: break-word;
}
:deep(.chat-gif) { max-width: 120px; max-height: 120px; border-radius: 8px; }
:deep(.chat-image) { max-width: 100%; border-radius: 12px; margin-top: 4px; }
:deep(.chat-emoji) { font-size: 28px; vertical-align: middle; }

.chat-row.me .chat-bubble { background: linear-gradient(90deg,#007aff,#00c6ff); color: #fff; border-radius: 22px 22px 22px 8px; }
.chat-row.other .chat-bubble { background: var(--bg-color-card, #fff); color: var(--text-color-light, #222); }

.chat-meta { font-size: 13px; color: var(--text-color-medium, #666); margin-top: 4px; text-align: right; }

footer {
  position: fixed; bottom: 0; left: 0; right: 0; background: var(--bg-color-card, #fff);
  border-top: 1px solid var(--border-color, #eee); padding: 8px 6px; z-index: 10;
}

.input-area { display: flex; align-items: center; height: 54px; }

#emoji-btn, #gif-btn {
  background: none; border: none; font-size: 22px; cursor: pointer; padding: 0 6px; color: var(--text-color-medium, #666);
}

#gif-btn {
  background: linear-gradient(90deg, #ffbfa8 0%, #ff6f91 100%);
  color: #fff; border-radius: 8px; font-size: 16px; padding: 0 10px; height: 34px; margin: 0 4px;
}

#chat-input {
  flex: 1; font-size: 16px; border-radius: 12px; border: 1.5px solid var(--border-color, #ddd); padding: 10px 12px; height: 34px;
  background: var(--bg-color-light, #f9f9f9);
  color: var(--text-color-light, #222);
}
#chat-input:focus { border-color: #007aff; outline: none; }

#send-btn {
  background: linear-gradient(90deg,#007aff,#00c6ff); color: #fff; border: none; border-radius: 18px;
  padding: 0 16px; height: 34px; cursor: pointer; margin-left: 8px;
}

#emoji-panel {
  position: absolute; bottom: 70px; left: 0; right: 0; background: var(--bg-color-card, #f0f4ff); border-top-left-radius: 18px;
  border-top-right-radius: 18px; max-height: 200px; overflow-y: auto; padding: 10px; z-index: 100;
  box-shadow: 0 -4px 12px var(--shadow-color, rgba(0,0,0,0.1));
}

.emoji-grid { display: flex; flex-wrap: wrap; gap: 8px; justify-content: center; }
.emoji-grid span { font-size: 28px; cursor: pointer; padding: 4px; }

#gif-panel {
  position: absolute; bottom: 70px; left: 10px; right: 10px; background: var(--bg-color-card, #fff); border-radius: 16px;
  max-width: 420px; max-height: 260px; overflow-y: auto; padding: 10px 0; z-index: 100; box-shadow: 0 4px 24px var(--shadow-color, rgba(0,0,0,0.1));
}

.upload-emoji-area { text-align: center; padding: 12px; margin: 8px; border: 2px dashed var(--border-color, #ddd); border-radius: 8px; }
.upload-form { display: flex; flex-direction: column; gap: 8px; align-items: center; }
.upload-emoji-area input[type=text] { padding: 6px; border-radius: 8px; border: 1px solid var(--border-color, #ddd); text-align: center; background: var(--bg-color-light, #fff); color: var(--text-color-light, #222); }
.upload-emoji-btn { background: #007aff; color: white; padding: 6px 12px; border-radius: 12px; cursor: pointer; }
.upload-tip { font-size: 12px; color: var(--text-color-medium, #888); margin-top: 4px; }

.gif-grid { display: flex; flex-wrap: wrap; gap: 8px; padding: 0 10px; }
.gif-thumb { width: 64px; height: 64px; object-fit: cover; border-radius: 8px; cursor: pointer; border: 2px solid transparent; }
.gif-thumb:hover { border-color: #ff6f91; transform: scale(1.05); }

.long-press-menu {
  position: fixed; background: var(--bg-color-card, #fff); border: 1px solid var(--border-color, #ddd); border-radius: 8px; box-shadow: 0 4px 12px var(--shadow-color, rgba(0,0,0,0.1));
  z-index: 1000; padding: 4px 0; min-width: 100px;
}
.menu-item { padding: 12px 16px; cursor: pointer; font-size: 14px; text-align: center; color: var(--text-color-light, #222); }
.menu-item:hover { background: var(--bg-color-light, #f0f8ff); }
</style>
