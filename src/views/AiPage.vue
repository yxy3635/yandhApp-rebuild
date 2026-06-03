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
          <div class="ai-loader-wrap">
            <div class="ai-loader"></div>
          </div>
          <span class="ai-thinking-label">
            叶鱼思考中
            <span class="ai-thinking-dots">
              <i>.</i><i>.</i><i>.</i>
            </span>
          </span>
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

      <div v-if="ocrProgress" class="ai-message assistant">
        <div class="ai-ocr-bubble">
          <div class="ai-thinking-dots-inline">
            <span class="ai-thinking-dot"></span>
            <span class="ai-thinking-dot"></span>
            <span class="ai-thinking-dot"></span>
          </div>
          <span class="ai-ocr-text">{{ ocrProgress }}</span>
        </div>
      </div>
    </main>

    <div class="ai-preview-overlay" v-if="previewImage" @click="previewImage = null">
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
import { ref, nextTick, onMounted, onBeforeUnmount, watch } from 'vue'
import { APP_CONFIG, commonFetch } from '../utils/config'
import Tesseract from 'tesseract.js'
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
const ocrProgress = ref('')
const previewImage = ref(null)
const chatListRef = ref(null)
const inputRef = ref(null)
const showHistory = ref(false)
const currentSessionId = ref(null)

const sessions = ref([])

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

let _aiViewportTimer = null
function onViewportChange() {
  if (_aiViewportTimer) clearTimeout(_aiViewportTimer)
  _aiViewportTimer = setTimeout(() => scrollToBottom(), 100)
}
function onInputFocus() {
  nextTick(() => { setTimeout(() => scrollToBottom(), 300) })
}

