<script setup>
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import Button from 'primevue/button';
import Card from 'primevue/card';
import Column from 'primevue/column';
import DataTable from 'primevue/datatable';
import Dialog from 'primevue/dialog';
import Paginator from 'primevue/paginator';
import Tag from 'primevue/tag';
import Toast from 'primevue/toast';
import { useToast } from 'primevue/usetoast';
import { computed, ref, onMounted, watch } from 'vue';
import { FilterMatchMode } from '@primevue/core/api';
import axios from 'axios';

const schedules = ref([]);

// Register tooltip directive locally if not globally registered
const vTooltip = {
    mounted(el, binding) {
        el.setAttribute('title', binding.value);
        el.style.cursor = binding.value ? 'pointer' : 'default';
    },
    updated(el, binding) {
        el.setAttribute('title', binding.value);
    },
};

const filters = ref({
    global: { value: null, matchMode: FilterMatchMode.CONTAINS },
});


const toast = useToast();

// ðŸ’¡ State for Modals
const showConfirmationModal = ref(false);
const showVoucherTypeModal = ref(false);
const currentSchedule = ref(null);
const currentAction = ref(null);
const selectedVoucherType = ref(null);

// ðŸ’¡ PROPS: Receive real schedules data from Laravel controller
const props = defineProps({
    schedules: {
        type: Object,
        required: true,
        default: () => ({
            data: [],
            total: 0,
            current_page: 1,
            per_page: 15,
            links: [],
        }),
    },
});

// Computeds
// const schedules = computed(() => props.schedules);

// --- VOUCHER TYPES ---
const voucherTypes = [
    {
        label: 'Standard Voucher',
        value: 'standard',
        description: 'Regular payment voucher for completed services/goods',
    },
    {
        label: 'Prepayment Voucher',
        value: 'prepayment',
        description: 'Advance payment before delivery of services/goods',
    },
    {
        label: 'Salary Voucher',
        value: 'salary',
        description: 'Payment voucher for employee salaries',
    },
];

// --- PERMISSION LOGIC ---
const canCreateVoucher = (schedule) => {
    if (!schedule || !schedule.status) return false;
    const status = schedule.status.toLowerCase().trim();
    // Allow voucher creation for approved/submitted schedules that don't have vouchers yet
    const voucherEligibleStatuses = [
        'submitted',
        'processed',
        'approved',
        'awaiting voucher',
    ];
    return voucherEligibleStatuses.includes(status) && !schedule.voucher_id;
};

const canEditSchedule = (schedule) => {
    if (!schedule || !schedule.status) return false;
    const status = schedule.status.toLowerCase().trim();
    // Only allow editing if it hasn't been processed into a voucher yet
    const editableStatuses = ['draft', 'saved', 'returned', 'needs attention'];
    return editableStatuses.includes(status);
};

const canDeleteSchedule = (schedule) => {
    if (!schedule || !schedule.status) return false;
    const status = schedule.status.toLowerCase().trim();
    // Only draft schedules can be deleted
    return ['draft', 'saved'].includes(status);
};

// --- STATUS COLORS ---
const getStatusSeverity = (status) => {
    if (!status) return 'info';
    const normalizedStatus = status.toLowerCase().trim();

    switch (normalizedStatus) {
        // âœ… Fully Processed
        case 'voucher raised':
        case 'processed':
        case 'approved':
            return 'success';

        // âŒ Rejected
        case 'rejected':
        case 'declined':
            return 'danger';

        // âš ï¸ Needs Action
        case 'returned':
        case 'needs attention':
            return 'warning';

        // â³ In Progress
        case 'submitted':
        case 'awaiting voucher':
            return 'secondary';

        // ðŸ“ Initial
        case 'draft':
        case 'saved':
            return 'info';

        default:
            return 'info';
    }
};

// --- MODAL HANDLING ---
const openConfirmationModal = (schedule, action) => {
    if (action === 'edit' && !canEditSchedule(schedule)) {
        toast.add({
            severity: 'error',
            summary: 'Cannot Edit',
            detail: `Schedule ${schedule.schedule_number} is "${schedule.status}" and cannot be edited.`,
            life: 5000,
        });
        return;
    }

    if (action === 'delete' && !canDeleteSchedule(schedule)) {
        toast.add({
            severity: 'error',
            summary: 'Cannot Delete',
            detail: `Schedule ${schedule.schedule_number} cannot be deleted in its current status.`,
            life: 5000,
        });
        return;
    }

    currentSchedule.value = schedule;
    currentAction.value = action;
    showConfirmationModal.value = true;
};

