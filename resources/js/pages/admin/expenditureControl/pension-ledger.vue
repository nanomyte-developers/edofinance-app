<template>
    <AppLayout>
        <Head title="Pension Expenditure Ledger" />

        <div class="card p-4 bg-white ledger-container">
            <!-- Ledger Header -->
            <div class="text-center mb-4 uppercase-header">
                <h3 class="font-bold m-0 text-lg text-gray-800">OFFICE OF THE ACCOUNTANT GENERAL</h3>
                <h4 class="font-semibold m-0 text-sm text-gray-600">EXPENDITURE AND CONTROL DEPARTMENT</h4>
                <h5 class="font-medium m-0 text-xs text-gray-500">TREASURY HOUSE</h5>
                <h5 class="font-medium m-0 text-xs text-gray-500">SECRETARIAT COMPLEX</h5>
                <h2 class="font-bold mt-2 text-xl text-gray-800 tracking-wider">PENSION EXPENDITURE</h2>
                <h6 class="font-bold mt-1 text-sm text-gray-700 uppercase">
                    {{ selectedMda ? getMdaName(selectedMda) : 'ALL MINISTRIES' }}
                </h6>
                <p class="text-xs text-gray-500 mt-1">
                    {{ monthName }} {{ year }}
                    <span v-if="selectedPensionType" class="ml-2">
                        | Type: {{ getPensionTypeLabel(selectedPensionType) }}
                    </span>
                </p>
            </div>

            <!-- Header Actions -->
            <div class="flex justify-content-between align-items-center mb-4 flex-wrap gap-3 no-print">
                <div>
                    <Button
                        icon="pi pi-arrow-left"
                        class="p-button-text p-button-rounded mr-3"
                        @click="goBack"
                        v-tooltip.top="'Go Back'"
                    />
                    <span class="text-500 text-sm">
                        Showing only paid/closed pension vouchers (TCO Approved)
                    </span>
                </div>
                <div class="flex gap-2 flex-wrap">
                    <Button
                        label="Export Excel"
                        icon="pi pi-file-excel"
                        class="p-button-outlined p-button-sm"
                        @click="exportExcel"
                    />
                    <Button
                        label="Print Ledger"
                        icon="pi pi-print"
                        class="p-button-outlined p-button-sm"
                        @click="openPrintDialog"
                    />
                </div>
            </div>

            <!-- Filters - Hidden on Print -->
            <div class="mb-4 grid no-print">
                <div class="col-12 md:col-2">
                    <label class="text-sm text-500">Month</label>
                    <Dropdown
                        v-model="selectedMonth"
                        :options="months"
                        optionLabel="label"
                        optionValue="value"
                        class="w-full"
                        @change="applyFilters"
                        filter
                    />
                </div>
                <div class="col-12 md:col-2">
                    <label class="text-sm text-500">Year</label>
                    <Dropdown
                        v-model="selectedYear"
                        :options="years"
                        class="w-full"
                        @change="applyFilters"
                        filter
                    />
                </div>
                <div class="col-12 md:col-2">
                    <label class="text-sm text-500">MDA</label>
                    <Dropdown
                        v-model="selectedMda"
                        :options="mdas"
                        optionLabel="name"
                        optionValue="id"
                        placeholder="All MDAs"
                        class="w-full"
                        @change="applyFilters"
                        :showClear="true"
                        filter
                    />
                </div>
                <div class="col-12 md:col-2">
                    <label class="text-sm text-500">Pension Type</label>
                    <Dropdown
                        v-model="selectedPensionType"
                        :options="pensionTypes"
                        optionLabel="label"
                        optionValue="value"
                        placeholder="All Types"
                        class="w-full"
                        @change="applyFilters"
                        :showClear="true"
                        filter
                    />
                </div>
                <div class="col-12 md:col-4">
                    <label class="text-sm text-500">Search</label>
                    <IconField>
                        <InputIcon>
                            <i class="pi pi-search" />
                        </InputIcon>
                        <InputText
                            v-model="searchQuery"
                            placeholder="Search vouchers..."
                            class="w-full"
                            @input="onSearch"
                        />
                    </IconField>
                </div>
            </div>

            <!-- Summary Cards - Hidden on Print -->
            <div class="mb-5 grid no-print">
                <div class="col-12 md:col-3">
                    <div class="card surface-50 p-3">
                        <div class="text-500 text-sm">Opening Balance</div>
                        <div class="text-900 text-xl font-bold">
                            {{ formatCurrency(summary.opening_balance) }}
                        </div>
                        <div class="text-500 text-xs">Carried forward</div>
                    </div>
                </div>
                <div class="col-12 md:col-3">
                    <div class="card surface-50 p-3">
                        <div class="text-500 text-sm">Total Payments</div>
                        <div class="text-900 text-xl font-bold text-red-600">
                            {{ formatCurrency(summary.total_payments) }}
                        </div>
                        <div class="text-500 text-xs">{{ summary.total_vouchers }} vouchers</div>
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
                        <div class="text-500 text-xs">Accumulated total</div>
                    </div>
                </div>
                <div class="col-12 md:col-3">
                    <div class="card surface-50 p-3">
                        <div class="text-500 text-sm">Total Vouchers</div>
                        <div class="text-900 text-xl font-bold">
                            {{ summary.total_vouchers || 0 }}
                        </div>
                        <div class="text-500 text-xs">Paid pension</div>
                    </div>
                </div>
            </div>

            <!-- Pension Type Breakdown - Hidden on Print -->
            <div v-if="pensionTypeStats.length" class="mb-4 no-print">
                <h5 class="mb-2">Pension Breakdown by Type</h5>
                <div class="grid">
                    <div v-for="stat in pensionTypeStats" :key="stat.type" class="col-12 md:col-3 lg:col-2">
                        <div class="card surface-50 p-2 text-center">
                            <div class="text-sm font-bold capitalize">{{ stat.type.replace('_', ' ') }}</div>
                            <div class="text-lg font-bold">{{ formatCurrency(stat.total) }}</div>
                            <div class="text-xs text-500">{{ stat.count }} transactions</div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Economy Code Breakdown - Hidden on Print -->
            <div v-if="economyCodeStats.length" class="mb-4 no-print">
                <h5 class="mb-2">Expenditure Breakdown by Economy Code</h5>
                <div class="grid">
                    <div v-for="stat in economyCodeStats" :key="stat.code" class="col-12 md:col-3 lg:col-2">
                        <div class="card surface-50 p-2 text-center">
                            <div class="text-sm font-bold">{{ stat.code }}</div>
                            <div class="text-xs text-500">{{ stat.name }}</div>
                            <div class="text-lg font-bold">{{ formatCurrency(stat.total) }}</div>
                            <div class="text-xs text-500">{{ stat.count }} transactions</div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Ledger Table with Horizontal Scroll -->
            <div class="overflow-x-auto">
                <DataTable
                    :value="entries"
                    class="p-datatable-sm custom-ledger-table"
                    paginator
                    :rows="20"
                    :rowsPerPageOptions="[10, 20, 50, 100]"
                    stripedRows
                    responsiveLayout="scroll"
                    :loading="loading"
                    :totalRecords="entries.length"
                    showGridlines
                    scrollable
                    scrollHeight="600px"
                    style="min-width: 100%"
                >
                    <Column field="transaction_date" header="DATE" sortable style="min-width: 100px; width: 8%">
                        <template #body="slotProps">
                            {{ formatDate(slotProps.data.transaction_date) }}
                        </template>
                    </Column>

                    <Column field="voucher_number" header="PV NO" style="min-width: 120px; width: 9%">
                        <template #body="slotProps">
                            <Link :href="'/vouchers/' + slotProps.data.id" class="text-primary font-medium hover:underline text-sm">
                                {{ slotProps.data.voucher_number }}
                            </Link>
                        </template>
                    </Column>

                    <Column field="mda.name" header="MDA" style="min-width: 180px; width: 11%">
                        <template #body="slotProps">
                            <span class="text-sm">{{ slotProps.data.mda?.name || 'N/A' }}</span>
                        </template>
                    </Column>

                    <Column field="voucher_type" header="VOUCHER TYPE" style="min-width: 130px; width: 8%">
                        <template #body="slotProps">
                            <Tag 
                                :value="slotProps.data.voucher_type || 'N/A'" 
                                :severity="getPensionTypeSeverity(slotProps.data.voucher_type)"
                                size="small"
                            />
                        </template>
                    </Column>

                    <Column field="pension_type" header="PENSION TYPE" style="min-width: 130px; width: 8%">
                        <template #body="slotProps">
                            <Tag 
                                :value="getPensionTypeLabel(slotProps.data.pension_type)" 
                                :severity="getPensionTypeSeverity(slotProps.data.pension_type)"
                                size="small"
                            />
                        </template>
                    </Column>

                    <Column field="pay_point" header="PAY POINT" style="min-width: 120px; width: 7%">
                        <template #body="slotProps">
                            <Tag 
                                :value="slotProps.data.pay_point || 'N/A'" 
                                :severity="slotProps.data.pay_point === 'MAS' ? 'success' : 'info'"
                                size="small"
                            />
                        </template>
                    </Column>

                    <Column field="schedule" header="SCH NO" style="min-width: 100px; width: 6%">
                        <template #body="slotProps">
                            <Link 
                                v-if="slotProps.data.schedule_id" 
                                :href="'/schedules/' + slotProps.data.schedule_id" 
                                class="text-primary font-medium hover:underline text-sm"
                            >
                                {{ slotProps.data.schedule_number || 'N/A' }}
                            </Link>
                            <span v-else class="text-500">-</span>
                        </template>
                    </Column>

                    <Column field="payee_name" header="PAYEE" style="min-width: 160px; width: 10%">
                        <template #body="slotProps">
                            <span class="text-sm">{{ slotProps.data.payee_name || 'N/A' }}</span>
                        </template>
                    </Column>

                    <Column field="description" header="DESCRIPTION" style="min-width: 200px; width: 13%">
                        <template #body="slotProps">
                            <div class="text-sm purpose-text-cell">{{ slotProps.data.description }}</div>
                        </template>
                    </Column>

                    <Column field="amount" header="AMOUNT" style="min-width: 150px; width: 10%" :sortable="true" bodyClass="text-right">
                        <template #body="slotProps">
                            <span class="text-red-600 font-bold text-sm">
                                {{ formatCurrency(slotProps.data.amount) }}
                            </span>
                        </template>
                    </Column>

                    <Column field="running_balance" header="BALANCE" style="min-width: 150px; width: 10%" :sortable="true" bodyClass="text-right">
                        <template #body="slotProps">
                            <span
                                :class="{
                                    'text-green-600': slotProps.data.running_balance > 0,
                                    'text-red-600': slotProps.data.running_balance < 0,
                                    'text-600': slotProps.data.running_balance === 0,
                                }"
                                class="font-bold text-sm"
                            >
                                {{ formatCurrency(slotProps.data.running_balance) }}
                            </span>
                        </template>
                    </Column>

                    <Column field="payment_date" header="PAYMENT DATE" style="min-width: 120px; width: 8%">
                        <template #body="slotProps">
                            {{ formatDate(slotProps.data.payment_date) }}
                        </template>
                    </Column>

                    <Column header="ACTIONS" style="min-width: 80px; width: 5%" bodyClass="text-center no-print">
                        <template #body="slotProps">
                            <Button
                                icon="pi pi-eye"
                                severity="info"
                                text
                                rounded
                                size="small"
                                v-tooltip.top="'View Voucher'"
                                @click="router.visit('/vouchers/' + slotProps.data.id)"
                            />
                        </template>
                    </Column>

                    <!-- Footer with Total -->
                    <template #footer>
                        <div class="flex justify-content-end font-bold p-2 bg-gray-50 border-top-1">
                            <span class="mr-4">Total Amount:</span>
                            <span class="text-red-600">{{ formatCurrency(totalAmount) }}</span>
                        </div>
                    </template>
                </DataTable>
            </div>
        </div>

        <!-- Print Dialog -->
        <Dialog
            v-model:visible="showPrintDialog"
            :style="{ width: '95vw', maxWidth: '1400px', height: '95vh' }"
            header="Print Preview - Pension Expenditure Ledger"
            :modal="true"
            :closable="true"
            @hide="closePrintDialog"
            class="print-dialog"
        >
            <div class="print-content-wrapper" id="print-content-wrapper">
                <div class="print-content" id="print-content" ref="printContentRef">
                    <!-- Print Header -->
                    <div class="print-header">
                        <div class="print-title">OFFICE OF THE ACCOUNTANT GENERAL</div>
                        <div class="print-subtitle">EXPENDITURE AND CONTROL DEPARTMENT</div>
                        <div class="print-subtitle">TREASURY HOUSE</div>
                        <div class="print-subtitle">SECRETARIAT COMPLEX</div>
                        <h2 class="print-title">PENSION EXPENDITURE</h2>
                        <div class="print-subtitle font-bold uppercase">
                            {{ selectedMda ? getMdaName(selectedMda) : 'ALL MINISTRIES' }}
                            <span v-if="selectedPensionType" class="ml-2">
                                | Type: {{ getPensionTypeLabel(selectedPensionType) }}
                            </span>
                        </div>
                        <div class="print-info">
                            <span>Period: {{ monthName }} {{ year }}</span>
                            <span>Generated: {{ currentDate }}</span>
                        </div>
                    </div>

                    <!-- Ledger Table -->
                    <table class="print-ledger-table" id="print-table">
                        <thead>
                            <tr>
                                <th class="date-col">DATE</th>
                                <th class="pv-col">PV NO</th>
                                <th class="mda-col">MDA</th>
                                <th class="voucher-type-col">VOUCHER TYPE</th>
                                <th class="pension-type-col">PENSION TYPE</th>
                                <th class="pay-point-col">PAY POINT</th>
                                <th class="sch-col">SCH NO</th>
                                <th class="payee-col">PAYEE</th>
                                <th class="purpose-col">DESCRIPTION</th>
                                <th class="amount-col">AMOUNT</th>
                                <th class="balance-col">BALANCE</th>
                                <th class="payment-date-col">PAYMENT DATE</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr class="opening-balance-row">
                                <td class="text-center">{{ formatShortDate(startDate) }}</td>
                                <td class="text-center">-</td>
                                <td class="text-center">-</td>
                                <td class="text-center">-</td>
                                <td class="text-center">-</td>
                                <td class="text-center">-</td>
                                <td class="text-center">-</td>
                                <td class="text-left">BALANCE B/F</td>
                                <td class="text-left">Opening Balance</td>
                                <td class="amount-cell"></td>
                                <td class="amount-cell">{{ formatCurrencyAmount(openingBalance) }}</td>
                                <td class="text-center">-</td>
                            </tr>

                            <tr v-for="(entry, index) in entries" :key="index" class="data-row">
                                <td class="text-center">{{ formatShortDate(entry.transaction_date) }}</td>
                                <td class="text-center">{{ entry.voucher_number || '-' }}</td>
                                <td class="text-left">{{ entry.mda?.name || 'N/A' }}</td>
                                <td class="text-center">
                                    <span class="voucher-type-badge">{{ entry.voucher_type || 'N/A' }}</span>
                                </td>
                                <td class="text-center">
                                    <span class="pension-type-badge">{{ getPensionTypeLabel(entry.pension_type) }}</span>
                                </td>
                                <td class="text-center">
                                    <span class="pay-point-badge">{{ entry.pay_point || 'MAS' }}</span>
                                </td>
                                <td class="text-center">{{ entry.schedule_number || '-' }}</td>
                                <td class="text-left">{{ entry.payee_name || 'N/A' }}</td>
                                <td class="text-left purpose-cell">{{ entry.description || 'N/A' }}</td>
                                <td class="amount-cell">{{ formatCurrencyAmount(entry.amount) }}</td>
                                <td class="amount-cell">{{ formatCurrencyAmount(entry.running_balance) }}</td>
                                <td class="text-center">{{ formatShortDate(entry.payment_date) }}</td>
                            </tr>

                            <tr v-for="n in emptyRows" :key="'empty-' + n" class="empty-row">
                                <td></td>
                                <td></td>
                                <td></td>
                                <td></td>
                                <td></td>
                                <td></td>
                                <td></td>
                                <td></td>
                                <td></td>
                                <td></td>
                                <td></td>
                                <td></td>
                            </tr>

                            <tr class="total-row">
                                <td colspan="9" class="text-right font-bold">TOTAL</td>
                                <td class="amount-cell font-bold">{{ formatCurrencyAmount(totalAmount) }}</td>
                                <td class="amount-cell font-bold">{{ formatCurrencyAmount(closingBalance) }}</td>
                                <td class="text-center">-</td>
                            </tr>
                        </tbody>
                    </table>

                    <div class="print-footer">
                        <span>Page 1</span>
                        <div class="text-right uppercase italic">
                            PREPARED BY EXPENDITURE AND CONTROL DEPARTMENT<br />
                            OFFICE OF THE ACCOUNTANT-GENERAL, BENIN CITY
                        </div>
                    </div>
                </div>
            </div>

            <template #footer>
                <div class="flex gap-2 justify-content-end">
                    <Button 
                        label="Cancel" 
                        icon="pi pi-times" 
                        @click="closePrintDialog" 
                        class="p-button-secondary p-button-sm"
                    />
                    <Button 
                        label="Print" 
                        icon="pi pi-print" 
                        @click="printContent" 
                        class="p-button-primary p-button-sm"
                    />
                </div>
            </template>
        </Dialog>
    </AppLayout>
