<!-- resources/js/pages/admin/expenditure-control/payment-status.vue -->
<script setup>
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { FilterMatchMode } from '@primevue/core/api';
import axios from 'axios';
import Badge from 'primevue/badge';
import Button from 'primevue/button';
import Card from 'primevue/card';
import Column from 'primevue/column';
import DataTable from 'primevue/datatable';
import Dialog from 'primevue/dialog';
import IconField from 'primevue/iconfield';
import InputIcon from 'primevue/inputicon';
import InputText from 'primevue/inputtext';
import Message from 'primevue/message';
import Tag from 'primevue/tag';
import Textarea from 'primevue/textarea';
import Toast from 'primevue/toast';
import ProgressSpinner from 'primevue/progressspinner';
import { useToast } from 'primevue/usetoast';
import { computed, onMounted, ref, watch } from 'vue';

const toast = useToast();

// Props from Laravel controller - MUST be defined first
const props = defineProps({
    vouchers: {
        type: Object,
        default: () => ({
            data: [],
            total: 0,
            per_page: 15,
            current_page: 1,
        }),
    },
    stats: {
        type: Object,
        default: () => ({
            total_vouchers: 0,
            paid_count: 0,
            pending_mas_count: 0,
            pending_ag_count: 0,
            total_amount_paid: 0,
            total_amount_pending: 0,
        }),
    },
    current_filter: {
        type: String,
        default: 'all',
    },
});

// State
const vouchers = ref([]);
const searchQuery = ref('');
const loading = ref(false);
const totalRecords = ref(0);
const showPaymentModal = ref(false);
const currentVoucher = ref(null);
const paymentReference = ref('');
const paymentComment = ref('');
const isProcessing = ref(false);
const currentFilter = ref(props.current_filter || 'all');

// Stats
const stats = ref({
    total_vouchers: 0,
    paid_count: 0,
    pending_mas_count: 0,
    pending_ag_count: 0,
    total_amount_paid: 0,
    total_amount_pending: 0,
});

const filters = ref({
    global: { value: null, matchMode: FilterMatchMode.CONTAINS },
    voucher_number: { value: null, matchMode: FilterMatchMode.CONTAINS },
    voucher_date: { value: null, matchMode: FilterMatchMode.CONTAINS },
    narration: { value: null, matchMode: FilterMatchMode.CONTAINS },
    voucher_type: { value: null, matchMode: FilterMatchMode.CONTAINS },
    payment_status: { value: null, matchMode: FilterMatchMode.EQUALS },
});

const lazyParams = ref({
    first: 0,
    rows: 10,
    page: 1,
});

let debounceTimer = null;

// Filter options - Updated to match actual status values
const filterOptions = [
    { label: 'All Vouchers', value: 'all' },
    { label: 'Paid', value: 'paid' },
    { label: 'Pending MAS', value: 'awaiting_mas' },
    { label: 'Pending AG', value: 'awaiting_ag' },
];

// Format currency
const formatCurrency = (value) => {
    const numValue = Number(value) || 0;
    return new Intl.NumberFormat('en-NG', {
        style: 'currency',
        currency: 'NGN',
        minimumFractionDigits: 2,
    }).format(numValue);
};

// Format date
const formatDate = (date) => {
    if (!date) return 'N/A';
    return new Date(date).toLocaleDateString('en-GB', {
        day: '2-digit',
        month: 'short',
        year: 'numeric',
    });
};