const openVoucherTypeModal = (schedule) => {
    console.log('Voucher button clicked for schedule:', schedule);
    console.log('Can create voucher:', canCreateVoucher(schedule));

    if (!canCreateVoucher(schedule)) {
        toast.add({
            severity: 'error',
            summary: 'Cannot Create Voucher',
            detail: `Schedule ${schedule.schedule_number} is not eligible for voucher creation. Status: ${schedule.status}`,
            life: 5000,
        });
        return;
    }

    currentSchedule.value = schedule;
    selectedVoucherType.value = null;
    showVoucherTypeModal.value = true;
};

const confirmAction = () => {
    showConfirmationModal.value = false;
    if (!currentSchedule.value) return;

    const id = currentSchedule.value.id;

    if (currentAction.value === 'delete') {
        router.delete(route('schedules.destroy', id), {
            preserveScroll: true,
            onSuccess: () => {
                toast.add({
                    severity: 'success',
                    summary: 'Deleted',
                    detail: 'Schedule deleted successfully.',
                });
            },
            onError: () => {
                toast.add({
                    severity: 'error',
                    summary: 'Error',
                    detail: 'Failed to delete schedule.',
                });
            },
        });
    } else if (currentAction.value === 'edit') {
        router.visit(`/schedules/${id}/edit`);
    }
};

const createVoucher = () => {
    if (!currentSchedule.value || !selectedVoucherType.value) {
        toast.add({
            severity: 'error',
            summary: 'Selection Required',
            detail: 'Please select a voucher type.',
            life: 3000,
        });
        return;
    }

    const scheduleId = currentSchedule.value.id;

    // Redirect to voucher creation page with type parameter
    router.visit(
        `/vouchers/create?schedule_id=${scheduleId}&type=${selectedVoucherType.value}`,
        {
            onSuccess: () => {
                showVoucherTypeModal.value = false;
                toast.add({
                    severity: 'success',
                    summary: 'Voucher Creation',
                    detail: `Redirecting to create ${selectedVoucherType.value} voucher...`,
                    life: 3000,
                });
            },
            onError: () => {
                toast.add({
                    severity: 'error',
                    summary: 'Error',
                    detail: 'Failed to redirect to voucher creation.',
                });
            },
        },
    );
};

// --- ACTIONS ---
const printSchedule = (schedule) => {
    const printUrl = `/schedules/${schedule.id}/print`;
    window.open(printUrl, '_blank');
};

const viewVoucher = (schedule) => {
    if (schedule.voucher_id) {
        console.log('Viewing voucher:', schedule.voucher_id);
        router.visit(`/vouchers/${schedule.voucher_id}`);
    } else {
        toast.add({
            severity: 'warn',
            summary: 'No Voucher',
            detail: 'This schedule does not have an associated voucher yet.',
            life: 3000,
        });
    }
};

const createSchedule = () => {
    router.visit('/schedules/create');
};

// --- FORMATTERS ---
const formatCurrency = (value) => {
    return new Intl.NumberFormat('en-NG', {
        style: 'currency',
        currency: 'NGN',
    }).format(value);
};

const formatDate = (dateString) => {
    if (!dateString) return 'N/A';
    return new Date(dateString).toLocaleDateString('en-GB');
};

// --- PAGINATION ---
const paginatorTotalRecords = computed(() => schedules.value.total);
const onPageChange = (event) => {
    const url = schedules.value.links[event.page + 1]?.url;
    if (url) {
        router.get(url, {}, { preserveState: true, replace: true });
    }
};

const breadcrumbs = ref([
    { title: 'Schedules', href: '/schedules' },
    { title: 'List', href: '#' },
]);



// const schedules = ref([]);

const searchQuery = ref(""); // Search input



const lazyParams = ref({
    first: 0,
    rows: 20,
    page: 1,
});

const totalRecords = ref(0);
const loading = ref(false);
let debounceTimer = null; // Timer for debounce