</template>

<script setup>
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { ref, computed, watch } from 'vue';
import Button from 'primevue/button';
import Column from 'primevue/column';
import DataTable from 'primevue/datatable';
import Dialog from 'primevue/dialog';
import Dropdown from 'primevue/dropdown';
import InputText from 'primevue/inputtext';
import IconField from 'primevue/iconfield';
import InputIcon from 'primevue/inputicon';
import Tag from 'primevue/tag';
import { useToast } from 'primevue/usetoast';

const props = defineProps({
    entries: Array,
    summary: Object,
    month_name: String,
    year: Number,
    month: Number,
    mdas: Array,
    pensionTypes: Array,
    filters: Object,
    economyCodeStats: Array,
    pensionTypeStats: Array,
});

const toast = useToast();
const loading = ref(false);
const printContentRef = ref(null);
const showPrintDialog = ref(false);

const currentDate = ref(
    new Date().toLocaleDateString('en-GB', {
        day: '2-digit',
        month: 'short',
        year: 'numeric',
    }),
);

// Format currency like the cashbook (Naira.Kobo)
const formatCurrencyAmount = (value) => {
    if (value === null || value === undefined || value === '') return '';
    const num = parseFloat(value) || 0;
    const parts = num.toFixed(2).split('.');
    return new Intl.NumberFormat('en-NG').format(parts[0]) + '.' + parts[1];
};

