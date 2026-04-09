<template>
    <AppLayout :breadcrumbs="breadcrumbs">

        <Head title="Audit Queue" />
        <Toast />

        <!-- Stats Cards Section -->
        <div class="mb-4 grid">
            <div v-for="stat in statsData" :key="stat.title" class="col-12 md:col-3">
                <Card class="h-full">
                    <template #content>
                        <div class="align-items-center flex">
                            <div class="mr-3">
                                <i :class="[stat.icon, stat.color, 'text-2xl']"></i>
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
                <div class="align-items-center justify-content-between flex">
                    <div class="align-items-center flex gap-2">
                        <span>Audit Queue</span>
                        <Badge :value="totalRecords" severity="info" />
                    </div>
                    <Button label="Refresh" icon="pi pi-refresh" severity="secondary" outlined @click="refreshData"
                        :loading="false" />
                </div>
            </template>

            <template #content>
                <DataTable v-model:filters="filters" :value="vouchers" dataKey="id" stripedRows
                    responsiveLayout="scroll" class="p-datatable-sm" :emptyMessage="'No vouchers found.'"
                    :paginator="true" :rowsPerPageOptions="[5, 10, 20, 50, 100]" :loading="loading"
                    :rows="lazyParams.rows" :totalRecords="totalRecords" @page="onPage" removableSort
                    :globalFilterFields="['voucher_number', 'voucher_type', 'voucher_date', 'mda.name', 'narration', 'status']"
                    lazy size="small"
                    paginatorTemplate="RowsPerPageDropdown FirstPageLink PrevPageLink CurrentPageReport NextPageLink LastPageLink"
                    currentPageReportTemplate="{first} to {last} of {totalRecords}"
                    >

                    <template #header>
                        <div class="flex justify-end">
                            <IconField>
                                <InputIcon>
                                    <i class="pi pi-search" />
                                </InputIcon>
                                <InputText v-model="searchQuery" placeholder="Keyword Search" />
                            </IconField>
                        </div>
                    </template>
                    <!-- Your existing columns remain the same -->
                    <Column field="voucher_number" header="Voucher #" headerStyle="width: 12%">
                        <template #body="slotProps">
                            <span class="text-900 font-medium">
                                {{ slotProps.data.voucher_number || 'N/A' }}
                            </span>
                        </template>
                    </Column>

                    <Column field="voucher_type" header="Type" headerStyle="width: 8%">
                        <template #body="slotProps">
                            <Tag :value="slotProps.data.voucher_type?.toUpperCase() ||
                                'STANDARD'
                                " :severity="slotProps.data.voucher_type === 'prepayment'
                                        ? 'warning'
                                        : 'info'
                                    " />
                        </template>
                    </Column>

                    <Column field="voucher_date" header="Date" headerStyle="width: 10%">
                        <template #body="slotProps">
                            <span class="text-900">
                                {{
                                    slotProps.data.voucher_date
                                        ? new Date(
                                            slotProps.data.voucher_date,
                                        ).toLocaleDateString()
                                        : 'N/A'
                                }}
                            </span>
                        </template>
                    </Column>

                    <Column field="mda.name" header="MDA" headerStyle="width: 15%">
                        <template #body="slotProps">
                            <div class="flex-column flex">
                                <span class="text-900 font-medium">
                                    {{ slotProps.data.mda?.name || 'N/A' }}
                                </span>
                                <small class="text-500">
                                    {{ slotProps.data.mda?.initials || '' }}
                                </small>
                            </div>
                        </template>
                    </Column>

                    <Column field="narration" header="Description" headerStyle="width: 20%">
                        <template #body="slotProps">
                            <span class="text-900 line-clamp-2" :title="slotProps.data.narration">
                                {{
                                    slotProps.data.narration ||
                                    'No description provided'
                                }}
                            </span>
                        </template>
                    </Column>

                    <Column field="total_amount" header="Amount" headerStyle="width: 12%"
                        bodyClass="font-bold text-right">
                        <template #body="slotProps">
                            <span class="text-900">
                                {{
                                    formatCurrency(
                                        slotProps.data.total_amount || 0,
                                    )
                                }}
                            </span>
                        </template>
                    </Column>

                    <Column header="Documents" headerStyle="width: 10%">
                        <template #body="slotProps">
                            <div class="flex-column align-items-start flex gap-1">
                                <Button icon="pi pi-file" :label="`${getDocumentCount(slotProps.data)}`" severity="info"
                                    text size="small" @click="openDocumentsModal(slotProps.data)" />
                                <Tag v-if="
                                    hasAllRequiredDocuments(slotProps.data)
                                " value="Complete" severity="success" size="small" />
                                <Tag v-else :value="`Missing ${getMissingDocuments(slotProps.data).length}`"
                                    severity="warning" size="small" />
                            </div>
                        </template>
                    </Column>

                    <Column header="Actions" headerStyle="width: 13%" bodyClass="text-center">
                        <template #body="slotProps">
                            <div class="justify-content-center flex gap-1">
                                <Button icon="pi pi-print" severity="info" size="small" text v-tooltip="'Print Voucher'"
                                    @click="printVoucher(slotProps.data)" />
                                <Button icon="pi pi-eye" severity="info" size="small" text v-tooltip="'View Details'"
                                    @click="viewVoucherDetails(slotProps.data)" />
                                <Button icon="pi pi-check" severity="success" size="small" text :disabled="!hasAllRequiredDocuments(slotProps.data)
                                    " v-tooltip="!hasAllRequiredDocuments(slotProps.data)
                                            ? `Missing: ${getMissingDocuments(
                                                slotProps.data,
                                            )
                                                .map((d) =>
                                                    formatDocumentType(d),
                                                )
                                                .join(', ')}`
                                            : 'Approve Voucher'
                                        " @click="openApproveModal(slotProps.data)" />
                                <Button icon="pi pi-times" severity="danger" size="small" text
                                    v-tooltip="'Reject Voucher'" @click="openRejectModal(slotProps.data)" />
                            </div>
                        </template>
                    </Column>
                </DataTable>
            </template>
        </Card>

        <!-- Documents Modal -->
        <Dialog v-model:visible="showDocumentsModal" :header="`Documents - ${currentVoucher?.voucher_number || 'N/A'}`"
            :style="{ width: '600px' }" :modal="true" @update:visible="closeDocumentsModal">
            <div class="p-fluid" v-if="currentVoucher">
                <div class="mb-4">
                    <h4>Required Documents:</h4>
                    <div class="mt-2 flex flex-wrap gap-2">
                        <Tag v-for="docType in requiredDocuments" :key="docType" :value="formatDocumentType(docType)"
                            :severity="currentVoucher.documents?.some(
                                (d) => d.document_type === docType,
                            )
                                    ? 'success'
                                    : 'danger'
                                " />
                    </div>
                </div>

                <div v-if="
                    currentVoucher.documents &&
                    currentVoucher.documents.length
                ">
                    <h4>Attached Documents:</h4>
                    <div class="mt-2">
                        <div v-for="document in currentVoucher.documents" :key="document.id"
                            class="align-items-center justify-content-between border-round surface-50 mb-2 flex p-2"
                            :class="{
                                'bg-green-50': requiredDocuments.includes(
                                    document.document_type,
                                ),
                            }">
                            <div class="align-items-center flex">
                                <i class="pi pi-file mr-2"></i>
                                <div class="flex-column flex">
                                    <span class="font-medium">
                                        {{
                                            formatDocumentType(
                                                document.document_type,
                                            )
                                        }}
                                    </span>
                                    <small class="text-500">
                                        {{
                                            document.file_name || 'No filename'
                                        }}
                                    </small>
                                </div>
                            </div>
                            <Button icon="pi pi-eye" severity="info" text @click="openDocument(document)"
                                v-tooltip="'View Document'" />
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
        <Dialog v-model:visible="showRejectionModal" :style="{ width: '500px' }" header="Reject Voucher" :modal="true"
            :closable="false" @update:visible="closeRejectionModal">
            <div class="flex-column flex gap-3" v-if="currentVoucher">
                <div class="align-items-center text-color-secondary flex gap-2">
                    <i class="pi pi-exclamation-triangle text-red-500"></i>
                    <span>
                        Voucher
                        <strong class="text-900">{{
                            currentVoucher.voucher_number
                            }}</strong>
                        will be returned to the originator.
                    </span>
                </div>

                <div class="border-round surface-border border-1 bg-gray-50 p-3">
                    <p class="m-0 text-sm">
                        Please state the
                        <strong class="text-red-500">mandatory reason</strong>
                        for rejection.
                    </p>
                </div>

                <div class="field">
                    <div class="align-items-center justify-content-between mb-2 flex">
                        <label for="reject_reason" class="text-color font-medium">
                            Reason for Rejection
                        </label>
                        <span class="text-sm text-red-500">Required *</span>
                    </div>

                    <Textarea id="reject_reason" v-model="rejectionReason" rows="4"
                        placeholder="Provide detailed reason for rejection..." :class="{
                            'p-invalid': !rejectionReason && rejectionTouched,
                        }" @blur="rejectionTouched = true" class="w-full" autoResize />

                    <div class="justify-content-between mt-1 flex">
                        <small class="text-color-secondary">
                            <i class="pi pi-info-circle mr-1"></i>
                            Minimum 10 characters required
                        </small>
                        <small :class="rejectionReason.length < 10
                                ? 'text-red-500'
                                : 'text-green-500'
                            ">
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
                <Button label="Cancel" icon="pi pi-times" @click="closeRejectionModal" text class="p-button-text" />
                <Button label="Confirm Rejection" icon="pi pi-ban" severity="danger" @click="handleReject"
                    :disabled="!rejectionReason || rejectionReason.length < 10" class="p-button-danger" />
            </template>
        </Dialog>

        <!-- Approval Modal -->
        <Dialog v-model:visible="showApprovalModal" :style="{ width: '400px' }" header="Confirm Approval" :modal="true"
            @update:visible="closeApprovalModal">
            <div class="align-items-center flex" v-if="currentVoucher">
                <i class="pi pi-exclamation-circle text-primary mr-3 text-2xl"></i>
                <span>
                    Approve Voucher
                    <strong>{{ currentVoucher.voucher_number }}</strong>? This will move it to payment processing.
                </span>
            </div>

            <template #footer>
                <Button label="Cancel" icon="pi pi-times" @click="closeApprovalModal" text />
                <Button label="Approve" icon="pi pi-check-circle" severity="success" @click="handleApprove" />
            </template>
        </Dialog>

        <!-- Enhanced Document Viewer -->
        <Dialog v-model:visible="showDocumentViewer"
            :header="`Document Viewer - ${currentDocument?.file_name || 'Document'}`"
            :style="{ width: '90vw', height: '95vh' }" :modal="true" maximizable @update:visible="closeDocumentViewer">
            <div class="flex-column flex h-full">
                <!-- Loading State -->
                <div v-if="loadingDocument" class="align-items-center justify-content-center flex h-full">
                    <i class="pi pi-spin pi-spinner text-primary text-4xl"></i>
                    <span class="ml-2">Loading document...</span>
                </div>

                <!-- Error State -->
                <div v-else-if="documentError"
                    class="align-items-center justify-content-center flex-column flex h-full">
                    <i class="pi pi-exclamation-triangle text-4xl text-red-500"></i>
                    <p class="text-500 mt-2">{{ documentError }}</p>
                    <Button label="Try Again" icon="pi pi-refresh" @click="loadDocument(currentDocument)"
                        class="mt-3" />
                </div>

                <!-- Document Content -->
                <div v-else-if="documentUrl" class="flex-column flex h-full">
                    <div class="justify-content-between align-items-center mb-3 flex">
                        <span class="font-bold">{{
                            currentDocument?.file_name
                            }}</span>
                        <div class="flex gap-2">
                            <Button icon="pi pi-download" label="Download" @click="downloadDocument(currentDocument)"
                                severity="secondary" size="small" />
                            <Button icon="pi pi-external-link" label="Open in New Tab"
                                @click="openInNewTab(currentDocument)" severity="help" size="small" />
                        </div>
                    </div>

                    <!-- Enhanced iframe with better PDF handling -->
                    <div class="surface-border border-round flex-1 border-1">
                        <iframe :src="documentUrl" frameborder="0" width="100%" height="100%" @load="onDocumentLoad"
                            @error="onDocumentError" class="border-round"></iframe>
                    </div>
                </div>

                <!-- No Document State -->
                <div v-else class="align-items-center justify-content-center flex h-full">
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
import axios from 'axios';


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
        default: () => ['invoice', 'receipt', 'delivery_note', 'approval_form'],
    },
});

