<script setup>
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import Button from 'primevue/button';
import Card from 'primevue/card';
import Column from 'primevue/column';
import DataTable from 'primevue/datatable';
import Dialog from 'primevue/dialog';
import Menu from 'primevue/menu';
import Tag from 'primevue/tag';
import Toast from 'primevue/toast';
import { useToast } from 'primevue/usetoast';
import { computed, onMounted, ref, watch } from 'vue';
import { FilterMatchMode } from '@primevue/core/api';
import InputText from 'primevue/inputtext';
import InputIcon from 'primevue/inputicon';
import IconField from 'primevue/iconfield';
import Dropdown from 'primevue/dropdown';
import Calendar from 'primevue/calendar';
import Badge from 'primevue/badge';
import Timeline from 'primevue/timeline';
import Message from 'primevue/message';
import axios from 'axios';
import * as XLSX from 'xlsx';
import jsPDF from 'jspdf';
import autoTable from 'jspdf-autotable';

const toast = useToast();

// ✅ PROPS: Receive real data from Laravel controller
const props = defineProps({
    vouchers: {
        type: Object,
        required: true,
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
            total_vouchers: 0,
            pending_count: 0,
            approved_count: 0,
            rejected_count: 0,
            forwarded_count: 0,
            draft_count: 0,
            paid_count: 0,
            pending_mas_count: 0,
            pending_ag_count: 0,
            pending_ec_count: 0,
            pending_audit_count: 0,
            total_amount: 0,
            total_amount_paid: 0,
            total_amount_pending: 0,
            liability_count: 0,
        }),
    },
    users: {
        type: Array,
        default: () => [],
    },
    userMdas: {
        type: [Array, Object],
        default: () => [],
    },
    isAdmin: {
        type: Boolean,
        default: false,
    },
});

// State
const searchQuery = ref("");
const loading = ref(false);
const totalRecords = ref(0);
const exporting = ref(false);

// Filter states
const selectedVoucherType = ref('');
const selectedStatus = ref('');
const dateRange = ref(null);

// Modal states
const showWorkflowModal = ref(false);
const showDocumentViewer = ref(false);
const showConfirmationModal = ref(false);

const currentVoucher = ref(null);
const currentAction = ref(null);
const workflowHistory = ref([]);
const documentUrl = ref('');
const currentDocument = ref(null);

// ✅ FIXED: Initialize filters properly
const filters = ref({
    global: { value: null, matchMode: FilterMatchMode.CONTAINS },
});

const lazyParams = ref({
    first: 0,
    rows: 10,
    page: 1,
});

let debounceTimer = null;

// ✅ FIXED: Initialize vouchers as empty array
const vouchers = ref([]);

// ✅ FIXED: Process userMdas properly - handle both array and collection
const userMdas = computed(() => {
    // If it's null or undefined
    if (!props.userMdas) return [];
    
    // If it's already an array
    if (Array.isArray(props.userMdas)) {
        return props.userMdas;
    }
    
    // If it's an object with data property (Inertia pagination format)
    if (props.userMdas.data && Array.isArray(props.userMdas.data)) {
        return props.userMdas.data;
    }
    
    // If it's an object with items property (some collection formats)
    if (props.userMdas.items && Array.isArray(props.userMdas.items)) {
        return props.userMdas.items;
    }
    
    // If it's a plain object with numeric keys (could be from collection)
    if (typeof props.userMdas === 'object') {
        const values = Object.values(props.userMdas);
        if (values.length > 0 && typeof values[0] === 'object') {
            return values;
        }
    }
    
    return [];
});

// ✅ Check if user has MDAs
const hasMdas = computed(() => {
    return userMdas.value && userMdas.value.length > 0;
});

// ✅ Check if user is admin
const isAdmin = computed(() => {
    const page = usePage();
    const userRoles = page.props.auth?.userRoles || [];
    return props.isAdmin || userRoles.includes('admin') || userRoles.includes('super_admin');
});

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
    { label: 'Draft', value: 'draft' },
    { label: 'Saved', value: 'saved' },
    { label: 'Submitted', value: 'submitted' },
    { label: 'FA Approved', value: 'fa_approved' },
    { label: 'Audit Approved', value: 'audit_approved' },
    { label: 'Forwarded', value: 'forwarded' },
    { label: 'EC Approved', value: 'ec_approved' },
    { label: 'AG Approved', value: 'ag_approved' },
    { label: 'MAS Approved', value: 'mas_approved' },
    { label: 'Closed', value: 'closed' },
    { label: 'Rejected', value: 'rejected' },
    { label: 'Sent Back', value: 'sent_back' },
];

