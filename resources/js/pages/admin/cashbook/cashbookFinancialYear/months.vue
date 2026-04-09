<script setup>
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, router } from '@inertiajs/vue3';
import axios from 'axios';
import Button from 'primevue/button';
import Checkbox from 'primevue/checkbox';
import Dropdown from 'primevue/dropdown';
import Tag from 'primevue/tag';
import Toast from 'primevue/toast';
import { useToast } from 'primevue/usetoast';
import { computed, ref, watch } from 'vue';

const props = defineProps({
    year: Object,
    accounts: Array,
});

const toast = useToast();
const loadingId = ref(null);
const selectedAccount = ref(
    props.accounts && props.accounts.length > 0 ? props.accounts[0] : null,
);
const selectedMonths = ref([]);
const isSelectAll = ref(false);
const isBulkGenerating = ref(false);
const progressToastRef = ref(null);

// Fixed array of month names
const MONTH_NAMES = [
    'January',
    'February',
    'March',
    'April',
    'May',
    'June',
    'July',
    'August',
    'September',
    'October',
    'November',
    'December',
];

// Watch for account changes to reset selections
watch(selectedAccount, () => {
    selectedMonths.value = [];
    isSelectAll.value = false;
});

/**
 * Get month name from month ID (1-12)
 */
const getMonthName = (m) => {
    if (!m) return 'Unknown Month';

    // Convert to number
    const monthNum = parseInt(m);

    // Validate month number
    if (isNaN(monthNum) || monthNum < 1 || monthNum > 12) {
        return 'Unknown Month';
    }

    // Return month name (monthNum is 1-12, array index is 0-11)
    return MONTH_NAMES[monthNum - 1];
};

const formatCurrency = (val) =>
    new Intl.NumberFormat('en-NG', {
        style: 'currency',
        currency: 'NGN',
    }).format(val || 0);

const getStatusSeverity = (status) => {
    const s = status ? status.toLowerCase() : '';
    return s === 'open' ? 'success' : 'danger';
};

/**
 * Get all unique months (1-12) for the selected account
 */
const allMonths = computed(() => {
    if (!selectedAccount.value?.cashbooks) return [];

    // Get unique months by month_id
    const monthMap = new Map();

    selectedAccount.value.cashbooks.forEach((cashbook) => {
        // Only keep the first occurrence of each month_id
        if (!monthMap.has(cashbook.month_id)) {
            monthMap.set(cashbook.month_id, cashbook);
        }
    });

    // Convert map to array and sort by month_id (1-12)
    const uniqueMonths = Array.from(monthMap.values()).sort(
        (a, b) => a.month_id - b.month_id,
    );

    // If we don't have all 12 months, create placeholders
    if (uniqueMonths.length < 12) {
        const year =
            uniqueMonths[0]?.year ||
            props.year?.year ||
            new Date().getFullYear();
        const existingMonthIds = uniqueMonths.map((m) => m.month_id);

        // Create missing months 1-12
        for (let monthId = 1; monthId <= 12; monthId++) {
            if (!existingMonthIds.includes(monthId)) {
                uniqueMonths.push({
                    id: monthId * 1000, // Unique ID for placeholder
                    month_id: monthId,
                    year: year,
                    status: 'no-data',
                    closing_balance: 0,
                    opening_balance: 0,
                    is_placeholder: true,
                });
            }
        }

        // Sort again
        uniqueMonths.sort((a, b) => a.month_id - b.month_id);
    }

    return uniqueMonths;
});

/**
 * Toggle select all months
 */
const toggleSelectAll = () => {
    if (isSelectAll.value) {
        // Only select non-placeholder months
        selectedMonths.value = allMonths.value
            .filter((month) => !month.is_placeholder)
            .map((month) => month.id);
    } else {
        selectedMonths.value = [];
    }
};

/**
 * Toggle individual month selection
 */
