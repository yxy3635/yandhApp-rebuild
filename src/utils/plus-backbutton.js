/**
 * HBuilder 5+ App：物理返回键默认会直接退出 Webview。
 * 注册 backbutton 后由本模块接管：先让页面拦截（预览/弹窗），再 router.back()，根页再按一次退出。
 */
import router from '../router';

const EXIT_DOUBLE_TAP_MS = 2200;
let lastExitTap = 0;
let bound = false;

function closeCustomModalIfOpen() {
  const modal = document.getElementById('custom-modal-overlay');
  if (!modal?.classList.contains('show')) return false;
  const cancel = document.getElementById('custom-modal-cancel-btn');
  const confirm = document.getElementById('custom-modal-confirm-btn');
  if (cancel && !cancel.classList.contains('hide')) {
    cancel.click();
    return true;
  }
  if (confirm) {
    confirm.click();
    return true;
  }
  return false;
}

function hardwareBack() {
  if (typeof plus === 'undefined' || !plus.key || !plus.runtime) return;

  const ev = new CustomEvent('app-hardware-back', { cancelable: true });
  window.dispatchEvent(ev);
  if (ev.defaultPrevented) return;

  if (closeCustomModalIfOpen()) return;

  try {
    const st = window.history.state;
    if (st && typeof st === 'object' && Object.prototype.hasOwnProperty.call(st, 'back') && st.back != null) {
      router.back();
      return;
    }
  } catch (_) {}

  if (window.history.length > 1) {
    router.back();
    return;
  }

  const now = Date.now();
  if (now - lastExitTap < EXIT_DOUBLE_TAP_MS) {
    plus.runtime.quit();
  } else {
    lastExitTap = now;
    plus.nativeUI.toast('再按一次退出应用', { duration: 'short' });
  }
}

function bindOnce() {
  if (bound || typeof plus === 'undefined' || !plus.key) return;
  bound = true;
  plus.key.addEventListener('backbutton', hardwareBack, false);
}

export function setupPlusBackButton() {
  document.addEventListener('plusready', bindOnce, false);
  setTimeout(bindOnce, 300);
}
