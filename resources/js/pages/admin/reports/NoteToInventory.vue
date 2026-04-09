<script setup>
import { Head } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import Card from 'primevue/card';
import DataTable from 'primevue/datatable';
import Column from 'primevue/column';

const props = defineProps({
    balanceData: Array, 
    notesData: Array,   // This must match the key in your controller
    totals: Object
});

const getRowClass = (row) => {
    if (!row || !row.details) return '';
    const text = row.details.toLowerCase();
    // Highlight "Total" rows and the "Tax Revenue" header
    if (text.includes('total') || text === 'tax revenue') return 'bg-gray-100 font-bold';
    return '';
};

const formatCurrency = (value) => {
    if (value === 0 || value === '-' || value === null || value === undefined || value === '') return '-';
    const num = parseFloat(value);
    if (isNaN(num)) return '-';
    return new Intl.NumberFormat('en-NG', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    }).format(num);
};

const breadcrumbs = [{ title: 'Reports' }, { title: 'Notes' }, { title: 'Inventory' }];
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head title="Notes to GPFS" />

        <div class="p-6">
            <Card class="report-card">
                <template #content>
                     <div class="flex justify-end mb-6">
                    <h1 class="text-lg font-bold border-b-2 border-black pb-1">
                    NOTES TO GPFS FOR THE YEAR ENDED 31ST DECEMBER 2025
                    </h1>
                    </div>

                    <table class="w-full border-collapse">
                        <thead>
                        <tr class="bg-black text-white uppercase">
                            <th class="border border-black p-2 w-2 text-center">Note</th>
                            <th class="border border-black p-2 text-left">Details</th>
                            <th class="border border-black p-2 w-16 text-center">Ref. Notes</th>
                            <th class="border border-black p-2 w-32 text-center">Actual<br>₦</th>
                            <th class="border border-black p-2 w-32 text-center">Budget 2025<br>₦</th>
                            <th class="border border-black p-2 w-32 text-center">Variance<br>₦</th>
                            <th class="border border-black p-2 w-32 text-center text-[9px]">2024<br>Actual ₦</th>
                        </tr>
                        </thead>
                        <tbody>
                        <tr v-for="(row, index) in notesData" :key="index" :class="getRowClass(row)">
                            <td class="border border-black p-1.5 text-center font-bold">
                            {{ row.note }}
                            </td>

                            <td class="border border-black p-1.5">
                            <div v-if="row.is_header" class="font-bold uppercase tracking-tight">
                                {{ row.title }}
                            </div>
                            <div v-else :class="{'pl-6': !row.is_subtotal, 'font-bold uppercase': row.is_subtotal}">
                                {{ row.details }}
                            </div>
                            </td>

                            <td class="border border-black p-1.5 text-center italic font-semibold">
                            {{ row.ref_notes }}
                            </td>

                            <td class="border border-black p-1.5 text-right font-medium">
                            {{ formatCurrency(row.actual_2025) }}
                            </td>
                            <td class="border border-black p-1.5 text-right font-medium">
                            {{ formatCurrency(row.budget_2025) }}
                            </td>
                            <td class="border border-black p-1.5 text-right font-medium">
                            {{ formatCurrency(row.variance_2025) }}
                            </td>
                            <td class="border border-black p-1.5 text-right font-medium">
                            {{ formatCurrency(row.actual_2024) }}
                            </td>
                        </tr>
                        </tbody>
                    </table>


                </template>
            </Card>
        </div>
    </AppLayout>
</template>