// =============================================
// WORKFLOW HELPER FUNCTIONS
// =============================================

const getActionSeverity = (action) => {
    const actionMap = {
        'Approved': 'success',
        'Declined': 'danger',
        'Rejected': 'danger',
        'Forwarded': 'info',
        'Submitted': 'info',
        'Created': 'info',
        'Updated': 'warning',
        'Saved': 'secondary',
        'Sent Back': 'warning',
        'Audit Approved': 'success',
        'FA Approved': 'success',
        'EC Approved': 'success',
        'AG Approved': 'success',
        'MAS Approved': 'success',
        'Closed': 'success',
    };
    return actionMap[action] || 'info';
};

const getActionIcon = (action) => {
    const iconMap = {
        'Approved': 'pi-check-circle',
        'Declined': 'pi-times-circle',
        'Rejected': 'pi-times-circle',
        'Forwarded': 'pi-send',
        'Submitted': 'pi-upload',
        'Created': 'pi-plus-circle',
        'Updated': 'pi-pencil',
        'Saved': 'pi-save',
        'Sent Back': 'pi-undo',
        'Audit Approved': 'pi-check-circle',
        'FA Approved': 'pi-check-circle',
        'EC Approved': 'pi-check-circle',
        'AG Approved': 'pi-check-circle',
        'MAS Approved': 'pi-check-circle',
        'Closed': 'pi-check-circle',
    };
    return iconMap[action] || 'pi-circle';
};

const getActionColor = (action) => {
    const colorMap = {
        'Approved': 'text-green-500 bg-green-100',
        'Declined': 'text-red-500 bg-red-100',
        'Rejected': 'text-red-500 bg-red-100',
        'Forwarded': 'text-blue-500 bg-blue-100',
        'Submitted': 'text-cyan-500 bg-cyan-100',
        'Created': 'text-purple-500 bg-purple-100',
        'Updated': 'text-orange-500 bg-orange-100',
        'Saved': 'text-gray-500 bg-gray-100',
        'Sent Back': 'text-yellow-500 bg-yellow-100',
        'Audit Approved': 'text-green-500 bg-green-100',
        'FA Approved': 'text-green-500 bg-green-100',
        'EC Approved': 'text-green-500 bg-green-100',
        'AG Approved': 'text-green-500 bg-green-100',
        'MAS Approved': 'text-green-500 bg-green-100',
        'Closed': 'text-green-500 bg-green-100',
    };
    return colorMap[action] || 'text-gray-500 bg-gray-100';
};

const getActionBorderColor = (action) => {
    const colorMap = {
        'Approved': 'border-green-500',
        'Declined': 'border-red-500',
        'Rejected': 'border-red-500',
        'Forwarded': 'border-blue-500',
        'Submitted': 'border-cyan-500',
        'Created': 'border-purple-500',
        'Updated': 'border-orange-500',
        'Saved': 'border-gray-500',
        'Sent Back': 'border-yellow-500',
        'Audit Approved': 'border-green-500',
        'FA Approved': 'border-green-500',
        'EC Approved': 'border-green-500',
        'AG Approved': 'border-green-500',
        'MAS Approved': 'border-green-500',
        'Closed': 'border-green-500',
    };
    return colorMap[action] || 'border-gray-300';
};

// =============================================
// VOUCHER PERMISSION FUNCTIONS
// =============================================

const canEditVoucher = (voucher) => {
    if (!voucher || !voucher.status) return false;
    const status = voucher.status.toLowerCase().trim();
    const editableStatuses = [
        'draft',
        'saved',
        'sent back',
        'returned',
        'declined',
        'rejected',
        'audit_rejected',
    ];
    return editableStatuses.includes(status);
};

const canDeleteVoucher = (voucher) => {
    if (isAdmin.value) {
        return true;
    }
    if (!voucher || !voucher.status) return false;
    const status = voucher.status.toLowerCase().trim();
    const deletableStatuses = ['draft', 'saved'];
    return deletableStatuses.includes(status);
};

// =============================================
// STATUS FUNCTIONS
// =============================================

