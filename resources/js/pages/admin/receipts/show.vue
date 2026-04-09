<script setup>
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, router } from '@inertiajs/vue3';
import Button from 'primevue/button';
import Card from 'primevue/card';
import Dialog from 'primevue/dialog';
import Tag from 'primevue/tag';
import Toast from 'primevue/toast';
import { useToast } from 'primevue/usetoast';
import { computed, ref } from 'vue';

const toast = useToast();

// State for Modal
const showConfirmationModal = ref(false);
const currentAction = ref(null);

// Props
const props = defineProps({
    receipt: {
        type: Object,
        required: true,
        default: () => ({}),
    },
    bank_tag: {
        type: String,
        default: null,
    },
});

// Computed property to get bank_tag from either source
const bankTag = computed(() => {
    return props.receipt.bank_tag || props.bank_tag;
});

// Status Colors
const getStatusSeverity = (status) => {
    if (!status) return 'info';
    const normalizedStatus = status.toLowerCase().trim();

    switch (normalizedStatus) {
        case 'approved':
        case 'active':
        case 'completed':
            return 'success';
        case 'rejected':
        case 'declined':
        case 'cancelled':
            return 'danger';
        case 'pending':
        case 'processing':
            return 'warning';
        case 'draft':
        case 'saved':
            return 'info';
        default:
            return 'info';
    }
};

// Classification Colors
const getClassificationSeverity = (classification) => {
    if (!classification) return 'info';
    const normalized = classification.toLowerCase().trim();

    switch (normalized) {
        case 'revenue':
            return 'success';
        case 'tax':
            return 'warning';
        case 'loan':
        case 'grant':
            return 'info';
        default:
            return 'info';
    }
};

// Check if receipt can be edited
const canEditReceipt = (receipt) => {
    return true; // Always allow editing for receipts
};

// Check if receipt can be deleted
const canDeleteReceipt = (receipt) => {
    return true; // Always allow deletion for receipts
};

// Formatters
const formatCurrency = (value) => {
    return new Intl.NumberFormat('en-NG', {
        style: 'currency',
        currency: 'NGN',
    }).format(value || 0);
};

const formatDate = (dateString) => {
    if (!dateString) return 'N/A';
    return new Date(dateString).toLocaleDateString('en-GB');
};

const formatDateTime = (dateString) => {
    if (!dateString) return 'N/A';
    return new Date(dateString).toLocaleString('en-GB');
};

const convertNumberToWords = (amount) => {
    if (isNaN(amount) || amount === 0) return 'Zero Naira';

    const units = [
        '',
        'One',
        'Two',
        'Three',
        'Four',
        'Five',
        'Six',
        'Seven',
        'Eight',
        'Nine',
    ];
    const teens = [
        'Ten',
        'Eleven',
        'Twelve',
        'Thirteen',
        'Fourteen',
        'Fifteen',
        'Sixteen',
        'Seventeen',
        'Eighteen',
        'Nineteen',
    ];
    const tens = [
        '',
        '',
        'Twenty',
        'Thirty',
        'Forty',
        'Fifty',
        'Sixty',
        'Seventy',
        'Eighty',
        'Ninety',
    ];

    const convertHundreds = (num) => {
        let result = '';
        if (num >= 100) {
            result += units[Math.floor(num / 100)] + ' Hundred ';
            num %= 100;
        }
        if (num >= 20) {
            result += tens[Math.floor(num / 10)] + ' ';
            num %= 10;
        } else if (num >= 10) {
            result += teens[num - 10] + ' ';
            num = 0;
        }
        if (num > 0) {
            result += units[num] + ' ';
        }
        return result.trim();
    };

    let words = '';
    let nairaAmount = Math.floor(amount);
    let koboAmount = Math.round((amount - nairaAmount) * 100);

    // Billions
    if (nairaAmount >= 1000000000) {
        words +=
            convertHundreds(Math.floor(nairaAmount / 1000000000)) + ' Billion ';
        nairaAmount %= 1000000000;
    }

    // Millions
    if (nairaAmount >= 1000000) {
        words +=
            convertHundreds(Math.floor(nairaAmount / 1000000)) + ' Million ';
        nairaAmount %= 1000000;
    }

    // Thousands
    if (nairaAmount >= 1000) {
        words += convertHundreds(Math.floor(nairaAmount / 1000)) + ' Thousand ';
        nairaAmount %= 1000;
    }

    // Hundreds
    if (nairaAmount > 0) {
        words += convertHundreds(nairaAmount) + ' ';
    }

    words += words ? 'Naira' : 'Zero Naira';
    if (koboAmount > 0) {
        words += ' and ' + convertHundreds(koboAmount) + ' Kobo';
    }

    return words.trim() + ' Only';
};

