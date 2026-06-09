<template>
  <div class="ai-container" @click="closeImagePreview">
    <AppHeader title="雨 & AI(Beta)">
      <template #actions>
        <button class="app-header-btn icon-only" @click="showHistory = true" title="历史记录">
          <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/>
          </svg>
        </button>
      </template>
    </AppHeader>

    <!-- 历史记录面板 -->
    <div class="history-overlay" :class="{ show: showHistory }" @click="showHistory = false">
      <div class="history-panel" @click.stop>
        <div class="history-header">
          <h3>对话历史</h3>
          <button class="history-close" @click="showHistory = false">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
          </button>
        </div>
        <button class="new-chat-btn" @click="newChat">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
          新对话
        </button>
        <div class="history-list" v-if="sessions.length">
          <div class="history-item" v-for="s in sessions" :key="s.id" @click="loadSession(s.id)">
            <div class="history-item-info">
              <div class="history-item-title">{{ s.title }}</div>
              <div class="history-item-date">{{ s.date }}</div>
            </div>
            <button class="history-item-del" @click.stop="deleteSession(s.id)" title="删除">
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
            </button>
          </div>
        </div>
        <div v-else class="history-empty">暂无历史记录</div>
      </div>
    </div>

    <main class="ai-chat-main" ref="chatListRef">
      <div v-if="messages.length === 0" class="ai-empty">
        <div class="ai-empty-icon">
          <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
            <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/>
            <path d="M8 10h.01M12 10h.01M16 10h.01" stroke-width="2"/>
          </svg>
        </div>
        <p>有问题尽管问我</p>
      </div>

      <div class="ai-message" v-for="(msg, idx) in messages" :key="idx" :class="msg.role">
        <!-- 正常内容气泡 -->
        <div v-if="msg.content" class="ai-bubble" v-html="renderMarkdown(msg.content)"></div>
        <!-- 思考中内联指示器（assistant 消息无内容时显示） -->
        <div v-if="!msg.content && msg.role === 'assistant'" class="ai-thinking-inline">
          <Transition name="thinking-content" mode="out-in">
            <span v-if="showEffortMessage" key="effort" class="ai-thinking-effort">叶鱼正在努力思考</span>
            <span v-else key="normal" class="ai-thinking-content">
          <div class="ai-loader-wrap">
            <div class="ai-loader"></div>
          </div>
          <span class="ai-thinking-label">
            叶鱼思考中
          </span>
          <Transition v-if="currentVisionStep" name="vision-step" mode="out-in">
            <span
              class="ai-vision-step"
              :key="currentVisionStep._key"
              :class="{ done: currentVisionStep.status === 'done' }"
            >
              <span class="ai-vision-text">{{ currentVisionStep.text }}</span>
            </span>
          </Transition>
          <span v-if="thinkingSeconds >= 5" class="ai-thinking-seconds">{{ thinkingSeconds }}s</span>
            </span>
          </Transition>
        </div>
        <div class="ai-images" v-if="msg.images && msg.images.length">
          <img v-for="(img, i) in msg.images" :key="i" :src="img" class="ai-msg-img" @click.stop="previewImage = img" alt="图片" />
        </div>
        <div class="ai-files" v-if="msg.files && msg.files.length">
          <a v-for="f in msg.files" :key="f.name" class="ai-file-link" :href="getFileUrl(f.url)" @click.prevent="openDownloadUrl(f.url)">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/>
            </svg>
            <span class="ai-file-name">{{ f.name }}</span>
            <span class="ai-file-size">{{ formatFileSize(f.size) }}</span>
          </a>
        </div>
        <div class="ai-file-hint" v-if="msg.fileHint">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/>
          </svg>
          <span>{{ msg.fileHint }}</span>
        </div>
      </div>

    </main>

    <div class="ai-preview-overlay" v-if="previewImage" @click="previewImage = null">
      <button class="ai-preview-close" @click.stop="previewImage = null" title="关闭">
        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
      </button>
      <img :src="previewImage" class="ai-preview-img" @click.stop />
    </div>

    <footer class="ai-footer" @click.stop>
      <div class="ai-thumbnails" v-if="pendingImages.length">
        <div class="ai-thumb-wrap" v-for="(img, i) in pendingImages" :key="i">
          <img :src="img" class="ai-thumb" />
          <button class="ai-thumb-remove" @click="removeImage(i)">x</button>
        </div>
      </div>
      <div class="ai-input-row">
        <label class="ai-upload-btn" title="上传图片">
          <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <rect x="3" y="3" width="18" height="18" rx="2" ry="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/>
          </svg>
          <input type="file" accept="image/*" multiple @change="onImagesSelected" style="display:none" />
        </label>
        <textarea
          ref="inputRef"
          class="ai-input"
          v-model="inputText"
          placeholder="输入消息..."
          rows="1"
          @keydown.enter.exact.prevent="sendMessage"
          @input="autoResize"
        ></textarea>
        <button class="ai-send-btn" @click="sendMessage" :disabled="!inputText.trim() && !pendingImages.length || thinking">
          <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/>
          </svg>
        </button>
      </div>
    </footer>
  </div>
</template>

<script setup>
import { ref, reactive, nextTick, onMounted, onBeforeUnmount, onActivated, onDeactivated, watch } from 'vue'
import { APP_CONFIG, commonFetch } from '../utils/config'
import { marked } from 'marked'
import hljs from 'highlight.js/lib/common'
import 'highlight.js/styles/github.css'

marked.setOptions({
  breaks: true,
  gfm: true,
  highlight(code, lang) {
    if (lang && hljs.getLanguage(lang)) {
      try {
        return hljs.highlight(code, { language: lang }).value
      } catch {}
    }
    try {
      return hljs.highlightAuto(code).value
    } catch {}
    return code
  }
})

const STORAGE_KEY = 'ai_chat_sessions'
const CURRENT_KEY = 'ai_current_session'

