<script setup>
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, router, usePage } from '@inertiajs/vue3';
import axios from 'axios';
import Button from 'primevue/button';
import Card from 'primevue/card';
import Column from 'primevue/column';
import DataTable from 'primevue/datatable';
import Dialog from 'primevue/dialog';
import Message from 'primevue/message';
import ProgressSpinner from 'primevue/progressspinner';
import Tag from 'primevue/tag';
import Textarea from 'primevue/textarea';
import Toast from 'primevue/toast';
import { useToast } from 'primevue/usetoast';
import { computed, onMounted, ref } from 'vue';

const toast = useToast();
const page = usePage();

// Props: Receive journal data from Laravel controller
const props = defineProps({
    journal: {
        type: Object,
        required: true,
        default: () => ({}),
    },
    gl_accounts: {
        type: Array,
        default: () => [],
    },
});

// State
const showConfirmationModal = ref(false);
const currentAction = ref(null);
const isLoading = ref(false);

// User info from Laravel backend
const currentUser = computed(() => page.props.auth?.user || {});
const currentUserId = computed(() => currentUser.value?.id);

// Breadcrumbs
const breadcrumbs = computed(() => [
    { title: 'Journals', href: '/journals' },
    { title: props.journal.journal_number || 'Journal Details', href: '#' },
]);

// Computed properties
const totalDebit = computed(() => {
    return props.journal.entries?.reduce((sum, entry) => sum + (parseFloat(entry.debit_amount) || 0), 0) || 0;
});

const totalCredit = computed(() => {
    return props.journal.entries?.reduce((sum, entry) => sum + (parseFloat(entry.credit_amount) || 0), 0) || 0;
});

const isBalanced = computed(() => {
    return Math.abs(totalDebit.value - totalCredit.value) < 0.01;
});

const balanceDifference = computed(() => {
    return Math.abs(totalDebit.value - totalCredit.value);
});

const entryCount = computed(() => {
    return props.journal.entries?.length || 0;
});

// Status severity
const getStatusSeverity = (status) => {
    if (!status) return 'info';
    const normalizedStatus = status.toLowerCase().trim();

    switch (normalizedStatus) {
        case 'draft':
        case 'saved':
            return 'warning';
        case 'pending':
        case 'submitted':
            return 'info';
        case 'approved':
        case 'completed':
        case 'posted':
            return 'success';
        case 'declined':
        case 'rejected':
        case 'failed':
        case 'returned':
        case 'sent back':
        case 'cancelled':
            return 'danger';
        default:
            return 'secondary';
    }
};