// State
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

// Stats data for cards
const statsData = computed(() => [
    {
        title: 'Pending Review',
        value: props.stats.pending_count || 0,
        icon: 'pi pi-clock',
        color: 'text-blue-500',
    },
    {
        title: 'Approved Today',
        value: props.stats.approved_today || 0,
        icon: 'pi pi-check-circle',
        color: 'text-green-500',
    },
    {
        title: 'Rejected Today',
        value: props.stats.rejected_today || 0,
        icon: 'pi pi-times-circle',
        color: 'text-red-500',
    },
    {
        title: 'Total Processed',
        value: props.stats.total_processed || 0,
        icon: 'pi pi-chart-bar',
        color: 'text-purple-500',
    },
]);

// Safe computed properties
const auditVoucherData = computed(() => {
    return (
        props.vouchers || { data: [], total: 0, per_page: 15, current_page: 1 }
    );
});

const paginatorTotalRecords = computed(() => auditVoucherData.value.total || 0);
const paginatorCurrentPage = computed(
    () => auditVoucherData.value.current_page || 1,
);
const paginatorRows = computed(() => auditVoucherData.value.per_page || 15);

const breadcrumbs = [{ title: 'Audit Queue', href: '#' }];

// Document checking functions
const hasAllRequiredDocuments = (voucher) => {
    if (!voucher || !voucher.documents || !Array.isArray(voucher.documents)) {
        return false;
    }

    const attachedDocTypes = voucher.documents
        .map((doc) => doc.document_type)
        .filter(Boolean);
    const requiredDocs = props.requiredDocuments || [];

    return requiredDocs.every((docType) => attachedDocTypes.includes(docType));
};

