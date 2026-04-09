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
import { useToast } from 'primevue/usetoast';
import { computed, ref } from 'vue';

const toast = useToast();

// Props
const props = defineProps({
    voucher: {
        type: Object,
        required: true,
    },
    requiredDocuments: {
        type: Array,
        default: () => ['invoice', 'receipt', 'delivery_note', 'approval_form'],
    },
});

// State
const showRejectionModal = ref(false);
const showApprovalModal = ref(false);
const showDocumentViewer = ref(false);
const rejectionReason = ref('');
const documentUrl = ref('');
const isSubmitting = ref(false);

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

// Document checking functions
const hasAllRequiredDocuments = () => {
    if (!props.voucher.documents || !Array.isArray(props.voucher.documents)) {
        return false;
    }

    const attachedDocTypes = props.voucher.documents
        .map((doc) => doc.document_type)
        .filter(Boolean);
    const requiredDocs = props.requiredDocuments || [];

    return requiredDocs.every((docType) => attachedDocTypes.includes(docType));
};

const getMissingDocuments = () => {
    if (!props.voucher.documents || !Array.isArray(props.voucher.documents)) {
        return props.requiredDocuments || [];
    }

    const attachedDocTypes = props.voucher.documents
        .map((doc) => doc.document_type)
        .filter(Boolean);
    const requiredDocs = props.requiredDocuments || [];

    return requiredDocs.filter(
        (docType) => !attachedDocTypes.includes(docType),
    );
};