// Actions
const printReceipt = () => {
    const printUrl = `/receipts/${props.receipt.id}/print`;
    window.open(printUrl, '_blank');
};

const editReceipt = () => {
    if (!canEditReceipt(props.receipt)) {
        toast.add({
            severity: 'error',
            summary: 'Cannot Edit',
            detail: `Receipt ${props.receipt.receipt_number} cannot be edited.`,
            life: 5000,
        });
        return;
    }
    router.visit(`/receipts/${props.receipt.id}/edit`);
};

const deleteReceipt = () => {
    if (!canDeleteReceipt(props.receipt)) {
        toast.add({
            severity: 'error',
            summary: 'Cannot Delete',
            detail: `Receipt ${props.receipt.receipt_number} cannot be deleted.`,
            life: 5000,
        });
        return;
    }
    currentAction.value = 'delete';
    showConfirmationModal.value = true;
};

const goBack = () => {
    window.history.back();
};

const confirmAction = () => {
    showConfirmationModal.value = false;

    if (currentAction.value === 'delete') {
        router.delete(route('receipts.destroy', props.receipt.id), {
            preserveScroll: true,
            onSuccess: () => {
                toast.add({
                    severity: 'success',
                    summary: 'Deleted',
                    detail: `Receipt ${props.receipt.receipt_number} successfully deleted.`,
                    life: 3000,
                });
                router.visit(route('receipts.index'));
            },
            onError: (errors) => {
                const detail =
                    errors.message || 'Failed to delete the receipt.';
                toast.add({
                    severity: 'error',
                    summary: 'Error',
                    detail: detail,
                    life: 5000,
                });
            },
        });
    }
};

// Computed properties
const amountInWords = computed(() => {
    return convertNumberToWords(props.receipt.amount || 0);
});

