<script setup>
import { Head, router, useForm } from '@inertiajs/vue3';
import { useField, useForm as useVeeForm } from 'vee-validate';
import { computed, onMounted, ref, watch } from 'vue';
import * as yup from 'yup';

// PrimeVue
import AppLayout from '@/layouts/AppLayout.vue';
import Button from 'primevue/button';
import Card from 'primevue/card';
import Column from 'primevue/column';
import DataTable from 'primevue/datatable';
import Dialog from 'primevue/dialog';
import Dropdown from 'primevue/dropdown';
import InputNumber from 'primevue/inputnumber';
import InputText from 'primevue/inputtext';
import Paginator from 'primevue/paginator';
import Tag from 'primevue/tag';
import Toast from 'primevue/toast';
import { useToast } from 'primevue/usetoast';

const props = defineProps({
    activities: {
        type: Object,
        default: () => ({
            data: [],
            meta: {
                total: 0,
                per_page: 20,
                current_page: 1,
                last_page: 1,
                links: [],
            },
        }),
    },
    statistics: {
        type: Object,
        default: () => ({
            total: 0,
            active: 0,
            inactive: 0,
        }),
    },
    filters: {
        type: Object,
        default: () => ({
            search: '',
            sort_field: 'id',
            sort_order: 'desc',
            per_page: 20,
        }),
    },
    flash: Object,
});

const toast = useToast();
const showModal = ref(false);
const isEdit = ref(false);
const currentId = ref(null);
const searchQuery = ref(props.filters.search || '');
const searchTimeout = ref(null);
const sortField = ref(props.filters.sort_field || 'id');
const sortOrder = ref(props.filters.sort_order || 'desc');
const perPage = ref(props.filters.per_page || 20);
const currentPage = ref(props.activities.meta?.current_page || 1);
const totalPages = ref(props.activities.meta?.last_page || 1);

const statusOptions = [
    { label: 'Active', value: 1 },
    { label: 'Inactive', value: 0 },
];

const perPageOptions = [
    { label: '10 per page', value: 10 },
    { label: '20 per page', value: 20 },
    { label: '50 per page', value: 50 },
    { label: '100 per page', value: 100 },
];

// Watch for prop changes
watch(
    () => props.activities,
    (newActivities) => {
        if (newActivities.meta) {
            currentPage.value = newActivities.meta.current_page;
            totalPages.value = newActivities.meta.last_page;
        }
    },
);

watch(
    () => props.filters,
    (newFilters) => {
        sortField.value = newFilters.sort_field || 'id';
        sortOrder.value = newFilters.sort_order || 'desc';
        perPage.value = newFilters.per_page || 20;
        searchQuery.value = newFilters.search || '';
    },
);

// --- Statistics ---
const statistics = computed(() => {
    return {
        total: props.statistics?.total || 0,
        active: props.statistics?.active || 0,
        inactive: props.statistics?.inactive || 0,
    };
});

// --- Validation ---
const schema = yup.object({
    tag: yup.string().required().max(10),
    bank_name: yup.string().required(),
    title: yup.string().required(),
    account_number: yup.string().nullable(),
    status: yup.number().required(),
    economic_code: yup.string().required(),
    balanceBFW: yup.number().required(),
});

const { handleSubmit, resetForm, setErrors } = useVeeForm({
    validationSchema: schema,
    initialValues: {
        tag: '',
        bank_name: '',
        title: '',
        account_number: '',
        status: 1,
        economic_code: '',
        balanceBFW: 0,
    },
});

const { value: tag, errorMessage: tagError } = useField('tag');
const { value: bank_name, errorMessage: bankNameError } = useField('bank_name');
const { value: title, errorMessage: titleError } = useField('title');
const { value: account_number } = useField('account_number');
const { value: status, errorMessage: statusError } = useField('status');
const { value: economic_code, errorMessage: economicCodeError } = useField('economic_code');
const { value: balanceBFW, errorMessage: balanceBFWError } = useField('balanceBFW');

const form = useForm({});

// --- Handlers ---
const openCreate = () => {
    isEdit.value = false;
    resetForm();
    showModal.value = true;
};

const openEdit = (data) => {
    isEdit.value = true;
    currentId.value = data.id;
    resetForm({ values: { ...data } });
    showModal.value = true;
};

