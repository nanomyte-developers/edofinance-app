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
            approved_today: 0,
            rejected_today: 0,
            total_processed: 0,
        }),
    },
    requiredDocuments: {
        type: Array,
        default: () => ['approval_form'],
    },
});

// State
const vouchers = ref([]);
const stats = ref({
    pending_count: 0,
    approved_today: 0,
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
const showDocumentViewer = ref(false);
const showDocumentsModal = ref(false);
const currentVoucher = ref(null);
const rejectionReason = ref('');
const rejectionTouched = ref(false);
const documentUrl = ref('');
const loadingDocument = ref(false);
const documentError = ref('');
const currentDocument = ref(null);

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
    { label: 'Submitted', value: 'submitted' },
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
        title: 'Pending Review',
        value: stats.value.pending_count || 0,
        icon: 'pi pi-clock',
        color: 'text-blue-500',
        bgColor: 'bg-blue-50',
    },
    {
        title: 'Approved Today',
        value: stats.value.approved_today || 0,
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

const breadcrumbs = [{ title: 'Internal Audit', href: '#' }];

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

const formatDateForApi = (date) => {
    if (!date) return null;
    const d = new Date(date);
    return d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0') + '-' + String(d.getDate()).padStart(2, '0');
};

const formatDocumentType = (docType) => {
    const typeMap = {
        approval_form: 'Approval Form',
        invoice: 'Invoice',
        receipt: 'Receipt',
        delivery_note: 'Delivery Note',
        other: 'Additional Document',
        supporting: 'Supporting Document',
    };
    return typeMap[docType] || docType.replace(/_/g, ' ').replace(/\b\w/g, (l) => l.toUpperCase());
};

// Document checking functions
const hasAllRequiredDocuments = (voucher) => {
    if (!voucher || !voucher.documents || !Array.isArray(voucher.documents)) {
        return false;
    }
    const attachedDocTypes = voucher.documents.map((doc) => doc.document_type).filter(Boolean);
    const requiredDocs = props.requiredDocuments || [];
    return requiredDocs.every((docType) => attachedDocTypes.includes(docType));
};

const getMissingDocuments = (voucher) => {
    if (!voucher || !voucher.documents || !Array.isArray(voucher.documents)) {
        return props.requiredDocuments || [];
    }
    const attachedDocTypes = voucher.documents.map((doc) => doc.document_type).filter(Boolean);
    const requiredDocs = props.requiredDocuments || [];
    return requiredDocs.filter((docType) => !attachedDocTypes.includes(docType));
};

const getDocumentCount = (voucher) => {
    if (!voucher || !voucher.documents || !Array.isArray(voucher.documents)) {
        return 0;
    }
    return voucher.documents.length;
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

        const response = await axios.get('/internal-audits/search', { params });
        
        if (response.data && response.data.success !== false) {
            const voucherData = response.data.vouchers;
            vouchers.value = voucherData.data || [];
            totalRecords.value = voucherData.total || 0;
            
            if (response.data.stats) {
                stats.value = {
                    pending_count: response.data.stats.pending_count || 0,
                    approved_today: response.data.stats.approved_today || 0,
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
        XLSX.writeFile(wb, `Internal_Audit_Vouchers_${new Date().toISOString().split('T')[0]}.xlsx`);
        
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

// Export to PDF - FIXED
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
        doc.text('Internal Audit Vouchers', pageWidth / 2, 15, { align: 'center' });
        
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

        // Use autoTable correctly
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
                // Footer
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

        doc.save(`Internal_Audit_Vouchers_${new Date().toISOString().split('T')[0]}.pdf`);
        
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
    router.visit(`/internal-audits/${voucher.id}`);
};

// Document functions
const getDocumentUrl = (document) => {
    if (!document) return null;
    if (document.url) return document.url;
    if (document.file_path) return `/storage/${document.file_path}`;
    return null;
};

const openDocument = async (document) => {
    const documentUrlValue = getDocumentUrl(document);
    if (!documentUrlValue) {
        toast.add({
            severity: 'error',
            summary: 'Error',
            detail: 'Document URL not found.',
            life: 5000,
        });
        return;
    }

    currentDocument.value = document;
    loadingDocument.value = true;
    documentError.value = '';
    documentUrl.value = '';

    try {
        documentUrl.value = documentUrlValue;
        showDocumentViewer.value = true;
    } catch (error) {
        console.error('Error loading document:', error);
        documentError.value = 'Failed to load document preview.';
        loadingDocument.value = false;
    }
};

const loadDocument = (document) => {
    openDocument(document);
};

const onDocumentLoad = () => {
    loadingDocument.value = false;
    documentError.value = '';
};

const onDocumentError = () => {
    loadingDocument.value = false;
    documentError.value = 'Failed to load document preview.';
};

const downloadDocument = (document) => {
    const documentUrlValue = getDocumentUrl(document);
    if (!documentUrlValue) return;

    const link = document.createElement('a');
    link.href = documentUrlValue;
    link.download = document.file_name || 'document';
    link.target = '_blank';
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
};

const openInNewTab = (document) => {
    const documentUrlValue = getDocumentUrl(document);
    if (!documentUrlValue) return;
    window.open(documentUrlValue, '_blank');
};

// Modal functions
const openDocumentsModal = (voucher) => {
    currentVoucher.value = voucher;
    showDocumentsModal.value = true;
};

const openRejectModal = (voucher) => {
    currentVoucher.value = voucher;
    rejectionReason.value = '';
    rejectionTouched.value = false;
    showRejectionModal.value = true;
};

const openApproveModal = (voucher) => {
    if (!hasAllRequiredDocuments(voucher)) {
        const missingDocs = getMissingDocuments(voucher);
        const formattedMissingDocs = missingDocs.map((doc) => formatDocumentType(doc));
        toast.add({
            severity: 'warn',
            summary: 'Missing Documents',
            detail: `Cannot approve: Missing ${formattedMissingDocs.join(', ')}`,
            life: 5000,
        });
        return;
    }
    currentVoucher.value = voucher;
    showApprovalModal.value = true;
};

const handleApprove = () => {
    if (!currentVoucher.value) return;

    router.post(
        `/internal-audits/${currentVoucher.value.id}/approve`,
        {},
        {
            preserveScroll: true,
            onSuccess: () => {
                showApprovalModal.value = false;
                currentVoucher.value = null;
                toast.add({
                    severity: 'success',
                    summary: 'Approved',
                    detail: `Voucher approved successfully.`,
                    life: 4000,
                });
                loadVouchers();
            },
            onError: (errors) => {
                showApprovalModal.value = false;
                toast.add({
                    severity: 'error',
                    summary: 'Error',
                    detail: errors.documents || 'Failed to approve voucher.',
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
        `/internal-audits/${currentVoucher.value.id}/reject`,
        { reason: rejectionReason.value },
        {
            preserveScroll: true,
            onSuccess: () => {
                toast.add({
                    severity: 'info',
                    summary: 'Rejected',
                    detail: `Voucher has been rejected.`,
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

const closeDocumentsModal = () => {
    showDocumentsModal.value = false;
    currentVoucher.value = null;
};

const closeDocumentViewer = () => {
    showDocumentViewer.value = false;
    documentUrl.value = '';
    loadingDocument.value = false;
    documentError.value = '';
    currentDocument.value = null;
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
            approved_today: props.stats.approved_today || 0,
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
        <Head title="Audit Queue" />
        <Toast />

        <!-- Stats Cards Section -->
        <div class="mb-4 grid">
            <div v-for="stat in statsData" :key="stat.title" class="col-12 md:col-3">
                <Card class="h-full">
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
        <Card>
            <template #title>
                <div class="flex align-items-center justify-content-between flex-wrap gap-3">
                    <div class="flex align-items-center gap-2">
                        <span>Audit Queue</span>
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
                    :emptyMessage="'No vouchers found.'"
                    :paginator="true" 
                    :rowsPerPageOptions="[5, 10, 20, 50, 100, 200, 500, 1000, 2000, 5000, 10000, 20000, 30000, 50000]" 
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
                            <span class="text-900 font-medium">
                                {{ slotProps.data.voucher_number || 'N/A' }}
                            </span>
                        </template>
                    </Column>

                    <!-- Type -->
                    <Column field="voucher_type" header="Type" headerStyle="width: 8%" :sortable="true">
                        <template #body="slotProps">
                            <Tag 
                                :value="slotProps.data.voucher_type?.toUpperCase() || 'STANDARD'" 
                                :severity="slotProps.data.voucher_type === 'prepayment' ? 'warning' : 
                                          slotProps.data.voucher_type === 'salary' ? 'success' : 'info'"
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

                    <!-- Documents -->
                    <Column header="Docs" headerStyle="width: 8%">
                        <template #body="slotProps">
                            <div class="flex flex-column gap-1">
                                <Button 
                                    icon="pi pi-file" 
                                    :label="`${getDocumentCount(slotProps.data)}`" 
                                    severity="info"
                                    text 
                                    size="small" 
                                    @click="openDocumentsModal(slotProps.data)" 
                                />
                                <Tag 
                                    v-if="hasAllRequiredDocuments(slotProps.data)" 
                                    value="Complete" 
                                    severity="success" 
                                    size="small" 
                                />
                                <Tag 
                                    v-else 
                                    :value="`Missing ${getMissingDocuments(slotProps.data).length}`"
                                    severity="warning" 
                                    size="small" 
                                />
                            </div>
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
                                    icon="pi pi-check" 
                                    severity="success" 
                                    size="small" 
                                    text 
                                    :disabled="!hasAllRequiredDocuments(slotProps.data)"
                                    v-tooltip="!hasAllRequiredDocuments(slotProps.data)
                                        ? `Missing: ${getMissingDocuments(slotProps.data).map(d => formatDocumentType(d)).join(', ')}`
                                        : 'Approve Voucher'"
                                    @click="openApproveModal(slotProps.data)" 
                                />
                                <Button 
                                    icon="pi pi-times" 
                                    severity="danger" 
                                    size="small" 
                                    text 
                                    v-tooltip="'Reject Voucher'"
                                    @click="openRejectModal(slotProps.data)" 
                                />
                            </div>
                        </template>
                    </Column>
                </DataTable>
            </template>
        </Card>

        <!-- Documents Modal -->
        <Dialog 
            v-model:visible="showDocumentsModal" 
            :header="`Documents - ${currentVoucher?.voucher_number || 'N/A'}`"
            :style="{ width: '600px' }" 
            :modal="true" 
            @update:visible="closeDocumentsModal"
        >
            <div class="p-fluid" v-if="currentVoucher">
                <div class="mb-4">
                    <h4>Required Documents:</h4>
                    <div class="mt-2 flex flex-wrap gap-2">
                        <Tag 
                            v-for="docType in requiredDocuments" 
                            :key="docType" 
                            :value="formatDocumentType(docType)"
                            :severity="currentVoucher.documents?.some(d => d.document_type === docType) ? 'success' : 'danger'"
                        />
                    </div>
                </div>

                <div v-if="currentVoucher.documents && currentVoucher.documents.length">
                    <h4>Attached Documents:</h4>
                    <div class="mt-2">
                        <div 
                            v-for="document in currentVoucher.documents" 
                            :key="document.id"
                            class="flex align-items-center justify-content-between border-round surface-50 mb-2 p-2"
                            :class="{ 'bg-green-50': requiredDocuments.includes(document.document_type) }"
                        >
                            <div class="flex align-items-center">
                                <i class="pi pi-file mr-2"></i>
                                <div class="flex flex-column">
                                    <span class="font-medium">
                                        {{ formatDocumentType(document.document_type) }}
                                    </span>
                                    <small class="text-500">
                                        {{ document.file_name || 'No filename' }}
                                    </small>
                                </div>
                            </div>
                            <Button 
                                icon="pi pi-eye" 
                                severity="info" 
                                text 
                                @click="openDocument(document)"
                                v-tooltip="'View Document'" 
                            />
                        </div>
                    </div>
                </div>
                <div v-else class="p-4 text-center">
                    <i class="pi pi-inbox text-500 text-4xl"></i>
                    <p class="text-500 mt-2">No documents attached</p>
                </div>
            </div>
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
                        will be returned to the originator.
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

        <!-- Approval Modal -->
        <Dialog 
            v-model:visible="showApprovalModal" 
            :style="{ width: '400px' }" 
            header="Confirm Approval" 
            :modal="true"
            @update:visible="closeApprovalModal"
        >
            <div class="flex align-items-center" v-if="currentVoucher">
                <i class="pi pi-exclamation-circle text-primary mr-3 text-2xl"></i>
                <span>
                    Approve Voucher <strong>{{ currentVoucher.voucher_number }}</strong>? 
                    This will move it to the next stage.
                </span>
            </div>

            <template #footer>
                <Button label="Cancel" icon="pi pi-times" @click="closeApprovalModal" text />
                <Button label="Approve" icon="pi pi-check-circle" severity="success" @click="handleApprove" />
            </template>
        </Dialog>

        <!-- Document Viewer -->
        <Dialog 
            v-model:visible="showDocumentViewer"
            :header="`Document Viewer - ${currentDocument?.file_name || 'Document'}`"
            :style="{ width: '90vw', height: '95vh' }" 
            :modal="true" 
            maximizable 
            @update:visible="closeDocumentViewer"
        >
            <div class="flex flex-column h-full">
                <!-- Loading State -->
                <div v-if="loadingDocument" class="flex align-items-center justify-content-center h-full">
                    <i class="pi pi-spin pi-spinner text-primary text-4xl"></i>
                    <span class="ml-2">Loading document...</span>
                </div>

                <!-- Error State -->
                <div v-else-if="documentError" class="flex flex-column align-items-center justify-content-center h-full">
                    <i class="pi pi-exclamation-triangle text-4xl text-red-500"></i>
                    <p class="text-500 mt-2">{{ documentError }}</p>
                    <Button label="Try Again" icon="pi pi-refresh" @click="loadDocument(currentDocument)" class="mt-3" />
                </div>

                <!-- Document Content -->
                <div v-else-if="documentUrl" class="flex flex-column h-full">
                    <div class="flex justify-content-between align-items-center mb-3">
                        <span class="font-bold">{{ currentDocument?.file_name }}</span>
                        <div class="flex gap-2">
                            <Button 
                                icon="pi pi-download" 
                                label="Download" 
                                @click="downloadDocument(currentDocument)"
                                severity="secondary" 
                                size="small" 
                            />
                            <Button 
                                icon="pi pi-external-link" 
                                label="Open in New Tab"
                                @click="openInNewTab(currentDocument)" 
                                severity="help" 
                                size="small" 
                            />
                        </div>
                    </div>

                    <div class="surface-border border-round flex-1 border-1">
                        <iframe 
                            :src="documentUrl" 
                            frameborder="0" 
                            width="100%" 
                            height="100%" 
                            @load="onDocumentLoad"
                            @error="onDocumentError" 
                            class="border-round"
                        ></iframe>
                    </div>
                </div>

                <!-- No Document State -->
                <div v-else class="flex align-items-center justify-content-center h-full">
                    <i class="pi pi-inbox text-500 text-4xl"></i>
                    <p class="text-500 mt-2">No document to display</p>
                </div>
            </div>

            <template #footer>
                <Button label="Close" icon="pi pi-times" @click="closeDocumentViewer" text />
            </template>
        </Dialog>
    </AppLayout>
</template>

<style scoped>
.line-clamp-2 {
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
}

.text-capitalize {
    text-transform: capitalize;
}

:deep(.p-datatable) {
    border: 1px solid #e5e7eb;
    border-radius: 6px;
}

:deep(.p-datatable-thead > tr > th) {
    background: #f8f9fa;
    color: #374151;
    font-weight: 600;
}

:deep(.p-datatable-tbody > tr) {
    transition: background-color 0.2s;
}

:deep(.p-datatable-tbody > tr:hover) {
    background: #f3f4f6;
}

:deep(.p-dialog .p-dialog-content) {
    padding: 1rem;
}

:deep(.p-dialog .p-dialog-header) {
    padding: 1.25rem;
}

:deep(.p-dialog .p-dialog-footer) {
    padding: 0.75rem 1.25rem;
}

:deep(.p-dialog) {
    display: flex;
    flex-direction: column;
}

:deep(.p-dialog-content) {
    flex: 1;
    display: flex;
    flex-direction: column;
}
</style>