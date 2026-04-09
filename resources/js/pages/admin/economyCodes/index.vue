<script setup>
import { Head, router, useForm } from '@inertiajs/vue3';
import { computed, onMounted, ref, watch } from 'vue';

// --- Frontend Validation Imports ---
import { useField, useForm as useVeeForm } from 'vee-validate';
import * as yup from 'yup';

// --- PrimeVue Imports ---
import AppLayout from '@/layouts/AppLayout.vue'; // Assuming this component exists
import Button from 'primevue/button';
import Card from 'primevue/card';
import Column from 'primevue/column';
import DataTable from 'primevue/datatable';
import Dialog from 'primevue/dialog';
import Dropdown from 'primevue/dropdown';
import InputText from 'primevue/inputtext';
import Message from 'primevue/message';
import Paginator from 'primevue/paginator';
import Tag from 'primevue/tag';
import Toast from 'primevue/toast';
import { useToast } from 'primevue/usetoast';

const toast = useToast();

// ---------------------------------------------
// --- PROPS and INITIAL SETUP ---
// ---------------------------------------------

const props = defineProps({
    economyCodes: {
        // Paginated data object from Laravel/Inertia
        type: Object,
        required: true,
    },
    statistics: {
        type: Object,
        default: () => ({
            total: 0,
            active: 0,
            inactive: 0,
        }),
    },
    flash: {
        type: Object,
        default: () => ({ message: null }),
    },
});

// Display success message from flash data on mount
onMounted(() => {
    if (props.flash.message) {
        toast.add({
            severity: 'success',
            summary: 'Success',
            detail: props.flash.message,
            life: 3000,
        });
    }
});

// ---------------------------------------------
// --- DATA SOURCES & COMPUTED PROPERTIES ---
// ---------------------------------------------

// Core data extracted from props
const codeData = computed(() => props.economyCodes);

// Statistics
const statistics = computed(() => {
    return {
        total: props.statistics?.total || 0,
        active: props.statistics?.active || 0,
        inactive: props.statistics?.inactive || 0,
    };
});

// Client-side filtering state
const globalFilter = ref('');

// Filtered data for DataTable (fixes the manual global filter implementation)
const filteredCodeData = computed(() => {
    if (!codeData.value.data) return [];
    if (!globalFilter.value || globalFilter.value.trim() === '') {
        return codeData.value.data;
    }
    const filterText = globalFilter.value.toLowerCase().trim();
    return codeData.value.data.filter(
        (item) =>
            item.name.toLowerCase().includes(filterText) ||
            item.code.toLowerCase().includes(filterText),
    );
});

// Paginator properties
const paginatorTotalRecords = computed(() => codeData.value.total);
const paginatorCurrentPage = computed(() => codeData.value.current_page);
const paginatorRows = computed(() => codeData.value.per_page);

// Status options for Dropdown
const statusOptions = ref([
    { label: 'Active', value: 1 },
    { label: 'Inactive', value: 0 },
]);

// Helper for status tag visualization
const getStatusSeverity = (status) => (status === 1 ? 'success' : 'danger');
const getStatusText = (status) => (status === 1 ? 'Active' : 'Inactive');

// Custom route helper (as Ziggy is not available in the sandbox)
const route = (name, params) => {
    if (name === 'economy_code.store' || name === 'economy_code.index') {
        return '/economy-codes';
    }
    if (name.includes('.') && params && (params.id || params.economy_code)) {
        const id = params.id || params.economy_code;
        return `/economy-codes/${id}`;
    }
    // Custom route for fetching sub-items
    if (name === 'economy_code.items.fetch' && params && params.economy_code) {
        return `/economy-codes/${params.economy_code}/items`;
    }
    return `/${name.replace(/\./g, '/')}`;
};

const breadcrumbs = [
    { title: 'Economy Code Management', href: route('economy_code.index') },
];

// ---------------------------------------------
// --- FORM & VALIDATION (VeeValidate & Inertia) ---
// ---------------------------------------------

// Form Modals State
const showCreateCodeModal = ref(false);
const isEdit = ref(false);
const currentCodeId = ref(null);

// 1. Validation Schema
const validationSchema = yup.object({
    name: yup
        .string()
        .required('Code Name is required.')
        .max(255, 'Code Name cannot exceed 255 characters.'),
    code: yup
        .string()
        .required('Unique Code is required.')
        .max(20, 'Unique Code cannot exceed 20 characters.'),
    status: yup
        .number()
        .required('Status is required.')
        .oneOf([1, 0], 'Invalid status value.'),
});

