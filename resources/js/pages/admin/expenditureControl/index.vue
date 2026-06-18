<!-- resources/js/pages/admin/expenditure-control/index.vue -->
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
import Calendar from 'primevue/calendar';
import RadioButton from 'primevue/radiobutton'; // <-- ADD THIS IMPORT
import { useToast } from 'primevue/usetoast';
import { computed, onMounted, ref, watch } from 'vue';
import * as XLSX from 'xlsx';
import jsPDF from 'jspdf';
import autoTable from 'jspdf-autotable';

const toast = useToast();

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
            paid_count: 0,
            pending_mas_count: 0,
            pending_ag_count: 0,
            total_amount_paid: 0,
            total_amount_pending: 0,
        }),
    },
    users: {
        type: Array,
        default: () => [],
    },
});

// State
const vouchers = ref([]);
const searchQuery = ref('');
const loading = ref(false);
const totalRecords = ref(0);
const exporting = ref(false);
const isProcessing = ref(false);

// Filter states
const selectedVoucherType = ref('');
const selectedStatus = ref('');
const selectedPaymentStatus = ref('');
const dateRange = ref(null);

// Modal states
const showRejectionModal = ref(false);
const showApprovalModal = ref(false);
const showForwardModal = ref(false);
const showPaymentModal = ref(false);
const showAssignModal = ref(false);
const showWorkflowModal = ref(false);
const showDocumentViewer = ref(false);
const showConfirmForwardModal = ref(false);

const currentVoucher = ref(null);
const rejectionReason = ref('');
const rejectionTouched = ref(false);
const paymentReference = ref('');
const paymentComment = ref('');
const selectedUser = ref(null);
const selectedDestination = ref('');
const forwardComment = ref('');
const workflowHistory = ref([]);
const documentUrl = ref('');
const loadingDocument = ref(false);
const documentError = ref('');
const currentDocument = ref(null);

// Destination options
const destinationOptions = [
    { label: 'Accountant General (AG)', value: 'ag', icon: 'pi pi-user' },
    { label: 'Management Account Section (MAS)', value: 'mas', icon: 'pi pi-money-bill' },
    { label: 'Inspectorate', value: 'inspectorate', icon: 'pi pi-search' },
    { label: 'Treasury Cash Office (TCO)', value: 'tco', icon: 'pi pi-building' },
];

// Stats
const stats = ref({
    pending_count: 0,
    approved_today: 0,
    rejected_today: 0,
    total_processed: 0,
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
    status: { value: null, matchMode: FilterMatchMode.CONTAINS },
    payment_status: { value: null, matchMode: FilterMatchMode.EQUALS },
});

const lazyParams = ref({
    first: 0,
    rows: 10,
    page: 1,
});

let debounceTimer = null;

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
    { label: 'Forwarded from FA', value: 'forwarded' },
    { label: 'EC Approved', value: 'ec_approved' },
    { label: 'Sent Back', value: 'sent_back' },
    { label: 'Rejected', value: 'rejected' },
];

const paymentStatusOptions = [
    { label: 'All Payment Status', value: '' },
    { label: 'Paid', value: 'paid' },
    { label: 'Pending MAS', value: 'awaiting_mas' },
    { label: 'Pending AG', value: 'awaiting_ag' },
];

