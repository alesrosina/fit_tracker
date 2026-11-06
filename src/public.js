import { createApp } from 'vue'
import { t, n } from '@nextcloud/l10n'
import { loadState } from '@nextcloud/initial-state'
import PublicActivity from './components/PublicActivity.vue'
import { useUnits } from './store/settings.js'
import './styles/tokens.css'

// Public read-only page of a shared activity (PublicController.php)
const share = loadState('fit_tracker', 'share')
useUnits(share.units)

const app = createApp(PublicActivity, { share })
app.config.globalProperties.t = t
app.config.globalProperties.n = n
app.mount('#fit-tracker-public')