// Format short date like the cashbook (DD-MMM-YY)
const formatShortDate = (date) => {
    if (!date) return '';
    try {
        const d = new Date(date);
        const day = d.getDate().toString().padStart(2, '0');
        const monthNames = ['JAN', 'FEB', 'MAR', 'APR', 'MAY', 'JUN', 'JUL', 'AUG', 'SEP', 'OCT', 'NOV', 'DEC'];
        const month = monthNames[d.getMonth()];
        const year = d.getFullYear().toString().slice(-2);
        return `${day}-${month}-${year}`;
    } catch (error) {
        return '';
    }
};

// Filter states
const selectedMonth = ref(props.month);
const selectedYear = ref(props.year);
const selectedMda = ref(props.filters?.mda_id || null);
const selectedPensionType = ref(props.filters?.pension_type || '');
const searchQuery = ref(props.filters?.search || '');

// Computed
const monthName = computed(() => {
    const month = months.find(m => m.value === selectedMonth.value);
    return month ? month.label : '';
});

const totalAmount = computed(() => {
    return props.entries.reduce((sum, entry) => sum + (entry.amount || 0), 0);
});

const openingBalance = computed(() => {
    return props.summary?.opening_balance || 0;
});

const closingBalance = computed(() => {
    return props.summary?.closing_balance || 0;
});

