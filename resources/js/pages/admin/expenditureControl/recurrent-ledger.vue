<template>
    <AppLayout>
        <Head title="Recurrent Expenditure Ledger" />

        <div class="card p-4 bg-white ledger-container">
            <!-- Ledger Header -->
            <div class="text-center mb-4 uppercase-header">
                <h3 class="font-bold m-0 text-lg text-gray-800">EXPENDITURE AND CONTROL DEPARTMENT</h3>
                <h4 class="font-semibold m-0 text-sm text-gray-600">TREASURY HOUSE</h4>
                <h5 class="font-medium m-0 text-xs text-gray-500">SECRETARIAT COMPLEX</h5>
                <h2 class="font-bold mt-2 text-xl text-gray-800 tracking-wider">RECURRENT EXPENDITURES</h2>
                <p class="text-xs text-gray-500 mt-1">
                    {{ monthName }} {{ year }}
                    <span v-if="filters.mda_id" class="ml-2">
                        | MDA: {{ getMdaName(filters.mda_id) }}
                    </span>
                </p>
            </div>

            <!-- Header -->
            <div class="flex justify-content-between align-items-center mb-4 flex-wrap gap-3 no-print">
                <div>
                    <Button
                        icon="pi pi-arrow-left"
                        class="p-button-text p-button-rounded mr-3"
                        @click="goBack"
                        v-tooltip.top="'Go Back'"
                    />
                    <span class="text-500 text-sm">
                        Showing only paid/closed recurrent vouchers
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
                <div class="col-12 md:col-3">
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
                <div class="col-12 md:col-3">
                    <label class="text-sm text-500">Year</label>
                    <Dropdown
                        v-model="selectedYear"
                        :options="years"
                        class="w-full"
                        @change="applyFilters"
                        filter
                    />
                </div>
                <div class="col-12 md:col-3">
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
                <div class="col-12 md:col-3">
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
                        <div class="text-500 text-xs">Paid recurrent</div>
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
                    <!-- DATE -->
                    <Column field="transaction_date" header="DATE" sortable style="min-width: 90px; width: 6%">
                        <template #body="slotProps">
                            {{ formatDate(slotProps.data.transaction_date) }}
                        </template>
                    </Column>

                    <!-- PAYEE -->
                    <Column field="payee_name" header="PAYEE" style="min-width: 120px; width: 8%">
                        <template #body="slotProps">
                            <span class="text-sm">{{ slotProps.data.payee_name || 'N/A' }}</span>
                        </template>
                    </Column>

                    <!-- PAY POINT -->
                    <Column field="pay_point" header="PAY POINT" style="min-width: 100px; width: 6%">
                        <template #body="slotProps">
                            <Tag 
                                :value="slotProps.data.pay_point || 'N/A'" 
                                :severity="slotProps.data.pay_point === 'MAS' ? 'success' : 'info'"
                                size="small"
                            />
                        </template>
                    </Column>

                    <!-- SCH NO -->
                    <Column field="schedule" header="SCH NO" style="min-width: 70px; width: 5%">
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

                    <!-- PV NO -->
                    <Column field="voucher_number" header="PV NO" style="min-width: 80px; width: 6%">
                        <template #body="slotProps">
                            <Link :href="'/vouchers/' + slotProps.data.id" class="text-primary font-medium hover:underline text-sm">
                                {{ slotProps.data.voucher_number }}
                            </Link>
                        </template>
                    </Column>

                    <!-- PURPOSE OF PAYMENT -->
                    <Column field="description" header="PURPOSE OF PAYMENT" style="min-width: 200px; width: 12%">
                        <template #body="slotProps">
                            <div class="text-sm purpose-text-cell">{{ slotProps.data.description }}</div>
                        </template>
                    </Column>

                    <!-- AMOUNT -->
                    <Column field="amount" header="AMOUNT" style="min-width: 120px; width: 7%" :sortable="true" bodyClass="text-right">
                        <template #body="slotProps">
                            <span class="text-red-600 font-bold text-sm">
                                {{ formatCurrency(slotProps.data.amount) }}
                            </span>
                        </template>
                    </Column>

                    <!-- 21 (PERSONNEL AND OTHER ALLOWANCES) -->
                    <Column field="c21" header="21 (Personnel and Other Allowances)" style="min-width: 100px; width: 6%" bodyClass="text-right">
                        <template #body="slotProps">
                            <span class="text-sm font-mono">{{ formatCurrency(slotProps.data.c21) }}</span>
                        </template>
                    </Column>

                    <!-- 220201 (TRANSPORT AND TRAVELLING) -->
                    <Column field="c220101" header="220201 (Transport and Travelling)" style="min-width: 100px; width: 6%" bodyClass="text-right">
                        <template #body="slotProps">
                            <span class="text-sm font-mono">{{ formatCurrency(slotProps.data.c220101) }}</span>
                        </template>
                    </Column>

                    <!-- 220202 (UTILITIES GENERAL) -->
                    <Column field="c220102" header="220202 (Utilities General)" style="min-width: 100px; width: 6%" bodyClass="text-right">
                        <template #body="slotProps">
                            <span class="text-sm font-mono">{{ formatCurrency(slotProps.data.c220102) }}</span>
                        </template>
                    </Column>

                    <!-- 220203 (MATERIALS AND SUPPLY) -->
                    <Column field="c220103" header="220203 (Materials and Supply)" style="min-width: 100px; width: 6%" bodyClass="text-right">
                        <template #body="slotProps">
                            <span class="text-sm font-mono">{{ formatCurrency(slotProps.data.c220103) }}</span>
                        </template>
                    </Column>

                    <!-- 220204 (MAINTENANCE SERVICE GENERAL) -->
                    <Column field="c220104" header="220204 (Maintenance Service General)" style="min-width: 100px; width: 6%" bodyClass="text-right">
                        <template #body="slotProps">
                            <span class="text-sm font-mono">{{ formatCurrency(slotProps.data.c220104) }}</span>
                        </template>
                    </Column>

                    <!-- 220205 (TRAINING GENERAL) -->
                    <Column field="c220105" header="220205 (Training General)" style="min-width: 100px; width: 6%" bodyClass="text-right">
                        <template #body="slotProps">
                            <span class="text-sm font-mono">{{ formatCurrency(slotProps.data.c220105) }}</span>
                        </template>
                    </Column>

                    <!-- 220206 (OTHER SERVICE GENERAL) -->
                    <Column field="c220106" header="220206 (Other Service General)" style="min-width: 100px; width: 6%" bodyClass="text-right">
                        <template #body="slotProps">
                            <span class="text-sm font-mono">{{ formatCurrency(slotProps.data.c220106) }}</span>
                        </template>
                    </Column>

                    <!-- 220207 (CONSULTING AND PROFESSIONAL SERVICE) -->
                    <Column field="c220107" header="220207 (Consulting and Professional Service)" style="min-width: 100px; width: 6%" bodyClass="text-right">
                        <template #body="slotProps">
                            <span class="text-sm font-mono">{{ formatCurrency(slotProps.data.c220107) }}</span>
                        </template>
                    </Column>

                    <!-- 220208 (FUEL AND LUBRICANT) -->
                    <Column field="c220108" header="220208 (Fuel and Lubricant)" style="min-width: 100px; width: 6%" bodyClass="text-right">
                        <template #body="slotProps">
                            <span class="text-sm font-mono">{{ formatCurrency(slotProps.data.c220108) }}</span>
                        </template>
                    </Column>

                    <!-- 220209 (FINANCIAL EXPENSES CHARGES) -->
                    <Column field="c220109" header="220209 (Financial Expenses Charges)" style="min-width: 100px; width: 6%" bodyClass="text-right">
                        <template #body="slotProps">
                            <span class="text-sm font-mono">{{ formatCurrency(slotProps.data.c220109) }}</span>
                        </template>
                    </Column>

                    <!-- 220210 (MISCELLANEOUS GENERAL) -->
                    <Column field="c220110" header="220210 (Miscellaneous General)" style="min-width: 100px; width: 6%" bodyClass="text-right">
                        <template #body="slotProps">
                            <span class="text-sm font-mono">{{ formatCurrency(slotProps.data.c220110) }}</span>
                        </template>
                    </Column>

                    <!-- 2202 OH (OVERHEAD) -->
                    <Column field="c2202ch" header="2202 OH (Overhead)" style="min-width: 100px; width: 6%" bodyClass="text-right">
                        <template #body="slotProps">
                            <span class="text-sm font-mono font-bold">{{ formatCurrency(slotProps.data.c2202ch) }}</span>
                        </template>
                    </Column>

                    <!-- Actions -->
                    <Column header="Actions" style="min-width: 80px; width: 5%" bodyClass="text-center no-print">
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
            header="Print Preview - Recurrent Expenditure Ledger"
            :modal="true"
            :closable="true"
            @hide="closePrintDialog"
            class="print-dialog"
        >
            <div class="print-content-wrapper" id="print-content-wrapper">
                <div class="print-content" id="print-content" ref="printContentRef">
                    <!-- Print Header -->
                    <div class="print-header">
                        <div class="print-title">EXPENDITURE AND CONTROL DEPARTMENT</div>
                        <div class="print-subtitle">TREASURY HOUSE</div>
                        <div class="print-subtitle">SECRETARIAT COMPLEX</div>
                        <h2 class="print-title">RECURRENT EXPENDITURES</h2>
                        <div class="print-subtitle font-bold uppercase">
                            {{ selectedMda ? getMdaName(selectedMda) : 'ALL MINISTRIES' }}
                        </div>
                        <div class="print-info">
                            <span>Period: {{ monthName }} {{ year }}</span>
                            <span>Generated: {{ currentDate }}</span>
                        </div>
                    </div>

                    <!-- Ledger Table with Sub-headers -->
                    <table class="print-ledger-table" id="print-table">
                        <thead>
                            <tr>
                                <th class="date-col">DATE</th>
                                <th class="payee-col">PAYEE</th>
                                <th class="pay-point-col">PAY POINT</th>
                                <th class="sch-col">SCH NO</th>
                                <th class="pv-col">PV NO</th>
                                <th class="purpose-col">PURPOSE OF PAYMENT</th>
                                <th class="amount-col">AMOUNT</th>
                                <th class="ec21-col">21</th>
                                <th class="ec220101-col">220201</th>
                                <th class="ec220102-col">220202</th>
                                <th class="ec220103-col">220203</th>
                                <th class="ec220104-col">220204</th>
                                <th class="ec220105-col">220205</th>
                                <th class="ec220106-col">220206</th>
                                <th class="ec220107-col">220207</th>
                                <th class="ec220108-col">220208</th>
                                <th class="ec220109-col">220209</th>
                                <th class="ec220110-col">220210</th>
                                <th class="oh-col">2202 OH</th>
                            </tr>
                            <!-- Sub-headers for Economy Codes -->
                            <tr>
                                <th colspan="7" class="sub-header"></th>
                                <th class="sub-header">Personnel and Other Allowances</th>
                                <th class="sub-header">Transport and Travelling</th>
                                <th class="sub-header">Utilities General</th>
                                <th class="sub-header">Materials and Supply</th>
                                <th class="sub-header">Maintenance Service General</th>
                                <th class="sub-header">Training General</th>
                                <th class="sub-header">Other Service General</th>
                                <th class="sub-header">Consulting and Professional Service</th>
                                <th class="sub-header">Fuel and Lubricant</th>
                                <th class="sub-header">Financial Expenses Charges</th>
                                <th class="sub-header">Miscellaneous General</th>
                                <th class="sub-header">Overhead</th>
                            </tr>
                        </thead>
                        <tbody>
                            <!-- Opening Balance Row -->
                            <tr class="opening-balance-row">
                                <td class="text-center">{{ formatShortDate(startDate) }}</td>
                                <td class="text-left">BALANCE B/F</td>
                                <td class="text-center">-</td>
                                <td class="text-center">-</td>
                                <td class="text-center">-</td>
                                <td class="text-left">Opening Balance</td>
                                <td class="amount-cell"></td>
                                <td class="amount-cell"></td>
                                <td class="amount-cell"></td>
                                <td class="amount-cell"></td>
                                <td class="amount-cell"></td>
                                <td class="amount-cell"></td>
                                <td class="amount-cell"></td>
                                <td class="amount-cell"></td>
                                <td class="amount-cell"></td>
                                <td class="amount-cell"></td>
                                <td class="amount-cell"></td>
                                <td class="amount-cell"></td>
                                <td class="amount-cell">{{ formatCurrencyAmount(openingBalance) }}</td>
                            </tr>

                            <!-- Data Rows -->
                            <tr v-for="(entry, index) in entries" :key="index" class="data-row">
                                <td class="text-center">{{ formatShortDate(entry.transaction_date) }}</td>
                                <td class="text-left">{{ entry.payee_name || 'N/A' }}</td>
                                <td class="text-center">
                                    <span class="pay-point-badge">{{ entry.pay_point || 'MAS' }}</span>
                                </td>
                                <td class="text-center">{{ entry.schedule_number || '-' }}</td>
                                <td class="text-center">{{ entry.voucher_number || '-' }}</td>
                                <td class="text-left purpose-cell">{{ entry.description || 'N/A' }}</td>
                                <td class="amount-cell">{{ formatCurrencyAmount(entry.amount) }}</td>
                                <td class="amount-cell">{{ formatCurrencyAmount(entry.c21) }}</td>
                                <td class="amount-cell">{{ formatCurrencyAmount(entry.c220101) }}</td>
                                <td class="amount-cell">{{ formatCurrencyAmount(entry.c220102) }}</td>
                                <td class="amount-cell">{{ formatCurrencyAmount(entry.c220103) }}</td>
                                <td class="amount-cell">{{ formatCurrencyAmount(entry.c220104) }}</td>
                                <td class="amount-cell">{{ formatCurrencyAmount(entry.c220105) }}</td>
                                <td class="amount-cell">{{ formatCurrencyAmount(entry.c220106) }}</td>
                                <td class="amount-cell">{{ formatCurrencyAmount(entry.c220107) }}</td>
                                <td class="amount-cell">{{ formatCurrencyAmount(entry.c220108) }}</td>
                                <td class="amount-cell">{{ formatCurrencyAmount(entry.c220109) }}</td>
                                <td class="amount-cell">{{ formatCurrencyAmount(entry.c220110) }}</td>
                                <td class="amount-cell">{{ formatCurrencyAmount(entry.c2202ch) }}</td>
                            </tr>

                            <!-- Empty Rows -->
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
                                <td></td>
                                <td></td>
                                <td></td>
                                <td></td>
                                <td></td>
                                <td></td>
                                <td></td>
                            </tr>

                            <!-- Total Row -->
                            <tr class="total-row">
                                <td colspan="6" class="text-right font-bold">TOTAL</td>
                                <td class="amount-cell font-bold">{{ formatCurrencyAmount(totalAmount) }}</td>
                                <td class="amount-cell font-bold">{{ formatCurrencyAmount(totalC21) }}</td>
                                <td class="amount-cell font-bold">{{ formatCurrencyAmount(totalC220101) }}</td>
                                <td class="amount-cell font-bold">{{ formatCurrencyAmount(totalC220102) }}</td>
                                <td class="amount-cell font-bold">{{ formatCurrencyAmount(totalC220103) }}</td>
                                <td class="amount-cell font-bold">{{ formatCurrencyAmount(totalC220104) }}</td>
                                <td class="amount-cell font-bold">{{ formatCurrencyAmount(totalC220105) }}</td>
                                <td class="amount-cell font-bold">{{ formatCurrencyAmount(totalC220106) }}</td>
                                <td class="amount-cell font-bold">{{ formatCurrencyAmount(totalC220107) }}</td>
                                <td class="amount-cell font-bold">{{ formatCurrencyAmount(totalC220108) }}</td>
                                <td class="amount-cell font-bold">{{ formatCurrencyAmount(totalC220109) }}</td>
                                <td class="amount-cell font-bold">{{ formatCurrencyAmount(totalC220110) }}</td>
                                <td class="amount-cell font-bold">{{ formatCurrencyAmount(totalC2202ch) }}</td>
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
    filters: Object,
    economyCodeStats: Array,
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

