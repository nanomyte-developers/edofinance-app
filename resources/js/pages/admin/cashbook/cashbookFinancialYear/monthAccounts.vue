<script setup>
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, router } from '@inertiajs/vue3';
import axios from 'axios';
import { ref } from 'vue';

import Badge from 'primevue/badge';
import Button from 'primevue/button';
import Card from 'primevue/card';
import ProgressBar from 'primevue/progressbar';
import Tag from 'primevue/tag';
import Toast from 'primevue/toast';
import { useToast } from 'primevue/usetoast';

// Define props with proper types
const props = defineProps({
    year: Object,
    month_id: {
        type: Number,
        required: true,
    },
    month_name: String,
    cashbooks: Array,
    summary: Object,
});

// Ensure month_id is a number
const monthId = Number(props.month_id);

const toast = useToast();
const loadingId = ref(null);

const formatCurrency = (val) =>
    new Intl.NumberFormat('en-NG', {
        style: 'currency',
        currency: 'NGN',
    }).format(val || 0);

const getStatusLabel = (status) => {
    const s = status ? status.toLowerCase() : '';
    switch (s) {
        case 'open':
            return 'Open';
        case 'closed':
            return 'Closed';
        case 'processed':
            return 'Processed';
        default:
            return 'Unknown';
    }
};

const getStatusSeverity = (status) => {
    const s = status ? status.toLowerCase() : '';
    switch (s) {
        case 'open':
            return 'warning';
        case 'closed':
            return 'danger';
        case 'processed':
            return 'success';
        default:
            return 'secondary';
    }
};

// Calculate progress percentage for each account
const calculateProgress = (cashbook) => {
    if (!cashbook.entries || cashbook.entries.length === 0) return 0;

    const totalEntries = cashbook.entries.length;
    const processedEntries = cashbook.entries.filter((e) => e.processed).length;

    return Math.round((processedEntries / totalEntries) * 100);
};

// Generate ledger entries for specific cashbook
const generateLedger = async (cashbook) => {
    loadingId.value = cashbook.id;

    try {
        const response = await axios.post(
            `/cashbook/${cashbook.id}/generate-entries`,
        );

        if (response.data.success) {
            toast.add({
                severity: 'success',
                summary: 'Success',
                detail: `Generated ${response.data.entries_count} entries`,
                life: 3000,
            });

            // Refresh the data
            router.reload();
        } else {
            throw new Error(response.data.message);
        }
    } catch (error) {
        toast.add({
            severity: 'error',
            summary: 'Error',
            detail: error.message || 'Failed to generate ledger',
            life: 3000,
        });
    } finally {
        loadingId.value = null;
    }
};

// Navigate to view cashbook ledger
const viewLedger = (cashbook) => {
    router.visit(
        `/cashbook/generate/${cashbook.month_id}/${cashbook.year}?account_id=${cashbook.bank_activities_id}`,
    );
};

// Navigate back to months view
const goBackToMonths = () => {
    router.visit(`/cashbook-years/${props.year.id}/months`);
};
</script>