const toggleMonthSelection = (monthId) => {
    // Don't allow selection of placeholder months
    const month = allMonths.value.find((m) => m.id === monthId);
    if (month?.is_placeholder) return;

    const index = selectedMonths.value.indexOf(monthId);
    if (index > -1) {
        selectedMonths.value.splice(index, 1);
    } else {
        selectedMonths.value.push(monthId);
    }

    // Update select all checkbox
    const selectableMonths = allMonths.value.filter((m) => !m.is_placeholder);
    isSelectAll.value = selectedMonths.value.length === selectableMonths.length;
};

/**
 * Navigate to view all accounts for a specific month
 */
const viewMonthAccounts = (month_id) => {
    const monthId = Number(month_id);
    const url = `/cashbook-years/${props.year.id}/month/${monthId}`;
    router.visit(url);
};

/**
 * Generate cashbook for individual month
 */
const generateIndividualCashbook = (month) => {
    if (month.is_placeholder) {
        toast.add({
            severity: 'warn',
            summary: 'No Data',
            detail: 'Cannot generate cashbook for month without data',
            life: 3000,
        });
        return;
    }

    loadingId.value = month.id;
    const url = `/cashbook/generate/${month.month_id}/${month.year}`;
    const accountId = selectedAccount.value?.id;
    const fullUrl = accountId ? `${url}?account_id=${accountId}` : url;
    window.location.href = fullUrl;
};

/**
 * Generate cashbook for selected months using batch API
 */
const generateSelectedCashbooks = async () => {
    if (selectedMonths.value.length === 0) {
        toast.add({
            severity: 'warn',
            summary: 'No Selection',
            detail: 'Please select at least one month to generate cashbooks',
            life: 3000,
        });
        return;
    }

    isBulkGenerating.value = true;

    try {
        // Get selected month details (excluding placeholders)
        const selectedMonthDetails = allMonths.value.filter(
            (month) =>
                selectedMonths.value.includes(month.id) &&
                !month.is_placeholder,
        );

        const monthIds = selectedMonthDetails.map((month) => month.month_id);

        // Get the year from the first selected month or the props year
        const year =
            selectedMonthDetails[0]?.year ||
            props.year?.year ||
            new Date().getFullYear();

        console.log('Batch generation request (ALL ACCOUNTS):', {
            month_ids: monthIds,
            year: year,
            cashbook_financial_year_id: props.year.id,
            selectedMonths: selectedMonthDetails,
        });

        // Show progress toast - store the reference properly
        progressToastRef.value = toast.add({
            severity: 'info',
            summary: 'Processing',
            detail: `Generating cashbooks for ${monthIds.length} months across ALL bank accounts...`,
            sticky: true,
        });

        // Prepare batch request (NO account_id needed - will process all accounts)
        const response = await axios.post('/cashbook/generate-batch-entries', {
            month_ids: monthIds,
            year: year,
            cashbook_financial_year_id: props.year.id,
        });

        // Remove progress toast using the stored reference
        if (progressToastRef.value) {
            toast.remove(progressToastRef.value);
            progressToastRef.value = null;
        }

        console.log('Batch generation response:', response.data);

        if (response.data.success) {
            // Show success summary
            const summary = response.data.summary;
            toast.add({
                severity: 'success',
                summary: 'Batch Generation Complete',
                detail: `Processed ${summary.total_cashbooks} cashbooks (${summary.total_months} months × ${summary.total_accounts} accounts). ${summary.successful} succeeded, ${summary.failed} failed. Total entries: ${summary.total_entries}`,
                life: 8000,
            });

            // Log detailed results
            console.log(
                'Batch generation detailed results:',
                response.data.results,
            );

            // Show success/failure summary
            const successes = response.data.results.filter((r) => r.success);
            const failures = response.data.results.filter((r) => !r.success);

            console.log(`✓ ${successes.length} cashbooks succeeded`);
            console.log(`✗ ${failures.length} cashbooks failed`);

            // Show first 5 failures if any
            if (failures.length > 0) {
                failures.slice(0, 5).forEach((failure) => {
                    console.error(
                        `✗ ${failure.month_name} - ${failure.bank_account_name}: ${failure.message}`,
                    );
                });

                // Show failure summary toast
                if (failures.length <= 5) {
                    failures.forEach((failure) => {
                        toast.add({
                            severity: 'warn',
                            summary: `Failed: ${failure.month_name} - ${failure.bank_account_name}`,
                            detail:
                                failure.message.substring(0, 80) +
                                (failure.message.length > 80 ? '...' : ''),
                            life: 4000,
                        });
                    });
                } else {
                    toast.add({
                        severity: 'warn',
                        summary: 'Multiple Failures',
                        detail: `${failures.length} cashbooks failed. Showing first 5. Check console for details.`,
                        life: 5000,
                    });

                    failures.slice(0, 5).forEach((failure) => {
                        toast.add({
                            severity: 'warn',
                            summary: `Failed: ${failure.month_name} - ${failure.bank_account_name}`,
                            detail:
                                failure.message.substring(0, 80) +
                                (failure.message.length > 80 ? '...' : ''),
                            life: 4000,
                        });
                    });
                }
            }

            // Refresh the page to show updated data
            setTimeout(() => {
                router.reload();
            }, 3000);
        } else {
            throw new Error(response.data.message || 'Batch generation failed');
        }

        // Reset selections
        selectedMonths.value = [];
        isSelectAll.value = false;
    } catch (error) {
        console.error('Batch generation error:', error);

        // Clean up progress toast on error
        if (progressToastRef.value) {
            toast.remove(progressToastRef.value);
            progressToastRef.value = null;
        }

        let errorMessage = error.message || 'Failed to generate cashbooks';
        if (error.response?.data?.message) {
            errorMessage = error.response.data.message;
        } else if (error.response?.data?.error) {
            errorMessage = error.response.data.error;
        }

        console.error('Error details:', {
            error: error,
            response: error.response,
            data: error.response?.data,
        });

        toast.add({
            severity: 'error',
            summary: 'Error',
            detail:
                errorMessage.substring(0, 200) +
                (errorMessage.length > 200 ? '...' : ''),
            life: 5000,
        });
    } finally {
        isBulkGenerating.value = false;
    }
};