const messages = ref([])
const inputText = ref('')
const pendingImages = ref([])
const thinking = ref(false)
const progressSteps = ref([])  // { text, status: 'done'|'active'|'pending' }
const thinkingSeconds = ref(0)
const showEffortMessage = ref(false)
const previewImage = ref(null)
const chatListRef = ref(null)
const inputRef = ref(null)
const showHistory = ref(false)
const currentSessionId = ref(null)

const sessions = ref([])

// AbortController：用于在页面真正销毁时中断请求，但 KeepAlive 切换页面时不中断
let abortController = null
function getAbortSignal() {
  // 如果已有未完成的请求，先中断旧的
  if (abortController) abortController.abort()
  abortController = new AbortController()
  return abortController.signal
}

onMounted(() => {
  loadSessions()
  const savedId = localStorage.getItem(CURRENT_KEY)
  if (savedId) {
    const found = sessions.value.find(s => s.id === savedId)
    if (found) {
      currentSessionId.value = found.id
      messages.value = found.messages || []
      nextTick(() => scrollToBottom())
    }
  }
  // 键盘适配
  if (window.visualViewport) {
    window.visualViewport.addEventListener('resize', onViewportChange)
    window.visualViewport.addEventListener('scroll', onViewportChange)
  }
  if (inputRef.value) {
    inputRef.value.addEventListener('focus', onInputFocus)
  }
})

// KeepAlive：切走页面时保存会话，但不中断请求
onDeactivated(() => {
  saveCurrentSession()
})

// KeepAlive：切回来时滚动到底部
onActivated(() => {
  nextTick(() => scrollToBottom())
})

let _aiViewportTimer = null
function onViewportChange() {
  if (_aiViewportTimer) clearTimeout(_aiViewportTimer)
  _aiViewportTimer = setTimeout(() => scrollToBottom(), 100)
}
function onInputFocus() {
  nextTick(() => { setTimeout(() => scrollToBottom(), 300) })
}

onBeforeUnmount(() => {
  // 真正销毁时中断所有未完成的请求
  if (abortController) {
    abortController.abort()
    abortController = null
  }
  if (window.visualViewport) {
    window.visualViewport.removeEventListener('resize', onViewportChange)
    window.visualViewport.removeEventListener('scroll', onViewportChange)
  }
  if (inputRef.value) {
    inputRef.value.removeEventListener('focus', onInputFocus)
  }
})

watch(showHistory, (val) => {
  if (val) loadSessions()
})

function loadSessions() {
  try {
    const raw = localStorage.getItem(STORAGE_KEY)
    sessions.value = raw ? JSON.parse(raw) : []
    sessions.value.sort((a, b) => b.date.localeCompare(a.date))
  } catch {
    sessions.value = []
  }
}

function saveCurrentSession() {
  if (messages.value.length === 0) return
  const id = currentSessionId.value || Date.now().toString()
  currentSessionId.value = id
  localStorage.setItem(CURRENT_KEY, id)

  const title = (() => {
    const first = messages.value.find(m => m.role === 'user')
    if (!first) return '新对话'
    const t = first.content.replace(/\n/g, ' ').trim()
    return t.length > 24 ? t.slice(0, 24) + '...' : t
  })()

  const session = {
    id,
    title,
    date: new Date().toISOString(),
    // 不存储 base64 图片（太大，会撑爆 localStorage），只保留文字内容
    messages: messages.value.map(m => ({ role: m.role, content: m.content }))
  }

  loadSessions()
  const idx = sessions.value.findIndex(s => s.id === id)
  if (idx >= 0) {
    sessions.value[idx] = session
  } else {
    sessions.value.unshift(session)
  }

  try {
    localStorage.setItem(STORAGE_KEY, JSON.stringify(sessions.value))
  } catch {
    // localStorage full, remove oldest sessions
    sessions.value = sessions.value.slice(0, 20)
    localStorage.setItem(STORAGE_KEY, JSON.stringify(sessions.value))
  }
}

function loadSession(id) {
  const s = sessions.value.find(s => s.id === id)
  if (s) {
    currentSessionId.value = s.id
    messages.value = s.messages || []
    localStorage.setItem(CURRENT_KEY, s.id)
    showHistory.value = false
    nextTick(() => scrollToBottom())
  }
}

function deleteSession(id) {
  sessions.value = sessions.value.filter(s => s.id !== id)
  localStorage.setItem(STORAGE_KEY, JSON.stringify(sessions.value))
  if (currentSessionId.value === id) {
    currentSessionId.value = null
    messages.value = []
    localStorage.removeItem(CURRENT_KEY)
  }
}

function newChat() {
  currentSessionId.value = null
  messages.value = []
  localStorage.removeItem(CURRENT_KEY)
  showHistory.value = false
}

// ====== Grok 式进度步骤管理 ======

let _progressKey = 0
let _typewriterTimers = []
let _leaveTimers = []
let _thinkingTimer = null
let _progressQueue = Promise.resolve()
let _effortTimer = null
let _effortReturnTimer = null

const visibleSteps = ref([])
const currentVisionStep = ref(null)

function startThinkingTimer() {
  if (_thinkingTimer) clearInterval(_thinkingTimer)
  if (_effortTimer) clearTimeout(_effortTimer)
  if (_effortReturnTimer) clearTimeout(_effortReturnTimer)
  thinkingSeconds.value = 0
  showEffortMessage.value = false
  const startedAt = Date.now()
  _thinkingTimer = setInterval(() => {
    thinkingSeconds.value = Math.floor((Date.now() - startedAt) / 1000)
  }, 250)
  _effortTimer = setTimeout(() => {
    showEffortMessage.value = true
    _effortReturnTimer = setTimeout(() => {
      showEffortMessage.value = false
    }, 1800)
  }, 8000)
}

function stopThinkingTimer() {
  if (_thinkingTimer) {
    clearInterval(_thinkingTimer)
    _thinkingTimer = null
  }
  if (_effortTimer) {
    clearTimeout(_effortTimer)
    _effortTimer = null
  }
  if (_effortReturnTimer) {
    clearTimeout(_effortReturnTimer)
    _effortReturnTimer = null
  }
  thinkingSeconds.value = 0
  showEffortMessage.value = false
}