const formDefaults = {
    name: '',
    code: '',
    status: 1,
};

// 2. Setup VeeValidate form
const { handleSubmit, resetForm, setErrors } = useVeeForm({
    validationSchema: validationSchema,
    initialValues: formDefaults,
});

// 3. Define fields using useField (links input fields to validation state)
const { value: name, errorMessage: nameError } = useField('name');
const { value: code, errorMessage: codeError } = useField('code');
const { value: status, errorMessage: statusError } = useField('status');

// 4. Setup Inertia form (used for submission)
const form = useForm(formDefaults);

// Watch for Inertia server-side errors and pass them to VeeValidate
watch(
    () => form.errors,
    (newErrors) => {
        if (Object.keys(newErrors).length > 0) {
            setErrors(newErrors);
            toast.add({
                severity: 'error',
                summary: 'Server Error',
                detail: 'A server error occurred. Please check the form fields.',
                life: 5000,
            });
        }
    },
    { deep: true },
);

// ---------------------------------------------
// --- CRUD ACTION HANDLERS ---
// ---------------------------------------------

// Opens the modal for creating a new code
const handleCreateCode = () => {
    isEdit.value = false;
    currentCodeId.value = null;
    resetForm(); // Reset VeeValidate state
    form.reset(); // Reset Inertia form state
    showCreateCodeModal.value = true;
};

// Opens the modal for editing an existing code
const handleEditCode = (codeItem) => {
    isEdit.value = true;
    currentCodeId.value = codeItem.id;

    // Set VeeValidate values (and thus the v-model refs)
    resetForm({
        values: {
            name: codeItem.name,
            code: codeItem.code,
            status: codeItem.status,
        },
    });

    // Also set Inertia form values immediately for the PUT submission
    form.name = codeItem.name;
    form.code = codeItem.code;
    form.status = codeItem.status;

    showCreateCodeModal.value = true;
};

// Handles form submission (Create or Update)
const saveCode = handleSubmit(async (values) => {
    // 1. Explicitly update Inertia form with latest validated values
    Object.assign(form, values);

    const options = {
        preserveScroll: true,
        onSuccess: () => {
            // Close the modal and reset state only upon successful save/update
            showCreateCodeModal.value = false;
            form.reset();
            resetForm();
            toast.add({
                severity: 'success',
                summary: 'Operation Successful',
                detail: isEdit.value
                    ? 'Economy Code updated successfully.'
                    : 'New Economy Code created successfully.',
                life: 3000,
            });
        },
        onError: (errors) => {
            // Errors are handled by the watch block above, but toast provides general feedback
            toast.add({
                severity: 'error',
                summary: 'Validation Failed',
                detail: 'Please fix the errors shown in the form fields.',
                life: 5000,
            });
        },
        onFinish: () => {
            form.processing = false;
        },
    };

    if (isEdit.value && currentCodeId.value) {
        // UPDATE: PUT request
        form.put(
            '/economy-codess/'+ currentCodeId.value ,
            options,
        );
    } else {
        // CREATE: POST request
        form.post('/economy-codess', options);
    }
});

// ---------------------------------------------
// --- CONFIRMATION MODAL (Delete) ---
// ---------------------------------------------
const showConfirmationModal = ref(false);
const currentCode = ref(null);
const currentAction = ref(null);

const openConfirmationModal = (codeItem, action) => {
    currentCode.value = codeItem;
    currentAction.value = action;
    showConfirmationModal.value = true;
};

const confirmAction = () => {
    showConfirmationModal.value = false;

    if (!currentCode.value || currentAction.value !== 'delete') return;

    // --- DELETE ACTION ---
    router.delete(
        route('economy_code.destroy', { economy_code: currentCode.value.id }),
        {
            preserveScroll: true,
            onSuccess: () => {
                toast.add({
                    severity: 'success',
                    summary: 'Deleted',
                    detail: `Economy Code ${currentCode.value.name} removed.`,
                    life: 3000,
                });
            },
            onError: (errors) => {
                toast.add({
                    severity: 'error',
                    summary: 'Error',
                    detail: errors.message || 'Failed to delete code.',
                    life: 5000,
                });
            },
        },
    );
};

// ---------------------------------------------
// --- ITEMS MODAL (View Sub-Categories) ---
// ---------------------------------------------
const showItemsModal = ref(false);
const codeItems = ref([]);
const isLoadingItems = ref(false);

