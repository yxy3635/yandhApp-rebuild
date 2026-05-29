import { createApp } from 'vue'
import './style.css'
import './assets/css/home.css'
import App from './App.vue'
import router from './router'
import { setupPlusBackButton } from './utils/plus-backbutton'
import { initPush, reportCid } from './utils/push'
import AppHeader from './components/AppHeader.vue'
import LoadingSpinner from './components/LoadingSpinner.vue'

const app = createApp(App)
app.use(router)
app.component('AppHeader', AppHeader)
app.component('LoadingSpinner', LoadingSpinner)
app.mount('#app')

setupPlusBackButton()

// 调试提示：toast 优先，不可用时降级为 alert
function pushDebug(msg) {
  console.log('[Push] ' + msg)
  try {
    if (typeof plus !== 'undefined' && plus.nativeUI?.toast) {
      plus.nativeUI.toast(msg, { duration: 'long' })
      return
    }
  } catch {}
  alert('[Push] ' + msg)
}

// 初始化 UniPush 2.0 推送 — 带完整诊断
function tryInitPush() {
  // // 1. plus 对象本身不可用
  // if (typeof plus === 'undefined') {
  //   pushDebug('plus 不可用，非 5+ App 环境')
  //   return
  // }

  // // 2. plus.push 不存在（标准基座或未勾选 Push 模块）
  // if (!plus.push) {
  //   pushDebug('Push 模块未加载\n请确认：\n1. manifest勾选了Push\n2. 使用的是自定义基座')
  //   return
  // }

  // // 3. 一切就绪
  // pushDebug('Push 模块已加载，开始注册...')
  initPush()

  if (localStorage.getItem('user_id')) {
    reportCid()
  }
}

// plusready 事件 + setTimeout 兜底
document.addEventListener('plusready', tryInitPush, false)
setTimeout(tryInitPush, 600)

// 监听登录成功事件，上报 CID
window.addEventListener('user-login', () => {
  reportCid()
})
