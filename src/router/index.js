import { createRouter, createWebHashHistory } from 'vue-router'
import DashboardPage from '../components/DashboardPage.vue'
import ActivityDetail from '../components/ActivityDetail.vue'
import SleepList from '../components/SleepList.vue'
import SleepDetail from '../components/SleepDetail.vue'

const routes = [
    // Dashboard for all activities, or one sport with ?sport=…; timeframe in the query (timeframe.js)
    { path: '/', component: DashboardPage },
    { path: '/activity/:id', component: ActivityDetail, props: true },
    { path: '/sleep', component: SleepList },
    { path: '/sleep/:id', component: SleepDetail, props: true },
    // Insights pages pull in Chart.js/Leaflet code the dashboard doesn't need
    { path: '/stats', component: () => import('../components/StatsPage.vue') },
    { path: '/records', component: () => import('../components/RecordsPage.vue') },
    { path: '/heatmap', component: () => import('../components/HeatmapPage.vue') },
    { path: '/gear', component: () => import('../components/GearPage.vue') },
    { path: '/goals', component: () => import('../components/GoalsPage.vue') },
]

export default createRouter({
    history: createWebHashHistory(),
    routes,
})