const startDate = computed(() => {
    return new Date(selectedYear.value, selectedMonth.value - 1, 1);
});

const emptyRows = computed(() => {
    const minRows = 15;
    const dataRows = props.entries.length + 1;
    return Math.max(0, minRows - dataRows);
});

// Month options
const months = [
    { label: 'January', value: 1 },
    { label: 'February', value: 2 },
    { label: 'March', value: 3 },
    { label: 'April', value: 4 },
    { label: 'May', value: 5 },
    { label: 'June', value: 6 },
    { label: 'July', value: 7 },
    { label: 'August', value: 8 },
    { label: 'September', value: 9 },
    { label: 'October', value: 10 },
    { label: 'November', value: 11 },
    { label: 'December', value: 12 },
];

// Year options
const currentYear = new Date().getFullYear();
const years = Array.from({ length: 10 }, (_, i) => currentYear - i);

const formatCurrency = (val) => {
    if (val === null || val === undefined || val === '') return '';
    return new Intl.NumberFormat('en-US', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    }).format(val);
};

const formatDate = (date) => {
    if (!date) return '';
    return new Date(date).toLocaleDateString('en-GB', {
        day: '2-digit',
        month: 'short',
        year: 'numeric',
    });
};

const getMdaName = (id) => {
    const mda = props.mdas.find(m => m.id === id);
    return mda ? mda.name : '';
};

