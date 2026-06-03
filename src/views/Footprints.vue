<template>
  <div class="footprints-container">
    <AppHeader title="足迹" />

    <main class="footprints-main" ref="mainRef">
      <div id="footprints-map" ref="mapRef"></div>

      <button v-if="currentProvince" class="map-control-btn back-btn" @click="resetToNational">← 返回全国</button>

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
  const s = new Set()
  footprints.value.forEach(fp => { if (fp.city) s.add(fp.city) })
  return s
})

let map = null
let geoJsonLayer = null
let citiesLayer = null
let suppressAutoPopups = false  // 点击蓝色城市时抑制自动弹出框
let markersLayer = null
let provinceGeoJson = null
let cityShapesGeoJson = null
let cityData = null
let activePopup = null

// ── 工具 ──

function isDark() { return document.body.classList.contains('dark-theme') }
function getImageUrl(img) {
  if (!img) return ''
  if (img.startsWith('http')) return img
  return `${APP_CONFIG.SERVER_BASE}/${img.replace(/^\//, '')}`
}

// ── 数据 ──

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

function makeUrls(filename) {
  const u = [`${APP_CONFIG.SERVER_BASE}/api/geojson/${filename}`]
  if (window.location.protocol !== 'file:') u.push(`./geojson/${filename}`)
  return u
}
async function loadGeoJSON() { return loadJSON(makeUrls('china_provinces.json'), 'china_geojson_v2') }
async function loadCityShapes() { return loadJSON(makeUrls('china_cities_shapes.json'), null) }
async function loadCityData() { return loadJSON(makeUrls('china_cities.json'), 'china_cities') || {} }

async function loadFootprints() {
  try {
    const d = await commonFetch(`${APP_CONFIG.API_BASE}/footprints.php?user_id=${currentUserId.value}`)
    if (d.success) footprints.value = d.footprints || []
    const s = await commonFetch(`${APP_CONFIG.API_BASE}/footprints.php?user_id=${currentUserId.value}&stats=1`)
    if (s.success && s.stats) visitedProvinces.value = s.stats.provinces || []
  } catch (e) { console.error('足迹数据加载失败:', e) }
}

// ── 渲染 ──

function provStyle() {
  return { fillColor: isDark() ? '#2a2e38' : '#e8ecf1', weight: 1.5, opacity: 1, color: isDark() ? '#444' : '#ccc', fillOpacity: 0.5 }
}

function cityStyle(feature) {
  const name = feature?.properties?.city_name || feature?.properties?.name || ''
  const visited = visitedCities.value.has(name)
  return {
    fillColor: visited ? (isDark() ? '#6699ff' : '#4f8cff') : 'transparent',
    weight: visited ? 1.5 : 0.5,
    opacity: 1,
    color: visited ? (isDark() ? '#88aaff' : '#4f8cff') : (isDark() ? '#444' : '#d0d0d0'),
    fillOpacity: visited ? 0.4 : 0,
  }
}

function renderProvinces() {
  if (geoJsonLayer) map.removeLayer(geoJsonLayer)
  geoJsonLayer = L.geoJSON(provinceGeoJson, {
    style: provStyle,
    onEachFeature: (f, l) => {
      l.bindTooltip(f.properties.name, { permanent: false, direction: 'center', opacity: 0.85 })
      l.on('click', () => {
        map.fitBounds(l.getBounds(), { padding: [20, 20], maxZoom: 8 })
        currentProvince.value = f.properties.name
      })
    },
  }).addTo(map)
}

function ensureCityLayer() {
  if (!cityShapesGeoJson || !map || map.getZoom() < 6) return
  if (citiesLayer) return // 已存在，不重建

  console.log('[足迹] 创建城市图层')
  citiesLayer = L.geoJSON(cityShapesGeoJson, {
    style: cityStyle,
    onEachFeature: (f, l) => {
      const name = f.properties.city_name || f.properties.name
      // 始终绑定点击 — 但只在 visited 时响应
      l.on('click', (e) => {
        L.DomEvent.stopPropagation(e)
        if (visitedCities.value.has(name)) {
          handleVisitedCityClick(name, f, l)
        }
      })
      l.on('mouseover', () => {
        if (visitedCities.value.has(name) && mapRef.value) mapRef.value.style.cursor = 'pointer'
      })
      l.on('mouseout', () => { if (mapRef.value) mapRef.value.style.cursor = '' })
    },
  }).addTo(map)
}

