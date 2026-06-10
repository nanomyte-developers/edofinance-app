<script setup>
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, router, usePage } from '@inertiajs/vue3';
import Button from 'primevue/button';
import Card from 'primevue/card';
import Column from 'primevue/column';
import DataTable from 'primevue/datatable';
import Dialog from 'primevue/dialog';
import Tag from 'primevue/tag';
import Toast from 'primevue/toast';
import { useToast } from 'primevue/usetoast';
import { computed, ref } from 'vue';


const toast = useToast();

// ðŸ’¡ State for Modal
const showConfirmationModal = ref(false);
const currentAction = ref(null);

// ðŸ’¡ PROPS: Receive voucher data from Laravel controller
const props = defineProps({
    voucher: {
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
        case 'closed':
        case 'retired':
            return 'success';
        case 'declined':
        case 'rejected':
        case 'decline and close':
            return 'danger';
        case 'sent back':
        case 'returned':
        case 'cancelled':
            return 'warning';
        case 'submitted':
        case 'pending':
        case 'forwarded':
            return 'secondary';
        case 'draft':
        case 'saved':
            return 'info';
        default:
            return 'info';
    }
};

// Get retirement status
const getRetirementStatus = (voucher) => {
    if (voucher.voucher_type !== 'prepayment') {
        return {
            label: 'Standard',
            severity: 'info',
            icon: 'pi pi-file',
            tooltip: 'Standard voucher - no retirement required',
        };
    }

    if (voucher.is_retired) {
        return {
            label: 'Retired',
            severity: 'success',
            icon: 'pi pi-check-circle',
            tooltip: `Retired on ${new Date(voucher.retired_at).toLocaleDateString()}`,
        };
    }

    if (voucher.requires_retirement) {
        return {
            label: 'Needs Retirement',
            severity: 'warning',
            icon: 'pi pi-exclamation-triangle',
            tooltip: 'Prepayment voucher requires retirement',
        };
    }

    return {
        label: 'Prepayment',
        severity: 'secondary',
        icon: 'pi pi-list',
        tooltip: 'Prepayment voucher',
    };
};

// Check if voucher can be edited
const canEditVoucher = (voucher) => {
    if (!voucher || !voucher.status) return false;
    const status = voucher.status.toLowerCase().trim();
    const editableStatuses = [
        'draft',
        'saved',
        'sent back',
        'returned',
        'declined',
        'rejected',
        'audit_rejected',
    ];
    return editableStatuses.includes(status);
};

// Check if voucher can be deleted
const canDeleteVoucher = (voucher) => {
    if (usePage().props.auth.userRoles.includes('Admin') || usePage().props.auth.userRoles.includes('admin')) {
        return true;
    }
    return false

    if (!voucher || !voucher.status) return false;
    const status = voucher.status.toLowerCase().trim();
    const deletableStatuses = ['draft', 'saved'];
    return deletableStatuses.includes(status);
};

// Check if prepayment voucher can be retired
const canRetireVoucher = (voucher) => {
    if (!voucher) return false;
    if (voucher.voucher_type !== 'prepayment' || !voucher.requires_retirement) {
        return false;
    }
    const status = voucher.status?.toLowerCase().trim();
    if (status !== 'approved') {
        return false;
    }
    if (voucher.is_retired || voucher.retired_at) {
        return false;
    }
    return true;
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

// Billions
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
const printVoucher = () => {
    const printUrl = `/vouchers/${props.voucher.id}/print`;
    window.open(printUrl, '_blank');
};

const editVoucher = () => {
    if (!canEditVoucher(props.voucher)) {
        toast.add({
            severity: 'error',
            summary: 'Cannot Edit',
            detail: `Voucher ${props.voucher.voucher_number} is "${props.voucher.status}" and cannot be edited.`,
            life: 5000,
        });
        return;
    }
    router.visit(`/vouchers/${props.voucher.id}/edit`);
};

const deleteVoucher = () => {
    if (!canDeleteVoucher(props.voucher)) {
        toast.add({
            severity: 'error',
            summary: 'Cannot Delete',
            detail: `Voucher ${props.voucher.voucher_number} is "${props.voucher.status}" and cannot be deleted.`,
            life: 5000,
        });
        return;
    }
    currentAction.value = 'delete';
    showConfirmationModal.value = true;
};

const retireVoucher = () => {
    if (!canRetireVoucher(props.voucher)) {
        toast.add({
            severity: 'error',
            summary: 'Cannot Retire',
            detail: `Voucher ${props.voucher.voucher_number} cannot be retired. Only approved prepayment vouchers that require retirement can be retired.`,
            life: 5000,
        });
        return;
    }
    currentAction.value = 'retire';
    showConfirmationModal.value = true;
};

const goBack = () => {
    window.history.back();
};

const confirmAction = () => {
    showConfirmationModal.value = false;

    if (currentAction.value === 'delete') {
        router.delete(route('vouchers.destroy', props.voucher.id), {
            preserveScroll: true,
            onSuccess: () => {
                toast.add({
                    severity: 'success',
                    summary: 'Deleted',
                    detail: `Voucher ${props.voucher.voucher_number} successfully deleted.`,
                    life: 3000,
                });
                router.visit(route('vouchers.index'));
            },
            onError: (errors) => {
                const detail =
                    errors.message || 'Failed to delete the voucher.';
                toast.add({
                    severity: 'error',
                    summary: 'Error',
                    detail: detail,
                    life: 5000,
                });
            },
        });
    } else if (currentAction.value === 'retire') {
        router.post(
            route('vouchers.retire', props.voucher.id),
            {},
            {
                preserveScroll: true,
                onSuccess: () => {
                    toast.add({
                        severity: 'success',
                        summary: 'Retired',
                        detail: `Prepayment voucher ${props.voucher.voucher_number} successfully retired.`,
                        life: 3000,
                    });
                    // Refresh the page to show updated status
                    router.reload({ only: ['voucher'] });
                },
                onError: (errors) => {
                    const detail =
                        errors.message || 'Failed to retire the voucher.';
                    toast.add({
                        severity: 'error',
                        summary: 'Error',
                        detail: detail,
                        life: 5000,
                    });
                },
            },
        );
    }
};

// Computed properties
const amountInWords = computed(() => {
    return convertNumberToWords(props.voucher.total_amount || 0);
});

const totalItems = computed(() => {
    return props.voucher.items?.length || 0;
});

const totalDocuments = computed(() => {
    return props.voucher.documents?.length || 0;
});

const retirementStatus = computed(() => {
    return getRetirementStatus(props.voucher);
});

// Breadcrumbs
const breadcrumbs = computed(() => [
    { title: 'Vouchers', href: '/vouchers' },
    { title: props.voucher.voucher_number || 'Voucher Details', href: '#' },
]);
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">

        <Head :title="`Voucher - ${voucher.voucher_number}`" />
        <Toast />

        <div class="grid">
            <!-- Header Actions -->
            <div class="col-12">
                <div class="justify-content-between align-items-center mb-4 flex">
                    <div class="align-items-center flex gap-3">
                        <Button icon="pi pi-arrow-left" text rounded severity="secondary" @click="goBack"
                            v-tooltip="'Go Back'" />
                        <div>
                            <h1 class="mb-1 text-2xl font-bold">
                                {{ voucher.voucher_number }}
                            </h1>
                            <div class="align-items-center flex gap-3">
                                <Tag :value="voucher.status" :severity="getStatusSeverity(voucher.status)
                                    " />
                                <Tag :value="retirementStatus.label" :severity="retirementStatus.severity"
                                    :icon="retirementStatus.icon" v-tooltip.top="retirementStatus.tooltip" />
                                <span class="text-500">
                                    Date: {{ formatDate(voucher.voucher_date) }}
                                </span>
                            </div>
                        </div>
                    </div>

                    <div class="flex gap-2">
                        <Button label="Print" icon="pi pi-print" severity="info" @click="printVoucher" />
                        <Button v-if="voucher.voucher_type === 'prepayment'" label="Retire" icon="pi pi-check-circle"
                            severity="success" :disabled="!canRetireVoucher(voucher)" v-tooltip="canRetireVoucher(voucher)
                                    ? 'Retire Prepayment Voucher'
                                    : 'Cannot retire this voucher'
                                " @click="retireVoucher" />
                        <Button label="Edit Voucher" icon="pi pi-pencil" severity="secondary"
                            :disabled="!canEditVoucher(voucher)" v-tooltip="canEditVoucher(voucher)
                                    ? 'Edit Voucher'
                                    : 'Cannot edit this voucher'
                                " @click="editVoucher" />
                        <Button label="Delete" icon="pi pi-trash" severity="danger"
                            :disabled="!canDeleteVoucher(voucher)" v-tooltip="canDeleteVoucher(voucher)
                                    ? 'Delete Voucher'
                                    : 'Cannot delete this voucher'
                                " @click="deleteVoucher" />
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
                                    <label class="text-500 font-semibold">Voucher Number</label>
                                    <div class="text-900 text-lg font-medium">
                                        {{ voucher.voucher_number }}
                                    </div>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="field">
                                    <label class="text-500 font-semibold">Voucher Date</label>
                                    <div class="text-900">
                                        {{ formatDate(voucher.voucher_date) }}
                                    </div>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="field">
                                    <label class="text-500 font-semibold">Voucher Type</label>
                                    <div class="text-900 font-medium">
                                        <Tag :value="voucher.voucher_type?.toUpperCase() ||
                                            'N/A'
                                            " :severity="voucher.voucher_type ===
                                                    'prepayment'
                                                    ? 'warning'
                                                    : 'info'
                                                " />
                                    </div>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="field">
                                    <label class="text-500 font-semibold">Retirement Status</label>
                                    <div class="text-900 font-medium">
                                        <Tag :value="retirementStatus.label" :severity="retirementStatus.severity
                                            " :icon="retirementStatus.icon" />
                                        <span v-if="voucher.retired_at" class="text-500 ml-2 text-sm">
                                            on
                                            {{ formatDate(voucher.retired_at) }}
                                        </span>
                                    </div>
                                </div>
                            </div>
                            <div class="col-12">
                                <div class="field">
                                    <label class="text-500 font-semibold">MDA</label>
                                    <div class="text-900 font-medium">
                                        <span v-if="voucher.mda">
                                            {{ voucher.mda.name }}
                                            <span class="text-500 ml-2">({{ voucher.mda.code }})</span>
                                        </span>
                                        <span v-else class="text-500">N/A</span>
                                    </div>
                                </div>
                            </div>
                            <div class="col-12">
                                <div class="field">
                                    <label class="text-500 font-semibold">Financial Year</label>
                                    <div class="text-900">
                                        {{ voucher.financial_year || 'N/A' }}
                                    </div>
                                </div>
                            </div>
                            <div class="col-12">
                                <div class="field">
                                    <label class="text-500 font-semibold">Source Schedule</label>
                                    <div class="text-900">
                                        <span v-if="voucher.schedule">
                                            <a :href="`/schedules/${voucher.schedule.id}`"
                                                class="text-primary hover:underline">
                                                {{
                                                    voucher.schedule
                                                        .schedule_number
                                                }}
                                            </a>
                                        </span>
                                        <span v-else class="text-500">No schedule linked</span>
                                    </div>
                                </div>
                            </div>
                            <div class="col-12">
                                <div class="field">
                                    <label class="text-500 font-semibold">Narration</label>
                                    <div class="text-900">
                                        {{
                                            voucher.narration ||
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
                            <div class="col-12" v-if="voucher.payee_name">
                                <div class="field">
                                    <label class="text-500 font-semibold">Payee/Beneficiary</label>
                                    <div class="text-900 text-primary text-2xl font-bold">
                                        {{
                                            voucher?.payee_name
                                        }}
                                    </div>
                                </div>
                            </div>
                            <div v-if="voucher.bankActivity">
                            <div class="col-12">

                                <div class="field">
                                    <label class="text-500 font-semibold">Title:</label>
                                    <div class="text-900 text-primary text-2xl font-bold"> {{ voucher?.bankActivity?.title
                                        }} </div>
                                </div>
                            </div>
                            <div class="col-12">
                                <div class="field"><label class="text-500 font-semibold">Tag:</label>
                                    <div class="text-900 text-primary text-2xl font-bold">{{ voucher?.bankActivity?.tag }}
                                    </div>
                                </div>
                            </div>
                            <div class="col-12">
                                <div class="field"><label class="text-500 font-semibold">Bank Name:</label>
                                    <div class="text-900 text-primary text-2xl font-bold">{{
                                        voucher?.bankActivity?.bank_name }}</div>
                                </div>
                            </div>
                            <div class="col-12">
                                <div class="field"><label class="text-500 font-semibold">Account Number:</label>
                                    <div class="text-900 text-primary text-2xl font-bold">{{
                                        voucher?.bankActivity?.account_number }}</div>
                                </div>
                            </div>

                            </div>

                            <div class="col-12">
                                <div class="field">
                                    <label class="text-500 font-semibold">Total Amount</label>
                                    <div class="text-900 text-primary text-2xl font-bold">
                                        {{
                                            formatCurrency(voucher.total_amount)
                                        }}
                                    </div>
                                </div>
                            </div>
                            <div class="col-12">
                                <div class="field">
                                    <label class="text-500 font-semibold">Amount in Words</label>
                                    <div class="text-900 border-round border-1 border-200 bg-gray-50 p-2 italic">
                                        {{ amountInWords }}
                                    </div>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="field">
                                    <label class="text-500 font-semibold">Status</label>
                                    <div>
                                        <Tag :value="voucher.status" :severity="getStatusSeverity(
                                            voucher.status,
                                        )
                                            " />
                                    </div>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="field">
                                    <label class="text-500 font-semibold">Line Items</label>
                                    <div class="text-900 font-medium">
                                        {{ totalItems }} items
                                    </div>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="field">
                                    <label class="text-500 font-semibold">Created By</label>
                                    <div class="text-900">
                                        {{ voucher.creator?.name || 'N/A' }}
                                    </div>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="field">
                                    <label class="text-500 font-semibold">Created Date</label>
                                    <div class="text-900">
                                        {{ formatDateTime(voucher.created_at) }}
                                    </div>
                                </div>
                            </div>
                        </div>
                    </template>
                </Card>
            </div>

            <!-- Voucher Items -->
            <div class="col-12">
                <Card>
                    <template #title>
                        <div class="justify-content-between align-items-center flex">
                            <div class="align-items-center flex gap-2">
                                <i class="pi pi-list text-primary"></i>
                                <span>Voucher Items ({{ totalItems }})</span>
                            </div>
                            <span class="text-lg font-bold">
                                Total:
                                {{ formatCurrency(voucher.total_amount) }}
                            </span>
                        </div>
                    </template>
                    <template #content>
                        <DataTable :value="voucher.items || []" dataKey="id" stripedRows responsiveLayout="scroll"
                            class="p-datatable-sm" :emptyMessage="'No items found in this voucher.'">
                            <Column field="description" header="Description" headerStyle="width: 40%">
                                <template #body="slotProps">
                                    <span class="font-medium">
                                        {{ slotProps.data.description }}
                                    </span>
                                </template>
                            </Column>

                            <Column field="economy_code" header="Economy Code" headerStyle="width: 15%">
                                <template #body="slotProps">
                                    <div v-if="slotProps.data.economy_code" class="flex-column flex">
                                        <span class="font-medium">
                                            {{
                                                slotProps.data.economy_code.code
                                            }}
                                        </span>
                                        <small class="text-500">
                                            {{
                                                slotProps.data.economy_code.name
                                            }}
                                        </small>
                                    </div>
                                    <span v-else class="text-500">N/A</span>
                                </template>
                            </Column>

                            <Column field="economy_code_item" header="Code Item" headerStyle="width: 15%">
                                <template #body="slotProps">
                                    <div v-if="slotProps.data.economy_code_item" class="flex-column flex">
                                        <span>
                                            {{
                                                slotProps.data.economy_code_item
                                                    .code
                                            }}
                                        </span>
                                        <small class="text-500">
                                            {{
                                                slotProps.data.economy_code_item
                                                    .name
                                            }}
                                        </small>
                                    </div>
                                    <span v-else class="text-500">N/A</span>
                                </template>
                            </Column>

                            <Column field="quantity" header="Qty" headerStyle="width: 8%" bodyClass="text-center">
                                <template #body="slotProps">
                                    <span class="font-mono">
                                        {{ slotProps.data.quantity }}
                                    </span>
                                </template>
                            </Column>

                            <Column field="unit_price" header="Unit Price" headerStyle="width: 12%"
                                bodyClass="text-right">
                                <template #body="slotProps">
                                    <span class="font-medium">
                                        {{
                                            formatCurrency(
                                                slotProps.data.unit_price,
                                            )
                                        }}
                                    </span>
                                </template>
                            </Column>

                            <Column field="sub_total" header="Sub Total" headerStyle="width: 10%"
                                bodyClass="text-right font-bold">
                                <template #body="slotProps">
                                    <span class="text-primary">
                                        {{
                                            formatCurrency(
                                                slotProps.data.sub_total,
                                            )
                                        }}
                                    </span>
                                </template>
                            </Column>
                        </DataTable>

                        <!-- Summary Row -->
                        <div
                            class="justify-content-between align-items-center border-round bg-primary-50 mt-4 flex border-1 border-200 p-3">
                            <span class="text-lg font-bold">Grand Total</span>
                            <span class="text-primary text-xl font-bold">{{
                                formatCurrency(voucher.total_amount)
                                }}</span>
                        </div>
                    </template>
                </Card>
            </div>

            <!-- Documents Section -->
            <div class="col-12 md:col-6" v-if="voucher.documents && voucher.documents.length > 0">
                <Card>
                    <template #title>
                        <div class="align-items-center flex gap-2">
                            <i class="pi pi-file-pdf text-primary"></i>
                            <span>Supporting Documents ({{
                                totalDocuments
                            }})</span>
                        </div>
                    </template>
                    <template #content>
                        <div class="space-y-2">
                            <div v-for="document in voucher.documents" :key="document.id"
                                class="align-items-center justify-content-between border-round flex border-1 border-200 p-3">
                                <div class="align-items-center flex gap-3">
                                    <i v-if="document.is_pdf" class="pi pi-file-pdf text-red-500"></i>
                                    <i v-else-if="document.is_image" class="pi pi-image text-green-500"></i>
                                    <i v-else class="pi pi-file text-blue-500"></i>
                                    <div>
                                        <div class="font-medium">
                                            {{ document.file_name }}
                                        </div>
                                        <div class="text-500 text-sm">
                                            {{ document.document_type_label }} â€¢
                                            {{
                                                (
                                                    document.file_size / 1024
                                                ).toFixed(2)
                                            }}
                                            KB
                                        </div>
                                    </div>
                                </div>
                                <Button icon="pi pi-download" text rounded severity="info"
                                    @click="window.open(document.url, '_blank')" v-tooltip="'Download document'" />
                            </div>
                        </div>
                    </template>
                </Card>
            </div>

            <!-- Approval History -->
            <div class="col-12" :class="voucher.documents && voucher.documents.length > 0
                    ? 'md:col-6'
                    : 'md:col-12'
                ">
                <Card>
                    <template #title>
                        <div class="align-items-center flex gap-2">
                            <i class="pi pi-history text-primary"></i>
                            <span>Approval History</span>
                        </div>
                    </template>
                    <template #content>
                        <div v-if="
                            voucher.approvals &&
                            voucher.approvals.length > 0
                        " class="space-y-3">
                            <div v-for="approval in voucher.approvals" :key="approval.id"
                                class="border-round border-1 border-200 p-3">
                                <div class="justify-content-between flex">
                                    <div>
                                        <div class="font-medium">
                                            {{
                                                approval.user?.name ||
                                                'Unknown User'
                                            }}
                                            <Tag :value="approval.approval_role" severity="info" class="ml-2" />
                                        </div>
                                        <div class="text-500 mt-1 text-sm">
                                            Step {{ approval.approval_step }} â€¢
                                            {{
                                                formatDateTime(
                                                    approval.action_at,
                                                )
                                            }}
                                        </div>
                                    </div>
                                    <div>
                                        <Tag :value="approval.action" :severity="{
                                                Approved: 'success',
                                                Declined: 'danger',
                                                'Sent Back': 'warning',
                                                Forwarded: 'info',
                                                Saved: 'secondary',
                                            }[approval.action] || 'info'
                                            " />
                                    </div>
                                </div>
                                <div v-if="approval.comment" class="text-500 mt-2 border-200 border-l-3 pl-2 text-sm">
                                    <i class="pi pi-comment mr-1"></i>
                                    {{ approval.comment }}
                                </div>
                            </div>
                        </div>
                        <div v-else class="text-500 p-4 text-center">
                            <i class="pi pi-info-circle mr-2"></i>
                            No approval history available
                        </div>
                    </template>
                </Card>
            </div>
        </div>

        <!-- Confirmation Modal -->
        <Dialog v-model:visible="showConfirmationModal" :style="{ width: '450px' }" :header="currentAction === 'retire'
                ? 'Retire Prepayment Voucher'
                : 'Delete Voucher'
            " :modal="true">
            <div class="align-items-center flex">
                <i v-if="currentAction === 'delete'" class="pi pi-exclamation-triangle mr-3 text-red-500"
                    style="font-size: 2rem"></i>
                <i v-else-if="currentAction === 'retire'" class="pi pi-check-circle mr-3 text-green-500"
                    style="font-size: 2rem"></i>

                <div>
                    <span v-if="currentAction === 'delete'">
                        Are you sure you want to
                        <strong class="text-red-600">permanently delete</strong>
                        Voucher
                        <strong>{{ voucher.voucher_number }}</strong>? This action cannot be undone.
                    </span>

                    <span v-else-if="currentAction === 'retire'">
                        Are you sure you want to
                        <strong class="text-green-600">retire</strong>
                        Prepayment Voucher
                        <strong>{{ voucher.voucher_number }}</strong>?
                        <div class="text-500 mt-2 text-sm">
                            <i class="pi pi-info-circle mr-1"></i>
                            Retiring marks this prepayment voucher as completed.
                            This action cannot be undone.
                        </div>
                    </span>
                </div>
            </div>

            <template #footer>
                <Button label="Cancel" icon="pi pi-times" @click="showConfirmationModal = false" text />

                <Button :label="currentAction === 'delete'
                        ? 'Yes, Delete'
                        : 'Yes, Retire'
                    " :icon="currentAction === 'delete'
                            ? 'pi pi-trash'
                            : 'pi pi-check-circle'
                        " :severity="currentAction === 'delete' ? 'danger' : 'success'
                        " @click="confirmAction" autofocus />
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

.space-y-2>*+* {
    margin-top: 0.5rem;
}

.space-y-3>*+* {
    margin-top: 0.75rem;
}
</style>