const getPensionTypeLabel = (type) => {
    const labels = {
        regular: 'Regular',
        contributory: 'Contributory',
        arrears: 'Arrears',
        gratuity: 'Gratuity',
        death_benefit: 'Death Benefit',
        other: 'Other',
    };
    return labels[type?.toLowerCase()] || type || 'Regular';
};

const getPensionTypeSeverity = (type) => {
    const types = {
        regular: 'info',
        contributory: 'success',
        arrears: 'warning',
        gratuity: 'danger',
        death_benefit: 'secondary',
        other: 'info',
    };
    return types[type?.toLowerCase()] || 'info';
};

// Dialog functions
const openPrintDialog = () => {
    showPrintDialog.value = true;
};

const closePrintDialog = () => {
    showPrintDialog.value = false;
};

// Print function
const printContent = () => {
    try {
        const printElement = document.getElementById('print-content');
        if (!printElement) {
            toast.add({
                severity: 'error',
                summary: 'Error',
                detail: 'Print content not found.',
                life: 3000,
            });
            return;
        }

        const printWindow = window.open('', '_blank', 'width=1200,height=800,scrollbars=yes');
        if (!printWindow) {
            toast.add({
                severity: 'error',
                summary: 'Popup Blocked',
                detail: 'Please allow popups for this site to print.',
                life: 5000,
            });
            return;
        }

        const contentHTML = printElement.innerHTML;

        printWindow.document.write(`
            <!DOCTYPE html>
            <html>
            <head>
                <title>Pension Expenditure Ledger - ${monthName.value} ${selectedYear.value}</title>
                <meta charset="UTF-8">
                <style>
                    /* Reset */
                    * { margin: 0; padding: 0; box-sizing: border-box; }
                    body {
                        font-family: Arial, sans-serif;
                        font-size: 10px;
                        padding: 20px;
                        background: white;
                        -webkit-print-color-adjust: exact !important;
                        print-color-adjust: exact !important;
                    }
                    
                    /* Print Header */
                    .print-header {
                        text-align: center;
                        margin-bottom: 15px;
                        border-bottom: 2px solid #000;
                        padding-bottom: 10px;
                    }
                    .print-title {
                        font-size: 16px;
                        font-weight: bold;
                        margin: 5px 0;
                        text-transform: uppercase;
                    }
                    .print-subtitle {
                        font-size: 12px;
                        margin: 3px 0;
                    }
                    .print-info {
                        font-size: 10px;
                        margin: 5px 0;
                        display: flex;
                        justify-content: space-between;
                    }
                    
                    /* Ledger Table */
                    .print-ledger-table {
                        width: 100%;
                        border-collapse: collapse;
                        font-size: 9px;
                        table-layout: fixed;
                        border: 1px solid #000;
                        margin-bottom: 20px;
                    }
                    .print-ledger-table th,
                    .print-ledger-table td {
                        border: 1px solid #000;
                        padding: 4px 6px;
                        vertical-align: middle;
                        text-align: center;
                        height: 28px;
                    }
                    .print-ledger-table th {
                        background-color: #f0f0f0 !important;
                        font-weight: bold;
                        text-transform: uppercase;
                        font-size: 8px;
                        -webkit-print-color-adjust: exact !important;
                        print-color-adjust: exact !important;
                    }
                    .print-ledger-table .amount-cell {
                        text-align: right;
                        font-family: 'Courier New', monospace;
                        font-weight: bold;
                        padding-right: 8px;
                    }
                    .print-ledger-table .purpose-cell {
                        text-align: left;
                        font-size: 8px;
                        word-wrap: break-word;
                        overflow-wrap: break-word;
                        white-space: normal;
                        line-height: 1.3;
                    }
                    .print-ledger-table .text-left { text-align: left; }
                    .print-ledger-table .text-right { text-align: right; }
                    .print-ledger-table .text-center { text-align: center; }
                    .print-ledger-table .font-bold { font-weight: bold; }
                    
                    .print-ledger-table .opening-balance-row {
                        background-color: #e3f2fd !important;
                        -webkit-print-color-adjust: exact !important;
                        print-color-adjust: exact !important;
                    }
                    .print-ledger-table .total-row {
                        background-color: #fff3e0 !important;
                        -webkit-print-color-adjust: exact !important;
                        print-color-adjust: exact !important;
                    }
                    .print-ledger-table .empty-row { height: 28px; }
                    
                    .print-ledger-table .pay-point-badge {
                        display: inline-block;
                        padding: 2px 10px;
                        border-radius: 4px;
                        font-weight: bold;
                        font-size: 8px;
                        background-color: #4caf50;
                        color: white;
                    }
                    
                    .print-ledger-table .voucher-type-badge {
                        display: inline-block;
                        padding: 2px 10px;
                        border-radius: 4px;
                        font-weight: bold;
                        font-size: 8px;
                        background-color: #2196f3;
                        color: white;
                    }
                    
                    .print-ledger-table .pension-type-badge {
                        display: inline-block;
                        padding: 2px 10px;
                        border-radius: 4px;
                        font-weight: bold;
                        font-size: 8px;
                        background-color: #9c27b0;
                        color: white;
                    }
                    
                    /* Column widths */
                    .print-ledger-table .date-col { width: 7%; }
                    .print-ledger-table .pv-col { width: 8%; }
                    .print-ledger-table .mda-col { width: 10%; }
                    .print-ledger-table .voucher-type-col { width: 8%; }
                    .print-ledger-table .pension-type-col { width: 8%; }
                    .print-ledger-table .pay-point-col { width: 7%; }
                    .print-ledger-table .sch-col { width: 6%; }
                    .print-ledger-table .payee-col { width: 9%; }
                    .print-ledger-table .purpose-col { width: 12%; }
                    .print-ledger-table .amount-col { width: 9%; }
                    .print-ledger-table .balance-col { width: 9%; }
                    .print-ledger-table .payment-date-col { width: 7%; }
                    
                    /* Print Footer */
                    .print-footer {
                        margin-top: 15px;
                        padding-top: 10px;
                        border-top: 1px solid #000;
                        font-size: 9px;
                        display: flex;
                        justify-content: space-between;
                    }
                    .uppercase { text-transform: uppercase; }
                    .italic { font-style: italic; }
                    
                    @page {
                        size: A4 landscape;
                        margin: 10mm;
                    }
                    
                    @media print {
                        body { padding: 0; }
                        .print-ledger-table th { background-color: #f0f0f0 !important; }
                        .print-ledger-table .opening-balance-row { background-color: #e3f2fd !important; }
                        .print-ledger-table .total-row { background-color: #fff3e0 !important; }
                    }
                </style>
            </head>
            <body>
                ${contentHTML}
                <script>
                    window.onload = function() {
                        setTimeout(function() {
                            window.print();
                            setTimeout(function() {
                                window.close();
                            }, 500);
                        }, 300);
                    };
                <\/script>
            </body>
            </html>
        `);

        printWindow.document.close();

    } catch (error) {
        console.error('Print error:', error);
        toast.add({
            severity: 'error',
            summary: 'Print Error',
            detail: 'Failed to print. Please try again.',
            life: 5000,
        });
    }
};

