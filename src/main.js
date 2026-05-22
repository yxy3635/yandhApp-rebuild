import { createApp } from 'vue'
import './style.css'
import './assets/css/home.css'
import App from './App.vue'
import router from './router'
import { setupPlusBackButton } from './utils/plus-backbutton'
import AppHeader from './components/AppHeader.vue'
import LoadingSpinner from './components/LoadingSpinner.vue'

const app = createApp(App)
app.use(router)
app.component('AppHeader', AppHeader)
app.component('LoadingSpinner', LoadingSpinner)
app.mount('#app')

setupPlusBackButton()