const getStatusSeverity = (status) => {
    if (!status) return 'info';
    const normalizedStatus = status.toLowerCase().trim();
    switch (normalizedStatus) {
        case 'approved':
        case 'paid':
        case 'closed':
        case 'fa_approved':
        case 'ec_approved':
        case 'ag_approved':
        case 'mas_approved':
        case 'audit_approved':
            return 'success';
        case 'declined':
        case 'rejected':
        case 'decline and close':
        case 'mas_rejected':
            return 'danger';
        case 'sent back':
        case 'returned':
        case 'cancelled':
        case 'ec_review':
            return 'warning';
        case 'submitted':
        case 'pending':
        case 'forwarded':
            return 'secondary';
        case 'draft':
        case 'saved':
            return 'info';
        default:
            return 'info';
    }
};

const getStatusDisplayName = (status) => {
    if (!status) return 'Unknown';
    const names = {
        draft: 'Draft',
        saved: 'Saved',
        submitted: 'Submitted',
        fa_approved: 'FA Approved',
        audit_approved: 'Audit Approved',
        forwarded: 'Forwarded',
        ec_approved: 'EC Approved',
        ag_approved: 'AG Approved',
        mas_approved: 'MAS Approved',
        closed: 'Closed',
        rejected: 'Rejected',
        sent_back: 'Sent Back',
        ec_review: 'EC Review',
        mas_rejected: 'MAS Rejected',
    };
    return names[status.toLowerCase()] || status;
};

// =============================================
// STATS
// =============================================

const statsData = computed(() => [
    {
        title: 'Total Vouchers',
        value: props.stats?.total_vouchers || 0,
        icon: 'pi pi-file',
        color: 'text-blue-500',
        bgColor: 'bg-blue-50',
    },
    {
        title: 'Pending Approval',
        value: props.stats?.pending_count || 0,
        icon: 'pi pi-clock',
        color: 'text-orange-500',
        bgColor: 'bg-orange-50',
    },
    {
        title: 'Approved',
        value: props.stats?.approved_count || 0,
        icon: 'pi pi-check-circle',
        color: 'text-green-500',
        bgColor: 'bg-green-50',
    },
    {
        title: 'Rejected',
        value: props.stats?.rejected_count || 0,
        icon: 'pi pi-times-circle',
        color: 'text-red-500',
        bgColor: 'bg-red-50',
    },
]);

const financialStatsData = computed(() => [
    {
        title: 'Total Amount',
        value: formatCurrency(props.stats?.total_amount || 0),
        icon: 'pi pi-money-bill',
        color: 'text-green-500',
        bgColor: 'bg-green-50',
    },
    {
        title: 'Total Paid',
        value: formatCurrency(props.stats?.total_amount_paid || 0),
        icon: 'pi pi-check-circle',
        color: 'text-emerald-500',
        bgColor: 'bg-emerald-50',
    },
    {
        title: 'Total Pending',
        value: formatCurrency(props.stats?.total_amount_pending || 0),
        icon: 'pi pi-clock',
        color: 'text-orange-500',
        bgColor: 'bg-orange-50',
    },
    {
        title: 'Liability',
        value: props.stats?.liability_count || 0,
        icon: 'pi pi-book',
        color: 'text-purple-500',
        bgColor: 'bg-purple-50',
    },
]);

// =============================================
// MODAL FUNCTIONS
// =============================================

const openConfirmationModal = (voucher, action) => {
    if (action === 'edit' && !canEditVoucher(voucher)) {
        toast.add({
            severity: 'error',
            summary: 'Cannot Edit',
            detail: `Voucher ${voucher.voucher_number} is "${voucher.status}" and cannot be edited.`,
            life: 5000,
        });
        return;
    }
    if (action === 'delete' && !canDeleteVoucher(voucher)) {
        toast.add({
            severity: 'error',
            summary: 'Cannot Delete',
            detail: `Voucher ${voucher.voucher_number} is "${voucher.status}" and cannot be deleted.`,
            life: 5000,
        });
        return;
    }
    currentVoucher.value = voucher;
    currentAction.value = action;
    showConfirmationModal.value = true;
};

const confirmAction = () => {
    showConfirmationModal.value = false;
    if (!currentVoucher.value) return;
    const id = currentVoucher.value.id;
    if (currentAction.value === 'delete') {
        router.delete(`/vouchers/${id}`, {
            preserveScroll: true,
            onSuccess: () => {
                toast.add({
                    severity: 'success',
                    summary: 'Deleted',
                    detail: `Voucher ${currentVoucher.value.voucher_number} successfully deleted.`,
                    life: 3000,
                });
                loadVouchers();
            },
            onError: (errors) => {
                toast.add({
                    severity: 'error',
                    summary: 'Error',
                    detail: errors.message || 'Failed to delete the voucher.',
                    life: 5000,
                });
            },
        });
    } else if (currentAction.value === 'edit') {
        router.visit(`/vouchers/${id}/edit`);
    }
};

