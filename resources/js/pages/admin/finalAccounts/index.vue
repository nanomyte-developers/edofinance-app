<!-- resources/js/pages/admin/finalAccounts/index.vue -->
<script setup>
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, router } from '@inertiajs/vue3';
import Badge from 'primevue/badge';
import Button from 'primevue/button';
import Card from 'primevue/card';
import Column from 'primevue/column';
import DataTable from 'primevue/datatable';
import Dialog from 'primevue/dialog';
import Tag from 'primevue/tag';
import Textarea from 'primevue/textarea';
import Toast from 'primevue/toast';
import { useToast } from 'primevue/usetoast';
import { computed, ref, onMounted, watch } from 'vue';
import { FilterMatchMode } from '@primevue/core/api';
import InputText from 'primevue/inputtext';
import InputIcon from 'primevue/inputicon';
import IconField from 'primevue/iconfield';
import Dropdown from 'primevue/dropdown';
import Calendar from 'primevue/calendar';
import axios from 'axios';
import * as XLSX from 'xlsx';
import jsPDF from 'jspdf';
import autoTable from 'jspdf-autotable';

const toast = useToast();

// Props with default values
const props = defineProps({
    vouchers: {
        type: Object,
        default: () => ({
            data: [],
            total: 0,
            per_page: 15,
            current_page: 1,
            from: 0,
            to: 0,
        }),
    },
    stats: {
        type: Object,
        default: () => ({
            pending_count: 0,
            processed_today: 0,
            rejected_today: 0,
            total_processed: 0,
        }),
    },
});

// State
const vouchers = ref([]);
const stats = ref({
    pending_count: 0,
    processed_today: 0,
    rejected_today: 0,
    total_processed: 0,
});
const totalRecords = ref(0);
const loading = ref(false);
const searchQuery = ref('');
const selectedVoucherType = ref('');
const selectedStatus = ref('');
const dateRange = ref(null);
const exporting = ref(false);

// Modal states
const showRejectionModal = ref(false);
const showApprovalModal = ref(false);
const showWorkflowModal = ref(false);
const showDocumentViewer = ref(false);
const currentVoucher = ref(null);
const rejectionReason = ref('');
const rejectionTouched = ref(false);
const documentUrl = ref('');
const loadingDocument = ref(false);
const documentError = ref('');
const currentDocument = ref(null);
const workflowHistory = ref([]);

// Filter options
const voucherTypes = [
    { label: 'All Types', value: '' },
    { label: 'Standard', value: 'standard' },
    { label: 'Capital', value: 'capital' },
    { label: 'Recurrent', value: 'recurrent' },
    { label: 'Prepayment', value: 'prepayment' },
    { label: 'Salary', value: 'salary' },
    { label: 'Gratuity', value: 'gratuity' },
    { label: 'Pension', value: 'pension' },
];

const statusOptions = [
    { label: 'All Status', value: '' },
    { label: 'Pending', value: 'pending' },
    { label: 'Approved', value: 'approved' },
    { label: 'Rejected', value: 'rejected' },
    { label: 'Sent Back', value: 'sent_back' },
    { label: 'Forwarded', value: 'forwarded' },
];

const filters = ref({
    global: { value: null, matchMode: FilterMatchMode.CONTAINS },
    voucher_number: { value: null, matchMode: FilterMatchMode.CONTAINS },
    voucher_type: { value: null, matchMode: FilterMatchMode.CONTAINS },
    voucher_date: { value: null, matchMode: FilterMatchMode.CONTAINS },
    narration: { value: null, matchMode: FilterMatchMode.CONTAINS },
    status: { value: null, matchMode: FilterMatchMode.CONTAINS }
});

const lazyParams = ref({
    first: 0,
    rows: 10,
    page: 1,
});

let debounceTimer = null;

// Stats data for cards
const statsData = computed(() => [
    {
        title: 'Pending FA Review',
        value: stats.value.pending_count || 0,
        icon: 'pi pi-clock',
        color: 'text-blue-500',
        bgColor: 'bg-blue-50',
    },
    {
        title: 'Processed Today',
        value: stats.value.processed_today || 0,
        icon: 'pi pi-check-circle',
        color: 'text-green-500',
        bgColor: 'bg-green-50',
    },
    {
        title: 'Rejected Today',
        value: stats.value.rejected_today || 0,
        icon: 'pi pi-times-circle',
        color: 'text-red-500',
        bgColor: 'bg-red-50',
    },
    {
        title: 'Total Processed',
        value: stats.value.total_processed || 0,
        icon: 'pi pi-chart-bar',
        color: 'text-purple-500',
        bgColor: 'bg-purple-50',
    },
]);

const breadcrumbs = [
    { title: 'Final Accounts', href: '/final-accounts' },
    { title: 'Queue', href: '#' },
];

