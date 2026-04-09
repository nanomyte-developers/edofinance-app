<script setup>
import AppFooter from '@/layouts/AppFooter.vue';
import AppSidebar from '@/layouts/AppSidebar.vue';
import AppTopbar from '@/layouts/AppTopbar.vue';
import { useLayout } from '@/layouts/composables/layout';
import Toast from 'primevue/toast'; // Assuming Toast is imported for the template
import { computed, ref, watch, watchEffect, onMounted } from 'vue'; // Added watchEffect

// --- FIX: Explicitly define the 'title' prop to eliminate the Vue warning ---
const props = defineProps({
    title: {
        type: String,
        default: 'Application Dashboard',
    },
   
});


onMounted(() => {
    
})
// --- Good practice: Update the document title based on the prop ---
watchEffect(() => {
    if (props.title) {
        document.title = props.title;
    }
});
// ---------------------------------------------------------------------------

const { layoutConfig, layoutState, isSidebarActive } = useLayout();

const outsideClickListener = ref(null);

watch(isSidebarActive, (newVal) => {
    if (newVal) {
        bindOutsideClickListener();
    } else {
        unbindOutsideClickListener();
    }
});

const containerClass = computed(() => {
    return {
        'layout-overlay': layoutConfig.menuMode === 'overlay',
        'layout-static': layoutConfig.menuMode === 'static',
        'layout-static-inactive':
            layoutState.staticMenuDesktopInactive &&
            layoutConfig.menuMode === 'static',
        'layout-overlay-active': layoutState.overlayMenuActive,
        'layout-mobile-active': layoutState.staticMenuMobileActive,
    };
});

function bindOutsideClickListener() {
    if (!outsideClickListener.value) {
        outsideClickListener.value = (event) => {
            if (isOutsideClicked(event)) {
                layoutState.overlayMenuActive = false;
                layoutState.staticMenuMobileActive = false;
                layoutState.menuHoverActive = false;
            }
        };
        document.addEventListener('click', outsideClickListener.value);
    }
}

function unbindOutsideClickListener() {
    if (outsideClickListener.value) {
        // Correctly pass the function reference to removeEventListener
        document.removeEventListener('click', outsideClickListener.value);
        outsideClickListener.value = null;
    }
}

function isOutsideClicked(event) {
    const sidebarEl = document.querySelector('.layout-sidebar');
    const topbarEl = document.querySelector('.layout-menu-button');

    return !(
        sidebarEl?.isSameNode(event.target) ||
        sidebarEl?.contains(event.target) ||
        topbarEl?.isSameNode(event.target) ||
        topbarEl?.contains(event.target)
    );
}




</script>

<template>
    <div class="layout-wrapper" :class="containerClass">
        <app-topbar></app-topbar>
        <app-sidebar></app-sidebar>
        <div class="layout-main-container">
            <div class="layout-main">
                <slot />
            </div>
            <app-footer></app-footer>
        </div>
        <div class="layout-mask animate-fadein"></div>
    </div>
    <Toast />
</template>