function updateCityStyles() {
  if (!citiesLayer) { ensureCityLayer(); return }
  citiesLayer.eachLayer(l => { if (l.feature) l.setStyle(cityStyle(l.feature)) })
}

function removeCityLayer() {
  if (citiesLayer) { citiesLayer.remove(); citiesLayer = null }
}

// ── 城市点击 ──

function handleVisitedCityClick(cityName, feature, layer) {
  const prints = footprints.value.filter(fp => fp.city === cityName)
  if (!prints.length) return
  if (activePopup) { map.closePopup(activePopup); activePopup = null }
  suppressAutoPopups = true  // 抑制自动弹出框，避免与手动弹出重复
  const center = feature.properties.center || feature.properties.centroid
  const latlng = center ? [center[1], center[0]] : (layer.getBounds ? layer.getBounds().getCenter() : null)

  let fired = false
  const show = () => { if (!fired) { fired = true; showCityPopup(prints, latlng) } }

  const bounds = layer.getBounds()
  if (bounds.isValid()) {
    map.once('moveend', show)
    map.fitBounds(bounds, { padding: [40, 40], maxZoom: 10 })
    setTimeout(show, 600)
  } else {
    show()
  }
}

function showCityPopup(prints, latlng) {
  if (activePopup) { map.closePopup(activePopup); activePopup = null }
  if (!latlng) { suppressAutoPopups = false; return }

  let items = ''
  prints.forEach(fp => {
    const thumb = fp.images && fp.images.length > 0
      ? `<img src="${getImageUrl(fp.images[0])}" class="pp-thumb" />`
      : '<div class="pp-thumb-placeholder">📍</div>'
    items += `<div class="pp-item" data-fpid="${fp.id}">${thumb}<div class="pp-info"><div class="pp-title">${fp.title}</div><div class="pp-meta">${fp.visited_date || ''}</div>${fp.description ? '<div class="pp-desc">' + fp.description.substring(0,60) + (fp.description.length>60?'...':'') + '</div>' : ''}</div></div>`
  })

  activePopup = L.popup({ maxWidth: 300, maxHeight: 320, className: 'city-records-popup' })
    .setLatLng(latlng)
    .setContent(`<div class="city-popup-container"><div class="cpp-header">📍 共 ${prints.length} 条记录</div><div class="cpp-list">${items}</div></div>`)
    .openOn(map)

  setTimeout(() => {
    document.querySelectorAll('.pp-item').forEach(el => {
      el.addEventListener('click', () => {
        const fp = prints.find(f => f.id == parseInt(el.dataset.fpid))
        if (fp) { selectedFootprint.value = fp; showDetail.value = true }
      })
    })
  }, 100)
}

function renderMarkers() {
  markersLayer.clearLayers()
  const zoom = map.getZoom()

  // ── 图钉模式（zoom 4-8，>=9 后交给弹出框） ──
  if (zoom < 9 && !suppressAutoPopups) {
    const sw = Math.round(48 * Math.pow(1.37, zoom - 4))
    const sh = Math.round(sw * 0.7)

    function fmtDate(d) {
      if (!d) return ''
      const parts = d.split('-')
      return parts.length >= 3 ? parts[1] + '-' + parts[2] : d
    }

    footprints.value.forEach(fp => {
      if (!fp.city) return
      const coords = getCityCenter(fp.city)
      if (!coords) return

      const img = fp.images && fp.images.length > 0 ? getImageUrl(fp.images[0]) : ''
      const date = fmtDate(fp.visited_date)
      const bodyH = img ? sh + Math.round(sw * 0.22) : sh + Math.round(sw * 0.08)

      const html = `<div class="fp-pin" style="width:${sw}px;">
        ${img ? `<img src="${img}" class="fp-pin-img" style="width:${sw}px;height:${sh}px;" />` : `<div class="fp-pin-placeholder" style="width:${sw}px;height:${sh}px;font-size:${Math.round(sw*0.35)}px;">📍</div>`}
        <div class="fp-pin-date" style="font-size:${Math.round(sw*0.17)}px;">${date}</div>
      </div>`

      const icon = L.divIcon({
        className: 'fp-pin-wrapper',
        html, iconSize: [sw, bodyH], iconAnchor: [Math.round(sw/2), bodyH],
      })
      L.marker(coords, { icon }).addTo(markersLayer)
        .on('click', (e) => { L.DomEvent.stopPropagation(e); handlePinClick(fp.city, [fp]) })
    })
  }

  // ── 弹出框模式（zoom ≥ 9）：仅在无手动弹出时显示，避免与 click 弹出重复 ──
  if (zoom >= 9 && !suppressAutoPopups) {
    const cityMap = new Map()
    footprints.value.forEach(fp => {
      if (!fp.city) return
      if (!cityMap.has(fp.city)) cityMap.set(fp.city, [])
      cityMap.get(fp.city).push(fp)
    })

    cityMap.forEach((prints, cityName) => {
      const coords = getCityCenter(cityName)
      if (!coords) return

      let items = ''
      prints.forEach(fp => {
        const thumb = fp.images && fp.images.length > 0
          ? `<img src="${getImageUrl(fp.images[0])}" class="am-pp-thumb" />`
          : '<div class="am-pp-placeholder">📍</div>'
        items += `<div class="am-pp-item" onclick="window.__fpOpenDetail &amp;&amp; window.__fpOpenDetail(${fp.id})">${thumb}<div class="am-pp-info"><div class="am-pp-title">${fp.title}</div><div class="am-pp-date">${fp.visited_date || ''}</div></div></div>`
      })

      const html = `<div class="fp-auto-popup">
        <div class="am-header">📍 ${cityName} · ${prints.length}条记录</div>
        <div class="am-list">${items}</div>
      </div>`

      const icon = L.divIcon({
        className: 'fp-auto-popup-wrapper',
        html, iconSize: [260, Math.min(prints.length * 56 + 36, 280)], iconAnchor: [130, -10],
      })
      L.marker(coords, { icon, zIndexOffset: 1000 }).addTo(markersLayer)
    })
  }
}

