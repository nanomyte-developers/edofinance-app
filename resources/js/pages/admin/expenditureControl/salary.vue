<!-- resources/js/pages/admin/expenditure-control/salary.vue -->
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
import Dropdown from 'primevue/dropdown';
import { useToast } from 'primevue/usetoast';
import { computed, onMounted, ref, watch } from 'vue';

const toast = useToast();

// State
const vouchers = ref([]);
const searchQuery = ref('');
const loading = ref(false);
const totalRecords = ref(0);
const showApprovalModal = ref(false);
const showRejectionModal = ref(false);
const showForwardModal = ref(false);
const currentVoucher = ref(null);
const rejectionReason = ref('');
const rejectionTouched = ref(false);
const isProcessing = ref(false);
const selectedSalaryType = ref('');
const selectedStatus = ref('');

// Stats
const stats = ref({
    pending_count: 0,
    ec_approved_count: 0,
    forwarded_to_inspectorate: 0,
    total_salary: 0,
});

const filters = ref({
    global: { value: null, matchMode: FilterMatchMode.CONTAINS },
    voucher_number: { value: null, matchMode: FilterMatchMode.CONTAINS },
    voucher_date: { value: null, matchMode: FilterMatchMode.CONTAINS },
    narration: { value: null, matchMode: FilterMatchMode.CONTAINS },
    salary_type: { value: null, matchMode: FilterMatchMode.CONTAINS },
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
            ec_approved_count: 0,
            forwarded_to_inspectorate: 0,
            total_salary: 0,
        }),
    },
    salaryTypes: {
        type: Array,
        default: () => ['monthly', 'bonus', 'allowance', 'gratuity', 'pension', 'other'],
    },
});

// Stats cards data
const statsData = computed(() => [
    {
        title: 'Pending EC Review',
        value: stats.value.pending_count,
        icon: 'pi pi-clock',
        color: 'text-blue-500',
        bgColor: 'bg-blue-50',
        detail: 'Awaiting EC approval',
    },
    {
        title: 'EC Approved',
        value: stats.value.ec_approved_count,
        icon: 'pi pi-check-circle',
        color: 'text-green-500',
        bgColor: 'bg-green-50',
        detail: 'Ready for Inspectorate',
    },
    {
        title: 'With Inspectorate',
        value: stats.value.forwarded_to_inspectorate,
        icon: 'pi pi-send',
        color: 'text-purple-500',
        bgColor: 'bg-purple-50',
        detail: 'Pending Inspectorate review',
    },
    {
        title: 'Total Salary Vouchers',
        value: stats.value.total_salary,
        icon: 'pi pi-users',
        color: 'text-orange-500',
        bgColor: 'bg-orange-50',
        detail: 'All salary payments',
    },
]);

