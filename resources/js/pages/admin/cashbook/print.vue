<script setup>
import PrintableCashbook from '@/components/PrintableCashbook.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { Head } from '@inertiajs/vue3';

const props = defineProps({
    cashbook: {
        type: Object,
        required: true,
    },
    receipts: {
        type: Array,
        default: () => [],
    },
    payments: {
        type: Array,
        default: () => [],
    },
});

const breadcrumbs = [
    { title: 'Cashbooks', href: '/cashbooks' },
    { title: 'Print Cashbook', href: '#' },
];
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head
            :title="`Print Cashbook - ${cashbook.month_name} ${cashbook.year}`"
        />
        <PrintableCashbook
            :cashbook="cashbook"
            :receipts="receipts"
            :payments="payments"
        />
    </AppLayout>
</template>
