<!-- resources/js/pages/admin/expenditure-control/ag.vue -->
<!-- resources/js/pages/admin/expenditure-control/ag.vue -->
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
import Timeline from 'primevue/timeline';
import Divider from 'primevue/divider';
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
            pending_ec_count: 0,
            pending_ag_count: 0,
            approved_today: 0,
            rejected_today: 0,
            total_processed: 0,
            total_amount_pending: 0,
            total_amount_approved: 0,
            liability_count: 0,
        }),
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
const activeTab = ref('all');

// Modal states
const showRejectionModal = ref(false);
const showApprovalModal = ref(false);
const showWorkflowModal = ref(false);
const showDocumentViewer = ref(false);
const showSuccessModal = ref(false);
const showErrorModal = ref(false);

const currentVoucher = ref(null);
const rejectionReason = ref('');
const rejectionTouched = ref(false);
const workflowHistory = ref([]);
const documentUrl = ref('');
const currentDocument = ref(null);
const successTitle = ref('');
const successMessage = ref('');
const successDetails = ref(null);
const errorTitle = ref('');
const errorMessage = ref('');

// Stats
const stats = ref({
    pending_ec_count: 0,
    pending_ag_count: 0,
    approved_today: 0,
    rejected_today: 0,
    total_processed: 0,
    total_amount_pending: 0,
    total_amount_approved: 0,
    liability_count: 0,
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
    { label: 'Pending EC', value: 'forwarded' },
    { label: 'Pending AG Review', value: 'ec_approved' },
    { label: 'AG Approved', value: 'ag_approved' },
    { label: 'Sent Back', value: 'sent_back' },
    { label: 'Rejected', value: 'rejected' },
];

const paymentStatusOptions = [
    { label: 'All Payment Status', value: '' },
    { label: 'Pending AG', value: 'awaiting_ag' },
    { label: 'Pending MAS', value: 'awaiting_mas' },
    { label: 'Paid', value: 'paid' },
];

// Stats cards data
const statsData = computed(() => [
    {
        title: 'Pending EC Review',
        value: stats.value.pending_ec_count,
        icon: 'pi pi-clock',
        color: 'text-blue-500',
        bgColor: 'bg-blue-50',
    },
    {
        title: 'Pending AG Review',
        value: stats.value.pending_ag_count,
        icon: 'pi pi-hourglass',
        color: 'text-orange-500',
        bgColor: 'bg-orange-50',
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
]);

const financialStatsData = computed(() => [
    {
        title: 'Total Amount Pending',
        value: formatCurrency(stats.value.total_amount_pending),
        icon: 'pi pi-clock',
        color: 'text-orange-500',
        bgColor: 'bg-orange-50',
    },
    {
        title: 'Total Amount Approved',
        value: formatCurrency(stats.value.total_amount_approved),
        icon: 'pi pi-check-circle',
        color: 'text-green-500',
        bgColor: 'bg-green-50',
    },
    {
        title: 'Total Processed',
        value: stats.value.total_processed,
        icon: 'pi pi-chart-bar',
        color: 'text-purple-500',
        bgColor: 'bg-purple-50',
    },
    {
        title: 'Liability Vouchers',
        value: stats.value.liability_count,
        icon: 'pi pi-calendar',
        color: 'text-yellow-500',
        bgColor: 'bg-yellow-50',
    },
]);

// Compute total amount from filtered vouchers
const totalAmount = computed(() => {
    if (!vouchers.value || vouchers.value.length === 0) return 0;
    return vouchers.value.reduce((sum, voucher) => {
        return sum + (Number(voucher.total_amount) || 0);
    }, 0);
});

const formattedTotalAmount = computed(() => {
    return formatCurrency(totalAmount.value);
});

const filteredCount = computed(() => {
    return vouchers.value.length || 0;
});

const breadcrumbs = [
    { title: 'Expenditure Control', href: '/expenditure-control' },
    { title: 'AG Review', href: '#' },
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

// Get today's date for liability check
const isToday = (dateString) => {
    if (!dateString) return false;
    const today = new Date();
    const date = new Date(dateString);
    return date.getFullYear() === today.getFullYear() &&
           date.getMonth() === today.getMonth() &&
           date.getDate() === today.getDate();
};

// Check if voucher is a liability
const isLiabilityVoucher = (voucher) => {
    if (voucher.fa_approved_at) {
        return isToday(voucher.fa_approved_at);
    }
    return false;
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
        ec_approved: 'info',
        ag_approved: 'success',
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
        forwarded: 'Pending EC Review',
        ec_approved: 'Pending AG Review',
        ag_approved: 'AG Approved',
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

// =============================================
// WORKFLOW HELPER FUNCTIONS
// =============================================

// Get action severity for workflow
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
    };
    return actionMap[action] || 'info';
};

// Get action icon for workflow
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
    };
    return iconMap[action] || 'pi-circle';
};

