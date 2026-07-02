<script setup>
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, router } from '@inertiajs/vue3';
import Button from 'primevue/button';
import Card from 'primevue/card';
import Column from 'primevue/column';
import DataTable from 'primevue/datatable';
import Dialog from 'primevue/dialog';
import Divider from 'primevue/divider';
import Tag from 'primevue/tag';
import Textarea from 'primevue/textarea';
import Toast from 'primevue/toast';
import Timeline from 'primevue/timeline';
import Badge from 'primevue/badge';
import { useToast } from 'primevue/usetoast';
import { computed, ref, watch, onMounted } from 'vue';

const toast = useToast();

// Props
const props = defineProps({
    voucher: {
        type: Object,
        required: true,
    },
    requiredDocuments: {
        type: Array,
        default: () => ['approval_form'],
    },
});

// State
const showRejectionModal = ref(false);
const showApprovalModal = ref(false);
const showDocumentViewer = ref(false);
const rejectionReason = ref('');
const documentUrl = ref('');
const isSubmitting = ref(false);
const debugInfo = ref('');
const currentDocument = ref(null);
const workflowHistory = ref([]);

const breadcrumbs = [
    { title: 'Audit Queue', href: '/internal-audits' },
    { title: `Voucher ${props.voucher.voucher_number}`, href: '#' },
];

// Computed properties for validation
const isRejectionReasonValid = computed(() => {
    return rejectionReason.value && rejectionReason.value.trim().length >= 10;
});

const rejectionError = computed(() => {
    if (!rejectionReason.value) {
        return 'Rejection reason is required';
    }
    if (rejectionReason.value.trim().length < 10) {
        return 'Reason must be at least 10 characters long';
    }
    if (rejectionReason.value.trim().length > 1000) {
        return 'Reason must not exceed 1000 characters';
    }
    return null;
});

// =============================================
// DOCUMENT CHECKING
// =============================================

// Get all document types from the voucher (case-insensitive)
const attachedDocumentTypes = computed(() => {
    if (!props.voucher.documents || !Array.isArray(props.voucher.documents)) {
        return [];
    }
    return props.voucher.documents
        .map((doc) => doc.document_type)
        .filter(Boolean)
        .map(type => type.toLowerCase().trim());
});

// Check if a specific document type is attached (case-insensitive)
const hasDocument = (documentType) => {
    if (!documentType) return false;
    const docTypeLower = documentType.toLowerCase().trim();
    return attachedDocumentTypes.value.includes(docTypeLower);
};

// Check if ALL required documents are present
const hasAllRequiredDocuments = () => {
    if (!props.requiredDocuments || props.requiredDocuments.length === 0) {
        return true;
    }

    if (attachedDocumentTypes.value.length === 0) {
        return false;
    }

    const missing = getMissingDocuments();
    return missing.length === 0;
};

// Get missing documents
const getMissingDocuments = () => {
    if (!props.requiredDocuments || props.requiredDocuments.length === 0) {
        return [];
    }

    return props.requiredDocuments.filter((requiredDoc) => {
        return !hasDocument(requiredDoc);
    });
};

// =============================================
// WORKFLOW HISTORY - Load from voucher approvals
// =============================================

const loadWorkflowHistory = () => {
    try {
        // Get approvals from the voucher prop
        const approvals = props.voucher?.approvals || [];
        
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

// Get action severity
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
    };
    return actionMap[action] || 'info';
};

// Get action icon
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
    };
    return iconMap[action] || 'pi-circle';
};

// Get action color
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
    };
    return colorMap[action] || 'text-gray-500 bg-gray-100';
};

// Get action border color
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
    };
    return colorMap[action] || 'border-gray-300';
};

// Format functions
const formatCurrency = (value) => {
    const numValue = Number(value) || 0;
    return new Intl.NumberFormat('en-NG', {
        style: 'currency',
        currency: 'NGN',
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    }).format(numValue);
};

