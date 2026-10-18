import { createApp } from 'vue'
import { t, n } from '@nextcloud/l10n'
import App from './App.vue'
import router from './router/index.js'
import './styles/tokens.css'

const app = createApp(App)
// Expose the l10n helpers to templates; calls keep the explicit app id
// (t('fit_tracker', '…')) so the translation extractor picks them up.
app.config.globalProperties.t = t
app.config.globalProperties.n = n
app.use(router)
app.mount('#fit-tracker-app')