// Function to implement exponential backoff retry for API calls (copied from original)
const fetchWithRetry = async (url, retries = 3, delay = 1000) => {
    for (let i = 0; i < retries; i++) {
        try {
            const response = await fetch(url);
            if (!response.ok) {
                // Treat non-2xx status codes as errors
                const errorBody = await response.json().catch(() => ({}));
                throw new Error(
                    `HTTP error! status: ${response.status}, message: ${errorBody.message || 'Unknown error'}`,
                );
            }
            return response;
        } catch (error) {
            if (i < retries - 1) {
                await new Promise((resolve) =>
                    setTimeout(resolve, delay * Math.pow(2, i)),
                );
            } else {
                throw error;
            }
        }
    }
};

// Fetches sub-items and displays the modal
const openItemsModal = (codeItem) => {
    currentCode.value = codeItem;
    showItemsModal.value = true;

    // Clear previous items and set loading state
    codeItems.value = [];
    isLoadingItems.value = true;

    const url = route('economy_code.items.fetch', {
        economy_code: codeItem.id,
    });

    fetchWithRetry(url)
        .then((response) => response.json())
        .then((data) => {
            codeItems.value = data.items || [];
        })
        .catch((error) => {
            console.error('Error fetching items:', error);
            toast.add({
                severity: 'error',
                summary: 'Error',
                detail: 'Could not load sub-items.',
                life: 3000,
            });
        })
        .finally(() => {
            isLoadingItems.value = false;
        });
};

// ---------------------------------------------
// --- PAGINATION HANDLER ---
// ---------------------------------------------

