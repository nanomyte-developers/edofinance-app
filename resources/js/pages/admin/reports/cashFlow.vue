<script setup>
import { Head } from '@inertiajs/vue3';
import Card from 'primevue/card';
import DataTable from 'primevue/datatable';
import Column from 'primevue/column';
import ColumnGroup from 'primevue/columngroup';
import Row from 'primevue/row';
import AppLayout from '@/layouts/AppLayout.vue';

const props = defineProps({
    cashFlowData: { type: Array, default: () => [] }
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
    if (data.isHeader) return 'bg-gray-100 font-bold text-green-900 uppercase tracking-wider';
    if (data.isSubHeader) return 'font-bold italic text-slate-700 bg-gray-50';
    if (data.isTotal) return 'font-bold border-y border-black bg-gray-50';
    if (data.isFinal) return 'bg-green-50 font-black border-y-2 border-green-900 text-green-900 uppercase';
    return '';
};
</script>

<template>
    <AppLayout>
        <Head title="Statement of Cash Flows" />

        <div class="max-w-full mx-auto py-6 px-4">
            <Card class="shadow-none border-0">
                <template #content>
                    <div class="text-center mb-6">
                        <div class="flex justify-center mb-2">
                            <img src="/logo-wmfROXvK.jpg" alt="Edo State Logo" class="w-16 h-16 object-contain" />
                        </div>
                        <h2 class="text-sm font-bold text-green-700 m-0 uppercase">EDO STATE AUDITED GENERAL PURPOSE FINANCIAL STATEMENTS (GPFS)</h2>
                        <h3 class="text-xs font-bold text-green-700 m-0 uppercase">FOR THE YEAR ENDED 31ST DECEMBER, 2025</h3>

                        <div class="mt-4 border-t-2 border-yellow-500 pt-2 text-center">
                            <h1 class="text-md font-bold uppercase">CONSOLIDATED STATEMENT OF CASHFLOWS</h1>
                            <p class="text-sm font-bold uppercase">FOR THE YEAR ENDED 31ST DECEMBER 2025</p>
                        </div>
                    </div>

                    <DataTable :value="cashFlowData" :rowClass="rowClass" class="p-datatable-sm custom-cashflow-table border">
                        <ColumnGroup type="header">
                            <Row>
                                <Column header="DESCRIPTION" :rowspan="2" class="bg-audit text-white border-r text-[10px]" />
                                <Column header="NOTES" :rowspan="2" class="bg-audit text-white border-r text-center text-[10px]" />
                                <Column header="ACTUAL" :colspan="2" class="bg-audit text-white border-r text-center text-[10px]" />
                                <Column header="2024" :colspan="2" class="bg-audit text-white text-center text-[10px]" />
                            </Row>
                            <Row>
                                <Column header="2025 (N)" class="bg-audit text-white border-r text-center text-[9px]" />
                                <Column header="N" class="bg-audit text-white border-r text-center text-[9px]" />
                                <Column header="N" class="bg-audit text-white border-r text-center text-[9px]" />
                                <Column header="N" class="bg-audit text-white text-center text-[9px]" />
                            </Row>
                        </ColumnGroup>

                        <Column field="description" class="border-r text-[10px] py-2">
                            <template #body="slotProps">
                                <span :class="{'ml-6': !slotProps.data.isHeader && !slotProps.data.isSubHeader && !slotProps.data.isTotal && !slotProps.data.isFinal}">
                                    {{ slotProps.data.description }}
                                </span>
                            </template>
                        </Column>

                        <Column field="notes" class="text-center border-r font-mono text-[10px]" />

                        <Column field="val2025" class="text-right border-r font-mono text-[11px]">
                            <template #body="sp">{{ formatCurrency(sp.data.val2025) }}</template>
                        </Column>
                        <Column field="total2025" class="text-right border-r font-mono text-[11px] font-bold">
                            <template #body="sp">{{ formatCurrency(sp.data.total2025) }}</template>
                        </Column>

                        <Column field="val2024" class="text-right border-r font-mono text-[11px]">
                            <template #body="sp">{{ formatCurrency(sp.data.val2024) }}</template>
                        </Column>
                        <Column field="total2024" class="text-right font-mono text-[11px] font-bold">
                            <template #body="sp">{{ formatCurrency(sp.data.total2024) }}</template>
                        </Column>
                    </DataTable>

                    <div class="mt-12 flex justify-start no-print">
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
                        <span>6</span>
                        <span class="uppercase">Audited Report</span>
                    </div>
                </template>
            </Card>
        </div>
    </AppLayout>
</template>

<style scoped>
:deep(.bg-audit) { background-color: #064e3b !important; }
:deep(.custom-cashflow-table td), :deep(.custom-cashflow-table th) {
    border: 0.5px solid #000 !important;
    padding: 3px 6px !important;
}
.font-mono { font-family: 'Courier New', monospace; font-variant-numeric: tabular-nums; }
@media print {
    .no-print { display: none !important; }
}
</style>
