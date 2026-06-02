<template>
  <div class="slide-panel-overlay" :class="{ show: visible }" @click.self="$emit('close')">
    <div class="slide-panel" :class="{ show: visible }">
      <div class="panel-handle"></div>
      <div class="panel-header">
        <span>我的足迹 ({{ footprints.length }})</span>
        <button class="panel-close" @click="$emit('close')">✕</button>
      </div>

      <div class="panel-list">
        <!-- 空状态 -->
        <div v-if="footprints.length === 0" class="empty-state">
          <div class="empty-icon">🗺️</div>
          <div class="empty-text">还没有足迹记录</div>
          <div class="empty-hint">长按地图任意位置添加你的第一个足迹吧！</div>
        </div>

        <!-- 足迹列表 -->
        <div
          v-for="fp in footprints"
          :key="fp.id"
          class="footprint-card"
          @click="$emit('select', fp)"
        >
          <!-- 缩略图 -->
          <img
            v-if="fp.images && fp.images.length > 0"
            :src="getImageUrl(fp.images[0])"
            :alt="fp.title"
            class="card-thumb"
            loading="lazy"
          />
          <div v-else class="card-thumb-placeholder">📍</div>

          <!-- 信息 -->
          <div class="card-info">
            <div class="card-title">{{ fp.title }}</div>
            <div class="card-meta">
              <span>{{ fp.province }}{{ fp.city ? ' · ' + fp.city : '' }}</span>
              <span>{{ fp.visited_date || '' }}</span>
            </div>
          </div>

          <div class="card-arrow">›</div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { APP_CONFIG } from '../utils/config'

defineProps({
  footprints: { type: Array, default: () => [] },
  visible: { type: Boolean, default: false },
})

defineEmits(['close', 'select'])

function getImageUrl(img) {
  if (!img) return ''
  if (img.startsWith('http')) return img
  return `${APP_CONFIG.SERVER_BASE}/${img.replace(/^\//, '')}`
}
</script>