// Apply filters
const applyFilters = () => {
    loading.value = true;
    const params = {
        month: selectedMonth.value,
        year: selectedYear.value,
        mda_id: selectedMda.value || '',
        pension_type: selectedPensionType.value || '',
        search: searchQuery.value || '',
    };
    
    router.get('/expenditure-control/pension-ledger', params, {
        preserveState: true,
        preserveScroll: true,
        onFinish: () => {
            loading.value = false;
        },
        onError: (errors) => {
            loading.value = false;
            toast.add({
                severity: 'error',
                summary: 'Error',
                detail: 'Failed to load ledger data',
                life: 3000,
            });
        }
    });
};

// Search handler with debounce
let searchTimeout = null;
const onSearch = () => {
    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(() => {
        applyFilters();
    }, 500);
};

// Export functions
const exportExcel = () => {
    const params = new URLSearchParams({
        month: selectedMonth.value,
        year: selectedYear.value,
        mda_id: selectedMda.value || '',
        pension_type: selectedPensionType.value || '',
    });
    
    window.open(`/expenditure-control/pension-ledger/export?${params.toString()}`, '_blank');
    
    toast.add({
        severity: 'success',
        summary: 'Export Started',
        detail: 'Excel file is being generated...',
        life: 3000,
    });
};

const goBack = () => {
    router.visit('/expenditure-control');
};