// Permission checks
const canEditJournal = (journal) => {
    if (!journal || !journal.status) return false;
    const status = journal.status.toLowerCase().trim();
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

const canDeleteJournal = (journal) => {
    if (!journal || !journal.status) return false;
    const status = journal.status.toLowerCase().trim();
    const deletableStatuses = ['draft', 'saved'];
    return deletableStatuses.includes(status);
};

const canApproveJournal = (journal) => {
    if (!journal || !journal.status) return false;
    const status = journal.status.toLowerCase().trim();
    return ['pending', 'submitted', 'draft', 'saved'].includes(status) && 
           currentUser.value?.can_approve_journals === true;
};

const canReverseJournal = (journal) => {
    if (!journal || !journal.status) return false;
    const status = journal.status.toLowerCase().trim();
    return ['approved', 'posted', 'completed'].includes(status) && 
           currentUser.value?.can_reverse_journals === true;
};

// Tooltip messages
const editTooltip = computed(() => {
    if (!canEditJournal(props.journal)) {
        return `Cannot edit - Status: ${props.journal.status}`;
    }
    return 'Edit Journal';
});

const deleteTooltip = computed(() => {
    if (!canDeleteJournal(props.journal)) {
        return `Cannot delete - Status: ${props.journal.status}`;
    }
    return 'Delete Journal';
});

const approveTooltip = computed(() => {
    if (!canApproveJournal(props.journal)) {
        return `Cannot approve - Status: ${props.journal.status}`;
    }
    return 'Approve Journal';
});

const reverseTooltip = computed(() => {
    if (!canReverseJournal(props.journal)) {
        return `Cannot reverse - Status: ${props.journal.status}`;
    }
    return 'Reverse Journal';
});

// Formatting functions
const formatCurrency = (value) => {
    return new Intl.NumberFormat('en-NG', {
        style: 'currency',
        currency: 'NGN',
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    }).format(value || 0);
};

const formatDate = (dateString) => {
    if (!dateString) return 'N/A';
    try {
        const date = new Date(dateString);
        if (isNaN(date.getTime())) return 'N/A';
        return date.toLocaleDateString('en-NG', {
            year: 'numeric',
            month: 'short',
            day: 'numeric',
        });
    } catch (e) {
        return 'N/A';
    }
};

const formatDateTime = (dateString) => {
    if (!dateString) return 'N/A';
    try {
        const date = new Date(dateString);
        if (isNaN(date.getTime())) return 'N/A';
        return date.toLocaleString('en-NG', {
            year: 'numeric',
            month: 'short',
            day: 'numeric',
            hour: '2-digit',
            minute: '2-digit',
        });
    } catch (e) {
        return 'N/A';
    }
};

const getEntryType = (entry) => {
    if (entry.debit_amount > 0) return 'Debit';
    if (entry.credit_amount > 0) return 'Credit';
    return 'N/A';
};

const getEntryTypeSeverity = (entry) => {
    if (entry.debit_amount > 0) return 'danger';
    if (entry.credit_amount > 0) return 'success';
    return 'info';
};

// Navigation
const goBack = () => {
    window.history.back();
};

const goToEdit = () => {
    if (!canEditJournal(props.journal)) {
        toast.add({
            severity: 'error',
            summary: 'Cannot Edit',
            detail: `Journal ${props.journal.journal_number} is "${props.journal.status}" and cannot be edited.`,
            life: 5000,
        });
        return;
    }
    router.visit(`/journals/${props.journal.id}/edit`);
};

// Print journal
const printJournal = () => {
    const printUrl = `/journals/${props.journal.id}/print`;
    window.open(printUrl, '_blank');
};

// Delete journal
const deleteJournal = () => {
    if (!canDeleteJournal(props.journal)) {
        toast.add({
            severity: 'error',
            summary: 'Cannot Delete',
            detail: `Journal ${props.journal.journal_number} is "${props.journal.status}" and cannot be deleted.`,
            life: 5000,
        });
        return;
    }
    currentAction.value = 'delete';
    showConfirmationModal.value = true;
};

const confirmDelete = () => {
    showConfirmationModal.value = false;

    router.delete(`/journals/${props.journal.id}`, {
        preserveScroll: true,
        onSuccess: () => {
            toast.add({
                severity: 'success',
                summary: 'Deleted',
                detail: `Journal ${props.journal.journal_number} successfully deleted.`,
                life: 3000,
            });
            router.visit('/journals');
        },
        onError: (errors) => {
            const detail = errors.message || 'Failed to delete the journal.';
            toast.add({
                severity: 'error',
                summary: 'Error',
                detail: detail,
                life: 5000,
            });
        },
    });
};

// Approve journal
const approveJournal = async () => {
    if (!canApproveJournal(props.journal)) return;

    try {
        isLoading.value = true;
        
        const response = await axios.post(`/journals/${props.journal.id}/approve`, {
            approved_by: currentUserId.value,
            remarks: 'Approved via journal details page',
        });

        if (response.data.success) {
            toast.add({
                severity: 'success',
                summary: 'Journal Approved',
                detail: `Journal ${props.journal.journal_number} has been approved successfully.`,
                life: 3000,
            });
            
            // Refresh the page to show updated status
            router.reload({ only: ['journal'] });
        } else {
            throw new Error(response.data.message || 'Approval failed');
        }
    } catch (error) {
        console.error('Error approving journal:', error);
        
        let errorMessage = 'Failed to approve journal.';
        if (error.response?.data?.message) {
            errorMessage = error.response.data.message;
        } else if (error.response?.data?.errors) {
            errorMessage = Object.values(error.response.data.errors).flat().join(', ');
        }
        
        toast.add({
            severity: 'error',
            summary: 'Approval Failed',
            detail: errorMessage,
            life: 5000,
        });
    } finally {
        isLoading.value = false;
    }
};

// Reverse journal
const reverseJournal = async () => {
    if (!canReverseJournal(props.journal)) return;

    try {
        currentAction.value = 'reverse';
        showConfirmationModal.value = true;
    } catch (error) {
        console.error('Error reversing journal:', error);
        toast.add({
            severity: 'error',
            summary: 'Error',
            detail: 'Failed to initiate reversal.',
            life: 5000,
        });
    }
};

const confirmReverse = async () => {
    showConfirmationModal.value = false;

    try {
        isLoading.value = true;
        
        const response = await axios.post(`/journals/${props.journal.id}/reverse`, {
            reversed_by: currentUserId.value,
            remarks: 'Reversed via journal details page',
        });

        if (response.data.success) {
            toast.add({
                severity: 'success',
                summary: 'Journal Reversed',
                detail: `Journal ${props.journal.journal_number} has been reversed successfully.`,
                life: 3000,
            });
            
            // Refresh the page to show updated status
            router.reload({ only: ['journal'] });
        } else {
            throw new Error(response.data.message || 'Reversal failed');
        }
    } catch (error) {
        console.error('Error reversing journal:', error);
        
        let errorMessage = 'Failed to reverse journal.';
        if (error.response?.data?.message) {
            errorMessage = error.response.data.message;
        } else if (error.response?.data?.errors) {
            errorMessage = Object.values(error.response.data.errors).flat().join(', ');
        }
        
        toast.add({
            severity: 'error',
            summary: 'Reversal Failed',
            detail: errorMessage,
            life: 5000,
        });
    } finally {
        isLoading.value = false;
    }
};

// Copy journal number to clipboard
const copyJournalNumber = () => {
    if (!props.journal.journal_number) return;
    
    navigator.clipboard.writeText(props.journal.journal_number).then(() => {
        toast.add({
            severity: 'info',
            summary: 'Copied',
            detail: 'Journal number copied to clipboard',
            life: 2000,
        });
    }).catch(err => {
        console.error('Failed to copy: ', err);
    });
};

// Export journal entries to CSV
const exportToCSV = () => {
    if (!props.journal.entries || props.journal.entries.length === 0) {
        toast.add({
            severity: 'warn',
            summary: 'No Data',
            detail: 'No journal entries to export',
            life: 3000,
        });
        return;
    }

    try {
        const headers = ['#', 'Account Code', 'Description', 'Debit (₦)', 'Credit (₦)', 'Type'];
        const csvData = props.journal.entries.map((entry, index) => [
            index + 1,
            entry.account_code || '',
            entry.description || '',
            entry.debit_amount || 0,
            entry.credit_amount || 0,
            getEntryType(entry)
        ]);

        const csvContent = [
            headers.join(','),
            ...csvData.map(row => row.join(','))
        ].join('\n');

        const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
        const link = document.createElement('a');
        const url = URL.createObjectURL(blob);
        
        link.setAttribute('href', url);
        link.setAttribute('download', `${props.journal.journal_number}_entries.csv`);
        link.style.visibility = 'hidden';
        
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);

        toast.add({
            severity: 'success',
            summary: 'Exported',
            detail: `Journal entries exported to CSV`,
            life: 3000,
        });
    } catch (error) {
        console.error('Error exporting CSV:', error);
        toast.add({
            severity: 'error',
            summary: 'Export Failed',
            detail: 'Failed to export journal entries',
            life: 5000,
        });
    }
};

