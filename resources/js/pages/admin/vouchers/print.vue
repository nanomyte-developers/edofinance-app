<script setup lang="ts">

import PrintableVoucher from '@/components/PrintableVoucher.vue'; // Adjust path to your component
import AppLayout from '@/layouts/AppLayout.vue';
import { Head } from '@inertiajs/vue3';

const props = defineProps({
    voucher: {
        type: Object,
        required: true,
    },
});

const breadcrumbs = [
    { title: 'Vouchers', href: '/vouchers' },
    { title: 'Print Voucher', href: '#' },
];
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head :title="`Print Voucher - ${voucher.voucher_number}`" />
        <PrintableVoucher :voucher="voucher" />
    </AppLayout>
</template>