// Format functions
const formatCurrency = (value) => {
    const numValue = Number(value) || 0;
    return new Intl.NumberFormat('en-NG', {
        style: 'currency',
        currency: 'NGN',
        minimumFractionDigits: 2,
    }).format(numValue);
};

const formatDate = (date) => {
    if (!date) return 'N/A';
    return new Date(date).toLocaleDateString('en-GB', {
        day: '2-digit',
        month: 'short',
        year: 'numeric',
    });
};

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

const formatDateForApi = (date) => {
    if (!date) return null;
    const d = new Date(date);
    return d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0') + '-' + String(d.getDate()).padStart(2, '0');
};

// Get voucher type badge severity
const getVoucherTypeSeverity = (type) => {
    const types = {
        standard: 'info',
        prepayment: 'warning',
        salary: 'success',
        capital: 'danger',
        recurrent: 'info',
        gratuity: 'warning',
        pension: 'secondary',
    };
    return types[type?.toLowerCase()] || 'info';
};

// Get next stage based on voucher type
const getNextStage = (voucher) => {
    if (!voucher) return 'N/A';
    
    if (voucher.voucher_type?.toLowerCase() === 'salary') {
        return 'Inspectorate → TCO';
    }
    return 'EC → AG → MAS';
};

// Load vouchers with filters
const loadVouchers = async () => {
    loading.value = true;
    try {
        const params = {
            per_page: lazyParams.value.rows,
            page: lazyParams.value.page,
            search: searchQuery.value || '',
        };

        if (selectedVoucherType.value) {
            params.voucher_type = selectedVoucherType.value;
        }
        if (selectedStatus.value) {
            params.status = selectedStatus.value;
        }
        if (dateRange.value && dateRange.value.length === 2) {
            if (dateRange.value[0]) {
                params.date_from = formatDateForApi(dateRange.value[0]);
            }
            if (dateRange.value[1]) {
                params.date_to = formatDateForApi(dateRange.value[1]);
            }
        }

        const response = await axios.get('/final-accounts/search', { params });
        
        if (response.data && response.data.success !== false) {
            const voucherData = response.data.vouchers;
            vouchers.value = voucherData.data || [];
            totalRecords.value = voucherData.total || 0;
            
            if (response.data.stats) {
                stats.value = {
                    pending_count: response.data.stats.pending_count || 0,
                    processed_today: response.data.stats.processed_today || 0,
                    rejected_today: response.data.stats.rejected_today || 0,
                    total_processed: response.data.stats.total_processed || 0,
                };
            }
        } else {
            vouchers.value = [];
            totalRecords.value = 0;
        }
    } catch (error) {
        console.error('Error loading vouchers:', error);
        toast.add({
            severity: "error",
            summary: "Error",
            detail: error.response?.data?.error || "Failed to load data",
            life: 3000
        });
        vouchers.value = [];
        totalRecords.value = 0;
    }
    loading.value = false;
};

// Refresh data
const refreshData = () => {
    loadVouchers();
    toast.add({
        severity: 'success',
        summary: 'Refreshed',
        detail: 'Data refreshed successfully',
        life: 2000,
    });
};

// Clear filters
const clearFilters = () => {
    searchQuery.value = '';
    selectedVoucherType.value = '';
    selectedStatus.value = '';
    dateRange.value = null;
    lazyParams.value.page = 1;
    loadVouchers();
};

// Export to Excel
const exportToExcel = () => {
    if (!vouchers.value || vouchers.value.length === 0) {
        toast.add({
            severity: 'warn',
            summary: 'No Data',
            detail: 'No vouchers to export',
            life: 3000,
        });
        return;
    }

    exporting.value = true;
    try {
        const exportData = vouchers.value.map(v => ({
            'Voucher Number': v.voucher_number || 'N/A',
            'Type': v.voucher_type || 'N/A',
            'Date': formatDate(v.voucher_date),
            'MDA': v.mda?.name || 'N/A',
            'Payee': v.payee_name || 'N/A',
            'Amount': v.total_amount || 0,
            'Status': v.status || 'N/A',
            'Narration': v.narration || 'N/A',
        }));

        const ws = XLSX.utils.json_to_sheet(exportData);
        const wb = XLSX.utils.book_new();
        XLSX.utils.book_append_sheet(wb, ws, 'Vouchers');
        XLSX.writeFile(wb, `Final_Accounts_Vouchers_${new Date().toISOString().split('T')[0]}.xlsx`);
        
        toast.add({
            severity: 'success',
            summary: 'Export Successful',
            detail: 'Excel file downloaded successfully',
            life: 3000,
        });
    } catch (error) {
        console.error('Export error:', error);
        toast.add({
            severity: 'error',
            summary: 'Export Failed',
            detail: 'Failed to export to Excel: ' + error.message,
            life: 5000,
        });
    } finally {
        exporting.value = false;
    }
};

