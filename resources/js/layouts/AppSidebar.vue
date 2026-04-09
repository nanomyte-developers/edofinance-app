<script setup>
import { useLayout } from '@/layouts/composables/layout';
import AppMenu from './AppMenu.vue';

const { layoutConfig, layoutState } = useLayout();
</script>

<template>
    <div class="layout-sidebar" :class="{
        'layout-sidebar-active': layoutState.overlayMenuActive || layoutState.staticMenuMobileActive
    }">
        <app-menu></app-menu>
    </div>
</template>

<style lang="scss" scoped>
.layout-sidebar {
    position: fixed;
    width: 20rem;
    height: calc(100vh - 8rem);
    z-index: 999;
    overflow-y: auto;
    user-select: none;
    top: 6rem;
    left: 2rem;
    transition:
        transform var(--layout-section-transition-duration),
        left var(--layout-section-transition-duration);
    background-color: var(--surface-overlay);
    border-radius: var(--content-border-radius);
    padding: 0.5rem 1.5rem;
}

/* Layout mode styles */
.layout-overlay .layout-sidebar {
    left: -20rem;
}

.layout-overlay-active .layout-sidebar {
    left: 2rem;
}

.layout-static .layout-sidebar {
    left: 2rem;
}

.layout-static-inactive .layout-sidebar {
    left: -20rem;
}

.layout-mobile-active .layout-sidebar {
    left: 2rem;
}

/* Mobile responsive */
@media screen and (max-width: 991px) {
    .layout-sidebar {
        left: -20rem;
        top: 0;
        height: 100vh;
        border-radius: 0;
    }
    
    .layout-mobile-active .layout-sidebar {
        left: 0;
    }
}
</style>
