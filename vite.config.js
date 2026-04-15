import { defineConfig } from 'vite'
import vue from '@vitejs/plugin-vue'

// https://vite.dev/config/
// HBuilder 等本地 file:// 打包必须用相对路径，否则 /YandH/... 会指向磁盘根目录，JS/CSS 全部 404。
// 若需部署到网站子目录（如 /YandH/rebuild/dist/），打包前改为 base: '/YandH/rebuild/dist/' 再 npm run build。
export default defineConfig({
  base: './',
  plugins: [vue()],
})