// Export to PDF
const exportToPDF = () => {
    if (!vouchers.value || vouchers.value.length === 0) {
        toast.add({
            severity: 'warn',
            summary: 'No Data',
            detail: 'No vouchers to export',
            life: 3000,
        });
        return;
    }

    exporting.value = true;
    try {
        const doc = new jsPDF('l', 'mm', 'a4');
        const pageWidth = doc.internal.pageSize.getWidth();

        // Title
        doc.setFontSize(18);
        doc.setTextColor(33, 37, 41);
        doc.text('Final Accounts Vouchers', pageWidth / 2, 15, { align: 'center' });
        
        // Subtitle
        doc.setFontSize(10);
        doc.setTextColor(108, 117, 125);
        doc.text(`Generated: ${new Date().toLocaleString()}`, pageWidth / 2, 22, { align: 'center' });
        doc.text(`Total Records: ${vouchers.value.length}`, pageWidth / 2, 28, { align: 'center' });

        // Prepare table data
        const tableData = vouchers.value.map(v => [
            v.voucher_number || 'N/A',
            v.voucher_type || 'N/A',
            formatDate(v.voucher_date),
            v.mda?.name || 'N/A',
            v.payee_name || 'N/A',
            `₦${Number(v.total_amount || 0).toLocaleString('en-NG', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`,
            v.status || 'N/A',
        ]);

        autoTable(doc, {
            head: [['Voucher #', 'Type', 'Date', 'MDA', 'Payee', 'Amount', 'Status']],
            body: tableData,
            startY: 35,
            theme: 'striped',
            headStyles: {
                fillColor: [52, 58, 64],
                textColor: [255, 255, 255],
                fontSize: 9,
                fontStyle: 'bold',
            },
            bodyStyles: {
                fontSize: 8,
            },
            columnStyles: {
                0: { cellWidth: 30 },
                1: { cellWidth: 20 },
                2: { cellWidth: 25 },
                3: { cellWidth: 40 },
                4: { cellWidth: 35 },
                5: { cellWidth: 30 },
                6: { cellWidth: 25 },
            },
            didDrawPage: function(data) {
                doc.setFontSize(8);
                doc.setTextColor(150);
                doc.text(
                    `Page ${data.pageNumber} of ${doc.internal.pages.length - 1}`,
                    pageWidth / 2,
                    doc.internal.pageSize.getHeight() - 5,
                    { align: 'center' }
                );
            }
        });

        doc.save(`Final_Accounts_Vouchers_${new Date().toISOString().split('T')[0]}.pdf`);
        
        toast.add({
            severity: 'success',
            summary: 'Export Successful',
            detail: 'PDF file downloaded successfully',
            life: 3000,
        });
    } catch (error) {
        console.error('PDF Export error:', error);
        toast.add({
            severity: 'error',
            summary: 'Export Failed',
            detail: 'Failed to export to PDF: ' + error.message,
            life: 5000,
        });
    } finally {
        exporting.value = false;
    }
};

// Print Voucher
const printVoucher = (voucher) => {
    const printUrl = `/vouchers/${voucher.id}/print`;
    window.open(printUrl, '_blank');
};

// View Voucher Details
const viewVoucherDetails = (voucher) => {
    router.visit(`/final-accounts/vouchers/${voucher.id}`);
};

// Open workflow modal
const openWorkflowModal = async (voucher) => {
    currentVoucher.value = voucher;
    showWorkflowModal.value = true;
    
    try {
        const response = await axios.get(`/vouchers/${voucher.id}/approvals`);
        workflowHistory.value = response.data || [];
    } catch (error) {
        console.error('Error loading workflow:', error);
        workflowHistory.value = [];
    }
};

// View document
const viewDocument = (document) => {
    if (!document) {
        toast.add({
            severity: 'info',
            summary: 'No Document',
            detail: 'No document attached to this voucher.',
            life: 3000,
        });
        return;
    }
    
    let docUrl = document.file_path;
    if (docUrl && !docUrl.startsWith('http') && !docUrl.startsWith('/')) {
        docUrl = `/storage/${docUrl}`;
    } else if (docUrl && !docUrl.startsWith('http') && docUrl.startsWith('/')) {
        docUrl = `${window.location.origin}${docUrl}`;
    }
    
    currentDocument.value = document;
    documentUrl.value = docUrl;
    showDocumentViewer.value = true;
};

// Close document viewer
const closeDocumentViewer = () => {
    showDocumentViewer.value = false;
    documentUrl.value = '';
    currentDocument.value = null;
};