// Format datetime
const formatDateTime = (date) => {
    if (!date) return 'N/A';
    return new Date(date).toLocaleString('en-GB', {
        day: '2-digit',
        month: 'short',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
};

// Get voucher type badge severity
const getVoucherTypeSeverity = (type) => {
    const types = {
        standard: 'info',
        prepayment: 'warning',
        salary: 'success',
    };
    return types[type?.toLowerCase()] || 'info';
};

// Get payment status badge
const getPaymentStatusSeverity = (status) => {
    const statuses = {
        paid: 'success',
        awaiting_mas: 'warning',
        awaiting_ag: 'info',
    };
    return statuses[status] || 'secondary';
};

const getPaymentStatusLabel = (status) => {
    const labels = {
        paid: 'Paid',
        awaiting_mas: 'Pending MAS',
        awaiting_ag: 'Pending AG',
    };
    return labels[status] || status;
};

// Stats cards data
const statsData = computed(() => [
    {
        title: 'Total Vouchers',
        value: stats.value.total_vouchers,
        icon: 'pi pi-list',
        color: 'text-blue-500',
        bgColor: 'bg-blue-50',
    },
    {
        title: 'Paid',
        value: stats.value.paid_count,
        icon: 'pi pi-check-circle',
        color: 'text-green-500',
        bgColor: 'bg-green-50',
    },
    {
        title: 'Pending MAS',
        value: stats.value.pending_mas_count,
        icon: 'pi pi-clock',
        color: 'text-orange-500',
        bgColor: 'bg-orange-50',
    },
    {
        title: 'Pending AG',
        value: stats.value.pending_ag_count,
        icon: 'pi pi-hourglass',
        color: 'text-purple-500',
        bgColor: 'bg-purple-50',
    },
]);

const financialStatsData = computed(() => [
    {
        title: 'Total Amount Paid',
        value: formatCurrency(stats.value.total_amount_paid),
        icon: 'pi pi-money-bill',
        color: 'text-green-500',
        bgColor: 'bg-green-50',
    },
    {
        title: 'Total Amount Pending',
        value: formatCurrency(stats.value.total_amount_pending),
        icon: 'pi pi-clock',
        color: 'text-orange-500',
        bgColor: 'bg-orange-50',
    },
]);

const breadcrumbs = [
    { title: 'Expenditure Control', href: '/expenditure-control' },
    { title: 'Payment Status', href: '#' },
];

// Load vouchers
const loadVouchers = () => {
    router.reload({ only: ['vouchers', 'stats'] });
};

// Search function
const searchVouchers = async () => {
    loading.value = true;
    try {
        const response = await axios.get('/expenditure-control/payment-status/search', {
            params: {
                per_page: lazyParams.value.rows,
                page: lazyParams.value.page,
                search: searchQuery.value,
                payment_status: currentFilter.value,
            },
        });
        vouchers.value = response.data.vouchers.data || [];
        totalRecords.value = response.data.paginator.total || 0;
        
        const statsResponse = await axios.get('/expenditure-control/payment-status/stats');
        stats.value = statsResponse.data;
    } catch (error) {
        console.error('Error searching vouchers:', error);
        toast.add({
            severity: 'error',
            summary: 'Error',
            detail: 'Failed to search data',
            life: 3000,
        });
    } finally {
        loading.value = false;
    }
};

// Refresh data
const refreshData = () => {
    if (searchQuery.value || currentFilter.value !== 'all') {
        searchVouchers();
    } else {
        loadVouchers();
    }
    toast.add({
        severity: 'success',
        summary: 'Refreshed',
        detail: 'Data refreshed successfully',
        life: 2000,
    });
};

// Change filter
const changeFilter = (filter) => {
    currentFilter.value = filter;
    lazyParams.value.page = 1;
    searchVouchers();
};

// Open payment modal
const openPaymentModal = (voucher) => {
    currentVoucher.value = voucher;
    paymentReference.value = '';
    paymentComment.value = '';
    showPaymentModal.value = true;
};

// Handle mark as paid
// const handleMarkAsPaid = () => {
//     if (!paymentReference.value) {
//         toast.add({
//             severity: 'warn',
//             summary: 'Required',
//             detail: 'Please enter a payment reference number.',
//             life: 3000,
//         });
//         return;
//     }

//     isProcessing.value = true;

//     router.post(`/expenditure-control/vouchers/${currentVoucher.value.id}/mark-paid`, {
//         payment_reference: paymentReference.value,
//         comment: paymentComment.value,
//     }, {
//         preserveScroll: true,
//         onSuccess: () => {
//             showPaymentModal.value = false;
//             isProcessing.value = false;
//             toast.add({
//                 severity: 'success',
//                 summary: 'Payment Recorded',
//                 detail: `Voucher ${currentVoucher.value.voucher_number} marked as paid.`,
//                 life: 5000,
//             });
//             refreshData();
//         },
//         onError: (errors) => {
//             isProcessing.value = false;
//             console.error('Payment error:', errors);
//             toast.add({
//                 severity: 'error',
//                 summary: 'Error',
//                 detail: errors.message || 'Failed to mark voucher as paid.',
//                 life: 5000,
//             });
//         },
//     });
// };

// In your payment-status.vue, update the handleMarkAsPaid function
// const handleMarkAsPaid = () => {
//     if (!paymentReference.value) {
//         toast.add({
//             severity: 'warn',
//             summary: 'Required',
//             detail: 'Please enter a payment reference number.',
//             life: 3000,
//         });
//         return;
//     }

//     isProcessing.value = true;

//     // Use Inertia post instead of axios for better redirect handling
//     router.post(`/expenditure-control/vouchers/${currentVoucher.value.id}/mark-paid`, {
//         payment_reference: paymentReference.value,
//         paymentComment: paymentComment.value,
//     }, {
//         preserveScroll: true,
//         onSuccess: () => {
//             showPaymentModal.value = false;
//             isProcessing.value = false;
//             toast.add({
//                 severity: 'success',
//                 summary: 'Payment Recorded',
//                 detail: `Voucher ${currentVoucher.value.voucher_number} marked as paid.`,
//                 life: 5000,
//             });
//             refreshData();
//         },
//         onError: (errors) => {
//             isProcessing.value = false;
//             console.error('Payment error:', errors);
//             toast.add({
//                 severity: 'error',
//                 summary: 'Error',
//                 detail: errors.message || 'Failed to mark voucher as paid.',
//                 life: 5000,
//             });
//         },
//     });
// };

const handleMarkAsPaid = () => {
    // Debug: Log the current values
    console.log('Payment Reference:', paymentReference.value);
    console.log('Payment Comment:', paymentComment.value);
    console.log('Current Voucher:', currentVoucher.value);
    
    if (!paymentReference.value) {
        toast.add({
            severity: 'warn',
            summary: 'Required',
            detail: 'Please enter a payment reference number.',
            life: 3000,
        });
        return;
    }

    isProcessing.value = true;

    // Build the data object explicitly
    const postData = {
        payment_reference: paymentReference.value,
        paymentComment: paymentComment.value || '',
    };

    console.log('Sending data:', postData);

    router.post(`/expenditure-control/vouchers/${currentVoucher.value.id}/mark-paid`, postData, {
        preserveScroll: true,
        onSuccess: () => {
            showPaymentModal.value = false;
            isProcessing.value = false;
            toast.add({
                severity: 'success',
                summary: 'Payment Recorded',
                detail: `Voucher ${currentVoucher.value.voucher_number} marked as paid.`,
                life: 5000,
            });
            refreshData();
        },
        onError: (errors) => {
            isProcessing.value = false;
            console.error('Payment error:', errors);
            toast.add({
                severity: 'error',
                summary: 'Error',
                detail: errors.message || 'Failed to mark voucher as paid.',
                life: 5000,
            });
        },
    });
};

// View voucher details
const viewVoucherDetails = (voucher) => {
    router.visit(`/expenditure-control/vouchers/${voucher.id}`);
};

// Print voucher
const printVoucher = (voucher) => {
    const printUrl = `/vouchers/${voucher.id}/print`;
    window.open(printUrl, '_blank');
};

// Pagination
const onPage = (event) => {
    lazyParams.value.page = event.page + 1;
    lazyParams.value.first = event.first;
    lazyParams.value.rows = event.rows;
    searchVouchers();
};

// Watch for search
watch(searchQuery, () => {
    clearTimeout(debounceTimer);
    debounceTimer = setTimeout(() => {
        lazyParams.value.page = 1;
        searchVouchers();
    }, 500);
});

// Initialize
onMounted(() => {
    if (props.vouchers && props.vouchers.data) {
        vouchers.value = props.vouchers.data;
        totalRecords.value = props.vouchers.total || 0;
    }
    if (props.stats) {
        stats.value = props.stats;
    }
});
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head title="Payment Status - MAS" />
        <Toast />

        <!-- Workflow Info Banner -->
        <div class="mb-4">
            <Message severity="success" :closable="false" class="workflow-banner">
                <div class="flex align-items-center gap-3 flex-wrap">
                    <i class="pi pi-money-bill text-xl"></i>
                    <div>
                        <strong>Management Account Section (MAS) - Payment Tracking</strong>
                        <div class="text-sm mt-1">
                            Track all vouchers awaiting payment and mark them as paid once processed.
                            This helps monitor the financial status of all approved vouchers.
                        </div>
                    </div>
                </div>
            </Message>
        </div>

        <!-- Stats Cards -->
        <div class="mb-4 grid">
            <div v-for="stat in statsData" :key="stat.title" class="col-12 md:col-3">
                <Card class="h-full stat-card">
                    <template #content>
                        <div class="flex align-items-center justify-content-between">
                            <div>
                                <div class="text-500 text-sm font-medium mb-1">{{ stat.title }}</div>
                                <div class="text-900 text-2xl font-bold">{{ stat.value }}</div>
                            </div>
                            <div :class="['border-circle flex align-items-center justify-content-center', stat.bgColor]" style="width: 3rem; height: 3rem;">
                                <i :class="[stat.icon, stat.color, 'text-xl']"></i>
                            </div>
                        </div>
                    </template>
                </Card>
            </div>
        </div>

        <!-- Financial Stats Cards -->
        <div class="mb-4 grid">
            <div v-for="stat in financialStatsData" :key="stat.title" class="col-12 md:col-6">
                <Card class="h-full stat-card financial-card">
                    <template #content>
                        <div class="flex align-items-center justify-content-between">
                            <div>
                                <div class="text-500 text-sm font-medium mb-1">{{ stat.title }}</div>
                                <div class="text-900 text-2xl font-bold">{{ stat.value }}</div>
                            </div>
                            <div :class="['border-circle flex align-items-center justify-content-center', stat.bgColor]" style="width: 3rem; height: 3rem;">
                                <i :class="[stat.icon, stat.color, 'text-xl']"></i>
                            </div>
                        </div>
                    </template>
                </Card>
            </div>
        </div>

        <!-- Filter Buttons -->
        <div class="mb-4 flex gap-2 flex-wrap">
            <Button
                v-for="option in filterOptions"
                :key="option.value"
                :label="option.label"
                :severity="currentFilter === option.value ? 'primary' : 'secondary'"
                :outlined="currentFilter !== option.value"
                @click="changeFilter(option.value)"
                size="small"
            />
        </div>

        <!-- Main Vouchers Table -->
        <Card class="main-card">
            <template #title>
                <div class="flex align-items-center justify-content-between flex-wrap gap-3">
                    <div class="flex align-items-center gap-2">
                        <i class="pi pi-list text-primary"></i>
                        <span>Payment Status List</span>
                        <Badge :value="totalRecords" severity="info" />
                    </div>
                    <div class="flex gap-2">
                        <Button 
                            label="Refresh" 
                            icon="pi pi-refresh" 
                            severity="secondary" 
                            outlined 
                            size="small"
                            @click="refreshData" 
                        />
                    </div>
                </div>
            </template>

            <template #content>
                <div class="table-container">
                    <DataTable
                        v-model:filters="filters"
                        :value="vouchers"
                        dataKey="id"
                        stripedRows
                        responsiveLayout="scroll"
                        class="p-datatable-sm"
                        :emptyMessage="'No vouchers found.'"
                        :paginator="true"
                        :rowsPerPageOptions="[5, 10, 20, 50, 100]"
                        :loading="loading"
                        :rows="lazyParams.rows"
                        :totalRecords="totalRecords"
                        @page="onPage"
                        removableSort
                        :globalFilterFields="['voucher_number', 'narration', 'mda.name', 'payee_name']"
                        size="small"
                        paginatorTemplate="RowsPerPageDropdown FirstPageLink PrevPageLink CurrentPageReport NextPageLink LastPageLink"
                        currentPageReportTemplate="{first} to {last} of {totalRecords}"
                    >
                        <template #header>
                            <div class="flex justify-content-end">
                                <IconField>
                                    <InputIcon>
                                        <i class="pi pi-search" />
                                    </InputIcon>
                                    <InputText 
                                        v-model="searchQuery" 
                                        placeholder="Search by voucher #, MDA, payee..." 
                                        class="p-inputtext-sm"
                                    />
                                </IconField>
                            </div>
                        </template>

                        <!-- Voucher Number Column -->
                        <Column field="voucher_number" header="Voucher #" headerStyle="width: 10%" :sortable="true">
                            <template #body="slotProps">
                                <Link 
                                    :href="`/expenditure-control/vouchers/${slotProps.data.id}`" 
                                    class="font-medium text-primary hover:underline"
                                >
                                    {{ slotProps.data.voucher_number || 'N/A' }}
                                </Link>
                            </template>
                        </Column>

                        <!-- Date Column -->
                        <Column field="voucher_date" header="Date" headerStyle="width: 8%" :sortable="true">
                            <template #body="slotProps">
                                <span class="text-600">{{ formatDate(slotProps.data.voucher_date) }}</span>
                            </template>
                        </Column>

                        <!-- MDA Column -->
                        <Column field="mda.name" header="MDA" headerStyle="width: 15%">
                            <template #body="slotProps">
                                <div>
                                    <div class="font-medium">{{ slotProps.data.mda?.name || 'N/A' }}</div>
                                    <div class="text-500 text-xs">{{ slotProps.data.mda?.code || '' }}</div>
                                </div>
                            </template>
                        </Column>

                        <!-- Payee Column -->
                        <Column field="payee_name" header="Payee" headerStyle="width: 12%">
                            <template #body="slotProps">
                                <span class="text-600">{{ slotProps.data.payee_name || 'N/A' }}</span>
                            </template>
                        </Column>

                        <!-- Voucher Type Column -->
                        <Column field="voucher_type" header="Type" headerStyle="width: 8%">
                            <template #body="slotProps">
                                <Tag 
                                    :value="slotProps.data.voucher_type?.toUpperCase() || 'N/A'" 
                                    :severity="getVoucherTypeSeverity(slotProps.data.voucher_type)"
                                    size="small"
                                />
                            </template>
                        </Column>

                        <!-- Amount Column -->
                        <Column field="total_amount" header="Amount" headerStyle="width: 12%" bodyClass="font-bold text-right">
                            <template #body="slotProps">
                                <span class="text-900">{{ formatCurrency(slotProps.data.total_amount) }}</span>
                            </template>
                        </Column>

                        <!-- Bank Column -->
                        <Column field="bank_activity" header="Bank" headerStyle="width: 15%">
                            <template #body="slotProps">
                                <div v-if="slotProps.data.bank_activity">
                                    <div class="font-medium">{{ slotProps.data.bank_activity.bank_name }}</div>
                                    <div class="text-500 text-xs">{{ slotProps.data.bank_activity.account_number }}</div>
                                    <div class="text-500 text-xs">{{ slotProps.data.bank_activity.tag }}</div>
                                </div>
                                <span v-else class="text-500">Not Assigned</span>
                            </template>
                        </Column>

                        <!-- Payment Status Column -->
                        <Column field="payment_status" header="Payment Status" headerStyle="width: 10%">
                            <template #body="slotProps">
                                <Tag 
                                    :value="getPaymentStatusLabel(slotProps.data.payment_status)" 
                                    :severity="getPaymentStatusSeverity(slotProps.data.payment_status)"
                                    size="small"
                                />
                            </template>
                        </Column>

                        <!-- Payment Date Column -->
                        <Column field="payment_date" header="Payment Date" headerStyle="width: 10%">
                            <template #body="slotProps">
                                <span class="text-600">{{ formatDate(slotProps.data.payment_date) || 'Pending' }}</span>
                            </template>
                        </Column>

                        <!-- Actions Column -->
                        <Column header="Actions" headerStyle="width: 10%" bodyClass="text-center">
                            <template #body="slotProps">
                                <div class="flex gap-1 justify-content-center">
                                    <Button
                                        icon="pi pi-print"
                                        severity="info"
                                        text
                                        rounded
                                        size="small"
                                        v-tooltip.top="'Print Voucher'"
                                        @click="printVoucher(slotProps.data)"
                                    />
                                    <Button
                                        icon="pi pi-eye"
                                        severity="info"
                                        text
                                        rounded
                                        size="small"
                                        v-tooltip.top="'View Details'"
                                        @click="viewVoucherDetails(slotProps.data)"
                                    />
                                    <Button
                                        v-if="slotProps.data.payment_status === 'paid'"
                                        icon="pi pi-check-circle"
                                        severity="success"
                                        text
                                        rounded
                                        size="small"
                                        v-tooltip.top="'Mark as Paid'"
                                        @click="openPaymentModal(slotProps.data)"
                                    />
                                </div>
                            </template>
                        </Column>
                    </DataTable>
                </div>
            </template>
        </Card>

        <!-- Mark as Paid Modal -->
        <!-- <Dialog
            v-model:visible="showPaymentModal"
            :style="{ width: '550px' }"
            header="Mark Voucher as Paid"
            :modal="true"
            class="payment-dialog"
            :closable="!isProcessing"
        >
            <div class="flex flex-column gap-3">
                <div class="flex align-items-center gap-3 p-3 bg-green-50 border-round">
                    <i class="pi pi-info-circle text-green-500 text-xl"></i>
                    <div>
                        <div class="font-semibold">Voucher: {{ currentVoucher?.voucher_number }}</div>
                        <div class="text-sm">Amount: {{ formatCurrency(currentVoucher?.total_amount) }}</div>
                        <div class="text-sm">Payee: {{ currentVoucher?.payee_name }}</div>
                    </div>
                </div>

                <div class="field">
                    <label class="font-semibold block mb-2">
                        Payment Reference Number <span class="text-red-500">*</span>
                    </label>
                    <InputText
                        v-model="paymentReference"
                        placeholder="Enter payment reference number (e.g., Teller #, Transfer Ref)"
                        class="w-full"
                    />
                    <small class="text-500 mt-1 block">
                        <i class="pi pi-info-circle mr-1"></i>
                        This reference will be recorded for audit purposes
                    </small>
                </div>

                <div class="field">
                    <label class="font-semibold block mb-2">
                        Comment (Optional)
                    </label>
                    <Textarea
                        v-model="paymentComment"
                        rows="3"
                        placeholder="Add any additional notes about the payment..."
                        class="w-full"
                        autoResize
                    />
                </div>

                <div class="border-round bg-yellow-50 p-3">
                    <div class="flex align-items-center gap-2">
                        <i class="pi pi-exclamation-triangle text-yellow-600"></i>
                        <span class="text-sm">This action will mark the voucher as paid and close it permanently.</span>
                    </div>
                </div>

                <div v-if="isProcessing" class="flex align-items-center justify-content-center gap-2 p-2">
                    <ProgressSpinner style="width: 30px; height: 30px" strokeWidth="4" />
                    <span>Processing...</span>
                </div>
            </div>

            <template #footer>
                <Button label="Cancel" icon="pi pi-times" @click="showPaymentModal = false" text :disabled="isProcessing" />
                <Button label="Mark as Paid" icon="pi pi-check-circle" severity="success" @click="handleMarkAsPaid" :loading="isProcessing" />
            </template>
        </Dialog> -->
        <!-- In your payment-status.vue, update the modal -->
<Dialog
    v-model:visible="showPaymentModal"
    :style="{ width: '550px' }"
    header="Mark Voucher as Paid"
    :modal="true"
    class="payment-dialog"
    :closable="!isProcessing"
>
    <div class="flex flex-column gap-3">
        <div class="flex align-items-center gap-3 p-3 bg-green-50 border-round">
            <i class="pi pi-info-circle text-green-500 text-xl"></i>
            <div>
                <div class="font-semibold">Voucher: {{ currentVoucher?.voucher_number }}</div>
                <div class="text-sm">Amount: {{ formatCurrency(currentVoucher?.total_amount) }}</div>
                <div class="text-sm">Payee: {{ currentVoucher?.payee_name }}</div>
            </div>
        </div>

        <div class="field">
            <label class="font-semibold block mb-2">
                Payment Reference Number <span class="text-red-500">*</span>
            </label>
            <InputText
                v-model="paymentReference"
                placeholder="Enter payment reference number (e.g., Teller #, Transfer Ref)"
                class="w-full"
                :class="{ 'p-invalid': !paymentReference && showPaymentModal }"
            />
            <small class="text-500 mt-1 block">
                <i class="pi pi-info-circle mr-1"></i>
                This reference will be recorded for audit purposes
            </small>
        </div>

        <div class="field">
            <label class="font-semibold block mb-2">
                Comment (Optional)
            </label>
            <Textarea
                v-model="paymentComment"
                rows="3"
                placeholder="Add any additional notes about the payment..."
                class="w-full"
                autoResize
            />
        </div>

        <!-- Debug: Show what will be sent -->
        <div class="border-round bg-gray-50 p-2 text-xs" v-if="false">
            <strong>Debug:</strong><br>
            Reference: {{ paymentReference }}<br>
            Comment: {{ paymentComment }}
        </div>

        <div class="border-round bg-yellow-50 p-3">
            <div class="flex align-items-center gap-2">
                <i class="pi pi-exclamation-triangle text-yellow-600"></i>
                <span class="text-sm">This action will mark the voucher as paid and close it permanently.</span>
            </div>
        </div>

        <div v-if="isProcessing" class="flex align-items-center justify-content-center gap-2 p-2">
            <ProgressSpinner style="width: 30px; height: 30px" strokeWidth="4" />
            <span>Processing...</span>
        </div>
    </div>

    <template #footer>
        <Button label="Cancel" icon="pi pi-times" @click="showPaymentModal = false" text :disabled="isProcessing" />
        <Button label="Mark as Paid" icon="pi pi-check-circle" severity="success" @click="handleMarkAsPaid" :loading="isProcessing" />
    </template>
</Dialog>
    </AppLayout>
</template>

<style scoped>
.stat-card :deep(.p-card) {
    border-radius: 1rem;
    transition: transform 0.2s, box-shadow 0.2s;
}

.stat-card :deep(.p-card):hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
}

.financial-card :deep(.p-card) {
    background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
}

.main-card :deep(.p-card) {
    border-radius: 1rem;
}

.table-container :deep(.p-datatable) {
    border-radius: 0.75rem;
    overflow: hidden;
}

.table-container :deep(.p-datatable-thead > tr > th) {
    background: #f8fafc;
    color: #1e293b;
    font-weight: 600;
    padding: 0.75rem 1rem;
}

.table-container :deep(.p-datatable-tbody > tr) {
    transition: background-color 0.2s;
}

.table-container :deep(.p-datatable-tbody > tr:hover) {
    background: #f1f5f9;
}

.workflow-banner :deep(.p-message) {
    background: linear-gradient(135deg, #dcfce7 0%, #bbf7d0 100%);
    border: none;
    border-radius: 0.75rem;
}

.payment-dialog :deep(.p-dialog-header) {
    background: #f8fafc;
    border-bottom: 1px solid #e2e8f0;
}

.payment-dialog :deep(.p-dialog-content) {
    padding: 1.5rem;
}

@media (max-width: 768px) {
    .table-container :deep(.p-datatable) {
        font-size: 0.875rem;
    }
    
    .table-container :deep(.p-datatable-thead > tr > th),
    .table-container :deep(.p-datatable-tbody > tr > td) {
        padding: 0.5rem;
    }
}
</style>