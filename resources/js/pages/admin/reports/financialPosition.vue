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
import { ref } from 'vue';
// --- PROPS DEFINITION ---
const props = defineProps({
    positionData: {
        type: Array,
        default: () => []
    }
});
const dt = ref();

// --- Currency Formatter ---
const formatCurrency = (val) => {
    if (val === 0 || val === null || val === undefined) return '-';
    const formatted = new Intl.NumberFormat('en-NG', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2
    }).format(Math.abs(val));
    return val < 0 ? `(${formatted})` : formatted;
};

// Row styling for formal document hierarchy
const rowClass = (data) => {
    if (data.isHeader) return 'bg-gray-100 font-bold tracking-widest text-green-900 uppercase';
    if (data.isSubHeader) return 'bg-gray-50 font-bold italic text-slate-800 underline';
    if (data.isTotal) return 'font-bold border-y border-black bg-gray-50';
    if (data.isFinal) return 'bg-green-50 font-black border-y-2 border-green-900 text-green-900 uppercase';
    return '';
};

const printReport = () => window.print();

const breadcrumbs = [{ title: 'Finance' }, { title: 'Audited Balance Sheet' }];

const exportCSV = () => {
    dt.value.exportCSV();
};
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">

        <Head title="Statement of Financial Position" />

        <div class="max-w-full mx-auto py-4 px-2">
            <div class="flex justify-end mb-4 no-print">
                <Button label="Print Balance Sheet" icon="pi pi-print" severity="secondary" @click="printReport" />
            </div>

            <Card class="shadow-none border-0">
                <template #content>
                    <div class="text-center mb-6">
                        <div class="flex justify-center mb-2">
                            <div
                                class="w-16 h-16 border border-green-600 rounded-full flex items-center justify-center">
                                <img src="/images/logo.jpg" alt="Edo State Logo" class="w-12 h-12 object-contain" />
                            </div>
                        </div>
                        <h2 class="text-sm font-bold text-green-700 m-0 uppercase">EDO STATE AUDITED GENERAL PURPOSE
                            FINANCIAL STATEMENTS (GPFS)</h2>
                        <h3 class="text-xs font-bold text-green-700 m-0 uppercase">FOR THE YEAR ENDED 31ST DECEMBER,
                            2025</h3>

                        <div class="mt-4 border-t-2 border-yellow-500 pt-2 text-center">
                            <h1 class="text-md font-bold uppercase">CONSOLIDATED STATEMENT OF FINANCIAL POSITION
                                (BALANCE SHEET)</h1>
                            <p class="text-sm font-bold uppercase">AS AT 31ST DECEMBER 2025</p>
                        </div>
                    </div>

                    <DataTable :value="props.positionData" :rowClass="rowClass"
                        class="p-datatable-sm custom-balance-table border" responsiveLayout="scroll" ref="dt">
                        <template #header>
                            <div style="text-align: left">
                                <Button icon="pi pi-external-link" label="Export" @click="exportCSV($event)" />
                            </div>
                        </template>
                        <ColumnGroup type="header">
                            <Row>
                                <Column header="DESCRIPTION" exportHeader="DESCRIPTION" :rowspan="2"
                                    class="bg-audit text-white border-r text-[10px]" />
                                <Column header="REF." exportHeader="REF." :rowspan="2"
                                    class="bg-audit text-white border-r text-center text-[10px]" />
                                <Column header="NOTE" exportHeader="NOTE" :rowspan="2"
                                    class="bg-audit text-white border-r text-center text-[10px]" />
                                <Column header="2025" exportHeader="2025" :colspan="2"
                                    class="bg-audit text-white border-r text-center text-[10px]" />
                                <Column header="2024" exportHeader="2024" :colspan="2"
                                    class="bg-audit text-white text-center text-[10px]" />
                            </Row>
                            <Row>
                                <Column header="N" class="bg-audit text-white border-r text-center text-[9px]" />
                                <Column header="N" class="bg-audit text-white border-r text-center text-[9px]" />
                                <Column header="N" class="bg-audit text-white border-r text-center text-[9px]" />
                                <Column header="N" class="bg-audit text-white text-center text-[9px]" />
                            </Row>
                        </ColumnGroup>

                        <Column field="description" exportHeader="DESCRIPTION" class="border-r text-[10px] py-2">
                            <template #body="slotProps">
                                <span
                                    :class="{ 'ml-6': !slotProps.data.isHeader && !slotProps.data.isSubHeader && !slotProps.data.isTotal && !slotProps.data.isFinal }">
                                    {{ slotProps.data.section ? slotProps.data.section + '. ' : '' }}
                                    {{ slotProps.data.category || slotProps.data.description }}
                                </span>
                            </template>
                        </Column>

                        <Column field="ref" exportHeader="REF" class="text-center border-r font-mono text-[10px]" />
                        <Column field="notes" exportHeader="NOTES" class="text-center border-r font-mono text-[10px]" />

                        <Column field="val2024" exportHeader="2025" class="text-right border-r font-mono text-[11px]">
                            <template #body="sp">{{ formatCurrency(sp.data.val2025) }}</template>
                        </Column>
                        <Column field="total2024" exportHeader="2025" class="text-right border-r font-mono text-[11px] font-bold">
                            <template #body="sp">{{ formatCurrency(sp.data.total2025) }}</template>
                        </Column>

                        <Column field="val2023" exportHeader="2024" class="text-right border-r font-mono text-[11px]">
                            <template #body="sp">{{ formatCurrency(sp.data.val2024) }}</template>
                        </Column>
                        <Column field="total2023" exportHeader="2024" class="text-right font-mono text-[11px] font-bold">
                            <template #body="sp">{{ formatCurrency(sp.data.total2024) }}</template>
                        </Column>
                    </DataTable>

                    <div class="mt-12 flex justify-start">
                        <div class="text-left">
                            <div class="mb-1 h-12 flex items-end">
                                <div class="italic font-serif text-blue-800 text-xl opacity-80 pl-4">JuliusAnelu</div>
                            </div>
                            <div class="border-t border-black pt-2 pr-12">
                                <p class="font-bold text-[11px] leading-tight">
                                    Dr. Julius O. Anelu (FCA, FCTI)<br />
                                    PS/Accountant General,<br />
                                    Edo State.<br />
                                    29th May, 2025
                                </p>
                            </div>
                        </div>
                    </div>

                    <div
                        class="mt-8 pt-2 border-t border-black flex justify-between items-center text-[10px] font-bold italic">
                        <span>www.edostate.gov.ng</span>
                        <span>4</span>
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

:deep(.custom-balance-table td),
:deep(.custom-balance-table th) {
    border: 0.5px solid #000 !important;
    padding: 3px 6px !important;
}

.font-mono {
    font-family: 'Courier New', Courier, monospace;
    font-variant-numeric: tabular-nums;
}

/* For printing without shadows or buttons */
@media print {
    .no-print {
        display: none !important;
    }

    body {
        -webkit-print-color-adjust: exact;
    }

    .custom-balance-table {
        font-size: 9px;
    }
}
</style>