// Get action color for workflow
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
    };
    return colorMap[action] || 'text-gray-500 bg-gray-100';
};

// Get action border color for workflow
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
    };
    return colorMap[action] || 'border-gray-300';
};

// Check if voucher is ready for AG approval (EC approved)
const isReadyForApproval = (voucher) => {
    return voucher.status?.toLowerCase() === 'ec_approved';
};

// Load vouchers
const loadVouchers = async () => {
    loading.value = true;
    try {
        const params = {
            per_page: lazyParams.value.rows,
            page: lazyParams.value.page,
            search: searchQuery.value || '',
            tab: activeTab.value,
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

        const response = await axios.get('/accountant-general/search', { params });
        
        if (response.data && response.data.success !== false) {
            const voucherData = response.data.vouchers;
            vouchers.value = voucherData.data || [];
            totalRecords.value = voucherData.total || 0;
            
            if (response.data.stats) {
                stats.value = {
                    pending_ec_count: response.data.stats.pending_ec_count || 0,
                    pending_ag_count: response.data.stats.pending_ag_count || 0,
                    approved_today: response.data.stats.approved_today || 0,
                    rejected_today: response.data.stats.rejected_today || 0,
                    total_processed: response.data.stats.total_processed || 0,
                    total_amount_pending: response.data.stats.total_amount_pending || 0,
                    total_amount_approved: response.data.stats.total_amount_approved || 0,
                    liability_count: response.data.stats.liability_count || 0,
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

// Switch tab
const switchTab = (tab) => {
    activeTab.value = tab;
    lazyParams.value.page = 1;
    loadVouchers();
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
            'EC Approved': formatDate(v.ec_approved_at),
            'MDA': v.mda?.name || 'N/A',
            'Payee': v.payee_name || 'N/A',
            'Amount': v.total_amount || 0,
            'Status': getStatusDisplayName(v.status) || 'N/A',
            'Payment Status': getPaymentStatusLabel(v.payment_status) || 'N/A',
            'Liability': isLiabilityVoucher(v) ? 'Yes' : 'No',
            'Narration': v.narration || 'N/A',
        }));

        const ws = XLSX.utils.json_to_sheet(exportData);
        const wb = XLSX.utils.book_new();
        XLSX.utils.book_append_sheet(wb, ws, 'AG_Vouchers');
        XLSX.writeFile(wb, `AG_Review_Vouchers_${new Date().toISOString().split('T')[0]}.xlsx`);
        
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
        doc.text('AG Review - Vouchers', pageWidth / 2, 15, { align: 'center' });
        
        doc.setFontSize(10);
        doc.setTextColor(108, 117, 125);
        doc.text(`Generated: ${new Date().toLocaleString()}`, pageWidth / 2, 22, { align: 'center' });
        doc.text(`Total Records: ${vouchers.value.length}`, pageWidth / 2, 28, { align: 'center' });

        const tableData = vouchers.value.map(v => [
            v.voucher_number || 'N/A',
            v.voucher_type || 'N/A',
            formatDate(v.voucher_date),
            formatDate(v.ec_approved_at),
            v.mda?.name || 'N/A',
            v.payee_name || 'N/A',
            `₦${Number(v.total_amount || 0).toLocaleString('en-NG', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`,
            getStatusDisplayName(v.status) || 'N/A',
            getPaymentStatusLabel(v.payment_status) || 'N/A',
            isLiabilityVoucher(v) ? 'Yes' : 'No',
        ]);

        autoTable(doc, {
            head: [['Voucher #', 'Type', 'Date', 'EC Approved', 'MDA', 'Payee', 'Amount', 'Status', 'Payment Status', 'Liability']],
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
                0: { cellWidth: 20 },
                1: { cellWidth: 16 },
                2: { cellWidth: 18 },
                3: { cellWidth: 18 },
                4: { cellWidth: 28 },
                5: { cellWidth: 22 },
                6: { cellWidth: 24 },
                7: { cellWidth: 20 },
                8: { cellWidth: 20 },
                9: { cellWidth: 14 },
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

        doc.save(`AG_Review_Vouchers_${new Date().toISOString().split('T')[0]}.pdf`);
        
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
    if (!isReadyForApproval(voucher)) {
        errorTitle.value = 'Cannot Approve Voucher';
        errorMessage.value = `This voucher has status "${getStatusDisplayName(voucher.status)}" and must be "Pending AG Review" before you can process it.`;
        showErrorModal.value = true;
        return;
    }
    currentVoucher.value = voucher;
    showApprovalModal.value = true;
};

const openRejectModal = (voucher) => {
    if (!isReadyForApproval(voucher)) {
        errorTitle.value = 'Cannot Reject Voucher';
        errorMessage.value = `This voucher has status "${getStatusDisplayName(voucher.status)}" and must be "Pending AG Review" before you can reject it.`;
        showErrorModal.value = true;
        return;
    }
    currentVoucher.value = voucher;
    rejectionReason.value = '';
    rejectionTouched.value = false;
    showRejectionModal.value = true;
};

// =============================================
// FIXED: Open workflow modal using voucher approvals directly
// =============================================
const openWorkflowModal = (voucher) => {
    console.log('=== OPEN WORKFLOW MODAL (AG) ===');
    console.log('Voucher:', voucher);
    console.log('Voucher approvals:', voucher.approvals);
    
    currentVoucher.value = voucher;
    showWorkflowModal.value = true;
    
    try {
        // Use approvals from the voucher directly
        const approvals = voucher.approvals || [];
        
        console.log('Approvals count:', approvals.length);
        
        // Sort by created_at descending (most recent first)
        workflowHistory.value = [...approvals].sort((a, b) => {
            const dateA = new Date(a.action_at || a.created_at);
            const dateB = new Date(b.action_at || b.created_at);
            return dateB - dateA;
        });
        
        console.log('Workflow History loaded:', workflowHistory.value.length, 'entries');
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

const closeDocumentViewer = () => {
    showDocumentViewer.value = false;
    documentUrl.value = '';
    currentDocument.value = null;
};

// Handle AG approval (forwards to MAS)
const handleApprove = () => {
    isProcessing.value = true;

    router.post(`/accountant-general/vouchers/${currentVoucher.value.id}/approve`, {}, {
        preserveScroll: true,
        onSuccess: (response) => {
            showApprovalModal.value = false;
            isProcessing.value = false;
            
            successTitle.value = 'Voucher Approved!';
            successMessage.value = response.message || `Voucher has been successfully forwarded to MAS.`;
            successDetails.value = {
                voucher_number: currentVoucher.value.voucher_number,
                amount: formatCurrency(currentVoucher.value.total_amount),
                forwarded_to: 'Management Account Section (MAS)',
                status: 'AG Approved',
                approved_at: new Date().toLocaleString()
            };
            showSuccessModal.value = true;
        },
        onError: (errors) => {
            isProcessing.value = false;
            console.error('Approval error:', errors);
            
            if (errors.response?.data?.message) {
                errorTitle.value = 'Approval Failed';
                errorMessage.value = errors.response.data.message;
                showErrorModal.value = true;
            } else if (errors.message) {
                errorTitle.value = 'Approval Failed';
                errorMessage.value = errors.message;
                showErrorModal.value = true;
            } else {
                toast.add({
                    severity: 'error',
                    summary: 'Error',
                    detail: 'Failed to process voucher approval.',
                    life: 5000,
                });
            }
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

    router.post(`/accountant-general/vouchers/${currentVoucher.value.id}/reject`, {
        reason: rejectionReason.value,
    }, {
        preserveScroll: true,
        onSuccess: () => {
            successTitle.value = 'Voucher Rejected';
            successMessage.value = `Voucher ${currentVoucher.value.voucher_number} has been rejected and returned to EC.`;
            successDetails.value = {
                voucher_number: currentVoucher.value.voucher_number,
                amount: formatCurrency(currentVoucher.value.total_amount),
                reason: rejectionReason.value,
                status: 'Sent Back',
                rejected_at: new Date().toLocaleString()
            };
            showRejectionModal.value = false;
            isProcessing.value = false;
            showSuccessModal.value = true;
        },
        onError: (errors) => {
            isProcessing.value = false;
            console.error('Rejection error:', errors);
            
            if (errors.response?.data?.message) {
                errorTitle.value = 'Rejection Failed';
                errorMessage.value = errors.response.data.message;
                showErrorModal.value = true;
            } else if (errors.message) {
                errorTitle.value = 'Rejection Failed';
                errorMessage.value = errors.message;
                showErrorModal.value = true;
            } else {
                toast.add({
                    severity: 'error',
                    summary: 'Error',
                    detail: 'Failed to reject voucher.',
                    life: 5000,
                });
            }
        },
    });
};

// Close success modal and redirect
const closeSuccessModal = () => {
    showSuccessModal.value = false;
    setTimeout(() => {
        router.visit('/accountant-general/search');
    }, 300);
};

// View voucher details
const viewVoucherDetails = (voucher) => {
    router.visit(`/accountant-general/vouchers/${voucher.id}`);
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

const closeApprovalModal = () => {
    showApprovalModal.value = false;
    currentVoucher.value = null;
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
            pending_ec_count: props.stats.pending_ec_count || 0,
            pending_ag_count: props.stats.pending_ag_count || 0,
            approved_today: props.stats.approved_today || 0,
            rejected_today: props.stats.rejected_today || 0,
            total_processed: props.stats.total_processed || 0,
            total_amount_pending: props.stats.total_amount_pending || 0,
            total_amount_approved: props.stats.total_amount_approved || 0,
            liability_count: props.stats.liability_count || 0,
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
        <Head title="AG Review" />
        <Toast />

        <!-- Workflow Info Banner -->
        <div class="mb-4">
            <Message severity="info" :closable="false" class="workflow-banner">
                <div class="flex align-items-center gap-3 flex-wrap">
                    <i class="pi pi-chart-line text-xl"></i>
                    <div>
                        <strong>Accountant General (AG) - Step 5 of 6</strong>
                        <div class="text-sm mt-1">
                            Review vouchers approved by Expenditure Control (EC).
                            Once approved, vouchers go to <strong>Management Account Section (MAS)</strong> for payment processing.
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

        <!-- PROMINENT LIABILITY AS AT BANNER -->
        <div v-if="activeTab === 'liability'" class="mb-4">
            <Message severity="warn" :closable="false" class="liability-banner">
                <template #messageicon>
                    <i class="pi pi-calendar text-2xl"></i>
                </template>
                <div class="flex align-items-center justify-content-between w-full flex-wrap">
                    <div class="flex align-items-center gap-3">
                        <div class="bg-orange-500 text-white font-bold px-4 py-2 border-round text-lg">
                            LIABILITY AS AT {{ new Date().toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' }).toUpperCase() }}
                        </div>
                        <div class="flex align-items-center gap-2">
                            <Badge :value="`${stats.liability_count || 0} vouchers`" severity="warn" size="large" />
                            <span class="text-sm text-600">
                                <i class="pi pi-info-circle mr-1"></i>
                                Vouchers approved by Final Accounts today
                            </span>
                        </div>
                    </div>
                    <div class="text-sm font-semibold text-orange-700">
                        Total Liability: {{ formatCurrency(stats.total_amount_pending || 0) }}
                    </div>
                </div>
            </Message>
        </div>

        <!-- Tab Navigation -->
        <div class="mb-4">
            <div class="flex align-items-center gap-3 flex-wrap">
                <Button
                    :label="`Vouchers with EC (${stats.pending_ec_count || 0})`"
                    :severity="activeTab === 'all' ? 'primary' : 'secondary'"
                    :outlined="activeTab !== 'all'"
                    @click="switchTab('all')"
                    size="small"
                />
                <Button
                    :label="`Pending AG Review (${stats.pending_ag_count || 0})`"
                    :severity="activeTab === 'pending' ? 'primary' : 'secondary'"
                    :outlined="activeTab !== 'pending'"
                    @click="switchTab('pending')"
                    size="small"
                >
                    <template #icon>
                        <i class="pi pi-clock"></i>
                    </template>
                </Button>
                <Button
                    :label="`Liability Vouchers (${stats.liability_count || 0})`"
                    :severity="activeTab === 'liability' ? 'primary' : 'secondary'"
                    :outlined="activeTab !== 'liability'"
                    @click="switchTab('liability')"
                    size="small"
                >
                    <template #icon>
                        <i class="pi pi-calendar"></i>
                    </template>
                </Button>
                <Button
                    :label="`Approved (${stats.approved_today || 0})`"
                    :severity="activeTab === 'approved' ? 'primary' : 'secondary'"
                    :outlined="activeTab !== 'approved'"
                    @click="switchTab('approved')"
                    size="small"
                >
                    <template #icon>
                        <i class="pi pi-check-circle"></i>
                    </template>
                </Button>
            </div>
        </div>

        <!-- Main Vouchers Table -->
        <Card class="main-card">
            <template #title>
                <div class="flex align-items-center justify-content-between flex-wrap gap-3">
                    <div class="flex align-items-center gap-2">
                        <i class="pi pi-list text-primary"></i>
                        <span>{{ activeTab === 'liability' ? 'Liability Vouchers' : activeTab === 'pending' ? 'Pending AG Review' : activeTab === 'approved' ? 'Approved Vouchers' : 'Vouchers with EC' }}</span>
                        <Badge :value="totalRecords" severity="info" />
                        <Tag v-if="activeTab === 'liability'" value="Today's Liabilities" severity="warning" size="small" />
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
                    <div class="flex justify-content-between mt-2">
                        <span class="text-sm text-500">
                            <i class="pi pi-info-circle mr-1"></i>
                            {{ totalRecords }} record(s) found
                        </span>
                        <span v-if="activeTab === 'liability'" class="text-sm text-orange-500 font-medium">
                            <i class="pi pi-calendar mr-1"></i>
                            Showing only vouchers approved by Final Accounts today ({{ new Date().toLocaleDateString() }})
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
                    :emptyMessage="activeTab === 'pending' ? 'No vouchers pending AG review.' : activeTab === 'liability' ? 'No liability vouchers found.' : activeTab === 'approved' ? 'No approved vouchers found.' : 'No vouchers found.'"
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
                    showGridlines
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

                    <!-- EC Approved Date -->
                    <Column field="ec_approved_at" header="EC Approved" headerStyle="width: 10%" :sortable="true">
                        <template #body="slotProps">
                            <div class="flex flex-column">
                                <span class="text-900">{{ formatDate(slotProps.data.ec_approved_at) }}</span>
                                <span v-if="isLiabilityVoucher(slotProps.data)" class="text-xs text-orange-500 font-medium">
                                    <i class="pi pi-calendar mr-1"></i> Today's Liability
                                </span>
                            </div>
                        </template>
                    </Column>

                    <!-- MDA -->
                    <Column field="mda.name" header="MDA" headerStyle="width: 10%">
                        <template #body="slotProps">
                            <div class="flex flex-column">
                                <span class="font-medium">{{ slotProps.data.mda?.name || 'N/A' }}</span>
                                <span class="text-500 text-xs">{{ slotProps.data.mda?.code || '' }}</span>
                            </div>
                        </template>
                    </Column>

                    <!-- Payee -->
                    <Column field="payee_name" header="Payee" headerStyle="width: 8%">
                        <template #body="slotProps">
                            <span class="text-600">{{ slotProps.data.payee_name || 'N/A' }}</span>
                        </template>
                    </Column>

                    <!-- Amount with Footer -->
                    <Column field="total_amount" header="Amount" headerStyle="width: 10%" bodyClass="font-bold text-right" :sortable="true" footerStyle="font-bold text-right text-primary">
                        <template #body="slotProps">
                            <span class="text-900">{{ formatCurrency(slotProps.data.total_amount) }}</span>
                        </template>
                        <template #footer>
                            <div class="flex flex-column align-items-end">
                                <span class="text-primary font-bold text-lg">{{ formattedTotalAmount }}</span>
                                <span class="text-500 text-xs">({{ filteredCount }} vouchers)</span>
                            </div>
                        </template>
                    </Column>

                    <!-- Bank -->
                    <Column field="bank_activity" header="Bank" headerStyle="width: 10%">
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

                    <!-- Liability Badge -->
                    <Column header="Liability" headerStyle="width: 6%">
                        <template #body="slotProps">
                            <Tag 
                                v-if="isLiabilityVoucher(slotProps.data)"
                                value="Today" 
                                severity="warning" 
                                size="small"
                                icon="pi pi-calendar"
                            />
                            <span v-else class="text-500 text-sm">-</span>
                        </template>
                    </Column>

                    <!-- Actions - 3 up, 3 down layout (NO ASSIGN, NO DESTINATION SELECTION) -->
                    <Column header="Actions" headerStyle="width: 12%" bodyClass="text-center">
                        <template #body="slotProps">
                            <div class="flex flex-column gap-1 align-items-center">
                                <!-- Row 1: 3 buttons -->
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
                                        icon="pi pi-sitemap"
                                        severity="secondary"
                                        text
                                        rounded
                                        size="small"
                                        v-tooltip.top="'View Workflow'"
                                        
                                        @click="openWorkflowModal(slotProps.data)"
                                    />
                                </div>
                                <!-- Row 2: 3 buttons -->
                                <div class="flex gap-1 justify-content-center">
                                    <Button
                                        v-if="slotProps.data.status === 'ec_approved'"
                                        icon="pi pi-check-circle"
                                        severity="success"
                                        text
                                        rounded
                                        size="small"
                                        v-tooltip.top="'Approve & Forward to MAS'"
                                        @click="openApproveModal(slotProps.data)"
                                    />
                                    <Button
                                        v-else
                                        icon="pi pi-check-circle"
                                        severity="success"
                                        text
                                        rounded
                                        size="small"
                                        disabled
                                        v-tooltip.top="'Not ready for approval'"
                                    />
                                    <Button
                                        v-if="slotProps.data.status === 'ec_approved'"
                                        icon="pi pi-times-circle"
                                        severity="danger"
                                        text
                                        rounded
                                        size="small"
                                        v-tooltip.top="'Reject & Return to EC'"
                                        @click="openRejectModal(slotProps.data)"
                                    />
                                    <Button
                                        v-else
                                        icon="pi pi-times-circle"
                                        severity="danger"
                                        text
                                        rounded
                                        size="small"
                                        disabled
                                        v-tooltip.top="'Cannot reject'"
                                    />
                                    <!-- Placeholder for alignment -->
                                    <div style="width: 2rem;"></div>
                                </div>
                            </div>
                        </template>
                    </Column>
                </DataTable>
            </template>
        </Card>

        <!-- Success Modal -->
        <Dialog
            v-model:visible="showSuccessModal"
            :style="{ width: '500px' }"
            header="Success"
            :modal="true"
            class="success-dialog"
            @update:visible="closeSuccessModal"
        >
            <div class="flex flex-column align-items-center text-center">
                <div class="success-icon mb-3">
                    <i class="pi pi-check-circle text-green-500" style="font-size: 4rem;"></i>
                </div>
                <h3 class="text-900 mb-2">{{ successTitle }}</h3>
                <p class="text-600 mb-3">{{ successMessage }}</p>
                
                <Divider />
                
                <div class="bg-green-50 p-3 border-round w-full text-left">
                    <div class="flex align-items-center gap-2 mb-2">
                        <i class="pi pi-info-circle text-green-600"></i>
                        <span class="font-semibold">Transaction Details:</span>
                    </div>
                    <div class="text-sm">
                        <div class="flex justify-content-between mb-1">
                            <span class="text-500">Voucher Number:</span>
                            <span class="font-semibold">{{ successDetails?.voucher_number }}</span>
                        </div>
                        <div class="flex justify-content-between mb-1">
                            <span class="text-500">Amount:</span>
                            <span class="font-semibold text-primary">{{ successDetails?.amount }}</span>
                        </div>
                        <div v-if="successDetails?.forwarded_to" class="flex justify-content-between mb-1">
                            <span class="text-500">Forwarded To:</span>
                            <Tag :value="successDetails.forwarded_to" severity="info" size="small" />
                        </div>
                        <div v-if="successDetails?.reason" class="flex justify-content-between mb-1">
                            <span class="text-500">Reason:</span>
                            <span class="text-600">{{ successDetails.reason }}</span>
                        </div>
                        <div class="flex justify-content-between">
                            <span class="text-500">Status:</span>
                            <Tag :value="successDetails?.status || 'Processed'" severity="success" size="small" />
                        </div>
                    </div>
                </div>
            </div>

            <template #footer>
                <Button 
                    label="Close" 
                    icon="pi pi-times" 
                    @click="closeSuccessModal" 
                    severity="secondary"
                />
                <Button 
                    label="Go to Queue" 
                    icon="pi pi-arrow-right" 
                    @click="closeSuccessModal" 
                    severity="primary"
                    autofocus
                />
            </template>
        </Dialog>

        <!-- Approval Modal - NO DESTINATION SELECTION -->
        <Dialog
            v-model:visible="showApprovalModal"
            :style="{ width: '500px' }"
            header="AG Approval"
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
                        <div class="text-sm">Type: {{ currentVoucher?.voucher_type?.toUpperCase() }}</div>
                        <div class="text-sm">EC Approved: {{ formatDate(currentVoucher?.ec_approved_at) }}</div>
                    </div>
                </div>

                <!-- Bank Information Display -->
                <div v-if="currentVoucher?.bank_activity" class="border-round bg-blue-50 p-3">
                    <div class="flex align-items-center gap-2 mb-2">
                        <i class="pi pi-building text-blue-600"></i>
                        <span class="font-semibold">Bank Information:</span>
                    </div>
                    <div class="text-sm">
                        <div><strong>Bank:</strong> {{ currentVoucher.bank_activity.bank_name }}</div>
                        <div><strong>Account:</strong> {{ currentVoucher.bank_activity.account_number }}</div>
                        <div><strong>Tag:</strong> {{ currentVoucher.bank_activity.tag }}</div>
                    </div>
                </div>

                <div class="border-round bg-yellow-50 p-3">
                    <div class="flex align-items-center gap-2 mb-2">
                        <i class="pi pi-arrow-right text-yellow-600"></i>
                        <span class="font-semibold">Next Stage:</span>
                        <Tag value="Management Account Section (MAS)" severity="info" />
                    </div>
                    <div class="text-sm text-600">
                        This voucher will be forwarded to MAS for payment processing.
                        <strong>Action cannot be undone.</strong>
                    </div>
                </div>

                <div class="border-round bg-green-50 p-3">
                    <div class="flex align-items-center gap-2">
                        <i class="pi pi-check-circle text-green-600"></i>
                        <span class="font-semibold">Confirmation:</span>
                        <span class="text-sm">I confirm that this voucher is ready for payment processing.</span>
                    </div>
                </div>

                <div v-if="isProcessing" class="flex align-items-center justify-content-center gap-2 p-2">
                    <ProgressSpinner style="width: 30px; height: 30px" strokeWidth="4" />
                    <span>Processing...</span>
                </div>
            </div>

            <template #footer>
                <Button label="Cancel" icon="pi pi-times" @click="closeApprovalModal" text :disabled="isProcessing" />
                <Button label="Approve & Forward to MAS" icon="pi pi-send" severity="success" @click="handleApprove" :loading="isProcessing" />
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
                        <div class="text-sm">This action will return the voucher to Expenditure Control (EC).</div>
                    </div>
                </div>

                <div class="field">
                    <label class="font-semibold block mb-2">
                        Reason for Rejection <span class="text-red-500">*</span>
                    </label>
                    <Textarea
                        v-model="rejectionReason"
                        rows="4"
                        placeholder="Provide detailed reason for rejection. This will be visible to the EC officer."
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
                        <span class="text-sm">The EC officer will be notified and can review the voucher again.</span>
                    </div>
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

        <!-- Error Modal -->
        <Dialog
            v-model:visible="showErrorModal"
            :style="{ width: '450px' }"
            header="Cannot Process Voucher"
            :modal="true"
            class="error-dialog"
        >
            <div class="flex flex-column align-items-center text-center">
                <div class="error-icon mb-3">
                    <i class="pi pi-exclamation-triangle text-red-500" style="font-size: 4rem;"></i>
                </div>
                <h3 class="text-900 mb-2">{{ errorTitle }}</h3>
                <p class="text-600 mb-3">{{ errorMessage }}</p>
            </div>

            <template #footer>
                <Button 
                    label="Close" 
                    icon="pi pi-times" 
                    @click="showErrorModal = false" 
                    severity="secondary"
                />
            </template>
        </Dialog>

        <!-- Workflow Timeline Modal -->
        <!-- Workflow Timeline Modal - UPDATED -->
<Dialog
    v-model:visible="showWorkflowModal"
    :style="{ width: '650px', maxHeight: '80vh' }"
    :header="`Workflow History - ${currentVoucher?.voucher_number}`"
    :modal="true"
    class="workflow-dialog"
    @update:visible="closeWorkflowModal"
>
    <div class="workflow-timeline" style="max-height: 60vh; overflow-y: auto;">
        <!-- No History State -->
        <div v-if="workflowHistory.length === 0" class="text-center p-4">
            <i class="pi pi-clock text-400 text-3xl mb-2"></i>
            <p class="text-600">No workflow history available</p>
            <p class="text-500 text-sm">Approval history will appear here once the voucher is processed.</p>
        </div>

        <!-- Timeline -->
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
/* Liability Banner - PROMINENT */
.liability-banner :deep(.p-message) {
    background: linear-gradient(135deg, #fff7ed 0%, #ffedd5 100%);
    border: 3px solid #f97316;
    border-radius: 0.75rem;
    padding: 1rem 1.5rem;
}

.liability-banner :deep(.p-message-icon) {
    color: #f97316;
}

.liability-banner :deep(.p-message-text) {
    width: 100%;
}

/* Stat Cards */
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

.workflow-banner :deep(.p-message) {
    background: linear-gradient(135deg, #e0f2fe 0%, #bae6fd 100%);
    border: none;
    border-radius: 0.75rem;
}

/* Table footer styling */
:deep(.p-datatable-tfoot) {
    background: #f8fafc;
    font-weight: 600;
}

:deep(.p-datatable-tfoot > tr > td) {
    border-top: 2px solid #e2e8f0;
    padding: 0.75rem 1rem;
}

/* Workflow Timeline */
/* Workflow Timeline - Add to styles */
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

.approval-dialog :deep(.p-dialog-header),
.rejection-dialog :deep(.p-dialog-header),
.error-dialog :deep(.p-dialog-header),
.success-dialog :deep(.p-dialog-header) {
    background: #f8fafc;
    border-bottom: 1px solid #e2e8f0;
}

.success-icon {
    animation: bounce 0.5s ease-in-out;
}

.error-icon {
    animation: shake 0.5s ease-in-out;
}

@keyframes bounce {
    0%, 100% { transform: scale(1); }
    50% { transform: scale(1.2); }
}

@keyframes shake {
    0%, 100% { transform: translateX(0); }
    25% { transform: translateX(-5px); }
    75% { transform: translateX(5px); }
}

:deep(.p-datatable) {
    border: 1px solid #e2e8f0;
    border-radius: 0.75rem;
    overflow: hidden;
}

:deep(.p-datatable-thead > tr > th) {
    background: #f8fafc;
    color: #1e293b;
    font-weight: 600;
    padding: 0.75rem 1rem;
}

@media (max-width: 768px) {
    :deep(.p-datatable) {
        font-size: 0.875rem;
    }
}
</style>