// Stats cards data
const statsData = computed(() => [
    {
        title: 'Pending EC Review',
        value: stats.value.pending_count,
        icon: 'pi pi-clock',
        color: 'text-blue-500',
        bgColor: 'bg-blue-50',
    },
    {
        title: 'Approved Today',
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
    {
        title: 'Paid Vouchers',
        value: stats.value.paid_count,
        icon: 'pi pi-check-circle',
        color: 'text-green-500',
        bgColor: 'bg-green-50',
    },
    {
        title: 'Pending MAS',
        value: stats.value.pending_mas_count,
        icon: 'pi pi-hourglass',
        color: 'text-yellow-500',
        bgColor: 'bg-yellow-50',
    },
]);

const breadcrumbs = [
    { title: 'Expenditure Control', href: '/expenditure-control' },
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

// Get status badge severity
const getStatusSeverity = (status) => {
    const statuses = {
        forwarded: 'warning',
        ec_approved: 'success',
        sent_back: 'danger',
        rejected: 'danger',
        approved: 'success',
        paid: 'success',
        awaiting_mas: 'warning',
        awaiting_ag: 'info',
    };
    return statuses[status?.toLowerCase()] || 'info';
};

// Get status display name
const getStatusDisplayName = (status) => {
    const names = {
        forwarded: 'Forwarded from FA',
        ec_approved: 'EC Approved',
        sent_back: 'Sent Back',
        rejected: 'Rejected',
        approved: 'Approved',
        paid: 'Paid',
        awaiting_mas: 'Pending MAS',
        awaiting_ag: 'Pending AG',
    };
    return names[status?.toLowerCase()] || status || 'Unknown';
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

// Get destination label
const getDestinationLabel = (value) => {
    const option = destinationOptions.find(d => d.value === value);
    return option ? option.label : value;
};

// Get next stage based on destination
const getNextStage = (voucher, destination) => {
    if (!voucher) return 'N/A';
    
    if (destination === 'inspectorate' || destination === 'tco') {
        return 'Inspectorate → TCO';
    }
    if (destination === 'ag' || destination === 'mas') {
        return 'AG → MAS';
    }
    return 'Unknown';
};

// Load vouchers
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
        if (selectedPaymentStatus.value) {
            params.payment_status = selectedPaymentStatus.value;
        }
        if (dateRange.value && dateRange.value.length === 2) {
            if (dateRange.value[0]) {
                params.date_from = formatDateForApi(dateRange.value[0]);
            }
            if (dateRange.value[1]) {
                params.date_to = formatDateForApi(dateRange.value[1]);
            }
        }

        const response = await axios.get('/expenditure-control/search', { params });
        
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
                    paid_count: response.data.stats.paid_count || 0,
                    pending_mas_count: response.data.stats.pending_mas_count || 0,
                    pending_ag_count: response.data.stats.pending_ag_count || 0,
                    total_amount_paid: response.data.stats.total_amount_paid || 0,
                    total_amount_pending: response.data.stats.total_amount_pending || 0,
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
    selectedPaymentStatus.value = '';
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
            'Payment Status': getPaymentStatusLabel(v.payment_status) || 'N/A',
            'Narration': v.narration || 'N/A',
        }));

        const ws = XLSX.utils.json_to_sheet(exportData);
        const wb = XLSX.utils.book_new();
        XLSX.utils.book_append_sheet(wb, ws, 'Vouchers');
        XLSX.writeFile(wb, `Expenditure_Control_Vouchers_${new Date().toISOString().split('T')[0]}.xlsx`);
        
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

        doc.setFontSize(18);
        doc.setTextColor(33, 37, 41);
        doc.text('Expenditure Control Vouchers', pageWidth / 2, 15, { align: 'center' });
        
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
            v.status || 'N/A',
            getPaymentStatusLabel(v.payment_status) || 'N/A',
        ]);

        autoTable(doc, {
            head: [['Voucher #', 'Type', 'Date', 'MDA', 'Payee', 'Amount', 'Status', 'Payment Status']],
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
                0: { cellWidth: 25 },
                1: { cellWidth: 18 },
                2: { cellWidth: 22 },
                3: { cellWidth: 35 },
                4: { cellWidth: 30 },
                5: { cellWidth: 28 },
                6: { cellWidth: 22 },
                7: { cellWidth: 22 },
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

        doc.save(`Expenditure_Control_Vouchers_${new Date().toISOString().split('T')[0]}.pdf`);
        
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

// Modal functions
const openApproveModal = (voucher) => {
    currentVoucher.value = voucher;
    selectedDestination.value = '';
    forwardComment.value = '';
    showForwardModal.value = true;
};

const openRejectModal = (voucher) => {
    currentVoucher.value = voucher;
    rejectionReason.value = '';
    rejectionTouched.value = false;
    showRejectionModal.value = true;
};

const openPaymentModal = (voucher) => {
    currentVoucher.value = voucher;
    paymentReference.value = '';
    paymentComment.value = '';
    showPaymentModal.value = true;
};

const openAssignModal = (voucher) => {
    currentVoucher.value = voucher;
    selectedUser.value = null;
    showAssignModal.value = true;
};

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

// Open confirmation modal after selecting destination
const openConfirmForwardModal = () => {
    if (!selectedDestination.value) {
        toast.add({
            severity: 'warn',
            summary: 'Required',
            detail: 'Please select a destination for this voucher.',
            life: 3000,
        });
        return;
    }
    showConfirmForwardModal.value = true;
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

const closeDocumentViewer = () => {
    showDocumentViewer.value = false;
    documentUrl.value = '';
    currentDocument.value = null;
};

// Handle forward with destination
const handleForward = () => {
    if (!currentVoucher.value) return;
    if (!selectedDestination.value) {
        toast.add({
            severity: 'warn',
            summary: 'Required',
            detail: 'Please select a destination.',
            life: 3000,
        });
        return;
    }

    isProcessing.value = true;

    const destinationMap = {
        'ag': 'Accountant General',
        'mas': 'Management Account Section',
        'inspectorate': 'Inspectorate',
        'tco': 'Treasury Cash Office'
    };

    const destinationName = destinationMap[selectedDestination.value] || selectedDestination.value;

    // Close the forward modal first, then show processing
    showForwardModal.value = false;

    router.post(`/expenditure-control/vouchers/${currentVoucher.value.id}/forward`, {
        destination: selectedDestination.value,
        comment: forwardComment.value || `Forwarded to ${destinationName}`,
    }, {
        preserveScroll: true,
        onSuccess: () => {
            showConfirmForwardModal.value = false;
            isProcessing.value = false;
            toast.add({
                severity: 'success',
                summary: 'Forwarded Successfully',
                detail: `Voucher ${currentVoucher.value.voucher_number} forwarded to ${destinationName}.`,
                life: 5000,
            });
            currentVoucher.value = null;
            selectedDestination.value = '';
            forwardComment.value = '';
            loadVouchers();
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

    isProcessing.value = true;

    router.post(`/expenditure-control/vouchers/${currentVoucher.value.id}/reject`, {
        reason: rejectionReason.value,
    }, {
        preserveScroll: true,
        onSuccess: () => {
            toast.add({
                severity: 'info',
                summary: 'Rejected',
                detail: `Voucher ${currentVoucher.value.voucher_number} returned to DFA.`,
                life: 4000,
            });
            showRejectionModal.value = false;
            isProcessing.value = false;
            loadVouchers();
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

// Handle mark as paid
const handleMarkAsPaid = () => {
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

    router.post(`/expenditure-control/vouchers/${currentVoucher.value.id}/mark-paid`, {
        payment_reference: paymentReference.value,
        paymentComment: paymentComment.value || '',
    }, {
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
            loadVouchers();
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

// Handle assign to user
const handleAssign = () => {
    if (!selectedUser.value) {
        toast.add({
            severity: 'warn',
            summary: 'Required',
            detail: 'Please select a user to assign this voucher.',
            life: 3000,
        });
        return;
    }

    isProcessing.value = true;

    router.post(`/expenditure-control/vouchers/${currentVoucher.value.id}/assign`, {
        user_id: selectedUser.value.id,
    }, {
        preserveScroll: true,
        onSuccess: () => {
            showAssignModal.value = false;
            isProcessing.value = false;
            toast.add({
                severity: 'success',
                summary: 'Assigned',
                detail: `Voucher ${currentVoucher.value.voucher_number} assigned to ${selectedUser.value.name}.`,
                life: 5000,
            });
            loadVouchers();
        },
        onError: (errors) => {
            isProcessing.value = false;
            console.error('Assignment error:', errors);
            toast.add({
                severity: 'error',
                summary: 'Error',
                detail: errors.message || 'Failed to assign voucher.',
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
    loadVouchers();
};

// Modal close handlers
const closeRejectionModal = () => {
    showRejectionModal.value = false;
    currentVoucher.value = null;
    rejectionReason.value = '';
    rejectionTouched.value = false;
};

const closeForwardModal = () => {
    showForwardModal.value = false;
    currentVoucher.value = null;
    selectedDestination.value = '';
    forwardComment.value = '';
};

const closeConfirmForwardModal = () => {
    showConfirmForwardModal.value = false;
    if (!isProcessing.value) {
        currentVoucher.value = null;
        selectedDestination.value = '';
        forwardComment.value = '';
    }
};

const closePaymentModal = () => {
    showPaymentModal.value = false;
    currentVoucher.value = null;
    paymentReference.value = '';
    paymentComment.value = '';
};

const closeAssignModal = () => {
    showAssignModal.value = false;
    currentVoucher.value = null;
    selectedUser.value = null;
};

const closeWorkflowModal = () => {
    showWorkflowModal.value = false;
    currentVoucher.value = null;
    workflowHistory.value = [];
};

// Watch for filter changes
watch([selectedVoucherType, selectedStatus, selectedPaymentStatus, dateRange, searchQuery], () => {
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
            paid_count: props.stats.paid_count || 0,
            pending_mas_count: props.stats.pending_mas_count || 0,
            pending_ag_count: props.stats.pending_ag_count || 0,
            total_amount_paid: props.stats.total_amount_paid || 0,
            total_amount_pending: props.stats.total_amount_pending || 0,
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
        <Head title="Expenditure Control" />
        <Toast />

        <!-- Workflow Info Banner -->
        <div class="mb-4">
            <Message severity="info" :closable="false" class="workflow-banner">
                <div class="flex align-items-center gap-3 flex-wrap">
                    <i class="pi pi-chart-line text-xl"></i>
                    <div>
                        <strong>Expenditure Control (EC) - Step 4 of 6</strong>
                        <div class="text-sm mt-1">
                            Vouchers approved by Final Accounts are reviewed here.
                            <span class="font-semibold">Salary vouchers</span> go to Inspectorate → TCO.
                            <span class="font-semibold">Other vouchers</span> go to AG → MAS.
                            <span class="ml-2 text-xs bg-yellow-200 px-2 py-1 border-round">
                                <i class="pi pi-info-circle mr-1"></i>
                                Choose where to forward each voucher
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

        <!-- Main Vouchers Table -->
        <Card class="main-card">
            <template #title>
                <div class="flex align-items-center justify-content-between flex-wrap gap-3">
                    <div class="flex align-items-center gap-2">
                        <i class="pi pi-list text-primary"></i>
                        <span>Expenditure Control Queue</span>
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

                        <!-- Payment Status Filter -->
                        <div class="col-12 md:col-2">
                            <Dropdown
                                v-model="selectedPaymentStatus"
                                :options="paymentStatusOptions"
                                optionLabel="label"
                                optionValue="value"
                                placeholder="All Payment Status"
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
                    </div>
                    <div class="flex justify-content-end mt-2">
                        <span class="text-sm text-500">
                            <i class="pi pi-info-circle mr-1"></i>
                            {{ totalRecords }} record(s) found
                        </span>
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
                    <!-- Voucher Number -->
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
                    <Column field="voucher_date" header="Date" headerStyle="width: 8%" :sortable="true">
                        <template #body="slotProps">
                            <span class="text-900">{{ formatDate(slotProps.data.voucher_date) }}</span>
                        </template>
                    </Column>

                    <!-- MDA -->
                    <Column field="mda.name" header="MDA" headerStyle="width: 12%">
                        <template #body="slotProps">
                            <div class="flex flex-column">
                                <span class="font-medium">{{ slotProps.data.mda?.name || 'N/A' }}</span>
                                <span class="text-500 text-xs">{{ slotProps.data.mda?.code || '' }}</span>
                            </div>
                        </template>
                    </Column>

                    <!-- Payee -->
                    <Column field="payee_name" header="Payee" headerStyle="width: 10%">
                        <template #body="slotProps">
                            <span class="text-600">{{ slotProps.data.payee_name || 'N/A' }}</span>
                        </template>
                    </Column>

                    <!-- Amount -->
                    <Column field="total_amount" header="Amount" headerStyle="width: 10%" bodyClass="font-bold text-right" :sortable="true">
                        <template #body="slotProps">
                            <span class="text-900">{{ formatCurrency(slotProps.data.total_amount) }}</span>
                        </template>
                    </Column>

                    <!-- Bank -->
                    <Column field="bank_activity" header="Bank" headerStyle="width: 12%">
                        <template #body="slotProps">
                            <div v-if="slotProps.data.bank_activity">
                                <div class="font-medium">{{ slotProps.data.bank_activity.bank_name }}</div>
                                <div class="text-500 text-xs">{{ slotProps.data.bank_activity.account_number }}</div>
                            </div>
                            <span v-else class="text-500">Not Assigned</span>
                        </template>
                    </Column>

                    <!-- Payment Status -->
                    <Column field="payment_status" header="Payment Status" headerStyle="width: 8%">
                        <template #body="slotProps">
                            <Tag 
                                :value="getPaymentStatusLabel(slotProps.data.payment_status)" 
                                :severity="getPaymentStatusSeverity(slotProps.data.payment_status)"
                                size="small"
                            />
                        </template>
                    </Column>

                    <!-- Status -->
                    <Column field="status" header="Status" headerStyle="width: 8%">
                        <template #body="slotProps">
                            <Tag 
                                :value="getStatusDisplayName(slotProps.data.status)" 
                                :severity="getStatusSeverity(slotProps.data.status)"
                                size="small"
                            />
                        </template>
                    </Column>

                    <!-- Actions -->
                    <Column header="Actions" headerStyle="width: 14%" bodyClass="text-center">
                        <template #body="slotProps">
                            <div class="flex gap-1 justify-content-center flex-wrap">
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
                                    icon="pi pi-sitemap"
                                    severity="secondary"
                                    text
                                    rounded
                                    size="small"
                                    v-tooltip.top="'View Workflow'"
                                    @click="openWorkflowModal(slotProps.data)"
                                />
                                <Button
                                    icon="pi pi-user-plus"
                                    severity="primary"
                                    text
                                    rounded
                                    size="small"
                                    v-tooltip.top="'Assign to Staff'"
                                    @click="openAssignModal(slotProps.data)"
                                />
                                <Button
                                    v-if="slotProps.data.status === 'forwarded'"
                                    icon="pi pi-send"
                                    severity="success"
                                    text
                                    rounded
                                    size="small"
                                    v-tooltip.top="'Forward to Destination'"
                                    @click="openApproveModal(slotProps.data)"
                                />
                                <Button
                                    v-if="slotProps.data.status === 'forwarded'"
                                    icon="pi pi-times-circle"
                                    severity="danger"
                                    text
                                    rounded
                                    size="small"
                                    v-tooltip.top="'Reject & Return'"
                                    @click="openRejectModal(slotProps.data)"
                                />
                                <Button
                                    v-if="slotProps.data.payment_status === 'awaiting_mas' || slotProps.data.payment_status === 'awaiting_ag'"
                                    icon="pi pi-money-bill"
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
            </template>
        </Card>

        <!-- Forward Modal (Choose Destination) -->
        <Dialog
            v-model:visible="showForwardModal"
            :style="{ width: '550px' }"
            header="Forward Voucher to Destination"
            :modal="true"
            class="forward-dialog"
            :closable="!isProcessing"
        >
            <div class="flex flex-column gap-3">
                <div class="flex align-items-center gap-3 p-3 bg-blue-50 border-round">
                    <i class="pi pi-info-circle text-blue-500 text-xl"></i>
                    <div>
                        <div class="font-semibold">Voucher: {{ currentVoucher?.voucher_number }}</div>
                        <div class="text-sm">Amount: {{ formatCurrency(currentVoucher?.total_amount) }}</div>
                        <div class="text-sm">Type: {{ currentVoucher?.voucher_type?.toUpperCase() }}</div>
                    </div>
                </div>

                <div class="field">
                    <label class="font-semibold block mb-2">
                        Select Destination <span class="text-red-500">*</span>
                    </label>
                    <div class="flex flex-column gap-2">
                        <div v-for="option in destinationOptions" :key="option.value" class="flex align-items-center p-2 border-round hover:bg-gray-50 cursor-pointer" @click="selectedDestination = option.value">
                            <RadioButton
                                :value="option.value"
                                v-model="selectedDestination"
                                :id="'dest-' + option.value"
                            />
                            <label :for="'dest-' + option.value" class="ml-2 flex align-items-center gap-2 cursor-pointer">
                                <i :class="[option.icon, 'text-primary']"></i>
                                {{ option.label }}
                            </label>
                        </div>
                    </div>
                    <small class="text-500 mt-1 block">
                        <i class="pi pi-info-circle mr-1"></i>
                        Choose where you want this voucher to be forwarded
                    </small>
                </div>

                <div class="field">
                    <label class="font-semibold block mb-2">
                        Comment (Optional)
                    </label>
                    <Textarea
                        v-model="forwardComment"
                        rows="3"
                        placeholder="Add any additional notes about this forwarding..."
                        class="w-full"
                        autoResize
                    />
                </div>

                <div class="border-round bg-yellow-50 p-3">
                    <div class="flex align-items-center gap-2">
                        <i class="pi pi-exclamation-triangle text-yellow-600"></i>
                        <span class="text-sm">This action will forward the voucher to the selected destination for further processing.</span>
                    </div>
                </div>

                <div v-if="isProcessing" class="flex align-items-center justify-content-center gap-2 p-2">
                    <ProgressSpinner style="width: 30px; height: 30px" strokeWidth="4" />
                    <span>Processing...</span>
                </div>
            </div>

            <template #footer>
                <Button label="Cancel" icon="pi pi-times" @click="closeForwardModal" text :disabled="isProcessing" />
                <Button label="Continue" icon="pi pi-arrow-right" severity="primary" @click="openConfirmForwardModal" :disabled="!selectedDestination || isProcessing" />
            </template>
        </Dialog>

        <!-- Confirm Forward Modal -->
        <Dialog
            v-model:visible="showConfirmForwardModal"
            :style="{ width: '500px' }"
            header="Confirm Forward"
            :modal="true"
            class="confirm-dialog"
            :closable="!isProcessing"
        >
            <div class="flex flex-column gap-3">
                <div class="flex align-items-center gap-3 p-3 bg-orange-50 border-round">
                    <i class="pi pi-exclamation-triangle text-orange-500 text-2xl"></i>
                    <div>
                        <div class="font-semibold">Confirm Forwarding</div>
                        <div class="text-sm">Please confirm the details below before proceeding.</div>
                    </div>
                </div>

                <div class="border-round bg-gray-50 p-3">
                    <div class="flex flex-column gap-2">
                        <div class="flex justify-content-between">
                            <span class="text-500">Voucher:</span>
                            <span class="font-semibold">{{ currentVoucher?.voucher_number }}</span>
                        </div>
                        <div class="flex justify-content-between">
                            <span class="text-500">Amount:</span>
                            <span class="font-semibold text-primary">{{ formatCurrency(currentVoucher?.total_amount) }}</span>
                        </div>
                        <div class="flex justify-content-between">
                            <span class="text-500">Destination:</span>
                            <Tag :value="getDestinationLabel(selectedDestination)" severity="info" />
                        </div>
                        <div v-if="forwardComment" class="flex justify-content-between">
                            <span class="text-500">Comment:</span>
                            <span class="text-sm">{{ forwardComment }}</span>
                        </div>
                    </div>
                </div>

                <div class="border-round bg-green-50 p-3">
                    <div class="flex align-items-center gap-2">
                        <i class="pi pi-check-circle text-green-600"></i>
                        <span class="text-sm">This will forward the voucher to <strong>{{ getDestinationLabel(selectedDestination) }}</strong>.</span>
                    </div>
                </div>

                <div v-if="isProcessing" class="flex align-items-center justify-content-center gap-2 p-2">
                    <ProgressSpinner style="width: 30px; height: 30px" strokeWidth="4" />
                    <span>Processing...</span>
                </div>
            </div>

            <template #footer>
                <Button label="Cancel" icon="pi pi-times" @click="closeConfirmForwardModal" text :disabled="isProcessing" />
                <Button label="Confirm Forward" icon="pi pi-send" severity="success" @click="handleForward" :loading="isProcessing" />
            </template>
        </Dialog>

        <!-- Rejection Modal -->
        <Dialog
            v-model:visible="showRejectionModal"
            :style="{ width: '500px' }"
            header="Reject Voucher"
            :modal="true"
            :closable="false"
            class="rejection-dialog"
        >
            <div class="flex flex-column gap-3" v-if="currentVoucher">
                <div class="flex align-items-center gap-3 p-3 bg-red-50 border-round">
                    <i class="pi pi-exclamation-triangle text-red-500 text-xl"></i>
                    <div>
                        <div class="font-semibold">Voucher: {{ currentVoucher.voucher_number }}</div>
                        <div class="text-sm">This action will return the voucher to DFA for correction.</div>
                    </div>
                </div>

                <div class="field">
                    <label class="font-semibold block mb-2">
                        Reason for Rejection <span class="text-red-500">*</span>
                    </label>
                    <Textarea
                        v-model="rejectionReason"
                        rows="4"
                        placeholder="Provide detailed reason for rejection. This will be visible to the DFA officer."
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
                <Button label="Cancel" icon="pi pi-times" @click="closeRejectionModal" text :disabled="isProcessing" />
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

        <!-- Payment Modal -->
        <Dialog
            v-model:visible="showPaymentModal"
            :style="{ width: '500px' }"
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
                <Button label="Cancel" icon="pi pi-times" @click="closePaymentModal" text :disabled="isProcessing" />
                <Button label="Mark as Paid" icon="pi pi-check-circle" severity="success" @click="handleMarkAsPaid" :loading="isProcessing" />
            </template>
        </Dialog>

        <!-- Assign to Staff Modal with Search -->
        <Dialog
            v-model:visible="showAssignModal"
            :style="{ width: '500px' }"
            header="Assign Voucher to Staff"
            :modal="true"
            class="assign-dialog"
            :closable="!isProcessing"
        >
            <div class="flex flex-column gap-3">
                <div class="flex align-items-center gap-3 p-3 bg-primary-50 border-round">
                    <i class="pi pi-user-plus text-primary-500 text-xl"></i>
                    <div>
                        <div class="font-semibold">Voucher: {{ currentVoucher?.voucher_number }}</div>
                        <div class="text-sm">Amount: {{ formatCurrency(currentVoucher?.total_amount) }}</div>
                        <div class="text-sm">Type: {{ currentVoucher?.voucher_type?.toUpperCase() }}</div>
                    </div>
                </div>

                <div class="field">
                    <label class="font-semibold block mb-2">
                        Select Staff <span class="text-red-500">*</span>
                    </label>
                    <Dropdown
                        v-model="selectedUser"
                        :options="users"
                        optionLabel="name"
                        optionValue="id"
                        placeholder="Search and select a staff member..."
                        class="w-full"
                        :showClear="true"
                        :filter="true"
                        filterPlaceholder="Search by name or email..."
                        :virtualScroll="true"
                        :virtualScrollItemSize="50"
                    >
                        <template #option="slotProps">
                            <div class="flex flex-column">
                                <span class="font-medium">{{ slotProps.option.name }}</span>
                                <span class="text-500 text-xs">{{ slotProps.option.email }}</span>
                            </div>
                        </template>
                        <template #emptyfilter>
                            <div class="p-3 text-center text-500">
                                <i class="pi pi-search text-2xl block mb-2"></i>
                                <span>No staff members found matching your search.</span>
                            </div>
                        </template>
                        <template #empty>
                            <div class="p-3 text-center text-500">
                                <i class="pi pi-users text-2xl block mb-2"></i>
                                <span>No staff members available.</span>
                            </div>
                        </template>
                    </Dropdown>
                    <small class="text-500 mt-1 block">
                        <i class="pi pi-info-circle mr-1"></i>
                        Type to search by name or email
                    </small>
                </div>

                <div class="border-round bg-blue-50 p-3">
                    <div class="flex align-items-center gap-2">
                        <i class="pi pi-info-circle text-blue-600"></i>
                        <span class="text-sm">You can still approve this voucher as an admin even after assignment.</span>
                    </div>
                </div>

                <!-- Selected User Preview -->
                <div v-if="selectedUser" class="border-round bg-green-50 p-3">
                    <div class="flex align-items-center gap-2">
                        <i class="pi pi-check-circle text-green-600"></i>
                        <div>
                            <span class="text-sm font-semibold">Selected Staff:</span>
                            <span class="text-sm ml-2">{{ selectedUser.name }}</span>
                            <span class="text-500 text-xs ml-2">({{ selectedUser.email }})</span>
                        </div>
                    </div>
                </div>

                <div v-if="isProcessing" class="flex align-items-center justify-content-center gap-2 p-2">
                    <ProgressSpinner style="width: 30px; height: 30px" strokeWidth="4" />
                    <span>Processing...</span>
                </div>
            </div>

            <template #footer>
                <Button label="Cancel" icon="pi pi-times" @click="closeAssignModal" text :disabled="isProcessing" />
                <Button label="Assign" icon="pi pi-user-plus" severity="primary" @click="handleAssign" :loading="isProcessing" />
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

.financial-card :deep(.p-card) {
    background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
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

.forward-dialog :deep(.p-dialog-header),
.confirm-dialog :deep(.p-dialog-header),
.approval-dialog :deep(.p-dialog-header),
.rejection-dialog :deep(.p-dialog-header),
.payment-dialog :deep(.p-dialog-header),
.assign-dialog :deep(.p-dialog-header),
.workflow-dialog :deep(.p-dialog-header) {
    background: #f8fafc;
    border-bottom: 1px solid #e2e8f0;
}

.forward-dialog :deep(.p-dialog-content),
.confirm-dialog :deep(.p-dialog-content),
.approval-dialog :deep(.p-dialog-content),
.rejection-dialog :deep(.p-dialog-content),
.payment-dialog :deep(.p-dialog-content),
.assign-dialog :deep(.p-dialog-content) {
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