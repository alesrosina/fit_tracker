<template>
    <div class="loading-skeleton" role="status" :aria-label="t('fit_tracker', 'Loading')">
        <template v-if="variant === 'detail'">
            <div class="loading-skeleton__header">
                <div class="ft-skeleton loading-skeleton__avatar" />
                <div class="loading-skeleton__lines">
                    <div class="ft-skeleton loading-skeleton__line loading-skeleton__line--title" />
                    <div class="ft-skeleton loading-skeleton__line" />
                </div>
            </div>
            <div class="loading-skeleton__stats">
                <div v-for="i in 6" :key="i" class="ft-skeleton loading-skeleton__stat" />
            </div>
            <div class="ft-skeleton loading-skeleton__block" />
        </template>
        <template v-else>
            <div class="loading-skeleton__row">
                <div class="ft-skeleton loading-skeleton__panel" />
                <div class="ft-skeleton loading-skeleton__panel" />
            </div>
            <div class="loading-skeleton__grid">
                <div v-for="i in 6" :key="i" class="ft-skeleton loading-skeleton__card" />
            </div>
        </template>
    </div>
</template>

<script>
export default {
    name: 'LoadingSkeleton',
    props: {
        variant: { type: String, default: 'list' }, // 'list' | 'detail'
    },
}
</script>

<style scoped>
.loading-skeleton {
    display: flex;
    flex-direction: column;
    gap: var(--ft-gap-5);
}
.loading-skeleton__row {
    display: flex;
    flex-wrap: wrap;
    gap: var(--ft-gap-5);
}
.loading-skeleton__panel {
    flex: 1;
    min-width: 280px;
    height: 260px;
    border-radius: var(--border-radius-container);
}
.loading-skeleton__grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
    gap: var(--ft-gap-4);
}
.loading-skeleton__card {
    height: calc(var(--default-grid-baseline) * 22);
}
.loading-skeleton__header {
    display: flex;
    align-items: center;
    gap: var(--ft-gap-4);
}
.loading-skeleton__avatar {
    width: calc(var(--default-grid-baseline) * 12);
    height: calc(var(--default-grid-baseline) * 12);
}
.loading-skeleton__lines {
    flex: 1;
    display: flex;
    flex-direction: column;
    gap: var(--ft-gap-2);
}
.loading-skeleton__line {
    height: var(--ft-gap-4);
    width: 40%;
    border-radius: var(--border-radius-small);
}
.loading-skeleton__line--title {
    height: var(--ft-gap-6);
    width: 60%;
}
.loading-skeleton__stats {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(130px, 1fr));
    gap: var(--ft-gap-3);
}
.loading-skeleton__stat {
    height: calc(var(--default-grid-baseline) * 17);
}
.loading-skeleton__block {
    height: 400px;
    border-radius: var(--border-radius-container);
}
</style>