// Modal functions
const openRejectModal = (voucher) => {
    currentVoucher.value = voucher;
    rejectionReason.value = '';
    rejectionTouched.value = false;
    showRejectionModal.value = true;
};

const openApproveModal = (voucher) => {
    currentVoucher.value = voucher;
    showApprovalModal.value = true;
};

const handleApprove = () => {
    if (!currentVoucher.value) return;

    router.post(
        `/final-accounts/vouchers/${currentVoucher.value.id}/approve`,
        {},
        {
            preserveScroll: true,
            onSuccess: () => {
                showApprovalModal.value = false;
                currentVoucher.value = null;
                const nextStage = getNextStage(currentVoucher.value);
                toast.add({
                    severity: 'success',
                    summary: 'Approved & Forwarded',
                    detail: `Voucher forwarded to ${nextStage}.`,
                    life: 4000,
                });
                loadVouchers();
            },
            onError: (errors) => {
                showApprovalModal.value = false;
                toast.add({
                    severity: 'error',
                    summary: 'Error',
                    detail: errors.message || 'Failed to approve voucher.',
                    life: 5000,
                });
            },
        },
    );
};

const handleReject = () => {
    if (!currentVoucher.value || !rejectionReason.value) {
        toast.add({
            severity: 'warn',
            summary: 'Required',
            detail: 'Please provide a reason for rejection.',
            life: 3000,
        });
        return;
    }

    if (rejectionReason.value.length < 10) {
        toast.add({
            severity: 'warn',
            summary: 'Reason Too Short',
            detail: 'Rejection reason must be at least 10 characters.',
            life: 3000,
        });
        return;
    }

    router.post(
        `/final-accounts/vouchers/${currentVoucher.value.id}/reject`,
        { reason: rejectionReason.value },
        {
            preserveScroll: true,
            onSuccess: () => {
                toast.add({
                    severity: 'info',
                    summary: 'Rejected',
                    detail: `Voucher rejected and returned to DFA.`,
                    life: 4000,
                });
                showRejectionModal.value = false;
                currentVoucher.value = null;
                rejectionReason.value = '';
                rejectionTouched.value = false;
                loadVouchers();
            },
            onError: (errors) => {
                toast.add({
                    severity: 'error',
                    summary: 'Error',
                    detail: errors.reason || 'Failed to reject voucher.',
                    life: 5000,
                });
            },
        },
    );
};

const closeRejectionModal = () => {
    showRejectionModal.value = false;
    currentVoucher.value = null;
    rejectionReason.value = '';
    rejectionTouched.value = false;
};

const closeApprovalModal = () => {
    showApprovalModal.value = false;
    currentVoucher.value = null;
};

const closeWorkflowModal = () => {
    showWorkflowModal.value = false;
    currentVoucher.value = null;
    workflowHistory.value = [];
};

const onPage = (event) => {
    lazyParams.value.page = event.page + 1;
    lazyParams.value.first = event.first;
    lazyParams.value.rows = event.rows;
    loadVouchers();
};

// Watch for filter changes
watch([selectedVoucherType, selectedStatus, dateRange, searchQuery], () => {
    clearTimeout(debounceTimer);
    debounceTimer = setTimeout(() => {
        lazyParams.value.page = 1;
        loadVouchers();
    }, 500);
}, { deep: true });

