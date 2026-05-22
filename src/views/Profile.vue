<template>
  <div class="profile-container">
    <AppHeader title="个人中心" />
    
    <main class="profile-main">
      <div class="profile-card">
        <div class="profile-avatar-container">
          <img id="avatar" :src="getAvatarUrl(userInfo.avatar_url)" alt="头像" @click="triggerAvatarUpload">
          <input type="file" ref="avatarInput" accept="image/*" style="display:none;" @change="updateAvatar">
          <button class="edit-avatar-btn" @click="triggerAvatarUpload">✏️</button>
        </div>
        <div class="profile-details">
          <template v-if="!isEditing">
            <h2>{{ userInfo.username || '用户名' }}</h2>
            <p>{{ userInfo.signature || '这个人很神秘，还没有写签名~' }}</p>
            <div class="profile-actions">
              <button @click="isEditing = true">编辑资料</button>
            </div>
          </template>
          <template v-else>
            <label class="edit-field">昵称/用户名</label>
            <input type="text" v-model="editForm.username" class="edit-field">
            <label class="edit-field">个性签名~</label>
            <textarea v-model="editForm.signature" class="edit-field" placeholder="添加你的个性签名..."></textarea>
            <div class="profile-actions">
              <button @click="saveProfile">保存</button>
              <button @click="cancelEdit">取消</button>
            </div>
          </template>
        </div>
      </div>

      <div class="settings-section">
        <h2>设置 <span title="点我有惊喜！" style="margin-left:8px; cursor:pointer; opacity:0.5; transition:opacity 0.2s;" @click="goTo('/love')">🪄</span></h2>
        
        <div class="setting-item">
          <span>暗/亮主题切换</span>
          <div class="theme-switch">
            <input type="checkbox" id="theme-toggle" class="checkbox" v-model="isDarkTheme" @change="toggleTheme">
            <label for="theme-toggle" class="toggle-label">
              <span class="ball"></span>
            </label>
          </div>
        </div>

        <div class="setting-item">
          <span>雪花特效</span>
          <div class="theme-switch">
            <input type="checkbox" id="snow-toggle" class="checkbox" v-model="isSnowEnabled" @change="toggleSnow">
            <label for="snow-toggle" class="toggle-label">
              <span class="ball"></span>
            </label>
          </div>
        </div>

        <div class="setting-item" style="display:flex; gap:12px; margin-top: 10px;">
          <button class="full-width-button about-button" style="flex:1; background: #007aff;" @click="checkUpdate">检查更新</button>
          <button class="full-width-button about-button" style="flex:1; background: #ff9500;" @click="showAbout">关于</button>
        </div>

        <div class="setting-item" style="margin-top: 10px;">
          <button class="full-width-button logout-button" @click="logout">退出登录</button>
        </div>
      </div>
    </main>

    <!-- 检查更新：版本对比 + App 内下载进度 -->
    <div
      v-if="updateUi.open"
      class="update-overlay"
      @click.self="closeUpdateModal"
    >
      <div class="update-panel" @click.stop>
        <h3 class="update-panel-title">
          {{ updateUi.loading ? '正在检查…' : (updateUi.hasNew ? '发现新版本' : '已是最新版本') }}
        </h3>
        <div v-if="updateUi.loading" class="update-loading">连接版本服务器…</div>
        <template v-else>
          <div class="update-meta">
            <p><span class="lbl">当前版本</span>{{ updateUi.currentVersion }}</p>
            <p><span class="lbl">最新版本</span>{{ updateUi.latestVersion }}</p>
            <p v-if="updateUi.releaseDate"><span class="lbl">发布日期</span>{{ updateUi.releaseDate }}</p>
          </div>
          <div v-if="updateUi.changelog" class="update-changelog-wrap">
            <div class="changelog-label">更新内容</div>
            <pre class="update-changelog">{{ updateUi.changelog }}</pre>
          </div>
          <div v-if="updateUi.downloading" class="update-progress-block">
            <div class="update-progress-track">
              <div class="update-progress-fill" :style="{ width: dlPercent + '%' }"></div>
            </div>
            <span class="update-progress-text">{{ dlPercent }}%</span>
          </div>
          <p v-if="updateUi.hasNew && !updateUi.downloadUrl" class="update-hint warn">
            服务端未配置下载地址，无法在线更新。
          </p>
        </template>
        <div class="update-actions">
          <button
            v-if="!updateUi.loading && updateUi.hasNew && updateUi.downloadUrl && !updateUi.downloading"
            type="button"
            class="btn-update-download"
            :disabled="updateUi.busy"
            @click="startApkDownload"
          >
            {{ plusEnv ? '下载并安装' : '浏览器打开下载' }}
          </button>
          <button type="button" class="btn-update-close" @click="closeUpdateModal">
            {{ updateUi.downloading ? '后台下载中…' : '关闭' }}
          </button>
        </div>
      </div>
    </div>

  </div>
