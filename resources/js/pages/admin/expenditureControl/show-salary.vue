<!-- resources/js/pages/admin/expenditure-control/show-salary.vue -->
<script setup>
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, router } from '@inertiajs/vue3';
import axios from 'axios';
import Badge from 'primevue/badge';
import Button from 'primevue/button';
import Card from 'primevue/card';
import Column from 'primevue/column';
import DataTable from 'primevue/datatable';
import Dialog from 'primevue/dialog';
import Divider from 'primevue/divider';
import InputText from 'primevue/inputtext';
import Message from 'primevue/message';
import Tag from 'primevue/tag';
import Textarea from 'primevue/textarea';
import Timeline from 'primevue/timeline';
import Toast from 'primevue/toast';
import ProgressSpinner from 'primevue/progressspinner';
import { useToast } from 'primevue/usetoast';
import { computed, onMounted, ref } from 'vue';

const toast = useToast();

// State
const showRejectionModal = ref(false);
const showApprovalModal = ref(false);
const showForwardModal = ref(false);
const showSuccessModal = ref(false);
const showErrorModal = ref(false);
const showDocumentViewer = ref(false);
const rejectionReason = ref('');
const rejectionTouched = ref(false);
const documentUrl = ref('');
const currentDocument = ref(null);
const workflowHistory = ref([]);
const errorMessage = ref('');
const errorTitle = ref('');
const successMessage = ref('');
const successTitle = ref('');
const successDetails = ref(null);
const isProcessing = ref(false);

// Props from Laravel controller
const props = defineProps({
    voucher: {
        type: Object,
        required: true,
    },
    approvalFlow: {
        type: Object,
        default: () => ({}),
    },
    currentStep: {
        type: Number,
        default: 0,
    },
    nextRole: {
        type: String,
        default: null,
    },
});

const breadcrumbs = [
    { title: 'Expenditure Control', href: '/expenditure-control' },
    { title: 'Salary Queue', href: '/expenditure-control/salary' },
    { title: props.voucher?.voucher_number || 'Salary Voucher', href: '#' },
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

// Get salary type label
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
        forwarded: 'warning',
        ec_approved: 'success',
        inspectorate_pending: 'info',
        inspectorate_approved: 'info',
        tco_approved: 'success',
        sent_back: 'danger',
        rejected: 'danger',
        closed: 'success',
    };
    return statuses[status?.toLowerCase()] || 'info';
};

// Get status display name
const getStatusDisplayName = (status) => {
    const names = {
        forwarded: 'Forwarded from FA',
        ec_approved: 'EC Approved',
        inspectorate_pending: 'With Inspectorate',
        inspectorate_approved: 'Inspectorate Approved',
        tco_approved: 'TCO Approved (Closed)',
        sent_back: 'Sent Back',
        rejected: 'Rejected',
        closed: 'Closed',
    };
    return names[status?.toLowerCase()] || status || 'Unknown';
};

