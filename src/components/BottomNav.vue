<template>
  <nav v-if="navVisible" id="bottom-nav" ref="navRef">
    <button
      v-for="tab in visibleTabs"
      :key="tab.label"
      :ref="el => setBtnRef(tab.label, el)"
      :class="{ active: isActive(tab.route) }"
      @click="goTo(tab)"
    >
      {{ tab.label }}
    </button>
    <span class="nav-indicator" ref="indicatorRef"></span>
  </nav>
</template>

<script setup>
import { ref, computed, watch, onMounted, nextTick } from 'vue'
import { useRouter, useRoute } from 'vue-router'

const router = useRouter()
const route = useRoute()

const navRef = ref(null)
const indicatorRef = ref(null)
const btnRefs = {}

function setBtnRef(route, el) {
  if (el) btnRefs[route] = el
}

const isAdmin = ref(localStorage.getItem('username') === 'admin')

const tabs = [
  { label: '主页', route: '/home' },
  { label: '互动', route: '/interaction' },
  { label: '纪念日', route: '/anniversary' },
  { label: 'AI', route: '/ai' },
  { label: '管理', route: '/home', adminOnly: true }
]

const visibleTabs = computed(() =>
  tabs.filter(t => !t.adminOnly || isAdmin.value)
)

const navRoutes = ['/home', '/interaction', '/anniversary', '/ai', '/userlist', '/profile']
const navVisible = computed(() => navRoutes.includes(route.path))

function isActive(tabRoute) {
  const current = route.path
  if (current === '/userlist' && tabRoute === '/interaction') return true
  return current === tabRoute
}

function goTo(tab) {
  if (tab.adminOnly) {
    window.location.href = '../../admin.html'
    return
  }
  if (route.path !== tab.route) {
    router.push(tab.route)
  }
}

// --- Indicator animation ---

function getActiveBtn() {
  if (!navRef.value) return null
  for (const tab of visibleTabs.value) {
    if (isActive(tab.route)) {
      return btnRefs[tab.label] || null
    }
  }
  return null
}

function updateIndicator(immediate = false) {
  const activeBtn = getActiveBtn()
  if (!activeBtn || !navRef.value || !indicatorRef.value) return

  const navRect = navRef.value.getBoundingClientRect()
  const btnRect = activeBtn.getBoundingClientRect()

  const btnWidth = btnRect.width
  const indicatorWidth = btnWidth * 0.35
  const btnCenter = btnRect.left - navRect.left + btnWidth / 2
  const left = btnCenter - indicatorWidth / 2

  if (immediate) {
    indicatorRef.value.style.transition = 'none'
    indicatorRef.value.style.width = indicatorWidth + 'px'
    indicatorRef.value.style.transform = `translateX(${left}px)`
    indicatorRef.value.offsetHeight
    indicatorRef.value.style.transition = ''
  } else {
    requestAnimationFrame(() => {
      if (!indicatorRef.value) return
      indicatorRef.value.style.width = indicatorWidth + 'px'
      indicatorRef.value.style.transform = `translateX(${left}px)`
    })
  }
}

watch(() => route.path, () => {
  nextTick(() => updateIndicator())
})

onMounted(() => {
  nextTick(() => {
    updateIndicator(true)
    requestAnimationFrame(() => updateIndicator())
  })
})

let resizeTimer = null
window.addEventListener('resize', () => {
  updateIndicator(true)
  if (resizeTimer) clearTimeout(resizeTimer)
  resizeTimer = setTimeout(() => updateIndicator(), 60)
})
</script>
