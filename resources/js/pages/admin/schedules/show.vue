<script setup>
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, router } from '@inertiajs/vue3';
import Button from 'primevue/button';
import Card from 'primevue/card';
import Column from 'primevue/column';
import DataTable from 'primevue/datatable';
import Tag from 'primevue/tag';
import Toast from 'primevue/toast';
import { useToast } from 'primevue/usetoast';
import { computed, ref } from 'vue';

const toast = useToast();

// ðŸ’¡ PROPS: Receive schedule data from Laravel controller
const props = defineProps({
    schedule: {
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
        case 'voucher raised':
        case 'processed':
        case 'approved':
            return 'success';
        case 'rejected':
        case 'declined':
            return 'danger';
        case 'returned':
        case 'needs attention':
            return 'warning';
        case 'submitted':
        case 'awaiting voucher':
            return 'secondary';
        case 'draft':
        case 'saved':
            return 'info';
        default:
            return 'info';
    }
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
const printSchedule = () => {
    const printUrl = `/schedules/${props.schedule.id}/print`;
    window.open(printUrl, '_blank');
};

const editSchedule = () => {
    router.visit(`/schedules/${props.schedule.id}/edit`);
};

const goBack = () => {
    window.history.back();
};

const viewVoucher = () => {
    if (props.schedule.voucher_id) {
        router.visit(`/vouchers/${props.schedule.voucher_id}`);
    } else {
        toast.add({
            severity: 'warn',
            summary: 'No Voucher',
            detail: 'This schedule does not have an associated voucher yet.',
            life: 3000,
        });
    }
};

// Computed properties
const amountInWords = computed(() => {
    return convertNumberToWords(props.schedule.total_amount || 0);
});

const totalItems = computed(() => {
    return props.schedule.items?.length || 0;
});

// Breadcrumbs
const breadcrumbs = ref([
    { title: 'Schedules', href: '/schedules' },
    { title: props.schedule.schedule_number || 'Schedule Details', href: '#' },
]);
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head :title="`Schedule - ${schedule.schedule_number}`" />
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
                                {{ schedule.schedule_number }}
                            </h1>
                            <div class="align-items-center flex gap-3">
                                <Tag
                                    :value="schedule.status"
                                    :severity="
                                        getStatusSeverity(schedule.status)
                                    "
                                />
                                <span class="text-500">
                                    Created: {{ formatDate(schedule.date) }}
                                </span>
                            </div>
                        </div>
                    </div>

                    <div class="flex gap-2">
                        <Button
                            label="Print"
                            icon="pi pi-print"
                            severity="info"
                            @click="printSchedule"
                        />
                        <Button
                            v-if="schedule.voucher_id"
                            label="View Voucher"
                            icon="pi pi-file"
                            severity="success"
                            @click="viewVoucher"
                        />
                        <Button
                            label="Edit Schedule"
                            icon="pi pi-pencil"
                            severity="secondary"
                            @click="editSchedule"
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
                                        >Schedule Number</label
                                    >
                                    <div class="text-900 text-lg font-medium">
                                        {{ schedule.schedule_number }}
                                    </div>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="field">
                                    <label class="text-500 font-semibold"
                                        >Schedule Date</label
                                    >
                                    <div class="text-900">
                                        {{ formatDate(schedule.date) }}
                                    </div>
                                </div>
                            </div>
                            <div class="col-12">
                                <div class="field">
                                    <label class="text-500 font-semibold"
                                        >MDA</label
                                    >
                                    <div class="text-900 font-medium">
                                        <span v-if="schedule.mda">
                                            {{ schedule.mda.name }}
                                            <span class="text-500 ml-2"
                                                >({{ schedule.mda.code }})</span
                                            >
                                        </span>
                                        <span v-else class="text-500">N/A</span>
                                    </div>
                                </div>
                            </div>
                            <div class="col-12">
                                <div class="field">
                                    <label class="text-500 font-semibold"
                                        >Financial Year</label
                                    >
                                    <div class="text-900">
                                        {{ schedule.financial_year || 'N/A' }}
                                    </div>
                                </div>
                            </div>
                            <div class="col-12">
                                <div class="field">
                                    <label class="text-500 font-semibold"
                                        >Administrative Code</label
                                    >
                                    <div class="text-900 font-mono">
                                        {{ schedule.budget_code || 'N/A' }}
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
                                            schedule.narration ||
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
                                        {{
                                            formatCurrency(
                                                schedule.total_amount,
                                            )
                                        }}
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
                                        >Status</label
                                    >
                                    <div>
                                        <Tag
                                            :value="schedule.status"
                                            :severity="
                                                getStatusSeverity(
                                                    schedule.status,
                                                )
                                            "
                                        />
                                    </div>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="field">
                                    <label class="text-500 font-semibold"
                                        >Line Items</label
                                    >
                                    <div class="text-900 font-medium">
                                        {{ totalItems }} items
                                    </div>
                                </div>
                            </div>
                        </div>
                    </template>
                </Card>
            </div>

            <!-- Schedule Items -->
            <div class="col-12">
                <Card>
                    <template #title>
                        <div
                            class="justify-content-between align-items-center flex"
                        >
                            <div class="align-items-center flex gap-2">
                                <i class="pi pi-list text-primary"></i>
                                <span>Schedule Items ({{ totalItems }})</span>
                            </div>
                            <span class="text-lg font-bold">
                                Total:
                                {{ formatCurrency(schedule.total_amount) }}
                            </span>
                        </div>
                    </template>
                    <template #content>
                        <DataTable
                            :value="schedule.items || []"
                            dataKey="id"
                            stripedRows
                            responsiveLayout="scroll"
                            class="p-datatable-sm"
                            :emptyMessage="'No items found in this schedule.'"
                        >
                            <Column
                                field="serial_no"
                                header="Serial No."
                                headerStyle="width: 8%"
                            >
                                <template #body="slotProps">
                                    <span class="font-mono font-bold">{{
                                        slotProps.data.serial_no
                                    }}</span>
                                </template>
                            </Column>

                            <Column
                                field="date"
                                header="Date"
                                headerStyle="width: 10%"
                            >
                                <template #body="slotProps">
                                    {{ slotProps.data.date }}
                                </template>
                            </Column>

                            <Column
                                field="economy_code"
                                header="Economic Code"
                                headerStyle="width: 20%"
                            >
                                <template #body="slotProps">
                                    <div class="flex-column flex">
                                        <span class="font-medium">{{
                                            slotProps.data.economy_code
                                        }}</span>
                                    </div>
                                </template>
                            </Column>

                            <Column
                                field="economy_code_item"
                                header="Economic Code Item"
                                headerStyle="width: 20%"
                            >
                                <template #body="slotProps">
                                    <span>{{
                                        slotProps.data.economy_code_item ||
                                        'N/A'
                                    }}</span>
                                </template>
                            </Column>

                            <Column
                                field="payee"
                                header="Payee"
                                headerStyle="width: 25%"
                            >
                                <template #body="slotProps">
                                    <span class="font-medium">{{
                                        slotProps.data.payee
                                    }}</span>
                                </template>
                            </Column>

                            <Column
                                field="amount"
                                header="Amount"
                                headerStyle="width: 17%"
                                bodyClass="text-right"
                            >
                                <template #body="slotProps">
                                    <span class="font-bold">{{
                                        formatCurrency(slotProps.data.amount)
                                    }}</span>
                                </template>
                            </Column>
                        </DataTable>

                        <!-- Summary Row -->
                        <div
                            class="justify-content-between align-items-center border-round bg-primary-50 mt-4 flex border-1 border-200 p-3"
                        >
                            <span class="text-lg font-bold">Grand Total</span>
                            <span class="text-primary text-xl font-bold">{{
                                formatCurrency(schedule.total_amount)
                            }}</span>
                        </div>
                    </template>
                </Card>
            </div>

            <!-- Authorization Section -->
            <div class="col-12">
                <Card>
                    <template #title>
                        <div class="flex align-items-center gap-2">
                            <i class="pi pi-shield text-primary"></i>
                            <span>Authorization</span>
                        </div>
                    </template>
                    <template #content>
                        <div class="grid">
                            <div class="col-12">
                                <div class="authorization-section border-1 surface-border border-round p-4 surface-50">
                                    <p class="text-center font-medium text-color-secondary mb-4">
                                        The Treasury Cash Officer at the Treasury Cash Office, Benin City 
                                        is authorized to make the following payments chargeable to the 
                                        above head of expenditure.
                                    </p>

                                    <div class="flex justify-content-between mt-4">
                                        <div class="text-center flex-1">
                                            <div class="border-top-1 surface-border pt-3">
                                                <div class="font-semibold text-color">Prepared By</div>
                                                <div class="text-color-secondary mt-2 text-sm">
                                                    __________________________
                                                </div>
                                                <div class="text-color-secondary mt-1 text-sm">
                                                    Account Officer
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <div class="text-center flex-1">
                                            <div class="border-top-1 surface-border pt-3">
                                                <div class="font-semibold text-color">Verified By</div>
                                                <div class="text-color-secondary mt-2 text-sm">
                                                    __________________________
                                                </div>
                                                <div class="text-color-secondary mt-1 text-sm">
                                                    Supervising Officer
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <div class="text-center flex-1">
                                            <div class="border-top-1 surface-border pt-3">
                                                <div class="font-semibold text-color">Approved By</div>
                                                <div class="text-color-secondary mt-2 text-sm">
                                                    __________________________
                                                </div>
                                                <div class="text-color-secondary mt-1 text-sm">
                                                    Authorized Officer
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </template>
                </Card>
            </div>
        </div>
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

.authorization-text {
    font-style: italic;
}

.bg-gray-50 {
    background-color: var(--surface-50);
}

.bg-primary-50 {
    background-color: var(--primary-50);
    border-color: var(--primary-200);
}
</style>