// =============================================
// WORKFLOW MODAL
// =============================================

const openWorkflowModal = (voucher) => {
    currentVoucher.value = voucher;
    showWorkflowModal.value = true;
    
    try {
        const approvals = voucher.approvals || [];
        workflowHistory.value = [...approvals].sort((a, b) => {
            const dateA = new Date(a.action_at || a.created_at);
            const dateB = new Date(b.action_at || b.created_at);
            return dateB - dateA;
        });
    } catch (error) {
        console.error('Error loading workflow:', error);
        workflowHistory.value = [];
    }
};

// =============================================
// DOCUMENT VIEWER
// =============================================

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
    }
    
    currentDocument.value = document;
    documentUrl.value = docUrl;
    showDocumentViewer.value = true;
};

const closeDocumentViewer = () => {
    showDocumentViewer.value = false;
    documentUrl.value = '';
    currentDocument.value = null;
};

// =============================================
// PRINT VOUCHER
// =============================================

const printVoucher = (voucher) => {
    const printUrl = `/vouchers/${voucher.id}/print`;
    window.open(printUrl, '_blank');
};

// =============================================
// CREATE VOUCHER
// =============================================

const createVoucher = () => {
    router.visit('/vouchers/create');
};

// =============================================
// FORMAT FUNCTIONS
// =============================================

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

