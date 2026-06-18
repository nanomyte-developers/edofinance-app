<!-- resources/js/pages/admin/finalAccounts/show.vue -->
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
import Panel from 'primevue/panel';
import Tag from 'primevue/tag';
import Textarea from 'primevue/textarea';
import Timeline from 'primevue/timeline';
import Toast from 'primevue/toast';
import ProgressSpinner from 'primevue/progressspinner';
import { useToast } from 'primevue/usetoast';
import { computed, onMounted, ref } from 'vue';
import moment from 'moment';

const toast = useToast();

// State
const showRejectionModal = ref(false);
const showApprovalModal = ref(false);
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
});

const breadcrumbs = [
    { title: 'Final Accounts', href: '/final-accounts' },
    { title: 'Voucher Details', href: '#' },
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

// Get status badge severity
const getStatusSeverity = (status) => {
    const statuses = {
        draft: 'secondary',
        submitted: 'info',
        audit_approved: 'success',
        approved: 'success',
        forwarded: 'warning',
        sent_back: 'danger',
        rejected: 'danger',
        closed: 'success',
    };
    return statuses[status?.toLowerCase()] || 'info';
};

// Get status display name
const getStatusDisplayName = (status) => {
    const names = {
        draft: 'Draft',
        submitted: 'Submitted',
        audit_approved: 'Audit Approved',
        approved: 'Approved',
        forwarded: 'Forwarded',
        sent_back: 'Sent Back',
        rejected: 'Rejected',
        closed: 'Closed',
    };
    return names[status?.toLowerCase()] || status || 'Unknown';
};

// Get next stage based on voucher type
const getNextStage = () => {
    if (!props.voucher) return 'N/A';
    
    if (props.voucher.voucher_type?.toLowerCase() === 'salary') {
        return 'Inspectorate → TCO';
    }
    return 'Expenditure Control (EC) → Accountant General (AG) → Management Account Section (MAS)';
};

// Check if voucher is ready for Final Accounts approval
const isReadyForApproval = computed(() => {
    return props.voucher.status?.toLowerCase() === 'audit_approved';
});

// Open approval modal
const openApproveModal = () => {
    if (!isReadyForApproval.value) {
        errorTitle.value = 'Cannot Approve Voucher';
        errorMessage.value = `This voucher has status "${getStatusDisplayName(props.voucher.status)}" and must be "Audit Approved" before Final Accounts can process it.`;
        showErrorModal.value = true;
        return;
    }
    showApprovalModal.value = true;
};

// Open rejection modal
const openRejectModal = () => {
    if (!isReadyForApproval.value) {
        errorTitle.value = 'Cannot Reject Voucher';
        errorMessage.value = `This voucher has status "${getStatusDisplayName(props.voucher.status)}" and must be "Audit Approved" before Final Accounts can reject it.`;
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
    
    router.post(`/final-accounts/vouchers/${props.voucher.id}/approve`, {}, {
        preserveScroll: true,
        onSuccess: (response) => {
            showApprovalModal.value = false;
            isProcessing.value = false;
            
            // Set success modal data
            successTitle.value = 'Voucher Approved & Forwarded!';
            successMessage.value = response.message || `Voucher has been successfully forwarded to ${response.next_role || 'the next stage'}.`;
            successDetails.value = {
                voucher_number: response.voucher?.voucher_number,
                amount: formatCurrency(response.voucher?.total_amount),
                next_role: response.next_role,
                status: 'Forwarded',
                forwarded_at: new Date().toLocaleString()
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

    router.post(`/final-accounts/vouchers/${props.voucher.id}/reject`, {
        reason: rejectionReason.value,
    }, {
        preserveScroll: true,
        onSuccess: () => {
            successTitle.value = 'Voucher Rejected';
            successMessage.value = `Voucher ${props.voucher.voucher_number} has been rejected and returned to DFA.`;
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
    // Redirect back to index after modal closes
    setTimeout(() => {
        router.visit('/final-accounts');
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
    router.visit('/final-accounts');
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
        <Head :title="`Final Accounts - ${voucher.voucher_number}`" />
        <Toast />

        <!-- Header with Actions -->
        <div class="mb-4 flex align-items-center justify-content-between flex-wrap gap-3">
            <div class="flex align-items-center gap-3">
                <Button 
                    icon="pi pi-arrow-left" 
                    label="Back to Queue" 
                    severity="secondary" 
                    text
                    @click="goBack" 
                />
                <div class="flex align-items-center gap-2">
                    <i class="pi pi-file-pdf text-primary text-xl"></i>
                    <h1 class="text-2xl font-semibold m-0">Voucher Details</h1>
                </div>
            </div>
            <div class="flex gap-2">
                <Button 
                    icon="pi pi-print" 
                    label="Print" 
                    severity="info" 
                    outlined
                    @click="printVoucher" 
                />
                <Button 
                    icon="pi pi-check-circle" 
                    label="Approve & Forward" 
                    severity="success" 
                    @click="openApproveModal" 
                />
                <Button 
                    icon="pi pi-times-circle" 
                    label="Reject & Return" 
                    severity="danger" 
                    @click="openRejectModal" 
                />
            </div>
        </div>

        <!-- Status Warning Banner if not ready for approval -->
        <div v-if="!isReadyForApproval" class="mb-4">
            <Message severity="warn" :closable="false">
                <div class="flex align-items-center gap-2">
                    <i class="pi pi-exclamation-triangle text-xl"></i>
                    <div>
                        <strong>Voucher Not Ready for Final Accounts Review</strong>
                        <div class="text-sm mt-1">
                            This voucher has status <strong>{{ getStatusDisplayName(voucher.status) }}</strong>. 
                            Only vouchers with status <strong>"Audit Approved"</strong> can be processed by Final Accounts.
                        </div>
                    </div>
                </div>
            </Message>
        </div>

        <!-- Workflow Info Banner -->
        <div class="mb-4">
            <Message severity="info" :closable="false" class="workflow-banner">
                <div class="flex align-items-center gap-3 flex-wrap">
                    <i class="pi pi-share-alt text-xl"></i>
                    <div>
                        <strong>Final Accounts (FA) - Step 3 of 6</strong>
                        <div class="text-sm mt-1">
                            Reviewing voucher <strong>{{ voucher.voucher_number }}</strong>. 
                            <span class="font-semibold">Salary vouchers</span> go to Inspectorate → TCO.
                            <span class="font-semibold">Other vouchers</span> go to EC → AG → MAS.
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
                                <i class="pi pi-file-text text-primary"></i>
                                <span>Voucher Information</span>
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
                                    <label class="text-500 text-sm font-semibold block mb-1">Total Amount</label>
                                    <div class="text-900 text-xl font-bold text-primary">
                                        {{ formatCurrency(voucher.total_amount) }}
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
                                <span class="text-500">Current Stage:</span>
                                <Tag value="Final Accounts (FA)" severity="warning" />
                            </div>
                            <div class="flex justify-content-between align-items-center">
                                <span class="text-500">Required Status:</span>
                                <Tag value="Audit Approved" severity="success" icon="pi pi-check-circle" />
                            </div>
                            <div class="flex justify-content-between align-items-center">
                                <span class="text-500">Current Status:</span>
                                <Tag :value="getStatusDisplayName(voucher.status)" :severity="getStatusSeverity(voucher.status)" />
                            </div>
                            <Divider />
                            <div class="flex justify-content-between align-items-center">
                                <span class="text-500">Next Stage:</span>
                                <Tag :value="getNextStage()" severity="info" icon="pi pi-arrow-right" />
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

        <!-- Success Modal (SweetAlert style) -->
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
                        <div v-if="successDetails?.next_role" class="flex justify-content-between mb-1">
                            <span class="text-500">Forwarded To:</span>
                            <Tag :value="successDetails.next_role" severity="info" size="small" />
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

        <!-- Approval Modal -->
        <Dialog
            v-model:visible="showApprovalModal"
            :style="{ width: '500px' }"
            header="Final Accounts Approval"
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
                        <div class="text-sm">Type: {{ voucher.voucher_type?.toUpperCase() }}</div>
                    </div>
                </div>

                <div class="border-round bg-yellow-50 p-3">
                    <div class="flex align-items-center gap-2 mb-2">
                        <i class="pi pi-arrow-right text-yellow-600"></i>
                        <span class="font-semibold">Next Stage:</span>
                        <Tag :value="getNextStage()" severity="info" />
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

                <div v-if="isProcessing" class="flex align-items-center justify-content-center gap-2 p-2">
                    <ProgressSpinner style="width: 30px; height: 30px" strokeWidth="4" />
                    <span>Processing...</span>
                </div>
            </div>

            <template #footer>
                <Button label="Cancel" icon="pi pi-times" @click="showApprovalModal = false" text :disabled="isProcessing" />
                <Button label="Approve & Forward" icon="pi pi-send" severity="success" @click="handleApprove" :loading="isProcessing" />
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
                        <div class="font-semibold">Voucher: {{ voucher.voucher_number }}</div>
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

                <div class="border-round bg-gray-50 p-3">
                    <div class="flex align-items-center gap-2">
                        <i class="pi pi-info-circle text-gray-600"></i>
                        <span class="text-sm">The DFA will be notified and can resubmit after corrections.</span>
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
                        <li>Ensure the voucher has been approved by Internal Audit</li>
                        <li>The voucher status should be "Audit Approved"</li>
                        <li>Contact Internal Audit if the voucher needs to be reviewed</li>
                        <li>Refresh the page and try again</li>
                    </ul>
                </div>
            </div>

            <template #footer>
                <Button 
                    label="Go Back to Queue" 
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
    background: linear-gradient(135deg, #e0f2fe 0%, #bae6fd 100%);
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

.approval-dialog :deep(.p-dialog-header),
.rejection-dialog :deep(.p-dialog-header),
.error-dialog :deep(.p-dialog-header),
.success-dialog :deep(.p-dialog-header) {
    background: #f8fafc;
    border-bottom: 1px solid #e2e8f0;
}

.approval-dialog :deep(.p-dialog-content),
.rejection-dialog :deep(.p-dialog-content),
.error-dialog :deep(.p-dialog-content),
.success-dialog :deep(.p-dialog-content) {
    padding: 1.5rem;
}

.error-icon {
    animation: shake 0.5s ease-in-out;
}

.success-icon {
    animation: bounce 0.5s ease-in-out;
}

@keyframes shake {
    0%, 100% { transform: translateX(0); }
    25% { transform: translateX(-5px); }
    75% { transform: translateX(5px); }
}

@keyframes bounce {
    0%, 100% { transform: scale(1); }
    50% { transform: scale(1.2); }
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

:deep(.p-datatable-tbody > tr) {
    transition: background-color 0.2s;
}

:deep(.p-datatable-tbody > tr:hover) {
    background: #f1f5f9;
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