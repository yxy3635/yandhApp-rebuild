<template>
  <div class="footprints-container">
    <AppHeader title="足迹" />

    <main class="footprints-main" ref="mainRef">
      <div id="footprints-map" ref="mapRef"></div>

      <button v-if="currentProvince" class="map-control-btn back-btn" @click="resetToNational">
        ← 返回全国
      </button>

      <button class="map-control-btn list-btn" @click="toggleList">
        📋 足迹列表
        <span class="badge" v-if="footprints.length">{{ footprints.length }}</span>
      </button>

      <button class="map-control-btn add-btn" @click="openAddFormAtCenter">+</button>

      <LoadingSpinner v-if="loading" text="加载地图数据..." />
    </main>

    <FootprintList :footprints="footprints" :visible="listVisible" @close="listVisible = false" @select="handleFootprintSelect" />

    <FootprintForm
      v-if="showForm"
      :editing-footprint="editingFootprint"
      :default-lat="formLat" :default-lng="formLng"
      :default-province="formProvince" :default-city="formCity"
      @saved="handleSaved" @close="closeForm"
    />

    <FootprintDetail
      v-if="showDetail"
      :footprint="selectedFootprint"
      @edit="handleEdit" @delete="handleDelete" @close="showDetail = false"
    />
  </div>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted, nextTick } from 'vue'
import { useRouter } from 'vue-router'
import { APP_CONFIG, commonFetch } from '../utils/config'
import { customAlert, customConfirm } from '../utils/modal'
import FootprintList from '../components/FootprintList.vue'
import FootprintForm from '../components/FootprintForm.vue'
import FootprintDetail from '../components/FootprintDetail.vue'
import L from 'leaflet'
import 'leaflet/dist/leaflet.css'
import markerIcon2x from 'leaflet/dist/images/marker-icon-2x.png'
import markerIcon from 'leaflet/dist/images/marker-icon.png'
import markerShadow from 'leaflet/dist/images/marker-shadow.png'

delete L.Icon.Default.prototype._getIconUrl
L.Icon.Default.mergeOptions({ iconRetinaUrl: markerIcon2x, iconUrl: markerIcon, shadowUrl: markerShadow })

const router = useRouter()
const currentUserId = ref(localStorage.getItem('user_id'))
const mainRef = ref(null); const mapRef = ref(null)
const loading = ref(true)
const footprints = ref([])
const visitedProvinces = ref([])
const listVisible = ref(false)
const showForm = ref(false); const showDetail = ref(false)
const currentProvince = ref(null)
const editingFootprint = ref(null); const selectedFootprint = ref(null)
const formLat = ref(null); const formLng = ref(null)
const formProvince = ref(''); const formCity = ref('')

const visitedCities = computed(() => {
  const set = new Set()
  footprints.value.forEach(fp => { if (fp.city) set.add(fp.city) })
  return set
})

let map = null
let geoJsonLayer = null       // 省份 SVG 层
let citiesLayer = null  // 城市 SVG 层
let markersLayer = null
let provinceGeoJson = null
let cityShapesGeoJson = null
let cityData = null

// ── 工具 ──

function isDark() { return document.body.classList.contains('dark-theme') }
function getImageUrl(img) {
  if (!img) return ''
  if (img.startsWith('http')) return img
  return `${APP_CONFIG.SERVER_BASE}/${img.replace(/^\//, '')}`
}

// ── 样式 ──

function getProvinceStyle() {
  return {
    fillColor: isDark() ? '#2a2e38' : '#e8ecf1', weight: 1.5, opacity: 1,
    color: isDark() ? '#444' : '#ccc', fillOpacity: 0.5,
  }
}

// Canvas 层城市样式（纯视觉，透明城市不绘制填充）
function getCityCanvasStyle(feature) {
  const name = feature.properties.city_name || feature.properties.name
  const visited = visitedCities.value.has(name)
  return {
    fillColor: visited ? (isDark() ? '#6699ff' : '#4f8cff') : 'transparent',
    weight: visited ? 1.0 : 0.5,
    opacity: 1,
    color: visited ? (isDark() ? '#88aaff' : '#4f8cff') : (isDark() ? '#444' : '#d8d8d8'),
    fillOpacity: visited ? 0.35 : 0,
  }
}

// SVG 点击层样式（透明填充，仅边框可见，仅已访问城市）
function getVisitedSvgStyle() {
  return {
    fillColor: 'transparent',
    weight: 1.5,
    opacity: 1,
    color: isDark() ? '#88aaff' : '#4f8cff',
    fillOpacity: 0,
  }
}

// ── 数据加载 ──

