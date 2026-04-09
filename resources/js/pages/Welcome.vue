<script setup>
// import FeaturesWidget from '@/components/landing/FeaturesWidget.vue';
// import FooterWidget from '@/components/landing/FooterWidget.vue';
import HeroWidget from '@/components/landing/HeroWidget.vue';
// import HighlightsWidget from '@/components/landing/HighlightsWidget.vue';
// import PricingWidget from '@/components/landing/PricingWidget.vue';
import TopbarWidget from '@/components/landing/TopbarWidget.vue';

// withDefaults(
//     defineProps<{
//         canRegister: boolean,
//     }>(),
//     {
//         canRegister: true,
//     },
// );
</script>

<template>
    <div class="bg-surface-0 dark:bg-surface-900">
        <div id="home" class="landing-wrapper overflow-hidden">
            <div
                class="relative mx-0 flex items-center justify-between px-6 py-6 md:mx-12 lg:static lg:mx-20 lg:px-20"
            >
                <TopbarWidget />
            </div>
            <HeroWidget />
            <!-- <FeaturesWidget /> -->
            <!-- <HighlightsWidget /> -->
            <!-- <PricingWidget /> -->
            <!-- <FooterWidget /> -->
        </div>
    </div>
</template>