</template>

<script setup>
import { ref, reactive, onMounted, computed } from 'vue';
import { useRouter } from 'vue-router';
import { APP_CONFIG, commonFetch, getAvatarUrl } from '../utils/config';
import { customAlert, customConfirm } from '../utils/modal';
import { isVersionLower } from '../utils/version-compare';
import { getAppVersionName, downloadApk, installApkFile } from '../utils/plus-app-update';

const router = useRouter();
const avatarInput = ref(null);
const isEditing = ref(false);

const isDarkTheme = ref(localStorage.getItem('app-theme') === 'dark');
const isSnowEnabled = ref(localStorage.getItem('snow_effect_enabled') !== 'false');

const userInfo = reactive({
  username: '',
  signature: '',
  avatar_url: ''
});

const editForm = reactive({
  username: '',
  signature: ''
});

onMounted(() => {
  loadProfile();
  
  if (isDarkTheme.value) {
    document.body.classList.add('dark-theme');
  } else {
    document.body.classList.remove('dark-theme');
  }
});

const toggleTheme = () => {
  const newTheme = isDarkTheme.value ? 'dark' : 'light';
  localStorage.setItem('app-theme', newTheme);
  if (newTheme === 'dark') {
    document.body.classList.add('dark-theme');
  } else {
    document.body.classList.remove('dark-theme');
  }
};

const toggleSnow = () => {
  localStorage.setItem('snow_effect_enabled', isSnowEnabled.value);
};

const plusEnv = computed(() => typeof window !== 'undefined' && !!window.plus?.downloader);

const updateUi = reactive({
  open: false,
  loading: false,
  busy: false,
  downloading: false,
  hasNew: false,
  currentVersion: '',
  latestVersion: '',
  releaseDate: '',
  changelog: '',
  downloadUrl: ''
});

const dlPercent = ref(0);

const closeUpdateModal = () => {
  if (updateUi.downloading) {
    customAlert('请等待下载完成后再关闭');
    return;
  }
  updateUi.open = false;
};

const checkUpdate = async () => {
  updateUi.open = true;
  updateUi.loading = true;
  updateUi.hasNew = false;
  updateUi.downloading = false;
  updateUi.busy = false;
  dlPercent.value = 0;

  let currentRaw = '';
  try {
    currentRaw = (await getAppVersionName()) || '';
  } catch {
    currentRaw = '';
  }
  const currentForCompare = currentRaw.trim() || '0.0.0';

  try {
    const response = await fetch(APP_CONFIG.VERSION_CHECK_URL);
    const data = await response.json();
    if (!data || !data.version) {
      updateUi.open = false;
      customAlert('未获取到版本信息');
      return;
    }

    updateUi.currentVersion = currentRaw || '（非 App 环境无法读取）';
    updateUi.latestVersion = String(data.version);
    updateUi.releaseDate = data.releaseDate != null ? String(data.releaseDate) : '';
    updateUi.changelog = data.changelog != null ? String(data.changelog) : '';
    updateUi.downloadUrl = data.downloadUrl != null ? String(data.downloadUrl).trim() : '';
    updateUi.hasNew = isVersionLower(currentForCompare, data.version);
  } catch {
    updateUi.open = false;
    customAlert('检查更新失败');
  } finally {
    updateUi.loading = false;
  }
};