const loadSchedules = async () => {
    loading.value = true;
    try {
        const response = await axios.get('sssearch', { params: { per_page: lazyParams.value.rows, page: lazyParams.value.page, search: searchQuery.value }, });
        // console.log(response.data);
        schedules.value = response.data.schedules;
        totalRecords.value = response.data.paginator.total;
    } catch (error) {
        toast.add({ severity: "error", summary: "Error", detail: "Failed to load data", life: 3000 });
        console.error(error);

    }
    loading.value = false;
};




watch(searchQuery, () => {
    clearTimeout(debounceTimer);
    debounceTimer = setTimeout(() => {
        lazyParams.value.page = 1; // Reset to first page when searching
        loadSchedules();
    }, 2000); // 500ms debounce delay

});

const onPage = (event) => {
    lazyParams.value.page = event.page + 1; // Laravel pagination starts at 1
    lazyParams.value.first = event.first;
    lazyParams.value.rows = event.rows;
    loadSchedules();
};


onMounted(() => {
    loadSchedules();
});

</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">

        <Head title="Schedule List" />
        <Toast />

        <Card>
            <template #title>
                <div class="justify-content-between align-items-center flex">
                    <span>Payment Schedules ({{ schedules.total }})</span>

                    <Button label="Create Schedule" icon="pi pi-plus" severity="primary" @click="createSchedule" />
                </div>
            </template>

            <template #content>

                <div class="mb-6">
                    <div class="flex flex-col gap-4 md:flex-row md:items-center">
                        <IconField iconPosition="left" class="flex-1">
                            <InputIcon class="pi pi-search text-gray-400" />
                            <InputText v-model="searchQuery" @input="performSearch"
                                placeholder="Search receipts by number, MDA, eco code, amount, bank..."
                                class="w-full" />
                        </IconField>
                        
                    </div>
                    <small class="mt-2 text-gray-500">
                        Search by: Schedule Number, MDA Name, Admin Code, Amount, Payee Name
                        
                    </small>
                </div>


                <!-- :value="schedules.data" dataKey="id" stripedRows responsiveLayout="scroll"
                    class="p-datatable-sm" :loading="false" :emptyMessage="'No schedules found.'"  -->
                <DataTable v-model:filters="filters" :value="schedules.data" dataKey="id" stripedRows
                    responsiveLayout="scroll" class="p-datatable-sm" :emptyMessage="'No schedules found.'"
                    :paginator="true" :rowsPerPageOptions="[5, 10, 20, 50, 100]" :loading="loading"
                    :rows="lazyParams.rows" :totalRecords="totalRecords" @page="onPage" removableSort
                    :globalFilterFields="['schedule_number', 'schedule_date',]" lazy size="small"
                    paginatorTemplate="RowsPerPageDropdown FirstPageLink PrevPageLink CurrentPageReport NextPageLink LastPageLink"
                    currentPageReportTemplate="{first} to {last} of {totalRecords}">
                    <Column field="schedule_number" header="Schedule #" headerStyle="width: 12%" :sortable="true">
                        <template #body="slotProps">
                            <Link :href="'/schedules/' + slotProps.data.id"
                                class="text-primary-600 font-medium hover:underline">
                                {{ slotProps.data.schedule_number }}
                            </Link>
                        </template>
                    </Column>

                    <Column field="schedule_date" header="Date" headerStyle="width: 8%" :sortable="true">
                        <template #body="slotProps">
                            {{ formatDate(slotProps.data.schedule_date) }}
                        </template>
                    </Column>

                    <Column field="mda.name" header="MDA" headerStyle="width: 15%">
                        <template #body="slotProps">
                            <span v-if="slotProps.data.mda">
                                {{ slotProps.data.mda.name }}
                            </span>
                            <span v-else class="text-500">N/A</span>
                        </template>
                    </Column>

                    <Column field="budget_code.code" header="Administrative Code" headerStyle="width: 12%">
                        <template #body="slotProps">
                            <span class="font-mono text-sm">
                                {{ slotProps.data.budget_code || 'N/A' }}
                            </span>
                        </template>
                    </Column>

                    <Column header="Payee / Description" headerStyle="width: 18%">
                        <template #body="slotProps">
                            <div class="flex-column flex">
                                <span class="font-medium">
                                    {{
                                        slotProps.data.payee_name ||
                                        'Multiple Payees'
                                    }}
                                </span>
                                <small class="text-500" v-if="slotProps.data.items_count">
                                    {{ slotProps.data.items_count }} line items
                                </small>
                            </div>
                        </template>
                    </Column>

                    <Column field="total_amount" header="Total Amount" headerStyle="width: 12%"
                        bodyClass="font-bold text-left" :sortable="true">
                        <template #body="slotProps">
                            {{
                                formatCurrency(slotProps.data.total_amount || 0)
                            }}
                            <br />
                            <small class="text-500 text-blue-500">
                                <!-- Voucher count -->
                                Raised: {{ formatCurrency(slotProps.data.amount_posted) }}
                            </small><br />

                            <small class="text-500 text-green-700"
                                v-if="(slotProps.data.total_amount - slotProps.data.amount_posted) > 0">
                                Oust: {{ formatCurrency(slotProps.data.total_amount - slotProps.data.amount_posted)
                                }}
                            </small>
                            <small class="text-500"
                                v-if="(slotProps.data.total_amount - slotProps.data.amount_posted) == 0">
                                Oust: {{ formatCurrency(slotProps.data.total_amount - slotProps.data.amount_posted)
                                }}
                            </small>

                            <small class="text-500 text-red-600"
                                v-if="(slotProps.data.total_amount - slotProps.data.amount_posted) < 0">
                                Oust: {{ formatCurrency(slotProps.data.total_amount - slotProps.data.amount_posted)
                                }}
                            </small>
                        </template>
                    </Column>

                    <Column field="status" header="Status" headerStyle="width: 8%">
                        <template #body="slotProps">
                            <Tag :value="slotProps.data.status" :severity="getStatusSeverity(slotProps.data.status)
                                " />
                        </template>
                    </Column>

                    <Column header="Voucher" headerStyle="width: 8%" bodyClass="text-center">
                        <template #body="slotProps">
                            <div class="justify-content-center grid">
                                <div class="font-bold mt-1"> {{ slotProps.data.voucher_count }} </div>
                                <div>
                                    <!-- View Voucher Button -->
                                    <Button v-if="slotProps.data.voucher_id" icon="pi pi-file" text rounded
                                        severity="success" v-tooltip="'View Voucher'"
                                        @click="viewVoucher(slotProps.data)" class="p-button-sm voucher-button" />
                                    <!-- Create Voucher Button -->
                                    <Button v-else icon="pi pi-plus-circle" text rounded :severity="canCreateVoucher(slotProps.data)
                                        ? 'primary'
                                        : 'secondary'
                                        " :disabled="!canCreateVoucher(slotProps.data)
                                            " v-tooltip="canCreateVoucher(slotProps.data)
                                            ? 'Create Voucher'
                                            : `Cannot create voucher - Status: ${slotProps.data.status}`
                                            " @click="
                                                openVoucherTypeModal(slotProps.data)
                                                " class="p-button-sm voucher-button" />
                                </div>
                            </div>
                        </template>
                    </Column>

                    <Column header="Actions" headerStyle="width: 15%" bodyClass="text-center">
                        <template #body="slotProps">
                            <div class="justify-content-center flex gap-2">
                                <Button icon="pi pi-print" text rounded severity="info" v-tooltip="'Print Schedule'"
                                    @click="printSchedule(slotProps.data)" class="p-button-sm" />

                                <Button icon="pi pi-pencil" text rounded severity="secondary"
                                    :disabled="!canEditSchedule(slotProps.data)" v-tooltip="canEditSchedule(slotProps.data)
                                        ? 'Edit Schedule'
                                        : 'Cannot edit'
                                        " @click="
                                            openConfirmationModal(
                                                slotProps.data,
                                                'edit',
                                            )
                                            " class="p-button-sm" />

                                <Button icon="pi pi-trash" text rounded severity="danger" :disabled="!canDeleteSchedule(slotProps.data)
                                    " v-tooltip="canDeleteSchedule(slotProps.data)
                                        ? 'Delete Schedule'
                                        : 'Cannot delete'
                                        " @click="
                                            openConfirmationModal(
                                                slotProps.data,
                                                'delete',
                                            )
                                            " class="p-button-sm" />
                            </div>
                        </template>
                    </Column>
                </DataTable>

                <div class="justify-content-end mt-4 flex">
                    <Paginator :rows="schedules.per_page" :totalRecords="paginatorTotalRecords" :first="(schedules.current_page - 1) * schedules.per_page
                        " @page="onPageChange" />
                </div>
            </template>
        </Card>

        <!-- Confirmation Modal -->
        <Dialog v-model:visible="showConfirmationModal" :style="{ width: '400px' }" header="Confirm Action"
            :modal="true">
            <div class="align-items-center flex">
                <i :class="currentAction === 'delete'
                    ? 'pi pi-exclamation-triangle mr-3 text-red-500'
                    : 'pi pi-question-circle mr-3 text-blue-500'
                    " style="font-size: 2rem"></i>

                <span v-if="currentSchedule && currentAction === 'delete'">
                    Are you sure you want to <strong>delete</strong> Schedule
                    <strong>{{ currentSchedule.schedule_number }}</strong>?
                </span>
                <span v-else-if="currentSchedule && currentAction === 'edit'">
                    Proceed to edit Schedule
                    <strong>{{ currentSchedule.schedule_number }}</strong>?
                </span>
            </div>

            <template #footer>
                <Button label="No" icon="pi pi-times" @click="showConfirmationModal = false" text />
                <Button :label="currentAction === 'delete'
                    ? 'Yes, Delete'
                    : 'Yes, Proceed'
                    " :icon="currentAction === 'delete'
                        ? 'pi pi-trash'
                        : 'pi pi-check'
                        " :severity="currentAction === 'delete' ? 'danger' : 'primary'
                            " @click="confirmAction" autofocus />
            </template>
        </Dialog>

        <!-- Voucher Type Selection Modal -->
        <Dialog v-model:visible="showVoucherTypeModal" :style="{ width: '500px' }" header="Select Voucher Type"
            :modal="true" :closable="true">
            <div class="flex-column flex gap-4">
                <p class="text-600">
                    Select the type of voucher to create for Schedule
                    <strong>{{ currentSchedule?.schedule_number }}</strong>:
                </p>

                <div class="voucher-type-options">
                    <div v-for="type in voucherTypes" :key="type.value"
                        class="voucher-type-option border-round mb-3 cursor-pointer border-1 p-3" :class="{
                            'border-primary bg-blue-50':
                                selectedVoucherType === type.value,
                            'border-200': selectedVoucherType !== type.value,
                        }" @click="selectedVoucherType = type.value">
                        <div class="align-items-center flex gap-3">
                            <i class="pi" :class="{
                                'pi-check-circle text-primary':
                                    selectedVoucherType === type.value,
                                'pi-circle text-400':
                                    selectedVoucherType !== type.value,
                            }"></i>
                            <div class="flex-column flex">
                                <span class="font-semibold">{{
                                    type.label
                                    }}</span>
                                <span class="text-600 text-sm">{{
                                    type.description
                                    }}</span>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="mt-2 flex gap-2">
                    <small class="text-500">
                        <i class="pi pi-info-circle mr-1"></i>
                        The voucher type determines the payment workflow and
                        requirements.
                    </small>
                </div>
            </div>

            <template #footer>
                <Button label="Cancel" icon="pi pi-times" @click="showVoucherTypeModal = false" text />
                <Button label="Create Voucher" icon="pi pi-check" severity="primary" :disabled="!selectedVoucherType"
                    @click="createVoucher" autofocus />
            </template>
        </Dialog>
    </AppLayout>
</template>

<style scoped>
.voucher-type-option {
    transition: all 0.2s ease;
}

.voucher-type-option:hover {
    background-color: #f8f9fa;
    border-color: #6c757d !important;
}

.voucher-type-option.selected {
    border-color: #3b82f6 !important;
    background-color: #eff6ff;
}

/* Ensure buttons are clickable */
:deep(.voucher-button) {
    position: relative;
    z-index: 10;
    pointer-events: auto !important;
}

:deep(.p-button) {
    pointer-events: auto !important;
}

:deep(.p-datatable) {
    position: relative;
    z-index: 1;
}
</style>