// Get workflow step for salary
const getWorkflowStep = (status) => {
    const steps = {
        forwarded: 1,
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

// Check if voucher is ready for EC approval
const isReadyForApproval = computed(() => {
    return props.voucher?.status?.toLowerCase() === 'forwarded';
});

// Check if voucher is ready to forward to Inspectorate
const isReadyForForward = computed(() => {
    return props.voucher?.status?.toLowerCase() === 'ec_approved';
});

// Check if voucher can be rejected
const canReject = computed(() => {
    return ['forwarded', 'ec_approved'].includes(props.voucher?.status?.toLowerCase());
});

// Open approval modal
const openApproveModal = () => {
    if (!isReadyForApproval.value) {
        errorTitle.value = 'Cannot Approve Voucher';
        errorMessage.value = `This voucher has status "${getStatusDisplayName(props.voucher.status)}" and must be "Forwarded from FA" before Expenditure Control can process it.`;
        showErrorModal.value = true;
        return;
    }
    showApprovalModal.value = true;
};

// Open forward modal
const openForwardModal = () => {
    if (!isReadyForForward.value) {
        errorTitle.value = 'Cannot Forward Voucher';
        errorMessage.value = `This voucher has status "${getStatusDisplayName(props.voucher.status)}" and must be "EC Approved" before forwarding to Inspectorate.`;
        showErrorModal.value = true;
        return;
    }
    showForwardModal.value = true;
};

// Open rejection modal
const openRejectModal = () => {
    if (!canReject.value) {
        errorTitle.value = 'Cannot Reject Voucher';
        errorMessage.value = `This voucher has status "${getStatusDisplayName(props.voucher.status)}" and cannot be rejected at this stage.`;
        showErrorModal.value = true;
        return;
    }
    rejectionReason.value = '';
    rejectionTouched.value = false;
    showRejectionModal.value = true;
};

// Handle approval
const handleApprove = () => {
    isProcessing.value = true;
    
    router.post(`/expenditure-control/salary-vouchers/${props.voucher.id}/approve`, {}, {
        preserveScroll: true,
        onSuccess: (response) => {
            showApprovalModal.value = false;
            isProcessing.value = false;
            
            successTitle.value = 'Salary Voucher Approved!';
            successMessage.value = response.message || `Salary voucher has been successfully approved by Expenditure Control.`;
            successDetails.value = {
                voucher_number: props.voucher.voucher_number,
                amount: formatCurrency(props.voucher.total_amount),
                next_stage: 'Inspectorate',
                status: 'EC Approved',
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

// Handle forward to Inspectorate
const handleForward = () => {
    isProcessing.value = true;
    
    router.post(`/expenditure-control/salary-vouchers/${props.voucher.id}/forward-inspectorate`, {}, {
        preserveScroll: true,
        onSuccess: (response) => {
            showForwardModal.value = false;
            isProcessing.value = false;
            
            successTitle.value = 'Forwarded to Inspectorate!';
            successMessage.value = response.message || `Salary voucher has been forwarded to Inspectorate for review.`;
            successDetails.value = {
                voucher_number: props.voucher.voucher_number,
                amount: formatCurrency(props.voucher.total_amount),
                forwarded_to: 'Inspectorate',
                status: 'With Inspectorate',
                forwarded_at: new Date().toLocaleString()
            };
            showSuccessModal.value = true;
        },
        onError: (errors) => {
            isProcessing.value = false;
            console.error('Forward error:', errors);
            
            if (errors.response?.data?.message) {
                errorTitle.value = 'Forward Failed';
                errorMessage.value = errors.response.data.message;
                showErrorModal.value = true;
            } else if (errors.message) {
                errorTitle.value = 'Forward Failed';
                errorMessage.value = errors.message;
                showErrorModal.value = true;
            } else {
                toast.add({
                    severity: 'error',
                    summary: 'Error',
                    detail: 'Failed to forward voucher to Inspectorate.',
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

    router.post(`/expenditure-control/salary-vouchers/${props.voucher.id}/reject`, {
        reason: rejectionReason.value,
    }, {
        preserveScroll: true,
        onSuccess: () => {
            successTitle.value = 'Salary Voucher Rejected';
            successMessage.value = `Salary voucher ${props.voucher.voucher_number} has been rejected and returned to FA.`;
            successDetails.value = {
                voucher_number: props.voucher.voucher_number,
                amount: formatCurrency(props.voucher.total_amount),
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
        router.visit('/expenditure-control/salary');
    }, 300);
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

// Print voucher
const printVoucher = () => {
    const printUrl = `/vouchers/${props.voucher.id}/print`;
    window.open(printUrl, '_blank');
};

// Go back
const goBack = () => {
    router.visit('/expenditure-control/salary');
};

// Load workflow history
const loadWorkflowHistory = async () => {
    try {
        const response = await axios.get(`/vouchers/${props.voucher.id}/approvals`);
        workflowHistory.value = response.data || [];
    } catch (error) {
        console.error('Error loading workflow:', error);
        workflowHistory.value = [];
    }
};

// Initialize
onMounted(() => {
    loadWorkflowHistory();
});
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head :title="`Salary Voucher - ${voucher.voucher_number}`" />
        <Toast />

        <!-- Header with Actions -->
        <div class="mb-4 flex align-items-center justify-content-between flex-wrap gap-3">
            <div class="flex align-items-center gap-3">
                <Button 
                    icon="pi pi-arrow-left" 
                    label="Back to Salary Queue" 
                    severity="secondary" 
                    text
                    @click="goBack" 
                />
                <div class="flex align-items-center gap-2">
                    <i class="pi pi-wallet text-primary text-xl"></i>
                    <h1 class="text-2xl font-semibold m-0">Salary Voucher Review</h1>
                </div>
            </div>
            <div class="flex gap-2 flex-wrap">
                <Button 
                    icon="pi pi-print" 
                    label="Print" 
                    severity="info" 
                    outlined
                    @click="printVoucher" 
                />
                <Button 
                    v-if="isReadyForApproval"
                    icon="pi pi-check-circle" 
                    label="Approve Salary" 
                    severity="success" 
                    @click="openApproveModal" 
                />
                <Button 
                    v-if="isReadyForForward"
                    icon="pi pi-send" 
                    label="Forward to Inspectorate" 
                    severity="primary" 
                    @click="openForwardModal" 
                />
                <Button 
                    v-if="canReject"
                    icon="pi pi-times-circle" 
                    label="Reject & Return" 
                    severity="danger" 
                    @click="openRejectModal" 
                />
            </div>
        </div>

        <!-- Status Warning Banner if not ready for approval -->
        <div v-if="!isReadyForApproval && !isReadyForForward && voucher.status !== 'inspectorate_pending' && voucher.status !== 'tco_approved'" class="mb-4">
            <Message severity="warn" :closable="false">
                <div class="flex align-items-center gap-2">
                    <i class="pi pi-exclamation-triangle text-xl"></i>
                    <div>
                        <strong>Voucher Not Ready for Expenditure Control Review</strong>
                        <div class="text-sm mt-1">
                            This voucher has status <strong>{{ getStatusDisplayName(voucher.status) }}</strong>. 
                            Only vouchers with status <strong>"Forwarded from FA"</strong> can be approved by Expenditure Control.
                        </div>
                    </div>
                </div>
            </Message>
        </div>

        <!-- Workflow Info Banner -->
        <div class="mb-4">
            <Message severity="info" :closable="false" class="workflow-banner">
                <div class="flex align-items-center gap-3 flex-wrap">
                    <i class="pi pi-wallet text-xl"></i>
                    <div>
                        <strong>Salary Voucher Workflow - Step 4 of 6</strong>
                        <div class="text-sm mt-1">
                            Reviewing salary voucher <strong>{{ voucher.voucher_number }}</strong>. 
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

        <!-- Main Content Grid -->
        <div class="grid">
            <!-- Left Column - Voucher Details -->
            <div class="col-12 lg:col-8">
                <Card class="voucher-details-card">
                    <template #title>
                        <div class="flex align-items-center justify-content-between flex-wrap gap-2">
                            <div class="flex align-items-center gap-2">
                                <i class="pi pi-wallet text-primary"></i>
                                <span>Salary Voucher Information</span>
                            </div>
                            <Tag 
                                :value="getStatusDisplayName(voucher.status)" 
                                :severity="getStatusSeverity(voucher.status)"
                                size="large"
                            />
                        </div>
                    </template>
                    <template #content>
                        <div class="grid">
                            <div class="col-12 md:col-6">
                                <div class="field mb-3">
                                    <label class="text-500 text-sm font-semibold block mb-1">Voucher Number</label>
                                    <div class="text-900 font-medium">{{ voucher.voucher_number }}</div>
                                </div>
                            </div>
                            <div class="col-12 md:col-6">
                                <div class="field mb-3">
                                    <label class="text-500 text-sm font-semibold block mb-1">Voucher Date</label>
                                    <div class="text-900">{{ formatDate(voucher.voucher_date) }}</div>
                                </div>
                            </div>
                            <div class="col-12 md:col-6">
                                <div class="field mb-3">
                                    <label class="text-500 text-sm font-semibold block mb-1">Voucher Type</label>
                                    <Tag 
                                        :value="voucher.voucher_type?.toUpperCase() || 'N/A'" 
                                        :severity="getVoucherTypeSeverity(voucher.voucher_type)"
                                    />
                                </div>
                            </div>
                            <div class="col-12 md:col-6">
                                <div class="field mb-3">
                                    <label class="text-500 text-sm font-semibold block mb-1">Salary Type</label>
                                    <Tag 
                                        :value="getSalaryTypeLabel(voucher.salary_type)" 
                                        :severity="getSalaryTypeSeverity(voucher.salary_type)"
                                    />
                                </div>
                            </div>
                            <div class="col-12 md:col-6">
                                <div class="field mb-3">
                                    <label class="text-500 text-sm font-semibold block mb-1">Total Amount</label>
                                    <div class="text-900 text-xl font-bold text-primary">
                                        {{ formatCurrency(voucher.total_amount) }}
                                    </div>
                                </div>
                            </div>
                            <div class="col-12 md:col-6">
                                <div class="field mb-3">
                                    <label class="text-500 text-sm font-semibold block mb-1">Progress</label>
                                    <div class="flex flex-column gap-1">
                                        <div class="flex align-items-center justify-content-between">
                                            <span class="text-xs font-medium">{{ getStatusDisplayName(voucher.status) }}</span>
                                            <span class="text-xs">{{ getProgressPercentage(voucher.status) }}%</span>
                                        </div>
                                        <div class="progress-bar-container">
                                            <div 
                                                class="progress-bar-fill"
                                                :class="getProgressPercentage(voucher.status) >= 80 ? 'bg-green-500' : 
                                                       getProgressPercentage(voucher.status) >= 40 ? 'bg-blue-500' : 'bg-yellow-500'"
                                                :style="{ width: getProgressPercentage(voucher.status) + '%' }"
                                            ></div>
                                        </div>
                                        <div class="text-500 text-xs flex justify-content-between">
                                            <span>DFA</span>
                                            <span>EC</span>
                                            <span>TCO</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-12">
                                <div class="field mb-3">
                                    <label class="text-500 text-sm font-semibold block mb-1">MDA</label>
                                    <div class="text-900">{{ voucher.mda?.name || 'N/A' }}</div>
                                    <div class="text-500 text-sm">{{ voucher.mda?.code || '' }}</div>
                                </div>
                            </div>
                            <div class="col-12">
                                <div class="field mb-3">
                                    <label class="text-500 text-sm font-semibold block mb-1">Payee Name</label>
                                    <div class="text-900">{{ voucher.payee_name || 'N/A' }}</div>
                                </div>
                            </div>

                            <!-- Bank Information Display -->
                            <div class="col-12">
                                <div class="field mb-3">
                                    <label class="text-500 text-sm font-semibold block mb-1">Destination Bank</label>
                                    <div v-if="voucher.bank_activity" class="bg-blue-50 border-round p-3">
                                        <div class="flex flex-column gap-1">
                                            <div class="flex align-items-center gap-2">
                                                <i class="pi pi-building text-blue-600"></i>
                                                <span class="font-semibold">{{ voucher.bank_activity.bank_name }}</span>
                                            </div>
                                            <div class="flex align-items-center gap-2">
                                                <i class="pi pi-credit-card text-blue-600"></i>
                                                <span>Account: {{ voucher.bank_activity.account_number }}</span>
                                            </div>
                                            <div class="flex align-items-center gap-2">
                                                <i class="pi pi-tag text-blue-600"></i>
                                                <span>Tag: {{ voucher.bank_activity.tag }}</span>
                                            </div>
                                            <div v-if="voucher.bank_activity.title" class="flex align-items-center gap-2">
                                                <i class="pi pi-info-circle text-blue-600"></i>
                                                <span>Title: {{ voucher.bank_activity.title }}</span>
                                            </div>
                                        </div>
                                    </div>
                                    <div v-else class="bg-yellow-50 border-round p-3">
                                        <div class="flex align-items-center gap-2">
                                            <i class="pi pi-info-circle text-yellow-600"></i>
                                            <span>Bank will be assigned by TCO</span>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="col-12">
                                <div class="field mb-3">
                                    <label class="text-500 text-sm font-semibold block mb-1">Narration / Description</label>
                                    <div class="text-700 p-2 bg-gray-50 border-round">{{ voucher.narration || 'No description' }}</div>
                                </div>
                            </div>
                        </div>
                    </template>
                </Card>

                <!-- Line Items Table -->
                <Card class="mt-4 line-items-card">
                    <template #title>
                        <div class="flex align-items-center gap-2">
                            <i class="pi pi-list text-primary"></i>
                            <span>Line Items</span>
                            <Badge :value="voucher.items?.length || 0" severity="info" />
                        </div>
                    </template>
                    <template #content>
                        <DataTable 
                            :value="voucher.items || []" 
                            class="p-datatable-sm" 
                            responsiveLayout="scroll"
                            :emptyMessage="'No line items found.'"
                        >
                            <Column field="description" header="Description" headerStyle="width: 35%">
                                <template #body="slotProps">
                                    <span class="text-900">{{ slotProps.data.description }}</span>
                                </template>
                            </Column>
                            <Column field="quantity" header="Qty" headerStyle="width: 10%" bodyClass="text-right">
                                <template #body="slotProps">
                                    {{ slotProps.data.quantity }}
                                </template>
                            </Column>
                            <Column field="unit_price" header="Unit Price" headerStyle="width: 15%" bodyClass="text-right">
                                <template #body="slotProps">
                                    {{ formatCurrency(slotProps.data.unit_price) }}
                                </template>
                            </Column>
                            <Column field="sub_total" header="Sub Total" headerStyle="width: 20%" bodyClass="text-right font-bold">
                                <template #body="slotProps">
                                    {{ formatCurrency(slotProps.data.sub_total) }}
                                </template>
                            </Column>
                            <Column field="programme_code" header="Programme Code" headerStyle="width: 20%">
                                <template #body="slotProps">
                                    <div>
                                        <div class="font-medium">{{ slotProps.data.programme_code || 'N/A' }}</div>
                                        <div class="text-500 text-xs">{{ slotProps.data.programme_name || '' }}</div>
                                    </div>
                                </template>
                            </Column>
                        </DataTable>
                    </template>
                </Card>

                <!-- Documents Section -->
                <Card class="mt-4 documents-card">
                    <template #title>
                        <div class="flex align-items-center gap-2">
                            <i class="pi pi-file-pdf text-primary"></i>
                            <span>Attached Documents</span>
                            <Badge :value="voucher.documents?.length || 0" severity="info" />
                        </div>
                    </template>
                    <template #content>
                        <div v-if="voucher.documents && voucher.documents.length > 0" class="grid">
                            <div v-for="doc in voucher.documents" :key="doc.id" class="col-12 md:col-6">
                                <div class="flex align-items-center justify-content-between p-3 surface-100 border-round">
                                    <div class="flex align-items-center gap-2">
                                        <i class="pi pi-file-pdf text-red-500 text-xl" v-if="doc.file_name?.endsWith('.pdf')"></i>
                                        <i class="pi pi-file-image text-blue-500 text-xl" v-else-if="doc.file_name?.match(/\.(jpg|jpeg|png|gif)$/i)"></i>
                                        <i class="pi pi-file text-gray-500 text-xl" v-else></i>
                                        <div>
                                            <div class="font-medium">{{ doc.document_label || 'Document' }}</div>
                                            <div class="text-500 text-sm">{{ doc.file_name }}</div>
                                        </div>
                                    </div>
                                    <Button 
                                        icon="pi pi-eye" 
                                        severity="info" 
                                        text 
                                        rounded
                                        @click="viewDocument(doc)"
                                        v-tooltip.top="'View Document'"
                                    />
                                </div>
                            </div>
                        </div>
                        <div v-else class="text-center p-4">
                            <i class="pi pi-inbox text-400 text-3xl mb-2"></i>
                            <p class="text-600 m-0">No documents attached to this voucher.</p>
                        </div>
                    </template>
                </Card>
            </div>

            <!-- Right Column - Workflow & Summary -->
            <div class="col-12 lg:col-4">
                <!-- Summary Card -->
                <Card class="summary-card">
                    <template #title>
                        <div class="flex align-items-center gap-2">
                            <i class="pi pi-chart-line text-primary"></i>
                            <span>Voucher Summary</span>
                        </div>
                    </template>
                    <template #content>
                        <div class="flex flex-column gap-3">
                            <div class="flex justify-content-between align-items-center">
                                <span class="text-500">Total Items:</span>
                                <span class="font-semibold">{{ voucher.items?.length || 0 }}</span>
                            </div>
                            <div class="flex justify-content-between align-items-center">
                                <span class="text-500">Total Amount:</span>
                                <span class="font-bold text-primary text-xl">{{ formatCurrency(voucher.total_amount) }}</span>
                            </div>
                            <Divider />
                            <div class="flex justify-content-between align-items-center">
                                <span class="text-500">Salary Type:</span>
                                <Tag :value="getSalaryTypeLabel(voucher.salary_type)" :severity="getSalaryTypeSeverity(voucher.salary_type)" />
                            </div>
                            <div class="flex justify-content-between align-items-center">
                                <span class="text-500">Current Stage:</span>
                                <Tag value="Expenditure Control (EC)" severity="warning" />
                            </div>
                            <div class="flex justify-content-between align-items-center">
                                <span class="text-500">Required Status:</span>
                                <Tag value="Forwarded from FA" severity="warning" icon="pi pi-arrow-right" />
                            </div>
                            <div class="flex justify-content-between align-items-center">
                                <span class="text-500">Current Status:</span>
                                <Tag :value="getStatusDisplayName(voucher.status)" :severity="getStatusSeverity(voucher.status)" />
                            </div>
                            <Divider />
                            <div class="flex justify-content-between align-items-center">
                                <span class="text-500">Next Stage:</span>
                                <Tag value="Inspectorate" severity="info" icon="pi pi-arrow-right" />
                            </div>
                            <Divider />
                            <div class="flex justify-content-between align-items-center">
                                <span class="text-500">Payment Bank:</span>
                                <div v-if="voucher.bank_activity">
                                    <Tag :value="voucher.bank_activity.bank_name" severity="info" size="small" />
                                </div>
                                <span v-else class="text-yellow-600">Pending TCO Assignment</span>
                            </div>
                            <div class="flex justify-content-between align-items-center">
                                <span class="text-500">Workflow:</span>
                                <span class="text-xs text-600">DFA → IA → FA → EC → Inspectorate → TCO</span>
                            </div>
                        </div>
                    </template>
                </Card>

                <!-- Workflow Timeline Card -->
                <Card class="mt-4 timeline-card">
                    <template #title>
                        <div class="flex align-items-center gap-2">
                            <i class="pi pi-sitemap text-primary"></i>
                            <span>Approval Workflow</span>
                        </div>
                    </template>
                    <template #content>
                        <div class="workflow-timeline" style="max-height: 500px; overflow-y: auto;">
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
                                    <Card class="workflow-card-item">
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
                    </template>
                </Card>
            </div>
        </div>

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
                        <div v-if="successDetails?.next_stage" class="flex justify-content-between mb-1">
                            <span class="text-500">Next Stage:</span>
                            <Tag :value="successDetails.next_stage" severity="info" size="small" />
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
                    label="Go to Salary Queue" 
                    icon="pi pi-arrow-right" 
                    @click="closeSuccessModal" 
                    severity="primary"
                    autofocus
                />
            </template>
        </Dialog>

        <!-- Approval Modal -->
        <Dialog
            v-model:visible="showApprovalModal"
            :style="{ width: '500px' }"
            header="Expenditure Control Approval - Salary Voucher"
            :modal="true"
            class="approval-dialog"
            :closable="!isProcessing"
        >
            <div class="flex flex-column gap-3">
                <div class="flex align-items-center gap-3 p-3 bg-blue-50 border-round">
                    <i class="pi pi-info-circle text-blue-500 text-xl"></i>
                    <div>
                        <div class="font-semibold">Voucher: {{ voucher.voucher_number }}</div>
                        <div class="text-sm">Amount: {{ formatCurrency(voucher.total_amount) }}</div>
                        <div class="text-sm">Type: {{ getSalaryTypeLabel(voucher.salary_type) }}</div>
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
            :style="{ width: '500px' }"
            header="Forward to Inspectorate"
            :modal="true"
            class="forward-dialog"
            :closable="!isProcessing"
        >
            <div class="flex flex-column gap-3">
                <div class="flex align-items-center gap-3 p-3 bg-purple-50 border-round">
                    <i class="pi pi-send text-purple-500 text-xl"></i>
                    <div>
                        <div class="font-semibold">Voucher: {{ voucher.voucher_number }}</div>
                        <div class="text-sm">Amount: {{ formatCurrency(voucher.total_amount) }}</div>
                        <div class="text-sm">Type: {{ getSalaryTypeLabel(voucher.salary_type) }}</div>
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
                <Button label="Forward to Inspectorate" icon="pi pi-send" severity="primary" @click="handleForward" :loading="isProcessing" />
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
                        <div class="font-semibold">Voucher: {{ voucher.voucher_number }}</div>
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

                <div class="border-round bg-gray-50 p-3">
                    <div class="flex align-items-center gap-2">
                        <i class="pi pi-info-circle text-gray-600"></i>
                        <span class="text-sm">The FA will be notified and can resubmit after corrections.</span>
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
                
                <Divider />
                
                <div class="bg-yellow-50 p-3 border-round w-full text-left">
                    <div class="flex align-items-center gap-2 mb-2">
                        <i class="pi pi-info-circle text-yellow-600"></i>
                        <span class="font-semibold">What you can do:</span>
                    </div>
                    <ul class="m-0 pl-3 text-sm">
                        <li>Ensure the voucher has been approved by Final Accounts</li>
                        <li>The voucher status should be "Forwarded from FA"</li>
                        <li>Contact Final Accounts if the voucher needs to be reviewed</li>
                        <li>Refresh the page and try again</li>
                    </ul>
                </div>
            </div>

            <template #footer>
                <Button 
                    label="Go Back to Salary Queue" 
                    icon="pi pi-arrow-left" 
                    @click="showErrorModal = false; goBack()" 
                    severity="secondary"
                />
                <Button 
                    label="Close" 
                    icon="pi pi-times" 
                    @click="showErrorModal = false" 
                    text
                />
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
    </AppLayout>
</template>

<style scoped>
.voucher-details-card :deep(.p-card),
.line-items-card :deep(.p-card),
.documents-card :deep(.p-card),
.summary-card :deep(.p-card),
.timeline-card :deep(.p-card) {
    border-radius: 1rem;
    overflow: hidden;
}

.voucher-details-card :deep(.p-card-header),
.line-items-card :deep(.p-card-header),
.documents-card :deep(.p-card-header),
.summary-card :deep(.p-card-header),
.timeline-card :deep(.p-card-header) {
    background: #f8fafc;
    border-bottom: 1px solid #e2e8f0;
    padding: 1rem 1.25rem;
}

.workflow-banner :deep(.p-message) {
    background: linear-gradient(135deg, #dbeafe 0%, #bfdbfe 100%);
    border: none;
    border-radius: 0.75rem;
}

.workflow-card-item :deep(.p-card) {
    box-shadow: none;
    border: 1px solid #e2e8f0;
    border-radius: 0.5rem;
    margin: 0.5rem 0;
}

.workflow-card-item :deep(.p-card-content) {
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

.progress-bar-container {
    width: 100%;
    height: 8px;
    background-color: #e5e7eb;
    border-radius: 4px;
    overflow: hidden;
}

.progress-bar-fill {
    height: 100%;
    border-radius: 4px;
    transition: width 0.3s ease;
}

.approval-dialog :deep(.p-dialog-header),
.forward-dialog :deep(.p-dialog-header),
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