function delay(ms) {
  return new Promise(resolve => {
    _leaveTimers.push(setTimeout(resolve, ms))
  })
}

async function showVisionStep(text, status = 'active', hold = 620) {
  const step = {
    _key: ++_progressKey,
    text,
    status
  }
  currentVisionStep.value = step
  scrollToBottom()
  await delay(hold)
  if (currentVisionStep.value === step) {
    currentVisionStep.value = null
  }
  await delay(260)
}

function clearProgress() {
  _typewriterTimers.forEach(t => clearInterval(t))
  _typewriterTimers = []
  _leaveTimers.forEach(t => clearTimeout(t))
  _leaveTimers = []
  _progressQueue = Promise.resolve()
  progressSteps.value = []
  visibleSteps.value = []
  currentVisionStep.value = null
}

// 将当前 active 步骤标记完成，并在短暂停留后移除（TransitionGroup 负责离场动画）
function _finishCurrentStep(doneText) {
  const active = visibleSteps.value.find(s => s.status === 'active')
  if (!active) return
  active._doneText = doneText || active._fullText || active.text
  active.text = active._doneText
  active.status = 'done'
  // 显示绿勾 0.7s，然后从列表移除触发 TransitionGroup leave 动画
  const step = active
  _leaveTimers.push(setTimeout(() => {
    step.status = 'leaving'
  }, 760))
  _leaveTimers.push(setTimeout(() => {
    const idx = visibleSteps.value.indexOf(step)
    if (idx >= 0) visibleSteps.value.splice(idx, 1)
    scrollToBottom()
  }, 1080))
}

function addProgressStep(text) {
  _finishCurrentStep()  // 先完成上一步

  const step = reactive({
    _key: ++_progressKey,
    _fullText: text,
    _doneText: '',
    text: '',
    status: 'active'
  })
  visibleSteps.value.push(step)
  step.text = text
  scrollToBottom()
  _progressQueue = _progressQueue.then(() => showVisionStep(text, 'active', 720))

  return step
}

function completeProgressStep(text) {
  _finishCurrentStep(text)
}

function autoResize() {
  const el = inputRef.value
  if (!el) return
  el.style.height = 'auto'
  el.style.height = Math.min(el.scrollHeight, 120) + 'px'
}

async function onImagesSelected(e) {
  const files = Array.from(e.target.files || [])
  for (const file of files) {
    if (!file.type.startsWith('image/')) continue
    const dataUrl = await resizeImageForUpload(file)
    pendingImages.value.push(dataUrl)
  }
  e.target.value = ''
}

/**
 * 将图片缩放到适合 API 上传的尺寸（长边 ≤ 2048px，JPEG 质量 0.8）
 * 减少请求体大小，加快上传速度
 */
function resizeImageForUpload(file) {
  return new Promise((resolve) => {
    const img = new Image()
    img.onload = () => {
      const maxDim = 2048
      let w = img.naturalWidth
      let h = img.naturalHeight
      if (w <= maxDim && h <= maxDim) {
        // 无需缩放，直接读取
        const reader = new FileReader()
        reader.onload = () => resolve(reader.result)
        reader.readAsDataURL(file)
        return
      }
      const scale = maxDim / Math.max(w, h)
      w = Math.round(w * scale)
      h = Math.round(h * scale)
      const canvas = document.createElement('canvas')
      canvas.width = w
      canvas.height = h
      const ctx = canvas.getContext('2d')
      ctx.imageSmoothingEnabled = true
      ctx.imageSmoothingQuality = 'high'
      ctx.drawImage(img, 0, 0, w, h)
      resolve(canvas.toDataURL('image/jpeg', 0.8))
    }
    img.src = URL.createObjectURL(file)
  })
}

function removeImage(i) {
  pendingImages.value.splice(i, 1)
}

function closeImagePreview() {
  previewImage.value = null
}

function getFileUrl(url) {
  if (url.startsWith('http')) return url
  return APP_CONFIG.SERVER_BASE + url
}

function openDownloadUrl(url) {
  const fullUrl = getFileUrl(url)
  if (typeof plus !== 'undefined' && plus.runtime?.openURL) {
    plus.runtime.openURL(fullUrl)
    return
  }
  window.open(fullUrl, '_blank')
}

function formatFileSize(bytes) {
  if (bytes < 1024) return bytes + ' B'
  if (bytes < 1024 * 1024) return (bytes / 1024).toFixed(1) + ' KB'
  return (bytes / (1024 * 1024)).toFixed(1) + ' MB'
}

function renderMarkdown(text) {
  return marked.parse(text)
}

function scrollToBottom() {
  nextTick(() => {
    if (chatListRef.value) {
      chatListRef.value.scrollTop = chatListRef.value.scrollHeight
    }
  })
}

/**
 * 将 base64 data URL 转为 Blob，用于 multipart 上传
 */
function dataUrlToBlob(dataUrl) {
  const parts = dataUrl.split(',')
  const mime = parts[0].match(/:(.*?);/)[1]
  const bytes = atob(parts[1])
  const arr = new Uint8Array(bytes.length)
  for (let i = 0; i < bytes.length; i++) {
    arr[i] = bytes.charCodeAt(i)
  }
  return new Blob([arr], { type: mime })
}

/**
 * 上传单张图片到服务器，返回公开 URL
 */
async function uploadImageToServer(dataUrl, signal) {
  const blob = dataUrlToBlob(dataUrl)
  const formData = new FormData()
  formData.append('image', blob, 'image.' + (blob.type === 'image/png' ? 'png' : 'jpg'))

  const resp = await fetch(`${APP_CONFIG.API_BASE}/upload_ai_image.php`, {
    method: 'POST',
    body: formData,
    signal,
  })
  const result = await resp.json()
  if (!result.success) {
    throw new Error(result.message || '图片上传失败')
  }
  return result.url
}