const submit = handleSubmit((values) => {
    const options = {
        onSuccess: () => {
            showModal.value = false;
            toast.add({
                severity: 'success',
                summary: 'Success',
                detail: 'Operation completed',
                life: 3000,
            });
        },
        onError: (err) => setErrors(err),
    };

    if (isEdit.value) {
        router.put(`/bank-activities/${currentId.value}`, values, options);
    } else {
        router.post('/bank-activities', values, options);
    }
});

const deleteActivity = (id) => {
    if (confirm('Are you sure?')) {
        router.delete(`/bank-activities/${id}`, {
            onSuccess: () =>
                toast.add({
                    severity: 'warn',
                    summary: 'Deleted',
                    detail: 'Record removed',
                    life: 3000,
                }),
        });
    }
};

// Navigation functions
const goToPage = (page) => {
    if (page < 1 || page > totalPages.value || page === currentPage.value)
        return;

    const params = getParams();
    params.page = page;

    router.get('/bank-activities', params, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    });
};

const nextPage = () => {
    if (currentPage.value < totalPages.value) {
        goToPage(currentPage.value + 1);
    }
};

const prevPage = () => {
    if (currentPage.value > 1) {
        goToPage(currentPage.value - 1);
    }
};

const onPage = (e) => {
    goToPage(e.page + 1);
};

const getParams = () => {
    return {
        search: searchQuery.value || null,
        sort_field: sortField.value,
        sort_order: sortOrder.value,
        per_page: perPage.value,
        page: currentPage.value,
    };
};

const handleSearch = () => {
    // Clear previous timeout
    if (searchTimeout.value) {
        clearTimeout(searchTimeout.value);
    }

    // Set new timeout for debouncing
    searchTimeout.value = setTimeout(() => {
        const params = getParams();
        params.page = 1; // Reset to first page when searching

        router.get('/bank-activities', params, {
            preserveState: true,
            preserveScroll: false,
            replace: true,
        });
    }, 500); // 500ms delay for better UX
};

const handleSort = (field) => {
    if (sortField.value === field) {
        // Toggle sort order if same field
        sortOrder.value = sortOrder.value === 'asc' ? 'desc' : 'asc';
    } else {
        // New field, default to ascending
        sortField.value = field;
        sortOrder.value = 'asc';
    }

    const params = getParams();
    router.get('/bank-activities', params, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    });
};

const handlePerPageChange = () => {
    const params = getParams();
    params.page = 1; // Reset to first page when changing per page

    router.get('/bank-activities', params, {
        preserveState: true,
        preserveScroll: false,
        replace: true,
    });
};

const clearSearch = () => {
    searchQuery.value = '';
    const params = getParams();
    params.search = null;
    params.page = 1;

    router.get('/bank-activities', params, {
        preserveState: true,
        preserveScroll: false,
        replace: true,
    });
};

// Get sort icon
const getSortIcon = (field) => {
    if (sortField.value !== field) return 'pi pi-sort-alt';
    return sortOrder.value === 'asc' ? 'pi pi-sort-up' : 'pi pi-sort-down';
};

onMounted(() => {
    if (props.flash?.message) {
        toast.add({
            severity: 'success',
            summary: 'Success',
            detail: props.flash.message,
            life: 3000,
        });
    }
});

const breadcrumbs = [{ title: 'Bank Activities', href: '#' }];