// Initialize
onMounted(() => {
    if (props.stats) {
        stats.value = {
            pending_count: props.stats.pending_count || 0,
            processed_today: props.stats.processed_today || 0,
            rejected_today: props.stats.rejected_today || 0,
            total_processed: props.stats.total_processed || 0,
        };
    }
    
    if (props.vouchers && props.vouchers.data && props.vouchers.data.length > 0) {
        vouchers.value = props.vouchers.data;
        totalRecords.value = props.vouchers.total || 0;
    } else {
        loadVouchers();
    }
});
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head title="Final Accounts Queue" />
        <Toast />

        <!-- Workflow Info Banner -->
        <div class="mb-4">
            <Message severity="info" :closable="false" class="workflow-banner">
                <div class="flex align-items-center gap-3 flex-wrap">
                    <i class="pi pi-share-alt text-xl"></i>
                    <div>
                        <strong>Final Accounts (FA) - Step 3 of 6</strong>
                        <div class="text-sm mt-1">
                            Vouchers approved by Internal Audit (IA) are reviewed here.
                            <span class="font-semibold">Salary vouchers</span> go to Inspectorate → TCO.
                            <span class="font-semibold">Other vouchers</span> go to EC → AG → MAS.
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
                        <div class="flex align-items-center">
                            <div :class="['border-circle flex align-items-center justify-content-center mr-3', stat.bgColor]" style="width: 3.5rem; height: 3.5rem;">
                                <i :class="[stat.icon, stat.color, 'text-xl']"></i>
                            </div>
                            <div class="flex-1">
                                <div class="text-900 text-xl font-bold">
                                    {{ stat.value }}
                                </div>
                                <div class="text-600 text-sm">
                                    {{ stat.title }}
                                </div>
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
                        <span>Final Accounts Queue</span>
                        <Badge :value="totalRecords" severity="info" />
                    </div>
                    <div class="flex gap-2 flex-wrap">
                        <Button 
                            label="Refresh" 
                            icon="pi pi-refresh" 
                            severity="secondary" 
                            outlined 
                            size="small"
                            @click="refreshData" 
                            :loading="loading" 
                        />
                        <Button 
                            label="Export Excel" 
                            icon="pi pi-file-excel" 
                            severity="success" 
                            outlined 
                            size="small"
                            @click="exportToExcel" 
                            :loading="exporting" 
                        />
                        <Button 
                            label="Export PDF" 
                            icon="pi pi-file-pdf" 
                            severity="danger" 
                            outlined 
                            size="small"
                            @click="exportToPDF" 
                            :loading="exporting" 
                        />
                        <Button 
                            label="Clear Filters" 
                            icon="pi pi-filter-slash" 
                            severity="secondary" 
                            outlined 
                            size="small"
                            @click="clearFilters" 
                        />
                    </div>
                </div>
            </template>

            <template #content>
                <!-- Filter Section -->
                <div class="mb-4">
                    <div class="grid">
                        <!-- Search -->
                        <div class="col-12 md:col-3">
                            <IconField>
                                <InputIcon>
                                    <i class="pi pi-search" />
                                </InputIcon>
                                <InputText 
                                    v-model="searchQuery" 
                                    placeholder="Search vouchers..." 
                                    class="w-full"
                                    size="small"
                                />
                            </IconField>
                        </div>

                        <!-- Voucher Type Filter -->
                        <div class="col-12 md:col-2">
                            <Dropdown
                                v-model="selectedVoucherType"
                                :options="voucherTypes"
                                optionLabel="label"
                                optionValue="value"
                                placeholder="All Types"
                                class="w-full"
                                size="small"
                                :showClear="true"
                            />
                        </div>

                        <!-- Status Filter -->
                        <div class="col-12 md:col-2">
                            <Dropdown
                                v-model="selectedStatus"
                                :options="statusOptions"
                                optionLabel="label"
                                optionValue="value"
                                placeholder="All Status"
                                class="w-full"
                                size="small"
                                :showClear="true"
                            />
                        </div>

                        <!-- Date Range -->
                        <div class="col-12 md:col-3">
                            <Calendar
                                v-model="dateRange"
                                selectionMode="range"
                                :manualInput="false"
                                placeholder="Date Range"
                                class="w-full"
                                size="small"
                                :showIcon="true"
                            />
                        </div>

                        <!-- Filter Info -->
                        <div class="col-12 md:col-2 flex align-items-center">
                            <span class="text-sm text-500">
                                <i class="pi pi-info-circle mr-1"></i>
                                {{ totalRecords }} record(s) found
                            </span>
                        </div>
                    </div>
                </div>

                <DataTable 
                    v-model:filters="filters" 
                    :value="vouchers" 
                    dataKey="id" 
                    stripedRows
                    responsiveLayout="scroll" 
                    class="p-datatable-sm" 
                    :emptyMessage="'No vouchers pending Final Accounts review.'"
                    :paginator="true" 
                    :rowsPerPageOptions="[5, 10, 20, 50, 100]" 
                    :loading="loading"
                    :rows="lazyParams.rows" 
                    :totalRecords="totalRecords" 
                    @page="onPage" 
                    removableSort
                    :globalFilterFields="['voucher_number', 'voucher_type', 'voucher_date', 'mda.name', 'narration', 'status']"
                    lazy 
                    size="small"
                    paginatorTemplate="RowsPerPageDropdown FirstPageLink PrevPageLink CurrentPageReport NextPageLink LastPageLink"
                    currentPageReportTemplate="{first} to {last} of {totalRecords}"
                >
                    <!-- Voucher Number -->
                    <Column field="voucher_number" header="Voucher #" headerStyle="width: 12%" :sortable="true">
                        <template #body="slotProps">
                            <Link 
                                :href="`/final-accounts/vouchers/${slotProps.data.id}`" 
                                class="font-medium text-primary hover:underline"
                            >
                                {{ slotProps.data.voucher_number || 'N/A' }}
                            </Link>
                        </template>
                    </Column>

                    <!-- Type -->
                    <Column field="voucher_type" header="Type" headerStyle="width: 8%" :sortable="true">
                        <template #body="slotProps">
                            <Tag 
                                :value="slotProps.data.voucher_type?.toUpperCase() || 'STANDARD'" 
                                :severity="getVoucherTypeSeverity(slotProps.data.voucher_type)"
                            />
                        </template>
                    </Column>

                    <!-- Date -->
                    <Column field="voucher_date" header="Date" headerStyle="width: 10%" :sortable="true">
                        <template #body="slotProps">
                            <span class="text-900">{{ formatDate(slotProps.data.voucher_date) }}</span>
                        </template>
                    </Column>

                    <!-- MDA -->
                    <Column field="mda.name" header="MDA" headerStyle="width: 15%" :sortable="true">
                        <template #body="slotProps">
                            <div class="flex flex-column">
                                <span class="text-900 font-medium">
                                    {{ slotProps.data.mda?.name || 'N/A' }}
                                </span>
                                <small class="text-500">
                                    {{ slotProps.data.mda?.code || '' }}
                                </small>
                            </div>
                        </template>
                    </Column>

                    <!-- Payee -->
                    <Column field="payee_name" header="Payee" headerStyle="width: 12%" :sortable="true">
                        <template #body="slotProps">
                            <span class="text-900">{{ slotProps.data.payee_name || 'N/A' }}</span>
                        </template>
                    </Column>

                    <!-- Amount -->
                    <Column field="total_amount" header="Amount" headerStyle="width: 12%" bodyClass="font-bold text-right" :sortable="true">
                        <template #body="slotProps">
                            <span class="text-900">{{ formatCurrency(slotProps.data.total_amount || 0) }}</span>
                        </template>
                    </Column>

                    <!-- Next Stage -->
                    <Column header="Next Stage" headerStyle="width: 12%">
                        <template #body="slotProps">
                            <Tag 
                                :value="getNextStage(slotProps.data)" 
                                severity="info" 
                                icon="pi pi-arrow-right"
                                size="small"
                            />
                        </template>
                    </Column>

                    <!-- Status -->
                    <Column field="status" header="Status" headerStyle="width: 10%" :sortable="true">
                        <template #body="slotProps">
                            <Tag 
                                :value="slotProps.data.status || 'Pending'" 
                                :severity="slotProps.data.status === 'approved' ? 'success' :
                                          slotProps.data.status === 'rejected' ? 'danger' :
                                          slotProps.data.status === 'sent_back' ? 'warning' : 'info'"
                            />
                        </template>
                    </Column>

                    <!-- Actions -->
                    <Column header="Actions" headerStyle="width: 13%" bodyClass="text-center">
                        <template #body="slotProps">
                            <div class="flex justify-content-center gap-1">
                                <Button 
                                    icon="pi pi-print" 
                                    severity="info" 
                                    size="small" 
                                    text 
                                    v-tooltip="'Print Voucher'"
                                    @click="printVoucher(slotProps.data)" 
                                />
                                <Button 
                                    icon="pi pi-eye" 
                                    severity="info" 
                                    size="small" 
                                    text 
                                    v-tooltip="'View Details'"
                                    @click="viewVoucherDetails(slotProps.data)" 
                                />
                                <Button 
                                    icon="pi pi-sitemap" 
                                    severity="secondary" 
                                    size="small" 
                                    text 
                                    v-tooltip="'View Workflow'"
                                    @click="openWorkflowModal(slotProps.data)" 
                                />
                                <Button 
                                    icon="pi pi-check" 
                                    severity="success" 
                                    size="small" 
                                    text 
                                    v-tooltip="'Approve & Forward'"
                                    @click="openApproveModal(slotProps.data)" 
                                />
                                <Button 
                                    icon="pi pi-times" 
                                    severity="danger" 
                                    size="small" 
                                    text 
                                    v-tooltip="'Reject & Return'"
                                    @click="openRejectModal(slotProps.data)" 
                                />
                            </div>
                        </template>
                    </Column>
                </DataTable>
            </template>
        </Card>

        <!-- Approval Modal -->
        <Dialog 
            v-model:visible="showApprovalModal" 
            :style="{ width: '480px' }" 
            header="Final Accounts Approval" 
            :modal="true"
            class="approval-dialog"
        >
            <div class="flex flex-column gap-3">
                <div class="flex align-items-center gap-3 p-3 bg-blue-50 border-round">
                    <i class="pi pi-info-circle text-blue-500 text-xl"></i>
                    <div>
                        <div class="font-semibold">Voucher: {{ currentVoucher?.voucher_number }}</div>
                        <div class="text-sm">Amount: {{ formatCurrency(currentVoucher?.total_amount) }}</div>
                    </div>
                </div>

                <div class="border-round bg-yellow-50 p-3">
                    <div class="flex align-items-center gap-2 mb-2">
                        <i class="pi pi-arrow-right text-yellow-600"></i>
                        <span class="font-semibold">Next Stage:</span>
                        <Tag :value="getNextStage(currentVoucher)" severity="info" />
                    </div>
                    <div class="text-sm text-600">
                        This voucher will be forwarded to the next approval stage based on its type.
                        <strong>Action cannot be undone.</strong>
                    </div>
                </div>

                <div class="border-round bg-green-50 p-3">
                    <div class="flex align-items-center gap-2">
                        <i class="pi pi-check-circle text-green-600"></i>
                        <span class="font-semibold">Confirmation:</span>
                        <span class="text-sm">I confirm that this voucher is ready for the next stage.</span>
                    </div>
                </div>
            </div>

            <template #footer>
                <Button label="Cancel" icon="pi pi-times" @click="closeApprovalModal" text />
                <Button label="Approve & Forward" icon="pi pi-send" severity="success" @click="handleApprove" />
            </template>
        </Dialog>

        <!-- Rejection Modal -->
        <Dialog 
            v-model:visible="showRejectionModal" 
            :style="{ width: '500px' }" 
            header="Reject Voucher" 
            :modal="true"
            :closable="false" 
            @update:visible="closeRejectionModal"
        >
            <div class="flex flex-column gap-3" v-if="currentVoucher">
                <div class="flex align-items-center text-color-secondary gap-2">
                    <i class="pi pi-exclamation-triangle text-red-500"></i>
                    <span>
                        Voucher <strong class="text-900">{{ currentVoucher.voucher_number }}</strong>
                        will be returned to DFA.
                    </span>
                </div>

                <div class="border-round surface-border border-1 bg-gray-50 p-3">
                    <p class="m-0 text-sm">
                        Please state the <strong class="text-red-500">mandatory reason</strong> for rejection.
                    </p>
                </div>

                <div class="field">
                    <div class="flex align-items-center justify-content-between mb-2">
                        <label for="reject_reason" class="text-color font-medium">Reason for Rejection</label>
                        <span class="text-sm text-red-500">Required *</span>
                    </div>

                    <Textarea 
                        id="reject_reason" 
                        v-model="rejectionReason" 
                        rows="4"
                        placeholder="Provide detailed reason for rejection..." 
                        :class="{ 'p-invalid': !rejectionReason && rejectionTouched }"
                        @blur="rejectionTouched = true" 
                        class="w-full" 
                        autoResize 
                    />

                    <div class="flex justify-content-between mt-1">
                        <small class="text-color-secondary">
                            <i class="pi pi-info-circle mr-1"></i>
                            Minimum 10 characters required
                        </small>
                        <small :class="rejectionReason.length < 10 ? 'text-red-500' : 'text-green-500'">
                            {{ rejectionReason.length }}/10
                        </small>
                    </div>

                    <small v-if="!rejectionReason && rejectionTouched" class="p-error mt-2 block">
                        <i class="pi pi-exclamation-circle mr-1"></i>
                        Reason for rejection is required
                    </small>
                </div>
            </div>

            <template #footer>
                <Button label="Cancel" icon="pi pi-times" @click="closeRejectionModal" text />
                <Button 
                    label="Confirm Rejection" 
                    icon="pi pi-ban" 
                    severity="danger" 
                    @click="handleReject"
                    :disabled="!rejectionReason || rejectionReason.length < 10" 
                />
            </template>
        </Dialog>

        <!-- Workflow Timeline Modal -->
        <Dialog
            v-model:visible="showWorkflowModal"
            :style="{ width: '650px', maxHeight: '80vh' }"
            :header="`Workflow History - ${currentVoucher?.voucher_number}`"
            :modal="true"
            class="workflow-dialog"
            @update:visible="closeWorkflowModal"
        >
            <div class="workflow-timeline" style="max-height: 60vh; overflow-y: auto;">
                <div v-if="workflowHistory.length === 0" class="text-center p-4">
                    <i class="pi pi-clock text-400 text-3xl mb-2"></i>
                    <p class="text-600">No workflow history available</p>
                </div>
                <Timeline
                    v-else
                    :value="workflowHistory"
                    layout="vertical"
                    align="left"
                    class="custom-timeline"
                >
                    <template #marker="slotProps">
                        <span 
                            class="custom-marker p-shadow-2" 
                            :class="{
                                'bg-green-500': slotProps.item.action === 'Approved',
                                'bg-red-500': slotProps.item.action === 'Declined',
                                'bg-blue-500': slotProps.item.action === 'Forwarded',
                                'bg-orange-500': slotProps.item.action === 'Sent Back',
                                'bg-gray-500': slotProps.item.action === 'Saved'
                            }"
                        >
                            <i :class="{
                                'pi pi-check': slotProps.item.action === 'Approved',
                                'pi pi-times': slotProps.item.action === 'Declined',
                                'pi pi-send': slotProps.item.action === 'Forwarded',
                                'pi pi-undo': slotProps.item.action === 'Sent Back',
                                'pi pi-save': slotProps.item.action === 'Saved'
                            }" class="text-white text-sm"></i>
                        </span>
                    </template>
                    <template #content="slotProps">
                        <Card class="workflow-card">
                            <template #content>
                                <div class="flex flex-column gap-2">
                                    <div class="flex align-items-center justify-content-between flex-wrap">
                                        <span class="font-semibold text-primary">
                                            {{ slotProps.item.approval_role || 'System' }}
                                        </span>
                                        <Badge 
                                            :value="slotProps.item.action" 
                                            :severity="slotProps.item.action === 'Approved' ? 'success' : 
                                                      slotProps.item.action === 'Declined' ? 'danger' :
                                                      slotProps.item.action === 'Forwarded' ? 'info' :
                                                      slotProps.item.action === 'Sent Back' ? 'warning' : 'secondary'"
                                            size="small"
                                        />
                                    </div>
                                    <div class="text-600 text-sm">
                                        {{ formatDateTime(slotProps.item.action_at) }}
                                    </div>
                                    <div v-if="slotProps.item.comment" class="text-500 text-sm mt-1 p-2 bg-gray-50 border-round">
                                        <i class="pi pi-comment mr-1"></i>
                                        {{ slotProps.item.comment }}
                                    </div>
                                    <div v-if="slotProps.item.user" class="text-500 text-xs">
                                        By: {{ slotProps.item.user.name }}
                                    </div>
                                </div>
                            </template>
                        </Card>
                    </template>
                </Timeline>
            </div>
            <template #footer>
                <Button label="Close" icon="pi pi-times" @click="closeWorkflowModal" text />
            </template>
        </Dialog>

        <!-- Document Viewer Dialog -->
        <Dialog
            v-model:visible="showDocumentViewer"
            :header="`Document - ${currentDocument?.file_name || 'Document'}`"
            :style="{ width: '90vw', height: '90vh' }"
            :modal="true"
            maximizable            @update:visible="closeDocumentViewer"
        >
            <div class="flex flex-column h-full">
                <div class="flex justify-content-between align-items-center mb-3 pb-2 border-bottom-1 surface-border">
                    <div class="flex align-items-center gap-2">
                        <i class="pi pi-file-pdf text-xl text-red-500" v-if="currentDocument?.file_name?.endsWith('.pdf')"></i>
                        <i class="pi pi-file-image text-xl text-blue-500" v-else-if="currentDocument?.file_name?.match(/\.(jpg|jpeg|png|gif)$/i)"></i>
                        <i class="pi pi-file text-xl text-gray-500" v-else></i>
                        <span class="font-semibold">{{ currentDocument?.file_name }}</span>
                    </div>
                    <Button 
                        icon="pi pi-download" 
                        label="Download" 
                        severity="secondary" 
                        size="small"
                        @click="window.open(documentUrl, '_blank')" 
                    />
                </div>
                <div class="flex-1 border-round surface-border border-1 overflow-hidden">
                    <iframe 
                        :src="documentUrl" 
                        frameborder="0" 
                        width="100%" 
                        height="100%" 
                        class="w-full h-full"
                        style="min-height: 500px;"
                    ></iframe>
                </div>
            </div>
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

