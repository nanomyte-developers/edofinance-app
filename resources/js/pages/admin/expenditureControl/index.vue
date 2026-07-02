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
import RadioButton from 'primevue/radiobutton';
import Timeline from 'primevue/timeline';
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
            liability_count: 0,
        }),
    },
    users: {
        type: Array,
        default: () => [],
    },
    mdas: {
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
const selectedMda = ref(null);
const dateRange = ref(null);
const activeTab = ref('all');

// Modal states
const showRejectionModal = ref(false);
const showApprovalModal = ref(false);
const showForwardModal = ref(false);
const showPaymentModal = ref(false);
const showAssignModal = ref(false);
const showWorkflowModal = ref(false);
const showDocumentViewer = ref(false);
const showConfirmForwardModal = ref(false);
const showLiabilityPrintDialog = ref(false);

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
const liabilityPrintContentRef = ref(null);

const currentDateTime = ref(
    new Date().toLocaleString('en-GB', {
        day: '2-digit',
        month: 'short',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
        hour12: true,
    })
);

// // Selected MDA name for print header
// const selectedMdaName = computed(() => {
//     if (selectedMda.value) {
//         const mda = props.mdas?.find(m => m.id === selectedMda.value);
//         return mda ? mda.name : '';
//     }
//     return 'ALL MINISTRIES';
// });

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

// Liability Print Data
const liabilityPrintData = computed(() => {
    if (activeTab.value === 'liability') {
        return vouchers.value.filter(v => {
            if (v.final_approved_at) {
                const today = new Date();
                const date = new Date(v.final_approved_at);
                return date.getFullYear() === today.getFullYear() &&
                       date.getMonth() === today.getMonth() &&
                       date.getDate() === today.getDate();
            }
            return false;
        });
    }
    return vouchers.value || [];
});

const totalLiabilityAmount = computed(() => {
    return liabilityPrintData.value.reduce((sum, v) => sum + (Number(v.total_amount) || 0), 0);
});

const selectedMdaName = computed(() => {
    if (selectedMda.value) {
        const mda = props.mdas?.find(m => m.id === selectedMda.value);
        return mda ? mda.name : '';
    }
    return 'ALL MINISTRIES';
});

const liabilityEmptyRows = computed(() => {
    const minRows = 15;
    const dataRows = liabilityPrintData.value.length + 1;
    return Math.max(0, minRows - dataRows);
});

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

const formatCurrencyAmount = (value) => {
    if (value === null || value === undefined || value === '') return '';
    const num = parseFloat(value) || 0;
    const parts = num.toFixed(2).split('.');
    return new Intl.NumberFormat('en-NG').format(parts[0]) + '.' + parts[1];
};

const formatCurrencyForPrint = (value) => {
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

// Check if voucher is a liability (approved by FA today)
const isLiabilityVoucher = (voucher) => {
    if (voucher.final_approved_at) {
        return isToday(voucher.final_approved_at);
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
        if (selectedMda.value) { // <-- ADD THIS
            params.mda_id = selectedMda.value;
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
    selectedMda.value = null;
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
            'FA Approved': formatDate(v.fa_approved_at),
            'MDA': v.mda?.name || 'N/A',
            'Payee': v.payee_name || 'N/A',
            'Amount': v.total_amount || 0,
            'Status': v.status || 'N/A',
            'Payment Status': getPaymentStatusLabel(v.payment_status) || 'N/A',
            'Liability': isLiabilityVoucher(v) ? 'Yes' : 'No',
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
            formatDate(v.fa_approved_at),
            v.mda?.name || 'N/A',
            v.payee_name || 'N/A',
            `₦${Number(v.total_amount || 0).toLocaleString('en-NG', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`,
            v.status || 'N/A',
            getPaymentStatusLabel(v.payment_status) || 'N/A',
            isLiabilityVoucher(v) ? 'Yes' : 'No',
        ]);

        autoTable(doc, {
            head: [['Voucher #', 'Type', 'Date', 'FA Approved', 'MDA', 'Payee', 'Amount', 'Status', 'Payment Status', 'Liability']],
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
    workflowHistory.value = [];
    
    try {
        let approvals = voucher.approvals || [];
        
        if (approvals.length === 0) {
            try {
                const response = await axios.get(`/vouchers/${voucher.id}/approvals`);
                approvals = response.data || [];
            } catch (apiError) {
                console.warn('Could not fetch approvals via API:', apiError);
            }
        }
        
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

// Liability Print Functions
const openLiabilityPrintDialog = () => {
    if (stats.liability_count === 0) {
        toast.add({
            severity: 'warn',
            summary: 'No Liabilities',
            detail: 'There are no liability vouchers to print for today.',
            life: 3000,
        });
        return;
    }
    showLiabilityPrintDialog.value = true;
};

const closeLiabilityPrintDialog = () => {
    showLiabilityPrintDialog.value = false;
};

const printLiabilityContent = () => {
    try {
        const printElement = document.getElementById('liability-print-content');
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
        const title = `Liability_Printout_${new Date().toISOString().split('T')[0]}`;

        printWindow.document.write(`
            <!DOCTYPE html>
            <html>
            <head>
                <title>${title}</title>
                <meta charset="UTF-8">
                <style>
                    * { margin: 0; padding: 0; box-sizing: border-box; }
                    body {
                        font-family: Arial, sans-serif;
                        font-size: 10px;
                        padding: 20px;
                        background: white;
                        -webkit-print-color-adjust: exact !important;
                        print-color-adjust: exact !important;
                    }
                    
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
                    
                    .print-ledger-table .total-row {
                        background-color: #fff3e0 !important;
                        -webkit-print-color-adjust: exact !important;
                        print-color-adjust: exact !important;
                    }
                    .print-ledger-table .empty-row { height: 28px; }
                    .print-ledger-table .data-row:hover { background-color: #f5f5f5 !important; }
                    
                    .print-ledger-table .type-badge {
                        display: inline-block;
                        padding: 2px 8px;
                        border-radius: 4px;
                        font-weight: bold;
                        font-size: 7px;
                        background-color: #2196f3;
                        color: white;
                    }
                    
                    .print-ledger-table .status-badge {
                        display: inline-block;
                        padding: 2px 8px;
                        border-radius: 4px;
                        font-weight: bold;
                        font-size: 7px;
                        background-color: #ff9800;
                        color: white;
                    }
                    
                    .print-ledger-table .sno-col { width: 5%; }
                    .print-ledger-table .date-col { width: 8%; }
                    .print-ledger-table .voucher-col { width: 10%; }
                    .print-ledger-table .type-col { width: 8%; }
                    .print-ledger-table .mda-col { width: 14%; }
                    .print-ledger-table .payee-col { width: 12%; }
                    .print-ledger-table .purpose-col { width: 18%; }
                    .print-ledger-table .amount-col { width: 10%; }
                    .print-ledger-table .fa-approved-col { width: 8%; }
                    .print-ledger-table .status-col { width: 7%; }
                    
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
            detail: 'Please select a staff member to assign this voucher.',
            life: 3000,
        });
        return;
    }

    const userId = selectedUser.value.id || selectedUser.value;
    
    if (!userId) {
        toast.add({
            severity: 'warn',
            summary: 'Error',
            detail: 'Invalid user selection. Please try again.',
            life: 3000,
        });
        return;
    }

    isProcessing.value = true;

    router.post(`/expenditure-control/vouchers/${currentVoucher.value.id}/assign`, {
        user_id: userId,
    }, {
        preserveScroll: true,
        onSuccess: () => {
            showAssignModal.value = false;
            isProcessing.value = false;
            toast.add({
                severity: 'success',
                summary: 'Assigned',
                detail: `Voucher ${currentVoucher.value.voucher_number} assigned to ${selectedUser.value.name || 'staff member'}.`,
                life: 5000,
            });
            currentVoucher.value = null;
            selectedUser.value = null;
            loadVouchers();
        },
        onError: (errors) => {
            isProcessing.value = false;
            console.error('Assignment error:', errors);
            toast.add({
                severity: 'error',
                summary: 'Error',
                detail: errors.response?.data?.message || errors.message || 'Failed to assign voucher.',
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

// Check if there are any capital vouchers in the liability data
const hasCapitalVouchers = computed(() => {
    return liabilityPrintData.value.some(v => 
        v.voucher_type?.toLowerCase() === 'capital'
    );
});

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

        <!-- Tab Navigation with Print Button -->
        <div class="mb-4">
            <div class="flex align-items-center justify-content-between flex-wrap gap-3">
                <div class="flex align-items-center gap-3 flex-wrap">
                    <Button
                        :label="`All Vouchers (${totalRecords || 0})`"
                        :severity="activeTab === 'all' ? 'primary' : 'secondary'"
                        :outlined="activeTab !== 'all'"
                        @click="switchTab('all')"
                        size="small"
                    />
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
                    <span class="text-sm text-500 ml-2">
                        <i class="pi pi-info-circle mr-1"></i>
                        Liability vouchers are vouchers approved by Final Accounts today
                    </span>
                </div>
                <!-- Print Liability Button -->
                <Button
                    v-if="activeTab === 'liability' && stats.liability_count > 0"
                    label="Print Liability"
                    icon="pi pi-print"
                    severity="warning"
                    size="small"
                    @click="openLiabilityPrintDialog"
                    class="liability-print-btn"
                />
            </div>
        </div>

        <!-- Main Vouchers Table -->
        <Card class="main-card">
            <template #title>
                <div class="flex align-items-center justify-content-between flex-wrap gap-3">
                    <div class="flex align-items-center gap-2">
                        <i class="pi pi-list text-primary"></i>
                        <span>{{ activeTab === 'liability' ? 'Liability Vouchers' : 'All Vouchers' }}</span>
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
                    :emptyMessage="activeTab === 'liability' ? 'No liability vouchers for today.' : 'No vouchers found.'"
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

                    <Column field="voucher_type" header="Type" headerStyle="width: 8%" :sortable="true">
                        <template #body="slotProps">
                            <Tag 
                                :value="slotProps.data.voucher_type?.toUpperCase() || 'STANDARD'" 
                                :severity="getVoucherTypeSeverity(slotProps.data.voucher_type)"
                            />
                        </template>
                    </Column>

                    <Column field="voucher_date" header="Date" headerStyle="width: 8%" :sortable="true">
                        <template #body="slotProps">
                            <span class="text-900">{{ formatDate(slotProps.data.voucher_date) }}</span>
                        </template>
                    </Column>

                    <Column field="fa_approved_at" header="FA Approved" headerStyle="width: 10%" :sortable="true">
                        <template #body="slotProps">
                            <div class="flex flex-column">
                                <span class="text-900">{{ formatDate(slotProps.data.fa_approved_at) }}</span>
                                <span v-if="isLiabilityVoucher(slotProps.data)" class="text-xs text-orange-500 font-medium">
                                    <i class="pi pi-calendar mr-1"></i> Today's Liability
                                </span>
                            </div>
                        </template>
                    </Column>

                    <Column field="mda.name" header="MDA" headerStyle="width: 10%">
                        <template #body="slotProps">
                            <div class="flex flex-column">
                                <span class="font-medium">{{ slotProps.data.mda?.name || 'N/A' }}</span>
                                <span class="text-500 text-xs">{{ slotProps.data.mda?.code || '' }}</span>
                            </div>
                        </template>
                    </Column>

                    <Column field="payee_name" header="Payee" headerStyle="width: 8%">
                        <template #body="slotProps">
                            <span class="text-600">{{ slotProps.data.payee_name || 'N/A' }}</span>
                        </template>
                    </Column>

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

                    <Column field="bank_activity" header="Bank" headerStyle="width: 10%">
                        <template #body="slotProps">
                            <div v-if="slotProps.data.bank_activity">
                                <div class="font-medium">{{ slotProps.data.bank_activity.bank_name }}</div>
                                <div class="text-500 text-xs">{{ slotProps.data.bank_activity.account_number }}</div>
                            </div>
                            <span v-else class="text-500">Not Assigned</span>
                        </template>
                    </Column>

                    <Column field="payment_status" header="Payment Status" headerStyle="width: 8%">
                        <template #body="slotProps">
                            <Tag 
                                :value="getPaymentStatusLabel(slotProps.data.payment_status)" 
                                :severity="getPaymentStatusSeverity(slotProps.data.payment_status)"
                                size="small"
                            />
                        </template>
                    </Column>

                    <Column field="status" header="Status" headerStyle="width: 8%">
                        <template #body="slotProps">
                            <Tag 
                                :value="getStatusDisplayName(slotProps.data.status)" 
                                :severity="getStatusSeverity(slotProps.data.status)"
                                size="small"
                            />
                        </template>
                    </Column>

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

                    <Column header="Actions" headerStyle="width: 12%" bodyClass="text-center">
                        <template #body="slotProps">
                            <div class="flex flex-column gap-1 align-items-center">
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
                                <div class="flex gap-1 justify-content-center">
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
                                        v-else
                                        icon="pi pi-send"
                                        severity="success"
                                        text
                                        rounded
                                        size="small"
                                        disabled
                                        v-tooltip.top="'Not ready to forward'"
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
                                        v-else
                                        icon="pi pi-times-circle"
                                        severity="danger"
                                        text
                                        rounded
                                        size="small"
                                        disabled
                                        v-tooltip.top="'Cannot reject'"
                                    />
                                </div>
                            </div>
                        </template>
                    </Column>
                </DataTable>
            </template>
        </Card>

        <!-- Forward Modal -->
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

        <!-- Assign to Staff Modal -->
        <Dialog
            v-model:visible="showAssignModal"
            :style="{ width: '500px' }"
            header="Assign Voucher to Staff"
            :modal="true"
            class="assign-dialog"
            :closable="!isProcessing"
            @hide="closeAssignModal"
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
                                <span>No staff members available for assignment.</span>
                            </div>
                        </template>
                    </Dropdown>
                    <small class="text-500 mt-1 block">
                        <i class="pi pi-info-circle mr-1"></i>
                        Type to search by name or email
                    </small>
                </div>

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

                <div class="border-round bg-blue-50 p-3">
                    <div class="flex align-items-center gap-2">
                        <i class="pi pi-info-circle text-blue-600"></i>
                        <span class="text-sm">This will assign the voucher to the selected staff member for review and action.</span>
                    </div>
                </div>

                <div v-if="isProcessing" class="flex align-items-center justify-content-center gap-2 p-2">
                    <ProgressSpinner style="width: 30px; height: 30px" strokeWidth="4" />
                    <span>Processing...</span>
                </div>
            </div>

            <template #footer>
                <Button label="Cancel" icon="pi pi-times" @click="closeAssignModal" text :disabled="isProcessing" />
                <Button 
                    label="Assign" 
                    icon="pi pi-user-plus" 
                    severity="primary" 
                    @click="handleAssign" 
                    :loading="isProcessing"
                    :disabled="!selectedUser || isProcessing"
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
                <Button label="Close" icon="pi pi-times" @click="closeWorkflowModal" text />
            </template>
        </Dialog>

        <!-- Liability Print Dialog -->
<Dialog
    v-model:visible="showLiabilityPrintDialog"
    :style="{ width: '95vw', maxWidth: '1400px', height: '95vh' }"
    header="Liability Printout - Expenditure Control"
    :modal="true"
    :closable="true"
    @hide="closeLiabilityPrintDialog"
    class="print-dialog"
>
    <div class="print-content-wrapper" id="liability-print-wrapper">
        <div class="print-content" id="liability-print-content" ref="liabilityPrintContentRef">
            <!-- Print Header -->
            <div class="print-header">
                <div class="print-title">OFFICE OF THE ACCOUNTANT GENERAL</div>
                <div class="print-subtitle">EXPENDITURE AND CONTROL DEPARTMENT</div>
                <div class="print-subtitle">TREASURY HOUSE</div>
                <div class="print-subtitle">SECRETARIAT COMPLEX</div>
                <h2 class="print-title">LIABILITY AS AT {{ new Date().toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' }).toUpperCase() }}</h2>
                <div class="print-subtitle font-bold uppercase">
                    {{ selectedMdaName || 'ALL MINISTRIES' }}
                </div>
                <div class="print-info">
                    <span>Generated: {{ currentDateTime }}</span>
                    <span>Total Liabilities: {{ liabilityPrintData.length }} vouchers</span>
                    <span>Total Amount: {{ formatCurrencyForPrint(totalLiabilityAmount) }}</span>
                </div>
            </div>

            <!-- Liability Table with Conditional HEAD/CODE Column -->
            <table class="print-ledger-table" :class="{ 'no-head-code': !hasCapitalVouchers }" id="liability-print-table">
                <thead>
                    <tr>
                        <th class="sno-col">S/NO</th>
                        <th class="date-col">DATE</th>
                        <th class="head-code-col" v-if="hasCapitalVouchers">HEAD/CODE</th>
                        <th class="voucher-col">VOUCHER NO</th>
                        <th class="type-col">TYPE</th>
                        <th class="mda-col">MDA</th>
                        <th class="payee-col">PAYEE</th>
                        <th class="purpose-col">PURPOSE</th>
                        <th class="amount-col">AMOUNT</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="(voucher, index) in liabilityPrintData" :key="voucher.id" class="data-row">
                        <td class="text-center">{{ index + 1 }}</td>
                        <td class="text-center">{{ formatShortDate(voucher.voucher_date) }}</td>
                        <td class="text-center" v-if="hasCapitalVouchers">
                            {{ voucher.voucher_type?.toLowerCase() === 'capital' ? (voucher.mda?.code || 'N/A') : '-' }}
                        </td>
                        <td class="text-center">{{ voucher.voucher_number || 'N/A' }}</td>
                        <td class="text-center">
                            <span class="type-badge">{{ voucher.voucher_type?.toUpperCase() || 'N/A' }}</span>
                        </td>
                        <td class="text-left">{{ voucher.mda?.name || 'N/A' }}</td>
                        <td class="text-left">{{ voucher.payee_name || 'N/A' }}</td>
                        <td class="text-left purpose-cell">{{ voucher.narration || 'N/A' }}</td>
                        <td class="amount-cell">{{ formatCurrencyAmount(voucher.total_amount) }}</td>
                    </tr>

                    <!-- Empty Rows -->
                    <tr v-for="n in liabilityEmptyRows" :key="'empty-' + n" class="empty-row">
                        <td></td>
                        <td></td>
                        <td v-if="hasCapitalVouchers"></td>
                        <td></td>
                        <td></td>
                        <td></td>
                        <td></td>
                        <td></td>
                        <td></td>
                    </tr>

                    <!-- Total Row -->
                    <tr class="total-row">
                        <td :colspan="hasCapitalVouchers ? 8 : 7" class="text-right font-bold">TOTAL LIABILITY</td>
                        <td class="amount-cell font-bold">{{ formatCurrencyAmount(totalLiabilityAmount) }}</td>
                    </tr>
                </tbody>
            </table>

            <!-- Print Footer -->
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
                @click="closeLiabilityPrintDialog" 
                class="p-button-secondary p-button-sm"
            />
            <Button 
                label="Print" 
                icon="pi pi-print" 
                @click="printLiabilityContent" 
                class="p-button-primary p-button-sm"
            />
        </div>
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
/* Liability Banner */
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

/* Liability Print Button */
.liability-print-btn {
    background: linear-gradient(135deg, #f97316, #ea580c) !important;
    border: none !important;
    color: white !important;
    font-weight: 600 !important;
    padding: 0.5rem 1rem !important;
    border-radius: 0.5rem !important;
    transition: all 0.3s ease !important;
    box-shadow: 0 2px 8px rgba(249, 115, 22, 0.3) !important;
}

.liability-print-btn:hover {
    transform: translateY(-2px) !important;
    box-shadow: 0 4px 16px rgba(249, 115, 22, 0.4) !important;
    background: linear-gradient(135deg, #ea580c, #c2410c) !important;
}

.liability-print-btn:active {
    transform: translateY(0) !important;
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

.print-ledger-table .total-row {
    background-color: #fff3e0 !important;
}

.print-ledger-table .empty-row {
    height: 28px;
}

.print-ledger-table .type-badge {
    display: inline-block;
    padding: 2px 8px;
    border-radius: 4px;
    font-weight: bold;
    font-size: 7px;
    background-color: #2196f3;
    color: white;
}

.print-ledger-table .status-badge {
    display: inline-block;
    padding: 2px 8px;
    border-radius: 4px;
    font-weight: bold;
    font-size: 7px;
    background-color: #ff9800;
    color: white;
}

/* Column widths */
.print-ledger-table .sno-col { width: 5%; }
.print-ledger-table .date-col { width: 8%; }
.print-ledger-table .voucher-col { width: 10%; }
.print-ledger-table .type-col { width: 8%; }
.print-ledger-table .mda-col { width: 14%; }
.print-ledger-table .payee-col { width: 12%; }
.print-ledger-table .purpose-col { width: 18%; }
.print-ledger-table .amount-col { width: 10%; }
.print-ledger-table .fa-approved-col { width: 8%; }
.print-ledger-table .status-col { width: 7%; }

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

/* Workflow Timeline */
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

/* Table styling */
:deep(.p-datatable-tfoot) {
    background: #f8fafc;
    font-weight: 600;
}

:deep(.p-datatable-tfoot > tr > td) {
    border-top: 2px solid #e2e8f0;
    padding: 0.75rem 1rem;
}

:deep(.p-datatable) {
    border-radius: 0.75rem;
    overflow: hidden;
}

:deep(.p-datatable-thead > tr > th) {
    background: #f8fafc;
    color: #1e293b;
    font-weight: 600;
    padding: 0.75rem 1rem;
    border-top: 1px solid #e2e8f0;
}

:deep(.p-datatable-tbody > tr) {
    transition: background-color 0.2s;
}

:deep(.p-datatable-tbody > tr:hover) {
    background: #f1f5f9;
}

/* Dialogs */
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

/* Mobile Responsive */
@media (max-width: 768px) {
    :deep(.p-datatable) {
        font-size: 0.875rem;
    }
    
    :deep(.p-datatable-thead > tr > th),
    :deep(.p-datatable-tbody > tr > td) {
        padding: 0.5rem;
    }

    .liability-banner :deep(.p-message) {
        padding: 0.75rem 1rem;
    }
}

/* Column widths - with HEAD/CODE */
.print-ledger-table .sno-col { width: 5%; }
.print-ledger-table .date-col { width: 8%; }
.print-ledger-table .head-code-col { width: 10%; }
.print-ledger-table .voucher-col { width: 10%; }
.print-ledger-table .type-col { width: 8%; }
.print-ledger-table .mda-col { width: 12%; }
.print-ledger-table .payee-col { width: 10%; }
.print-ledger-table .purpose-col { width: 17%; }
.print-ledger-table .amount-col { width: 10%; }

/* Column widths - without HEAD/CODE (when no capital vouchers) */
.print-ledger-table.no-head-code .sno-col { width: 6%; }
.print-ledger-table.no-head-code .date-col { width: 10%; }
.print-ledger-table.no-head-code .voucher-col { width: 12%; }
.print-ledger-table.no-head-code .type-col { width: 10%; }
.print-ledger-table.no-head-code .mda-col { width: 15%; }
.print-ledger-table.no-head-code .payee-col { width: 12%; }
.print-ledger-table.no-head-code .purpose-col { width: 20%; }
.print-ledger-table.no-head-code .amount-col { width: 12%; }
</style>