async function sendMessage() {
  const text = inputText.value.trim()
  if (!text && !pendingImages.value.length) return
  if (thinking.value) return

  const signal = getAbortSignal()
  clearProgress()

  const localImages = [...pendingImages.value]
  const userMsg = {
    role: 'user',
    content: text || (localImages.length ? '[图片]' : ''),
    images: localImages
  }
  messages.value.push(userMsg)
  inputText.value = ''
  pendingImages.value = []
  if (inputRef.value) {
    inputRef.value.style.height = 'auto'
  }
  scrollToBottom()
  saveCurrentSession()

  // 先展示思考动画
  thinking.value = true
  startThinkingTimer()
  messages.value.push({ role: 'assistant', content: '' })
  scrollToBottom()

  // 第一步：上传图片到服务器，获取可公开访问的 URL
  let uploadedUrls = []
  if (localImages.length) {
    addProgressStep(localImages.length > 1 ? `上传 ${localImages.length} 张图片` : '上传图片')
    scrollToBottom()
    try {
      for (let i = 0; i < localImages.length; i++) {
        const url = await uploadImageToServer(localImages[i], signal)
        uploadedUrls.push(url)
      }
      completeProgressStep('图片上传完成')
    } catch (e) {
      if (e.name === 'AbortError') return
      completeProgressStep('图片上传失败')
      const assistantMsg = messages.value[messages.value.length - 1]
      assistantMsg.content = '图片上传失败：' + (e.message || '网络错误')
      thinking.value = false
      stopThinkingTimer()
      return
    }
  }

  // 第二步：通过硅基流动视觉模型理解图片内容
  let ocrText = ''
  let ocrFailed = false
  if (localImages.length) {
    addProgressStep(localImages.length > 1 ? `理解 ${localImages.length} 张图片内容` : '理解图片内容')
    scrollToBottom()
    try {
      const ocrResults = []
      for (let i = 0; i < localImages.length; i++) {
        const ocrBody = uploadedUrls[i]
          ? { image_url: uploadedUrls[i] }
          : { image: localImages[i] }
        const resp = await fetch(`${APP_CONFIG.API_BASE}/ocr_siliconflow.php`, {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify(ocrBody),
          signal,
        })
        const result = await resp.json()
        console.log(`[视觉] 图片${i+1}:`, result.success ? '成功' : '失败', result.message || '', result.text ? `(${result.text.length}字)` : '')
        if (result.success && result.text) {
          ocrResults.push(result.text)
        }
      }
      if (ocrResults.length) {
        ocrText = ocrResults.join('\n---\n')
        completeProgressStep(`图片理解完成（${ocrText.length} 字）`)
        console.log('[视觉] 全部完成，总字数:', ocrText.length)
      } else {
        completeProgressStep('图片中未检测到内容')
        ocrFailed = true
        console.warn('[视觉] 所有图片均未能理解')
      }
    } catch (e) {
      if (e.name === 'AbortError') return
      completeProgressStep('图片理解失败')
      ocrFailed = true
      console.error('[视觉] 请求异常:', e)
    }
  }

  // 构建最终消息文本（附图片描述供 AI 理解）
  let finalMessage = text
  if (ocrText) {
    finalMessage = text
      ? text + '\n\n【以下是对用户发送图片的内容描述（由视觉模型生成）】\n' + ocrText
      : '【以下是对用户发送图片的内容描述（由视觉模型生成）】\n' + ocrText
  } else if (ocrFailed && text) {
    finalMessage = text + '\n\n（用户发了一张图片，但暂时无法分析图片内容。如果用户问图片相关的问题，请让用户用文字描述图片内容。）'
  }

  const assistantMsg = messages.value[messages.value.length - 1]

  try {
    const resp = await fetch(`${APP_CONFIG.API_BASE}/ai_chat.php`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        message: finalMessage,
        images: uploadedUrls,
        history: messages.value.slice(0, -1).map(m => ({ role: m.role, content: m.content }))
      }),
      signal,
    })

    if (!resp.ok) {
      assistantMsg.content = '服务器错误：HTTP ' + resp.status
      return
    }

    const reader = resp.body.getReader()
    const decoder = new TextDecoder()
    let buffer = ''

    while (true) {
      const { done, value } = await reader.read()
      if (done) {
        // 处理 stream 结束后 buffer 中残留的数据
        if (buffer.startsWith('data: ')) {
          try {
            const data = JSON.parse(buffer.slice(6))
            if (data.reply !== undefined) {
              assistantMsg.content = data.reply || '(空回复)'
              if (data.files) assistantMsg.files = data.files
              if (data.file_hint) assistantMsg.fileHint = data.file_hint
            }
          } catch {}
        }
        break
      }

      buffer += decoder.decode(value, { stream: true })
      const lines = buffer.split('\n')
      buffer = lines.pop() || ''

      for (const line of lines) {
        if (!line.startsWith('data: ')) continue
        try {
          const data = JSON.parse(line.slice(6))
          if (data.c) {
            assistantMsg.content += data.c
            scrollToBottom()
          } else if (data.reply !== undefined) {
            // 最终事件：替换为清理后的文本 + 文件信息
            assistantMsg.content = data.reply || '(空回复)'
            if (data.files) assistantMsg.files = data.files
            if (data.file_hint) assistantMsg.fileHint = data.file_hint
          } else if (data.error) {
            assistantMsg.content = '错误：' + data.error
          }
        } catch {}
      }
    }
  } catch (e) {
    if (e.name === 'AbortError') return
    if (!assistantMsg.content) {
      assistantMsg.content = '网络错误，请检查网络连接后重试'
    }
  } finally {
    thinking.value = false
    stopThinkingTimer()
    clearProgress()
    scrollToBottom()
    saveCurrentSession()
  }
}
</script>