const formatDate = (date) => {
    if (!date) return 'N/A';
    return new Date(date).toLocaleDateString('en-GB', {
        day: '2-digit',
        month: 'short',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
};

const formatDocumentType = (docType) => {
    if (!docType) return 'Unknown';
    const typeMap = {
        approval_form: 'Approval Form',
        invoice: 'Invoice',
        receipt: 'Receipt',
        delivery_note: 'Delivery Note',
        other: 'Additional Document',
        supporting: 'Supporting Document',
        'approval form': 'Approval Form',
        'invoice': 'Invoice',
        'receipt': 'Receipt',
        'delivery note': 'Delivery Note',
    };
    return typeMap[docType.toLowerCase()] || docType.replace(/_/g, ' ').replace(/\b\w/g, (l) => l.toUpperCase());
};

// Go back to list
const goBack = () => {
    router.visit('/internal-audits');
};

// Print Voucher Function
const printVoucher = () => {
    const printUrl = `/vouchers/${props.voucher.id}/print`;
    window.open(printUrl, '_blank');
};

// Action Handlers
const openDocument = (document) => {
    if (!document || !document.file_path) {
        toast.add({
            severity: 'error',
            summary: 'Error',
            detail: 'Document not available',
            life: 3000,
        });
        return;
    }
    currentDocument.value = document;
    documentUrl.value = `/storage/${document.file_path}`;
    showDocumentViewer.value = true;
};

const openRejectModal = () => {
    rejectionReason.value = '';
    showRejectionModal.value = true;
};

const openApproveModal = () => {
    if (!hasAllRequiredDocuments()) {
        const missingDocs = getMissingDocuments();
        const formattedMissing = missingDocs.map(d => formatDocumentType(d)).join(', ');
        toast.add({
            severity: 'warn',
            summary: 'Missing Documents',
            detail: `Cannot approve: Missing ${formattedMissing}`,
            life: 5000,
        });
        return;
    }
    showApprovalModal.value = true;
};

const handleApprove = () => {
    router.post(
        `/internal-audits/${props.voucher.id}/approve`,
        {},
        {
            preserveScroll: true,
            onSuccess: () => {
                showApprovalModal.value = false;
                toast.add({
                    severity: 'success',
                    summary: 'Approved',
                    detail: `Voucher ${props.voucher.voucher_number} approved successfully.`,
                    life: 4000,
                });
                router.reload();
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

const handleReject = async () => {
    if (!isRejectionReasonValid.value) {
        toast.add({
            severity: 'warn',
            summary: 'Validation Error',
            detail: rejectionError.value,
            life: 4000,
        });
        return;
    }

    isSubmitting.value = true;

    try {
        await router.post(
            `/internal-audits/${props.voucher.id}/reject`,
            {
                reason: rejectionReason.value.trim(),
            },
            {
                preserveScroll: true,
                onSuccess: () => {
                    toast.add({
                        severity: 'info',
                        summary: 'Rejected',
                        detail: `Voucher ${props.voucher.voucher_number} has been rejected and returned to originator.`,
                        life: 5000,
                    });
                    showRejectionModal.value = false;
                    rejectionReason.value = '';
                    router.reload();
                },
                onError: (errors) => {
                    const errorMessage =
                        errors.reason ||
                        errors.message ||
                        'Failed to reject voucher.';
                    toast.add({
                        severity: 'error',
                        summary: 'Error',
                        detail: errorMessage,
                        life: 5000,
                    });
                },
            },
        );
    } catch (error) {
        console.error('Rejection error:', error);
        toast.add({
            severity: 'error',
            summary: 'Network Error',
            detail: 'Failed to submit rejection. Please try again.',
            life: 5000,
        });
    } finally {
        isSubmitting.value = false;
    }
};

const closeRejectionModal = () => {
    if (!isSubmitting.value) {
        showRejectionModal.value = false;
        rejectionReason.value = '';
    }
};

const closeApprovalModal = () => {
    showApprovalModal.value = false;
};

const closeDocumentViewer = () => {
    showDocumentViewer.value = false;
    documentUrl.value = '';
    currentDocument.value = null;
};

const onRejectionReasonInput = () => {
    if (rejectionReason.value) {
        rejectionReason.value = rejectionReason.value.trimStart();
    }
};

// Get status severity
const getStatusSeverity = (status) => {
    const statusMap = {
        'approved': 'success',
        'rejected': 'danger',
        'sent_back': 'warning',
        'pending': 'info',
        'submitted': 'info',
        'forwarded': 'info',
        'inspectorate_approved': 'success',
        'ag_approved': 'success',
        'mas_approved': 'success',
        'ec_approved': 'info',
        'closed': 'success',
        'ec_review': 'warning',
        'mas_rejected': 'danger',
        'audit_approved': 'success',
        'fa_approved': 'success',
    };
    return statusMap[status?.toLowerCase()] || 'info';
};

// Get status display name
const getStatusDisplayName = (status) => {
    const names = {
        draft: 'Draft',
        submitted: 'Submitted',
        fa_approved: 'FA Approved',
        audit_approved: 'Audit Approved',
        approved: 'Approved',
        forwarded: 'Forwarded',
        sent_back: 'Sent Back',
        rejected: 'Rejected',
        closed: 'Closed',
        ec_approved: 'EC Approved',
        ag_approved: 'AG Approved',
        mas_approved: 'MAS Approved',
        inspectorate_approved: 'Inspectorate Approved',
        tco_approved: 'TCO Approved',
        ec_review: 'EC Review',
        ag_rejected: 'AG Rejected',
        mas_rejected: 'MAS Rejected',
    };
    return names[status?.toLowerCase()] || status || 'Unknown';
};

// Check if document is required
const isDocumentRequired = (docType) => {
    if (!docType) return false;
    const docTypeLower = docType.toLowerCase().trim();
    return props.requiredDocuments.some(req => req.toLowerCase().trim() === docTypeLower);
};

// Watch for voucher changes to reload workflow
watch(() => props.voucher, () => {
    loadWorkflowHistory();
}, { deep: true, immediate: true });

// Initialize
onMounted(() => {
    loadWorkflowHistory();
});
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head :title="`Audit - ${voucher.voucher_number}`" />
        <Toast />

        <div class="mb-3">
            <Button 
                label="Back to List" 
                icon="pi pi-arrow-left" 
                severity="secondary" 
                text 
                @click="goBack"
            />
        </div>

        <div class="grid">
            <!-- Left Column - Voucher Details -->
            <div class="col-12 lg:col-8">
                <!-- Voucher Header -->
                <Card class="mb-4">
                    <template #title>
                        <div class="flex justify-content-between align-items-center flex-wrap">
                            <div class="flex align-items-center gap-3">
                                <span class="text-xl font-bold">Voucher {{ voucher.voucher_number }}</span>
                                <Tag 
                                    :value="voucher.voucher_type?.toUpperCase() || 'STANDARD'" 
                                    :severity="voucher.voucher_type === 'prepayment' ? 'warning' : 
                                              voucher.voucher_type === 'salary' ? 'success' : 'info'"
                                />
                            </div>
                            <div class="flex align-items-center gap-2">
                                <Tag 
                                    :value="getStatusDisplayName(voucher.status)" 
                                    :severity="getStatusSeverity(voucher.status)"
                                />
                                <Button 
                                    icon="pi pi-print" 
                                    severity="info" 
                                    outlined 
                                    size="small"
                                    @click="printVoucher"
                                    v-tooltip="'Print Voucher'"
                                />
                            </div>
                        </div>
                    </template>
                    <template #content>
                        <div class="grid">
                            <div class="col-12 md:col-4">
                                <strong>MDA:</strong>
                                <p class="mt-1">
                                    {{ voucher.mda?.name || 'N/A' }}
                                </p>
                                <small class="text-500" v-if="voucher.mda?.code">
                                    Code: {{ voucher.mda.code }}
                                </small>
                            </div>
                            <div class="col-12 md:col-4">
                                <strong>Date:</strong>
                                <p class="mt-1">
                                    {{ formatDate(voucher.voucher_date) }}
                                </p>
                            </div>
                            <div class="col-12 md:col-4">
                                <strong>Financial Year:</strong>
                                <p class="mt-1">
                                    {{ voucher.financial_year?.name || 'N/A' }}
                                </p>
                            </div>
                            <div class="col-12">
                                <strong>Payee:</strong>
                                <p class="mt-1">{{ voucher.payee_name || 'N/A' }}</p>
                            </div>
                            <div class="col-12">
                                <strong>Narration:</strong>
                                <p class="mt-1">{{ voucher.narration || 'N/A' }}</p>
                            </div>
                            <div class="col-12">
                                <strong>Total Amount:</strong>
                                <h3 class="text-primary mt-1">
                                    {{ formatCurrency(voucher.total_amount || 0) }}
                                </h3>
                            </div>
                        </div>
                    </template>
                </Card>

                <!-- Line Items -->
                <Card class="mb-4">
                    <template #title>
                        <div class="flex align-items-center gap-2">
                            <i class="pi pi-list text-primary"></i>
                            <span>Line Items</span>
                            <Badge :value="voucher.items?.length || 0" severity="info" />
                        </div>
                    </template>
                    <template #content>
                        <DataTable
                            :value="voucher.items"
                            class="p-datatable-sm"
                            responsiveLayout="scroll"
                            stripedRows
                        >
                            <Column
                                field="description"
                                header="Description"
                                headerStyle="width: 35%"
                            >
                                <template #body="slotProps">
                                    {{ slotProps.data.description }}
                                </template>
                            </Column>
                            <Column
                                field="programme_code"
                                header="Programme"
                                headerStyle="width: 15%"
                            >
                                <template #body="slotProps">
                                    <span v-if="slotProps.data.programme_code">
                                        {{ slotProps.data.programme_code }}
                                        <small class="text-500 block">
                                            {{ slotProps.data.programme_name || '' }}
                                        </small>
                                    </span>
                                    <span v-else>N/A</span>
                                </template>
                            </Column>
                            <Column
                                field="quantity"
                                header="Qty"
                                headerStyle="width: 10%"
                                bodyClass="text-center"
                            >
                                <template #body="slotProps">
                                    {{ slotProps.data.quantity }}
                                </template>
                            </Column>
                            <Column
                                field="unit_price"
                                header="Unit Price"
                                headerStyle="width: 15%"
                                bodyClass="text-right"
                            >
                                <template #body="slotProps">
                                    {{ formatCurrency(slotProps.data.unit_price) }}
                                </template>
                            </Column>
                            <Column
                                field="sub_total"
                                header="Sub Total"
                                headerStyle="width: 20%"
                                bodyClass="text-right font-bold"
                            >
                                <template #body="slotProps">
                                    {{ formatCurrency(slotProps.data.sub_total) }}
                                </template>
                            </Column>
                        </DataTable>

                        <Divider />

                        <div class="flex justify-content-end">
                            <div class="text-right">
                                <h3 class="text-primary m-0">
                                    Total: {{ formatCurrency(voucher.total_amount || 0) }}
                                </h3>
                            </div>
                        </div>
                    </template>
                </Card>
            </div>

            <!-- Right Column - Actions & Documents -->
            <div class="col-12 lg:col-4">
                <!-- Action Panel -->
                <Card class="mb-4">
                    <template #title>Audit Actions</template>
                    <template #content>
                        <div class="flex flex-column gap-3">
                            <Button
                                label="Approve Voucher"
                                icon="pi pi-check"
                                severity="success"
                                :disabled="!hasAllRequiredDocuments()"
                                @click="openApproveModal"
                            />
                            <Button
                                label="Reject Voucher"
                                icon="pi pi-times"
                                severity="danger"
                                outlined
                                @click="openRejectModal"
                            />
                        </div>

                        <Divider />

                        <!-- Document Status -->
                        <div class="mt-4">
                            <h4>Document Status</h4>
                            
                            <!-- Show document status for each required document -->
                            <div v-if="requiredDocuments && requiredDocuments.length > 0">
                                <div 
                                    v-for="docType in requiredDocuments" 
                                    :key="docType"
                                    class="align-items-center justify-content-between flex mt-2"
                                >
                                    <span class="text-capitalize">{{ formatDocumentType(docType) }}</span>
                                    <Tag 
                                        :value="hasDocument(docType) ? 'Attached' : 'Missing'"
                                        :severity="hasDocument(docType) ? 'success' : 'danger'"
                                    />
                                </div>
                            </div>
                            <div v-else class="mt-2">
                                <small class="text-500">No documents required for approval</small>
                            </div>

                            <!-- Status message -->
                            <div class="mt-3">
                                <div v-if="!hasAllRequiredDocuments()" class="p-2 border-round bg-red-50">
                                    <small class="p-error">
                                        <i class="pi pi-exclamation-circle mr-1"></i>
                                        Missing required documents: {{ getMissingDocuments().map(d => formatDocumentType(d)).join(', ') }}
                                    </small>
                                </div>
                                <div v-else-if="requiredDocuments && requiredDocuments.length > 0" class="p-2 border-round bg-green-50">
                                    <small class="p-success">
                                        <i class="pi pi-check-circle mr-1"></i>
                                        All required documents are attached
                                    </small>
                                </div>
                            </div>

                            <!-- Show all attached documents -->
                            <div v-if="voucher.documents && voucher.documents.length > 0" class="mt-3">
                                <Divider />
                                <h5>Attached Documents ({{ voucher.documents.length }})</h5>
                                <div 
                                    v-for="doc in voucher.documents" 
                                    :key="doc.id"
                                    class="align-items-center flex gap-2 mt-1"
                                >
                                    <i class="pi pi-file" :class="isDocumentRequired(doc.document_type) ? 'text-green-500' : 'text-500'"></i>
                                    <span class="text-capitalize">{{ formatDocumentType(doc.document_type) }}</span>
                                    <small class="text-500">- {{ doc.file_name }}</small>
                                    <Tag 
                                        v-if="isDocumentRequired(doc.document_type)" 
                                        value="Required" 
                                        severity="info" 
                                        size="small"
                                    />
                                </div>
                            </div>
                        </div>

                        <!-- Bank Activity (if assigned) -->
                        <div v-if="voucher.bank_activity" class="mt-4">
                            <Divider />
                            <h4>Bank Details</h4>
                            <div class="flex flex-column gap-1">
                                <div class="flex justify-content-between">
                                    <span class="text-500">Bank:</span>
                                    <span class="font-medium">{{ voucher.bank_activity.bank_name || 'N/A' }}</span>
                                </div>
                                <div class="flex justify-content-between">
                                    <span class="text-500">Account:</span>
                                    <span class="font-medium">{{ voucher.bank_activity.account_number || 'N/A' }}</span>
                                </div>
                                <div class="flex justify-content-between" v-if="voucher.bank_activity.tag">
                                    <span class="text-500">Tag:</span>
                                    <span class="font-medium">{{ voucher.bank_activity.tag }}</span>
                                </div>
                            </div>
                        </div>

                        <!-- Creator Info -->
                        <div v-if="voucher.creator" class="mt-4">
                            <Divider />
                            <h4>Created By</h4>
                            <div class="flex flex-column gap-1">
                                <div class="flex justify-content-between">
                                    <span class="text-500">Name:</span>
                                    <span class="font-medium">{{ voucher.creator.name || 'N/A' }}</span>
                                </div>
                                <div class="flex justify-content-between" v-if="voucher.creator.email">
                                    <span class="text-500">Email:</span>
                                    <span class="font-medium">{{ voucher.creator.email }}</span>
                                </div>
                                <div class="flex justify-content-between">
                                    <span class="text-500">Created:</span>
                                    <span class="font-medium">{{ formatDate(voucher.created_at) }}</span>
                                </div>
                            </div>
                        </div>
                    </template>
                </Card>

                <!-- Documents Panel -->
                <Card class="mb-4">
                    <template #title>
                        <div class="flex justify-content-between align-items-center">
                            <span>Supporting Documents</span>
                            <Tag :value="voucher.documents?.length || 0" severity="info" />
                        </div>
                    </template>
                    <template #content>
                        <div
                            v-if="voucher.documents && voucher.documents.length"
                            class="flex flex-column gap-2"
                        >
                            <div
                                v-for="document in voucher.documents"
                                :key="document.id"
                                class="border-round flex align-items-center justify-content-between p-2"
                                :class="{
                                    'bg-green-50': isDocumentRequired(document.document_type),
                                    'surface-50': !isDocumentRequired(document.document_type),
                                }"
                            >
                                <div class="flex align-items-center">
                                    <i class="pi pi-file mr-2" :class="isDocumentRequired(document.document_type) ? 'text-green-500' : 'text-500'"></i>
                                    <div class="flex flex-column">
                                        <span class="text-capitalize font-medium">
                                            {{ formatDocumentType(document.document_type) }}
                                        </span>
                                        <small class="text-500">
                                            {{ document.file_name || 'Unknown' }}
                                        </small>
                                    </div>
                                </div>
                                <Button
                                    icon="pi pi-eye"
                                    severity="info"
                                    text
                                    size="small"
                                    @click="openDocument(document)"
                                    v-tooltip="'View Document'"
                                />
                            </div>
                        </div>
                        <div v-else class="p-4 text-center">
                            <i class="pi pi-inbox text-500 text-4xl"></i>
                            <p class="text-500 mt-2">No documents attached</p>
                        </div>
                    </template>
                </Card>

                <!-- ============================================= -->
                <!-- APPROVAL WORKFLOW TIMELINE - ADDED -->
                <!-- ============================================= -->
                <Card class="timeline-card">
                    <template #title>
                        <div class="flex align-items-center justify-content-between">
                            <div class="flex align-items-center gap-2">
                                <i class="pi pi-sitemap text-primary"></i>
                                <span>Approval Workflow</span>
                            </div>
                            <Badge :value="workflowHistory.length" severity="info" />
                        </div>
                    </template>
                    <template #content>
                        <div class="workflow-timeline" style="max-height: 400px; overflow-y: auto;">
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
                                                {{ formatDate(slotProps.item.action_at || slotProps.item.created_at) }}
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
                    </template>
                </Card>
            </div>
        </div>

        <!-- Rejection Modal -->
        <Dialog
            v-model:visible="showRejectionModal"
            :style="{ width: '500px' }"
            header="Reject Voucher"
            :modal="true"
            :closable="!isSubmitting"
            @update:visible="closeRejectionModal"
        >
            <div class="p-fluid">
                <div class="mb-4">
                    <i class="pi pi-exclamation-triangle mr-2 text-red-500"></i>
                    <span class="font-semibold">You are about to reject this voucher.</span>
                </div>

                <p class="mb-3">
                    Voucher
                    <strong class="text-900">{{ voucher.voucher_number }}</strong>
                    will be returned to
                    <strong class="text-900">{{ voucher.creator?.name || 'the originator' }}</strong>
                    for correction.
                </p>

                <div class="field">
                    <label for="reject_reason" class="font-semibold">
                        Reason for Rejection *
                        <span class="text-500 ml-2 text-sm">(Minimum 10 characters)</span>
                    </label>
                    <Textarea
                        id="reject_reason"
                        v-model="rejectionReason"
                        rows="5"
                        placeholder="Please provide a detailed and constructive reason for rejection..."
                        :class="{
                            'p-invalid': rejectionError,
                            'p-valid': isRejectionReasonValid,
                        }"
                        @input="onRejectionReasonInput"
                        :disabled="isSubmitting"
                        class="w-full"
                        autoResize
                    />
                    <div class="flex justify-content-between mt-2">
                        <small v-if="rejectionError" class="p-error">
                            <i class="pi pi-exclamation-circle mr-1"></i>
                            {{ rejectionError }}
                        </small>
                        <small v-else-if="isRejectionReasonValid" class="p-success">
                            <i class="pi pi-check-circle mr-1"></i>
                            Reason looks good
                        </small>
                        <small v-else class="text-500">
                            Please provide a detailed reason
                        </small>
                        <small class="text-500">
                            {{ rejectionReason?.length || 0 }}/1000
                        </small>
                    </div>
                </div>

                <div class="surface-100 border-round mt-4 p-3">
                    <h5 class="mt-0 mb-2">What happens next?</h5>
                    <ul class="m-0 text-sm">
                        <li>Voucher will be returned to the originator</li>
                        <li>Originator will receive notification with your reason</li>
                        <li>Voucher status will change to "Rejected"</li>
                        <li>Originator can make corrections and resubmit</li>
                    </ul>
                </div>
            </div>

            <template #footer>
                <Button
                    label="Cancel"
                    icon="pi pi-times"
                    @click="closeRejectionModal"
                    text
                    :disabled="isSubmitting"
                />
                <Button
                    label="Confirm Rejection"
                    icon="pi pi-ban"
                    severity="danger"
                    @click="handleReject"
                    :disabled="!isRejectionReasonValid || isSubmitting"
                    :loading="isSubmitting"
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
            <div class="flex align-items-center">
                <i class="pi pi-exclamation-circle text-primary mr-3 text-2xl"></i>
                <span>
                    Approve Voucher <strong>{{ voucher.voucher_number }}</strong>? 
                    This will move it to the next stage.
                </span>
            </div>

            <template #footer>
                <Button
                    label="Cancel"
                    icon="pi pi-times"
                    @click="closeApprovalModal"
                    text
                />
                <Button
                    label="Approve"
                    icon="pi pi-check-circle"
                    severity="success"
                    @click="handleApprove"
                />
            </template>
        </Dialog>

        <!-- Document Viewer -->
        <Dialog
            v-model:visible="showDocumentViewer"
            :header="`Document Viewer - ${currentDocument?.file_name || 'Document'}`"
            :style="{ width: '80vw', height: '90vh' }"
            :modal="true"
            maximizable
            @update:visible="closeDocumentViewer"
        >
            <div style="height: 70vh">
                <iframe
                    v-if="documentUrl"
                    :src="documentUrl"
                    frameborder="0"
                    width="100%"
                    height="100%"
                ></iframe>
                <div v-else class="flex align-items-center justify-content-center h-full">
                    <p class="text-500">No document to display</p>
                </div>
            </div>

            <template #footer>
                <Button label="Close" icon="pi pi-times" @click="closeDocumentViewer" text />
            </template>
        </Dialog>
    </AppLayout>
</template>

<style scoped>
.text-capitalize {
    text-transform: capitalize;
}

.p-success {
    color: var(--green-600);
}

:deep(.p-invalid) {
    border-color: var(--red-500) !important;
}

:deep(.p-valid) {
    border-color: var(--green-500) !important;
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

/* Workflow Timeline Styles */
.timeline-card :deep(.p-card) {
    border-radius: 1rem;
    overflow: hidden;
}

.timeline-card :deep(.p-card-header) {
    background: #f8fafc;
    border-bottom: 1px solid #e2e8f0;
    padding: 1rem 1.25rem;
}

.workflow-card-item {
    background: white;
    transition: all 0.2s ease;
}

.workflow-card-item:hover {
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
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
</style>