async function loadJSON(urls, cacheKey) {
  if (cacheKey) {
    const cached = localStorage.getItem(cacheKey)
    if (cached) { try { return JSON.parse(cached) } catch (e) { localStorage.removeItem(cacheKey) } }
  }
  for (const url of urls) {
    try {
      const resp = await fetch(url)
      if (resp.ok) {
        const data = await resp.json()
        if (cacheKey) { try { localStorage.setItem(cacheKey, JSON.stringify(data)) } catch (e) {} }
        return data
      }
    } catch (e) { continue }
  }
  return null
}

async function loadGeoJSON() { return loadJSON(['/geojson/china_provinces.json', `${APP_CONFIG.SERVER_BASE}/api/geojson/china_provinces.json`], 'china_geojson_v2') }
async function loadCityShapes() { return loadJSON(['/geojson/china_cities_shapes.json', `${APP_CONFIG.SERVER_BASE}/api/geojson/china_cities_shapes.json`], null) }
async function loadCityData() { return loadJSON(['/geojson/china_cities.json', `${APP_CONFIG.SERVER_BASE}/api/geojson/china_cities.json`], 'china_cities') || {} }

async function loadFootprints() {
  try {
    const data = await commonFetch(`${APP_CONFIG.API_BASE}/footprints.php?user_id=${currentUserId.value}`)
    if (data.success) footprints.value = data.footprints || []
    const stats = await commonFetch(`${APP_CONFIG.API_BASE}/footprints.php?user_id=${currentUserId.value}&stats=1`)
    if (stats.success && stats.stats) visitedProvinces.value = stats.stats.provinces || []
  } catch (e) { console.error('足迹数据加载失败:', e) }
}

// ── 渲染 ──

function renderProvinces() {
  if (geoJsonLayer) map.removeLayer(geoJsonLayer)
  geoJsonLayer = L.geoJSON(provinceGeoJson, {
    style: getProvinceStyle,
    onEachFeature: (feature, layer) => {
      layer.bindTooltip(feature.properties.name, { permanent: false, direction: 'center', opacity: 0.85 })
      layer.on('click', () => {
        map.fitBounds(layer.getBounds(), { padding: [20, 20], maxZoom: 8 })
        currentProvince.value = feature.properties.name
      })
    },
  }).addTo(map)
}

// Canvas 层：全部城市，纯视觉，无点击，流畅
function renderCityPolygons() {
  if (!cityShapesGeoJson) return
  if (citiesLayer) map.removeLayer(citiesLayer)

  // 单一 SVG 层渲染所有城市
  citiesLayer = L.geoJSON(cityShapesGeoJson, {
    style: getCityCanvasStyle,
    onEachFeature: (feature, layer) => {
      const name = feature.properties.city_name || feature.properties.name
      const isVisited = visitedCities.value.has(name)

      if (isVisited) {
        // 已访问城市：显示 tooltip + 点击事件
        if (map.getZoom() >= 7) {
          layer.bindTooltip(name, { permanent: false, direction: 'center', opacity: 0.85, className: 'city-tooltip' })
        }
        layer.on('click', (e) => {
          L.DomEvent.stopPropagation(e)
          handleVisitedCityClick(name, feature, layer)
        })
        layer.on('mouseover', () => { mapRef.value && (mapRef.value.style.cursor = 'pointer') })
        layer.on('mouseout', () => { mapRef.value && (mapRef.value.style.cursor = '') })
      }
      // 未访问城市：无 tooltip，无事件，纯视觉
    },
  }).addTo(map)
}

function updateCityVisibility() {
  if (!cityShapesGeoJson) return
  if (map.getZoom() >= 6) {
    if (!citiesLayer) renderCityPolygons()
  } else {
    if (citiesLayer) { map.removeLayer(citiesLayer); citiesLayer = null }
  }
}

// 点击已访问城市：放大 + 弹出卡片
function handleVisitedCityClick(cityName, feature, layer) {
  const cityPrints = footprints.value.filter(fp => fp.city === cityName)
  if (cityPrints.length === 0) return

  // 放大到城市边界
  const bounds = layer.getBounds()
  if (bounds.isValid()) {
    map.fitBounds(bounds, { padding: [40, 40], maxZoom: 10 })
  }
  // 动画结束后显示 popup
  const center = feature.properties.center || feature.properties.centroid
  const latlng = center ? [center[1], center[0]] : bounds.getCenter()
  map.once('moveend', () => {
    showCityPopup(cityPrints, latlng)
  })
}

