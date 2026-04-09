<script setup>
import { Head } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import Card from 'primevue/card';
import DataTable from 'primevue/datatable';
import Column from 'primevue/column';
import ColumnGroup from 'primevue/columngroup';
import Row from 'primevue/row';

const props = defineProps({
    balances: Object,
    totals: Object
});

const formatCurrency = (value) => {
    return new Intl.NumberFormat('en-NG', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    }).format(value || 0);
};

const breadcrumbs = [{ title: 'Reports' }, { title: 'GPFS Cash and Bank Balances' }];
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head title="Cash and Bank Balances Report" />

        <Card class="report-card">
            <template #content>
                <div class="text-center mb-6">
                    <h3 class="text-lg font-bold uppercase underline">Notes to GPFS for the Year Ended 31st December, 2024 contd.</h3>
                    <h4 class="text-md font-bold mt-2 uppercase text-green-800">
                        CASH AND BANK BALANCES HELD BY MDAs AS AT 31ST DECEMBER 2024 contd.
                    </h4>
                </div>

                <DataTable :value="balances.data" responsiveLayout="scroll" class="p-datatable-gridlines p-datatable-sm custom-report-table">
                    <Column header="S/N" headerStyle="width: 3rem">
                        <template #body="slotProps">
                            {{ slotProps.index + 87 }} </template>
                    </Column>

                    <Column header="MDAs">
                        <template #body="{ data }">
                            <span class="uppercase text-xs">{{ data.title}}</span>
                        </template>
                    </Column>

                    <Column header="BANK">
                        <template #body="{ data }">
                            <span class="uppercase text-xs">{{ data.bank?.name || data.bank_name }}</span>
                        </template>
                    </Column>

                    <Column field="account_number" header="ACCOUNT NO." />

                    <Column header="2024 (₦)" class="text-right">
                        <template #body="{ data }">
                            {{ formatCurrency(data.balance_current_year) }}
                        </template>
                    </Column>

                    <Column header="2023 (₦)" class="text-right">
                        <template #body="{ data }">
                            {{ formatCurrency(data.balance_previous_year) }}
                        </template>
                    </Column>

                    <ColumnGroup type="footer">
                        <Row>
                            <Column footer="TOTAL" :colspan="4" footerStyle="text-align:center; font-weight:bold; background-color: #f8fafc;" />
                            <Column :footer="formatCurrency(totals.current)" footerStyle="text-align:right; font-weight:bold; background-color: #f8fafc;" />
                            <Column :footer="formatCurrency(totals.previous)" footerStyle="text-align:right; font-weight:bold; background-color: #f8fafc;" />
                        </Row>
                    </ColumnGroup>
                </DataTable>

                <div class="mt-8 text-center text-xs text-gray-500 italic">
                    www.edostate.gov.ng
                </div>
            </template>
        </Card>
    </AppLayout>
</template>

<style scoped>
.report-card {
    border: 1px solid #e2e8f0;
    box-shadow: none;
    font-family: 'serif';
}
.custom-report-table :deep(.p-datatable-thead > tr > th) {
    background-color: #064e3b; /* Dark green matching image */
    color: white;
    font-size: 0.75rem;
    padding: 0.5rem;
}
.custom-report-table :deep(.p-datatable-tbody > tr > td) {
    font-size: 0.75rem;
    padding: 0.4rem;
}
</style>
