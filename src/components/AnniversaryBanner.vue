<template>
  <Transition name="banner-slide">
    <div v-if="visible" class="anniv-banner" @click.stop>
      <div class="anniv-banner-icon">
        <div class="anniv-hamster-wrap">
          <div class="anniv-hamster">
            <div class="anniv-hamster__body">
              <div class="anniv-hamster__head">
                <div class="anniv-hamster__ear"></div>
                <div class="anniv-hamster__eye"></div>
                <div class="anniv-hamster__nose"></div>
              </div>
              <div class="anniv-hamster__limb anniv-hamster__limb--fr"></div>
              <div class="anniv-hamster__limb anniv-hamster__limb--fl"></div>
              <div class="anniv-hamster__limb anniv-hamster__limb--br"></div>
              <div class="anniv-hamster__limb anniv-hamster__limb--bl"></div>
              <div class="anniv-hamster__tail"></div>
            </div>
          </div>
          <div class="anniv-hamster-wheel"></div>
          <div class="anniv-hamster-spoke"></div>
        </div>
      </div>
      <div class="anniv-banner-body">
        <div class="anniv-banner-title">
          <span class="anniv-banner-name">{{ item.name }}</span>
          <span class="anniv-banner-countdown">{{ daysText }}</span>
        </div>
        <div class="anniv-banner-desc">{{ description }}</div>
      </div>
      <button class="anniv-banner-close" @click="dismiss" title="关闭">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round">
          <line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/>
        </svg>
      </button>
      <span class="anniv-deco-dot"></span>
      <span class="anniv-deco-dot"></span>
      <span class="anniv-deco-dot"></span>
    </div>
  </Transition>
</template>

<script setup>
import { ref, onMounted, computed } from 'vue'
import { APP_CONFIG } from '../utils/config'
import { lunarCalendar } from '../utils/lunar-calendar'

const DAY_MS = 1000 * 60 * 60 * 24

const visible = ref(false)
const item = ref({})
const description = ref('')
const daysText = ref('')

// ===== 从 Anniversary.vue 复用的工具函数 =====

function isRepeatYearlyItem(it) {
  const raw = it.repeat_yearly
  if (raw === true || raw === 1 || raw === '1') return true
  const title = String(it.title || it.name || '').trim()
  return /生日|诞辰/.test(title)
}

function getNextSolarOccurrenceDate(dateStr) {
  if (!dateStr) return null
  const today = new Date(); today.setHours(0, 0, 0, 0)
  const parsed = new Date(dateStr)
  if (Number.isNaN(parsed.getTime())) return null
  let next = new Date(today.getFullYear(), parsed.getMonth(), parsed.getDate())
  next.setHours(0, 0, 0, 0)
  if (next < today) {
    next = new Date(today.getFullYear() + 1, parsed.getMonth(), parsed.getDate())
    next.setHours(0, 0, 0, 0)
  }
  return next
}

function getNextLunarOccurrenceDate(lunarMonth, lunarDay, lunarLeap) {
  const today = new Date(); today.setHours(0, 0, 0, 0)
  const currentYear = today.getFullYear()
  const thisYearSolar = lunarCalendar.lunar2solar(currentYear, lunarMonth, lunarDay, lunarLeap)
  if (!thisYearSolar) {
    const nextTry = lunarCalendar.lunar2solar(currentYear + 1, lunarMonth, lunarDay, lunarLeap)
    if (!nextTry) return null
    return new Date(nextTry.cYear, nextTry.cMonth - 1, nextTry.cDay)
  }
  let target = new Date(thisYearSolar.cYear, thisYearSolar.cMonth - 1, thisYearSolar.cDay)
  target.setHours(0, 0, 0, 0)
  if (target < today) {
    const nextYearSolar = lunarCalendar.lunar2solar(currentYear + 1, lunarMonth, lunarDay, lunarLeap)
    if (!nextYearSolar) return null
    target = new Date(nextYearSolar.cYear, nextYearSolar.cMonth - 1, nextYearSolar.cDay)
  }
  return target
}

