<!-- resources/js/Pages/Remittances/Show.vue -->
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

// ðŸ’¡ State for Modal
const showConfirmationModal = ref(false);
const currentAction = ref(null);

// ðŸ’¡ PROPS: Receive remittance data from Laravel controller
const props = defineProps({
    remittance: {
        type: Object,
        required: true,
        default: () => ({}),
    },
});

// --- STATUS COLORS ---
const getStatusSeverity = (status) => {
    if (!status) return 'info';
    const normalizedStatus = status.toLowerCase().trim();

    switch (normalizedStatus) {
        case 'approved':
        case 'paid':
        case 'completed':
        case 'retired':
            return 'success';
        case 'declined':
        case 'rejected':
        case 'failed':
            return 'danger';
        case 'sent back':
        case 'returned':
        case 'cancelled':
            return 'warning';
        case 'submitted':
        case 'pending':
        case 'pending approval':
            return 'secondary';
        case 'draft':
        case 'saved':
            return 'info';
        default:
            return 'info';
    }
};

// Check if remittance can be edited
const canEditRemittance = (remittance) => {
    if (!remittance || !remittance.status) return false;
    const status = remittance.status.toLowerCase().trim();
    const editableStatuses = [
        'draft',
        'saved',
        'sent back',
        'returned',
        'declined',
        'rejected',
    ];
    return editableStatuses.includes(status);
};

// Check if remittance can be deleted
const canDeleteRemittance = (remittance) => {
    if (!remittance || !remittance.status) return false;
    const status = remittance.status.toLowerCase().trim();
    const deletableStatuses = ['draft', 'saved'];
    return deletableStatuses.includes(status);
};

// --- FORMATTERS ---
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

    if (nairaAmount >= 1000000000) {
        words +=
            convertHundreds(Math.floor(nairaAmount / 1000000000)) + ' Billion ';
        nairaAmount %= 1000000000;
    }
    if (nairaAmount >= 1000000) {
        words +=
            convertHundreds(Math.floor(nairaAmount / 1000000)) + ' Million ';
        nairaAmount %= 1000000;
    }
    if (nairaAmount >= 1000) {
        words += convertHundreds(Math.floor(nairaAmount / 1000)) + ' Thousand ';
        nairaAmount %= 1000;
    }
    if (nairaAmount > 0) {
        words += convertHundreds(nairaAmount) + ' ';
    }

    words += words ? 'Naira' : 'Zero Naira';
    if (koboAmount > 0) {
        words += ' and ' + convertHundreds(koboAmount) + ' Kobo';
    }

    return words.trim() + ' Only';
};

// --- ACTIONS ---
const printRemittance = () => {
    const printUrl = `/remittances/${props.remittance.id}/print`;
    window.open(printUrl, '_blank');
};

const editRemittance = () => {
    if (!canEditRemittance(props.remittance)) {
        toast.add({
            severity: 'error',
            summary: 'Cannot Edit',
            detail: `Remittance ${props.remittance.receipt_number} is "${props.remittance.status}" and cannot be edited.`,
            life: 5000,
        });
        return;
    }
    router.visit(`/remittances/${props.remittance.id}/edit`);
};

