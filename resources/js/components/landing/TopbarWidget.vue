<script setup>
import { usePage } from '@inertiajs/vue3';
import logo from '../../../../public/images/logo.jpg';

// Use page props to access auth
const page = usePage();
const user = page.props.auth.user;

// Define props for canRegister if needed
const props = defineProps({
    canRegister: {
        type: Boolean,
        default: true,
    },
});

function smoothScroll(id) {
    document.body.click();
    const element = document.getElementById(id);
    if (element) {
        element.scrollIntoView({
            behavior: 'smooth',
            block: 'start',
        });
    }
}
</script>

<template>
    <a class="flex items-center" href="/">
        <img :src="logo" alt="EDFS Logo" class="mr-2 h-12 w-auto" />
        <span
            class="text-surface-900 dark:text-surface-0 mr-20 text-2xl leading-normal font-medium"
            >EDFS</span
        >
    </a>
    <Button
        class="lg:!hidden"
        text
        severity="secondary"
        rounded
        v-styleclass="{
            selector: '@next',
            enterFromClass: 'hidden',
            enterActiveClass: 'animate-scalein',
            leaveToClass: 'hidden',
            leaveActiveClass: 'animate-fadeout',
            hideOnOutsideClick: true,
        }"
    >
        <i class="pi pi-bars !text-2xl"></i>
    </Button>
    <div
        class="bg-surface-0 dark:bg-surface-900 rounded-border absolute top-full left-0 z-20 hidden w-full grow items-center justify-between px-12 lg:static lg:flex lg:px-0"
    >
        <ul
            class="m-0 flex cursor-pointer list-none flex-col gap-8 p-0 select-none lg:flex-row lg:items-center"
        >
            <li>
                <a
                    @click="smoothScroll('hero')"
                    class="text-surface-900 dark:text-surface-0 px-0 py-4 text-xl font-medium"
                >
                    <span>Home</span>
                </a>
            </li>
            <li>
                <a
                    @click="smoothScroll('features')"
                    class="text-surface-900 dark:text-surface-0 px-0 py-4 text-xl font-medium"
                >
                    <!-- <span>Features</span> -->
                </a>
            </li>
            <li>
                <a
                    @click="smoothScroll('highlights')"
                    class="text-surface-900 dark:text-surface-0 px-0 py-4 text-xl font-medium"
                >
                    <!-- <span>Highlights</span> -->
                </a>
            </li>
            <li>
                <a
                    @click="smoothScroll('pricing')"
                    class="text-surface-900 dark:text-surface-0 px-0 py-4 text-xl font-medium"
                >
                    <!-- <span>Pricing</span> -->
                </a>
            </li>
        </ul>
        <div
            class="border-surface mt-4 flex gap-2 border-t py-4 lg:mt-0 lg:border-t-0 lg:py-0"
        >
            <!-- Authenticated User - Show Dashboard Button -->
            <Button
                v-if="user"
                label="Dashboard"
                icon="pi pi-home"
                severity="primary"
                raised
                as="a"
                :href="'/dashboard'"
                rounded
            ></Button>

            <!-- Guest User - Show Login/Register Buttons -->
            <template v-else>
                <Button
                    label="Login"
                    icon="pi pi-sign-in"
                    text
                    severity="primary"
                    as="a"
                    :href="'/login'"
                    rounded
                ></Button>
                <!-- <Button
                    v-if="canRegister"
                    label="Register"
                    icon="pi pi-user-plus"
                    severity="primary"
                    outlined
                    as="a"
                    :href="'/register'"
                    rounded
                ></Button> -->
            </template>
        </div>
    </div>
</template>