<template>
    <AppLayout>
        <Head :title="`${month_name} - ${year?.name || 'Financial Year'}`" />
        <Toast />

        <div class="card">
            <!-- Header -->
            <div
                class="flex-column md:justify-content-between md:align-items-center mb-5 flex md:flex-row"
            >
                <div>
                    <Button
                        icon="pi pi-arrow-left"
                        class="p-button-text p-button-rounded mr-3"
                        @click="goBackToMonths"
                        title="Back to Months"
                    />
                    <h4 class="text-900 m-0 font-bold">
                        {{ month_name }} - {{ year?.name }}
                    </h4>
                    <p class="text-500">
                        Managing all bank accounts for this month
                    </p>
                </div>

                <div class="mt-3 md:mt-0">
                    <div class="align-items-center flex gap-3">
                        <Badge
                            :value="summary.total_accounts"
                            severity="info"
                            class="mr-2"
                        >
                            <span class="text-sm">Accounts</span>
                        </Badge>
                        <span class="text-600">
                            Total Balance:
                            {{ formatCurrency(summary.total_closing_balance) }}
                        </span>
                    </div>
                </div>
            </div>

            <!-- Summary Cards -->
            <div class="mb-5 grid">
                <div class="col-12 md:col-3">
                    <Card class="surface-50 border-1">
                        <template #title>
                            <div class="text-500 text-sm">Total Accounts</div>
                        </template>
                        <template #content>
                            <div class="text-900 text-2xl font-bold">
                                {{ summary.total_accounts }}
                            </div>
                            <div class="align-items-center mt-2 flex">
                                <Tag
                                    value="Active"
                                    severity="success"
                                    class="mr-2"
                                />
                                <span class="text-sm">{{
                                    summary.active_accounts
                                }}</span>
                            </div>
                        </template>
                    </Card>
                </div>
                <div class="col-12 md:col-3">
                    <Card class="surface-50 border-1">
                        <template #title>
                            <div class="text-500 text-sm">Opening Balance</div>
                        </template>
                        <template #content>
                            <div class="text-900 text-2xl font-bold">
                                {{
                                    formatCurrency(
                                        summary.total_opening_balance,
                                    )
                                }}
                            </div>
                        </template>
                    </Card>
                </div>
                <div class="col-12 md:col-3">
                    <Card class="surface-50 border-1">
                        <template #title>
                            <div class="text-500 text-sm">Closing Balance</div>
                        </template>
                        <template #content>
                            <div class="text-900 text-2xl font-bold">
                                {{
                                    formatCurrency(
                                        summary.total_closing_balance,
                                    )
                                }}
                            </div>
                        </template>
                    </Card>
                </div>
                <div class="col-12 md:col-3">
                    <Card class="surface-50 border-1">
                        <template #title>
                            <div class="text-500 text-sm">Status Overview</div>
                        </template>
                        <template #content>
                            <div class="flex-column flex gap-2">
                                <div class="justify-content-between flex">
                                    <span class="text-sm">Processed</span>
                                    <Tag
                                        :value="summary.processed_accounts || 0"
                                        severity="success"
                                    />
                                </div>
                                <div class="justify-content-between flex">
                                    <span class="text-sm">Open</span>
                                    <Tag
                                        :value="summary.open_accounts || 0"
                                        severity="warning"
                                    />
                                </div>
                            </div>
                        </template>
                    </Card>
                </div>
            </div>

            <!-- Bank Accounts Grid -->
            <div class="grid">
                <div
                    v-for="cashbook in cashbooks"
                    :key="cashbook.id"
                    class="col-12 md:col-6 lg:col-4 xl:col-3"
                >
                    <Card class="hover:shadow-3 h-full border-1 transition-all">
                        <template #title>
                            <div
                                class="justify-content-between align-items-start flex"
                            >
                                <div>
                                    <div class="text-900 text-lg font-bold">
                                        {{
                                            cashbook.bank_account?.title ||
                                            'Unknown Account'
                                        }}. ({{ cashbook.bank_account?.tag }})
                                    </div>
                                    <small class="text-500 block">
                                        {{
                                            cashbook.bank_account
                                                ?.account_number ||
                                            'No Account Number'
                                        }}
                                    </small>
                                </div>
                                <div
                                    class="flex-column align-items-end flex gap-1"
                                >
                                    <Tag
                                        :value="getStatusLabel(cashbook.status)"
                                        :severity="
                                            getStatusSeverity(cashbook.status)
                                        "
                                    />
                                    <small
                                        v-if="cashbook.entries?.length > 0"
                                        class="text-500 text-xs"
                                    >
                                        {{ cashbook.entries.length }} entries
                                    </small>
                                </div>
                            </div>
                        </template>

                        <template #subtitle>
                            <div class="align-items-center mt-2 flex gap-2">
                                <i class="pi pi-bank text-500"></i>
                                <span class="text-sm">{{
                                    cashbook.bank_account?.bank_name ||
                                    'Unknown Bank'
                                }}</span>
                            </div>
                        </template>

                        <template #content>
                            <div class="flex-column flex gap-3">
                                <!-- Balance Information -->
                                <div class="border-top-1 border-bottom-1 py-3">
                                    <div
                                        class="justify-content-between mb-2 flex"
                                    >
                                        <span class="text-500"
                                            >Opening Balance</span
                                        >
                                        <span class="font-medium">{{
                                            formatCurrency(
                                                cashbook.opening_balance,
                                            )
                                        }}</span>
                                    </div>
                                    <div class="justify-content-between flex">
                                        <span class="text-500"
                                            >Closing Balance</span
                                        >
                                        <span
                                            class="font-bold"
                                            :class="{
                                                'text-green-600':
                                                    cashbook.closing_balance >
                                                    0,
                                                'text-red-600':
                                                    cashbook.closing_balance <
                                                    0,
                                                'text-600':
                                                    cashbook.closing_balance ===
                                                    0,
                                            }"
                                        >
                                            {{
                                                formatCurrency(
                                                    cashbook.closing_balance,
                                                )
                                            }}
                                        </span>
                                    </div>
                                </div>

                                <!-- Entries Summary -->
                                <div class="border-bottom-1 py-3">
                                    <div
                                        class="justify-content-between mb-2 flex"
                                    >
                                        <span class="text-500"
                                            >Total Entries</span
                                        >
                                        <Badge
                                            :value="
                                                cashbook.entries?.length || 0
                                            "
                                            severity="info"
                                        />
                                    </div>
                                    <div
                                        v-if="
                                            cashbook.entries &&
                                            cashbook.entries.length > 0
                                        "
                                    >
                                        <div class="text-500 mb-1 text-sm">
                                            Processing Progress
                                        </div>
                                        <ProgressBar
                                            :value="calculateProgress(cashbook)"
                                            :showValue="false"
                                            class="mb-2"
                                        />
                                        <small class="text-500">
                                            {{ calculateProgress(cashbook) }}%
                                            Complete
                                        </small>
                                    </div>
                                    <div v-else class="py-2 text-center">
                                        <i
                                            class="pi pi-inbox text-300 text-2xl"
                                        ></i>
                                        <p class="text-500 mt-2 text-sm">
                                            No entries yet
                                        </p>
                                    </div>
                                </div>

                                <!-- Quick Stats -->
                                <div class="grid">
                                    <div class="col-6">
                                        <div
                                            class="surface-50 border-round p-2 text-center"
                                        >
                                            <div
                                                class="font-bold text-green-600"
                                            >
                                                {{
                                                    formatCurrency(
                                                        cashbook.total_remittances ||
                                                            0,
                                                    )
                                                }}
                                            </div>
                                            <small class="text-500"
                                                >Receipts</small
                                            >
                                        </div>
                                    </div>
                                    <div class="col-6">
                                        <div
                                            class="surface-50 border-round p-2 text-center"
                                        >
                                            <div class="font-bold text-red-600">
                                                {{
                                                    formatCurrency(
                                                        cashbook.total_payments ||
                                                            0,
                                                    )
                                                }}
                                            </div>
                                            <small class="text-500"
                                                >Payments</small
                                            >
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </template>

                        <template #footer>
                            <div class="justify-content-between flex gap-2">
                                <Button
                                    :label="
                                        loadingId === cashbook.id
                                            ? 'Generating...'
                                            : 'Generate'
                                    "
                                    :icon="
                                        loadingId === cashbook.id
                                            ? 'pi pi-spin pi-spinner'
                                            : 'pi pi-calculator'
                                    "
                                    class="p-button-outlined p-button-success flex-1"
                                    @click="generateLedger(cashbook)"
                                    :disabled="
                                        cashbook.status === 'closed' ||
                                        loadingId === cashbook.id
                                    "
                                    :title="'Generate ledger entries from receipts and vouchers'"
                                />
                                <Button
                                    label="View"
                                    icon="pi pi-eye"
                                    class="p-button-outlined p-button-info flex-1"
                                    @click="viewLedger(cashbook)"
                                    :title="'View cashbook with accounting layout'"
                                />
                                <!-- <Button
                                    label="View"
                                    icon="pi pi-eye"
                                    class="p-button-outlined p-button-info flex-1"
                                    @click="viewLedger(cashbook)"
                                    :disabled="
                                        !cashbook.entries ||
                                        cashbook.entries.length === 0
                                    "
                                    :title="'View cashbook with accounting layout'"
                                /> -->
                            </div>
                        </template>
                    </Card>
                </div>
            </div>

            <!-- Empty State -->
            <div
                v-if="cashbooks.length === 0"
                class="surface-50 border-round border-1 border-300 p-8 text-center"
            >
                <i class="pi pi-briefcase text-400 mb-3 text-4xl"></i>
                <p class="text-600 text-xl font-medium">
                    No bank accounts found for {{ month_name }}
                </p>
                <p class="text-500">
                    There are no bank accounts configured for this month.
                </p>
                <Button
                    label="Configure Accounts"
                    icon="pi pi-cog"
                    class="p-button-outlined mt-3"
                    @click="goBackToMonths"
                />
            </div>
        </div>
    </AppLayout>
</template>

<style scoped>
.card {
    transition: all 0.3s ease;
}

.card:hover {
    transform: translateY(-2px);
    box-shadow: 0 10px 20px rgba(0, 0, 0, 0.1);
}

:deep(.p-card) {
    height: 100%;
    display: flex;
    flex-direction: column;
}

:deep(.p-card-content) {
    flex-grow: 1;
}

:deep(.p-card-footer) {
    margin-top: auto;
}
</style>