.main-card :deep(.p-card-header) {
    border-top-left-radius: 1rem;
    border-top-right-radius: 1rem;
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
    border-top: 1px solid #e2e8f0;
}

.table-container :deep(.p-datatable-tbody > tr) {
    transition: background-color 0.2s;
}

.table-container :deep(.p-datatable-tbody > tr:hover) {
    background: #f1f5f9;
}

.workflow-banner :deep(.p-message) {
    background: linear-gradient(135deg, #e0f2fe 0%, #bae6fd 100%);
    border: none;
    border-radius: 0.75rem;
}

.workflow-card :deep(.p-card) {
    box-shadow: none;
    border: 1px solid #e2e8f0;
    border-radius: 0.5rem;
    margin: 0.5rem 0;
}

.workflow-card :deep(.p-card-content) {
    padding: 0.75rem;
}

.custom-marker {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 2rem;
    height: 2rem;
    border-radius: 50%;
    flex-shrink: 0;
}

.custom-timeline :deep(.p-timeline-event-opposite) {
    flex: 0;
    padding: 0;
}

.custom-timeline :deep(.p-timeline-event-content) {
    margin-left: 1rem;
}

.approval-dialog :deep(.p-dialog-header),
.rejection-dialog :deep(.p-dialog-header),
.workflow-dialog :deep(.p-dialog-header) {
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