// Lifecycle hooks
onMounted(() => {
    // Log view activity
    console.log('Journal loaded:', props.journal);
});
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head :title="`Journal - ${journal.journal_number}`" />
        <Toast />

        <div class="grid">
            <!-- Header Actions -->
            <div class="col-12">
                <div class="mb-4 flex items-center justify-between">
                    <div class="flex items-center gap-3">
                        <Button
                            icon="pi pi-arrow-left"
                            text
                            rounded
                            severity="secondary"
                            @click="goBack"
                            v-tooltip="'Go Back'"
                        />
                        <div>
                            <div class="flex items-center gap-2">
                                <h1 class="mb-1 text-2xl font-bold">
                                    {{ journal.journal_number }}
                                    <Button
                                        icon="pi pi-copy"
                                        text
                                        rounded
                                        severity="secondary"
                                        @click="copyJournalNumber"
                                        v-tooltip="'Copy journal number'"
                                        class="ml-2"
                                    />
                                </h1>
                            </div>
                            <div class="flex items-center gap-3">
                                <Tag
                                    :value="journal.status"
                                    :severity="getStatusSeverity(journal.status)"
                                />
                                <span class="text-gray-500">
                                    Date: {{ formatDate(journal.journal_date) }}
                                </span>
                                <span class="text-gray-500">
                                    | Entries: {{ entryCount }}
                                </span>
                                <span class="text-red-500">
                                    | Total Debit: {{ formatCurrency(totalDebit) }}
                                </span>
                                <span class="text-green-500">
                                    | Total Credit: {{ formatCurrency(totalCredit) }}
                                </span>
                                <Tag
                                    :value="isBalanced ? 'Balanced' : 'Not Balanced'"
                                    :severity="isBalanced ? 'success' : 'danger'"
                                    size="small"
                                />
                            </div>
                        </div>
                    </div>

                    <div class="flex gap-2">
                        <!-- Approve Button -->
                        <Button
                            v-if="canApproveJournal(journal)"
                            label="Approve"
                            icon="pi pi-check"
                            severity="success"
                            :disabled="isLoading"
                            v-tooltip="approveTooltip"
                            @click="approveJournal"
                        />

                        <!-- Reverse Button -->
                        <Button
                            v-if="canReverseJournal(journal)"
                            label="Reverse"
                            icon="pi pi-undo"
                            severity="warning"
                            :disabled="isLoading"
                            v-tooltip="reverseTooltip"
                            @click="reverseJournal"
                        />

                        <Button
                            label="Print"
                            icon="pi pi-print"
                            severity="info"
                            @click="printJournal"
                        />
                        
                        <Button
                            label="Edit"
                            icon="pi pi-pencil"
                            severity="secondary"
                            :disabled="!canEditJournal(journal)"
                            v-tooltip="editTooltip"
                            @click="goToEdit"
                        />
                        
                        <Button
                            label="Delete"
                            icon="pi pi-trash"
                            severity="danger"
                            :disabled="!canDeleteJournal(journal)"
                            v-tooltip="deleteTooltip"
                            @click="deleteJournal"
                        />
                    </div>
                </div>
            </div>

            <!-- Journal Information -->
            <div class="col-12 lg:col-8">
                <Card>
                    <template #title>
                        <div class="flex items-center gap-2">
                            <i class="pi pi-info-circle text-primary"></i>
                            <span>Journal Information</span>
                        </div>
                    </template>
                    <template #content>
                        <div class="grid">
                            <!-- Row 1: Journal Number & Dates -->
                            <div class="col-12 md:col-4">
                                <div class="space-y-1">
                                    <label class="text-sm font-semibold text-gray-500">
                                        Journal Number
                                    </label>
                                    <div class="text-lg font-bold">
                                        {{ journal.journal_number || 'N/A' }}
                                    </div>
                                </div>
                            </div>
                            
                            <div class="col-12 md:col-4">
                                <div class="space-y-1">
                                    <label class="text-sm font-semibold text-gray-500">
                                        Journal Date
                                    </label>
                                    <div class="text-lg">
                                        {{ formatDate(journal.journal_date) }}
                                    </div>
                                </div>
                            </div>
                            
                            <!-- <div class="col-12 md:col-4">
                                <div class="space-y-1">
                                    <label class="text-sm font-semibold text-gray-500">
                                        Posting Date
                                    </label>
                                    <div class="text-lg">
                                        {{ formatDate(journal.posting_date) }}
                                    </div>
                                </div>
                            </div> -->

                            <!-- Row 2: Description -->
                            <div class="col-12">
                                <div class="space-y-1">
                                    <label class="text-sm font-semibold text-gray-500">
                                        Description
                                    </label>
                                    <div class="text-lg">
                                        {{ journal.description || 'No description' }}
                                    </div>
                                </div>
                            </div>

                            <!-- Row 3: MDA & Economic Code -->
                            <div class="col-12 md:col-6" v-if="journal.mda">
                                <div class="space-y-1">
                                    <label class="text-sm font-semibold text-gray-500">
                                        MDA
                                    </label>
                                    <div class="flex items-center gap-2">
                                        <Tag :value="journal.mda.code" severity="info" size="small" />
                                        <span class="text-lg">{{ journal.mda.name }}</span>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="col-12 md:col-6" v-if="journal.economic_code">
                                <div class="space-y-1">
                                    <label class="text-sm font-semibold text-gray-500">
                                        Economic Code
                                    </label>
                                    <div class="flex items-center gap-2">
                                        <Tag :value="journal.economic_code.code" severity="warning" size="small" />
                                        <span class="text-lg">{{ journal.economic_code.name }}</span>
                                    </div>
                                </div>
                            </div>

                            <!-- Row 4: Administrative Codes -->
                            <div class="col-12 md:col-6" v-if="journal.administrative_code">
                                <div class="space-y-1">
                                    <label class="text-sm font-semibold text-gray-500">
                                        Administrative Code
                                    </label>
                                    <div class="flex items-center gap-2">
                                        <Tag :value="journal.administrative_code.code" severity="success" size="small" />
                                        <span class="text-lg">{{ journal.administrative_code.name }}</span>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="col-12 md:col-6" v-if="journal.administrative_sector_code">
                                <div class="space-y-1">
                                    <label class="text-sm font-semibold text-gray-500">
                                        Administrative Sector
                                    </label>
                                    <div class="flex items-center gap-2">
                                        <Tag :value="journal.administrative_sector_code.code" severity="help" size="small" />
                                        <span class="text-lg">
                                            {{ journal.administrative_sector_code.name }}
                                            <span class="text-sm text-gray-500 ml-2">
                                                ({{ journal.administrative_sector_code.type }})
                                            </span>
                                        </span>
                                    </div>
                                </div>
                            </div>

                            <!-- Row 5: Reference & Batch -->
                            <div class="col-12 md:col-6" v-if="journal.reference_number">
                                <div class="space-y-1">
                                    <label class="text-sm font-semibold text-gray-500">
                                        Reference Number
                                    </label>
                                    <div class="text-lg font-medium">
                                        {{ journal.reference_number }}
                                    </div>
                                </div>
                            </div>
                            
                            <div class="col-12 md:col-6" v-if="journal.batch_number">
                                <div class="space-y-1">
                                    <label class="text-sm font-semibold text-gray-500">
                                        Batch Number
                                    </label>
                                    <div class="text-lg font-medium">
                                        {{ journal.batch_number }}
                                    </div>
                                </div>
                            </div>

                            <!-- Row 6: Financial Year & Status -->
                            <!-- <div class="col-12 md:col-6">
                                <div class="space-y-1">
                                    <label class="text-sm font-semibold text-gray-500">
                                        Financial Year
                                    </label>
                                    <div class="text-lg">
                                        {{ journal.financial_year || 'N/A' }}
                                    </div>
                                </div>
                            </div> -->
                            
                            <div class="col-12 md:col-6">
                                <div class="space-y-1">
                                    <label class="text-sm font-semibold text-gray-500">
                                        Status
                                    </label>
                                    <div>
                                        <Tag
                                            :value="journal.status"
                                            :severity="getStatusSeverity(journal.status)"
                                            class="text-sm font-semibold"
                                        />
                                    </div>
                                </div>
                            </div>

                            <!-- Row 7: Creator & Dates -->
                            <div class="col-12 md:col-6">
                                <div class="space-y-1">
                                    <label class="text-sm font-semibold text-gray-500">
                                        Created By
                                    </label>
                                    <div class="text-lg">
                                        {{ journal.creator?.name || 'System' }}
                                        <span class="text-sm text-gray-500 ml-2">
                                            ({{ formatDateTime(journal.created_at) }})
                                        </span>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="col-12 md:col-6" v-if="journal.approver">
                                <div class="space-y-1">
                                    <label class="text-sm font-semibold text-gray-500">
                                        Approved By
                                    </label>
                                    <div class="text-lg">
                                        {{ journal.approver.name }}
                                        <span class="text-sm text-gray-500 ml-2">
                                            ({{ formatDateTime(journal.approved_at) }})
                                        </span>
                                    </div>
                                </div>
                            </div>

                            <!-- Row 8: Remarks -->
                            <div class="col-12" v-if="journal.remarks">
                                <div class="space-y-1">
                                    <label class="text-sm font-semibold text-gray-500">
                                        Remarks / Notes
                                    </label>
                                    <div class="border-l-4 border-gray-200 pl-3 italic text-gray-600">
                                        {{ journal.remarks }}
                                    </div>
                                </div>
                            </div>
                        </div>
                    </template>
                </Card>
            </div>

            <!-- Financial Summary -->
            <div class="col-12 lg:col-4">
                <Card>
                    <template #title>
                        <div class="flex items-center gap-2">
                            <i class="pi pi-calculator text-primary"></i>
                            <span>Financial Summary</span>
                        </div>
                    </template>
                    <template #content>
                        <div class="space-y-4">
                            <!-- Total Amount -->
                            <!-- <div class="rounded-lg bg-gray-50 p-4">
                                <div class="text-center">
                                    <div class="text-sm font-semibold text-gray-500">
                                        Total Amount
                                    </div>
                                    <div class="text-3xl font-bold text-primary">
                                        {{ formatCurrency(journal.total_amount) }}
                                    </div>
                                </div>
                            </div> -->

                            <!-- Debit & Credit Breakdown -->
                            <div class="grid">
                                <div class="col-6">
                                    <div class="text-center">
                                        <div class="text-sm font-semibold text-red-500">
                                            Total Debit
                                        </div>
                                        <div class="text-xl font-bold text-red-600">
                                            {{ formatCurrency(totalDebit) }}
                                        </div>
                                    </div>
                                </div>
                                <div class="col-6">
                                    <div class="text-center">
                                        <div class="text-sm font-semibold text-green-500">
                                            Total Credit
                                        </div>
                                        <div class="text-xl font-bold text-green-600">
                                            {{ formatCurrency(totalCredit) }}
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Balance Status -->
                            <div class="rounded-lg p-4" :class="{
                                'bg-green-50': isBalanced,
                                'bg-red-50': !isBalanced
                            }">
                                <div class="text-center">
                                    <div class="text-sm font-semibold" :class="{
                                        'text-green-600': isBalanced,
                                        'text-red-600': !isBalanced
                                    }">
                                        Balance Status
                                    </div>
                                    <div class="text-xl font-bold" :class="{
                                        'text-green-600': isBalanced,
                                        'text-red-600': !isBalanced
                                    }">
                                        {{ formatCurrency(balanceDifference) }}
                                    </div>
                                    <div class="mt-1 text-sm" :class="{
                                        'text-green-600': isBalanced,
                                        'text-red-600': !isBalanced
                                    }">
                                        {{ isBalanced ? '✓ Balanced' : '✗ Not Balanced' }}
                                    </div>
                                </div>
                            </div>

                            <!-- Entry Count -->
                            <div class="rounded-lg bg-blue-50 p-4">
                                <div class="text-center">
                                    <div class="text-sm font-semibold text-blue-500">
                                        Journal Entries
                                    </div>
                                    <div class="text-2xl font-bold text-blue-600">
                                        {{ entryCount }}
                                    </div>
                                    <div class="mt-1 text-sm text-blue-600">
                                        {{ entryCount === 1 ? 'entry' : 'entries' }}
                                    </div>
                                </div>
                            </div>

                            <!-- Quick Actions -->
                            <div class="space-y-2">
                                <Button
                                    label="Export Entries (CSV)"
                                    icon="pi pi-file-export"
                                    severity="secondary"
                                    outlined
                                    class="w-full"
                                    @click="exportToCSV"
                                />
                                <Button
                                    label="Print Journal"
                                    icon="pi pi-print"
                                    severity="info"
                                    outlined
                                    class="w-full"
                                    @click="printJournal"
                                />
                            </div>
                        </div>
                    </template>
                </Card>
            </div>

            <!-- Journal Entries -->
            <div class="col-12">
                <Card>
                    <template #title>
                        <div class="flex items-center justify-between">
                            <div class="flex items-center gap-2">
                                <i class="pi pi-table text-primary"></i>
                                <span>Journal Entries ({{ entryCount }})</span>
                            </div>
                            <!-- <div class="flex items-center gap-2">
                                <span class="text-lg font-bold">
                                    Total: {{ formatCurrency(journal.total_amount) }}
                                </span>
                            </div> -->
                        </div>
                    </template>
                    <template #content>
                        <DataTable
                            :value="journal.entries || []"
                            dataKey="id"
                            stripedRows
                            responsiveLayout="scroll"
                            class="p-datatable-sm"
                            :emptyMessage="'No entries found in this journal.'"
                        >
                            <Column
                                field="line_number"
                                header="#"
                                headerStyle="width: 5%"
                                bodyClass="text-center"
                            >
                                <template #body="slotProps">
                                    <span class="font-mono">
                                        {{ slotProps.data.line_number || slotProps.index + 1 }}
                                    </span>
                                </template>
                            </Column>

                            <Column
                                field="account_code"
                                header="Account Code"
                                headerStyle="width: 15%"
                            >
                                <template #body="slotProps">
                                    <span class="font-medium">
                                        {{ slotProps.data.account_code }}
                                    </span>
                                    <div v-if="slotProps.data.account_name" class="text-xs text-gray-500">
                                        {{ slotProps.data.account_name }}
                                    </div>
                                </template>
                            </Column>

                            <Column
                                field="description"
                                header="Description"
                                headerStyle="width: 30%"
                            >
                                <template #body="slotProps">
                                    <span class="font-medium">
                                        {{ slotProps.data.description || 'No description' }}
                                    </span>
                                </template>
                            </Column>

                            <Column
                                field="debit_amount"
                                header="Debit (₦)"
                                headerStyle="width: 15%"
                                bodyClass="text-right"
                            >
                                <template #body="slotProps">
                                    <div v-if="slotProps.data.debit_amount > 0">
                                        <span class="font-bold text-red-600">
                                            {{ formatCurrency(slotProps.data.debit_amount) }}
                                        </span>
                                        <Tag
                                            value="DR"
                                            severity="danger"
                                            size="small"
                                            class="ml-2"
                                        />
                                    </div>
                                    <span v-else class="text-gray-400">-</span>
                                </template>
                            </Column>

                            <Column
                                field="credit_amount"
                                header="Credit (₦)"
                                headerStyle="width: 15%"
                                bodyClass="text-right"
                            >
                                <template #body="slotProps">
                                    <div v-if="slotProps.data.credit_amount > 0">
                                        <span class="font-bold text-green-600">
                                            {{ formatCurrency(slotProps.data.credit_amount) }}
                                        </span>
                                        <Tag
                                            value="CR"
                                            severity="success"
                                            size="small"
                                            class="ml-2"
                                        />
                                    </div>
                                    <span v-else class="text-gray-400">-</span>
                                </template>
                            </Column>

                            <Column
                                field="entry_type"
                                header="Type"
                                headerStyle="width: 10%"
                            >
                                <template #body="slotProps">
                                    <Tag
                                        :value="getEntryType(slotProps.data)"
                                        :severity="getEntryTypeSeverity(slotProps.data)"
                                        class="font-semibold"
                                    />
                                </template>
                            </Column>

                            <Column
                                header="Additional Info"
                                headerStyle="width: 10%"
                            >
                                <template #body="slotProps">
                                    <div class="text-xs text-gray-500">
                                        <div v-if="slotProps.data.cost_center">
                                            CC: {{ slotProps.data.cost_center }}
                                        </div>
                                        <div v-if="slotProps.data.project_code">
                                            Project: {{ slotProps.data.project_code }}
                                        </div>
                                        <div v-if="slotProps.data.reference">
                                            Ref: {{ slotProps.data.reference }}
                                        </div>
                                    </div>
                                </template>
                            </Column>
                        </DataTable>

                        <!-- Totals Row -->
                        <div class="mt-4 rounded-lg border border-gray-200 bg-gray-50 p-3">
                            <div class="grid">
                                <div class="col-4">
                                    <div class="text-center">
                                        <div class="text-sm font-semibold text-gray-500">
                                            Total Debit
                                        </div>
                                        <div class="text-xl font-bold text-red-600">
                                            {{ formatCurrency(totalDebit) }}
                                        </div>
                                    </div>
                                </div>
                                <div class="col-4">
                                    <div class="text-center">
                                        <div class="text-sm font-semibold text-gray-500">
                                            Total Credit
                                        </div>
                                        <div class="text-xl font-bold text-green-600">
                                            {{ formatCurrency(totalCredit) }}
                                        </div>
                                    </div>
                                </div>
                                <div class="col-4">
                                    <div class="text-center">
                                        <div class="text-sm font-semibold text-gray-500">
                                            Balance Difference
                                        </div>
                                        <div class="text-xl font-bold" :class="{
                                            'text-green-600': isBalanced,
                                            'text-red-600': !isBalanced
                                        }">
                                            {{ formatCurrency(balanceDifference) }}
                                        </div>
                                        <div class="mt-1 text-sm" :class="{
                                            'text-green-600': isBalanced,
                                            'text-red-600': !isBalanced
                                        }">
                                            {{ isBalanced ? '✓ Balanced' : '✗ Not Balanced' }}
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </template>
                </Card>
            </div>

            <!-- Audit Trail / Activity Log -->
            <div class="col-12" v-if="journal.audit_trails && journal.audit_trails.length > 0">
                <Card>
                    <template #title>
                        <div class="flex items-center gap-2">
                            <i class="pi pi-history text-primary"></i>
                            <span>Audit Trail</span>
                        </div>
                    </template>
                    <template #content>
                        <div class="space-y-3">
                            <div
                                v-for="audit in journal.audit_trails"
                                :key="audit.id"
                                class="rounded-lg border border-gray-200 p-3"
                            >
                                <div class="flex justify-between">
                                    <div>
                                        <div class="font-medium">
                                            {{ audit.user?.name || 'System' }}
                                            <Tag
                                                :value="audit.action"
                                                severity="info"
                                                class="ml-2"
                                            />
                                        </div>
                                        <div class="mt-1 text-sm text-gray-500">
                                            {{ formatDateTime(audit.created_at) }}
                                        </div>
                                    </div>
                                    <div>
                                        <Tag
                                            :value="audit.model"
                                            severity="secondary"
                                            size="small"
                                        />
                                    </div>
                                </div>
                                <div v-if="audit.description" class="mt-2 border-l-4 border-gray-200 pl-3 text-sm text-gray-600">
                                    {{ audit.description }}
                                </div>
                                <div v-if="audit.changes" class="mt-2 text-xs text-gray-500">
                                    <pre class="whitespace-pre-wrap">{{ JSON.stringify(audit.changes, null, 2) }}</pre>
                                </div>
                            </div>
                        </div>
                    </template>
                </Card>
            </div>
        </div>

        <!-- Delete/Reverse Confirmation Modal -->
        <Dialog
            v-model:visible="showConfirmationModal"
            :style="{ width: '450px' }"
            :header="currentAction === 'delete' ? 'Delete Journal' : 'Reverse Journal'"
            :modal="true"
        >
            <div class="flex items-center">
                <i
                    class="pi pi-exclamation-triangle mr-3 text-red-500"
                    style="font-size: 2rem"
                ></i>
                <div>
                    <span v-if="currentAction === 'delete'">
                        Are you sure you want to
                        <strong class="text-red-600">permanently delete</strong>
                        Journal <strong>{{ journal.journal_number }}</strong>?
                        <br>
                        <small class="text-gray-500">This action cannot be undone.</small>
                    </span>
                    <span v-else-if="currentAction === 'reverse'">
                        Are you sure you want to
                        <strong class="text-orange-600">reverse</strong>
                        Journal <strong>{{ journal.journal_number }}</strong>?
                        <br>
                        <small class="text-gray-500">This will reverse all GL account postings.</small>
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
                    :label="currentAction === 'delete' ? 'Yes, Delete' : 'Yes, Reverse'"
                    :icon="currentAction === 'delete' ? 'pi pi-trash' : 'pi pi-undo'"
                    :severity="currentAction === 'delete' ? 'danger' : 'warning'"
                    @click="currentAction === 'delete' ? confirmDelete() : confirmReverse()"
                    autofocus
                />
            </template>
        </Dialog>

        <!-- Loading Overlay -->
        <div v-if="isLoading" class="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-50">
            <Card>
                <template #content>
                    <div class="flex flex-col items-center p-4">
                        <ProgressSpinner style="width: 50px; height: 50px" strokeWidth="4" />
                        <p class="mt-4 text-gray-600">
                            {{ currentAction === 'approve' ? 'Approving journal...' : 
                               currentAction === 'reverse' ? 'Reversing journal...' : 
                               'Processing...' }}
                        </p>
                    </div>
                </template>
            </Card>
        </div>
    </AppLayout>