function formatItemDateLabel(it) {
  if (it.is_lunar && it.lunar_year != null && it.lunar_month != null && it.lunar_day != null) {
    const leap = it.lunar_leap ? '闰' : ''
    return `农历${it.lunar_year}年${leap}${it.lunar_month}月${it.lunar_day}日`
  }
  return `公历 ${it.date || ''}`
}

function computeDaysLeft(it) {
  const today = new Date(); today.setHours(0, 0, 0, 0)
  const repeat = isRepeatYearlyItem(it)

  let target = null

  if (it.is_lunar && it.lunar_month != null && it.lunar_day != null) {
    if (repeat) {
      target = getNextLunarOccurrenceDate(it.lunar_month, it.lunar_day, !!it.lunar_leap)
    } else {
      const solar = lunarCalendar.lunar2solar(today.getFullYear(), it.lunar_month, it.lunar_day, !!it.lunar_leap)
      if (solar) {
        target = new Date(solar.cYear, solar.cMonth - 1, solar.cDay)
        target.setHours(0, 0, 0, 0)
      }
    }
  } else {
    if (repeat) {
      target = getNextSolarOccurrenceDate(it.date)
    } else {
      if (it.date) {
        const parsed = new Date(it.date)
        if (!Number.isNaN(parsed.getTime())) {
          target = new Date(today.getFullYear(), parsed.getMonth(), parsed.getDate())
          target.setHours(0, 0, 0, 0)
          if (target < today) target = null
        }
      }
    }
  }

  if (!target) return { days_left: Infinity, is_today: false }

  if (target.getTime() === today.getTime()) return { days_left: 0, is_today: true }
  return { days_left: Math.max(0, Math.ceil((target - today) / DAY_MS)), is_today: false }
}

// ===== 主逻辑 =====

const DISMISS_KEY = 'anniv_banner_dismissed'

onMounted(async () => {
  const userId = localStorage.getItem('user_id')
  if (!userId) return

  // 获取纪念日列表
  let anniversaries = []
  try {
    const resp = await fetch(`${APP_CONFIG.API_BASE}/anniversary.php?user_id=${userId}`)
    const data = await resp.json()
    if (data.success && data.data) {
      anniversaries = data.data.map(it => ({
        ...it,
        name: it.title || it.name,
        dateLabel: formatItemDateLabel(it)
      }))
    }
  } catch {
    return
  }

  if (!anniversaries.length) return

  // 找到最近一个快到的（30天内的 upcoming 或 today）
  const upcoming = []
  for (const it of anniversaries) {
    const { days_left, is_today } = computeDaysLeft(it)
    if (is_today || (days_left <= 30)) {
      upcoming.push({ ...it, days_left, is_today })
    }
  }
  if (!upcoming.length) return

  upcoming.sort((a, b) => a.days_left - b.days_left)
  const nearest = upcoming[0]

  // 检查是否今天已关闭（关闭有效期仅当天）
  const todayForKey = new Date()
  const dateKey = `${todayForKey.getFullYear()}-${todayForKey.getMonth() + 1}-${todayForKey.getDate()}`
  const dismissId = `${nearest.id || nearest.name}_${dateKey}`
  if (localStorage.getItem(`${DISMISS_KEY}_${dismissId}`)) return

  // 设置纪念日信息
  item.value = nearest
  if (nearest.is_today) {
    daysText.value = '就在今天!'
  } else if (nearest.days_left === 0) {
    daysText.value = '就是今天!'
  } else {
    daysText.value = `还有 ${nearest.days_left} 天`
  }

  // 请求 AI 描述（每次进入都重新生成）
  description.value = '正在生成描述...'
  visible.value = true

  try {
    const today = new Date()
    const todayStr = `${today.getFullYear()}年${today.getMonth() + 1}月${today.getDate()}日`
    const prompt = `今天是${todayStr}，距离「${nearest.name}」（${nearest.dateLabel}）${nearest.is_today ? '就是今天' : '还有' + nearest.days_left + '天'}。请用一句温馨优美的话（20字左右）来祝福或描述这个即将到来的纪念日，语气亲切自然，不要用markdown格式，直接输出纯文字。`

    const aiResp = await fetch(`${APP_CONFIG.API_BASE}/ai_chat.php`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ message: prompt, stream: false })
    })
    const aiData = await aiResp.json()
    if (aiData.success && aiData.reply) {
      const text = aiData.reply.trim()
      description.value = text
    } else {
      description.value = '又一个值得纪念的日子即将到来~'
    }
  } catch {
    description.value = '又一个值得纪念的日子即将到来~'
  }
})

