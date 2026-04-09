<!-- PrintSchedule.vue -->
<script setup>
import PrintableSchedule from '@/components/PrintableSchedule.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { Head } from '@inertiajs/vue3';

const props = defineProps({
    schedule: {
        type: Object,
        required: true,
    },
    administrativeCodes: {
        type: Array,
        default: () => [],
    },
    administrativeSectorCodes: {
        type: Array,
        default: () => [],
    },
    economyCodes: {
        type: Array,
        default: () => [],
    },
    economyCodeItems: {
        type: Array,
        default: () => [],
    },
});

const breadcrumbs = [
    { title: 'Schedules', href: '/schedules' },
    { title: 'Print Schedule', href: '#' },
];
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head :title="`Print Schedule - ${schedule.schedule_number}`" />
        <PrintableSchedule
            :schedule="schedule"
            :administrativeCodes="administrativeCodes"
            :administrativeSectorCodes="administrativeSectorCodes"
            :economyCodes="economyCodes"
            :economyCodeItems="economyCodeItems"
        />
    </AppLayout>
</template>