const onPageChange = (event) => {
    // Navigate using the link provided by Laravel's pagination structure
    // event.page is 0-indexed, so we use codeData.value.links[event.page + 1]
    const url = codeData.value.links[event.page + 1]?.url;

    if (url) {
        router.get(url, {}, { preserveState: true, replace: true });
    } else {
        console.warn('Attempted to navigate to a page without a valid URL.');
    }
};
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head title="Economy Code Management" />

        <!-- Global Toast Component -->
        <Toast />

        <!-- Statistics Cards -->
        <div class="mb-6 grid grid-cols-1 gap-4 md:grid-cols-3">
            <Card>
                <template #title>
                    <div class="flex items-center gap-2">
                        <i class="pi pi-building-columns text-blue-500"></i>
                        <span>Total Economy Codes</span>
                    </div>
                </template>
                <template #content>
                    <div class="text-3xl font-bold text-gray-800">
                        {{ statistics.total }}
                    </div>
                    <p class="mt-2 text-sm text-gray-500">All economy codes</p>
                </template>
            </Card>

            <Card>
                <template #title>
                    <div class="flex items-center gap-2">
                        <i class="pi pi-check-circle text-green-500"></i>
                        <span>Active Codes</span>
                    </div>
                </template>
                <template #content>
                    <div class="text-3xl font-bold text-green-600">
                        {{ statistics.active }}
                    </div>
                    <p class="mt-2 text-sm text-gray-500">
                        Currently active codes
                    </p>
                </template>
            </Card>

            <Card>
                <template #title>
                    <div class="flex items-center gap-2">
                        <i class="pi pi-times-circle text-red-500"></i>
                        <span>Inactive Codes</span>
                    </div>
                </template>
                <template #content>
                    <div class="text-3xl font-bold text-red-600">
                        {{ statistics.inactive }}
                    </div>
                    <p class="mt-2 text-sm text-gray-500">Deactivated codes</p>
                </template>
            </Card>
        </div>

        <!-- Main Card -->
        <Card class="rounded-2xl border-none shadow-xl">
            <template #title>
                <div
                    class="flex flex-col items-start justify-between p-3 sm:flex-row sm:items-center"
                >
                    <h2 class="text-2xl font-extrabold text-gray-800">
                        Economy Codes List ({{ statistics.total }})
                    </h2>
                    <div
                        class="mt-4 flex flex-col items-stretch gap-3 sm:mt-0 sm:flex-row sm:items-center"
                    >
                        <!-- Search Input (Global Filter) -->
                        <span class="p-input-icon-left w-full sm:w-auto">
                            <i class="pi pi-search" />
                            <InputText
                                v-model="globalFilter"
                                placeholder="Search Codes..."
                                class="p-inputtext-sm w-full"
                            />
                        </span>

                        <!-- Create Button -->
                        <Button
                            label="Create New Code"
                            icon="pi pi-plus"
                            severity="primary"
                            @click="handleCreateCode"
                            class="p-button-sm w-full sm:w-auto"
                        />
                    </div>
                </div>
            </template>

            <template #content>
                <div class="overflow-x-auto">
                    <DataTable
                        :value="filteredCodeData"
                        dataKey="id"
                        stripedRows
                        responsiveLayout="scroll"
                        class="p-datatable-sm rounded-lg"
                        :emptyMessage="'No Economy Code found matching your search. Try adjusting your filter or creating a new one.'"
                    >
                        <Column field="id" header="ID" headerStyle="width: 5%">
                            <template #body="slotProps">
                                <span class="text-500 font-medium">{{
                                    slotProps.data.id
                                }}</span>
                            </template>
                        </Column>

                        <Column
                            field="name"
                            header="Name"
                            headerStyle="width: 40%"
                            :sortable="true"
                        >
                            <template #body="slotProps">
                                <span class="font-semibold">{{
                                    slotProps.data.name
                                }}</span>
                            </template>
                        </Column>

                        <Column
                            field="code"
                            header="Code"
                            headerStyle="width: 25%"
                            :sortable="true"
                        >
                            <template #body="slotProps">
                                <Tag
                                    :value="slotProps.data.code"
                                    severity="info"
                                    class="font-mono text-sm"
                                />
                            </template>
                        </Column>

                        <Column
                            field="status"
                            header="Status"
                            headerStyle="width: 15%"
                            :sortable="true"
                        >
                            <template #body="slotProps">
                                <Tag
                                    :value="
                                        getStatusText(slotProps.data.status)
                                    "
                                    :severity="
                                        getStatusSeverity(slotProps.data.status)
                                    "
                                />
                            </template>
                        </Column>

                        <Column
                            header="Actions"
                            headerStyle="width: 15%"
                            bodyClass="text-center"
                        >
                            <template #body="slotProps">
                                <div class="flex justify-center gap-1">
                                    <Button
                                        icon="pi pi-list"
                                        severity="info"
                                        text
                                        rounded
                                        v-tooltip.top="'View Sub-Items'"
                                        @click="openItemsModal(slotProps.data)"
                                    />

                                    <Button
                                        icon="pi pi-pencil"
                                        severity="secondary"
                                        text
                                        rounded
                                        v-tooltip.top="'Edit Code'"
                                        @click="handleEditCode(slotProps.data)"
                                    />

                                    <Button
                                        icon="pi pi-trash"
                                        severity="danger"
                                        text
                                        rounded
                                        v-tooltip.top="'Delete Code'"
                                        @click="
                                            openConfirmationModal(
                                                slotProps.data,
                                                'delete',
                                            )
                                        "
                                    />
                                </div>
                            </template>
                        </Column>
                    </DataTable>
                </div>

                <!-- Paginator -->
                <div
                    class="mt-4 flex justify-end"
                    v-if="codeData.total > paginatorRows && !globalFilter"
                >
                    <Paginator
                        :rows="paginatorRows"
                        :totalRecords="paginatorTotalRecords"
                        :first="(paginatorCurrentPage - 1) * paginatorRows"
                        @page="onPageChange"
                        :template="{
                            default:
                                'FirstPageLink PrevPageLink PageLinks NextPageLink LastPageLink',
                        }"
                    />
                </div>
            </template>
        </Card>

        <!-- 1. DELETE CONFIRMATION DIALOG -->
        <Dialog
            v-model:visible="showConfirmationModal"
            :style="{ width: '450px' }"
            header="Confirm Deletion"
            :modal="true"
            :closable="!form.processing"
        >
            <div
                class="flex items-center rounded-lg border-l-4 border-red-500 bg-red-50 p-4"
            >
                <i
                    class="pi pi-exclamation-triangle mr-3 text-3xl text-red-600"
                ></i>

                <span v-if="currentCode" class="text-gray-700">
                    Are you sure you want to **permanently delete** Economy
                    Code: **{{ currentCode.name }}**? This action cannot be
                    undone.
                </span>
            </div>

            <template #footer>
                <Button
                    label="Cancel"
                    icon="pi pi-times"
                    @click="showConfirmationModal = false"
                    text
                    :disabled="form.processing"
                />
                <Button
                    label="Yes, Delete"
                    icon="pi pi-trash"
                    severity="danger"
                    @click="confirmAction"
                    :loading="form.processing"
                    :disabled="form.processing"
                    autofocus
                />
            </template>
        </Dialog>

        <!-- 2. VIEW ITEMS DIALOG (EconomyCodeItem sub-categories) -->
        <Dialog
            v-model:visible="showItemsModal"
            :style="{ width: '90vw', maxWidth: '600px' }"
            :header="`Sub-Items for Code: ${currentCode?.code || 'N/A'}`"
            :modal="true"
        >
            <div v-if="isLoadingItems" class="p-5 text-center">
                <i class="pi pi-spin pi-spinner text-primary mb-2 text-4xl"></i>
                <p class="text-600">Loading sub-items...</p>
            </div>

            <div v-else-if="codeItems.length > 0">
                <DataTable
                    :value="codeItems"
                    stripedRows
                    class="p-datatable-sm"
                >
                    <Column
                        field="id"
                        header="Item ID"
                        headerStyle="width: 15%"
                    />
                    <Column
                        field="name"
                        header="Item Name"
                        headerStyle="width: 55%"
                    />
                    <Column
                        field="code"
                        header="Item Code"
                        headerStyle="width: 30%"
                    >
                        <template #body="slotProps">
                            <Tag
                                :value="slotProps.data.code"
                                severity="warning"
                            />
                        </template>
                    </Column>
                </DataTable>
            </div>

            <div v-else class="text-500 rounded-lg bg-gray-50 p-5 text-center">
                <i
                    class="pi pi-info-circle text-primary mb-2 block text-2xl"
                ></i>
                <p>
                    No specific sub-items defined yet for **{{
                        currentCode?.name
                    }}**.
                </p>
            </div>

            <template #footer>
                <Button
                    label="Close"
                    icon="pi pi-check"
                    @click="showItemsModal = false"
                    severity="secondary"
                />
            </template>
        </Dialog>

        <!-- 3. CREATE/EDIT Code DIALOG -->
        <Dialog
            v-model:visible="showCreateCodeModal"
            :style="{ width: '90vw', maxWidth: '500px' }"
            :header="isEdit ? 'Edit Economy Code' : 'Create New Economy Code'"
            :modal="true"
            :closable="!form.processing"
            class="p-fluid"
        >
            <!-- Form wrapper uses @submit.prevent to link to saveCode (VeeValidate handler) -->
            <form @submit.prevent="saveCode" class="mt-2 flex flex-col gap-5">
                <!-- Economy Code Name -->
                <div class="field">
                    <label for="name" class="mb-2 block font-semibold"
                        >Economy Code Name</label
                    >
                    <InputText
                        id="name"
                        v-model="name"
                        :class="{ 'p-invalid': nameError }"
                        placeholder="e.g., Personnel Emoluments"
                        class="w-full"
                    />
                    <Message
                        v-if="nameError"
                        severity="error"
                        :closable="false"
                        class="mt-2 text-sm"
                        >{{ nameError }}</Message
                    >
                </div>

                <!-- Unique Code -->
                <div class="field">
                    <label for="code" class="mb-2 block font-semibold"
                        >Unique Code</label
                    >
                    <InputText
                        id="code"
                        v-model="code"
                        :class="{ 'p-invalid': codeError }"
                        maxlength="20"
                        placeholder="e.g., 221001"
                        class="w-full"
                    />
                    <Message
                        v-if="codeError"
                        severity="error"
                        :closable="false"
                        class="mt-2 text-sm"
                        >{{ codeError }}</Message
                    >
                </div>

                <!-- Status -->
                <div class="field">
                    <label for="status" class="mb-2 block font-semibold"
                        >Status</label
                    >
                    <Dropdown
                        id="status"
                        v-model="status"
                        :options="statusOptions"
                        optionLabel="label"
                        optionValue="value"
                        :class="{ 'p-invalid': statusError }"
                        placeholder="Select Status"
                        class="w-full"
                    />
                    <Message
                        v-if="statusError"
                        severity="error"
                        :closable="false"
                        class="mt-2 text-sm"
                        >{{ statusError }}</Message
                    >
                </div>

                <!-- Submit button inside the form (optional, moved to footer below) -->
            </form>

            <template #footer>
                <Button
                    label="Cancel"
                    icon="pi pi-times"
                    @click="showCreateCodeModal = false"
                    text
                    :disabled="form.processing"
                />
                <Button
                    :label="isEdit ? 'Update Code' : 'Save Code'"
                    icon="pi pi-check"
                    @click="saveCode"
                    :loading="form.processing"
                    :disabled="form.processing"
                    autofocus
                />
            </template>
        </Dialog>
    </AppLayout>
</template>

<style scoped>
.grid {
    display: grid;
}
</style>