function dismiss() {
  visible.value = false
  const today = new Date()
  const dateKey = `${today.getFullYear()}-${today.getMonth() + 1}-${today.getDate()}`
  const dismissId = `${item.value.id || item.value.name}_${dateKey}`
  localStorage.setItem(`${DISMISS_KEY}_${dismissId}`, '1')
}
</script>

<style scoped>
/* ===== 横幅容器 ===== */
.anniv-banner {
  display: flex;
  align-items: center;
  gap: 12px;
  margin: 8px 14px 0;
  padding: 12px 14px;
  border-radius: 16px;
  position: relative;
  overflow: hidden;
  background: linear-gradient(135deg, #fef9f4 0%, #fff5ee 40%, #fef9f4 100%);
  border: 1px solid rgba(255, 160, 120, 0.18);
  box-shadow:
    0 2px 16px rgba(180, 120, 80, 0.08),
    0 0 0 1px rgba(255, 180, 140, 0.06) inset;
}

/* 顶部温馨装饰线 */
.anniv-banner::before {
  content: '';
  position: absolute;
  top: 0;
  left: 20%;
  width: 60%;
  height: 1.5px;
  background: linear-gradient(90deg,
    transparent, rgba(255, 160, 130, 0.5) 20%, rgba(255, 140, 100, 0.5) 50%, rgba(255, 160, 130, 0.5) 80%, transparent);
  border-radius: 0 0 50% 50%;
  animation: warmLine 3s ease-in-out infinite;
  pointer-events: none;
  z-index: 1;
}

/* 底部装饰虚线 */
.anniv-banner::after {
  content: '';
  position: absolute;
  bottom: 6px;
  right: 50px;
  width: 60px;
  height: 1px;
  background: linear-gradient(90deg,
    transparent,
    rgba(255, 180, 150, 0.4) 15%,
    rgba(255, 180, 150, 0) 30%,
    rgba(255, 180, 150, 0.4) 45%,
    rgba(255, 180, 150, 0) 60%,
    rgba(255, 180, 150, 0.4) 75%,
    transparent);
  animation: warmDots 4s ease-in-out infinite;
  pointer-events: none;
  z-index: 1;
}

.anniv-deco-dot {
  position: absolute;
  width: 3px;
  height: 3px;
  border-radius: 50%;
  background: rgba(255, 160, 130, 0.5);
  pointer-events: none;
  z-index: 1;
}

.anniv-deco-dot:nth-child(4) {
  bottom: 8px; left: 15%;
  animation: floatDot 3.5s ease-in-out infinite;
}
.anniv-deco-dot:nth-child(5) {
  bottom: 5px; left: 45%;
  animation: floatDot 3.5s ease-in-out 1.2s infinite;
}
.anniv-deco-dot:nth-child(6) {
  bottom: 10px; left: 75%;
  animation: floatDot 3.5s ease-in-out 2.4s infinite;
}

body.dark-theme .anniv-banner {
  background: linear-gradient(135deg, #2a2420 0%, #25201c 40%, #2a2420 100%);
  border: 1px solid rgba(255, 160, 120, 0.12);
  box-shadow:
    0 2px 16px rgba(0, 0, 0, 0.3),
    0 0 0 1px rgba(255, 180, 140, 0.04) inset;
}

/* ===== 迷你仓鼠跑轮（温暖色调） ===== */
.anniv-banner-icon {
  flex-shrink: 0;
  z-index: 1;
}

.anniv-hamster-wrap {
  --dur: 0.7s;
  position: relative;
  width: 36px;
  height: 36px;
  font-size: 3.6px;
}

.anniv-hamster-wheel,
.anniv-hamster,
.anniv-hamster div,
.anniv-hamster-spoke {
  position: absolute;
}

.anniv-hamster-wheel,
.anniv-hamster-spoke {
  border-radius: 50%;
  top: 0;
  left: 0;
  width: 100%;
  height: 100%;
}

.anniv-hamster-wheel {
  background: radial-gradient(100% 100% at center, hsla(25, 50%, 65%, 0) 47.8%, hsla(25, 45%, 60%, 0.4) 48%);
  z-index: 2;
}

.anniv-hamster {
  animation: annivHamster var(--dur) ease-in-out infinite;
  top: 50%;
  left: calc(50% - 3.5em);
  width: 7em;
  height: 3.75em;
  transform: rotate(4deg) translate(-0.8em, 1.85em);
  transform-origin: 50% 0;
  z-index: 1;
}

.anniv-hamster__head {
  animation: annivHamsterHead var(--dur) ease-in-out infinite;
  background: hsl(28, 55%, 58%);
  border-radius: 70% 30% 0 100% / 40% 25% 25% 60%;
  box-shadow: 0 -0.25em 0 hsl(28, 50%, 72%) inset,
    0.75em -1.55em 0 hsl(28, 50%, 78%) inset;
  top: 0;
  left: -2em;
  width: 2.75em;
  height: 2.5em;
  transform-origin: 100% 50%;
}

.anniv-hamster__ear {
  animation: annivHamsterEar var(--dur) ease-in-out infinite;
  background: hsl(28, 48%, 68%);
  border-radius: 50%;
  box-shadow: -0.25em 0 hsl(28, 55%, 60%) inset;
  top: -0.25em;
  right: -0.25em;
  width: 0.75em;
  height: 0.75em;
  transform-origin: 50% 75%;
}

.anniv-hamster__eye {
  animation: annivHamsterEye var(--dur) linear infinite;
  background-color: hsl(0, 0%, 12%);
  border-radius: 50%;
  top: 0.375em;
  left: 1.25em;
  width: 0.5em;
  height: 0.5em;
}

.anniv-hamster__nose {
  background: hsl(20, 40%, 52%);
  border-radius: 35% 65% 85% 15% / 70% 50% 50% 30%;
  top: 0.75em;
  left: 0;
  width: 0.2em;
  height: 0.25em;
}

.anniv-hamster__body {
  animation: annivHamsterBody var(--dur) ease-in-out infinite;
  background: hsl(28, 55%, 72%);
  border-radius: 50% 30% 50% 30% / 15% 60% 40% 40%;
  box-shadow: 0.1em 0.75em 0 hsl(28, 55%, 58%) inset,
    0.15em -0.5em 0 hsl(28, 55%, 68%) inset;
  top: 0.25em;
  left: 2em;
  width: 4.5em;
  height: 3em;
  transform-origin: 17% 50%;
}

.anniv-hamster__limb--fr,
.anniv-hamster__limb--fl {
  clip-path: polygon(0 0, 100% 0, 70% 80%, 60% 100%, 0% 100%, 40% 80%);
  top: 2em;
  left: 0.5em;
  width: 1em;
  height: 1.5em;
  transform-origin: 50% 0;
}

.anniv-hamster__limb--fr {
  animation: annivHamsterFRLimb var(--dur) linear infinite;
  background: linear-gradient(hsl(28, 55%, 68%) 80%, hsl(28, 50%, 55%) 80%);
  transform: rotate(15deg);
}

.anniv-hamster__limb--fl {
  animation: annivHamsterFLLimb var(--dur) linear infinite;
  background: linear-gradient(hsl(28, 55%, 78%) 80%, hsl(28, 50%, 62%) 80%);
  transform: rotate(15deg);
}

.anniv-hamster__limb--br,
.anniv-hamster__limb--bl {
  border-radius: 0.75em 0.75em 0 0;
  clip-path: polygon(0 0, 100% 0, 100% 30%, 70% 90%, 70% 100%, 30% 100%, 40% 90%, 0% 30%);
  top: 1em;
  left: 2.8em;
  width: 1.5em;
  height: 2.5em;
  transform-origin: 50% 30%;
}

.anniv-hamster__limb--br {
  animation: annivHamsterBRLimb var(--dur) linear infinite;
  background: linear-gradient(hsl(28, 55%, 68%) 90%, hsl(28, 50%, 55%) 90%);
  transform: rotate(-25deg);
}

.anniv-hamster__limb--bl {
  animation: annivHamsterBLLimb var(--dur) linear infinite;
  background: linear-gradient(hsl(28, 55%, 78%) 90%, hsl(28, 50%, 62%) 90%);
  transform: rotate(-25deg);
}

.anniv-hamster__tail {
  animation: annivHamsterTail var(--dur) linear infinite;
  background: hsl(28, 48%, 68%);
  border-radius: 0.25em 50% 50% 0.25em;
  box-shadow: 0 -0.2em 0 hsl(28, 50%, 58%) inset;
  top: 1.5em;
  right: -0.5em;
  width: 1em;
  height: 0.5em;
  transform: rotate(30deg);
  transform-origin: 0.25em 0.25em;
}

.anniv-hamster-spoke {
  animation: annivSpoke var(--dur) linear infinite;
  background: radial-gradient(100% 100% at center, hsla(25, 45%, 62%, 0.5) 4.8%, hsla(25, 45%, 62%, 0) 5%),
    linear-gradient(hsla(25, 40%, 60%, 0) 46.9%, hsla(25, 40%, 64%, 0.35) 47% 52.9%, hsla(25, 40%, 60%, 0) 53%) 50% 50% / 99% 99% no-repeat;
}

/* ===== 文字区 ===== */
.anniv-banner-body {
  flex: 1;
  min-width: 0;
  z-index: 1;
}

.anniv-banner-title {
  display: flex;
  align-items: center;
  gap: 8px;
  margin-bottom: 4px;
}

.anniv-banner-name {
  font-size: 14px;
  font-weight: 700;
  color: #5c3d2e;
  letter-spacing: 0.3px;
}

.anniv-banner-countdown {
  font-size: 11px;
  font-weight: 600;
  color: #c0784a;
  background: linear-gradient(135deg, rgba(255, 180, 140, 0.2), rgba(255, 160, 120, 0.15));
  padding: 3px 10px;
  border-radius: 20px;
  border: 1px solid rgba(255, 160, 120, 0.25);
  animation: badgeBreathe 3s ease-in-out infinite;
}

.anniv-banner-desc {
  font-size: 13px;
  color: #9b7b6c;
  line-height: 1.5;
}

body.dark-theme .anniv-banner-name {
  color: #e8d5c8;
}

body.dark-theme .anniv-banner-countdown {
  color: #e0a880;
  background: linear-gradient(135deg, rgba(255, 180, 140, 0.12), rgba(255, 160, 120, 0.08));
  border: 1px solid rgba(255, 160, 120, 0.15);
}

body.dark-theme .anniv-banner-desc {
  color: #b8a090;
}

/* ===== 关闭按钮 ===== */
.anniv-banner-close {
  flex-shrink: 0;
  background: rgba(180, 120, 80, 0.08);
  border: none;
  color: rgba(160, 100, 60, 0.5);
  cursor: pointer;
  width: 26px;
  height: 26px;
  border-radius: 50%;
  transition: all 0.2s;
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 1;
}

.anniv-banner-close:hover {
  color: #8b5e3c;
  background: rgba(180, 120, 80, 0.16);
  transform: scale(1.1);
}

body.dark-theme .anniv-banner-close {
  background: rgba(255, 200, 160, 0.06);
  color: rgba(200, 150, 120, 0.4);
}

body.dark-theme .anniv-banner-close:hover {
  color: #d4a88c;
  background: rgba(255, 200, 160, 0.12);
}

/* ===== 入场/离场动画 ===== */
.banner-slide-enter-active {
  animation: bannerSlideIn 0.5s cubic-bezier(0.23, 1.02, 0.53, 0.97);
}

.banner-slide-leave-active {
  animation: bannerSlideOut 0.35s ease-in forwards;
}

/* ===== Keyframes ===== */
@keyframes warmLine {
  0%, 100% { opacity: 0.4; transform: translateX(0); }
  50%      { opacity: 0.8; transform: translateX(2px); }
}

@keyframes warmDots {
  0%, 100% { opacity: 0.3; }
  50%      { opacity: 0.7; }
}

@keyframes floatDot {
  0%, 100% { transform: translateY(0); opacity: 0.3; }
  50%      { transform: translateY(-4px); opacity: 0.7; }
}

@keyframes badgeBreathe {
  0%, 100% { box-shadow: 0 0 0 rgba(255, 160, 120, 0); }
  50%      { box-shadow: 0 0 8px rgba(255, 160, 120, 0.2); }
}

@keyframes bannerSlideIn {
  from {
    opacity: 0;
    transform: translateY(-100%);
    max-height: 0;
    margin-top: 0;
  }
  to {
    opacity: 1;
    transform: translateY(0);
    max-height: 100px;
  }
}

@keyframes bannerSlideOut {
  to {
    opacity: 0;
    transform: translateY(-40%);
    max-height: 0;
    margin-top: 0;
  }
}

/* ===== 仓鼠动画（暖调配色） ===== */
@keyframes annivHamster {
  from, to { transform: rotate(4deg) translate(-0.8em, 1.85em); }
  50%      { transform: rotate(0) translate(-0.8em, 1.85em); }
}

@keyframes annivHamsterHead {
  from, 25%, 50%, 75%, to { transform: rotate(0); }
  12.5%, 37.5%, 62.5%, 87.5% { transform: rotate(8deg); }
}

@keyframes annivHamsterEye {
  from, 90%, to { transform: scaleY(1); }
  95%           { transform: scaleY(0); }
}

@keyframes annivHamsterEar {
  from, 25%, 50%, 75%, to { transform: rotate(0); }
  12.5%, 37.5%, 62.5%, 87.5% { transform: rotate(12deg); }
}

@keyframes annivHamsterBody {
  from, 25%, 50%, 75%, to { transform: rotate(0); }
  12.5%, 37.5%, 62.5%, 87.5% { transform: rotate(-2deg); }
}

@keyframes annivHamsterFRLimb {
  from, 25%, 50%, 75%, to { transform: rotate(50deg); }
  12.5%, 37.5%, 62.5%, 87.5% { transform: rotate(-30deg); }
}

@keyframes annivHamsterFLLimb {
  from, 25%, 50%, 75%, to { transform: rotate(-30deg); }
  12.5%, 37.5%, 62.5%, 87.5% { transform: rotate(50deg); }
}

@keyframes annivHamsterBRLimb {
  from, 25%, 50%, 75%, to { transform: rotate(-60deg); }
  12.5%, 37.5%, 62.5%, 87.5% { transform: rotate(20deg); }
}

@keyframes annivHamsterBLLimb {
  from, 25%, 50%, 75%, to { transform: rotate(20deg); }
  12.5%, 37.5%, 62.5%, 87.5% { transform: rotate(-60deg); }
}

@keyframes annivHamsterTail {
  from, 25%, 50%, 75%, to { transform: rotate(30deg); }
  12.5%, 37.5%, 62.5%, 87.5% { transform: rotate(10deg); }
}

@keyframes annivSpoke {
  from { transform: rotate(0); }
  to   { transform: rotate(-1turn); }
}
</style>