// =============================================
// EXPORT FUNCTIONS
// =============================================

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
            'Status': getStatusDisplayName(v.status) || 'N/A',
            'Narration': v.narration || 'N/A',
            'Bank': v.bank_activity?.bank_name || 'N/A',
        }));

        const ws = XLSX.utils.json_to_sheet(exportData);
        const wb = XLSX.utils.book_new();
        XLSX.utils.book_append_sheet(wb, ws, 'Vouchers');
        XLSX.writeFile(wb, `Vouchers_${new Date().toISOString().split('T')[0]}.xlsx`);
        
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

        doc.setFontSize(18);
        doc.setTextColor(33, 37, 41);
        doc.text('Vouchers List', pageWidth / 2, 15, { align: 'center' });
        
        doc.setFontSize(10);
        doc.setTextColor(108, 117, 125);
        doc.text(`Generated: ${new Date().toLocaleString()}`, pageWidth / 2, 22, { align: 'center' });
        doc.text(`Total Records: ${vouchers.value.length}`, pageWidth / 2, 28, { align: 'center' });

        const tableData = vouchers.value.map(v => [
            v.voucher_number || 'N/A',
            v.voucher_type || 'N/A',
            formatDate(v.voucher_date),
            v.mda?.name || 'N/A',
            v.payee_name || 'N/A',
            `₦${Number(v.total_amount || 0).toLocaleString('en-NG', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`,
            getStatusDisplayName(v.status) || 'N/A',
            v.narration || 'N/A',
        ]);

        autoTable(doc, {
            head: [['Voucher #', 'Type', 'Date', 'MDA', 'Payee', 'Amount', 'Status', 'Narration']],
            body: tableData,
            startY: 35,
            theme: 'striped',
            headStyles: {
                fillColor: [52, 58, 64],
                textColor: [255, 255, 255],
                fontSize: 9,
                fontStyle: 'bold',
            },
            bodyStyles: { fontSize: 8 },
            columnStyles: {
                0: { cellWidth: 22 },
                1: { cellWidth: 16 },
                2: { cellWidth: 18 },
                3: { cellWidth: 30 },
                4: { cellWidth: 22 },
                5: { cellWidth: 24 },
                6: { cellWidth: 20 },
                7: { cellWidth: 38 },
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

        doc.save(`Vouchers_${new Date().toISOString().split('T')[0]}.pdf`);
        
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

// =============================================
// LOAD VOUCHERS
// =============================================

const loadVouchers = async () => {
    loading.value = true;
    try {
        const params = {
            per_page: lazyParams.value.rows,
            page: lazyParams.value.page,
            search: searchQuery.value,
            voucher_type: selectedVoucherType.value,
            status: selectedStatus.value,
        };

        if (dateRange.value && dateRange.value.length === 2) {
            if (dateRange.value[0]) {
                const d = new Date(dateRange.value[0]);
                params.date_from = d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0') + '-' + String(d.getDate()).padStart(2, '0');
            }
            if (dateRange.value[1]) {
                const d = new Date(dateRange.value[1]);
                params.date_to = d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0') + '-' + String(d.getDate()).padStart(2, '0');
            }
        }

        const response = await axios.get('/vouchers/search', { params });
        
        // ✅ FIXED: Always ensure vouchers is set to an array
        if (response.data && response.data.status === 'success') {
            if (response.data.vouchers && response.data.vouchers.data) {
                vouchers.value = Array.isArray(response.data.vouchers.data) 
                    ? response.data.vouchers.data 
                    : [];
                totalRecords.value = response.data.vouchers.total || 0;
            } else if (Array.isArray(response.data.vouchers)) {
                vouchers.value = response.data.vouchers;
                totalRecords.value = response.data.paginator?.total || 0;
            } else {
                vouchers.value = [];
                totalRecords.value = 0;
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
            detail: error.response?.data?.message || "Failed to load data", 
            life: 3000 
        });
        vouchers.value = [];
        totalRecords.value = 0;
    }
    loading.value = false;
};

// =============================================
// WATCHERS & LIFECYCLE
// =============================================

watch([selectedVoucherType, selectedStatus, dateRange, searchQuery], () => {
    clearTimeout(debounceTimer);
    debounceTimer = setTimeout(() => {
        lazyParams.value.page = 1;
        loadVouchers();
    }, 500);
}, { deep: true });

const onPage = (event) => {
    lazyParams.value.page = event.page + 1;
    lazyParams.value.first = event.first;
    lazyParams.value.rows = event.rows;
    loadVouchers();
};

const clearFilters = () => {
    searchQuery.value = '';
    selectedVoucherType.value = '';
    selectedStatus.value = '';
    dateRange.value = null;
    lazyParams.value.page = 1;
    loadVouchers();
};

const refreshData = () => {
    loadVouchers();
    toast.add({
        severity: 'success',
        summary: 'Refreshed',
        detail: 'Data refreshed successfully',
        life: 2000,
    });
};

const breadcrumbs = [{ title: 'Vouchers', href: '#' }];
const dt = ref(null);

// =============================================
// INIT
// =============================================

onMounted(() => {
    // Debug logs to see what's being passed
    console.log('User MDAs raw:', props.userMdas);
    console.log('User MDAs processed:', userMdas.value);
    console.log('Has MDAs:', hasMdas.value);
    console.log('Is Admin:', isAdmin.value);
    
    lazyParams.value.page = 1;
    // ✅ FIXED: Initialize with props data
    if (props.vouchers && props.vouchers.data) {
        vouchers.value = Array.isArray(props.vouchers.data) ? props.vouchers.data : [];
        totalRecords.value = props.vouchers.total || 0;
    }
    loadVouchers();
});
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head title="Voucher List" />
        <Toast />

        <!-- ✅ Show MDA info banner when user has MDAs -->
        <div v-if="!isAdmin && hasMdas" class="mb-4">
            <Message severity="info" :closable="false">
                <div class="flex align-items-center gap-2 flex-wrap">
                    <i class="pi pi-building"></i>
                    <span>
                        <strong>Showing vouchers for your assigned MDAs:</strong>
                        <span v-for="(mda, index) in userMdas" :key="mda.id || index" class="ml-2">
                            <Badge :value="mda.name || mda" severity="info" />
                        </span>
                    </span>
                </div>
            </Message>
        </div>

        <!-- ✅ Show warning only when user has NO MDAs and is NOT admin -->
        <div v-if="!isAdmin && !hasMdas" class="mb-4">
            <Message severity="warn" :closable="false">
                <div class="flex align-items-center gap-2">
                    <i class="pi pi-exclamation-triangle"></i>
                    <span>
                        <strong>No MDAs assigned to you.</strong>
                        Please contact your administrator to get MDA access.
                    </span>
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

        <!-- Financial Stats Cards -->
        <div class="mb-4 grid">
            <div v-for="stat in financialStatsData" :key="stat.title" class="col-12 md:col-3">
                <Card class="h-full stat-card financial-card">
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

        <Card>
            <template #title>
                <div class="flex justify-content-between align-items-center flex-wrap gap-3">
                    <div class="flex align-items-center gap-2">
                        <span>Voucher List</span>
                        <Badge :value="totalRecords" severity="info" />
                        <!-- ✅ MDA Filter Badge -->
                        <Badge 
                            v-if="!isAdmin && hasMdas"
                            value="Filtered by MDAs"
                            severity="info"
                            class="ml-2"
                        />
                        <Badge 
                            v-if="isAdmin"
                            value="Admin - All MDAs"
                            severity="success"
                            class="ml-2"
                        />
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
                        <Button 
                            label="Create Voucher" 
                            icon="pi pi-plus" 
                            severity="primary" 
                            size="small"
                            @click="createVoucher" 
                        />
                    </div>
                </div>
            </template>

            <template #content>
                <!-- Filter Section -->
                <div class="mb-4">
                    <div class="grid">
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

                        <div class="col-12 md:col-2 flex align-items-center">
                            <Button 
                                icon="pi pi-external-link" 
                                label="Export" 
                                severity="secondary"
                                size="small"
                                @click="dt?.exportCSV()" 
                            />
                        </div>
                    </div>
                    <div class="flex justify-content-between mt-2">
                        <span class="text-sm text-500">
                            <i class="pi pi-info-circle mr-1"></i>
                            {{ totalRecords }} record(s) found
                        </span>
                    </div>
                </div>

                <!-- DataTable -->
                <DataTable 
                    ref="dt"
                    :filters="filters" 
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
                    :globalFilterFields="['voucher_number', 'voucher_type', 'voucher_date', 'mda.name', 'narration', 'status']"
                    lazy 
                    size="small"
                    paginatorTemplate="RowsPerPageDropdown FirstPageLink PrevPageLink CurrentPageReport NextPageLink LastPageLink"
                    currentPageReportTemplate="{first} to {last} of {totalRecords}"
                    exportFilename="vouchers"
                    showGridlines
                >
                    <Column field="voucher_number" header="Voucher #" headerStyle="width: 10%" :sortable="true">
                        <template #body="slotProps">
                            <Link :href="'/vouchers/' + slotProps.data.id" class="text-primary-600 font-medium hover:underline">
                                {{ slotProps.data.voucher_number }}
                            </Link>
                        </template>
                    </Column>

                    <Column field="voucher_type" header="Type" headerStyle="width: 8%" :sortable="true">
                        <template #body="slotProps">
                            <Tag 
                                :value="slotProps.data.voucher_type?.toUpperCase() || 'STANDARD'" 
                                :severity="slotProps.data.voucher_type === 'prepayment' ? 'warning' : 
                                          slotProps.data.voucher_type === 'salary' ? 'success' : 'info'"
                            />
                        </template>
                    </Column>

                    <Column field="voucher_date" header="Date" headerStyle="width: 8%" :sortable="true">
                        <template #body="slotProps">
                            <span class="text-900">{{ formatDate(slotProps.data.voucher_date) }}</span>
                        </template>
                    </Column>

                    <Column field="mda.name" header="MDA" headerStyle="width: 15%" :sortable="true">
                        <template #body="slotProps">
                            <div class="flex flex-column">
                                <span class="font-medium">{{ slotProps.data.mda?.name || 'N/A' }}</span>
                                <span class="text-500 text-xs">{{ slotProps.data.mda?.code || '' }}</span>
                            </div>
                        </template>
                    </Column>

                    <Column field="payee_name" header="Payee" headerStyle="width: 10%">
                        <template #body="slotProps">
                            <span class="text-600">{{ slotProps.data.payee_name || 'N/A' }}</span>
                        </template>
                    </Column>

                    <Column field="total_amount" header="Amount" headerStyle="width: 12%" bodyClass="font-bold text-right" :sortable="true">
                        <template #body="slotProps">
                            <span class="text-900">{{ formatCurrency(slotProps.data.total_amount || 0) }}</span>
                        </template>
                    </Column>

                    <Column field="bank_activity" header="Bank" headerStyle="width: 10%">
                        <template #body="slotProps">
                            <div v-if="slotProps.data.bank_activity">
                                <div class="font-medium">{{ slotProps.data.bank_activity.bank_name }}</div>
                                <div class="text-500 text-xs">{{ slotProps.data.bank_activity.account_number }}</div>
                            </div>
                            <span v-else class="text-500">Not Assigned</span>
                        </template>
                    </Column>

                    <Column field="status" header="Status" headerStyle="width: 8%" :sortable="true">
                        <template #body="slotProps">
                            <Tag 
                                :value="getStatusDisplayName(slotProps.data.status)" 
                                :severity="getStatusSeverity(slotProps.data.status)"
                            />
                        </template>
                    </Column>

                    <Column header="Actions" headerStyle="width: 15%" bodyClass="text-center">
                        <template #body="slotProps">
                            <div class="flex justify-content-center gap-1 flex-wrap">
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
                                    @click="router.visit('/vouchers/' + slotProps.data.id)" 
                                />
                                <Button 
                                    icon="pi pi-sitemap" 
                                    severity="secondary" 
                                    text 
                                    rounded 
                                    size="small" 
                                    v-tooltip.top="'View Workflow'"
                                    @click="openWorkflowModal(slotProps.data)" 
                                />
                                <Button 
                                    icon="pi pi-file" 
                                    severity="primary" 
                                    text 
                                    rounded 
                                    size="small" 
                                    v-tooltip.top="'View Documents'"
                                    @click="viewDocument(slotProps.data.documents?.[0])" 
                                />
                                <Button 
                                    icon="pi pi-pencil" 
                                    severity="secondary" 
                                    text 
                                    rounded 
                                    size="small" 
                                    :disabled="!canEditVoucher(slotProps.data)"
                                    v-tooltip.top="canEditVoucher(slotProps.data) ? 'Edit Voucher' : `Cannot edit - Status: ${slotProps.data.status}`"
                                    @click="openConfirmationModal(slotProps.data, 'edit')" 
                                />
                                <Button 
                                    v-if="isAdmin"
                                    icon="pi pi-trash" 
                                    severity="danger" 
                                    text 
                                    rounded 
                                    size="small" 
                                    :disabled="!canDeleteVoucher(slotProps.data)"
                                    v-tooltip.top="canDeleteVoucher(slotProps.data) ? 'Delete Voucher' : `Cannot delete - Status: ${slotProps.data.status}`"
                                    @click="openConfirmationModal(slotProps.data, 'delete')" 
                                />
                            </div>
                        </template>
                    </Column>
                </DataTable>
            </template>
        </Card>

        <!-- Workflow Timeline Modal -->
        <Dialog
            v-model:visible="showWorkflowModal"
            :style="{ width: '650px', maxHeight: '80vh' }"
            :header="`Workflow History - ${currentVoucher?.voucher_number}`"
            :modal="true"
            class="workflow-dialog"
            @update:visible="showWorkflowModal = false; workflowHistory = []"
        >
            <div class="workflow-timeline" style="max-height: 60vh; overflow-y: auto;">
                <div v-if="workflowHistory.length === 0" class="text-center p-4">
                    <i class="pi pi-clock text-400 text-3xl mb-2"></i>
                    <p class="text-600">No workflow history available</p>
                    <p class="text-500 text-sm">Approval history will appear here once the voucher is processed.</p>
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
                            :class="getActionColor(slotProps.item.action)"
                        >
                            <i :class="getActionIcon(slotProps.item.action)" class="text-white text-sm"></i>
                        </span>
                    </template>
                    <template #content="slotProps">
                        <div class="workflow-card-item border-round border-1 p-3" :class="getActionBorderColor(slotProps.item.action)">
                            <div class="flex flex-column gap-2">
                                <div class="flex align-items-center justify-content-between flex-wrap">
                                    <span class="font-semibold text-primary">
                                        {{ slotProps.item.approval_role || 'System' }}
                                    </span>
                                    <Tag 
                                        :value="slotProps.item.action" 
                                        :severity="getActionSeverity(slotProps.item.action)"
                                        size="small"
                                    />
                                </div>
                                <div class="text-600 text-sm">
                                    <i class="pi pi-clock mr-1"></i>
                                    {{ formatDateTime(slotProps.item.action_at || slotProps.item.created_at) }}
                                </div>
                                <div v-if="slotProps.item.comment" class="text-500 text-sm mt-1 p-2 bg-gray-50 border-round">
                                    <i class="pi pi-comment mr-1"></i>
                                    {{ slotProps.item.comment }}
                                </div>
                                <div v-if="slotProps.item.user" class="text-500 text-xs">
                                    <i class="pi pi-user mr-1"></i>
                                    By: {{ slotProps.item.user.name }}
                                </div>
                                <div v-if="slotProps.item.status" class="text-500 text-xs">
                                    <i class="pi pi-info-circle mr-1"></i>
                                    Status: {{ slotProps.item.status }}
                                </div>
                            </div>
                        </div>
                    </template>
                </Timeline>
            </div>
            <template #footer>
                <Button label="Close" icon="pi pi-times" @click="showWorkflowModal = false; workflowHistory = []" text />
            </template>
        </Dialog>

        <!-- Document Viewer Modal -->
        <Dialog
            v-model:visible="showDocumentViewer"
            :header="`Document Viewer - ${currentDocument?.file_name || 'Document'}`"
            :style="{ width: '80vw', height: '90vh' }"
            :modal="true"
            maximizable
            @update:visible="closeDocumentViewer"
        >
            <div class="flex flex-column h-full">
                <div class="flex justify-content-between align-items-center mb-3 pb-2 border-bottom-1 surface-border">
                    <div class="flex align-items-center gap-2">
                        <i class="pi pi-file-pdf text-xl text-red-500" v-if="currentDocument?.file_name?.endsWith('.pdf')"></i>
                        <i class="pi pi-file-image text-xl text-blue-500" v-else-if="currentDocument?.file_name?.match(/\.(jpg|jpeg|png|gif)$/i)"></i>
                        <i class="pi pi-file text-xl text-gray-500" v-else></i>
                        <span class="font-semibold">{{ currentDocument?.file_name }}</span>
                    </div>
                    <div class="flex gap-2">
                        <Button 
                            icon="pi pi-download" 
                            label="Download" 
                            severity="secondary" 
                            size="small"
                            @click="window.open(documentUrl, '_blank')" 
                        />
                        <Button 
                            icon="pi pi-external-link" 
                            label="Open in New Tab" 
                            severity="info" 
                            size="small"
                            @click="window.open(documentUrl, '_blank')" 
                        />
                    </div>
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

        <!-- Confirmation Modal -->
        <Dialog 
            v-model:visible="showConfirmationModal" 
            :style="{ width: '400px' }" 
            header="Confirm Action" 
            :modal="true"
        >
            <div class="flex align-items-center">
                <i :class="currentAction === 'delete'
                    ? 'pi pi-exclamation-triangle mr-3 text-red-500'
                    : 'pi pi-question-circle mr-3 text-orange-500'
                " style="font-size: 2rem"></i>

                <span v-if="currentVoucher && currentAction === 'delete'">
                    Are you sure you want to
                    <strong>permanently delete</strong> Voucher
                    <strong>{{ currentVoucher.voucher_number }}</strong>? This action cannot be undone.
                </span>

                <span v-else-if="currentVoucher && currentAction === 'edit'">
                    Do you want to proceed to the edit page for Voucher
                    <strong>{{ currentVoucher.voucher_number }}</strong>?
                </span>
            </div>

            <template #footer>
                <Button label="No" icon="pi pi-times" @click="showConfirmationModal = false" text />
                <Button 
                    :label="currentAction === 'delete' ? 'Yes, Delete' : 'Yes, Proceed'"
                    :icon="currentAction === 'delete' ? 'pi pi-trash' : 'pi pi-check'"
                    :severity="currentAction === 'delete' ? 'danger' : 'secondary'"
                    @click="confirmAction" 
                    autofocus 
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

.financial-card :deep(.p-card) {
    background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
}

:deep(.p-datatable) {
    border: 1px solid #e5e7eb;
    border-radius: 6px;
    overflow: hidden;
}

:deep(.p-datatable-thead > tr > th) {
    background: #f8f9fa;
    color: #374151;
    font-weight: 600;
}

:deep(.p-datatable-tbody > tr:hover) {
    background: #f3f4f6;
}

.workflow-card-item {
    background: white;
    transition: all 0.2s ease;
}

.workflow-card-item:hover {
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
}

.workflow-timeline::-webkit-scrollbar {
    width: 4px;
}

.workflow-timeline::-webkit-scrollbar-track {
    background: #f1f1f1;
    border-radius: 4px;
}

.workflow-timeline::-webkit-scrollbar-thumb {
    background: #c1c1c1;
    border-radius: 4px;
}

.workflow-timeline::-webkit-scrollbar-thumb:hover {
    background: #a8a8a8;
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

.custom-timeline :deep(.p-timeline-event-marker) {
    background: transparent !important;
    border: none !important;
}

.workflow-dialog :deep(.p-dialog-header) {
    background: #f8fafc;
    border-bottom: 1px solid #e2e8f0;
}

.workflow-dialog :deep(.p-dialog-content) {
    padding: 1rem;
}

@media (max-width: 768px) {
    :deep(.p-datatable) {
        font-size: 0.875rem;
    }
    
    :deep(.p-datatable-thead > tr > th),
    :deep(.p-datatable-tbody > tr > td) {
        padding: 0.5rem;
    }
}
</style>