// Watch for changes
watch([selectedMonth, selectedYear], () => {
    applyFilters();
});
</script>

<style scoped>
.ledger-container {
    font-family: 'Arial', sans-serif;
    max-width: 100%;
    overflow-x: auto;
}

.uppercase-header {
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.overflow-x-auto {
    overflow-x: auto;
    -webkit-overflow-scrolling: touch;
}

.overflow-x-auto::-webkit-scrollbar {
    height: 8px;
}
.overflow-x-auto::-webkit-scrollbar-track {
    background: #f1f1f1;
    border-radius: 4px;
}
.overflow-x-auto::-webkit-scrollbar-thumb {
    background: #c1c1c1;
    border-radius: 4px;
}
.overflow-x-auto::-webkit-scrollbar-thumb:hover {
    background: #a8a8a8;
}

:deep(.custom-ledger-table .p-datatable-thead > tr > th) {
    background-color: #f8f9fa !important;
    color: #333 !important;
    border: 1px solid #94a3b8 !important;
    text-align: center;
    font-weight: 700;
    font-size: 9px;
    padding: 0.4rem 0.25rem !important;
    text-transform: uppercase;
    white-space: nowrap;
}

:deep(.custom-ledger-table .p-datatable-thead > tr > th .p-column-header-content) {
    justify-content: center;
}

:deep(.custom-ledger-table .p-datatable-tbody > tr > td) {
    border: 1px solid #cbd5e1 !important;
    padding: 0.4rem 0.3rem !important;
    height: 38px;
    vertical-align: middle;
}

:deep(.custom-ledger-table .p-datatable-tbody > tr:hover) {
    background-color: #f1f5f9 !important;
}

:deep(.custom-ledger-table .p-datatable-tbody > tr:last-child > td) {
    border-bottom: 1px solid #94a3b8 !important;
}

.purpose-text-cell {
    font-size: 10px !important;
    line-height: 1.3;
    max-width: 200px;
    white-space: normal;
    word-wrap: break-word;
}

/* Print Dialog Styles */
:deep(.print-dialog .p-dialog-content) {
    overflow: auto;
    padding: 0;
    background: #f5f5f5;
}

.print-content-wrapper {
    padding: 20px;
    background: white;
    min-height: 500px;
}

.print-content {
    max-width: 1200px;
    margin: 0 auto;
    background: white;
    padding: 20px;
}

/* Print Styles inside dialog */
.print-header {
    text-align: center;
    margin-bottom: 15px;
    border-bottom: 2px solid #000;
    padding-bottom: 10px;
}

.print-title {
    font-size: 16px;
    font-weight: bold;
    margin: 5px 0;
    text-transform: uppercase;
}

.print-subtitle {
    font-size: 12px;
    margin: 3px 0;
}

.print-info {
    font-size: 10px;
    margin: 5px 0;
    display: flex;
    justify-content: space-between;
}

.print-ledger-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 9px;
    table-layout: fixed;
    border: 1px solid #000;
    margin-bottom: 20px;
}