onBeforeUnmount(() => {
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
    messages: [...messages.value]
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

function autoResize() {
  const el = inputRef.value
  if (!el) return
  el.style.height = 'auto'
  el.style.height = Math.min(el.scrollHeight, 120) + 'px'
}

function onImagesSelected(e) {
  const files = Array.from(e.target.files || [])
  for (const file of files) {
    if (!file.type.startsWith('image/')) continue
    resizeImageForUpload(file).then(dataUrl => {
      pendingImages.value.push(dataUrl)
    })
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
 * OCR 图片预处理：Canvas 多阶段管道
 * 降噪 → 放大 → 灰度 → Unsharp Mask → Otsu 二值化 → 暗色检测反色
 */
function preprocessImage(dataUrl) {
  return new Promise((resolve) => {
    const img = new Image()
    img.onload = () => {
      const w = img.naturalWidth
      const h = img.naturalHeight

      // 确保短边至少 1600px，Tesseract 对低清图片几乎无法识别中文
      const minDim = Math.min(w, h)
      const scale = Math.max(1, 1600 / minDim)
      const sw = Math.round(w * scale)
      const sh = Math.round(h * scale)

      const canvas = document.createElement('canvas')
      canvas.width = sw
      canvas.height = sh
      const ctx = canvas.getContext('2d')

      ctx.fillStyle = '#fff'
      ctx.fillRect(0, 0, sw, sh)
      ctx.imageSmoothingEnabled = true
      ctx.imageSmoothingQuality = 'high'
      ctx.drawImage(img, 0, 0, sw, sh)

      const src = ctx.getImageData(0, 0, sw, sh)
      const gray = new Float32Array(sw * sh)

      // 1) 灰度化
      for (let i = 0; i < src.data.length; i += 4) {
        gray[i / 4] = 0.299 * src.data[i] + 0.587 * src.data[i + 1] + 0.114 * src.data[i + 2]
      }

      // 2) 3×3 中值滤波去噪（保护文字笔画不被高斯模糊抹掉）
      const denoised = new Float32Array(sw * sh)
      denoised[0] = gray[0]
      for (let y = 1; y < sh - 1; y++) {
        for (let x = 1; x < sw - 1; x++) {
          const wnd = []
          for (let dy = -1; dy <= 1; dy++)
            for (let dx = -1; dx <= 1; dx++)
              wnd.push(gray[(y + dy) * sw + (x + dx)])
          wnd.sort((a, b) => a - b)
          denoised[y * sw + x] = wnd[4]
        }
      }
      // 边界直接拷贝
      for (let y = 0; y < sh; y++) {
        denoised[y * sw] = gray[y * sw]
        denoised[y * sw + sw - 1] = gray[y * sw + sw - 1]
      }
      for (let x = 0; x < sw; x++) {
        denoised[x] = gray[x]
        denoised[(sh - 1) * sw + x] = gray[(sh - 1) * sw + x]
      }

      // 3) Unsharp Masking：原图 + 强度 × (原图 - 模糊)，文字边缘更锋利
      const sharp = new Float32Array(sw * sh)
      const blurR = 3
      for (let y = 0; y < sh; y++) {
        for (let x = 0; x < sw; x++) {
          let sum = 0, cnt = 0
          for (let dy = -blurR; dy <= blurR; dy++) {
            for (let dx = -blurR; dx <= blurR; dx++) {
              const nx = x + dx, ny = y + dy
              if (nx >= 0 && nx < sw && ny >= 0 && ny < sh) {
                sum += denoised[ny * sw + nx]
                cnt++
              }
            }
          }
          const v = denoised[y * sw + x] + 1.8 * (denoised[y * sw + x] - sum / cnt)
          sharp[y * sw + x] = Math.max(0, Math.min(255, v))
        }
      }

      // 4) Otsu 全局最优阈值
      const hist = new Uint32Array(256)
      const total = sw * sh
      for (let i = 0; i < total; i++) hist[Math.round(sharp[i])]++

      let otsuT = 128, best = 0
      let w0 = 0, sum0 = 0
      const sumAll = (() => { let s = 0; for (let i = 0; i < 256; i++) s += i * hist[i]; return s })()
      for (let t = 0; t < 256; t++) {
        w0 += hist[t]
        sum0 += t * hist[t]
        const w1 = total - w0
        if (w0 === 0 || w1 === 0) continue
        const m0 = sum0 / w0
        const m1 = (sumAll - sum0) / w1
        const vb = w0 * w1 * (m0 - m1) * (m0 - m1)
        if (vb > best) { best = vb; otsuT = t }
      }

      // 5) 二值化 + 暗色模式检测：如果超过 55% 像素在阈值以下是暗色，整体反色
      let darkCount = 0
      for (let i = 0; i < total; i++) {
        if (sharp[i] < otsuT) darkCount++
      }
      const invert = darkCount > total * 0.55

      const dst = ctx.createImageData(sw, sh)
      for (let i = 0, j = 0; i < dst.data.length; i += 4, j++) {
        let bin = sharp[j] < otsuT ? 0 : 255
        if (invert) bin = 255 - bin
        dst.data[i] = bin
        dst.data[i + 1] = bin
        dst.data[i + 2] = bin
        dst.data[i + 3] = 255
      }
      ctx.putImageData(dst, 0, 0)

      resolve(canvas.toDataURL('image/png'))
    }
    img.src = dataUrl
  })
}

/**
 * 对预处理后的图片做双 pass OCR，取更可信的结果
 * Pass A: PSM 3 (全自动)  |  Pass B: PSM 6 (统一文本块)
 * 选择中文字符占比更高的结果
 */
async function ocrWithBestPsm(imgDataUrl) {
  const configs = [
    { psm: '3', label: 'auto' },
    { psm: '6', label: 'block' },
  ]

  let bestText = ''
  let bestScore = -1

  for (const cfg of configs) {
    try {
      const result = await Tesseract.recognize(imgDataUrl, 'chi_sim+eng', {
        tessedit_pageseg_mode: cfg.psm,
      })
      const text = result.data.text.trim()
      if (!text) continue

      // 评分：中文字符 + 常见标点占比越高越好，偏向长文本但惩罚全是乱码的
      const chineseCount = (text.match(/[一-鿿　-〿＀-￯]/g) || []).length
      const junkCount = (text.match(/[^\x20-\x7e一-鿿　-〿＀-￯\s]/g) || []).length
      const score = chineseCount * 2 + text.length - junkCount * 5

      if (score > bestScore) {
        bestScore = score
        bestText = text
      }
    } catch {
      // 单个 pass 失败，试下一个
    }
  }

  // 如果双 pass 都失败，回退到默认 PSM 3
  if (!bestText) {
    try {
      const fallback = await Tesseract.recognize(imgDataUrl, 'chi_sim+eng')
      bestText = fallback.data.text.trim()
    } catch {
      bestText = ''
    }
  }

  return bestText
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
async function uploadImageToServer(dataUrl) {
  const blob = dataUrlToBlob(dataUrl)
  const formData = new FormData()
  formData.append('image', blob, 'image.' + (blob.type === 'image/png' ? 'png' : 'jpg'))

  const resp = await fetch(`${APP_CONFIG.API_BASE}/upload_ai_image.php`, {
    method: 'POST',
    body: formData,
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
  messages.value.push({ role: 'assistant', content: '' })
  scrollToBottom()

  // 第一步：上传图片到服务器，获取可公开访问的 URL
  let uploadedUrls = []
  if (localImages.length) {
    ocrProgress.value = '正在上传图片...'
    scrollToBottom()
    try {
      for (let i = 0; i < localImages.length; i++) {
        ocrProgress.value = `正在上传图片 (${i + 1}/${localImages.length})...`
        const url = await uploadImageToServer(localImages[i])
        uploadedUrls.push(url)
      }
    } catch (e) {
      const assistantMsg = messages.value[messages.value.length - 1]
      assistantMsg.content = '图片上传失败：' + (e.message || '网络错误')
      thinking.value = false
      ocrProgress.value = ''
      return
    }
  }

  // 第二步：本地 OCR 识别图片文字（预处理 + 双 PSM 优选）
  let ocrText = ''
  if (localImages.length) {
    ocrProgress.value = '正在预处理图片...'
    scrollToBottom()
    try {
      const ocrResults = []
      for (let i = 0; i < localImages.length; i++) {
        ocrProgress.value = `正在预处理图片 (${i + 1}/${localImages.length})...`
        const preprocessed = await preprocessImage(localImages[i])

        ocrProgress.value = `正在识别文字 (${i + 1}/${localImages.length})...`
        const words = await ocrWithBestPsm(preprocessed)
        if (words) ocrResults.push(words)
      }
      if (ocrResults.length) {
        ocrText = ocrResults.join('\n---\n')
      }
    } catch {
      // OCR 失败，继续发送消息
    }
    ocrProgress.value = ''
  }

  // 构建最终消息文本
  let finalMessage = text
  if (ocrText) {
    finalMessage = text
      ? text + '\n\n【以下是从图片中识别出的文字内容】\n' + ocrText
      : '【以下是从图片中识别出的文字内容】\n' + ocrText
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
      })
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
  } catch {
    if (!assistantMsg.content) {
      assistantMsg.content = '网络错误，请检查网络连接后重试'
    }
  } finally {
    thinking.value = false
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

/* OCR 进度提示 */
.ai-ocr-bubble {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 10px 16px;
  background: var(--bg-color-card, #fff);
  border-radius: 18px;
  border-bottom-left-radius: 6px;
  box-shadow: 0 1px 3px rgba(0,0,0,0.06);
  font-size: 13px;
  color: var(--text-color-medium, #888);
}

body.dark-theme .ai-ocr-bubble {
  background: #3a404b;
}

.ai-ocr-text {
  font-size: 13px;
  color: var(--text-color-medium, #888);
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

</style>