// Economy Code Totals
const totalC21 = computed(() => {
    return props.entries.reduce((sum, entry) => sum + (entry.c21 || 0), 0);
});

const totalC220101 = computed(() => {
    return props.entries.reduce((sum, entry) => sum + (entry.c220101 || 0), 0);
});

const totalC220102 = computed(() => {
    return props.entries.reduce((sum, entry) => sum + (entry.c220102 || 0), 0);
});

const totalC220103 = computed(() => {
    return props.entries.reduce((sum, entry) => sum + (entry.c220103 || 0), 0);
});

const totalC220104 = computed(() => {
    return props.entries.reduce((sum, entry) => sum + (entry.c220104 || 0), 0);
});

const totalC220105 = computed(() => {
    return props.entries.reduce((sum, entry) => sum + (entry.c220105 || 0), 0);
});

const totalC220106 = computed(() => {
    return props.entries.reduce((sum, entry) => sum + (entry.c220106 || 0), 0);
});

const totalC220107 = computed(() => {
    return props.entries.reduce((sum, entry) => sum + (entry.c220107 || 0), 0);
});

const totalC220108 = computed(() => {
    return props.entries.reduce((sum, entry) => sum + (entry.c220108 || 0), 0);
});

const totalC220109 = computed(() => {
    return props.entries.reduce((sum, entry) => sum + (entry.c220109 || 0), 0);
});