const getMissingDocuments = (voucher) => {
    if (!voucher || !voucher.documents || !Array.isArray(voucher.documents)) {
        return props.requiredDocuments || [];
    }

    const attachedDocTypes = voucher.documents
        .map((doc) => doc.document_type)
        .filter(Boolean);
    const requiredDocs = props.requiredDocuments || [];

    return requiredDocs.filter(
        (docType) => !attachedDocTypes.includes(docType),
    );
};

const getDocumentCount = (voucher) => {
    if (!voucher || !voucher.documents || !Array.isArray(voucher.documents)) {
        return 0;
    }
    return voucher.documents.length;
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

// Format document type for display
const formatDocumentType = (docType) => {
    const typeMap = {
        approval_form: 'Approval Form',
        invoice: 'Invoice',
        receipt: 'Receipt',
        delivery_note: 'Delivery Note',
        other: 'Additional Document',
        supporting: 'Supporting Document',
    };
    return (
        typeMap[docType] ||
        docType.replace(/_/g, ' ').replace(/\b\w/g, (l) => l.toUpperCase())
    );
};

// Print Voucher Function
const printVoucher = (voucher) => {
    const printUrl = `/vouchers/${voucher.id}/print`;
    window.open(printUrl, '_blank');
};

// View Voucher Details Function
const viewVoucherDetails = (voucher) => {
    router.visit(`/internal-audits/${voucher.id}`);
};

// Enhanced Document Functions
// Enhanced Document Functions
const getDocumentUrl = (document) => {
    if (!document) return null;

    console.log('Document properties:', document);

    // Priority 1: Use the URL if provided by backend (which it now is!)
    if (document.url) {
        console.log('Using provided URL:', document.url);
        return document.url;
    }

    // Priority 2: Fallback to file_path
    if (document.file_path) {
        console.log('Using file_path:', document.file_path);
        return `/storage/${document.file_path}`;
    }

    console.log(
        'No file path found. Available properties:',
        Object.keys(document),
    );
    return null;
};

const openDocument = async (document) => {
    console.log('Opening document:', document);

    const documentUrlValue = getDocumentUrl(document);

    if (!documentUrlValue) {
        console.error(
            'No valid document URL found. Document properties:',
            document,
        );
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
        console.log('Attempting to load document URL:', documentUrlValue);

        // Set the URL and show the viewer
        documentUrl.value = documentUrlValue;
        showDocumentViewer.value = true;

        // For PDFs, browsers handle them well in iframes
        // The iframe will trigger the onDocumentLoad when ready
    } catch (error) {
        console.error('Error loading document:', error);
        documentError.value =
            'Failed to load document. Please try downloading the file.';
        loadingDocument.value = false;
        toast.add({
            severity: 'error',
            summary: 'Error',
            detail: 'Failed to load document preview',
            life: 3000,
        });
    }
};

const loadDocument = (document) => {
    openDocument(document);
};

const onDocumentLoad = () => {
    loadingDocument.value = false;
    documentError.value = '';
    console.log('Document loaded successfully');
};

const onDocumentError = () => {
    loadingDocument.value = false;
    documentError.value =
        'Failed to load document preview. The file may be corrupted or in an unsupported format.';
    console.error('Document iframe load error');
};

const downloadDocument = (document) => {
    const documentUrlValue = getDocumentUrl(document);

    if (!documentUrlValue) {
        toast.add({
            severity: 'error',
            summary: 'Error',
            detail: 'Cannot download document - URL not found',
            life: 3000,
        });
        return;
    }

    try {
        const link = document.createElement('a');
        link.href = documentUrlValue;
        link.download = document.file_name || 'document';
        link.target = '_blank';
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);

        toast.add({
            severity: 'success',
            summary: 'Download Started',
            detail: 'Document download has been initiated',
            life: 3000,
        });
    } catch (error) {
        console.error('Download error:', error);
        toast.add({
            severity: 'error',
            summary: 'Download Failed',
            detail: 'Failed to download document',
            life: 3000,
        });
    }
};

