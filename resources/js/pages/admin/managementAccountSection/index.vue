<!-- resources/js/pages/admin/management-account-section/index.vue -->
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

// State
const vouchers = ref([]);
const searchQuery = ref('');
const loading = ref(false);
const totalRecords = ref(0);
const showRejectionModal = ref(false);
const showApprovalModal = ref(false);
const currentVoucher = ref(null);
const rejectionReason = ref('');
const rejectionTouched = ref(false);
const isProcessing = ref(false);

// Stats
const stats = ref({
    pending_count: 0,
    approved_today: 0,
    rejected_today: 0,
    total_processed: 0,
});

const filters = ref({
    global: { value: null, matchMode: FilterMatchMode.CONTAINS },
    voucher_number: { value: null, matchMode: FilterMatchMode.CONTAINS },
    voucher_date: { value: null, matchMode: FilterMatchMode.CONTAINS },
    narration: { value: null, matchMode: FilterMatchMode.CONTAINS },
    voucher_type: { value: null, matchMode: FilterMatchMode.CONTAINS },
});

const lazyParams = ref({
    first: 0,
    rows: 10,
    page: 1,
});

let debounceTimer = null;

// Props from Laravel controller
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
            pending_count: 0,
            approved_today: 0,
            rejected_today: 0,
            total_processed: 0,
        }),
    },
});

// Stats cards data
const statsData = computed(() => [
    {
        title: 'Pending MAS Review',
        value: stats.value.pending_count,
        icon: 'pi pi-clock',
        color: 'text-blue-500',
        bgColor: 'bg-blue-50',
    },
    {
        title: 'Closed Today',
        value: stats.value.approved_today,
        icon: 'pi pi-check-circle',
        color: 'text-green-500',
        bgColor: 'bg-green-50',
    },
    {
        title: 'Rejected Today',
        value: stats.value.rejected_today,
        icon: 'pi pi-times-circle',
        color: 'text-red-500',
        bgColor: 'bg-red-50',
    },
    {
        title: 'Total Processed',
        value: stats.value.total_processed,
        icon: 'pi pi-chart-bar',
        color: 'text-purple-500',
        bgColor: 'bg-purple-50',
    },
]);

