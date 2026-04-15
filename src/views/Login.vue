<template>
  <div class="login-container">
    <!-- 背景动画层 -->
    <div class="background-animation">
      <div class="particles" ref="particlesContainer"></div>
      <div class="sphere sphere-1"></div>
      <div class="sphere sphere-2"></div>
      <div class="sphere sphere-3"></div>
      <div class="sphere sphere-4"></div>
      <div class="sphere sphere-5"></div>
      <div class="sphere sphere-6"></div>
    </div>
    
    <div v-if="isLogin" id="login-box">
      <h2>登录</h2>
      <input type="text" v-model="loginForm.username" placeholder="用户名">
      <input type="password" v-model="loginForm.password" placeholder="密码">
      <button @click="handleLogin">登录</button>
      <p>没有账号？<a href="#" @click.prevent="isLogin = false">注册</a></p>
    </div>

    <div v-else id="register-box">
      <h2>注册</h2>
      <input type="text" v-model="registerForm.username" placeholder="用户名">
      <input type="password" v-model="registerForm.password" placeholder="密码">
      <button @click="handleRegister">注册</button>
      <p>已有账号？<a href="#" @click.prevent="isLogin = true">登录</a></p>
    </div>
  </div>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue';
import { useRouter } from 'vue-router';
import '../assets/css/login.css';
import { APP_CONFIG, commonFetch } from '../utils/config';
import { customAlert } from '../utils/modal';

const router = useRouter();
const isLogin = ref(true);
const particlesContainer = ref(null);

const loginForm = reactive({
  username: '',
  password: ''
});

const registerForm = reactive({
  username: '',
  password: ''
});

onMounted(() => {
  createParticles();
  checkLoginStatus();
});

const checkLoginStatus = () => {
  const userId = localStorage.getItem('user_id');
  const loginTimestamp = localStorage.getItem('login_timestamp');

  if (userId && loginTimestamp) {
    const now = Date.now();
    const ONE_DAY_IN_MS = 72 * 60 * 60 * 1000;
    if (now - parseInt(loginTimestamp) < ONE_DAY_IN_MS) {
      router.push('/home');
    } else {
      localStorage.removeItem('user_id');
      localStorage.removeItem('username');
      localStorage.removeItem('login_timestamp');
    }
  }
};

const handleLogin = async () => {
  if (!loginForm.username || !loginForm.password) {
    customAlert('请输入用户名和密码');
    return;
  }
  
  try {
    const data = await commonFetch(`${APP_CONFIG.API_BASE}/login.php`, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json'
      },
      body: JSON.stringify(loginForm)
    });
    
    if (data.success) {
      customAlert('登录成功');
      localStorage.setItem('user_id', data.user.id);
      localStorage.setItem('username', data.user.username);
      localStorage.setItem('login_timestamp', Date.now());
      
      if (loginForm.username === 'admin') {
        // Handle admin route if needed, for now redirect to home
        router.push('/home');
      } else {
        router.push('/home');
      }
    } else {
      customAlert(data.message || '登录失败');
    }
  } catch (err) {
    customAlert('网络错误');
  }
};

const handleRegister = () => {
  customAlert('未开放注册');
  // Original logic could be placed here if needed
};

const createParticles = () => {
  if (!particlesContainer.value) return;
  const particleCount = window.innerWidth < 480 ? 25 : window.innerWidth < 768 ? 35 : 50;
  
  for (let i = 0; i < particleCount; i++) {
    const particle = document.createElement('div');
    particle.className = 'particle';
    particle.style.left = Math.random() * 100 + '%';
    particle.style.top = (Math.random() * 50 + 50) + '%';
    particle.style.animationDelay = Math.random() * 2 + 's';
    particle.style.animationDuration = (3 + Math.random() * 4) + 's';
    const size = 2 + Math.random() * 3;
    particle.style.width = size + 'px';
    particle.style.height = size + 'px';
    particle.style.opacity = 0.4 + Math.random() * 0.4;
    particlesContainer.value.appendChild(particle);
  }
};
</script>

<style scoped>
.login-container {
  width: 100vw;
  height: 100vh;
  overflow: hidden;
  background: #e8eaf0;
  display: flex;
  align-items: center;
  justify-content: center;
  position: relative;
  transition: background-color 0.3s;
}

:global(body.dark-theme) .login-container {
  background: #2a2d35;
}
</style>