// 地图上弹出城市记录卡片（Leaflet popup）
let activePopup = null
function showCityPopup(cityPrints, latlng) {
  if (activePopup) { map.closePopup(activePopup); activePopup = null }

  let itemsHtml = ''
  cityPrints.forEach((fp, idx) => {
    const imgTag = fp.images && fp.images.length > 0
      ? `<img src="${getImageUrl(fp.images[0])}" class="pp-thumb" />`
      : '<div class="pp-thumb-placeholder">📍</div>'
    itemsHtml += `
      <div class="pp-item" data-fpid="${fp.id}">
        ${imgTag}
        <div class="pp-info">
          <div class="pp-title">${fp.title}</div>
          <div class="pp-meta">${fp.visited_date || ''}</div>
          ${fp.description ? `<div class="pp-desc">${fp.description.substring(0, 60)}${fp.description.length > 60 ? '...' : ''}</div>` : ''}
        </div>
      </div>`
  })

  const html = `
    <div class="city-popup-container">
      <div class="cpp-header">📍 共 ${cityPrints.length} 条记录</div>
      <div class="cpp-list">${itemsHtml}</div>
    </div>`

  activePopup = L.popup({ maxWidth: 300, maxHeight: 320, className: 'city-records-popup' })
    .setLatLng(latlng)
    .setContent(html)
    .openOn(map)

  // popup 中的条目点击 → 打开详情
  setTimeout(() => {
    document.querySelectorAll('.pp-item').forEach(el => {
      el.addEventListener('click', () => {
        const id = parseInt(el.dataset.fpid)
        const fp = cityPrints.find(f => f.id == id)
        if (fp) { selectedFootprint.value = fp; showDetail.value = true }
      })
    })
  }, 100)
}

function findProvinceForCity(cityName) {
  if (!cityData || !cityName) return ''
  for (const [prov, info] of Object.entries(cityData)) {
    if (info.cities && info.cities[cityName]) return prov
  }
  const fp = footprints.value.find(f => f.city === cityName)
  return fp ? fp.province : ''
}

function renderMarkers() {
  markersLayer.clearLayers()
  footprints.value.forEach((fp) => {
    if (!fp.latitude || !fp.longitude) return
    const icon = L.divIcon({ className: 'footprint-marker', iconSize: [12, 12], iconAnchor: [6, 6] })
    L.marker([parseFloat(fp.latitude), parseFloat(fp.longitude)], { icon }).addTo(markersLayer)
  })
}

// ── 交互 ──

function handleMapLongPress(latlng) {
  const { lat, lng } = latlng
  let province = ''
  if (provinceGeoJson?.features) {
    for (const f of provinceGeoJson.features) {
      const c = f.properties.center || f.properties.centroid
      if (c && Math.sqrt((lat - c[1]) ** 2 + (lng - c[0]) ** 2) < 8) { province = f.properties.name; break }
    }
  }
  openAddForm(lat, lng, province, '')
}

function openAddFormAtCenter() {
  const c = map.getCenter()
  openAddForm(c.lat, c.lng, currentProvince.value || '', '')
}

function openAddForm(lat, lng, province, city) {
  formLat.value = lat; formLng.value = lng
  formProvince.value = province; formCity.value = city
  editingFootprint.value = null; showForm.value = true
}

function closeForm() { showForm.value = false; editingFootprint.value = null }

async function handleSaved() {
  showForm.value = false; editingFootprint.value = null
  if (activePopup) { map.closePopup(activePopup); activePopup = null }
  await loadFootprints()
  renderProvinces()
  if (citiesLayer) { map.removeLayer(citiesLayer); citiesLayer = null }  updateCityVisibility()
  renderMarkers()
}

function toggleList() { listVisible.value = !listVisible.value }

function handleFootprintSelect(fp) {
  listVisible.value = false; selectedFootprint.value = fp
  const bounds = getCityBounds(fp.city)
  const coords = getCityCenter(fp.city)
  const target = coords || (fp.latitude && fp.longitude ? [parseFloat(fp.latitude), parseFloat(fp.longitude)] : null)

  if (bounds && bounds.isValid()) {
    map.fitBounds(bounds, { padding: [40, 40], maxZoom: 10 })
  } else if (target) {
    map.setView(target, 10, { animate: true, duration: 0.5 })
  }
}

function getCityBounds(cityName) {
  if (!cityShapesGeoJson || !cityName) return null
  for (const feature of cityShapesGeoJson.features) {
    const name = feature.properties.city_name || feature.properties.name
    if (name === cityName && feature.geometry) return L.geoJSON(feature).getBounds()
  }
  return null
}

function getCityCenter(cityName) {
  if (!cityName || !cityData) return null
  for (const info of Object.values(cityData)) {
    if (info.cities?.[cityName]) { const c = info.cities[cityName]; return [c[1], c[0]] }
  }
  return null
}

