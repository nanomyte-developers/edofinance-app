<script setup>
import { Head } from '@inertiajs/vue3';
import Card from 'primevue/card';
import DataTable from 'primevue/datatable';
import Column from 'primevue/column';
import AppLayout from '@/layouts/AppLayout.vue';

const props = defineProps({
    balanceData: { type: Array, default: () => [] }
});

const formatCurrency = (val) => {
    if (val === 0 || val === null || val === undefined) return '-';
    const isNeg = val < 0;
    const formatted = new Intl.NumberFormat('en-NG', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2
    }).format(Math.abs(val));
    return isNeg ? `(${formatted})` : formatted;
};

const rowClass = (data) => {
    if (data.isHeader) return 'bg-audit text-white font-bold uppercase';
    if (data.isTotal) return 'font-bold border-y border-black bg-gray-100';
    return '';
};
</script>

<template>
    <AppLayout>
        <Head title="Cash and Bank Balances Held by Treasury" />

        <div class="max-w-full mx-auto py-6 px-4">
            <Card class="shadow-none border-0">
                <template #content>
                    <div class="text-center mb-6">
                        <div class="flex justify-center mb-2">
                            <img src="/logo-wmfROXvK.jpg" alt="Logo" class="w-16 h-16 object-contain" />
                        </div>
                        <h2 class="text-sm font-bold text-green-700 m-0 uppercase">EDO STATE AUDITED GENERAL PURPOSE FINANCIAL STATEMENTS (GPFS)</h2>
                        <h3 class="text-xs font-bold text-green-700 m-0 uppercase">FOR THE YEAR ENDED 31ST DECEMBER, 2024</h3>

                        <div class="mt-4 border-t-2 border-yellow-500 pt-2 text-center">
                            <h1 class="text-md font-bold uppercase">CASH AND BANK BALANCES HELD BY THE TREASURY</h1>
                            <p class="text-sm font-bold uppercase">AS AT 31ST DECEMBER 2024 (CONTD.)</p>
                        </div>
                    </div>

                    <DataTable :value="balanceData" :rowClass="rowClass" class="p-datatable-sm custom-table border">
                        <Column field="sno" header="S/NO." class="border-r text-[5px] w-2 text-center" />

                        <Column field="economic_code" header="ECONOMIC CODE" class="border-r text-[5px] w-16 text-center" />

                        <Column field="description" header="DESCRIPTION" class="border-r text-[20px] py-2 w-10/32">
                            <template #body="slotProps">
                                <span :class="{'font-bold': slotProps.data.isTotal}">
                                    {{ slotProps.data.description }}
                                </span>
                            </template>
                        </Column>

                        <Column field="current_year" header="2024 (N)" class="text-right border-r font-mono text-[11px]">
                            <template #header><div class="w-full text-center">2024<br/>(N)</div></template>
                            <template #body="sp">{{ formatCurrency(sp.data.current_year) }}</template>
                        </Column>

                        <Column field="prev_year" header="2023 (N)" class="text-right font-mono text-[11px]">
                            <template #header><div class="w-full text-center">2023<br/>(N)</div></template>
                            <template #body="sp">{{ formatCurrency(sp.data.prev_year) }}</template>
                        </Column>
                    </DataTable>

                    <div class="mt-8 pt-2 border-t border-black flex justify-between text-[10px] font-bold italic">
                        <span>www.edostate.gov.ng</span>
                        <span>34</span>
                        <span class="uppercase">Audited Report</span>
                    </div>
                </template>
            </Card>
        </div>
    </AppLayout>
</template>

<style scoped>
:deep(.bg-audit) { background-color: #064e3b !important; }
:deep(.custom-table td), :deep(.custom-table th) {
    border: 0.5px solid #000 !important;
    padding: 4px 8px !important;
}
.font-mono { font-family: 'Courier New', monospace; font-variant-numeric: tabular-nums; }
</style>