// 点击图钉 → 放大 + 弹出记录
function handlePinClick(cityName, prints) {
  if (activePopup) { map.closePopup(activePopup); activePopup = null }
  suppressAutoPopups = true
  const bounds = getCityBounds(cityName)
  const coords = getCityCenter(cityName)

  let fired = false
  const show = () => { if (!fired) { fired = true; showCityPopup(prints, coords) } }

  if (bounds && bounds.isValid()) {
    map.once('moveend', show)
    map.fitBounds(bounds, { padding: [40, 40], maxZoom: 10 })
    setTimeout(show, 600)
  } else {
    show()
  }
}

// ── 查找 ──

function findProvinceForCity(cityName) {
  if (!cityData || !cityName) return ''
  for (const [prov, info] of Object.entries(cityData)) {
    if (info.cities && info.cities[cityName]) return prov
  }
  return footprints.value.find(f => f.city === cityName)?.province || ''
}
function getCityBounds(cn) {
  if (!cityShapesGeoJson || !cn) return null
  for (const f of cityShapesGeoJson.features) {
    if ((f.properties.city_name || f.properties.name) === cn && f.geometry) return L.geoJSON(f).getBounds()
  }
  return null
}
function getCityCenter(cn) {
  if (!cn || !cityData) return null
  for (const info of Object.values(cityData)) {
    if (info.cities?.[cn]) { const c = info.cities[cn]; return [c[1], c[0]] }
  }
  return null
}

// ── 交互 ──

function handleMapLongPress(ll) {
  let prov = ''
  if (provinceGeoJson?.features) {
    for (const f of provinceGeoJson.features) {
      const c = f.properties.center || f.properties.centroid
      if (c && Math.sqrt((ll.lat - c[1])**2 + (ll.lng - c[0])**2) < 8) { prov = f.properties.name; break }
    }
  }
  openAddForm(ll.lat, ll.lng, prov, '')
}
function openAddFormAtCenter() { const c = map.getCenter(); openAddForm(c.lat, c.lng, currentProvince.value||'', '') }
function openAddForm(lat, lng, prov, city) {
  formLat.value=lat; formLng.value=lng; formProvince.value=prov; formCity.value=city
  editingFootprint.value=null; showForm.value=true
}
function closeForm() { showForm.value=false; editingFootprint.value=null }

async function handleSaved() {
  showForm.value=false; editingFootprint.value=null
  await loadFootprints()
  updateCityStyles()
  renderMarkers()
}
function toggleList() { listVisible.value=!listVisible.value }

