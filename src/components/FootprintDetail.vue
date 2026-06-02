<template>
  <!-- 图片全屏浏览 -->
  <div class="img-viewer-overlay" v-if="viewerVisible" @click="closeViewer">
    <button class="img-viewer-close" @click="closeViewer">✕</button>
    <button class="img-viewer-prev" v-if="images.length > 1" @click.stop="prevImage">‹</button>
    <img :src="currentImage" class="img-viewer-img" @click.stop />
    <button class="img-viewer-next" v-if="images.length > 1" @click.stop="nextImage">›</button>
    <div class="img-viewer-counter" v-if="images.length > 1">{{ viewerIndex + 1 }} / {{ images.length }}</div>
  </div>

  <!-- 详情模态框 -->
  <div class="modal-overlay" @click.self="$emit('close')">
    <div class="modal-sheet">
      <div class="modal-sheet-header">
        <h3>足迹详情</h3>
        <button class="modal-sheet-close" @click="$emit('close')">✕</button>
      </div>

      <div class="modal-sheet-body" v-if="footprint">
        <!-- 图片画廊 -->
        <div v-if="images.length > 0" class="detail-images">
          <img
            v-for="(img, idx) in images"
            :key="idx"
            :src="img"
            :alt="footprint.title"
            loading="lazy"
            @click="openViewer(idx)"
          />
        </div>

        <!-- 标题 -->
        <div class="detail-field">
          <div class="field-label">标题</div>
          <div class="field-value" style="font-size:18px;font-weight:600;">{{ footprint.title }}</div>
        </div>

        <!-- 地点 -->
        <div class="detail-field">
          <div class="field-label">地点</div>
          <div class="field-value">{{ footprint.location_name }}</div>
        </div>

        <!-- 省/市 -->
        <div v-if="footprint.province || footprint.city" class="detail-field">
          <div class="field-label">所属地区</div>
          <div class="field-value">{{ footprint.province }}{{ footprint.city ? ' · ' + footprint.city : '' }}</div>
        </div>

        <!-- 到访日期 -->
        <div v-if="footprint.visited_date" class="detail-field">
          <div class="field-label">到访日期</div>
          <div class="field-value">{{ footprint.visited_date }}</div>
        </div>

        <!-- 描述 -->
        <div v-if="footprint.description" class="detail-field">
          <div class="field-label">描述</div>
          <div class="field-value">{{ footprint.description }}</div>
        </div>

        <button class="submit-btn" @click="$emit('edit', footprint)" style="margin-top:16px;">编辑</button>
        <button class="delete-btn" @click="$emit('delete', footprint)">删除此足迹</button>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed } from 'vue'
import { APP_CONFIG } from '../utils/config'

const props = defineProps({
  footprint: { type: Object, default: null },
})

defineEmits(['close', 'edit', 'delete'])

// 图片列表
const images = computed(() => {
  if (!props.footprint?.images || !props.footprint.images.length) return []
  return props.footprint.images.map(img => getImageUrl(img))
})

// 全屏图片浏览器
const viewerVisible = ref(false)
const viewerIndex = ref(0)
const currentImage = computed(() => images.value[viewerIndex.value] || '')

function openViewer(idx) {
  viewerIndex.value = idx
  viewerVisible.value = true
}
function closeViewer() { viewerVisible.value = false }
function prevImage() { viewerIndex.value = (viewerIndex.value - 1 + images.value.length) % images.value.length }
function nextImage() { viewerIndex.value = (viewerIndex.value + 1) % images.value.length }

function getImageUrl(img) {
  if (!img) return ''
  if (img.startsWith('http')) return img
  return `${APP_CONFIG.SERVER_BASE}/${img.replace(/^\//, '')}`
}
</script>