const startApkDownload = async () => {
  const url = updateUi.downloadUrl;
  if (!url) {
    customAlert('未配置下载地址');
    return;
  }

  if (!window.plus?.downloader) {
    window.open(url, '_blank', 'noopener,noreferrer');
    return;
  }

  updateUi.busy = true;
  updateUi.downloading = true;
  dlPercent.value = 0;

  try {
    const path = await downloadApk(url, (p) => {
      dlPercent.value = p;
    });
    dlPercent.value = 100;
    await installApkFile(path);
    customAlert('安装包已下载，请按系统提示完成安装');
    updateUi.open = false;
  } catch (e) {
    const msg = e && e.message ? e.message : String(e);
    if (msg === 'NO_PLUS') {
      window.open(url, '_blank', 'noopener,noreferrer');
    } else {
      customAlert('下载或安装失败：' + msg);
    }
  } finally {
    updateUi.busy = false;
    updateUi.downloading = false;
    dlPercent.value = 0;
  }
};

const showAbout = () => {
  customAlert('软件名称：YandH\n作者：千纸雏鸳\n邮箱：yxy3635@gmail.com');
};

const loadProfile = async () => {
  const userId = localStorage.getItem('user_id');
  if (!userId) {
    router.push('/');
    return;
  }
  try {
    const data = await commonFetch(`${APP_CONFIG.API_BASE}/profile.php?user_id=${userId}`);
    if (data.success && data.user) {
      userInfo.username = data.user.username;
      userInfo.signature = data.user.signature;
      userInfo.avatar_url = data.user.avatar_url;
      
      editForm.username = data.user.username;
      editForm.signature = data.user.signature;
    }
  } catch (error) {
    console.error('Failed to load profile:', error);
  }
};

const triggerAvatarUpload = () => {
  avatarInput.value.click();
};

const updateAvatar = async (event) => {
  const file = event.target.files[0];
  if (!file) return;
  
  const userId = localStorage.getItem('user_id');
  const formData = new FormData();
  formData.append('user_id', userId);
  formData.append('avatar', file);
  
  try {
    const response = await fetch(`${APP_CONFIG.API_BASE}/upload_avatar.php`, {
      method: 'POST',
      body: formData
    });
    const data = await response.json();
    if (data.success) {
      userInfo.avatar_url = data.avatar_url;
      customAlert('头像上传成功');
    } else {
      customAlert(data.message || '头像上传失败');
    }
  } catch (error) {
    customAlert('网络错误');
  }
};

const saveProfile = async () => {
  const userId = localStorage.getItem('user_id');
  const formData = new FormData();
  formData.append('user_id', userId);
  formData.append('username', editForm.username);
  formData.append('signature', editForm.signature);

  try {
    const response = await fetch(`${APP_CONFIG.API_BASE}/update_profile.php`, {
      method: 'POST',
      body: formData
    });
    const data = await response.json();
    
    if (data.success) {
      userInfo.username = editForm.username;
      userInfo.signature = editForm.signature;
      localStorage.setItem('username', userInfo.username);
      isEditing.value = false;
      customAlert('资料更新成功');
    } else {
      customAlert(data.message || '更新失败');
    }
  } catch (error) {
    customAlert('网络错误');
  }
};

const cancelEdit = () => {
  editForm.username = userInfo.username;
  editForm.signature = userInfo.signature;
  isEditing.value = false;
};

