import { createRouter, createWebHashHistory } from 'vue-router'

const routes = [
  /* Hash 模式：路由在 # 之后（如 .../index.html#/home），不依赖服务器路径，
   * 子目录部署 / HBuilder 内置浏览器 / file:// 真机包均不会出现 History 白屏 */
  {
    path: '/',
    name: 'Login',
    component: () => import('../views/Login.vue')
  },
  {
    path: '/home',
    name: 'Home',
    component: () => import('../views/Home.vue')
  },
  {
    path: '/interaction',
    name: 'Interaction',
    component: () => import('../views/Interaction.vue')
  },
  {
    path: '/anniversary',
    name: 'Anniversary',
    component: () => import('../views/Anniversary.vue')
  },
  {
    path: '/profile',
    name: 'Profile',
    component: () => import('../views/Profile.vue')
  },
  {
    path: '/ai',
    name: 'AiPage',
    component: () => import('../views/AiPage.vue')
  },
  {
    path: '/post',
    name: 'Post',
    component: () => import('../views/Post.vue')
  },
  {
    path: '/detail',
    name: 'Detail',
    component: () => import('../views/Detail.vue')
  },
  {
    path: '/diary',
    name: 'Diary',
    component: () => import('../views/Diary.vue')
  },
  {
    path: '/diary/detail',
    name: 'DiaryDetail',
    component: () => import('../views/DiaryDetail.vue')
  },
  {
    path: '/diary/edit',
    name: 'DiaryEdit',
    component: () => import('../views/DiaryEdit.vue')
  },
  {
    path: '/userlist',
    name: 'UserList',
    component: () => import('../views/UserList.vue')
  },
  {
    path: '/chat',
    name: 'Chat',
    component: () => import('../views/Chat.vue')
  },
  {
    path: '/gomoku',
    name: 'Gomoku',
    component: () => import('../views/Gomoku.vue')
  },
  {
    path: '/gomoku-cracked',
    name: 'GomokuCracked',
    component: () => import('../views/GomokuCracked.vue')
  },
  {
    path: '/shoot-guess',
    name: 'ShootGuess',
    component: () => import('../views/ShootGuess.vue')
  },
  {
    path: '/draw-guess',
    name: 'DrawGuess',
    component: () => import('../views/DrawGuess.vue')
  },
  {
    path: '/pindou',
    name: 'Pindou',
    component: () => import('../views/Pindou.vue')
  }
]

const router = createRouter({
  history: createWebHashHistory(),
  routes,
  scrollBehavior(to, from, savedPosition) {
    if (savedPosition) {
      return savedPosition
    } else {
      return { top: 0 }
    }
  }
})

export default router