function handleFootprintSelect(fp) {
  listVisible.value=false; selectedFootprint.value=fp; showDetail.value=true
  const b = getCityBounds(fp.city)
  const c = getCityCenter(fp.city) || (fp.latitude&&fp.longitude?[parseFloat(fp.latitude),parseFloat(fp.longitude)]:null)
  if (b&&b.isValid()) map.fitBounds(b,{padding:[40,40],maxZoom:10})
  else if (c) map.setView(c,10,{animate:true,duration:0.5})
}

function handleEdit(fp) {
  showDetail.value=false; editingFootprint.value=fp
  const c=getCityCenter(fp.city)
  formLat.value=c?c[0]:parseFloat(fp.latitude); formLng.value=c?c[1]:parseFloat(fp.longitude)
  formProvince.value=fp.province||''; formCity.value=fp.city||''
  showForm.value=true
}
async function handleDelete(fp) {
  if (!await customConfirm('确定要删除这条足迹吗？')) return
  try {
    const d=await commonFetch(`${APP_CONFIG.API_BASE}/footprints.php`,{method:'DELETE',headers:{'Content-Type':'application/json'},body:JSON.stringify({id:fp.id,user_id:currentUserId.value})})
    if(d.success){ customAlert('删除成功'); showDetail.value=false; selectedFootprint.value=null; await loadFootprints(); updateCityStyles(); renderMarkers() }
    else customAlert(d.message||'删除失败')
  } catch(e) { customAlert('网络错误') }
}
function resetToNational() {
  currentProvince.value=null; suppressAutoPopups=false
  map.setView([35.86,104.19],4,{animate:true,duration:0.5})
  if(activePopup){map.closePopup(activePopup);activePopup=null}
  renderMarkers()
}

// ── 主题 ──

let themeObs=null
function setupThemeObs() {
  themeObs=new MutationObserver(()=>{
    if(geoJsonLayer) geoJsonLayer.setStyle(provStyle)
    updateCityStyles()
    renderMarkers()
  })
  themeObs.observe(document.body,{attributes:true,attributeFilter:['class']})
}

// ── 地图 ──

async function initMap() {
  if(!mapRef.value) return
  map=L.map('footprints-map',{
    center:[35.86,104.19],zoom:4,minZoom:3,maxZoom:12,
    zoomControl:true,attributionControl:false,
    scrollWheelZoom:false,touchZoom:true,dragging:true,
  })
  L.tileLayer('https://webrd0{s}.is.autonavi.com/appmaptile?lang=zh_cn&size=1&scale=1&style=8&x={x}&y={y}&z={z}',{
    maxZoom:12,subdomains:'1234',attribution:'&copy; 高德地图',
  }).addTo(map)

  provinceGeoJson=await loadGeoJSON()
  cityShapesGeoJson=await loadCityShapes()
  cityData=await loadCityData()

  if(provinceGeoJson) renderProvinces()
  markersLayer=L.layerGroup().addTo(map)
  renderMarkers()
  ensureCityLayer()

  map.on('zoomend',()=>{
    if(map.getZoom()>=6) ensureCityLayer()
    else removeCityLayer()
    renderMarkers()
  })
  // 用户手动拖拽地图时解除抑制
  map.on('dragstart',()=>{ suppressAutoPopups = false })

  let pt=null
  map.on('mousedown touchstart',e=>{
    pt=setTimeout(()=>{const ll=e.latlng||(e.touches?.[0]&&map.mouseEventToLatLng(e.touches[0]));if(ll) handleMapLongPress(ll)},600)
  })
  map.on('mouseup touchend touchcancel',()=>clearTimeout(pt))
  map.on('mousemove touchmove',()=>clearTimeout(pt))

  loading.value=false
}

onMounted(async()=>{
  if(!currentUserId.value){router.push('/');return}
  // 全局函数：自动弹出框条目点击
  window.__fpOpenDetail = (id) => {
    const fp = footprints.value.find(f => f.id == id)
    if (fp) { selectedFootprint.value = fp; showDetail.value = true }
  }
  await loadFootprints()
  await nextTick()
  await initMap()
  ensureCityLayer()
  setupThemeObs()
  const nav=document.getElementById('bottom-nav')
  if(nav&&window.getComputedStyle(nav).display!=='none') mainRef.value?.classList.add('has-nav')
})

onUnmounted(()=>{
  window.__fpOpenDetail = null
  if(map){map.remove();map=null}
  if(themeObs){themeObs.disconnect();themeObs=null}
  if(activePopup){map?.closePopup(activePopup);activePopup=null}
})
</script>

<style>
@import '../assets/css/footprints.css';
</style>