function handleEdit(fp) {
  showDetail.value = false; editingFootprint.value = fp
  const coords = getCityCenter(fp.city)
  if (coords) { formLat.value = coords[0]; formLng.value = coords[1] }
  else { formLat.value = parseFloat(fp.latitude); formLng.value = parseFloat(fp.longitude) }
  formProvince.value = fp.province || ''; formCity.value = fp.city || ''
  showForm.value = true
}

async function handleDelete(fp) {
  if (!await customConfirm('确定要删除这条足迹吗？')) return
  try {
    const data = await commonFetch(`${APP_CONFIG.API_BASE}/footprints.php`, {
      method: 'DELETE', headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ id: fp.id, user_id: currentUserId.value }),
    })
    if (data.success) {
      customAlert('删除成功'); showDetail.value = false; selectedFootprint.value = null
      if (activePopup) { map.closePopup(activePopup); activePopup = null }
      await loadFootprints(); renderProvinces()
      if (citiesLayer) { map.removeLayer(citiesLayer); citiesLayer = null }
            updateCityVisibility(); renderMarkers()
    } else { customAlert(data.message || '删除失败') }
  } catch (e) { customAlert('网络错误') }
}

function resetToNational() {
  currentProvince.value = null
  map.setView([35.86, 104.19], 4, { animate: true, duration: 0.5 })
  if (activePopup) { map.closePopup(activePopup); activePopup = null }
  renderMarkers()
  updateCityVisibility()
}

// ── 暗色模式 ──

let themeObserver = null
function setupThemeObserver() {
  themeObserver = new MutationObserver(() => {
    if (geoJsonLayer) geoJsonLayer.setStyle(getProvinceStyle)
    if (citiesLayer) { map.removeLayer(citiesLayer); citiesLayer = null }
        updateCityVisibility()
    renderMarkers()
  })
  themeObserver.observe(document.body, { attributes: true, attributeFilter: ['class'] })
}

// ── 初始化 ──

async function initMap() {
  if (!mapRef.value) return
  map = L.map('footprints-map', {
    center: [35.86, 104.19], zoom: 4, minZoom: 3, maxZoom: 12,
    zoomControl: true, attributionControl: false,
    scrollWheelZoom: false, touchZoom: true, dragging: true,
  })
  L.tileLayer('https://webrd0{s}.is.autonavi.com/appmaptile?lang=zh_cn&size=1&scale=1&style=8&x={x}&y={y}&z={z}', {
    maxZoom: 12, subdomains: '1234', attribution: '&copy; 高德地图',
  }).addTo(map)

  provinceGeoJson = await loadGeoJSON()
  cityShapesGeoJson = await loadCityShapes()
  cityData = await loadCityData()

  if (provinceGeoJson) renderProvinces()
  markersLayer = L.layerGroup().addTo(map)
  renderMarkers()
  updateCityVisibility()

  map.on('zoomend', () => {
    // 缩放变化时重建城市图层
    if (map.getZoom() >= 6) {
      if (citiesLayer) { map.removeLayer(citiesLayer); citiesLayer = null }
      renderCityPolygons()
    } else {
      if (citiesLayer) { map.removeLayer(citiesLayer); citiesLayer = null }
    }
  })

  let pressTimer = null
  map.on('mousedown touchstart', (e) => {
    pressTimer = setTimeout(() => {
      const ll = e.latlng || (e.touches?.[0] && map.mouseEventToLatLng(e.touches[0]))
      if (ll) handleMapLongPress(ll)
    }, 600)
  })
  map.on('mouseup touchend touchcancel', () => clearTimeout(pressTimer))
  map.on('mousemove touchmove', () => clearTimeout(pressTimer))

  loading.value = false
}

onMounted(async () => {
  if (!currentUserId.value) { router.push('/'); return }
  await loadFootprints()
  await nextTick()
  await initMap()
  // 初始加载时若已在城市级缩放，直接渲染
  if (cityShapesGeoJson && map.getZoom() >= 6) renderCityPolygons()
  setupThemeObserver()
  const nav = document.getElementById('bottom-nav')
  if (nav && window.getComputedStyle(nav).display !== 'none') mainRef.value?.classList.add('has-nav')
})

onUnmounted(() => {
  if (map) { map.remove(); map = null }
  if (themeObserver) { themeObserver.disconnect(); themeObserver = null }
  if (activePopup) { map?.closePopup(activePopup); activePopup = null }
})
</script>

<style>
@import '../assets/css/footprints.css';
</style>
