<template>
  <div class="modal-overlay" @click.self="$emit('close')">
    <div class="modal-sheet">
      <div class="modal-sheet-header">
        <h3>{{ isEditing ? '编辑足迹' : '添加足迹' }}</h3>
        <button class="modal-sheet-close" @click="$emit('close')">✕</button>
      </div>

      <div class="modal-sheet-body">
        <!-- 标题 -->
        <div class="form-group">
          <label>标题 <span class="required">*</span></label>
          <input
            type="text"
            v-model="form.title"
            placeholder="如：杭州西湖一日游"
            maxlength="100"
          />
        </div>

        <!-- 地点名称 -->
        <div class="form-group">
          <label>地点 <span class="required">*</span></label>
          <input
            type="text"
            v-model="form.location_name"
            placeholder="如：浙江省杭州市西湖区"
            maxlength="255"
          />
        </div>

        <!-- 坐标 -->
        <div class="form-group">
          <label>坐标</label>
          <div class="coord-display">
            <span>纬度: {{ form.latitude?.toFixed(4) || '未设置' }}</span>
            <span>经度: {{ form.longitude?.toFixed(4) || '未设置' }}</span>
          </div>
        </div>

        <!-- 省 / 市 -->
        <div style="display: flex; gap: 12px;">
          <div class="form-group" style="flex: 1;">
            <label>省份</label>
            <select v-model="form.province" @change="onProvinceChange">
              <option value="">选择省份</option>
              <option v-for="p in provinceList" :key="p" :value="p">{{ p }}</option>
            </select>
          </div>
          <div class="form-group" style="flex: 1;">
            <label>城市</label>
            <select v-model="form.city" :disabled="!form.province" @change="onCityChange">
              <option value="">选择城市</option>
              <option v-for="c in availableCities" :key="c" :value="c">{{ c }}</option>
            </select>
          </div>
        </div>

        <!-- 日期 -->
        <div class="form-group">
          <label>到访日期</label>
          <input type="date" v-model="form.visited_date" />
        </div>

        <!-- 描述 -->
        <div class="form-group">
          <label>描述</label>
          <textarea
            v-model="form.description"
            placeholder="记录下这里的故事..."
            maxlength="2000"
          ></textarea>
        </div>

        <!-- 图片 -->
        <div class="form-group">
          <label>照片 ({{ images.length }}/9)</label>
          <div class="image-upload-area">
            <div
              v-for="(img, idx) in images"
              :key="idx"
              class="image-upload-item"
            >
              <img :src="imgPreview(img)" style="width:100%;height:100%;object-fit:cover;border-radius:10px;" />
              <button class="remove-btn" @click="removeImage(idx)">×</button>
            </div>
            <div
              v-if="images.length < 9"
              class="image-upload-add"
              @click="triggerFileInput"
            >
              +
            </div>
          </div>
          <input
            ref="fileInput"
            type="file"
            accept="image/*"
            multiple
            style="display:none;"
            @change="handleFileSelect"
          />
        </div>

        <!-- 提示 -->
        <div v-if="form.latitude === null && form.longitude === null" style="font-size:12px;color:#999;text-align:center;margin-bottom:12px;">
          💡 提示：长按地图可以自动获取坐标
        </div>

        <!-- 提交按钮 -->
        <button
          class="submit-btn"
          :disabled="!form.title || !form.location_name || submitting"
          @click="handleSubmit"
        >
          {{ submitting ? '保存中...' : (isEditing ? '保存修改' : '添加足迹') }}
        </button>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, reactive, computed, watch, onMounted } from 'vue'
import { APP_CONFIG, commonFetch } from '../utils/config'
import { customAlert } from '../utils/modal'

const props = defineProps({
  editingFootprint: { type: Object, default: null },
  defaultLat: { type: Number, default: null },
  defaultLng: { type: Number, default: null },
  defaultProvince: { type: String, default: '' },
  defaultCity: { type: String, default: '' },
})

const emit = defineEmits(['saved', 'close'])

const currentUserId = ref(localStorage.getItem('user_id'))
const fileInput = ref(null)
const submitting = ref(false)
const images = ref([])
const cityData = ref({})

const isEditing = computed(() => !!props.editingFootprint)

const form = reactive({
  title: '',
  location_name: '',
  province: '',
  city: '',
  latitude: null,
  longitude: null,
  visited_date: '',
  description: '',
})

// 省份列表
const provinceList = computed(() => Object.keys(cityData.value))

// 可用城市列表
const availableCities = computed(() => {
  if (!form.province || !cityData.value[form.province]) return []
  return Object.keys(cityData.value[form.province].cities)
})

// 省份改变时更新坐标（仅当城市未预设时，即非地图点击触发）
function onProvinceChange() {
  form.city = ''
  if (form.province && cityData.value[form.province]) {
    const center = cityData.value[form.province].center
    form.latitude = center[1]
    form.longitude = center[0]
  }
}

// 城市改变时更新坐标为城市中心
function onCityChange() {
  if (form.province && form.city) {
    const provinceInfo = cityData.value[form.province]
    if (provinceInfo?.cities?.[form.city]) {
      const center = provinceInfo.cities[form.city]
      form.latitude = center[1]
      form.longitude = center[0]
    }
  }
}