const dt = ref();
const exportCSV = () => {
    dt.value.exportCSV();
};
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head title="Bank Activities" />
        <Toast />

        <!-- Statistics Cards -->
        <div class="mb-6 grid grid-cols-1 gap-4 md:grid-cols-3">
            <Card>
                <template #title>
                    <div class="flex items-center gap-2">
                        <i class="pi pi-building-columns text-blue-500"></i>
                        <span>Total Activities</span>
                    </div>
                </template>
                <template #content>
                    <div class="text-3xl font-bold text-gray-800">
                        {{ statistics.total }}
                    </div>
                    <p class="mt-2 text-sm text-gray-500">
                        All bank activities
                    </p>
                </template>
            </Card>

            <Card>
                <template #title>
                    <div class="flex items-center gap-2">
                        <i class="pi pi-check-circle text-green-500"></i>
                        <span>Active</span>
                    </div>
                </template>
                <template #content>
                    <div class="text-3xl font-bold text-green-600">
                        {{ statistics.active }}
                    </div>
                    <p class="mt-2 text-sm text-gray-500">
                        Currently active accounts
                    </p>
                </template>
            </Card>

            <Card>
                <template #title>
                    <div class="flex items-center gap-2">
                        <i class="pi pi-times-circle text-red-500"></i>
                        <span>Inactive</span>
                    </div>
                </template>
                <template #content>
                    <div class="text-3xl font-bold text-red-600">
                        {{ statistics.inactive }}
                    </div>
                    <p class="mt-2 text-sm text-gray-500">
                        Deactivated accounts
                    </p>
                </template>
            </Card>
        </div>

        <!-- Main Card with Search and Table -->
        <Card>
            <template #title>
                <div
                    class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between"
                >
                    <div class="flex items-center gap-2">
                        <i class="pi pi-list text-primary"></i>
                        <span>Bank Activities List</span>
                    </div>

                    <div class="flex flex-col gap-3 md:flex-row">
                        <!-- Search Input -->
                        <div class="relative">
                            <span class="p-input-icon-left w-full md:w-auto">
                                <i class="pi pi-search" />
                                <InputText
                                    v-model="searchQuery"
                                    placeholder="Search by bank, title, tag..."
                                    class="w-full md:w-64"
                                    @update:model-value="handleSearch"
                                />
                            </span>
                            <Button
                                v-if="searchQuery"
                                icon="pi pi-times"
                                text
                                rounded
                                severity="secondary"
                                @click="clearSearch"
                                class="absolute top-1/2 right-2 -translate-y-1/2 transform"
                            />
                        </div>

                        <Button icon="  pi pi-external-link" label="Export" @click="exportCSV($event)" />

                        <Button
                            label="New Activity"
                            icon="pi pi-plus"
                            @click="openCreate"
                        />
                    </div>
                </div>
            </template>

            <template #content>
                <!-- Search Results Info -->
                <div
                    v-if="searchQuery"
                    class="mb-4 rounded-lg border border-blue-100 bg-blue-50 p-3"
                >
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-2">
                            <i class="pi pi-info-circle text-blue-500"></i>
                            <span class="text-blue-700">
                                Showing results for: "<strong>{{
                                    searchQuery
                                }}</strong
                                >"
                            </span>
                        </div>
                        <Button
                            label="Clear"
                            icon="pi pi-times"
                            text
                            size="small"
                            @click="clearSearch"
                        />
                    </div>
                </div>

                <!-- Table with Sorting -->
                <DataTable
                    :value="activities?.data || []"
                    stripedRows
                    responsiveLayout="scroll"
                    class="p-datatable-sm"
                    ref="dt"
                >
                    <Column field="tag" header="Tag" sortable>
                        <template #header>
                            <div
                                class="flex cursor-pointer items-center gap-2"
                                @click="handleSort('tag')"
                            >
                                <span>Tag</span>
                                <i :class="getSortIcon('tag')"></i>
                            </div>
                        </template>
                        <template #body="{ data }">
                            <Tag :value="data.tag" class="font-mono" />
                        </template>
                    </Column>

                    <Column field="bank_name" header="Bank" sortable>
                        <template #header>
                            <div
                                class="flex cursor-pointer items-center gap-2"
                                @click="handleSort('bank_name')"
                            >
                                <span>Bank</span>
                                <i :class="getSortIcon('bank_name')"></i>
                            </div>
                        </template>
                    </Column>

                    <Column field="title" header="Account Title" sortable>
                        <template #header>
                            <div
                                class="flex cursor-pointer items-center gap-2"
                                @click="handleSort('title')"
                            >
                                <span>Account Title</span>
                                <i :class="getSortIcon('title')"></i>
                            </div>
                        </template>
                    </Column>

                    <Column
                        field="account_number"
                        header="Account No."
                        sortable
                    >
                        <template #header>
                            <div
                                class="flex cursor-pointer items-center gap-2"
                                @click="handleSort('account_number')"
                            >
                                <span>Account No.</span>
                                <i :class="getSortIcon('account_number')"></i>
                            </div>
                        </template>
                        <template #body="{ data }">
                            <span v-if="data.account_number" class="font-mono">
                                {{ data.account_number }}
                            </span>
                            <span v-else class="text-gray-400 italic">N/A</span>
                        </template>
                    </Column>

                    <Column field="status" header="Status" sortable>
                        <template #header>
                            <div
                                class="flex cursor-pointer items-center gap-2"
                                @click="handleSort('status')"
                            >
                                <span>Status</span>
                                <i :class="getSortIcon('status')"></i>
                            </div>
                        </template>
                        <template #body="{ data }">
                            <Tag
                                :value="
                                    data.status === 1 ? 'Active' : 'Inactive'
                                "
                                :severity="
                                    data.status === 1 ? 'success' : 'danger'
                                "
                                :icon="
                                    data.status === 1
                                        ? 'pi pi-check'
                                        : 'pi pi-times'
                                "
                            />
                        </template>
                    </Column>

                    <Column field="created_at" header="Created" sortable>
                        <template #header>
                            <div
                                class="flex cursor-pointer items-center gap-2"
                                @click="handleSort('created_at')"
                            >
                                <span>Created</span>
                                <i :class="getSortIcon('created_at')"></i>
                            </div>
                        </template>
                        <template #body="{ data }">
                            <span class="text-sm text-gray-600">
                                {{
                                    new Date(
                                        data.created_at,
                                    ).toLocaleDateString()
                                }}
                            </span>
                        </template>
                    </Column>

                    <Column header="Actions" style="width: 120px">
                        <template #body="{ data }">
                            <div class="flex gap-2">
                                <Button
                                    icon="pi pi-pencil"
                                    text
                                    rounded
                                    severity="info"
                                    @click="openEdit(data)"
                                    v-tooltip="'Edit'"
                                />
                                <Button
                                    icon="pi pi-trash"
                                    text
                                    rounded
                                    severity="danger"
                                    @click="deleteActivity(data.id)"
                                    v-tooltip="'Delete'"
                                />
                            </div>
                        </template>
                    </Column>
                </DataTable>

                <!-- Pagination Controls -->
                <div v-if="activities?.meta?.total > 0" class="mt-6">
                    <div
                        class="mb-4 flex flex-col justify-between gap-4 md:flex-row md:items-center"
                    >
                        <!-- Page Info & Per Page Selector -->
                        <div
                            class="flex flex-col gap-4 md:flex-row md:items-center"
                        >
                            <div class="text-sm text-gray-600">
                                Showing {{ (currentPage - 1) * perPage + 1 }} to
                                {{
                                    Math.min(
                                        currentPage * perPage,
                                        activities.meta.total,
                                    )
                                }}
                                of {{ activities.meta.total }} records
                            </div>

                            <div class="flex items-center gap-2">
                                <span class="text-sm text-gray-600">Show:</span>
                                <Dropdown
                                    v-model="perPage"
                                    :options="perPageOptions"
                                    optionLabel="label"
                                    optionValue="value"
                                    class="w-32"
                                    @change="handlePerPageChange"
                                />
                            </div>
                        </div>

                        <!-- Pagination -->
                        <div class="flex items-center gap-2">
                            <!-- Simple Pagination Controls -->
                            <div class="flex items-center gap-1">
                                <Button
                                    icon="pi pi-angle-double-left"
                                    text
                                    rounded
                                    :disabled="currentPage === 1"
                                    @click="goToPage(1)"
                                    v-tooltip="'First page'"
                                />
                                <Button
                                    icon="pi pi-angle-left"
                                    text
                                    rounded
                                    :disabled="currentPage === 1"
                                    @click="prevPage"
                                    v-tooltip="'Previous page'"
                                />

                                <div class="mx-2 flex items-center gap-1">
                                    <span class="text-sm text-gray-600"
                                        >Page</span
                                    >
                                    <InputNumber
                                        v-model="currentPage"
                                        :min="1"
                                        :max="totalPages"
                                        class="w-16"
                                        @update:model-value="goToPage"
                                    />
                                    <span class="text-sm text-gray-600"
                                        >of {{ totalPages }}</span
                                    >
                                </div>

                                <Button
                                    icon="pi pi-angle-right"
                                    text
                                    rounded
                                    :disabled="currentPage === totalPages"
                                    @click="nextPage"
                                    v-tooltip="'Next page'"
                                />
                                <Button
                                    icon="pi pi-angle-double-right"
                                    text
                                    rounded
                                    :disabled="currentPage === totalPages"
                                    @click="goToPage(totalPages)"
                                    v-tooltip="'Last page'"
                                />
                            </div>

                            <!-- PrimeVue Paginator (as backup) -->
                            <Paginator
                                :rows="perPage"
                                :totalRecords="activities.meta.total"
                                :first="(currentPage - 1) * perPage"
                                @page="onPage"
                                class="hidden border-0 md:block"
                                template="FirstPageLink PrevPageLink CurrentPageReport NextPageLink LastPageLink"
                            />
                        </div>
                    </div>

                    <!-- Quick Page Navigation -->
                    <div class="mt-4 flex justify-center">
                        <div class="flex flex-wrap gap-1">
                            <Button
                                v-for="page in Math.min(5, totalPages)"
                                :key="page"
                                :label="page.toString()"
                                text
                                :severity="
                                    currentPage === page
                                        ? 'primary'
                                        : 'secondary'
                                "
                                @click="goToPage(page)"
                            />
                            <span
                                v-if="totalPages > 5"
                                class="flex items-center px-2"
                                >...</span
                            >
                            <Button
                                v-if="totalPages > 5"
                                :label="totalPages.toString()"
                                text
                                :severity="
                                    currentPage === totalPages
                                        ? 'primary'
                                        : 'secondary'
                                "
                                @click="goToPage(totalPages)"
                            />
                        </div>
                    </div>
                </div>

                <!-- No Results -->
                <div v-if="!activities?.data?.length" class="py-12 text-center">
                    <i class="pi pi-search mb-4 text-4xl text-gray-300"></i>
                    <h4 class="mb-2 text-lg font-semibold text-gray-600">
                        {{
                            searchQuery
                                ? 'No matching records found'
                                : 'No bank activities yet'
                        }}
                    </h4>
                    <p class="mb-4 text-gray-500">
                        {{
                            searchQuery
                                ? 'Try a different search term'
                                : 'Create your first bank activity to get started'
                        }}
                    </p>
                    <Button
                        v-if="!searchQuery"
                        label="Create First Activity"
                        icon="pi pi-plus"
                        @click="openCreate"
                    />
                    <Button
                        v-if="searchQuery"
                        label="Clear Search"
                        icon="pi pi-times"
                        text
                        @click="clearSearch"
                    />
                </div>
            </template>
        </Card>

        <!-- Create/Edit Modal -->
        <Dialog
            v-model:visible="showModal"
            :header="isEdit ? 'Edit Bank Activity' : 'New Bank Activity'"
            modal
            class="p-fluid w-full max-w-md"
        >
            <form @submit.prevent="submit" class="form-container">
                <div class="flex flex-col gap-4">
                    <div class="form-group">
                        <label class="form-label"
                            >Tag <span class="text-red-500">*</span></label
                        >
                        <InputText
                            v-model="tag"
                            :class="{ 'p-invalid': tagError }"
                            placeholder="e.g., BDO, BPI, MB"
                            class="w-full"
                        />
                        <small class="p-error mt-1 block">{{ tagError }}</small>
                        <small class="mt-1 block text-sm text-gray-500"
                            >Short identifier (max 10 characters)</small
                        >
                    </div>

                    <div class="form-group">
                        <label class="form-label"
                            >Bank Name
                            <span class="text-red-500">*</span></label
                        >
                        <InputText
                            v-model="bank_name"
                            :class="{ 'p-invalid': bankNameError }"
                            placeholder="e.g., Bank of the Philippine Islands"
                            class="w-full"
                        />
                        <small class="p-error mt-1 block">{{
                            bankNameError
                        }}</small>
                    </div>

                    <div class="form-group">
                        <label class="form-label"
                            >Account Title
                            <span class="text-red-500">*</span></label
                        >
                        <InputText
                            v-model="title"
                            :class="{ 'p-invalid': titleError }"
                            placeholder="e.g., John Doe Corporation"
                            class="w-full"
                        />
                        <small class="p-error mt-1 block">{{
                            titleError
                        }}</small>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Account Number</label>
                        <InputText
                            v-model="account_number"
                            placeholder="Optional account number"
                            class="w-full"
                        />
                        <small class="mt-1 block text-sm text-gray-500"
                            >Leave empty if not applicable</small
                        >
                    </div>

                    <div class="form-group">
                        <label class="form-label">Economic Code</label>
                        <InputText
                            v-model="economic_code"
                            placeholder="Economic Code"
                            class="w-full"
                            :class="{ 'p-invalid': economicCodeError }"
                        />
                        <small class="p-error mt-1 block">{{
                            economicCodeError
                        }}</small>
                        
                            
                    
                    </div>

                    <div class="form-group">
                        <label class="form-label">Opening Balance</label>
                        <InputNumber
                            v-model="balanceBFW"
                            mode="currency" currency="NGN" locale="en-NG"
                            placeholder="Opening Balance"
                            class="w-full"
                            :class="{ 'p-invalid': balanceBFWError }"
                        />
                        <small class="p-error mt-1 block">{{
                            balanceBFWError
                        }}</small>
                        
                            
                    
                    </div>

                    

                    <div class="form-group">
                        <label class="form-label"
                            >Status <span class="text-red-500">*</span></label
                        >
                        <Dropdown
                            v-model="status"
                            :options="statusOptions"
                            optionLabel="label"
                            optionValue="value"
                            :class="{ 'p-invalid': statusError }"
                            class="w-full"
                        />
                        <small class="p-error mt-1 block">{{
                            statusError
                        }}</small>
                    </div>
                </div>
            </form>
            <template #footer>
                <div class="flex w-full justify-end gap-2">
                    <Button
                        label="Cancel"
                        icon="pi pi-times"
                        text
                        @click="showModal = false"
                    />
                    <Button
                        :label="isEdit ? 'Update' : 'Create'"
                        icon="pi pi-check"
                        @click="submit"
                    />
                </div>
            </template>
        </Dialog>
    </AppLayout>
