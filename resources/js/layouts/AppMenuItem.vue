<script setup>
import { useLayout } from '@/layouts/composables/layout';
import { onBeforeMount, ref, watch } from 'vue';
import { usePage } from '@inertiajs/vue3';

const page = usePage();

const { layoutState, setActiveMenuItem, toggleMenu } = useLayout();

const props = defineProps({
    item: {
        type: Object,
        default: () => ({})
    },
    index: {
        type: Number,
        default: 0
    },
    root: {
        type: Boolean,
        default: true
    },
    parentItemKey: {
        type: String,
        default: null
    }
});

const isActiveMenu = ref(false);
const itemKey = ref(null);

onBeforeMount(() => {
    itemKey.value = props.parentItemKey ? props.parentItemKey + '-' + props.index : String(props.index);

    const activeItem = layoutState.activeMenuItem;

    isActiveMenu.value = activeItem === itemKey.value || activeItem ? activeItem.startsWith(itemKey.value + '-') : false;
});

watch(
    () => layoutState.activeMenuItem,
    (newVal) => {
        isActiveMenu.value = newVal === itemKey.value || (newVal ? newVal.startsWith(itemKey.value + '-') : false);
    }
);

function itemClick(event, item) {
    if (item.disabled) {
        event.preventDefault();
        return;
    }

    if ((item.to || item.url) && (layoutState.staticMenuMobileActive || layoutState.overlayMenuActive)) {
        toggleMenu();
    }

    if (item.command) {
        item.command({ originalEvent: event, item: item });
    }

    const foundItemKey = item.items ? (isActiveMenu.value ? props.parentItemKey : itemKey) : itemKey.value;

    setActiveMenuItem(foundItemKey);
}

function checkActiveRoute(item) {
    return page.url === item.to;
}
</script>

<template>
    <li :class="{ 'layout-root-menuitem': root, 'active-menuitem': isActiveMenu }">
        <div v-if="root && item.visible !== false" class="layout-menuitem-root-text">{{ item.label }}</div>
        <a v-if="(!item.to || item.items) && item.visible !== false" 
           :href="item.url || item.to" 
           @click="itemClick($event, item, index)" 
           :class="item.class" 
           :target="item.target" 
           tabindex="0">
            <i :class="item.icon" class="layout-menuitem-icon"></i>
            <span class="layout-menuitem-text">{{ item.label }}</span>
            <i class="pi pi-fw pi-angle-down layout-submenu-toggler" v-if="item.items"></i>
        </a>
        <a v-if="item.to && !item.items && item.visible !== false" 
           @click="itemClick($event, item, index)" 
           :class="[item.class, { 'active-route': checkActiveRoute(item) }]" 
           tabindex="0" 
           :href="item.to">
            <i :class="item.icon" class="layout-menuitem-icon"></i>
            <span class="layout-menuitem-text">{{ item.label }}</span>
            <i class="pi pi-fw pi-angle-down layout-submenu-toggler" v-if="item.items"></i>
        </a>
        <Transition v-if="item.items && item.visible !== false" name="layout-submenu">
            <ul v-show="root ? true : isActiveMenu" class="layout-submenu">
                <app-menu-item v-for="(child, i) in item.items" :key="child" :index="i" :item="child" :parentItemKey="itemKey" :root="false"></app-menu-item>
            </ul>
        </Transition>
    </li>
</template>

<!-- <style lang="scss" scoped>
.layout-root-menuitem {
    margin-bottom: 0.5rem;
}

.layout-menuitem-root-text {
    padding: 1rem 1.5rem 0.5rem 1.5rem;
    font-weight: 600;
    color: var(--p-text-color-secondary);
    font-size: 0.875rem;
    text-transform: uppercase;
}

.layout-menuitem-icon {
    margin-right: 0.5rem;
}

.layout-menuitem-text {
    flex-grow: 1;
}

.layout-submenu-toggler {
    margin-left: auto;
}

a {
    display: flex;
    align-items: center;
    padding: 0.75rem 1.5rem;
    border-radius: 12px;
    color: var(--p-text-color);
    transition: background-color 0.2s;
    text-decoration: none;
    cursor: pointer;
}

a:hover {
    background-color: var(--p-surface-hover);
}

.active-route, .active-menuitem a {
    background-color: var(--p-highlight-bg);
    color: var(--p-highlight-color-text);
}

.layout-submenu {
    list-style: none;
    margin: 0;
    padding: 0;
    padding-left: 1rem;
}

/* Transition styles */
.layout-submenu-enter-active,
.layout-submenu-leave-active {
    transition: all 0.3s ease;
}

.layout-submenu-enter-from,
.layout-submenu-leave-to {
    opacity: 0;
    max-height: 0;
}

.layout-submenu-enter-to,
.layout-submenu-leave-from {
    opacity: 1;
    max-height: 500px;
}
</style> -->