.print-ledger-table th,
.print-ledger-table td {
    border: 1px solid #000;
    padding: 4px 6px;
    vertical-align: middle;
    text-align: center;
    height: 28px;
}

.print-ledger-table th {
    background-color: #f0f0f0 !important;
    font-weight: bold;
    text-transform: uppercase;
    font-size: 8px;
}

.print-ledger-table .amount-cell {
    text-align: right;
    font-family: 'Courier New', monospace;
    font-weight: bold;
    padding-right: 8px;
}

.print-ledger-table .purpose-cell {
    text-align: left;
    font-size: 8px;
    word-wrap: break-word;
    overflow-wrap: break-word;
    white-space: normal;
    line-height: 1.3;
}

.print-ledger-table .text-left { text-align: left; }
.print-ledger-table .text-right { text-align: right; }
.print-ledger-table .text-center { text-align: center; }
.print-ledger-table .font-bold { font-weight: bold; }

.print-ledger-table .opening-balance-row {
    background-color: #e3f2fd !important;
}

.print-ledger-table .total-row {
    background-color: #fff3e0 !important;
}

.print-ledger-table .empty-row {
    height: 28px;
}

.print-ledger-table .pay-point-badge {
    display: inline-block;
    padding: 2px 10px;
    border-radius: 4px;
    font-weight: bold;
    font-size: 8px;
    background-color: #4caf50;
    color: white;
}

.print-ledger-table .voucher-type-badge {
    display: inline-block;
    padding: 2px 10px;
    border-radius: 4px;
    font-weight: bold;
    font-size: 8px;
    background-color: #2196f3;
    color: white;
}

.print-ledger-table .pension-type-badge {
    display: inline-block;
    padding: 2px 10px;
    border-radius: 4px;
    font-weight: bold;
    font-size: 8px;
    background-color: #9c27b0;
    color: white;
}

/* Column widths */
.print-ledger-table .date-col { width: 7%; }
.print-ledger-table .pv-col { width: 8%; }
.print-ledger-table .mda-col { width: 10%; }
.print-ledger-table .voucher-type-col { width: 8%; }
.print-ledger-table .pension-type-col { width: 8%; }
.print-ledger-table .pay-point-col { width: 7%; }
.print-ledger-table .sch-col { width: 6%; }
.print-ledger-table .payee-col { width: 9%; }
.print-ledger-table .purpose-col { width: 12%; }
.print-ledger-table .amount-col { width: 9%; }
.print-ledger-table .balance-col { width: 9%; }
.print-ledger-table .payment-date-col { width: 7%; }

.print-footer {
    margin-top: 15px;
    padding-top: 10px;
    border-top: 1px solid #000;
    font-size: 9px;
    display: flex;
    justify-content: space-between;
}

.uppercase { text-transform: uppercase; }
.italic { font-style: italic; }

@media print {
    .p-datatable { font-size: 8px; }
    .card { box-shadow: none !important; border: none !important; padding: 0 !important; }
    .p-button { display: none !important; }
    .surface-50 { background: #f8f9fa !important; }
    .no-print { display: none !important; }
    .overflow-x-auto { overflow-x: visible !important; }
    :deep(.custom-ledger-table .p-datatable-thead > tr > th) {
        font-size: 7px !important;
        padding: 0.15rem !important;
    }
    :deep(.custom-ledger-table .p-datatable-tbody > tr > td) {
        padding: 0.15rem !important;
        height: 22px;
        font-size: 7px !important;
    }
    .purpose-text-cell { font-size: 7px !important; }
}
</style>