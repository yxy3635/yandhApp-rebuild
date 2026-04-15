import { createApp } from 'vue'
import './style.css'
import './assets/css/home.css'
import App from './App.vue'
import router from './router'
import { setupPlusBackButton } from './utils/plus-backbutton'

const app = createApp(App)
app.use(router)
app.mount('#app')

setupPlusBackButton()
