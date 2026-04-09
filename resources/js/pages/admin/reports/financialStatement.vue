<script setup>
import { Head } from '@inertiajs/vue3';

// PrimeVue Components
import Card from 'primevue/card';
import DataTable from 'primevue/datatable';
import Column from 'primevue/column';
import ColumnGroup from 'primevue/columngroup';
import Row from 'primevue/row';
import Button from 'primevue/button';
import AppLayout from '@/layouts/AppLayout.vue';

// --- PROPS DEFINITION ---
const props = defineProps({
    financialData: {
        type: Array,
        default: () => []
    }
});

// --- Currency Formatter ---
// Following standard accounting: negative numbers in parentheses
const formatCurrency = (val) => {
    if (val === 0 || val === null || val === undefined) return '-';
    const formatted = new Intl.NumberFormat('en-NG', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2
    }).format(Math.abs(val));
    return val < 0 ? `(${formatted})` : formatted;
};

// Row styling for visual hierarchy matching the audited report
const rowClass = (data) => {
    if (data.isHeader) return 'bg-gray-100 font-bold tracking-widest text-slate-800';
    if (data.isTotal) return 'bg-gray-50 font-bold border-y border-black';
    if (data.isFinal) return 'bg-green-50 font-black border-y-2 border-green-900 text-green-900 uppercase';
    return '';
};

const printReport = () => window.print();

const breadcrumbs = [{ title: 'Finance' }, { title: 'Audited GPFS' }];
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">

        <Head title="Audited Financial Statement" />

        <div class="max-w-full mx-auto py-4 px-2">
            <div class="flex justify-end mb-4 no-print">
                <Button label="Print Statement" icon="pi pi-print" severity="secondary" @click="printReport" />
            </div>

            <Card class="shadow-none border-0">
                <template #content>
                    <div class="text-center mb-4">
                        <div class="flex justify-center mb-2">
                            <div
                                class="w-16 h-16 border border-green-600 rounded-full flex items-center justify-center">
                                <img src="/images/logo.jpg" alt="Edo State Logo"
                                    class="w-12 h-12 object-contain" />
                            </div>
                        </div>
                        <h2 class="text-sm font-bold text-green-700 m-0 uppercase">EDO STATE AUDITED GENERAL PURPOSE
                            FINANCIAL STATEMENTS (GPFS)</h2>
                        <h3 class="text-xs font-bold text-green-700 m-0 uppercase">FOR THE YEAR ENDED 31ST DECEMBER,
                            2025</h3>

                        <div class="mt-6 border-t-2 border-yellow-500 pt-2">
                            <h1 class="text-md font-bold uppercase">CONSOLIDATED STATEMENT OF FINANCIAL PERFORMANCE
                                (INCOME & EXPENDITURE)</h1>
                            <p class="text-sm font-bold uppercase">FOR THE YEAR ENDED 31ST DEC. 2025 </p> contd
                        </div>
                    </div>

                    <DataTable :value="props.financialData" :rowClass="rowClass"
                        class="p-datatable-sm custom-audit-table" responsiveLayout="scroll">
                        <ColumnGroup type="header">
                            <Row>
                                <Column header="PREVIOUS YEAR" :rowspan="2"
                                    class="bg-audit text-white border-r text-[10px]" />
                                <Column header="DESCRIPTION" :rowspan="2"
                                    class="bg-audit text-white border-r text-[10px]" />
                                <Column header="NOTES" :rowspan="2" class="bg-audit text-white border-r text-[10px]" />
                                <Column header="ACTUAL 2025" :rowspan="2"
                                    class="bg-audit text-white border-r text-[10px]" />
                                <Column header="FINAL BUDGET 2025" :rowspan="2"
                                    class="bg-audit text-white border-r text-[10px]" />
                                <Column header="SUPPLEMENTARY" :colspan="1"
                                    class="bg-audit text-white border-r text-center text-[10px]" />
                                <Column header="INITIAL BUDGET 2025" :rowspan="2"
                                    class="bg-audit text-white border-r text-[10px]" />
                                <Column header="VARIANCE ON FINAL" :rowspan="2"
                                    class="bg-audit text-white text-[10px]" />
                            </Row>
                            <Row>
                                <Column header="BUDGET 2025" class="bg-audit text-white border-r text-[10px]" />
                            </Row>
                            <Row>
                                <Column header="ACTUAL 2024 (N)"
                                    class="bg-audit text-white text-[9px] italic border-r text-center" />
                                <Column header="DETAILS"
                                    class="bg-audit text-white text-[9px] italic border-r text-center" />
                                <Column header="" class="bg-audit text-white border-r" />
                                <Column header="N" class="bg-audit text-white border-r text-center text-[9px]" />
                                <Column header="N" class="bg-audit text-white border-r text-center text-[9px]" />
                                <Column header="N" class="bg-audit text-white border-r text-center text-[9px]" />
                                <Column header="N" class="bg-audit text-white border-r text-center text-[9px]" />
                                <Column header="ANNUAL BUDGET" class="bg-audit text-white text-center text-[9px]" />
                            </Row>
                        </ColumnGroup>

                        <Column field="prevActual" class="text-right border-r font-mono text-[11px]">
                            <template #body="sp">{{ formatCurrency(sp.data.prevActual) }}</template>
                        </Column>

                        <Column field="description" class="border-r text-[10px] uppercase py-2">
                            <template #body="sp">
                                <span :class="{ 'font-bold': sp.data.isHeader || sp.data.isTotal || sp.data.isFinal }">
                                    {{ sp.data.description }}
                                </span>
                            </template>
                        </Column>

                        <Column field="notes" class="text-center border-r text-[11px]" />

                        <Column field="actual2025" class="text-right border-r font-mono text-[11px]">
                            <template #body="sp">{{ formatCurrency(sp.data.actual2025) }}</template>
                        </Column>

                        <Column field="finalBudget" class="text-right border-r font-mono text-[11px]">
                            <template #body="sp">{{ formatCurrency(sp.data.finalBudget) }}</template>
                        </Column>

                        <Column field="suppBudget" class="text-right border-r font-mono text-[11px]">
                            <template #body="sp">{{ formatCurrency(sp.data.suppBudget) }}</template>
                        </Column>

                        <Column field="initialBudget" class="text-right border-r font-mono text-[11px]">
                            <template #body="sp">{{ formatCurrency(sp.data.initialBudget) }}</template>
                        </Column>

                        <Column field="variance" class="text-right font-mono text-[11px]">
                            <template #body="sp">{{ formatCurrency(sp.data.variance) }}</template>
                        </Column>
                    </DataTable>

                    <div
                        class="mt-8 pt-2 border-t border-black flex justify-between items-center text-[10px] font-bold italic">
                        <span>www.edostate.gov.ng</span>
                        <span>2</span>
                        <span class="text-right uppercase">Audited Report</span>
                    </div>
                </template>
            </Card>
        </div>
    </AppLayout>
</template>

<style scoped>
:deep(.bg-audit) {
    background-color: #064e3b !important;
}

:deep(.custom-audit-table td),
:deep(.custom-audit-table th) {
    border: 0.5px solid #000 !important;
    padding: 3px 6px !important;
}

.font-mono {
    font-family: 'Courier New', Courier, monospace;
    font-variant-numeric: tabular-nums;
}

@media print {
    .no-print {
        display: none !important;
    }

    body {
        -webkit-print-color-adjust: exact;
    }
}
</style>