const logout = async () => {
  if (await customConfirm('确定要退出登录吗？')) {
    localStorage.removeItem('user_id');
    localStorage.removeItem('username');
    localStorage.removeItem('login_timestamp');
    router.push('/');
  }
};

const goTo = (path) => {
  router.push(path);
};
</script>

<style scoped>
.profile-container {
  min-height: 100vh;
  padding-top: 70px;
  padding-bottom: 90px;
}

.profile-header {
  padding: 20px;
  font-size: 20px;
  font-weight: bold;
}
.profile-card {
  background: var(--bg-color-card, #fff);
  border-radius: 16px;
  padding: 20px;
  margin: 20px;
  box-shadow: 0 4px 12px var(--shadow-color, rgba(0,0,0,0.1));
  text-align: center;
  color: var(--text-color-light, #333);
}

.profile-main{
  animation: fadeInUp 0.7s;
}

:global(body.dark-theme) .profile-card {
  background: var(--bg-color-card);
  box-shadow: 0 4px 12px var(--shadow-color, rgba(0,0,0,0.4));
  color: var(--text-color-light);
}

.profile-avatar-container {
  position: relative;
  display: inline-block;
  margin-bottom: 16px;
}
.profile-avatar-container img {
  width: 100px;
  height: 100px;
  border-radius: 50%;
  object-fit: cover;
}
.edit-avatar-btn {
  position: absolute;
  bottom: 0;
  right: 0;
  border: none;
  background: white;
  border-radius: 50%;
  padding: 6px;
  cursor: pointer;
  box-shadow: 0 2px 6px rgba(0,0,0,0.2);
}
.profile-details input,
.profile-details textarea {
  width: 100%;
  padding: 10px;
  margin: 10px 0;
  border: 1px solid var(--border-color, #ddd);
  border-radius: 8px;
  background: var(--bg-color-card, #fff);
  color: var(--text-color-light, #333);
  box-sizing: border-box;
}
.profile-actions button {
  padding: 8px 16px;
  margin: 0 8px;
  border: none;
  border-radius: 8px;
  background: #007aff;
  color: white;
  cursor: pointer;
}
.settings-section {
  padding: 20px;
}
.setting-item {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 12px 16px;
  background: var(--bg-color-card, #fff);
  color: var(--text-color-light, #333);
  margin-bottom: 10px;
  border-radius: 8px;
  box-shadow: 0 2px 6px var(--shadow-color, rgba(0,0,0,0.05));
}

:global(body.dark-theme) .setting-item {
  background: var(--bg-color-card);
}

.full-width-button {
  width: 100%;
  padding: 12px;
  background: #ff3b30;
  color: white;
  border: none;
  border-radius: 8px;
  font-size: 16px;
}

/* Toggle Switch Styles */
.theme-switch {
  position: relative;
}
.checkbox {
  opacity: 0;
  position: absolute;
}
.toggle-label {
  background-color: #111;
  width: 50px;
  height: 26px;
  border-radius: 50px;
  position: relative;
  padding: 5px;
  cursor: pointer;
  display: flex;
  justify-content: space-between;
  align-items: center;
  box-shadow: inset 0 2px 5px rgba(0,0,0,0.4);
}
.checkbox:checked + .toggle-label {
  background-color: #4CAF50;
}
.ball {
  background-color: #fff;
  width: 22px;
  height: 22px;
  position: absolute;
  left: 2px;
  top: 2px;
  border-radius: 50%;
  transition: transform 0.2s linear;
}
.checkbox:checked + .toggle-label .ball {
  transform: translateX(24px);
}

/* 检查更新弹层 */
.update-overlay {
  position: fixed;
  inset: 0;
  z-index: 200000;
  background: rgba(0, 0, 0, 0.55);
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 20px;
  box-sizing: border-box;
  backdrop-filter: blur(4px);
  -webkit-backdrop-filter: blur(4px);
}

.update-panel {
  width: 100%;
  max-width: 400px;
  max-height: min(85vh, 620px);
  overflow: hidden;
  display: flex;
  flex-direction: column;
  background: var(--bg-color-card, #fff);
  color: var(--text-color-light, #222);
  border-radius: 18px;
  box-shadow: 0 12px 40px rgba(0, 0, 0, 0.25);
  border: 1px solid var(--border-color, #e8e8e8);
}

.update-panel-title {
  margin: 0;
  padding: 16px 18px 10px;
  font-size: 18px;
  font-weight: 700;
  color: var(--nav-btn-active-color, #007aff);
  text-align: center;
  flex-shrink: 0;
}

:global(body.dark-theme) .update-panel-title {
  color: #6ea8ff;
}

.update-loading {
  padding: 24px 18px 28px;
  text-align: center;
  color: var(--text-color-medium, #666);
  font-size: 15px;
}

.update-meta {
  padding: 0 18px;
  font-size: 14px;
  line-height: 1.65;
  flex-shrink: 0;
}

.update-meta p {
  margin: 6px 0;
  display: flex;
  flex-wrap: wrap;
  gap: 6px 10px;
  align-items: baseline;
}

.update-meta .lbl {
  color: var(--text-color-medium, #888);
  min-width: 4.5em;
}

.update-changelog-wrap {
  margin: 12px 18px 0;
  flex: 1;
  min-height: 0;
  display: flex;
  flex-direction: column;
}

.changelog-label {
  font-size: 13px;
  color: var(--text-color-medium, #888);
  margin-bottom: 6px;
}

.update-changelog {
  margin: 0;
  padding: 10px 12px;
  font-size: 14px;
  line-height: 1.55;
  white-space: pre-line;
  word-break: break-word;
  background: var(--bg-color-light, #f5f7fa);
  border-radius: 10px;
  border: 1px solid var(--border-color, #e5e5e5);
  color: var(--text-color-light, #333);
  overflow-y: auto;
  max-height: 36vh;
  font-family: inherit;
}

.update-hint.warn {
  margin: 10px 18px 0;
  font-size: 13px;
  color: #c0392b;
}

:global(body.dark-theme) .update-hint.warn {
  color: #ff8a80;
}

.update-progress-block {
  padding: 14px 18px 6px;
  display: flex;
  align-items: center;
  gap: 12px;
  flex-shrink: 0;
}

.update-progress-track {
  flex: 1;
  height: 10px;
  border-radius: 999px;
  background: var(--border-color, #e0e0e0);
  overflow: hidden;
}

.update-progress-fill {
  height: 100%;
  border-radius: 999px;
  background: linear-gradient(90deg, #007aff, #00c6ff);
  transition: width 0.15s ease-out;
}

:global(body.dark-theme) .update-progress-track {
  background: #3a3a3a;
}

.update-progress-text {
  font-size: 14px;
  font-weight: 600;
  color: var(--nav-btn-active-color, #007aff);
  min-width: 3em;
  text-align: right;
}

.update-actions {
  display: flex;
  flex-direction: column;
  gap: 10px;
  padding: 16px 18px 18px;
  flex-shrink: 0;
}

.btn-update-download {
  width: 100%;
  padding: 12px;
  border: none;
  border-radius: 12px;
  font-size: 16px;
  font-weight: 600;
  color: #fff;
  cursor: pointer;
  background: linear-gradient(90deg, #007aff, #00c6ff);
}

.btn-update-download:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}

.btn-update-close {
  width: 100%;
  padding: 11px;
  border-radius: 12px;
  font-size: 15px;
  cursor: pointer;
  border: 1px solid var(--border-color, #ccc);
  background: var(--bg-color-light, #f2f2f7);
  color: var(--text-color-light, #333);
}

:global(body.dark-theme) .btn-update-close {
  background: #2c2c2e;
  border-color: #444;
  color: #e0e0e0;
}
</style>
