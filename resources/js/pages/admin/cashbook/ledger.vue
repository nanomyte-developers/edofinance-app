<template>
    <AppLayout>
        <Head :title="`Ledger - ${cashbook.bank_account?.title}`" />

        <div class="card">
            <!-- Header -->
            <div class="justify-content-between align-items-center mb-4 flex">
                <div>
                    <Button
                        icon="pi pi-arrow-left"
                        class="p-button-text p-button-rounded mr-3"
                        @click="goBack"
                        v-tooltip.top="'Go Back'"
                    />
                    <h4 class="m-0 inline">
                        {{ cashbook.bank_account?.title }}
                    </h4>
                    <p class="text-500 m-0">
                        {{ month_name }} {{ cashbook.year }} | Account:
                        {{ cashbook.bank_account?.account_number }}
                    </p>
                </div>
                <div>
                    <Tag
                        :value="cashbook.status"
                        :severity="
                            cashbook.status === 'open' ? 'success' : 'danger'
                        "
                        class="mr-3"
                    />
                    <Button
                        label="Export"
                        icon="pi pi-file-excel"
                        class="p-button-outlined"
                    />
                </div>
            </div>

            <!-- Summary Cards -->
            <div class="mb-5 grid">
                <div class="col-12 md:col-3">
                    <div class="card surface-50 p-3">
                        <div class="text-500 text-sm">Opening Balance</div>
                        <div class="text-900 text-xl font-bold">
                            {{ formatCurrency(summary.opening_balance) }}
                        </div>
                    </div>
                </div>
                <div class="col-12 md:col-3">
                    <div class="card surface-50 p-3">
                        <div class="text-500 text-sm">Total Receipts</div>
                        <div class="text-900 text-xl font-bold text-green-600">
                            {{ formatCurrency(summary.total_receipts) }}
                        </div>
                    </div>
                </div>
                <div class="col-12 md:col-3">
                    <div class="card surface-50 p-3">
                        <div class="text-500 text-sm">Total Payments</div>
                        <div class="text-900 text-xl font-bold text-red-600">
                            {{ formatCurrency(summary.total_payments) }}
                        </div>
                    </div>
                </div>
                <div class="col-12 md:col-3">
                    <div class="card surface-50 p-3">
                        <div class="text-500 text-sm">Closing Balance</div>
                        <div
                            class="text-900 text-xl font-bold"
                            :class="{
                                'text-green-600': summary.closing_balance > 0,
                                'text-red-600': summary.closing_balance < 0,
                            }"
                        >
                            {{ formatCurrency(summary.closing_balance) }}
                        </div>
                    </div>
                </div>
            </div>

            <!-- Ledger Table -->
            <DataTable
                :value="entries"
                class="p-datatable-sm"
                paginator
                :rows="20"
            >
                <Column field="transaction_date" header="Date" sortable>
                    <template #body="slotProps">
                        {{ formatDate(slotProps.data.transaction_date) }}
                    </template>
                </Column>

                <Column
                    field="description"
                    header="Description"
                    sortable
                ></Column>

                <Column field="reference_number" header="Ref No.">
                    <template #body="slotProps">
                        <Tag
                            v-if="slotProps.data.reference_number"
                            :value="slotProps.data.reference_number"
                            severity="info"
                        />
                        <span v-else class="text-500">-</span>
                    </template>
                </Column>

                <Column field="type" header="Type" sortable>
                    <template #body="slotProps">
                        <Tag
                            :value="slotProps.data.type.toUpperCase()"
                            :severity="
                                slotProps.data.type === 'receipt'
                                    ? 'success'
                                    : 'danger'
                            "
                        />
                    </template>
                </Column>

                <Column field="amount" header="Amount" sortable>
                    <template #body="slotProps">
                        <span
                            :class="
                                slotProps.data.type === 'receipt'
                                    ? 'text-green-600'
                                    : 'text-red-600'
                            "
                        >
                            {{ formatCurrency(slotProps.data.amount) }}
                        </span>
                    </template>
                </Column>

                <Column field="running_balance" header="Balance" sortable>
                    <template #body="slotProps">
                        <span
                            :class="{
                                'text-green-600':
                                    slotProps.data.running_balance > 0,
                                'text-red-600':
                                    slotProps.data.running_balance < 0,
                                'text-600':
                                    slotProps.data.running_balance === 0,
                            }"
                        >
                            {{ formatCurrency(slotProps.data.running_balance) }}
                        </span>
                    </template>
                </Column>

                <Column field="source_type" header="Source">
                    <template #body="slotProps">
                        <Badge
                            v-if="slotProps.data.source_type"
                            :value="slotProps.data.source_type"
                            severity="secondary"
                        />
                    </template>
                </Column>
            </DataTable>
        </div>
    </AppLayout>
</template>

<script setup>
import AppLayout from '@/layouts/AppLayout.vue';

import { Head, router } from '@inertiajs/vue3';
import Badge from 'primevue/badge';
import Button from 'primevue/button';
import Column from 'primevue/column';
import DataTable from 'primevue/datatable';
import Tag from 'primevue/tag';

const props = defineProps({
    cashbook: Object,
    entries: Array,
    summary: Object,
    month_name: String,
});

const formatCurrency = (val) =>
    new Intl.NumberFormat('en-NG', {
        style: 'currency',
        currency: 'NGN',
    }).format(val || 0);

const formatDate = (date) => new Date(date).toLocaleDateString('en-GB');

const goBack = () => {
    // Go back to month accounts page
    router.visit(
        `/cashbook-years/${props.cashbook.cashbook_financial_year_id}/month/${props.cashbook.month_id}`,
    );
};
</script>
