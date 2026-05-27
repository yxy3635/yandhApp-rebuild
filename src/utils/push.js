/**
 * UniPush 2.0 推送模块
 */

import { APP_CONFIG } from './config'

let clientInfo = null
let initialized = false

function pushDebug(msg) {
  console.log('[Push] ' + msg)
  try {
    if (typeof plus !== 'undefined' && plus.nativeUI?.toast) {
      plus.nativeUI.toast(msg, { duration: 'long' })
      return
    }
  } catch {}
}

// ══════════════════════════════════════════════════════════════
// 电池优化豁免 — 延长后台推送接收时间
// Android 6.0+ 的 Doze 模式会在息屏后休眠 App，断开推送长连接。
// 引导用户将 App 加入电池优化白名单可显著延长后台存活时间。
// ══════════════════════════════════════════════════════════════

function isBatteryIgnoring() {
  try {
    const main = plus.android.runtimeMainActivity()
    const BuildVersion = plus.android.importClass('android.os.Build$VERSION')
    if (BuildVersion.SDK_INT < 23) return true // Android 5.x 无此限制

    const Context = plus.android.importClass('android.content.Context')
    const pkgName = plus.android.invoke(main, 'getPackageName')
    const svc = plus.android.invoke(main, 'getSystemService', Context.POWER_SERVICE)
    return plus.android.invoke(svc, 'isIgnoringBatteryOptimizations', pkgName)
  } catch (e) {
    console.log('[Push] 电池优化检测异常: ' + e.message)
    return null
  }
}

function requestBatteryIgnore() {
  try {
    const main = plus.android.runtimeMainActivity()
    const Settings = plus.android.importClass('android.provider.Settings')
    const Uri = plus.android.importClass('android.net.Uri')
    const Intent = plus.android.importClass('android.content.Intent')

    const pkgName = plus.android.invoke(main, 'getPackageName')
    const intent = plus.android.newObject(Intent, Settings.ACTION_REQUEST_IGNORE_BATTERY_OPTIMIZATIONS)
    plus.android.invoke(intent, 'setData', Uri.parse('package:' + pkgName))
    plus.android.invoke(main, 'startActivity', intent)
  } catch (e) {
    console.error('[Push] 打开电池优化设置失败: ' + e.message)
  }
}

function checkAndRequestBattery() {
  if (typeof plus === 'undefined' || !plus.android) return
  if (localStorage.getItem('_battery_opt_asked')) return // 已询问过

  // 延迟一下，等界面加载完
  setTimeout(() => {
    const ignoring = isBatteryIgnoring()
    console.log('[Push] 电池优化豁免状态: ' + ignoring)
    if (ignoring === true) return // 已豁免
    if (ignoring === null) return // 检测异常，不打扰用户

    // 未豁免 — 弹出提示引导用户
    localStorage.setItem('_battery_opt_asked', '1')
    const agreed = confirm(
      '为了在后台也能及时收到消息推送，建议关闭本应用的电池优化。\n\n点击"确定"前往设置页面，然后选择"不优化"。'
    )
    if (agreed) {
      setTimeout(() => requestBatteryIgnore(), 300)
    }
  }, 2000)
}