<style scoped>
.ai-container {
  display: flex;
  flex-direction: column;
  height: 100vh;
  padding-bottom: 90px;
  box-sizing: border-box;
  background: var(--bg-color-light, #f6f8fa);
}

body.dark-theme .ai-container {
  background: #282c34;
}

/* 历史记录面板 */
.history-overlay {
  position: fixed;
  inset: 0;
  z-index: 2000;
  background: rgba(0,0,0,0.4);
  opacity: 0;
  pointer-events: none;
  transition: opacity 0.25s;
  display: flex;
  justify-content: flex-end;
}

.history-overlay.show {
  opacity: 1;
  pointer-events: auto;
}

.history-panel {
  width: 85%;
  max-width: 360px;
  height: 100%;
  background: var(--bg-color-card, #fff);
  display: flex;
  flex-direction: column;
  transform: translateX(100%);
  transition: transform 0.3s cubic-bezier(0.25, 0.1, 0.25, 1);
}

.history-overlay.show .history-panel {
  transform: translateX(0);
}

body.dark-theme .history-panel {
  background: #2c3038;
}

.history-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 16px 18px;
  border-bottom: 1px solid var(--border-color, #eee);
  flex-shrink: 0;
}

.history-header h3 {
  margin: 0;
  font-size: 18px;
  color: var(--text-color-light, #333);
}

body.dark-theme .history-header h3 {
  color: #e0e0e0;
}

.history-close {
  background: none;
  border: none;
  color: var(--text-color-medium, #888);
  cursor: pointer;
  padding: 4px;
}

.new-chat-btn {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 6px;
  margin: 12px 16px;
  padding: 10px;
  border: 1.5px dashed var(--border-color, #ddd);
  border-radius: 12px;
  background: transparent;
  color: #007aff;
  font-size: 15px;
  cursor: pointer;
  transition: background 0.2s;
  flex-shrink: 0;
}

.new-chat-btn:hover {
  background: rgba(0,122,255,0.06);
}

.history-list {
  flex: 1;
  overflow-y: auto;
  padding: 0 12px;
}

.history-item {
  display: flex;
  align-items: center;
  padding: 12px;
  border-radius: 10px;
  cursor: pointer;
  transition: background 0.15s;
  margin-bottom: 4px;
}

.history-item:hover {
  background: var(--bg-color-light, #f5f6f8);
}

body.dark-theme .history-item:hover {
  background: rgba(255,255,255,0.06);
}

.history-item-info {
  flex: 1;
  min-width: 0;
}

.history-item-title {
  font-size: 15px;
  color: var(--text-color-light, #333);
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

body.dark-theme .history-item-title {
  color: #e0e0e0;
}

.history-item-date {
  font-size: 12px;
  color: var(--text-color-medium, #999);
  margin-top: 4px;
}

.history-item-del {
  background: none;
  border: none;
  color: var(--text-color-medium, #ccc);
  cursor: pointer;
  padding: 6px;
  flex-shrink: 0;
  transition: color 0.15s;
}

.history-item-del:hover {
  color: #ff3b30;
}

.history-empty {
  text-align: center;
  color: var(--text-color-medium, #aaa);
  font-size: 14px;
  padding: 40px 0;
}

.ai-chat-main {
  flex: 1;
  overflow-y: auto;
  padding: 80px 14px 20px;
  scroll-behavior: smooth;
}

.ai-empty {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  min-height: 50vh;
  color: var(--text-color-medium, #aaa);
}

.ai-empty-icon {
  opacity: 0.35;
  margin-bottom: 12px;
}

.ai-empty p {
  font-size: 15px;
  margin: 0;
}

.ai-message {
  display: flex;
  flex-direction: column;
  margin-bottom: 18px;
}

.ai-message.user { align-items: flex-end; }
.ai-message.assistant { align-items: flex-start; }

.ai-bubble {
  max-width: 78%;
  padding: 12px 16px;
  border-radius: 18px;
  font-size: 15px;
  line-height: 1.6;
  word-break: break-word;
}

.ai-message.user .ai-bubble {
  background: linear-gradient(135deg, #007aff, #5856d6);
  color: #fff;
  border-bottom-right-radius: 6px;
}

.ai-message.assistant .ai-bubble {
  background: var(--bg-color-card, #fff);
  color: var(--text-color-light, #333);
  border-bottom-left-radius: 6px;
  box-shadow: 0 1px 3px rgba(0,0,0,0.06);
}

body.dark-theme .ai-message.assistant .ai-bubble {
  background: #3a404b;
  color: #e0e0e0;
}

/* Grok 式进度步骤卡片 */
.ai-progress-card {
  padding: 12px 18px;
  background: var(--bg-color-card, #fff);
  border-radius: 18px;
  border-bottom-left-radius: 6px;
  box-shadow: 0 1px 3px rgba(0,0,0,0.06);
  overflow: hidden;
}

body.dark-theme .ai-progress-card {
  background: #3a404b;
}

.ai-progress-step {
  display: flex;
  align-items: center;
  gap: 10px;
  font-size: 14px;
  color: var(--text-color-light, #333);
}

body.dark-theme .ai-progress-step {
  color: #e0e0e0;
}

/* 完成状态：文字颜色稍微变淡，显示绿勾 */
.ai-progress-step.done {
  color: var(--text-color-medium, #888);
}

body.dark-theme .ai-progress-step.done {
  color: #999;
}

/* ===== Vu e TransitionGroup 动画 ===== */

/* 入场：从下方滑入 + 淡入 */
.progress-step-enter-active {
  transition: all 0.35s cubic-bezier(0.25, 0.1, 0.25, 1);
}

.progress-step-enter-from {
  opacity: 0;
  transform: translateY(16px);
}

/* 离场：向上滑出 + 淡出 */
.progress-step-leave-active {
  transition: all 0.3s ease-in;
}

.progress-step-leave-to {
  opacity: 0;
  transform: translateY(-12px);
}

/* ===== 图标 ===== */

.ai-progress-icon {
  flex-shrink: 0;
  width: 20px;
  height: 20px;
  display: flex;
  align-items: center;
  justify-content: center;
}

.ai-progress-icon svg {
  animation: checkPop 0.35s cubic-bezier(0.25, 0.8, 0.25, 1.2);
}

@keyframes checkPop {
  0% { transform: scale(0); opacity: 0; }
  60% { transform: scale(1.3); }
  100% { transform: scale(1); opacity: 1; }
}

/* 进行中的旋转环 */
.ai-progress-ring {
  width: 16px;
  height: 16px;
  border: 2px solid rgba(0, 122, 255, 0.2);
  border-top-color: #007aff;
  border-radius: 50%;
  animation: ringSpin 0.8s linear infinite;
}

@keyframes ringSpin {
  to { transform: rotate(360deg); }
}

.ai-progress-label {
  line-height: 1.4;
}

/* 思考中内联指示器 */
.ai-thinking-inline {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 10px 18px 10px 14px;
  background: var(--bg-color-card, #fff);
  border-radius: 18px;
  border-bottom-left-radius: 6px;
  box-shadow: 0 1px 3px rgba(0,0,0,0.06);
  width: max-content;
  max-width: calc(100vw - 36px);
  flex-wrap: nowrap;
  white-space: nowrap;
  overflow-x: auto;
  scrollbar-width: none;
}

.ai-thinking-inline::-webkit-scrollbar {
  display: none;
}

body.dark-theme .ai-thinking-inline {
  background: #3a404b;
}

/* 彩色四球旋转 loader */
.ai-loader-wrap {
  width: 20px;
  height: 20px;
  flex-shrink: 0;
  overflow: visible;
}

.ai-loader {
  height: 50px;
  width: 50px;
  transform: scale(0.4);
  transform-origin: top left;
}

.ai-loader:before,
.ai-loader:after {
  border-radius: 50%;
  content: '';
  display: block;
  height: 20px;
  width: 20px;
}

.ai-loader:before {
  animation: aiBall1 1s infinite;
  background-color: #cb2025;
  box-shadow: 30px 0 0 #f8b334;
  margin-bottom: 10px;
}

.ai-loader:after {
  animation: aiBall2 1s infinite;
  background-color: #00a096;
  box-shadow: 30px 0 0 #97bf0d;
}

@keyframes aiBall1 {
  0% {
    box-shadow: 30px 0 0 #f8b334;
  }
  50% {
    box-shadow: 0 0 0 #f8b334;
    margin-bottom: 0;
    transform: translate(15px, 15px);
  }
  100% {
    box-shadow: 30px 0 0 #f8b334;
    margin-bottom: 10px;
  }
}

@keyframes aiBall2 {
  0% {
    box-shadow: 30px 0 0 #97bf0d;
  }
  50% {
    box-shadow: 0 0 0 #97bf0d;
    margin-top: -20px;
    transform: translate(15px, 15px);
  }
  100% {
    box-shadow: 30px 0 0 #97bf0d;
    margin-top: 0;
  }
}

/* 思考文字 — 渐变闪烁 */
.ai-thinking-label {
  font-size: 14px;
  font-weight: 500;
  color: transparent;
  background: linear-gradient(90deg, #7c3aed 0%, #a78bfa 40%, #c4b5fd 60%, #7c3aed 80%);
  background-size: 200% 100%;
  -webkit-background-clip: text;
  background-clip: text;
  animation: shimmerText 2.5s ease-in-out infinite;
  white-space: nowrap;
}

body.dark-theme .ai-thinking-label {
  background: linear-gradient(90deg, #a78bfa 0%, #c4b5fd 40%, #ddd6fe 60%, #a78bfa 80%);
  background-size: 200% 100%;
  -webkit-background-clip: text;
  background-clip: text;
}

@keyframes shimmerText {
  0%   { background-position: 200% 0; }
  100% { background-position: -200% 0; }
}

/* 省略号依次跳动 */
.ai-thinking-dots i {
  font-style: normal;
  font-weight: 700;
  animation: dotFadeIn 1.5s infinite both;
}

.ai-thinking-dots i:nth-child(1) { animation-delay: 0s; }
.ai-thinking-dots i:nth-child(2) { animation-delay: 0.3s; }
.ai-thinking-dots i:nth-child(3) { animation-delay: 0.6s; }

@keyframes dotFadeIn {
  0%, 40%   { opacity: 0; }
  60%, 100% { opacity: 1; }
}

.ai-bubble :deep(strong) { font-weight: 700; }

.ai-bubble :deep(code) {
  background: rgba(0,0,0,0.08);
  padding: 2px 6px;
  border-radius: 4px;
  font-size: 13px;
  font-family: 'SF Mono', 'Consolas', monospace;
}

body.dark-theme .ai-bubble :deep(code) { background: rgba(255,255,255,0.1); }

.ai-bubble :deep(pre) {
  background: #f6f8fa;
  padding: 14px;
  border-radius: 8px;
  overflow-x: auto;
  margin: 8px 0;
  line-height: 1.5;
}

.ai-bubble :deep(pre code) {
  background: none;
  padding: 0;
  font-size: 13px;
}

body.dark-theme .ai-bubble :deep(pre) {
  background: #1e2028;
}

/* highlight.js dark mode overrides */
body.dark-theme .ai-bubble :deep(.hljs) { color: #e0e0e0; background: none; }
body.dark-theme .ai-bubble :deep(.hljs-keyword) { color: #c792ea; }
body.dark-theme .ai-bubble :deep(.hljs-string) { color: #c3e88d; }
body.dark-theme .ai-bubble :deep(.hljs-number) { color: #f78c6c; }
body.dark-theme .ai-bubble :deep(.hljs-comment) { color: #676e95; }
body.dark-theme .ai-bubble :deep(.hljs-function) { color: #82aaff; }
body.dark-theme .ai-bubble :deep(.hljs-title) { color: #82aaff; }
body.dark-theme .ai-bubble :deep(.hljs-params) { color: #e0e0e0; }
body.dark-theme .ai-bubble :deep(.hljs-built_in) { color: #c792ea; }
body.dark-theme .ai-bubble :deep(.hljs-type) { color: #ffcb6b; }
body.dark-theme .ai-bubble :deep(.hljs-literal) { color: #f78c6c; }
body.dark-theme .ai-bubble :deep(.hljs-attr) { color: #ffcb6b; }
body.dark-theme .ai-bubble :deep(.hljs-selector-class) { color: #ffcb6b; }
body.dark-theme .ai-bubble :deep(.hljs-meta) { color: #89ddff; }
body.dark-theme .ai-bubble :deep(.hljs-regexp) { color: #c3e88d; }
body.dark-theme .ai-bubble :deep(.hljs-symbol) { color: #c3e88d; }

.ai-message.user .ai-bubble :deep(code) { background: rgba(255,255,255,0.2); }
.ai-message.user .ai-bubble :deep(pre) { background: rgba(0,0,0,0.15); }
.ai-message.user .ai-bubble :deep(.hljs) { color: rgba(255,255,255,0.9); }
.ai-message.user .ai-bubble :deep(.hljs-keyword) { color: #f0a0ff; }
.ai-message.user .ai-bubble :deep(.hljs-string) { color: #a0ffa0; }
.ai-message.user .ai-bubble :deep(.hljs-number) { color: #ffa070; }
.ai-message.user .ai-bubble :deep(.hljs-comment) { color: rgba(255,255,255,0.4); }
.ai-message.user .ai-bubble :deep(.hljs-function) { color: #80c0ff; }
.ai-message.user .ai-bubble :deep(.hljs-title) { color: #80c0ff; }

/* Headings */
.ai-bubble :deep(h1), .ai-bubble :deep(h2), .ai-bubble :deep(h3),
.ai-bubble :deep(h4), .ai-bubble :deep(h5), .ai-bubble :deep(h6) {
  margin: 12px 0 6px;
  line-height: 1.3;
  font-weight: 700;
}
.ai-bubble :deep(h1) { font-size: 1.4em; }
.ai-bubble :deep(h2) { font-size: 1.25em; }
.ai-bubble :deep(h3) { font-size: 1.1em; }
.ai-bubble :deep(h4), .ai-bubble :deep(h5), .ai-bubble :deep(h6) { font-size: 1em; }

/* Paragraphs */
.ai-bubble :deep(p) { margin: 4px 0; }
.ai-bubble :deep(p:first-child) { margin-top: 0; }
.ai-bubble :deep(p:last-child) { margin-bottom: 0; }

/* Lists */
.ai-bubble :deep(ul), .ai-bubble :deep(ol) {
  padding-left: 20px;
  margin: 6px 0;
}
.ai-bubble :deep(li) { margin: 2px 0; }
.ai-bubble :deep(li)::marker { color: inherit; }

/* Blockquote */
.ai-bubble :deep(blockquote) {
  border-left: 3px solid #007aff;
  margin: 8px 0;
  padding: 4px 12px;
  color: #666;
  background: rgba(0,122,255,0.04);
  border-radius: 0 6px 6px 0;
}
body.dark-theme .ai-bubble :deep(blockquote) {
  color: #aaa;
  background: rgba(0,122,255,0.08);
  border-left-color: #5a9eff;
}

/* Links */
.ai-bubble :deep(a) {
  color: #007aff;
  text-decoration: none;
  word-break: break-all;
}
.ai-bubble :deep(a:hover) { text-decoration: underline; }
body.dark-theme .ai-bubble :deep(a) { color: #7bb6ff; }

/* Tables */
.ai-bubble :deep(table) {
  border-collapse: collapse;
  width: 100%;
  margin: 8px 0;
  font-size: 13px;
}
.ai-bubble :deep(th), .ai-bubble :deep(td) {
  border: 1px solid var(--border-color, #ddd);
  padding: 6px 10px;
  text-align: left;
}
.ai-bubble :deep(th) {
  background: rgba(0,0,0,0.04);
  font-weight: 700;
}
body.dark-theme .ai-bubble :deep(th) { background: rgba(255,255,255,0.06); }
body.dark-theme .ai-bubble :deep(th),
body.dark-theme .ai-bubble :deep(td) { border-color: rgba(255,255,255,0.12); }

/* Horizontal rule */
.ai-bubble :deep(hr) {
  border: none;
  border-top: 1px solid var(--border-color, #ddd);
  margin: 12px 0;
}

/* Strikethrough */
.ai-bubble :deep(del) { opacity: 0.6; }

.ai-images {
  display: flex;
  flex-wrap: wrap;
  gap: 6px;
  margin-top: 6px;
}

.ai-message.user .ai-images { justify-content: flex-end; }

.ai-msg-img {
  max-width: 140px;
  max-height: 140px;
  border-radius: 10px;
  object-fit: cover;
  cursor: pointer;
  transition: transform 0.2s;
}

.ai-msg-img:hover { transform: scale(1.05); }

.ai-files {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
  margin-top: 8px;
}

.ai-file-link {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 10px 14px;
  background: var(--bg-color-card, #fff);
  border: 1px solid var(--border-color, #e0e0e0);
  border-radius: 12px;
  color: #007aff;
  text-decoration: none;
  font-size: 14px;
  transition: all 0.2s;
}

.ai-file-link:hover {
  background: rgba(0, 122, 255, 0.06);
  border-color: rgba(0, 122, 255, 0.2);
  transform: translateY(-1px);
  box-shadow: 0 2px 8px rgba(0, 122, 255, 0.1);
}

.ai-file-name {
  font-weight: 500;
}

.ai-file-size {
  color: var(--text-color-medium, #999);
  font-size: 12px;
}

body.dark-theme .ai-file-link {
  background: #3a404b;
  border-color: rgba(255, 255, 255, 0.08);
  color: #7bb6ff;
}

body.dark-theme .ai-file-link:hover {
  background: rgba(123, 182, 255, 0.08);
  border-color: rgba(123, 182, 255, 0.2);
}

.ai-file-hint {
  display: flex;
  align-items: center;
  gap: 6px;
  margin-top: 8px;
  padding: 8px 12px;
  background: #fff3cd;
  border: 1px solid #ffc107;
  border-radius: 8px;
  font-size: 13px;
  color: #856404;
  max-width: 78%;
}

body.dark-theme .ai-file-hint {
  background: rgba(255, 193, 7, 0.1);
  border-color: rgba(255, 193, 7, 0.3);
  color: #ffc107;
}

.ai-preview-overlay {
  position: fixed;
  inset: 0;
  z-index: 2000;
  background: rgba(0,0,0,0.85);
  display: flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
}

.ai-preview-img {
  max-width: 92vw;
  max-height: 92vh;
  border-radius: 12px;
  object-fit: contain;
  cursor: default;
}

.ai-preview-close {
  position: absolute;
  top: 16px;
  right: 16px;
  width: 40px;
  height: 40px;
  border-radius: 50%;
  border: none;
  background: rgba(255,255,255,0.15);
  color: #fff;
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 2001;
  transition: background 0.2s;
  backdrop-filter: blur(4px);
}

.ai-preview-close:hover {
  background: rgba(255,255,255,0.3);
}

.ai-footer {
  background: var(--bg-color-card, #fff);
  border-top: 1px solid var(--border-color, #eee);
  padding: 8px 10px;
  padding-bottom: max(8px, env(safe-area-inset-bottom));
}

body.dark-theme .ai-footer {
  background: #2c3038;
  border-color: rgba(255,255,255,0.08);
}

.ai-thumbnails {
  display: flex;
  gap: 8px;
  padding: 0 4px 8px;
  flex-wrap: wrap;
}

.ai-thumb-wrap {
  position: relative;
  width: 56px;
  height: 56px;
}

.ai-thumb {
  width: 100%;
  height: 100%;
  border-radius: 8px;
  object-fit: cover;
}

.ai-thumb-remove {
  position: absolute;
  top: -6px;
  right: -6px;
  width: 20px;
  height: 20px;
  border-radius: 50%;
  border: none;
  background: rgba(0,0,0,0.6);
  color: #fff;
  font-size: 12px;
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
  line-height: 1;
}

.ai-input-row {
  display: flex;
  align-items: flex-end;
  gap: 8px;
}

.ai-upload-btn {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 40px;
  height: 40px;
  border-radius: 50%;
  cursor: pointer;
  color: var(--text-color-medium, #888);
  transition: all 0.2s;
  flex-shrink: 0;
}

.ai-upload-btn:hover {
  background: rgba(0,122,255,0.08);
  color: #007aff;
}

.ai-input {
  flex: 1;
  border: 1.5px solid var(--border-color, #ddd);
  border-radius: 20px;
  padding: 10px 16px;
  font-size: 15px;
  resize: none;
  max-height: 120px;
  background: var(--bg-color-light, #f9f9f9);
  color: var(--text-color-light, #333);
  outline: none;
  font-family: inherit;
  line-height: 1.4;
}

.ai-input:focus { border-color: #007aff; }

body.dark-theme .ai-input {
  background: #1e2028;
  border-color: rgba(255,255,255,0.1);
  color: #e0e0e0;
}

.ai-send-btn {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 40px;
  height: 40px;
  border-radius: 50%;
  border: none;
  background: linear-gradient(135deg, #007aff, #5856d6);
  color: #fff;
  cursor: pointer;
  transition: all 0.2s;
  flex-shrink: 0;
}

.ai-send-btn:hover:not(:disabled) {
  transform: scale(1.05);
  box-shadow: 0 4px 12px rgba(0,122,255,0.3);
}

.ai-send-btn:active:not(:disabled) { transform: scale(0.95); }
.ai-send-btn:disabled { opacity: 0.4; cursor: not-allowed; }
.ai-vision-progress {
  position: relative;
  display: inline-flex;
  align-items: center;
  width: max-content;
  min-width: max-content;
  height: 20px;
  margin-left: -4px;
  padding-left: 10px;
  overflow: visible;
  vertical-align: middle;
  flex: 0 0 auto;
}

.ai-vision-step {
  position: relative;
  display: inline-flex;
  align-items: center;
  margin-left: -4px;
  color: rgba(99, 102, 122, 0.82);
  font-size: 12px;
  font-weight: 500;
  line-height: 20px;
  white-space: nowrap;
  width: max-content;
  will-change: opacity, transform, filter;
}

body.dark-theme .ai-vision-step {
  color: rgba(216, 220, 230, 0.74);
}

.ai-vision-step.done {
  color: rgba(52, 199, 89, 0.9);
}

.ai-vision-text {
  position: relative;
  display: inline-block;
  overflow: visible;
  text-overflow: clip;
  white-space: nowrap;
  animation: visionTextBreathe 1.8s ease-in-out infinite;
}

.ai-thinking-seconds {
  margin-left: -4px;
  color: rgba(99, 102, 122, 0.72);
  font-size: 12px;
  font-variant-numeric: tabular-nums;
  line-height: 20px;
}

body.dark-theme .ai-thinking-seconds {
  color: rgba(216, 220, 230, 0.68);
}

.ai-thinking-content {
  display: inline-flex;
  align-items: center;
  gap: 12px;
  white-space: nowrap;
}

.ai-thinking-effort {
  display: inline-flex;
  align-items: center;
  min-height: 20px;
  color: rgba(99, 102, 122, 0.9);
  font-size: 14px;
  font-weight: 500;
  white-space: nowrap;
}

body.dark-theme .ai-thinking-effort {
  color: rgba(232, 235, 240, 0.88);
}

.thinking-content-enter-active,
.thinking-content-leave-active {
  transition: opacity 0.28s ease, transform 0.28s ease;
}

.thinking-content-enter-from {
  opacity: 0;
  transform: translateY(4px);
}

.thinking-content-leave-to {
  opacity: 0;
  transform: translateY(-4px);
}

.vision-step-enter-active {
  animation: visionStepIn 0.28s ease-out both;
}

.vision-step-leave-active {
  transition: opacity 0.24s ease, transform 0.24s ease;
}

.vision-step-leave-to {
  opacity: 0;
  transform: translateY(-6px);
}

@keyframes visionStepIn {
  from {
    opacity: 0;
    transform: translateY(5px);
  }
  to {
    opacity: 1;
    transform: translateY(0);
  }
}

@keyframes visionTextBreathe {
  0%, 100% { opacity: 0.62; }
  50% { opacity: 0.92; }
}

</style>