const deleteRemittance = () => {
    if (!canDeleteRemittance(props.remittance)) {
        toast.add({
            severity: 'error',
            summary: 'Cannot Delete',
            detail: `Remittance ${props.remittance.receipt_number} is "${props.remittance.status}" and cannot be deleted.`,
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
        router.delete(route('remittances.destroy', props.remittance.id), {
            preserveScroll: true,
            onSuccess: () => {
                toast.add({
                    severity: 'success',
                    summary: 'Deleted',
                    detail: `Remittance ${props.remittance.receipt_number} successfully deleted.`,
                    life: 3000,
                });
                router.visit(route('remittances.index'));
            },
            onError: (errors) => {
                const detail =
                    errors.message || 'Failed to delete the remittance.';
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
    return convertNumberToWords(props.remittance.amount || 0);
});

// Breadcrumbs
const breadcrumbs = computed(() => [
    { title: 'Remittances', href: '/remittances' },
    {
        title: props.remittance.receipt_number || 'Remittance Details',
        href: '#',
    },
]);
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head :title="`Remittance - ${remittance.receipt_number}`" />
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
                                {{ remittance.receipt_number }}
                            </h1>
                            <div class="align-items-center flex gap-3">
                                <Tag
                                    :value="remittance.status"
                                    :severity="
                                        getStatusSeverity(remittance.status)
                                    "
                                />
                                <span class="text-500">
                                    Date:
                                    {{ formatDate(remittance.transfer_date) }}
                                </span>
                            </div>
                        </div>
                    </div>

                    <div class="flex gap-2">
                        <Button
                            label="Print"
                            icon="pi pi-print"
                            severity="info"
                            @click="printRemittance"
                        />
                        <Button
                            label="Edit Remittance"
                            icon="pi pi-pencil"
                            severity="secondary"
                            :disabled="!canEditRemittance(remittance)"
                            v-tooltip="
                                canEditRemittance(remittance)
                                    ? 'Edit Remittance'
                                    : 'Cannot edit this remittance'
                            "
                            @click="editRemittance"
                        />
                        <Button
                            label="Delete"
                            icon="pi pi-trash"
                            severity="danger"
                            :disabled="!canDeleteRemittance(remittance)"
                            v-tooltip="
                                canDeleteRemittance(remittance)
                                    ? 'Delete Remittance'
                                    : 'Cannot delete this remittance'
                            "
                            @click="deleteRemittance"
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
                            <span>Basic Information</span>
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
                                        {{ remittance.receipt_number }}
                                    </div>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="field">
                                    <label class="text-500 font-semibold"
                                        >Transfer Date</label
                                    >
                                    <div class="text-900">
                                        {{
                                            formatDate(remittance.transfer_date)
                                        }}
                                    </div>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="field">
                                    <label class="text-500 font-semibold"
                                        >Treasury</label
                                    >
                                    <div class="text-900 font-medium">
                                        {{
                                            remittance.treasury || 'Benin City'
                                        }}
                                    </div>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="field">
                                    <label class="text-500 font-semibold"
                                        >Status</label
                                    >
                                    <div>
                                        <Tag
                                            :value="remittance.status"
                                            :severity="
                                                getStatusSeverity(
                                                    remittance.status,
                                                )
                                            "
                                        />
                                    </div>
                                </div>
                            </div>
                            <div class="col-12">
                                <div class="field">
                                    <label class="text-500 font-semibold"
                                        >Narration</label
                                    >
                                    <div class="text-900">
                                        {{
                                            remittance.narration ||
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
                            <!-- Source Bank -->
                            <div
                                class="col-12"
                                v-if="remittance.source_bank_details"
                            >
                                <div class="field">
                                    <label class="text-500 font-semibold"
                                        >Source Bank</label
                                    >
                                    <div class="text-900 font-medium">
                                        {{
                                            remittance.source_bank_details
                                                .bank_name
                                        }}
                                    </div>
                                    <div class="text-500 text-sm">
                                        {{
                                            remittance.source_bank_details.title
                                        }}
                                        â€¢
                                        {{
                                            remittance.source_bank_details
                                                .account_number
                                        }}
                                        <Tag
                                            :value="
                                                remittance.source_bank_details
                                                    .tag
                                            "
                                            severity="info"
                                            size="small"
                                            class="ml-2"
                                        />
                                    </div>
                                </div>
                            </div>

                            <!-- Destination Bank -->
                            <div
                                class="col-12"
                                v-if="remittance.destination_bank_details"
                            >
                                <div class="field">
                                    <label class="text-500 font-semibold"
                                        >Destination Bank</label
                                    >
                                    <div class="text-900 font-medium">
                                        {{
                                            remittance.destination_bank_details
                                                .bank_name
                                        }}
                                    </div>
                                    <div class="text-500 text-sm">
                                        {{
                                            remittance.destination_bank_details
                                                .title
                                        }}
                                        â€¢
                                        {{
                                            remittance.destination_bank_details
                                                .account_number
                                        }}
                                        <Tag
                                            :value="
                                                remittance
                                                    .destination_bank_details
                                                    .tag
                                            "
                                            severity="success"
                                            size="small"
                                            class="ml-2"
                                        />
                                    </div>
                                </div>
                            </div>

                            <div class="col-12">
                                <div class="field">
                                    <label class="text-500 font-semibold"
                                        >Amount</label
                                    >
                                    <div
                                        class="text-900 text-primary text-2xl font-bold"
                                    >
                                        {{ formatCurrency(remittance.amount) }}
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
                            <div class="col-6">
                                <div class="field">
                                    <label class="text-500 font-semibold"
                                        >Created By</label
                                    >
                                    <div class="text-900">
                                        {{
                                            remittance.created_by?.name || 'N/A'
                                        }}
                                    </div>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="field">
                                    <label class="text-500 font-semibold"
                                        >Created Date</label
                                    >
                                    <div class="text-900">
                                        {{
                                            formatDateTime(
                                                remittance.created_at,
                                            )
                                        }}
                                    </div>
                                </div>
                            </div>
                        </div>
                    </template>
                </Card>
            </div>

            <!-- Transaction Details Card -->
            <div class="col-12">
                <Card>
                    <template #title>
                        <div class="align-items-center flex gap-2">
                            <i class="pi pi-credit-card text-primary"></i>
                            <span>Transaction Details</span>
                        </div>
                    </template>
                    <template #content>
                        <div class="grid">
                            <div class="col-12 md:col-6">
                                <div class="field">
                                    <label class="text-500 font-semibold"
                                        >From (Source)</label
                                    >
                                    <div class="text-900 font-medium">
                                        {{
                                            remittance.source_bank_details
                                                ?.bank_name ||
                                            remittance.source_bank
                                        }}
                                    </div>
                                    <div
                                        v-if="remittance.source_bank_details"
                                        class="text-500 text-sm"
                                    >
                                        A/c:
                                        {{
                                            remittance.source_bank_details
                                                .account_number
                                        }}<br />
                                        {{ remittance.source_bank_details.title
                                        }}<br />
                                        Tag:
                                        <Tag
                                            :value="
                                                remittance.source_bank_details
                                                    .tag
                                            "
                                            severity="info"
                                            size="small"
                                        />
                                    </div>
                                </div>
                            </div>
                            <div class="col-12 md:col-6">
                                <div class="field">
                                    <label class="text-500 font-semibold"
                                        >To (Destination)</label
                                    >
                                    <div class="text-900 font-medium">
                                        {{
                                            remittance.destination_bank_details
                                                ?.bank_name ||
                                            remittance.destination_bank
                                        }}
                                    </div>
                                    <div
                                        v-if="
                                            remittance.destination_bank_details
                                        "
                                        class="text-500 text-sm"
                                    >
                                        A/c:
                                        {{
                                            remittance.destination_bank_details
                                                .account_number
                                        }}<br />
                                        {{
                                            remittance.destination_bank_details
                                                .title
                                        }}<br />
                                        Tag:
                                        <Tag
                                            :value="
                                                remittance
                                                    .destination_bank_details
                                                    .tag
                                            "
                                            severity="success"
                                            size="small"
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
            header="Delete Remittance"
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
                        Remittance
                        <strong>{{ remittance.receipt_number }}</strong
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

:deep(.p-datatable) {
    font-size: 0.875rem;
}

:deep(.p-datatable .p-datatable-thead > tr > th) {
    background-color: var(--surface-100);
    color: var(--text-color);
    font-weight: 600;
}

.bg-gray-50 {
    background-color: var(--surface-50);
}

.bg-primary-50 {
    background-color: var(--primary-50);
    border-color: var(--primary-200);
}

.space-y-2 > * + * {
    margin-top: 0.5rem;
}

.space-y-3 > * + * {
    margin-top: 0.75rem;
}
</style>
