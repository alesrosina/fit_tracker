import { createRouter, createWebHashHistory } from 'vue-router'
import ActivityList from '../components/ActivityList.vue'
import ActivityDetail from '../components/ActivityDetail.vue'
import SleepList from '../components/SleepList.vue'
import SleepDetail from '../components/SleepDetail.vue'

const routes = [
    { path: '/', component: ActivityList },
    { path: '/activity/:id', component: ActivityDetail, props: true },
    { path: '/sleep', component: SleepList },
    { path: '/sleep/:id', component: SleepDetail, props: true },
]

export default createRouter({
    history: createWebHashHistory(),
    routes,
})