/**
 * View ledger for specific cashbook
 */
const viewLedger = (cashbookId) => {
    const url = `/cashbook/${cashbookId}/ledger`;
    router.visit(url);
};

/**
 * View month accounts for all selected months
 */
const viewSelectedMonthAccounts = () => {
    if (selectedMonths.value.length === 0) {
        toast.add({
            severity: 'warn',
            summary: 'No Selection',
            detail: 'Please select at least one month to view accounts',
            life: 3000,
        });
        return;
    }

    if (selectedMonths.value.length === 1) {
        // If only one selected, go directly to that month
        const month = allMonths.value.find(
            (m) => m.id === selectedMonths.value[0],
        );
        if (month && !month.is_placeholder) {
            viewMonthAccounts(month.month_id);
        }
    } else {
        // For multiple selection, show message
        toast.add({
            severity: 'info',
            summary: 'Multiple Selection',
            detail: 'Please select only one month to view its accounts',
            life: 3000,
        });
    }
};

/**
 * Clear all selections
 */
const clearSelections = () => {
    selectedMonths.value = [];
    isSelectAll.value = false;
};
</script>

<template>
    <AppLayout>
        <Head :title="`Months for ${year?.name || 'Financial Year'}`" />
        <Toast />

        <div class="card">
            <!-- Header with Account Selection -->
            <div
                class="flex-column md:justify-content-between md:align-items-center mb-5 flex md:flex-row"
            >
                <div>
                    <h4 class="text-900 m-0 font-bold">
                        {{ year?.name || 'Financial Year' }}
                    </h4>
                    <p class="text-500">
                        Select a bank account to manage monthly ledgers
                    </p>
                </div>

                <div class="mt-3 md:mt-0">
                    <div class="flex-column flex gap-2">
                        <label class="text-900 font-medium"
                            >Current Ledger Account</label
                        >
                        <Dropdown
                            v-model="selectedAccount"
                            :options="accounts"
                            optionLabel="title"
                            placeholder="Select Bank Account"
                            class="md:w-25rem w-full"
                            filter
                        >
                            <template #option="slotProps">
                                <div class="flex-column flex">
                                    <span class="font-bold">{{
                                        slotProps.option.title
                                    }}</span>
                                    <small class="text-500"
                                        >{{ slotProps.option.bank_name }} -
                                        {{
                                            slotProps.option.account_number
                                        }}</small
                                    >
                                </div>
                            </template>
                        </Dropdown>
                    </div>
                </div>
            </div>

            <!-- Bulk Actions Toolbar -->
            <div
                v-if="selectedAccount && allMonths.length > 0"
                class="surface-50 border-round mb-4 flex flex-wrap items-center justify-between gap-3 p-4"
            >
                <div class="flex items-center gap-3">
                    <Checkbox
                        v-model="isSelectAll"
                        :binary="true"
                        @change="toggleSelectAll"
                        :disabled="!selectedAccount"
                    />
                    <span class="font-medium">
                        {{ selectedMonths.length }} month(s) selected
                    </span>
                    <Button
                        v-if="selectedMonths.length > 0"
                        label="Clear"
                        icon="pi pi-times"
                        class="p-button-text p-button-sm"
                        @click="clearSelections"
                        size="small"
                    />
                </div>

                <div class="flex flex-wrap gap-2">
                    <Button
                        label="View Selected Accounts"
                        icon="pi pi-eye"
                        class="p-button-outlined p-button-info"
                        @click="viewSelectedMonthAccounts"
                        :disabled="selectedMonths.length === 0"
                        size="small"
                    />
                    <Button
                        :label="
                            isBulkGenerating
                                ? 'Generating...'
                                : `Generate ${selectedMonths.length} Months (All Accounts)`
                        "
                        :icon="
                            isBulkGenerating
                                ? 'pi pi-spin pi-spinner'
                                : 'pi pi-calculator'
                        "
                        class="p-button-outlined p-button-success"
                        @click="generateSelectedCashbooks"
                        :disabled="
                            selectedMonths.length === 0 || isBulkGenerating
                        "
                        size="small"
                    />
                </div>
            </div>

            <hr class="border-top-1 surface-border mb-5" />

            <!-- Months Grid -->
            <div class="grid" v-if="selectedAccount && allMonths.length > 0">
                <div
                    v-for="month in allMonths"
                    :key="month.id"
                    class="col-12 md:col-4 lg:col-3"
                >
                    <div
                        class="card shadow-1 border-round surface-card hover:shadow-3 transition-duration-200 border-top-3 m-0 cursor-pointer border-blue-500 p-4 transition-all"
                        @click="
                            !month.is_placeholder &&
                            viewMonthAccounts(month.month_id)
                        "
                        :class="{ 'opacity-60': month.is_placeholder }"
                    >
                        <!-- Month Selection Checkbox -->
                        <div class="absolute top-3 right-3 z-10" @click.stop>
                            <Checkbox
                                :modelValue="selectedMonths.includes(month.id)"
                                @change="toggleMonthSelection(month.id)"
                                :binary="true"
                                :disabled="month.is_placeholder"
                            />
                        </div>

                        <div class="justify-content-between mb-3 flex">
                            <div>
                                <span class="text-500 mb-2 block font-medium">
                                    {{ getMonthName(month.month_id) }}
                                    {{ month.year }}
                                </span>
                                <div class="text-900 text-xl font-bold">
                                    {{ formatCurrency(month.closing_balance) }}
                                </div>
                            </div>
                            <div
                                class="align-items-center justify-content-center border-round flex bg-blue-50"
                                style="width: 2.5rem; height: 2.5rem"
                            >
                                <i
                                    class="pi pi-calendar text-xl text-blue-500"
                                ></i>
                            </div>
                        </div>

                        <Tag
                            :severity="
                                month.is_placeholder
                                    ? 'secondary'
                                    : getStatusSeverity(month.status)
                            "
                            :value="
                                month.is_placeholder
                                    ? 'NO DATA'
                                    : month.status
                                      ? month.status.toUpperCase()
                                      : 'UNKNOWN'
                            "
                            class="mb-4"
                        />

                        <div class="border-top-1 surface-border pt-3">
                            <!-- Button to view all accounts in this month -->
                            <button
                                v-if="!month.is_placeholder"
                                @click.stop="viewMonthAccounts(month.month_id)"
                                class="p-button p-button-outlined p-button-primary align-items-center justify-content-center flex w-full"
                                style="
                                    border-radius: 6px;
                                    cursor: pointer;
                                    margin-bottom: 0.5rem;
                                "
                            >
                                <i class="pi pi-users mr-2"></i>
                                <span class="font-bold">View All Accounts</span>
                            </button>
                            <div
                                v-else
                                class="text-500 mb-2 py-2 text-center text-sm"
                            >
                                No data available
                            </div>

                            <!-- Button to generate/view ledger for this specific account/month -->
                            <button
                                v-if="!month.is_placeholder"
                                @click.stop="generateIndividualCashbook(month)"
                                :disabled="loadingId === month.id"
                                class="p-button p-button-outlined p-button-secondary align-items-center justify-content-center flex w-full"
                                style="border-radius: 6px; cursor: pointer"
                            >
                                <i
                                    :class="
                                        loadingId === month.id
                                            ? 'pi pi-spin pi-spinner'
                                            : 'pi pi-calculator'
                                    "
                                    class="mr-2"
                                ></i>
                                <span class="font-bold">{{
                                    loadingId === month.id
                                        ? 'Generating...'
                                        : 'Generate Cashbook'
                                }}</span>
                            </button>
                            <div
                                v-else
                                class="text-500 py-2 text-center text-sm"
                            >
                                Cannot generate
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Empty State -->
            <div
                v-else
                class="surface-50 border-round border-1 border-300 p-8 text-center"
            >
                <i class="pi pi-search text-400 mb-3 text-4xl"></i>
                <p class="text-600 text-xl font-medium">
                    No bank account selected.
                </p>
                <p class="text-500">
                    Please choose a treasury account from the dropdown above.
                </p>
            </div>
        </div>
    </AppLayout>
</template>

<style scoped>
/* Ensure the card interaction feels premium */
.surface-card {
    cursor: default;
    border: 1px solid var(--surface-border);
    transition: all 0.3s ease;
    position: relative;
}

.surface-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 10px 20px rgba(0, 0, 0, 0.1) !important;
}

.p-button-outlined {
    padding: 0.5rem 1rem;
    transition: background-color 0.2s;
}

.p-button-outlined:hover {
    background: var(--blue-50) !important;
}

/* Fix for the float label spacing if needed */
.field {
    margin-bottom: 0;
}

/* Make the entire month card clickable */
.cursor-pointer {
    cursor: pointer;
}

/* Position the checkbox absolutely */
.absolute {
    position: absolute;
}

.top-3 {
    top: 1rem;
}

.right-3 {
    right: 1rem;
}

.z-10 {
    z-index: 10;
}

/* Small button styling */
.p-button-sm {
    padding: 0.25rem 0.5rem;
    font-size: 0.875rem;
}

/* Style for placeholder months */
.opacity-60 {
    opacity: 0.6;
}
</style>