const totalC220110 = computed(() => {
    return props.entries.reduce((sum, entry) => sum + (entry.c220110 || 0), 0);
});

const totalC2202ch = computed(() => {
    return props.entries.reduce((sum, entry) => sum + (entry.c2202ch || 0), 0);
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
                <title>Recurrent Expenditure Ledger - ${monthName.value} ${selectedYear.value}</title>
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
                        font-size: 8px;
                        table-layout: fixed;
                        border: 1px solid #000;
                        margin-bottom: 20px;
                    }
                    .print-ledger-table th,
                    .print-ledger-table td {
                        border: 1px solid #000;
                        padding: 3px 5px;
                        vertical-align: middle;
                        text-align: center;
                        height: 26px;
                    }
                    .print-ledger-table th {
                        background-color: #f0f0f0 !important;
                        font-weight: bold;
                        text-transform: uppercase;
                        font-size: 7px;
                        -webkit-print-color-adjust: exact !important;
                        print-color-adjust: exact !important;
                    }
                    .print-ledger-table .sub-header {
                        font-size: 6px;
                        text-transform: none;
                        font-weight: 600;
                        background-color: #e8e8e8 !important;
                    }
                    .print-ledger-table .amount-cell {
                        text-align: right;
                        font-family: 'Courier New', monospace;
                        font-weight: bold;
                        padding-right: 6px;
                        font-size: 7px;
                    }
                    .print-ledger-table .purpose-cell {
                        text-align: left;
                        font-size: 7px;
                        word-wrap: break-word;
                        overflow-wrap: break-word;
                        white-space: normal;
                        line-height: 1.2;
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
                    .print-ledger-table .empty-row { height: 26px; }
                    
                    .print-ledger-table .pay-point-badge {
                        display: inline-block;
                        padding: 1px 6px;
                        border-radius: 3px;
                        font-weight: bold;
                        font-size: 6px;
                        background-color: #4caf50;
                        color: white;
                    }
                    
                    /* Column widths */
                    .print-ledger-table .date-col { width: 5%; }
                    .print-ledger-table .payee-col { width: 7%; }
                    .print-ledger-table .pay-point-col { width: 5%; }
                    .print-ledger-table .sch-col { width: 4%; }
                    .print-ledger-table .pv-col { width: 5%; }
                    .print-ledger-table .purpose-col { width: 10%; }
                    .print-ledger-table .amount-col { width: 5%; }
                    .print-ledger-table .ec21-col { width: 5%; }
                    .print-ledger-table .ec220101-col { width: 5%; }
                    .print-ledger-table .ec220102-col { width: 5%; }
                    .print-ledger-table .ec220103-col { width: 5%; }
                    .print-ledger-table .ec220104-col { width: 5%; }
                    .print-ledger-table .ec220105-col { width: 5%; }
                    .print-ledger-table .ec220106-col { width: 5%; }
                    .print-ledger-table .ec220107-col { width: 5%; }
                    .print-ledger-table .ec220108-col { width: 5%; }
                    .print-ledger-table .ec220109-col { width: 5%; }
                    .print-ledger-table .ec220110-col { width: 5%; }
                    .print-ledger-table .oh-col { width: 5%; }
                    
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
                        .print-ledger-table .sub-header { background-color: #e8e8e8 !important; }
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
        search: searchQuery.value || '',
    };
    
    router.get('/expenditure-control/recurrent-ledger', params, {
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
    });
    
    window.open(`/expenditure-control/recurrent-ledger/export?${params.toString()}`, '_blank');
    
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
    padding: 0.4rem 0.2rem !important;
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
    font-size: 8px;
    table-layout: fixed;
    border: 1px solid #000;
    margin-bottom: 20px;
}

.print-ledger-table th,
.print-ledger-table td {
    border: 1px solid #000;
    padding: 3px 5px;
    vertical-align: middle;
    text-align: center;
    height: 26px;
}

.print-ledger-table th {
    background-color: #f0f0f0 !important;
    font-weight: bold;
    text-transform: uppercase;
    font-size: 7px;
}

.print-ledger-table .sub-header {
    font-size: 6px;
    text-transform: none;
    font-weight: 600;
    background-color: #e8e8e8 !important;
}

.print-ledger-table .amount-cell {
    text-align: right;
    font-family: 'Courier New', monospace;
    font-weight: bold;
    padding-right: 6px;
}

.print-ledger-table .purpose-cell {
    text-align: left;
    font-size: 7px;
    word-wrap: break-word;
    overflow-wrap: break-word;
    white-space: normal;
    line-height: 1.2;
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
    height: 26px;
}

.print-ledger-table .pay-point-badge {
    display: inline-block;
    padding: 1px 6px;
    border-radius: 3px;
    font-weight: bold;
    font-size: 6px;
    background-color: #4caf50;
    color: white;
}

/* Column widths */
.print-ledger-table .date-col { width: 5%; }
.print-ledger-table .payee-col { width: 7%; }
.print-ledger-table .pay-point-col { width: 5%; }
.print-ledger-table .sch-col { width: 4%; }
.print-ledger-table .pv-col { width: 5%; }
.print-ledger-table .purpose-col { width: 10%; }
.print-ledger-table .amount-col { width: 5%; }
.print-ledger-table .ec21-col { width: 5%; }
.print-ledger-table .ec220101-col { width: 5%; }
.print-ledger-table .ec220102-col { width: 5%; }
.print-ledger-table .ec220103-col { width: 5%; }
.print-ledger-table .ec220104-col { width: 5%; }
.print-ledger-table .ec220105-col { width: 5%; }
.print-ledger-table .ec220106-col { width: 5%; }
.print-ledger-table .ec220107-col { width: 5%; }
.print-ledger-table .ec220108-col { width: 5%; }
.print-ledger-table .ec220109-col { width: 5%; }
.print-ledger-table .ec220110-col { width: 5%; }
.print-ledger-table .oh-col { width: 5%; }

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