</template>

<style scoped>
.grid {
    display: grid;
}

/* Form Alignment Styles */
.form-container {
    width: 100%;
}

.form-group {
    margin-bottom: 1rem;
}

.form-label {
    display: block;
    font-weight: 600;
    margin-bottom: 0.5rem;
    color: #374151;
}

/* Ensure all form inputs have consistent styling */
:deep(.p-inputtext) {
    width: 100%;
    box-sizing: border-box;
}

:deep(.p-dropdown) {
    width: 100%;
}

:deep(.p-dialog .p-dialog-content) {
    padding: 1.5rem;
}

:deep(.p-dialog .p-dialog-footer) {
    padding: 1rem 1.5rem;
    border-top: 1px solid #e5e7eb;
}

:deep(.p-dialog) {
    min-width: 450px;
}

/* Mobile responsive adjustments */
@media (max-width: 640px) {
    :deep(.p-dialog) {
        min-width: 90vw;
        margin: 0 1rem;
    }

    .form-group {
        margin-bottom: 1.25rem;
    }
}

/* Improve spacing and alignment */
:deep(.p-fluid .p-inputtext) {
    margin: 0;
}

:deep(.p-invalid) {
    border-color: #f87171 !important;
}

.p-error {
    color: #ef4444;
    font-size: 0.875rem;
    line-height: 1.25rem;
}

.text-gray-500 {
    color: #6b7280;
}

/* Sortable header styling */
:deep(.p-column-header-content) {
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

:deep(.p-sortable-column) {
    cursor: pointer;
}

:deep(.p-sortable-column:hover) {
    background-color: #f9fafb;
}

/* Table responsive */
:deep(.p-datatable) {
    font-size: 0.875rem;
}

:deep(.p-datatable.p-datatable-sm .p-datatable-thead > tr > th),
:deep(.p-datatable.p-datatable-sm .p-datatable-tbody > tr > td) {
    padding: 0.5rem 0.75rem;
}

/* Pagination improvements */
:deep(.p-paginator) {
    background: transparent;
    border: none;
    padding: 0;
}

:deep(.p-paginator .p-paginator-pages .p-paginator-page) {
    min-width: 2.5rem;
    height: 2.5rem;
}

:deep(.p-paginator .p-paginator-current) {
    margin-left: 0.5rem;
}
</style>