const openInNewTab = (document) => {
    const documentUrlValue = getDocumentUrl(document);
    if (!documentUrlValue) return;

    window.open(documentUrlValue, '_blank');
};

const openDocumentsModal = (voucher) => {
    if (!voucher) return;
    currentVoucher.value = voucher;
    showDocumentsModal.value = true;
};

const openRejectModal = (voucher) => {
    if (!voucher) return;
    currentVoucher.value = voucher;
    rejectionReason.value = '';
    rejectionTouched.value = false;
    showRejectionModal.value = true;
};

const openApproveModal = (voucher) => {
    if (!voucher) return;

    if (!hasAllRequiredDocuments(voucher)) {
        const missingDocs = getMissingDocuments(voucher);
        const formattedMissingDocs = missingDocs.map((doc) =>
            formatDocumentType(doc),
        );
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
                    detail: `Voucher ${currentVoucher.value?.voucher_number} approved successfully.`,
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
        {
            reason: rejectionReason.value,
        },
        {
            preserveScroll: true,
            onSuccess: () => {
                toast.add({
                    severity: 'info',
                    summary: 'Rejected',
                    detail: `Voucher ${currentVoucher.value?.voucher_number} has been rejected.`,
                    life: 4000,
                });
                showRejectionModal.value = false;
                currentVoucher.value = null;
                rejectionReason.value = '';
                rejectionTouched.value = false;
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

const onPageChange = (event) => {
    router.get(
        '/internal-audits',
        { page: event.page + 1 },
        {
            preserveScroll: true,
            preserveState: true,
            replace: true,
        },
    );
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

// Refresh data
const refreshData = () => {
    router.reload();
};




const vouchers = ref([]);

const searchQuery = ref(""); // Search input

const filters = ref({
    global: { value: null, matchMode: FilterMatchMode.CONTAINS },

    voucher_number: { value: null, matchMode: FilterMatchMode.CONTAINS },

    voucher_type: { value: null, matchMode: FilterMatchMode.CONTAINS },

    voucher_date: { value: null, matchMode: FilterMatchMode.CONTAINS },

    // mda.name: { value: null, matchMode: FilterMatchMode.CONTAINS },

    narration: { value: null, matchMode: FilterMatchMode.CONTAINS },
    status: { value: null, matchMode: FilterMatchMode.CONTAINS }
});


const lazyParams = ref({
    first: 0,
    rows: 10,
    page: 1,
});

const totalRecords = ref(0);
const loading = ref(false);
let debounceTimer = null; // Timer for debounce



const loadVouchers = async () => {
    loading.value = true;
    try {
        const response = await axios.get('avsearch', { params: { per_page: lazyParams.value.rows, page: lazyParams.value.page, search: searchQuery.value }, });
        console.log(response.data);
        vouchers.value = response.data.vouchers.data;
        totalRecords.value = response.data.paginator.total;
    } catch (error) {
        toast.add({ severity: "error", summary: "Error", detail: "Failed to load data", life: 3000 });
        console.error(error);

    }
    loading.value = false;
};


onMounted(() => {
    // debugVoucherStatuses();
    console.log('=== END DEBUG ===');
    console.log(props);
    console.log('=== END DEBUG ===');
    lazyParams.value.page = 1;
    loadVouchers();
});


watch(searchQuery, () => {
    clearTimeout(debounceTimer);
    debounceTimer = setTimeout(() => {
        lazyParams.value.page = 1; // Reset to first page when searching
        loadVouchers();
    }, 2000); // 500ms debounce delay

});

const onPage = (event) => {
    lazyParams.value.page = event.page + 1; // Laravel pagination starts at 1
    lazyParams.value.first = event.first;
    lazyParams.value.rows = event.rows;
    loadVouchers();
};





</script>

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

/* Enhanced document viewer styles */
:deep(.p-dialog .p-dialog-content) {
    padding: 1rem;
}

:deep(.p-dialog .p-dialog-header) {
    padding: 1.25rem;
}

:deep(.p-dialog .p-dialog-footer) {
    padding: 0.75rem 1.25rem;
}

/* Ensure iframe takes full height */
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