</template>

<style scoped>
.field {
    margin-bottom: 1.25rem;
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
    font-size: 0.75rem;
    padding: 0.5rem;
}

:deep(.p-datatable .p-datatable-tbody > tr > td) {
    padding: 0.5rem;
}

.space-y-1 > * + * {
    margin-top: 0.25rem;
}

.space-y-2 > * + * {
    margin-top: 0.5rem;
}

.space-y-3 > * + * {
    margin-top: 0.75rem;
}

.space-y-4 > * + * {
    margin-top: 1rem;
}

.bg-green-50 {
    background-color: #f0fdf4;
    border: 1px solid #bbf7d0;
}

.bg-red-50 {
    background-color: #fef2f2;
    border: 1px solid #fecaca;
}

.bg-blue-50 {
    background-color: #eff6ff;
    border: 1px solid #bfdbfe;
}

.border-l-4 {
    border-left-width: 4px;
}

.border-gray-200 {
    border-color: #e5e7eb;
}

/* Fix for scrollable datatable */
:deep(.p-datatable-scrollable-wrapper) {
    flex: 1;
    min-height: 0;
}

:deep(.p-datatable-scrollable-body) {
    max-height: none !important;
}

/* Status colors */
.text-green-600 {
    color: #059669;
}

.text-red-600 {
    color: #dc2626;
}

.text-orange-600 {
    color: #ea580c;
}

.bg-green-500 {
    background-color: #10b981;
}

.bg-red-500 {
    background-color: #ef4444;
}

/* Responsive adjustments */
@media (max-width: 768px) {
    :deep(.p-card-content .col-12),
    :deep(.p-card-content .col-6) {
        padding: 0.75rem;
    }
    
    .flex-col-mobile {
        flex-direction: column;
    }
    
    .text-center-mobile {
        text-align: center;
    }
}
</style>