<template>
  <header class="app-header">
    <div class="app-header-left">
      <button v-if="showBack" class="app-header-icon-btn" @click="handleBack" aria-label="返回">
        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
          <path d="M15 18L9 12L15 6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
        </svg>
      </button>
      
      <div class="app-header-title-wrap">
        <span v-if="emoji" class="app-header-emoji">{{ emoji }}</span>
        <h1 class="app-header-title">{{ title }}</h1>
      </div>
    </div>
    
    <div class="app-header-right">
      <slot name="actions"></slot>
    </div>
  </header>
</template>

<script setup>
import { useRouter } from 'vue-router';

const props = defineProps({
  title: {
    type: String,
    required: true
  },
  emoji: {
    type: String,
    default: ''
  },
  showBack: {
    type: Boolean,
    default: false
  },
  customBack: {
    type: Function,
    default: null
  }
});

const router = useRouter();

const handleBack = () => {
  if (props.customBack) {
    props.customBack();
  } else {
    router.back();
  }
};
</script>

<style scoped>
.app-header {
  position: fixed;
  top: 0;
  left: 50%;
  transform: translateX(-50%);
  width: 100%;
  max-width: 500px;
  height: 64px;
  /* 磨砂玻璃：复用 AiPage 关闭按钮方案，简单直接 */
  background: rgba(255, 255, 255, 0.72);
  backdrop-filter: blur(24px) saturate(180%);
  -webkit-backdrop-filter: blur(24px) saturate(180%);
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 0 16px;
  z-index: 100;
  box-shadow:
    0 1px 0 rgba(255, 255, 255, 0.6) inset,
    0 4px 24px rgba(0, 0, 0, 0.06),
    0 1px 3px rgba(0, 0, 0, 0.04);
  border-bottom-left-radius: 16px;
  border-bottom-right-radius: 16px;
  border-bottom: 1px solid rgba(0, 0, 0, 0.06);
  color: var(--text-color-light, #333);
  transition: background 0.35s ease, box-shadow 0.35s ease, border-color 0.35s ease;
  box-sizing: border-box;
  animation: headerSlideDown 0.5s cubic-bezier(0.16, 1, 0.3, 1);
}

@keyframes headerSlideDown {
  from {
    opacity: 0;
    transform: translateX(-50%) translateY(-100%);
  }
  to {
    opacity: 1;
    transform: translateX(-50%) translateY(0);
  }
}

body.dark-theme .app-header {
  background: rgba(28, 30, 36, 0.74);
  backdrop-filter: blur(24px) saturate(180%);
  -webkit-backdrop-filter: blur(24px) saturate(180%);
  border-bottom: 1px solid rgba(255, 255, 255, 0.06);
  box-shadow:
    0 1px 0 rgba(255, 255, 255, 0.04) inset,
    0 4px 24px rgba(0, 0, 0, 0.3),
    0 1px 3px rgba(0, 0, 0, 0.2);
}

/* 顶部高光线 */
.app-header::after {
  content: '';
  position: absolute;
  top: 0;
  left: 16px;
  right: 16px;
  height: 1px;
  background: linear-gradient(
    90deg,
    transparent 0%,
    rgba(255, 255, 255, 0.5) 20%,
    rgba(255, 255, 255, 0.7) 50%,
    rgba(255, 255, 255, 0.5) 80%,
    transparent 100%
  );
  border-radius: 1px;
  pointer-events: none;
}

body.dark-theme .app-header::after {
  background: linear-gradient(
    90deg,
    transparent 0%,
    rgba(255, 255, 255, 0.06) 20%,
    rgba(255, 255, 255, 0.1) 50%,
    rgba(255, 255, 255, 0.06) 80%,
    transparent 100%
  );
}

.app-header-left {
  display: flex;
  align-items: center;
  gap: 12px;
  flex: 1;
  min-width: 0;
}

.app-header-icon-btn {
  background: rgba(0, 0, 0, 0.03);
  border: 1px solid rgba(0, 0, 0, 0.04);
  color: inherit;
  display: flex;
  align-items: center;
  justify-content: center;
  width: 36px;
  height: 36px;
  padding: 0;
  margin-left: -4px;
  border-radius: 50%;
  cursor: pointer;
  transition: all 0.2s ease;
  backdrop-filter: blur(8px);
  -webkit-backdrop-filter: blur(8px);
}

.app-header-icon-btn:hover,
.app-header-icon-btn:active {
  background: rgba(0, 0, 0, 0.08);
  transform: scale(1.05);
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.06);
}