// Format amount with proper currency
const formatCurrency = (value) => {
    const numValue = Number(value) || 0;
    return new Intl.NumberFormat('en-NG', {
        style: 'currency',
        currency: 'NGN',
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    }).format(numValue);
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
        toast.add({
            severity: 'warn',
            summary: 'Missing Documents',
            detail: `Cannot approve: Missing ${missingDocs.join(', ')}`,
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

// const handleReject = async () => {
//     if (!isRejectionReasonValid.value) {
//         toast.add({
//             severity: 'warn',
//             summary: 'Validation Error',
//             detail: rejectionError.value,
//             life: 4000,
//         });
//         return;
//     }

//     isSubmitting.value = true;

//     try {
//         await router.post(
//             `/internal-audits/${props.voucher.id}/reject`,
//             {
//                 reason: rejectionReason.value.trim(),
//             },
//             {
//                 preserveScroll: true,
//                 onSuccess: () => {
//                     toast.add({
//                         severity: 'info',
//                         summary: 'Rejected',
//                         detail: `Voucher ${props.voucher.voucher_number} has been rejected and returned to originator.`,
//                         life: 5000,
//                     });
//                     showRejectionModal.value = false;
//                     rejectionReason.value = '';
//                 },
//                 onError: (errors) => {
//                     const errorMessage =
//                         errors.reason ||
//                         errors.message ||
//                         'Failed to reject voucher.';
//                     toast.add({
//                         severity: 'error',
//                         summary: 'Error',
//                         detail: errorMessage,
//                         life: 5000,
//                     });
//                 },
//             },
//         );
//     } catch (error) {
//         toast.add({
//             severity: 'error',
//             summary: 'Network Error',
//             detail: 'Failed to submit rejection. Please try again.',
//             life: 5000,
//         });
//     } finally {
//         isSubmitting.value = false;
//     }
// };

// Modal close handlers

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

    // Debug: log what we're sending
    console.log('Voucher ID:', props.voucher.id);
    console.log('Full URL:', `/internal-audits/${props.voucher.id}/reject`);
    console.log('Reason:', rejectionReason.value.trim());

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
};

// Real-time validation for textarea
const onRejectionReasonInput = () => {
    // Auto-trim and validate as user types
    if (rejectionReason.value) {
        rejectionReason.value = rejectionReason.value.trimStart();
    }
};
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head :title="`Audit - ${voucher.voucher_number}`" />
        <Toast />

        <div class="grid">
            <!-- Left Column - Voucher Details -->
            <div class="col-8">
                <!-- Voucher Header -->
                <Card class="mb-4">
                    <template #title>
                        <div
                            class="justify-content-between align-items-center flex"
                        >
                            <span>Voucher {{ voucher.voucher_number }}</span>
                            <div class="align-items-center flex gap-2">
                                <Tag
                                    :value="voucher.voucher_type"
                                    :severity="
                                        voucher.voucher_type === 'PREPAYMENT'
                                            ? 'warning'
                                            : 'info'
                                    "
                                />
                                <Tag
                                    :value="voucher.status"
                                    severity="secondary"
                                />
                            </div>
                        </div>
                    </template>
                    <template #content>
                        <div class="grid">
                            <div class="col-4">
                                <strong>MDA:</strong>
                                <p class="mt-1">
                                    {{ voucher.mda?.name || 'N/A' }}
                                </p>
                            </div>
                            <div class="col-4">
                                <strong>Date:</strong>
                                <p class="mt-1">
                                    {{
                                        new Date(
                                            voucher.voucher_date,
                                        ).toLocaleDateString()
                                    }}
                                </p>
                            </div>
                            <div class="col-4">
                                <strong>Financial Year:</strong>
                                <p class="mt-1">
                                    {{ voucher.financial_year?.name || 'N/A' }}
                                </p>
                            </div>
                            <div class="col-12 mt-3">
                                <strong>Narration:</strong>
                                <p class="mt-1">{{ voucher.narration }}</p>
                            </div>
                        </div>
                    </template>
                </Card>

                <!-- Line Items -->
                <Card class="mb-4">
                    <template #title>Line Items</template>
                    <template #content>
                        <DataTable
                            :value="voucher.items"
                            class="p-datatable-sm"
                            responsiveLayout="scroll"
                        >
                            <Column
                                field="description"
                                header="Description"
                                headerStyle="width: 50%"
                            >
                                <template #body="slotProps">
                                    {{ slotProps.data.description }}
                                </template>
                            </Column>
                            <Column
                                field="quantity"
                                header="Qty"
                                headerStyle="width: 15%"
                                bodyClass="text-left"
                            >
                                <template #body="slotProps">
                                    {{ slotProps.data.quantity }}
                                </template>
                            </Column>
                            <Column
                                field="unit_price"
                                header="Unit Price"
                                headerStyle="width: 15%"
                                bodyClass="text-left"
                            >
                                <template #body="slotProps">
                                    {{
                                        formatCurrency(
                                            slotProps.data.unit_price,
                                        )
                                    }}
                                </template>
                            </Column>
                            <Column
                                field="sub_total"
                                header="Sub Total"
                                headerStyle="width: 20%"
                                bodyClass="text-left font-bold"
                            >
                                <template #body="slotProps">
                                    {{
                                        formatCurrency(slotProps.data.sub_total)
                                    }}
                                </template>
                            </Column>
                        </DataTable>

                        <Divider />

                        <div class="justify-content-end flex">
                            <div class="text-right">
                                <h3 class="text-primary m-0">
                                    Total:
                                    {{ formatCurrency(voucher.total_amount) }}
                                </h3>
                            </div>
                        </div>
                    </template>
                </Card>

                <!-- Approval History -->
                <Card v-if="voucher.approvals && voucher.approvals.length">
                    <template #title>Approval History</template>
                    <template #content>
                        <div
                            v-for="approval in voucher.approvals"
                            :key="approval.id"
                            class="border-round surface-50 mb-3 p-3"
                        >
                            <div
                                class="justify-content-between align-items-center flex"
                            >
                                <div>
                                    <strong>{{
                                        approval.approval_role
                                    }}</strong>
                                    -
                                    <Tag
                                        :value="approval.action"
                                        :severity="
                                            approval.action === 'Approved'
                                                ? 'success'
                                                : approval.action === 'Declined'
                                                  ? 'danger'
                                                  : 'warning'
                                        "
                                    />
                                </div>
                                <small class="text-500">{{
                                    new Date(
                                        approval.created_at,
                                    ).toLocaleString()
                                }}</small>
                            </div>
                            <div class="mt-2" v-if="approval.comment">
                                <strong>Comment:</strong> {{ approval.comment }}
                            </div>
                            <div class="mt-1" v-if="approval.approver">
                                <strong>By:</strong>
                                {{ approval.approver.name }}
                            </div>
                        </div>
                    </template>
                </Card>
            </div>

            <!-- Right Column - Actions & Documents -->
            <div class="col-4">
                <!-- Action Panel -->
                <Card class="mb-4">
                    <template #title>Audit Actions</template>
                    <template #content>
                        <div class="flex-column flex gap-3">
                            <Button
                                label="Print Voucher"
                                icon="pi pi-print"
                                severity="info"
                                outlined
                                @click="printVoucher"
                            />
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

                        <!-- Document Status -->
                        <div class="mt-4">
                            <h4>Document Status</h4>
                            <div class="mt-2 flex flex-wrap gap-2">
                                <Tag
                                    v-for="docType in requiredDocuments"
                                    :key="docType"
                                    :value="docType"
                                    :severity="
                                        voucher.documents?.some(
                                            (d) => d.document_type === docType,
                                        )
                                            ? 'success'
                                            : 'danger'
                                    "
                                />
                            </div>
                            <div v-if="!hasAllRequiredDocuments()" class="mt-2">
                                <small class="p-error">
                                    Missing:
                                    {{ getMissingDocuments().join(', ') }}
                                </small>
                            </div>
                        </div>
                    </template>
                </Card>

                <!-- Documents Panel -->
                <Card>
                    <template #title>Supporting Documents</template>
                    <template #content>
                        <div
                            v-if="voucher.documents && voucher.documents.length"
                            class="flex-column flex gap-2"
                        >
                            <div
                                v-for="document in voucher.documents"
                                :key="document.id"
                                class="align-items-center justify-content-between border-round flex p-2"
                                :class="{
                                    'bg-green-50': requiredDocuments.includes(
                                        document.document_type,
                                    ),
                                }"
                            >
                                <div class="align-items-center flex">
                                    <i class="pi pi-file mr-2"></i>
                                    <div class="flex-column flex">
                                        <span
                                            class="text-capitalize font-medium"
                                            >{{
                                                document.document_type ||
                                                'Unknown'
                                            }}</span
                                        >
                                        <small class="text-500">{{
                                            document.file_name
                                        }}</small>
                                    </div>
                                </div>
                                <Button
                                    icon="pi pi-eye"
                                    severity="info"
                                    text
                                    @click="openDocument(document)"
                                />
                            </div>
                        </div>
                        <div v-else class="p-4 text-center">
                            <i class="pi pi-inbox text-500 text-4xl"></i>
                            <p class="text-500 mt-2">No documents attached</p>
                        </div>
                    </template>
                </Card>
            </div>
        </div>

        <!-- Rejection Modal - IMPROVED -->
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
                    <span class="font-semibold"
                        >You are about to reject this voucher.</span
                    >
                </div>

                <p class="mb-3">
                    Voucher
                    <strong class="text-900">{{
                        voucher.voucher_number
                    }}</strong>
                    will be returned to
                    <strong class="text-900">{{
                        voucher.creator?.name || 'the originator'
                    }}</strong>
                    for correction.
                </p>

                <div class="field">
                    <label for="reject_reason" class="font-semibold">
                        Reason for Rejection *
                        <span class="text-500 ml-2 text-sm">
                            (Minimum 10 characters)
                        </span>
                    </label>
                    <Textarea
                        id="reject_reason"
                        v-model="rejectionReason"
                        rows="5"
                        placeholder="Please provide a detailed and constructive reason for rejection. This will help the originator understand what needs to be corrected..."
                        :class="{
                            'p-invalid': rejectionError,
                            'p-valid': isRejectionReasonValid,
                        }"
                        @input="onRejectionReasonInput"
                        :disabled="isSubmitting"
                        class="w-full"
                        autoResize
                    />
                    <div class="justify-content-between mt-2 flex">
                        <small v-if="rejectionError" class="p-error">
                            <i class="pi pi-exclamation-circle mr-1"></i>
                            {{ rejectionError }}
                        </small>
                        <small
                            v-else-if="isRejectionReasonValid"
                            class="p-success"
                        >
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
                        <li>
                            Originator will receive notification with your
                            reason
                        </li>
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
            <div class="align-items-center flex">
                <i
                    class="pi pi-exclamation-circle text-primary mr-3 text-2xl"
                ></i>
                <span>
                    Approve Voucher <strong>{{ voucher.voucher_number }}</strong
                    >? This will move it to payment processing.
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
            header="Document Viewer"
            :style="{ width: '80vw' }"
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
                <div
                    v-else
                    class="align-items-center justify-content-center flex h-full"
                >
                    <p class="text-500">No document to display</p>
                </div>
            </div>
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
</style>
`