export function initPush() {
  if (initialized) return
  initialized = true

  console.log('[Push] initPush 开始')
  console.log('[Push] plus.push 可用方法:', Object.keys(plus.push))

  // 1. 先尝试同步调用（部分 UniPush 2.0 SDK 版本是同步的）
  let info = null
  try {
    info = plus.push.getClientInfo()
    console.log('[Push] 同步 getClientInfo 返回:', JSON.stringify(info))
  } catch (e) {
    console.log('[Push] 同步 getClientInfo 抛异常:', e.message)
  }

  // 处理同步返回结果
  if (info && info.clientid) {
    handleClientInfo(info)
    registerEvents()
    return
  }

  // 2. 尝试 getClientInfoAsync（部分版本提供的异步方法）
  if (typeof plus.push.getClientInfoAsync === 'function') {
    console.log('[Push] 尝试 getClientInfoAsync...')
    plus.push.getClientInfoAsync(
      (asyncInfo) => {
        console.log('[Push] getClientInfoAsync 成功:', JSON.stringify(asyncInfo))
        if (asyncInfo?.clientid) {
          handleClientInfo(asyncInfo)
        } else {
          pushDebug('CID 为空，检查 DCloud 后台 UniPush 配置')
        }
      },
      (err) => {
        console.error('[Push] getClientInfoAsync 失败:', JSON.stringify(err))
        pushDebug('推送注册失败: ' + (err?.message || ''))
      }
    )
    registerEvents()
    return
  }

  // 3. 尝试带回调的 getClientInfo（传统 API）
  console.log('[Push] 尝试带回调的 getClientInfo...')
  plus.push.getClientInfo(
    (cbInfo) => {
      console.log('[Push] 回调 getClientInfo 成功:', JSON.stringify(cbInfo))
      if (cbInfo?.clientid) {
        handleClientInfo(cbInfo)
      } else {
        pushDebug('CID 为空，检查 DCloud 后台 UniPush 配置')
      }
    },
    (err) => {
      console.error('[Push] 回调 getClientInfo 失败:', JSON.stringify(err))
      pushDebug('推送注册失败: ' + (err?.message || ''))
    }
  )

  registerEvents()
}

function handleClientInfo(info) {
  clientInfo = info
  console.log('[Push] CID: ' + info.clientid + ' token: ' + (info.token || '无'))

  if (info.clientid) {
    uploadCid(info.clientid)
    pushDebug('推送已就绪')
    checkAndRequestBattery()
  } else {
    pushDebug('CID 为空，检查 DCloud 后台 UniPush 配置')
  }
}

function registerEvents() {
  plus.push.addEventListener('receive', (msg) => {
    console.log('[Push] 在线推送:', JSON.stringify(msg))
    if (msg.payload) {
      try {
        const payload = typeof msg.payload === 'string' ? JSON.parse(msg.payload) : msg.payload
        window.dispatchEvent(new CustomEvent('push-message', { detail: payload }))
      } catch {}
    }
    showLocalNotification(msg.title || '新消息', msg.content || '', msg.payload)
  }, false)

  plus.push.addEventListener('click', (msg) => {
    console.log('[Push] 通知点击:', JSON.stringify(msg))
    if (msg.payload) {
      try {
        const payload = typeof msg.payload === 'string' ? JSON.parse(msg.payload) : msg.payload
        window.dispatchEvent(new CustomEvent('push-click', { detail: payload }))
      } catch {}
    }
  }, false)

  console.log('[Push] 事件监听已注册')
}

function uploadCid(cid) {
  const userId = localStorage.getItem('user_id')
  if (!userId || !cid) return

  console.log('[Push] 上报 CID: cid=' + cid + ' user_id=' + userId)
  fetch(`${APP_CONFIG.API_BASE}/register_push.php`, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ user_id: Number(userId), cid, platform: 'android' }),
  })
    .then((r) => r.json())
    .then((d) => console.log('[Push] 上报结果:', JSON.stringify(d)))
    .catch((e) => console.error('[Push] 上报失败:', e))
}

function showLocalNotification(title, content, payload) {
  try {
    plus.push.createMessage(content || '', payload || '', {
      title: title || '新消息',
      cover: true,
    })
  } catch {}
}

export function getPushClientInfo() {
  return clientInfo
}

export function reportCid() {
  if (clientInfo?.clientid) {
    uploadCid(clientInfo.clientid)
  } else if (typeof plus !== 'undefined' && plus.push) {
    // 尝试同步获取
    try {
      const info = plus.push.getClientInfo()
      if (info?.clientid) {
        clientInfo = info
        uploadCid(info.clientid)
      }
    } catch {}
  }
}