const breadcrumbs = computed(() => [
    { title: 'Receipts', href: '/receipts' },
    { title: props.receipt.receipt_number || 'Receipt Details', href: '#' },
]);
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head :title="`Receipt - ${receipt.receipt_number}`" />
        <Toast />

        <div class="grid">
            <!-- Header Actions -->
            <div class="col-12">
                <div
                    class="justify-content-between align-items-center mb-4 flex"
                >
                    <div class="align-items-center flex gap-3">
                        <Button
                            icon="pi pi-arrow-left"
                            text
                            rounded
                            severity="secondary"
                            @click="goBack"
                            v-tooltip="'Go Back'"
                        />
                        <div>
                            <h1 class="mb-1 text-2xl font-bold">
                                {{ receipt.receipt_number }}
                            </h1>
                            <div class="align-items-center flex gap-3">
                                <Tag
                                    :value="receipt.classification"
                                    :severity="
                                        getClassificationSeverity(
                                            receipt.classification,
                                        )
                                    "
                                />
                                <span class="text-500">
                                    Date: {{ formatDate(receipt.receipt_date) }}
                                </span>
                            </div>
                        </div>
                    </div>

                    <div class="flex gap-2">
                        <Button
                            label="Print"
                            icon="pi pi-print"
                            severity="info"
                            @click="printReceipt"
                        />
                        <Button
                            label="Edit Receipt"
                            icon="pi pi-pencil"
                            severity="secondary"
                            :disabled="!canEditReceipt(receipt)"
                            v-tooltip="
                                canEditReceipt(receipt)
                                    ? 'Edit Receipt'
                                    : 'Cannot edit this receipt'
                            "
                            @click="editReceipt"
                        />
                        <Button
                            label="Delete"
                            icon="pi pi-trash"
                            severity="danger"
                            :disabled="!canDeleteReceipt(receipt)"
                            v-tooltip="
                                canDeleteReceipt(receipt)
                                    ? 'Delete Receipt'
                                    : 'Cannot delete this receipt'
                            "
                            @click="deleteReceipt"
                        />
                    </div>
                </div>
            </div>

            <!-- Basic Information -->
            <div class="col-12 md:col-6">
                <Card>
                    <template #title>
                        <div class="align-items-center flex gap-2">
                            <i class="pi pi-info-circle text-primary"></i>
                            <span>Receipt Information</span>
                        </div>
                    </template>
                    <template #content>
                        <div class="grid">
                            <div class="col-6">
                                <div class="field">
                                    <label class="text-500 font-semibold"
                                        >Receipt Number</label
                                    >
                                    <div class="text-900 text-lg font-medium">
                                        {{ receipt.receipt_number }}
                                    </div>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="field">
                                    <label class="text-500 font-semibold"
                                        >Receipt Date</label
                                    >
                                    <div class="text-900">
                                        {{ formatDate(receipt.receipt_date) }}
                                    </div>
                                </div>
                            </div>
                            <div class="col-12">
                                <div class="field">
                                    <label class="text-500 font-semibold"
                                        >MDA</label
                                    >
                                    <div class="text-900 text-lg font-medium">
                                        {{ receipt.mda_name || 'N/A' }}
                                    </div>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="field">
                                    <label class="text-500 font-semibold"
                                        >Classification</label
                                    >
                                    <div class="text-900 font-medium">
                                        <Tag
                                            :value="
                                                receipt.classification?.toUpperCase() ||
                                                'N/A'
                                            "
                                            :severity="
                                                getClassificationSeverity(
                                                    receipt.classification,
                                                )
                                            "
                                        />
                                    </div>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="field">
                                    <label class="text-500 font-semibold"
                                        >Economic Codes</label
                                    >
                                    <div class="text-900 font-medium">
                                        <div
                                            v-if="
                                                receipt.eco_code &&
                                                receipt.eco_code_item
                                            "
                                        >
                                            {{ receipt.eco_code }}.{{
                                                receipt.eco_code_item
                                            }}
                                        </div>
                                        <div v-else-if="receipt.eco_code">
                                            {{ receipt.eco_code }}
                                        </div>
                                        <span v-else class="text-500">N/A</span>
                                    </div>
                                </div>
                            </div>
                            <div class="col-12">
                                <div class="field">
                                    <label class="text-500 font-semibold"
                                        >Activity / Description</label
                                    >
                                    <div
                                        class="text-900 border-round border-1 border-200 bg-gray-50 p-3"
                                    >
                                        {{
                                            receipt.activity ||
                                            'No description provided'
                                        }}
                                    </div>
                                </div>
                            </div>
                        </div>
                    </template>
                </Card>
            </div>

            <!-- Financial Information -->
            <div class="col-12 md:col-6">
                <Card>
                    <template #title>
                        <div class="align-items-center flex gap-2">
                            <i class="pi pi-wallet text-primary"></i>
                            <span>Financial Information</span>
                        </div>
                    </template>
                    <template #content>
                        <div class="grid">
                            <div class="col-12">
                                <div class="field">
                                    <label class="text-500 font-semibold"
                                        >Total Amount</label
                                    >
                                    <div
                                        class="text-900 text-primary text-2xl font-bold"
                                    >
                                        {{ formatCurrency(receipt.amount) }}
                                    </div>
                                </div>
                            </div>
                            <div class="col-12">
                                <div class="field">
                                    <label class="text-500 font-semibold"
                                        >Amount in Words</label
                                    >
                                    <div
                                        class="text-900 border-round border-1 border-200 bg-gray-50 p-2 italic"
                                    >
                                        {{ amountInWords }}
                                    </div>
                                </div>
                            </div>
                        </div>
                    </template>
                </Card>

                <!-- Banking Information -->
                <!-- <Card class="mt-4">
                    <template #title>
                        <div class="align-items-center flex gap-2">
                            <i class="pi pi-building text-primary"></i>
                            <span>Banking Information</span>
                        </div>
                    </template>
                    <template #content>
                        <div class="grid">
                            <div class="col-12">
                                <div class="field">
                                    <label class="text-500 font-semibold"
                                        >Bank Name</label
                                    >
                                    <div class="text-900">
                                        {{ receipt.bank_name || 'N/A' }}
                                    </div>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="field">
                                    <label class="text-500 font-semibold"
                                        >Account Name</label
                                    >
                                    <div class="text-900">
                                        {{ receipt.account_name || 'N/A' }}
                                    </div>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="field">
                                    <label class="text-500 font-semibold"
                                        >Account Number</label
                                    >
                                    <div class="text-900">
                                        {{ receipt.account_number || 'N/A' }}
                                    </div>
                                </div>
                            </div>
                            <div v-if="receipt.bank_tag" class="col-12">
                                <div class="field">
                                    <label class="text-500 font-semibold"
                                        >Bank Tag</label
                                    >
                                    <div class="text-900 font-mono">
                                        {{ receipt.bank_tag }}
                                    </div>
                                </div>
                            </div>
                        </div>
                    </template>
                </Card> -->
            </div>

            <!-- Banking Information in its own column -->
            <div class="col-12">
                <Card>
                    <template #title>
                        <div class="align-items-center flex gap-2">
                            <i class="pi pi-building text-primary"></i>
                            <span>Banking Information</span>
                        </div>
                    </template>
                    <template #content>
                        <div class="grid">
                            <div class="col-12">
                                <div class="field">
                                    <label class="text-500 font-semibold"
                                        >Bank Name</label
                                    >
                                    <div class="text-900">
                                        {{ receipt.bank_name || 'N/A' }}
                                    </div>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="field">
                                    <label class="text-500 font-semibold"
                                        >Account Name</label
                                    >
                                    <div class="text-900">
                                        {{ receipt.account_name || 'N/A' }}
                                    </div>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="field">
                                    <label class="text-500 font-semibold"
                                        >Account Number</label
                                    >
                                    <div class="text-900">
                                        {{ receipt.account_number || 'N/A' }}
                                    </div>
                                </div>
                            </div>
                            <div v-if="receipt.bank_tag" class="col-12">
                                <div class="field">
                                    <label class="text-500 font-semibold"
                                        >Bank Tag</label
                                    >
                                    <div class="text-900 font-mono">
                                        {{ receipt.bank_tag }}
                                    </div>
                                </div>
                            </div>
                        </div>
                    </template>
                </Card>
            </div>

            <!-- Economic Code Details -->
            <div class="col-12">
                <Card>
                    <template #title>
                        <div class="align-items-center flex gap-2">
                            <i class="pi pi-list text-primary"></i>
                            <span>Economic Code Details</span>
                        </div>
                    </template>
                    <template #content>
                        <div class="grid">
                            <div class="col-6">
                                <div class="field">
                                    <label class="text-500 font-semibold"
                                        >Head of Receipt (Economic Code)</label
                                    >
                                    <div class="text-900 text-lg font-medium">
                                        {{ receipt.eco_code || 'N/A' }}
                                    </div>
                                    <div
                                        v-if="receipt.economy_code_id"
                                        class="text-500 text-sm"
                                    >
                                        ID: {{ receipt.economy_code_id }}
                                    </div>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="field">
                                    <label class="text-500 font-semibold"
                                        >Sub-Head (Code Item)</label
                                    >
                                    <div class="text-900 text-lg font-medium">
                                        {{ receipt.eco_code_item || 'N/A' }}
                                    </div>
                                    <div
                                        v-if="receipt.economy_code_item_id"
                                        class="text-500 text-sm"
                                    >
                                        ID: {{ receipt.economy_code_item_id }}
                                    </div>
                                </div>
                            </div>
                            <div class="col-12">
                                <div class="field">
                                    <label class="text-500 font-semibold"
                                        >Combined Code</label
                                    >
                                    <div
                                        class="text-900 font-mono text-xl font-bold"
                                    >
                                        <span
                                            v-if="
                                                receipt.eco_code &&
                                                receipt.eco_code_item
                                            "
                                        >
                                            {{ receipt.eco_code }}.{{
                                                receipt.eco_code_item
                                            }}
                                        </span>
                                        <span v-else class="text-500"
                                            >Not Available</span
                                        >
                                    </div>
                                </div>
                            </div>
                        </div>
                    </template>
                </Card>
            </div>

            <!-- Timeline / Audit Trail -->
            <div class="col-12">
                <Card>
                    <template #title>
                        <div class="align-items-center flex gap-2">
                            <i class="pi pi-history text-primary"></i>
                            <span>Timeline</span>
                        </div>
                    </template>
                    <template #content>
                        <div class="grid">
                            <div class="col-6 md:col-3">
                                <div class="field">
                                    <label class="text-500 font-semibold"
                                        >Created Date</label
                                    >
                                    <div class="text-900">
                                        {{ formatDateTime(receipt.created_at) }}
                                    </div>
                                </div>
                            </div>
                            <div class="col-6 md:col-3">
                                <div class="field">
                                    <label class="text-500 font-semibold"
                                        >Updated Date</label
                                    >
                                    <div class="text-900">
                                        {{ formatDateTime(receipt.updated_at) }}
                                    </div>
                                </div>
                            </div>
                            <div class="col-6 md:col-3">
                                <div class="field">
                                    <label class="text-500 font-semibold"
                                        >Bank Tag Reference</label
                                    >
                                    <div class="text-900 font-mono">
                                        {{ bankTag || 'N/A' }}
                                    </div>
                                </div>
                            </div>
                            <div class="col-6 md:col-3">
                                <div class="field">
                                    <label class="text-500 font-semibold"
                                        >Receipt Type</label
                                    >
                                    <div class="text-900">
                                        <Tag
                                            :value="receipt.classification"
                                            :severity="
                                                getClassificationSeverity(
                                                    receipt.classification,
                                                )
                                            "
                                        />
                                    </div>
                                </div>
                            </div>
                        </div>
                    </template>
                </Card>
            </div>
        </div>

        <!-- Confirmation Modal -->
        <Dialog
            v-model:visible="showConfirmationModal"
            :style="{ width: '450px' }"
            header="Delete Receipt"
            :modal="true"
        >
            <div class="align-items-center flex">
                <i
                    class="pi pi-exclamation-triangle mr-3 text-red-500"
                    style="font-size: 2rem"
                ></i>
                <div>
                    <span>
                        Are you sure you want to
                        <strong class="text-red-600">permanently delete</strong>
                        Receipt
                        <strong>{{ receipt.receipt_number }}</strong
                        >? This action cannot be undone.
                    </span>
                </div>
            </div>

            <template #footer>
                <Button
                    label="Cancel"
                    icon="pi pi-times"
                    @click="showConfirmationModal = false"
                    text
                />
                <Button
                    label="Yes, Delete"
                    icon="pi pi-trash"
                    severity="danger"
                    @click="confirmAction"
                    autofocus
                />
            </template>
        </Dialog>
    </AppLayout>
</template>

<style scoped>
.field {
    margin-bottom: 1.5rem;
}

.field:last-child {
    margin-bottom: 0;
}

.field label {
    display: block;
    margin-bottom: 0.25rem;
    font-size: 0.875rem;
}

:deep(.p-card) {
    height: 100%;
}

:deep(.p-card-content) {
    padding: 0;
}

:deep(.p-card-content .grid) {
    margin: 0 -1rem;
}

:deep(.p-card-content .col-12),
:deep(.p-card-content .col-6) {
    padding: 1rem;
}

.bg-gray-50 {
    background-color: var(--surface-50);
}

.space-y-2 > * + * {
    margin-top: 0.5rem;
}

.space-y-3 > * + * {
    margin-top: 0.75rem;
}
</style>