const breadcrumbs = [
    { title: 'Expenditure Control', href: '/expenditure-control' },
    { title: 'Salary Queue', href: '#' },
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

// Format salary type
const getSalaryTypeLabel = (type) => {
    const types = {
        monthly: 'Monthly Salary',
        bonus: 'Bonus',
        allowance: 'Allowance',
        gratuity: 'Gratuity',
        pension: 'Pension',
        other: 'Other',
    };
    return types[type?.toLowerCase()] || type || 'N/A';
};

// Get salary type badge severity
const getSalaryTypeSeverity = (type) => {
    const types = {
        monthly: 'info',
        bonus: 'success',
        allowance: 'warning',
        gratuity: 'danger',
        pension: 'secondary',
        other: 'info',
    };
    return types[type?.toLowerCase()] || 'info';
};

// Get status badge severity
const getStatusSeverity = (status) => {
    const statuses = {
        fa_approved: 'warning',
        ec_approved: 'success',
        inspectorate_pending: 'info',
        inspectorate_approved: 'info',
        tco_approved: 'success',
        sent_back: 'danger',
        rejected: 'danger',
    };
    return statuses[status?.toLowerCase()] || 'info';
};

// Get status display name
const getStatusDisplayName = (status) => {
    const names = {
        fa_approved: 'FA Approved',
        ec_approved: 'EC Approved',
        inspectorate_pending: 'With Inspectorate',
        inspectorate_approved: 'Inspectorate Approved',
        tco_approved: 'TCO Approved (Closed)',
        sent_back: 'Sent Back',
        rejected: 'Rejected',
    };
    return names[status?.toLowerCase()] || status || 'Unknown';
};

// Get workflow step
const getWorkflowStep = (status) => {
    const steps = {
        fa_approved: 1,
        ec_approved: 2,
        inspectorate_pending: 3,
        inspectorate_approved: 4,
        tco_approved: 5,
    };
    return steps[status?.toLowerCase()] || 0;
};

// Get progress percentage
const getProgressPercentage = (status) => {
    const step = getWorkflowStep(status);
    return Math.round((step / 5) * 100);
};

// Load vouchers
const loadVouchers = () => {
    router.reload({ only: ['vouchers', 'stats'] });
};

// Search function
const searchVouchers = async () => {
    loading.value = true;
    try {
        const response = await axios.get('/expenditure-control/salary/search', {
            params: {
                per_page: lazyParams.value.rows,
                page: lazyParams.value.page,
                search: searchQuery.value,
                salary_type: selectedSalaryType.value,
                status: selectedStatus.value,
            },
        });
        vouchers.value = response.data.vouchers.data || [];
        totalRecords.value = response.data.paginator.total || 0;
        
        const statsResponse = await axios.get('/expenditure-control/salary/stats');
        stats.value = statsResponse.data;
    } catch (error) {
        console.error('Error searching salary vouchers:', error);
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
    if (searchQuery.value || selectedSalaryType.value || selectedStatus.value) {
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

// Open forward to inspectorate modal
const openForwardModal = (voucher) => {
    currentVoucher.value = voucher;
    showForwardModal.value = true;
};

// Handle approval (EC approves, forwards to Inspectorate)
const handleApprove = () => {
    isProcessing.value = true;
    
    router.post(`/expenditure-control/salary-vouchers/${currentVoucher.value.id}/approve`, {}, {
        preserveScroll: true,
        onSuccess: () => {
            showApprovalModal.value = false;
            isProcessing.value = false;
            toast.add({
                severity: 'success',
                summary: 'Approved',
                detail: `Salary voucher ${currentVoucher.value.voucher_number} approved. Forward to Inspectorate next.`,
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
                detail: errors.message || 'Failed to process voucher.',
                life: 5000,
            });
        },
    });
};

// Handle forward to Inspectorate
const handleForwardToInspectorate = () => {
    isProcessing.value = true;
    
    router.post(`/expenditure-control/salary-vouchers/${currentVoucher.value.id}/forward-inspectorate`, {}, {
        preserveScroll: true,
        onSuccess: () => {
            showForwardModal.value = false;
            isProcessing.value = false;
            toast.add({
                severity: 'info',
                summary: 'Forwarded',
                detail: `Salary voucher ${currentVoucher.value.voucher_number} forwarded to Inspectorate.`,
                life: 5000,
            });
            refreshData();
        },
        onError: (errors) => {
            isProcessing.value = false;
            console.error('Forward error:', errors);
            toast.add({
                severity: 'error',
                summary: 'Error',
                detail: errors.message || 'Failed to forward voucher.',
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

    router.post(`/expenditure-control/salary-vouchers/${currentVoucher.value.id}/reject`, {
        reason: rejectionReason.value,
    }, {
        preserveScroll: true,
        onSuccess: () => {
            toast.add({
                severity: 'info',
                summary: 'Rejected',
                detail: `Salary voucher ${currentVoucher.value.voucher_number} returned to FA.`,
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
    router.visit(`/expenditure-control/salary-vouchers/${voucher.id}`);
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
    if (searchQuery.value || selectedSalaryType.value || selectedStatus.value) {
        searchVouchers();
    } else {
        loadVouchers();
    }
};

// Watch for search
watch([searchQuery, selectedSalaryType, selectedStatus], () => {
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
        <Head title="Salary Vouchers - Expenditure Control" />
        <Toast />

        <!-- Workflow Info Banner -->
        <div class="mb-4">
            <Message severity="info" :closable="false" class="workflow-banner">
                <div class="flex align-items-center gap-3 flex-wrap">
                    <i class="pi pi-wallet text-xl"></i>
                    <div>
                        <strong>Salary Voucher Workflow - Step 4 of 6</strong>
                        <div class="text-sm mt-1">
                            Salary/Pension/Gratuity vouchers approved by Final Accounts are reviewed here.
                            <strong>Flow: DFA → IA → FA → EC → Inspectorate → TCO → Closed</strong>
                            <span class="ml-2 text-xs bg-yellow-200 px-2 py-1 border-round">
                                <i class="pi pi-info-circle mr-1"></i>
                                Skip Accountant General for salary payments
                            </span>
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
                                <div class="text-500 text-xs mt-1">{{ stat.detail }}</div>
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
                        <i class="pi pi-money-bill text-primary"></i>
                        <span>Salary Vouchers Queue</span>
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
                        <Link href="/expenditure-control">
                            <Button 
                                label="Regular Vouchers" 
                                icon="pi pi-list" 
                                severity="primary" 
                                outlined 
                                size="small"
                            />
                        </Link>
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
                        :emptyMessage="'No salary vouchers pending Expenditure Control review.'"
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
                            <div class="flex flex-wrap gap-3 align-items-center">
                                <IconField class="flex-1">
                                    <InputIcon>
                                        <i class="pi pi-search" />
                                    </InputIcon>
                                    <InputText 
                                        v-model="searchQuery" 
                                        placeholder="Search by voucher #, MDA, payee..." 
                                        class="p-inputtext-sm w-full"
                                    />
                                </IconField>
                                
                                <Dropdown
                                    v-model="selectedSalaryType"
                                    :options="salaryTypes"
                                    optionLabel="label"
                                    placeholder="Filter by Salary Type"
                                    class="p-inputtext-sm w-12rem"
                                    :showClear="true"
                                >
                                    <template #value="slotProps">
                                        <span v-if="slotProps.value">
                                            {{ getSalaryTypeLabel(slotProps.value) }}
                                        </span>
                                        <span v-else>All Types</span>
                                    </template>
                                    <template #option="slotProps">
                                        <span>{{ getSalaryTypeLabel(slotProps.option) }}</span>
                                    </template>
                                </Dropdown>
                                
                                <Dropdown
                                    v-model="selectedStatus"
                                    :options="['fa_approved', 'ec_approved', 'inspectorate_pending', 'inspectorate_approved', 'tco_approved']"
                                    placeholder="Filter by Status"
                                    class="p-inputtext-sm w-12rem"
                                    :showClear="true"
                                >
                                    <template #value="slotProps">
                                        <span v-if="slotProps.value">
                                            {{ getStatusDisplayName(slotProps.value) }}
                                        </span>
                                        <span v-else>All Status</span>
                                    </template>
                                    <template #option="slotProps">
                                        <span>{{ getStatusDisplayName(slotProps.option) }}</span>
                                    </template>
                                </Dropdown>
                            </div>
                        </template>

                        <!-- Voucher Number Column -->
                        <Column field="voucher_number" header="Voucher #" headerStyle="width: 10%" :sortable="true">
                            <template #body="slotProps">
                                <Link 
                                    :href="`/expenditure-control/salary-vouchers/${slotProps.data.id}`" 
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
                        <Column field="mda.name" header="MDA" headerStyle="width: 12%">
                            <template #body="slotProps">
                                <div>
                                    <div class="font-medium">{{ slotProps.data.mda?.name || 'N/A' }}</div>
                                    <div class="text-500 text-xs">{{ slotProps.data.mda?.code || '' }}</div>
                                </div>
                            </template>
                        </Column>

                        <!-- Payee Column -->
                        <Column field="payee_name" header="Payee" headerStyle="width: 10%">
                            <template #body="slotProps">
                                <span class="text-600">{{ slotProps.data.payee_name || 'N/A' }}</span>
                            </template>
                        </Column>

                        <!-- Salary Type Column -->
                        <Column field="salary_type" header="Salary Type" headerStyle="width: 10%">
                            <template #body="slotProps">
                                <Tag 
                                    :value="getSalaryTypeLabel(slotProps.data.salary_type)" 
                                    :severity="getSalaryTypeSeverity(slotProps.data.salary_type)"
                                    size="small"
                                />
                            </template>
                        </Column>

                        <!-- Amount Column -->
                        <Column field="total_amount" header="Amount" headerStyle="width: 10%" bodyClass="font-bold text-right">
                            <template #body="slotProps">
                                <span class="text-900">{{ formatCurrency(slotProps.data.total_amount) }}</span>
                            </template>
                        </Column>

                        <!-- Progress Column -->
                        <Column field="status" header="Progress" headerStyle="width: 12%">
                            <template #body="slotProps">
                                <div class="flex flex-column gap-1">
                                    <div class="flex align-items-center justify-content-between">
                                        <span class="text-xs font-medium">{{ getStatusDisplayName(slotProps.data.status) }}</span>
                                        <span class="text-xs">{{ getProgressPercentage(slotProps.data.status) }}%</span>
                                    </div>
                                    <div class="progress-bar-container">
                                        <div 
                                            class="progress-bar-fill"
                                            :class="getProgressPercentage(slotProps.data.status) >= 80 ? 'bg-green-500' : 
                                                   getProgressPercentage(slotProps.data.status) >= 40 ? 'bg-blue-500' : 'bg-yellow-500'"
                                            :style="{ width: getProgressPercentage(slotProps.data.status) + '%' }"
                                        ></div>
                                    </div>
                                    <div class="text-500 text-xs flex justify-content-between">
                                        <span>DFA</span>
                                        <span>EC</span>
                                        <span>TCO</span>
                                    </div>
                                </div>
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
                        <Column header="Actions" headerStyle="width: 16%" bodyClass="text-center">
                            <template #body="slotProps">
                                <div class="flex gap-1 justify-content-center flex-wrap">
                                    <!-- Print Button -->
                                    <Button
                                        icon="pi pi-print"
                                        severity="info"
                                        text
                                        rounded
                                        size="small"
                                        v-tooltip.top="'Print Voucher'"
                                        @click="printVoucher(slotProps.data)"
                                    />
                                    
                                    <!-- View Details Button -->
                                    <Button
                                        icon="pi pi-eye"
                                        severity="info"
                                        text
                                        rounded
                                        size="small"
                                        v-tooltip.top="'View Details'"
                                        @click="viewVoucherDetails(slotProps.data)"
                                    />
                                    
                                    <!-- EC Approval Button - Only for FA Approved -->
                                    <Button
                                        icon="pi pi-check-circle"
                                        severity="success"
                                        text
                                        rounded
                                        size="small"
                                        v-tooltip.top="'Approve Salary Voucher'"
                                        @click="openApproveModal(slotProps.data)"
                                    />
                                    
                                    <!-- Forward to Inspectorate Button - Only for EC Approved -->
                                    <Button
                                        v-if="slotProps.data.status === 'ec_approved'"
                                        icon="pi pi-send"
                                        severity="primary"
                                        text
                                        rounded
                                        size="small"
                                        v-tooltip.top="'Forward to Inspectorate'"
                                        @click="openForwardModal(slotProps.data)"
                                    />
                                    
                                    <!-- Reject Button - Only for FA Approved -->
                                    <Button
                                        icon="pi pi-times-circle"
                                        severity="danger"
                                        text
                                        rounded
                                        size="small"
                                        v-tooltip.top="'Reject & Return to FA'"
                                        @click="openRejectModal(slotProps.data)"
                                    />
                                </div>
                            </template>
                        </Column>
                    </DataTable>
                </div>
            </template>
        </Card>

        <!-- Approval Modal -->
        <Dialog
            v-model:visible="showApprovalModal"
            :style="{ width: '550px' }"
            header="Expenditure Control Approval - Salary Voucher"
            :modal="true"
            class="approval-dialog"
            :closable="!isProcessing"
        >
            <div class="flex flex-column gap-3">
                <div class="flex align-items-center gap-3 p-3 bg-blue-50 border-round">
                    <i class="pi pi-info-circle text-blue-500 text-xl"></i>
                    <div>
                        <div class="font-semibold">Voucher: {{ currentVoucher?.voucher_number }}</div>
                        <div class="text-sm">Amount: {{ formatCurrency(currentVoucher?.total_amount) }}</div>
                        <div class="text-sm">Type: {{ getSalaryTypeLabel(currentVoucher?.salary_type) }}</div>
                    </div>
                </div>

                <div class="border-round bg-green-50 p-3">
                    <div class="flex align-items-center gap-2 mb-2">
                        <i class="pi pi-arrow-right text-green-600"></i>
                        <span class="font-semibold">Next Stage:</span>
                        <Tag value="Inspectorate" severity="info" />
                    </div>
                    <div class="text-sm text-600">
                        This salary voucher will be forwarded to the <strong>Inspectorate</strong> for review.
                        <strong>Action cannot be undone.</strong>
                    </div>
                </div>

                <div class="border-round bg-yellow-50 p-3">
                    <div class="flex align-items-center gap-2">
                        <i class="pi pi-info-circle text-yellow-600"></i>
                        <span class="font-semibold">Salary Flow:</span>
                        <span class="text-sm">DFA → IA → FA → EC → Inspectorate → TCO → Closed</span>
                    </div>
                </div>

                <div v-if="isProcessing" class="flex align-items-center justify-content-center gap-2 p-2">
                    <ProgressSpinner style="width: 30px; height: 30px" strokeWidth="4" />
                    <span>Processing...</span>
                </div>
            </div>

            <template #footer>
                <Button label="Cancel" icon="pi pi-times" @click="showApprovalModal = false" text :disabled="isProcessing" />
                <Button label="Approve Salary Voucher" icon="pi pi-check" severity="success" @click="handleApprove" :loading="isProcessing" />
            </template>
        </Dialog>

        <!-- Forward to Inspectorate Modal -->
        <Dialog
            v-model:visible="showForwardModal"
            :style="{ width: '550px' }"
            header="Forward to Inspectorate"
            :modal="true"
            class="forward-dialog"
            :closable="!isProcessing"
        >
            <div class="flex flex-column gap-3">
                <div class="flex align-items-center gap-3 p-3 bg-purple-50 border-round">
                    <i class="pi pi-send text-purple-500 text-xl"></i>
                    <div>
                        <div class="font-semibold">Voucher: {{ currentVoucher?.voucher_number }}</div>
                        <div class="text-sm">Amount: {{ formatCurrency(currentVoucher?.total_amount) }}</div>
                        <div class="text-sm">Type: {{ getSalaryTypeLabel(currentVoucher?.salary_type) }}</div>
                    </div>
                </div>

                <div class="border-round bg-purple-50 p-3">
                    <div class="flex align-items-center gap-2 mb-2">
                        <i class="pi pi-arrow-right text-purple-600"></i>
                        <span class="font-semibold">Destination:</span>
                        <Tag value="Inspectorate" severity="info" />
                    </div>
                    <div class="text-sm text-600">
                        This will forward the salary voucher to the <strong>Inspectorate</strong> for review.
                        <strong>Action cannot be undone.</strong>
                    </div>
                </div>

                <div class="border-round bg-gray-50 p-3">
                    <div class="flex align-items-center gap-2">
                        <i class="pi pi-clock text-gray-600"></i>
                        <span class="text-sm">The Inspectorate will review and forward to TCO for payment.</span>
                    </div>
                </div>

                <div v-if="isProcessing" class="flex align-items-center justify-content-center gap-2 p-2">
                    <ProgressSpinner style="width: 30px; height: 30px" strokeWidth="4" />
                    <span>Processing...</span>
                </div>
            </div>

            <template #footer>
                <Button label="Cancel" icon="pi pi-times" @click="showForwardModal = false" text :disabled="isProcessing" />
                <Button label="Forward to Inspectorate" icon="pi pi-send" severity="primary" @click="handleForwardToInspectorate" :loading="isProcessing" />
            </template>
        </Dialog>

        <!-- Rejection Modal -->
        <Dialog
            v-model:visible="showRejectionModal"
            :style="{ width: '550px' }"
            header="Reject Salary Voucher"
            :modal="true"
            class="rejection-dialog"
            :closable="!isProcessing"
        >
            <div class="flex flex-column gap-3">
                <div class="flex align-items-center gap-3 p-3 bg-red-50 border-round">
                    <i class="pi pi-exclamation-triangle text-red-500 text-xl"></i>
                    <div>
                        <div class="font-semibold">Voucher: {{ currentVoucher?.voucher_number }}</div>
                        <div class="text-sm">This action will return the voucher to FA for correction.</div>
                    </div>
                </div>

                <div class="field">
                    <label class="font-semibold block mb-2">
                        Reason for Rejection <span class="text-red-500">*</span>
                    </label>
                    <Textarea
                        v-model="rejectionReason"
                        rows="4"
                        placeholder="Provide detailed reason for rejection. This will be visible to the FA officer."
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
    background: linear-gradient(135deg, #dbeafe 0%, #bfdbfe 100%);
    border: none;
    border-radius: 0.75rem;
}

.progress-bar-container {
    width: 100%;
    height: 6px;
    background-color: #e5e7eb;
    border-radius: 3px;
    overflow: hidden;
}

.progress-bar-fill {
    height: 100%;
    border-radius: 3px;
    transition: width 0.3s ease;
}

.approval-dialog :deep(.p-dialog-header),
.forward-dialog :deep(.p-dialog-header),
.rejection-dialog :deep(.p-dialog-header) {
    background: #f8fafc;
    border-bottom: 1px solid #e2e8f0;
}

.approval-dialog :deep(.p-dialog-content),
.forward-dialog :deep(.p-dialog-content),
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