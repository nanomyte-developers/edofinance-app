<script setup lang="ts">
import PrintableRemittance from '@/components/PrintableRemittance.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, usePage } from '@inertiajs/vue3';

const props = defineProps({
    remittance: {
        type: Object,
        required: true,
    },
});

const page = usePage();

const breadcrumbs = [
    { title: 'Remittances', href: '/remittances' },
    {
        title: `Print Remittance - ${props.remittance.receipt_number}`,
        href: '#',
    },
];
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head :title="`Print Remittance - ${remittance.receipt_number}`" />
        <PrintableRemittance :remittance="remittance" />
    </AppLayout>
</template>