// 触发文件选择
function triggerFileInput() {
  fileInput.value?.click()
}

// 处理文件选择
function handleFileSelect(event) {
  const files = Array.from(event.target.files || [])
  const remaining = 9 - images.value.length
  const toAdd = files.slice(0, remaining)

  toAdd.forEach(file => {
    const reader = new FileReader()
    reader.onload = (e) => {
      images.value.push(e.target.result)
    }
    reader.readAsDataURL(file)
  })

  event.target.value = ''
}

// 移除图片
function removeImage(index) {
  images.value.splice(index, 1)
}

// 图片预览
function imgPreview(img) {
  return img
}

// 加载城市数据
async function loadCityData() {
  const cached = localStorage.getItem('china_cities')
  if (cached) {
    try {
      cityData.value = JSON.parse(cached)
      return
    } catch (e) {}
  }
  const urls = [
    `${APP_CONFIG.SERVER_BASE}/api/geojson/china_cities.json`  // 服务器
  ]
  for (const url of urls) {
    try {
      const resp = await fetch(url)
      if (resp.ok) {
        const data = await resp.json()
        cityData.value = data
        try { localStorage.setItem('china_cities', JSON.stringify(data)) } catch (e) {}
        return
      }
    } catch (e) { continue }
  }
  console.error('城市数据加载失败：请确认 public/geojson/ 或服务器上有 china_cities.json')
}

// 初始化表单
function initForm() {
  if (props.editingFootprint) {
    const fp = props.editingFootprint
    form.title = fp.title || ''
    form.location_name = fp.location_name || ''
    form.province = fp.province || ''
    form.city = fp.city || ''
    form.latitude = fp.latitude ? parseFloat(fp.latitude) : null
    form.longitude = fp.longitude ? parseFloat(fp.longitude) : null
    form.visited_date = fp.visited_date || ''
    form.description = fp.description || ''

    // 已存在的图片
    if (fp.images && Array.isArray(fp.images)) {
      images.value = fp.images.map(img => {
        if (img.startsWith('http')) return img
        return `${APP_CONFIG.SERVER_BASE}/${img.replace(/^\//, '')}`
      })
    }
  } else {
    form.title = ''
    form.location_name = ''
    form.province = props.defaultProvince || ''
    form.city = props.defaultCity || ''
    form.latitude = props.defaultLat
    form.longitude = props.defaultLng
    form.visited_date = new Date().toISOString().split('T')[0]
    form.description = ''
    images.value = []
  }
}

// 提交表单
async function handleSubmit() {
  if (!form.title || !form.location_name) return
  submitting.value = true

  try {
    // 先上传新图片（base64 → multipart），已存在的 URL 保持不变
    const imageUrls = []
    for (const img of images.value) {
      if (img.startsWith('data:image/')) {
        // 新图片：通过 multipart 上传
        const blob = dataURItoBlob(img)
        const formData = new FormData()
        formData.append('user_id', currentUserId.value)
        formData.append('image', blob, 'footprint.jpg')
        const uploadResp = await fetch(`${APP_CONFIG.API_BASE}/upload_footprint.php`, {
          method: 'POST',
          body: formData,
        })
        const uploadData = await uploadResp.json()
        if (uploadData.success) {
          imageUrls.push(uploadData.image_url)
        }
      } else {
        // 已有 URL（编辑时）
        imageUrls.push(img.startsWith(APP_CONFIG.SERVER_BASE) ? img.replace(APP_CONFIG.SERVER_BASE + '/', '') : img)
      }
    }

    const payload = {
      user_id: currentUserId.value,
      title: form.title,
      location_name: form.location_name,
      province: form.province,
      city: form.city,
      latitude: form.latitude,
      longitude: form.longitude,
      visited_date: form.visited_date,
      description: form.description,
      images: imageUrls,
    }

    let data
    if (isEditing.value) {
      payload.id = props.editingFootprint.id
      data = await commonFetch(`${APP_CONFIG.API_BASE}/footprints.php`, {
        method: 'PUT',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload),
      })
    } else {
      data = await commonFetch(`${APP_CONFIG.API_BASE}/footprints.php`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload),
      })
    }

    if (data.success) {
      customAlert(isEditing.value ? '修改成功' : '添加成功')
      emit('saved')
    } else {
      customAlert(data.message || '操作失败')
    }
  } catch (e) {
    console.error('提交足迹失败:', e)
    customAlert('提交失败: ' + (e.message || '未知错误'))
  } finally {
    submitting.value = false
  }
}

// base64 data URI → Blob
function dataURItoBlob(dataURI) {
  const byteString = atob(dataURI.split(',')[1])
  const mimeString = dataURI.split(',')[0].split(':')[1].split(';')[0]
  const ab = new ArrayBuffer(byteString.length)
  const ia = new Uint8Array(ab)
  for (let i = 0; i < byteString.length; i++) ia[i] = byteString.charCodeAt(i)
  return new Blob([ab], { type: mimeString })
}

onMounted(async () => {
  await loadCityData()
  initForm()
})
</script>
