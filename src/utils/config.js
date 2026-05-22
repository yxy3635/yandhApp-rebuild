import defaultAvatar from '../assets/img/default-avatar.png';

// 主要的后端配置
export const APP_CONFIG = {
  // 服务器基础地址
  SERVER_BASE: 'http://38.207.133.8',
  
  // API 接口地址
  API_BASE: 'http://38.207.133.8/api',
  
  // 静态资源地址
  AVATAR_BASE: 'http://38.207.133.8/',
  
  // GIF 相关地址
  GIF_BASE: 'http://38.207.133.8/gif/',
  USER_EMOJI_BASE: 'http://38.207.133.8/gif/user_uploads/',
  
  // 其他服务地址
  VERSION_CHECK_URL: 'http://38.207.133.8/appVersionCheck.php'
};

// 通用网络请求函数
export async function commonFetch(url, options = {}) {
  try {
    // 发送实际请求
    const response = await fetch(url, options);
    const data = await response.json();

    // 如果用户已登录，更新在线状态
    const userId = localStorage.getItem('user_id');
    const updateOnlineUrl = `${APP_CONFIG.API_BASE}/update_online_status.php`;
    
    if (userId && url !== updateOnlineUrl) {
      // 避免在更新在线状态的请求中再次更新在线状态
      fetch(updateOnlineUrl, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `user_id=${userId}`
      }).catch(error => {
        console.error('更新在线状态失败:', error);
      });
    }

    return data;
  } catch (error) {
    console.error('请求失败:', error, '请求URL:', url);
    throw error;
  }
}

export function getAvatarUrl(url) {
  if (!url) return defaultAvatar;
  if (url.startsWith('http')) return url;
  if (url.includes('default-avatar')) return defaultAvatar;
  // Handle relative paths from server
  return url.startsWith('/') ? `${APP_CONFIG.SERVER_BASE}${url}` : `${APP_CONFIG.SERVER_BASE}/${url}`;
}
