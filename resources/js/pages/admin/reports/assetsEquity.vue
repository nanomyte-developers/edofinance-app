<script setup>
import { Head } from '@inertiajs/vue3';
import Card from 'primevue/card';
import DataTable from 'primevue/datatable';
import Column from 'primevue/column';
import AppLayout from '@/layouts/AppLayout.vue';

const props = defineProps({
    equityData: { type: Array, default: () => [] }
});

const formatCurrency = (val) => {
    if (val === 0 || !val) return '-';
    const isNeg = val < 0;
    const formatted = new Intl.NumberFormat('en-NG', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2
    }).format(Math.abs(val));
    return isNeg ? `(${formatted})` : formatted;
};

const rowClass = (data) => {
    if (data.isHeader) return 'bg-audit text-white font-bold uppercase';
    if (data.isSubHeader) return 'font-bold bg-gray-50 italic';
    if (data.isTotal) return 'font-bold border-y border-black bg-gray-100';
    if (data.isFinal) return 'bg-green-50 font-black border-y-2 border-green-900 text-green-900 uppercase';
    return '';
};
</script>

<template>
    <AppLayout>
        <Head title="Statement of Changes in Net Assets/Equity" />

        <div class="max-w-full mx-auto py-6 px-4">
            <Card class="shadow-none border-0">
                <template #content>
                    <div class="text-center mb-6">
                        <div class="flex justify-center mb-2">
                            <img src="/logo-wmfROXvK.jpg" alt="Logo" class="w-16 h-16 object-contain" />
                        </div>
                        <h2 class="text-sm font-bold text-green-700 m-0 uppercase">EDO STATE AUDITED GENERAL PURPOSE FINANCIAL STATEMENTS (GPFS)</h2>
                        <h3 class="text-xs font-bold text-green-700 m-0 uppercase">FOR THE YEAR ENDED 31ST DECEMBER, 2025</h3>

                        <div class="mt-4 border-t-2 border-yellow-500 pt-2 text-center">
                            <h1 class="text-md font-bold uppercase">CONSOLIDATED STATEMENT OF CHANGES IN NET ASSETS/EQUITY</h1>
                            <p class="text-sm font-bold uppercase">FOR THE YEAR ENDED 31ST DECEMBER 2025</p>
                        </div>
                    </div>

                    <DataTable :value="equityData" :rowClass="rowClass" class="p-datatable-sm custom-equity-table border">
                        <Column field="description" header="DESCRIPTION" class="border-r text-[10px] py-3 w-5/12">
                            <template #body="slotProps">
                                <span :class="{'ml-4': !slotProps.data.isHeader && !slotProps.data.isTotal && !slotProps.data.isFinal}">
                                    {{ slotProps.data.description }}
                                </span>
                            </template>
                        </Column>

                        <Column field="reserves" header=" REVALUATION RESERVE" class="text-right border-r font-mono text-[11px]">
                            <template #header><div class="w-full text-center">(N)</div></template>
                            <template #body="sp">{{ formatCurrency(sp.data.reserves) }}</template>
                        </Column>
                        <Column field="revaluation_reserve" header="REVALUATION RESERVE" class="text-right border-r font-mono text-[11px]">
                            <template #header><div class="w-full text-center">(N)</div></template>
                            <template #body="sp">{{ formatCurrency(sp.data.revaluation_reserve) }}</template>
                        </Column>

                        <Column field="accumulated" header="ACCUMULATED SURPLUS/ (DEFICIT)" class="text-right border-r font-mono text-[11px]">
                            <template #header><div class="w-full text-center">(N)</div></template>
                            <template #body="sp">{{ formatCurrency(sp.data.accumulated) }}</template>
                        </Column>

                        <Column field="total" header="TOTAL" class="text-right font-mono text-[11px] font-bold">
                            <template #header><div class="w-full text-center">(N)</div></template>
                            <template #body="sp">{{ formatCurrency(sp.data.total) }}</template>
                        </Column>
                    </DataTable>

                    <div class="mt-12 flex justify-start">
                        <div class="text-left">
                            <div class="mb-1 h-12 flex items-end">
                                <span class="italic font-serif text-blue-800 text-xl opacity-80 pl-4 select-none">JuliusAnelu</span>
                            </div>
                            <div class="border-t border-black pt-2 pr-12">
                                <p class="font-bold text-[11px] leading-tight">
                                    Dr. Julius O. Anelu (FCA, FCTI)<br/>
                                    PS/Accountant General,<br/>
                                    Edo State.<br/>
                                    <!-- 29th May, 2025 -->
                                </p>
                            </div>
                        </div>
                    </div>

                    <div class="mt-8 pt-2 border-t border-black flex justify-between text-[10px] font-bold italic">
                        <span>www.edostate.gov.ng</span>
                        <span>5</span>
                        <span class="uppercase">Audited Report</span>
                    </div>
                </template>
            </Card>
        </div>
    </AppLayout>
</template>

<style scoped>
:deep(.bg-audit) { background-color: #064e3b !important; }
:deep(.custom-equity-table td), :deep(.custom-equity-table th) {
    border: 0.5px solid #000 !important;
    padding: 6px 10px !important;
}
.font-mono { font-family: 'Courier New', monospace; font-variant-numeric: tabular-nums; }
</style>