const breadcrumbs = [
    { title: 'Management Account Section', href: '/management-account-section' },
    { title: 'Queue', href: '#' },
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

// Get voucher type badge severity
const getVoucherTypeSeverity = (type) => {
    const types = {
        standard: 'info',
        prepayment: 'warning',
        salary: 'success',
    };
    return types[type?.toLowerCase()] || 'info';
};

// Get status badge severity
const getStatusSeverity = (status) => {
    const statuses = {
        ag_approved: 'warning',
        closed: 'success',
        mas_rejected: 'danger',
    };
    return statuses[status?.toLowerCase()] || 'info';
};

// Get status display name
const getStatusDisplayName = (status) => {
    const names = {
        ag_approved: 'Forwarded from AG',
        closed: 'Closed',
        mas_rejected: 'Rejected',
    };
    return names[status?.toLowerCase()] || status || 'Unknown';
};

// Handle search - use Inertia visit instead of axios
const handleSearch = () => {
    router.get('/management-account-section', 
        { search: searchQuery.value, page: 1 },
        { preserveScroll: true, preserveState: true, only: ['vouchers', 'stats'] }
    );
};

// Refresh data
const refreshData = () => {
    searchQuery.value = '';
    router.reload({ only: ['vouchers', 'stats'] });
    toast.add({
        severity: 'success',
        summary: 'Refreshed',
        detail: 'Data refreshed successfully',
        life: 2000,
    });
};

// Open approval modal
const openApproveModal = (voucher) => {
    currentVoucher.value = voucher;
    showApprovalModal.value = true;
};

// Open rejection modal
const openRejectModal = (voucher) => {
    currentVoucher.value = voucher;
    rejectionReason.value = '';
    rejectionTouched.value = false;
    showRejectionModal.value = true;
};

// Handle approval (Close voucher)
const handleApprove = () => {
    isProcessing.value = true;
    
    router.post(`/management-account-section/vouchers/${currentVoucher.value.id}/approve`, {}, {
        preserveScroll: true,
        onSuccess: () => {
            showApprovalModal.value = false;
            isProcessing.value = false;
            toast.add({
                severity: 'success',
                summary: 'Voucher Closed',
                detail: `Voucher ${currentVoucher.value.voucher_number} has been closed successfully.`,
                life: 5000,
            });
            refreshData();
        },
        onError: (errors) => {
            isProcessing.value = false;
            console.error('Approval error:', errors);
            toast.add({
                severity: 'error',
                summary: 'Error',
                detail: errors.message || 'Failed to close voucher.',
                life: 5000,
            });
        },
    });
};

// Handle rejection
const handleReject = () => {
    if (!rejectionReason.value || rejectionReason.value.length < 10) {
        toast.add({
            severity: 'warn',
            summary: 'Required',
            detail: 'Please provide a detailed reason for rejection (minimum 10 characters).',
            life: 3000,
        });
        return;
    }

    isProcessing.value = true;

    router.post(`/management-account-section/vouchers/${currentVoucher.value.id}/reject`, {
        reason: rejectionReason.value,
    }, {
        preserveScroll: true,
        onSuccess: () => {
            toast.add({
                severity: 'info',
                summary: 'Rejected',
                detail: `Voucher ${currentVoucher.value.voucher_number} has been rejected.`,
                life: 4000,
            });
            showRejectionModal.value = false;
            isProcessing.value = false;
            refreshData();
        },
        onError: (errors) => {
            isProcessing.value = false;
            console.error('Rejection error:', errors);
            toast.add({
                severity: 'error',
                summary: 'Error',
                detail: errors.message || 'Failed to reject voucher.',
                life: 5000,
            });
        },
    });
};

// View voucher details
const viewVoucherDetails = (voucher) => {
    router.visit(`/management-account-section/vouchers/${voucher.id}`);
};

// Print voucher
const printVoucher = (voucher) => {
    const printUrl = `/vouchers/${voucher.id}/print`;
    window.open(printUrl, '_blank');
};

// Pagination
const onPage = (event) => {
    const page = event.page + 1;
    const params = { page, per_page: event.rows };
    if (searchQuery.value) {
        params.search = searchQuery.value;
    }
    router.get('/management-account-section', params, {
        preserveScroll: true,
        preserveState: true,
        only: ['vouchers', 'stats'],
    });
};

// Watch for search with debounce
watch(searchQuery, () => {
    clearTimeout(debounceTimer);
    debounceTimer = setTimeout(() => {
        handleSearch();
    }, 500);
});

// Initialize - use props data directly (NO API CALLS)
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
        <Head title="Management Account Section Queue" />
        <Toast />

        <!-- Workflow Info Banner -->
        <div class="mb-4">
            <Message severity="success" :closable="false" class="workflow-banner">
                <div class="flex align-items-center gap-3 flex-wrap">
                    <i class="pi pi-check-circle text-xl"></i>
                    <div>
                        <strong>Management Account Section (MAS) - Step 6 of 6 (Final Stage)</strong>
                        <div class="text-sm mt-1">
                            Vouchers approved by Accountant General are reviewed here for final closure.
                            Once approved, the voucher will be <strong>closed</strong> and the process is complete.
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

        <!-- Main Vouchers Table -->
        <Card class="main-card">
            <template #title>
                <div class="flex align-items-center justify-content-between flex-wrap gap-3">
                    <div class="flex align-items-center gap-2">
                        <i class="pi pi-list text-primary"></i>
                        <span>Management Account Section Queue</span>
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
                        :emptyMessage="'No vouchers pending Management Account Section review.'"
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
                                    :href="`/management-account-section/vouchers/${slotProps.data.id}`" 
                                    class="font-medium text-primary hover:underline"
                                >
                                    {{ slotProps.data.voucher_number || 'N/A' }}
                                </Link>
                            </template>
                        </Column>

                        <!-- Date Column -->
                        <Column field="voucher_date" header="Date" headerStyle="width: 10%" :sortable="true">
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

                        <!-- Bank Column - NEW -->
                        <Column field="bank_activity" header="Destination Bank" headerStyle="width: 18%">
                            <template #body="slotProps">
                                <div v-if="slotProps.data.bank_activity">
                                    <div class="font-medium">{{ slotProps.data.bank_activity.bank_name }}</div>
                                    <div class="text-500 text-xs">{{ slotProps.data.bank_activity.account_number }}</div>
                                    <div class="text-500 text-xs">{{ slotProps.data.bank_activity.tag }}</div>
                                </div>
                                <span v-else class="text-500">Not Assigned</span>
                            </template>
                        </Column>

                        <!-- Status Column -->
                        <Column field="status" header="Status" headerStyle="width: 8%">
                            <template #body="slotProps">
                                <Tag 
                                    :value="getStatusDisplayName(slotProps.data.status)" 
                                    :severity="getStatusSeverity(slotProps.data.status)"
                                    size="small"
                                />
                            </template>
                        </Column>

                        <!-- Actions Column -->
                        <Column header="Actions" headerStyle="width: 12%" bodyClass="text-center">
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
                                        icon="pi pi-check-circle"
                                        severity="success"
                                        text
                                        rounded
                                        size="small"
                                        v-tooltip.top="'Close Voucher'"
                                        @click="openApproveModal(slotProps.data)"
                                    />
                                    <Button
                                        icon="pi pi-times-circle"
                                        severity="danger"
                                        text
                                        rounded
                                        size="small"
                                        v-tooltip.top="'Reject Voucher'"
                                        @click="openRejectModal(slotProps.data)"
                                    />
                                </div>
                            </template>
                        </Column>
                    </DataTable>
                </div>
            </template>
        </Card>

        <!-- Approval Modal (Close Voucher) -->
        <Dialog
            v-model:visible="showApprovalModal"
            :style="{ width: '550px' }"
            header="Close Voucher"
            :modal="true"
            class="approval-dialog"
            :closable="!isProcessing"
        >
            <div class="flex flex-column gap-3">
                <div class="flex align-items-center gap-3 p-3 bg-green-50 border-round">
                    <i class="pi pi-info-circle text-green-500 text-xl"></i>
                    <div>
                        <div class="font-semibold">Voucher: {{ currentVoucher?.voucher_number }}</div>
                        <div class="text-sm">Amount: {{ formatCurrency(currentVoucher?.total_amount) }}</div>
                        <div class="text-sm">Type: {{ currentVoucher?.voucher_type?.toUpperCase() }}</div>
                    </div>
                </div>

                <!-- Bank Information Display -->
                <div v-if="currentVoucher?.bank_activity" class="border-round bg-blue-50 p-3">
                    <div class="flex align-items-center gap-2 mb-2">
                        <i class="pi pi-building text-blue-600"></i>
                        <span class="font-semibold">Payment Bank Details:</span>
                    </div>
                    <div class="text-sm">
                        <div><strong>Bank:</strong> {{ currentVoucher.bank_activity.bank_name }}</div>
                        <div><strong>Account:</strong> {{ currentVoucher.bank_activity.account_number }}</div>
                        <div><strong>Tag:</strong> {{ currentVoucher.bank_activity.tag }}</div>
                    </div>
                </div>
                <div v-else class="border-round bg-red-50 p-3">
                    <div class="flex align-items-center gap-2">
                        <i class="pi pi-exclamation-triangle text-red-600"></i>
                        <span class="text-sm">No bank assigned. Please contact Accountant General.</span>
                    </div>
                </div>

                <div class="border-round bg-yellow-50 p-3">
                    <div class="flex align-items-center gap-2 mb-2">
                        <i class="pi pi-flag-checkered text-yellow-600"></i>
                        <span class="font-semibold">Final Stage:</span>
                        <Tag value="Close Voucher" severity="success" />
                    </div>
                    <div class="text-sm text-600">
                        This is the final approval stage. Closing the voucher will complete the workflow.
                        <strong>Action cannot be undone.</strong>
                    </div>
                </div>

                <div class="border-round bg-blue-50 p-3">
                    <div class="flex align-items-center gap-2">
                        <i class="pi pi-check-circle text-blue-600"></i>
                        <span class="font-semibold">Confirmation:</span>
                        <span class="text-sm">I confirm that this voucher is ready to be closed.</span>
                    </div>
                </div>

                <div v-if="isProcessing" class="flex align-items-center justify-content-center gap-2 p-2">
                    <ProgressSpinner style="width: 30px; height: 30px" strokeWidth="4" />
                    <span>Processing...</span>
                </div>
            </div>

            <template #footer>
                <Button label="Cancel" icon="pi pi-times" @click="showApprovalModal = false" text :disabled="isProcessing" />
                <Button label="Close Voucher" icon="pi pi-check-circle" severity="success" @click="handleApprove" :loading="isProcessing" />
            </template>
        </Dialog>

        <!-- Rejection Modal -->
        <Dialog
            v-model:visible="showRejectionModal"
            :style="{ width: '550px' }"
            header="Reject Voucher"
            :modal="true"
            class="rejection-dialog"
            :closable="!isProcessing"
        >
            <div class="flex flex-column gap-3">
                <div class="flex align-items-center gap-3 p-3 bg-red-50 border-round">
                    <i class="pi pi-exclamation-triangle text-red-500 text-xl"></i>
                    <div>
                        <div class="font-semibold">Voucher: {{ currentVoucher?.voucher_number }}</div>
                        <div class="text-sm">This action will reject the voucher at the final stage.</div>
                    </div>
                </div>

                <div class="field">
                    <label class="font-semibold block mb-2">
                        Reason for Rejection <span class="text-red-500">*</span>
                    </label>
                    <Textarea
                        v-model="rejectionReason"
                        rows="4"
                        placeholder="Provide detailed reason for rejection. This will be recorded in the audit log."
                        :class="{ 'p-invalid': !rejectionReason && rejectionTouched }"
                        @blur="rejectionTouched = true"
                        class="w-full"
                        autoResize
                    />
                    <div class="flex justify-content-between mt-1">
                        <small class="text-600">
                            <i class="pi pi-info-circle mr-1"></i>
                            Minimum 10 characters required
                        </small>
                        <small :class="rejectionReason.length < 10 ? 'text-red-500' : 'text-green-500'">
                            {{ rejectionReason.length }}/10
                        </small>
                    </div>
                    <small v-if="!rejectionReason && rejectionTouched" class="text-red-500 block mt-1">
                        <i class="pi pi-exclamation-circle mr-1"></i>
                        Rejection reason is required
                    </small>
                </div>

                <div class="border-round bg-gray-50 p-3">
                    <div class="flex align-items-center gap-2">
                        <i class="pi pi-info-circle text-gray-600"></i>
                        <span class="text-sm">This action will be recorded in the audit trail.</span>
                    </div>
                </div>

                <div v-if="isProcessing" class="flex align-items-center justify-content-center gap-2 p-2">
                    <ProgressSpinner style="width: 30px; height: 30px" strokeWidth="4" />
                    <span>Processing...</span>
                </div>
            </div>

            <template #footer>
                <Button label="Cancel" icon="pi pi-times" @click="showRejectionModal = false" text :disabled="isProcessing" />
                <Button
                    label="Confirm Rejection"
                    icon="pi pi-arrow-left"
                    severity="danger"
                    @click="handleReject"
                    :disabled="!rejectionReason || rejectionReason.length < 10 || isProcessing"
                    :loading="isProcessing"
                />
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

.approval-dialog :deep(.p-dialog-header),
.rejection-dialog :deep(.p-dialog-header) {
    background: #f8fafc;
    border-bottom: 1px solid #e2e8f0;
}

.approval-dialog :deep(.p-dialog-content),
.rejection-dialog :deep(.p-dialog-content) {
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