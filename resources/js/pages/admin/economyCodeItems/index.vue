<script setup>
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import { useConfirm } from 'primevue/useconfirm';
import { useToast } from 'primevue/usetoast';
import { computed, onMounted, ref, watch } from 'vue';

// --- PrimeVue Imports ---
import Button from 'primevue/button';
import Card from 'primevue/card';
import Column from 'primevue/column';
import ConfirmDialog from 'primevue/confirmdialog';
import DataTable from 'primevue/datatable';
import Dialog from 'primevue/dialog';
import Dropdown from 'primevue/dropdown';
import InputSwitch from 'primevue/inputswitch';
import InputText from 'primevue/inputtext';
import Paginator from 'primevue/paginator';
import Tag from 'primevue/tag';
import Toast from 'primevue/toast';

const toast = useToast();
const confirm = useConfirm();

// ---------------------------------------------
// --- PROPS: Economy Code Item Data ---
// ---------------------------------------------
const props = defineProps({
    economyCodeItems: {
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
    // The list of parent Economy Codes for the dropdown
    economyCodes: {
        type: Array,
        default: () => [],
    },
});

// --- STATE ---
const showCreateEditModal = ref(false);
const isEdit = ref(false);
const globalFilter = ref('');
const currentItem = ref(null);

// ---------------------------------------------
// --- DATA & COMPUTED PROPERTIES ---
// ---------------------------------------------

// Local reactive array to hold data for immediate UI updates
const itemDataArray = ref(props.economyCodeItems.data);
watch(
    () => props.economyCodeItems.data,
    (newData) => {
        itemDataArray.value = newData;
    },
    { deep: true },
);

const itemData = computed(() => props.economyCodeItems);
const paginatorTotalRecords = computed(
    () => itemData.value.meta?.total || itemData.value.total || 0,
);
const paginatorCurrentPage = computed(
    () => itemData.value.meta?.current_page || itemData.value.current_page || 1,
);
const paginatorRows = computed(
    () => itemData.value.meta?.per_page || itemData.value.per_page || 10,
);

// Statistics computed property
const statistics = computed(() => {
    return {
        total: props.statistics?.total || 0,
        active: props.statistics?.active || 0,
        inactive: props.statistics?.inactive || 0,
    };
});

// Inertia Form for Create/Edit
const formDefaults = {
    economy_code_id: null,
    name: '',
    code: '',
    status: 1,
};
const form = useForm(formDefaults);

// ---------------------------------------------
// --- HELPER FUNCTIONS & ROUTING ---
// ---------------------------------------------
const route = (name, params) => {
    // Updated to match your actual route names (economy-code-items plural)
    if (
        name === 'economy-code-itemss.store' ||
        name === 'economy-code-itemss.index'
    ) {
        return '/economy-code-itemss';
    }
    if (
        name === 'economy-code-itemss.update' &&
        params &&
        params.economy_code_item
    ) {
        const id = params.economy_code_item;
        return `/economy-code-itemss/${id}`;
    }
    if (
        name === 'economy-code-itemss.destroy' &&
        params &&
        params.economy_code_item
    ) {
        const id = params.economy_code_item;
        return `/economy-code-itemss/${id}`;
    }
    return `/${name.replace(/\./g, '/')}`;
};

const breadcrumbs = [
    {
        title: 'Economic Code Items Management',
        href: route('economy-code-itemss.index'),
    },
];

const getStatusSeverity = (status) => {
    return status === 1 ? 'success' : 'danger';
};

const getStatusText = (status) => {
    return status === 1 ? 'Active' : 'Inactive';
};

// --- CRUD ACTION HANDLERS ---
const handleCreateItem = () => {
    isEdit.value = false;
    currentItem.value = null;
    form.reset();
    form.status = 1; // Default to active
    showCreateEditModal.value = true;
};

const handleEditItem = (item) => {
    isEdit.value = true;
    currentItem.value = item;

    // Reset form first
    form.reset();

    // Load data into the form
    form.economy_code_id = item.economy_code_id;
    form.name = item.name;
    form.code = item.code;
    form.status = item.status;

    showCreateEditModal.value = true;
};

const saveItem = () => {
    const options = {
        preserveScroll: true,
        onSuccess: () => {
            showCreateEditModal.value = false;
            form.reset();
            currentItem.value = null;
            toast.add({
                severity: 'success',
                summary: 'Operation Successful',
                detail: isEdit.value
                    ? 'Item updated successfully.'
                    : 'New Item created successfully.',
                life: 3000,
            });
        },
        onError: (errors) => {
            // Safely handle errors - errors might be null or undefined
            const errorMessage =
                errors?.message ||
                (typeof errors === 'string'
                    ? errors
                    : 'Please check the form for errors.');

            toast.add({
                severity: 'error',
                summary: 'Error',
                detail: errorMessage,
                life: 5000,
            });
        },
        onFinish: () => {
            // Ensure processing state is reset
            form.processing = false;
        },
    };

    if (isEdit.value && currentItem.value) {
        // Use PUT for update
        form.put(
            '/economy-code-itemss/' +
            currentItem.value.id,
            options,
        );
    } else {
        // Use POST for store
        // form.post(route('economy-code-items.store'), options);
        form.post('/economy-code-itemss', options);
    }
};

const confirmDeleteItem = (item) => {
    confirm.require({
        message: `Are you sure you want to delete the item: ${item.name} (${item.code})? This action cannot be undone.`,
        header: 'Delete Confirmation',
        icon: 'pi pi-info-circle',
        rejectClass: 'p-button-secondary p-button-outlined',
        acceptClass: 'p-button-danger',
        rejectLabel: 'Cancel',
        acceptLabel: 'Delete',
        accept: () => {
            router.delete(
                route('economy-code-items.destroy', {
                    economy_code_item: item.id,
                }),
                {
                    preserveScroll: true,
                    onSuccess: () => {
                        toast.add({
                            severity: 'success',
                            summary: 'Deleted',
                            detail: 'Item deleted successfully.',
                            life: 3000,
                        });
                    },
                    onError: (errors) => {
                        // Safely handle delete errors
                        const errorMessage =
                            errors?.message ||
                            (typeof errors === 'string'
                                ? errors
                                : 'Failed to delete item. Please try again.');

                        toast.add({
                            severity: 'error',
                            summary: 'Error',
                            detail: errorMessage,
                            life: 3000,
                        });
                    },
                },
            );
        },
    });
};

const onPageChange = (event) => {
    console.log(props.itemData);
    const pageUrl = itemData.value.meta.links[event.page + 1]?.url;

    if (pageUrl) {
        router.get(pageUrl, {}, { preserveState: true, replace: true });
    } else {
        console.warn('Attempted to navigate to a page without a valid URL.');
    }
};

// --- FLASH MESSAGE HANDLERS ---
onMounted(() => {
    if (props.flash?.success) {
        toast.add({
            severity: 'success',
            summary: 'Success',
            detail: props.flash.success,
            life: 3000,
        });
    }

    if (props.flash?.error) {
        toast.add({
            severity: 'error',
            summary: 'Error',
            detail: props.flash.error,
            life: 5000,
        });
    }
});
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">

        <Head title="Economic Code Item Management" />

        <Toast />
        <ConfirmDialog />

        <!-- Statistics Cards -->
        <div class="mb-6 grid grid-cols-1 gap-4 md:grid-cols-3">
            <Card>
                <template #title>
                    <div class="flex items-center gap-2">
                        <i class="pi pi-list text-blue-500"></i>
                        <span>Total Items</span>
                    </div>
                </template>
                <template #content>
                    <div class="text-3xl font-bold text-gray-800">
                        {{ statistics.total }}
                    </div>
                    <p class="mt-2 text-sm text-gray-500">
                        All economic code items
                    </p>
                </template>
            </Card>

            <Card>
                <template #title>
                    <div class="flex items-center gap-2">
                        <i class="pi pi-check-circle text-green-500"></i>
                        <span>Active Items</span>
                    </div>
                </template>
                <template #content>
                    <div class="text-3xl font-bold text-green-600">
                        {{ statistics.active }}
                    </div>
                    <p class="mt-2 text-sm text-gray-500">
                        Currently active items
                    </p>
                </template>
            </Card>

            <Card>
                <template #title>
                    <div class="flex items-center gap-2">
                        <i class="pi pi-times-circle text-red-500"></i>
                        <span>Inactive Items</span>
                    </div>
                </template>
                <template #content>
                    <div class="text-3xl font-bold text-red-600">
                        {{ statistics.inactive }}
                    </div>
                    <p class="mt-2 text-sm text-gray-500">Deactivated items</p>
                </template>
            </Card>
        </div>

        <!-- Main Card -->
        <Card class="rounded-2xl border-none shadow-xl">
            <template #title>
                <div class="flex flex-col items-start justify-between p-3 sm:flex-row sm:items-center">
                    <h2 class="text-2xl font-extrabold text-gray-800">
                        Economic Code Items List ({{
                            paginatorTotalRecords || 0
                        }})
                    </h2>
                    <div class="mt-4 flex flex-col items-stretch gap-3 sm:mt-0 sm:flex-row sm:items-center">
                        <!-- Search Input -->
                        <span class="p-input-icon-left w-full sm:w-auto">
                            <i class="pi pi-search" />
                            <InputText v-model="globalFilter" placeholder="Search Items..."
                                class="p-inputtext-sm w-full" />
                        </span>

                        <!-- Create Button -->
                        <Button label="Create New Item" icon="pi pi-plus" severity="primary" @click="handleCreateItem"
                            class="p-button-sm w-full sm:w-auto" />
                    </div>
                </div>
            </template>

            <template #content>
                <!-- Search Results Info -->
                <div v-if="globalFilter" class="mb-4 rounded-lg border border-blue-100 bg-blue-50 p-3">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-2">
                            <i class="pi pi-info-circle text-blue-500"></i>
                            <span class="text-blue-700">
                                Searching for: "<strong>{{
                                    globalFilter
                                    }}</strong>"
                            </span>
                        </div>
                        <Button label="Clear" icon="pi pi-times" text size="small" @click="globalFilter = ''" />
                    </div>
                </div>

                <div class="overflow-x-auto">
                    <DataTable :value="itemDataArray" dataKey="id" stripedRows responsiveLayout="scroll"
                        class="p-datatable-sm rounded-lg"
                        :emptyMessage="'No Economic Code Item found. Try creating a new one or adjusting your search.'">
                        <!-- <Column field="id" header="ID" headerStyle="width: 5%">
                            <template #body="slotProps">
                                <span class="text-500 font-medium">{{
                                    slotProps.data.id
                                }}</span>
                            </template>
    </Column> -->

                        <Column field="economy_code_name" header="Economic Parent Code" headerStyle="width: 25%"
                            :sortable="true">
                            <template #body="slotProps">
                                <Tag :value="slotProps.data.economy_code_name ||
                                    'N/A'
                                    " severity="info" class="font-mono text-sm" />
                            </template>
                        </Column>

                        <Column field="name" header="Item Name" headerStyle="width: 30%" :sortable="true">
                            <template #body="slotProps">
                                <span class="font-semibold">{{
                                    slotProps.data.name
                                    }}</span>
                            </template>
                        </Column>

                        <Column field="code" header="Code" headerStyle="width: 15%" :sortable="true">
                            <template #body="slotProps">
                                <Tag :value="slotProps.data.code" severity="secondary" class="font-mono text-sm" />
                            </template>
                        </Column>

                        <Column field="status" header="Status" headerStyle="width: 10%" :sortable="true">
                            <template #body="slotProps">
                                <Tag :value="getStatusText(slotProps.data.status)
                                    " :severity="getStatusSeverity(slotProps.data.status)
                                        " />
                            </template>
                        </Column>

                        <Column header="Actions" headerStyle="width: 15%" bodyClass="text-center">
                            <template #body="slotProps">
                                <div class="flex justify-center gap-2">
                                    <Button icon="pi pi-pencil" severity="secondary" text rounded
                                        v-tooltip.top="'Edit Item'" @click="handleEditItem(slotProps.data)" />
                                    <Button icon="pi pi-trash" severity="danger" text rounded
                                        v-tooltip.top="'Delete Item'" @click="
                                            confirmDeleteItem(slotProps.data)
                                            " />
                                </div>
                            </template>
                        </Column>
                    </DataTable>
                </div>

                <!-- Paginator -->
                <div class="mt-4 flex justify-end" v-if="
                    paginatorTotalRecords > paginatorRows && !globalFilter
                ">
                    <Paginator :rows="paginatorRows" :totalRecords="paginatorTotalRecords"
                        :first="(paginatorCurrentPage - 1) * paginatorRows" @page="onPageChange" :template="{
                            default:
                                'FirstPageLink PrevPageLink PageLinks NextPageLink LastPageLink',
                        }" />
                </div>

                <!-- No Results -->
                <div v-if="!itemDataArray.length" class="py-12 text-center">
                    <i class="pi pi-search mb-4 text-4xl text-gray-300"></i>
                    <h4 class="mb-2 text-lg font-semibold text-gray-600">
                        {{
                            globalFilter
                                ? 'No matching records found'
                                : 'No economic code items yet'
                        }}
                    </h4>
                    <p class="mb-4 text-gray-500">
                        {{
                            globalFilter
                                ? 'Try a different search term'
                                : 'Create your first economic code item to get started'
                        }}
                    </p>
                    <Button v-if="!globalFilter" label="Create First Item" icon="pi pi-plus"
                        @click="handleCreateItem" />
                    <Button v-if="globalFilter" label="Clear Search" icon="pi pi-times" text
                        @click="globalFilter = ''" />
                </div>
            </template>
        </Card>

        <!-- Create/Edit Modal -->
        <Dialog v-model:visible="showCreateEditModal" :style="{ width: '500px' }" :header="isEdit
                ? 'Edit Economic Code Item'
                : 'Create New Economic Code Item'
            " :modal="true" class="p-fluid" :closable="!form.processing">
            <form @submit.prevent="saveItem" class="mt-2 flex flex-col gap-4">
                <div class="field">
                    <label for="economy_code_id" class="mb-2 block font-semibold">
                        Parent Economic Code <span class="text-red-500">*</span>
                    </label>
                    <Dropdown id="economy_code_id" v-model="form.economy_code_id" :options="props.economyCodes"
                        optionLabel="name" optionValue="id" placeholder="Select Economic Parent Code" class="w-full"
                        :class="{ 'p-invalid': form.errors.economy_code_id }" :filter="true" :disabled="form.processing"
                        :showClear="true" />
                    <small v-if="form.errors.economy_code_id" class="p-error mt-1 block">
                        {{ form.errors.economy_code_id }}
                    </small>
                </div>

                <div class="field">
                    <label for="name" class="mb-2 block font-semibold">
                        Item Name / Description
                        <span class="text-red-500">*</span>
                    </label>
                    <InputText id="name" v-model="form.name" placeholder="e.g., Office Supplies" class="w-full"
                        :class="{ 'p-invalid': form.errors.name }" :disabled="form.processing" />
                    <small v-if="form.errors.name" class="p-error mt-1 block">
                        {{ form.errors.name }}
                    </small>
                </div>

                <div class="field">
                    <label for="code" class="mb-2 block font-semibold">
                        Item Code <span class="text-red-500">*</span>
                    </label>
                    <InputText id="code" v-model="form.code" placeholder="e.g., 5210" class="w-full"
                        :class="{ 'p-invalid': form.errors.code }" :disabled="form.processing" />
                    <small v-if="form.errors.code" class="p-error mt-1 block">
                        {{ form.errors.code }}
                    </small>
                </div>

                <div class="field flex items-center gap-3">
                    <label for="status" class="font-semibold">Status</label>
                    <InputSwitch id="status" v-model="form.status" :trueValue="1" :falseValue="0"
                        :disabled="form.processing" />
                    <span class="text-sm" :class="form.status === 1
                            ? 'text-green-600'
                            : 'text-red-600'
                        ">
                        {{ form.status === 1 ? 'Active' : 'Inactive' }}
                    </span>
                </div>
            </form>

            <template #footer>
                <div class="flex w-full justify-end gap-2">
                    <Button label="Cancel" icon="pi pi-times" @click="showCreateEditModal = false" text
                        :disabled="form.processing" />
                    <Button :label="isEdit ? 'Update Item' : 'Save Item'" icon="pi pi-check" @click="saveItem"
                        :loading="form.processing" :disabled="form.processing" autofocus />
                </div>
            </template>
        </Dialog>
    </AppLayout>
</template>

<style scoped>
.grid {
    display: grid;
}

/* Form styling improvements */
.form-group {
    margin-bottom: 1rem;
}

.form-label {
    display: block;
    font-weight: 600;
    margin-bottom: 0.5rem;
    color: #374151;
}

/* Ensure consistent form widths */
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
}

/* Error styling */
:deep(.p-invalid) {
    border-color: #f87171 !important;
}

.p-error {
    color: #ef4444;
    font-size: 0.875rem;
    line-height: 1.25rem;
}
</style>