body.dark-theme .app-header-icon-btn {
  background: rgba(255, 255, 255, 0.06);
  border-color: rgba(255, 255, 255, 0.06);
}

body.dark-theme .app-header-icon-btn:hover,
body.dark-theme .app-header-icon-btn:active {
  background: rgba(255, 255, 255, 0.12);
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.3);
}

.app-header-title-wrap {
  display: flex;
  align-items: center;
  gap: 8px;
  overflow: hidden;
}

.app-header-emoji {
  font-size: 24px;
  line-height: 1;
  filter: drop-shadow(0 1px 2px rgba(0, 0, 0, 0.1));
  animation: emojiFloat 3s ease-in-out infinite;
}

@keyframes emojiFloat {
  0%, 100% { transform: translateY(0) rotate(0deg); }
  25% { transform: translateY(-2px) rotate(3deg); }
  75% { transform: translateY(1px) rotate(-3deg); }
}

.app-header-title {
  font-size: 20px;
  font-weight: 700;
  margin: 0;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
  letter-spacing: 0.5px;
  background: linear-gradient(135deg, #333 0%, #555 100%);
  -webkit-background-clip: text;
  -webkit-text-fill-color: transparent;
  background-clip: text;
}

body.dark-theme .app-header-title {
  background: linear-gradient(135deg, #e8e8e8 0%, #bbb 100%);
  -webkit-background-clip: text;
  -webkit-text-fill-color: transparent;
  background-clip: text;
}

.app-header-right {
  display: flex;
  align-items: center;
  gap: 10px;
}

:deep(.app-header-btn) {
  background: rgba(0, 122, 255, 0.08);
  color: #007aff;
  border: 1px solid rgba(0, 122, 255, 0.12);
  border-radius: 20px;
  padding: 6px 14px;
  font-size: 14px;
  font-weight: 500;
  cursor: pointer;
  display: flex;
  align-items: center;
  gap: 4px;
  transition: all 0.25s ease;
  backdrop-filter: blur(8px);
  -webkit-backdrop-filter: blur(8px);
}

:deep(.app-header-btn:hover) {
  background: rgba(0, 122, 255, 0.14);
  transform: translateY(-1px);
  box-shadow: 0 4px 12px rgba(0, 122, 255, 0.15);
}

:deep(.app-header-btn:active) {
  transform: scale(0.96);
  background: rgba(0, 122, 255, 0.2);
}

:deep(.app-header-btn.primary) {
  background: linear-gradient(135deg, #007aff, #5856d6);
  color: white;
  border-color: transparent;
  box-shadow: 0 4px 14px rgba(0, 122, 255, 0.35);
}

:deep(.app-header-btn.primary:hover) {
  box-shadow: 0 6px 20px rgba(0, 122, 255, 0.45);
  transform: translateY(-1px);
}

:deep(.app-header-btn.icon-only) {
  background: rgba(0, 0, 0, 0.03);
  border: 1px solid rgba(0, 0, 0, 0.06);
  color: #555;
}

body.dark-theme :deep(.app-header-btn.icon-only) {
  background: rgba(255, 255, 255, 0.06);
  border-color: rgba(255, 255, 255, 0.06);
  color: #ccc;
}

body.dark-theme :deep(.app-header-btn) {
  background: rgba(123, 182, 255, 0.08);
  color: #7bb6ff;
  border-color: rgba(123, 182, 255, 0.12);
}

body.dark-theme :deep(.app-header-btn:hover) {
  background: rgba(123, 182, 255, 0.16);
  box-shadow: 0 4px 12px rgba(123, 182, 255, 0.1);
}
</style>
