<script setup>
import { Head, router, useForm } from '@inertiajs/vue3';
import { computed, onMounted, ref, watch } from 'vue';

// --- Validation Imports ---
import { useField, useForm as useVeeForm } from 'vee-validate';
import * as yup from 'yup';

// --- PrimeVue Imports (Direct imports for stability) ---
import AppLayout from '@/layouts/AppLayout.vue';
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
// --- PROPS: Sectors and MDAs from Controller ---
// ---------------------------------------------
const props = defineProps({
    sectors: {
        type: Object,
        required: true,
    },
    mdas: {
        type: Array,
        required: true,
    },
    flash: {
        type: Object,
        default: () => ({ message: null }),
    },
});

// --- SUCCESS MESSAGE HANDLER ---
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
// --- CORE DATA & STATE ---
// ---------------------------------------------
const sectorData = computed(() => props.sectors);
const showCreateSectorModal = ref(false);
const isEdit = ref(false);
const currentSectorId = ref(null);
const globalFilter = ref('');
const showConfirmationModal = ref(false);
const currentSector = ref(null);
const currentAction = ref(null);

const statusOptions = ref([
    { label: 'Active', value: 1 },
    { label: 'Inactive', value: 0 },
]);

// ---------------------------------------------
// --- FORM & VALIDATION (VeeValidate & Inertia) ---
// ---------------------------------------------

// 1. Define the validation schema using Yup
const validationSchema = yup.object({
    name: yup
        .string()
        .required('Sector Name is required.')
        .max(255, 'Sector Name cannot exceed 255 characters.'),
    code: yup
        .string()
        .required('Short Code is required.')
        .max(10, 'Short Code cannot exceed 10 characters.'),
    mda_id: yup
        .number()
        .required('MDA is required.')
        .positive('Please select a valid MDA.'),
    initials: yup
        .string()
        .required('Initials are required.')
        .max(10, 'Initials cannot exceed 10 characters.'),
    location: yup
        .string()
        .nullable()
        .max(255, 'Location cannot exceed 255 characters.'),
    status: yup
        .number()
        .required('Status is required.')
        .oneOf([1, 0], 'Invalid status selected.'),
});

const formDefaults = {
    name: '',
    code: '',
    mda_id: null,
    initials: '',
    location: '',
    status: 1,
};

// 2. Setup VeeValidate form
const { handleSubmit, resetForm, setErrors } = useVeeForm({
    validationSchema: validationSchema,
    initialValues: formDefaults,
});

// 3. Define fields using useField
const { value: name, errorMessage: nameError } = useField('name');
const { value: code, errorMessage: codeError } = useField('code');
const { value: mda_id, errorMessage: mdaIdError } = useField('mda_id');
const { value: initials, errorMessage: initialsError } = useField('initials');
const { value: location, errorMessage: locationError } = useField('location');
const { value: status, errorMessage: statusError } = useField('status');

// 4. Setup Inertia form using VeeValidate field values
const form = useForm(formDefaults);

// Watch for Inertia server-side errors
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
// --- ACTION HANDLERS ---
// ---------------------------------------------

// Handler for Create Button (Opens Modal)
const handleCreateSector = () => {
    isEdit.value = false;
    currentSectorId.value = null;
    resetForm();
    form.reset();
    showCreateSectorModal.value = true;
};

// Handler for Edit Action (Opens Modal and loads data)
const handleEditSector = (sector) => {
    isEdit.value = true;
    currentSectorId.value = sector.id;

    // Set values for VeeValidate and Inertia
    const sectorData = {
        name: sector.name,
        code: sector.code,
        mda_id: Number(sector.mda_id),
        initials: sector.initials,
        location: sector.location || '',
        status: Number(sector.status),
    };

    resetForm({ values: sectorData });
    Object.assign(form, sectorData);
    showCreateSectorModal.value = true;
};

// Handler for saving the Sector (POST/PUT submission)
const saveSector = handleSubmit(async (values) => {
    Object.assign(form, values);

    const options = {
        preserveScroll: true,
        onSuccess: () => {
            showCreateSectorModal.value = false;
            form.reset();
            resetForm();
            toast.add({
                severity: 'success',
                summary: 'Operation Successful',
                detail: isEdit.value
                    ? 'Sector updated successfully.'
                    : 'New Sector created successfully.',
                life: 3000,
            });
        },
        onError: () => {
            toast.add({
                severity: 'error',
                summary: 'Validation Failed',
                detail: 'Please fix the errors shown in the form fields.',
                life: 5000,
            });
        },
    };

    if (isEdit.value && currentSectorId.value) {
        form.put(
            route('sectors.update', { sector: currentSectorId.value }),
            options,
        );
    } else {
        form.post(route('sectors.store'), options);
    }
});

// Helper for Inertia delete (triggered by confirmation modal)
const confirmAction = () => {
    showConfirmationModal.value = false;

    if (!currentSector.value || currentAction.value !== 'delete') return;

    router.delete(
        route('sectors.destroy', { sector: currentSector.value.id }),
        {
            preserveScroll: true,
            onSuccess: () => {
                toast.add({
                    severity: 'success',
                    summary: 'Deleted',
                    detail: `Sector ${currentSector.value.name} removed.`,
                    life: 3000,
                });
            },
            onError: (errors) => {
                toast.add({
                    severity: 'error',
                    summary: 'Error',
                    detail: errors.message || 'Failed to delete Sector.',
                    life: 5000,
                });
            },
        },
    );
};

const openConfirmationModal = (sector, action) => {
    currentSector.value = sector;
    currentAction.value = action;
    showConfirmationModal.value = true;
};

// --- COMPUTED PROPERTIES for Paginator ---
const paginatorTotalRecords = computed(() => sectorData.value.total);
const paginatorCurrentPage = computed(() => sectorData.value.current_page);
const paginatorRows = computed(() => sectorData.value.per_page);

const onPageChange = (event) => {
    const url = sectorData.value.links[event.page + 1].url;
    if (url) {
        router.get(url, {}, { preserveState: true, replace: true });
    }
};

const breadcrumbs = [
    { title: 'Sector Management', href: route('sectors.index') },
];

// --- Helper function for Tag styling ---
const getStatusSeverity = (status) => {
    switch (Number(status)) {
        case 1:
            return 'success';
        case 0:
            return 'warning';
        default:
            return 'info';
    }
};

// --- Helper function for status display ---
const getStatusLabel = (status) => {
    switch (Number(status)) {
        case 1:
            return 'Active';
        case 0:
            return 'Inactive';
        default:
            return 'Unknown';
    }
};
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head title="Sector Management" />
        <Toast />

        <Card class="w-full">
            <template #title>
                <div class="flex flex-wrap items-center justify-between gap-4">
                    <h2 class="text-xl font-bold">
                        Sectors List ({{ sectorData.total }})
                    </h2>
                    <div class="flex items-center gap-3">
                        <!-- Search Input -->
                        <span class="p-input-icon-left">
                            <i class="pi pi-search" />
                            <InputText
                                v-model="globalFilter"
                                placeholder="Search Sectors..."
                                class="w-full"
                            />
                        </span>
                        <!-- Create Button -->
                        <Button
                            label="Create New Sector"
                            icon="pi pi-plus"
                            severity="primary"
                            @click="handleCreateSector"
                        />
                    </div>
                </div>
            </template>

            <template #content>
                <div class="w-full overflow-x-auto">
                    <DataTable
                        :value="sectorData.data"
                        dataKey="id"
                        stripedRows
                        responsiveLayout="scroll"
                        class="p-datatable-sm w-full"
                        :globalFilterFields="[
                            'name',
                            'code',
                            'initials',
                            'location',
                            'mda.initials',
                            'mda.name',
                        ]"
                        :globalFilterValue="globalFilter"
                        filterDisplay="menu"
                        :emptyMessage="'No sectors found.'"
                    >
                        <Column field="id" header="ID" headerStyle="width: 5%">
                            <template #body="slotProps">
                                <span class="font-medium text-gray-500">
                                    {{ slotProps.data.id }}
                                </span>
                            </template>
                        </Column>

                        <Column
                            field="mda.initials"
                            header="MDA"
                            headerStyle="width: 10%"
                            :sortable="true"
                        >
                            <template #body="slotProps">
                                <Tag
                                    :value="slotProps.data.mda.initials"
                                    severity="info"
                                    v-tooltip.top="slotProps.data.mda.name"
                                />
                            </template>
                        </Column>

                        <Column
                            field="initials"
                            header="Initials"
                            headerStyle="width: 10%"
                            :sortable="true"
                        >
                            <template #body="slotProps">
                                <span class="font-semibold">
                                    {{ slotProps.data.initials }}
                                </span>
                            </template>
                        </Column>

                        <Column
                            field="name"
                            header="Sector Name"
                            headerStyle="width: 25%"
                            :sortable="true"
                        >
                            <template #body="slotProps">
                                <span>{{ slotProps.data.name }}</span>
                            </template>
                        </Column>

                        <Column
                            field="location"
                            header="Location"
                            headerStyle="width: 15%"
                            :sortable="true"
                        />

                        <Column
                            field="status"
                            header="Status"
                            headerStyle="width: 10%"
                            :sortable="true"
                        >
                            <template #body="slotProps">
                                <Tag
                                    :value="
                                        getStatusLabel(slotProps.data.status)
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
                                <div class="flex justify-center gap-2">
                                    <Button
                                        icon="pi pi-pencil"
                                        severity="secondary"
                                        text
                                        rounded
                                        v-tooltip.top="'Edit Sector'"
                                        @click="
                                            handleEditSector(slotProps.data)
                                        "
                                    />
                                    <Button
                                        icon="pi pi-trash"
                                        severity="danger"
                                        text
                                        rounded
                                        v-tooltip.top="'Delete Sector'"
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
                    v-if="sectorData.total > paginatorRows"
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

        <!-- DELETE CONFIRMATION DIALOG -->
        <Dialog
            v-model:visible="showConfirmationModal"
            :style="{ width: '450px' }"
            header="Confirm Deletion"
            :modal="true"
        >
            <div class="flex items-center p-4">
                <i
                    class="pi pi-exclamation-triangle mr-3 text-red-500"
                    style="font-size: 2rem"
                ></i>
                <span v-if="currentSector">
                    Are you sure you want to
                    <strong>permanently delete</strong> Sector:
                    <strong>{{ currentSector.name }}</strong
                    >?<br />
                    This action cannot be undone.
                </span>
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

        <!-- CREATE/EDIT SECTOR DIALOG -->
        <Dialog
            v-model:visible="showCreateSectorModal"
            :style="{ width: '500px' }"
            :header="isEdit ? 'Edit Sector Details' : 'Create New Sector'"
            :modal="true"
            class="p-fluid"
        >
            <form @submit.prevent="saveSector" class="mt-2 flex flex-col gap-4">
                <!-- Associated MDA Dropdown -->
                <div class="w-full">
                    <label for="mda" class="mb-2 block font-semibold">
                        Associated MDA
                    </label>
                    <Dropdown
                        id="mda"
                        v-model="mda_id"
                        :options="props.mdas"
                        optionLabel="label"
                        optionValue="value"
                        :class="{ 'p-invalid': mdaIdError }"
                        placeholder="Select an MDA"
                        class="w-full"
                        :filter="true"
                        filterPlaceholder="Search MDA..."
                    />
                    <Message
                        v-if="mdaIdError"
                        severity="error"
                        :closable="false"
                        class="mt-2"
                    >
                        {{ mdaIdError }}
                    </Message>
                </div>

                <!-- Sector Initials -->
                <div class="w-full">
                    <label for="initials" class="mb-2 block font-semibold">
                        Sector Initials (e.g., SECTOR_X)
                    </label>
                    <InputText
                        id="initials"
                        v-model="initials"
                        :class="{ 'p-invalid': initialsError }"
                        maxlength="10"
                        placeholder="e.g., BPD"
                        class="w-full"
                    />
                    <Message
                        v-if="initialsError"
                        severity="error"
                        :closable="false"
                        class="mt-2"
                    >
                        {{ initialsError }}
                    </Message>
                </div>

                <!-- Sector Name -->
                <div class="w-full">
                    <label for="name" class="mb-2 block font-semibold">
                        Sector Name
                    </label>
                    <InputText
                        id="name"
                        v-model="name"
                        :class="{ 'p-invalid': nameError }"
                        placeholder="e.g., Budget Planning & Monitoring"
                        class="w-full"
                    />
                    <Message
                        v-if="nameError"
                        severity="error"
                        :closable="false"
                        class="mt-2"
                    >
                        {{ nameError }}
                    </Message>
                </div>

                <!-- Short Code -->
                <div class="w-full">
                    <label for="code" class="mb-2 block font-semibold">
                        Short Code
                    </label>
                    <InputText
                        id="code"
                        v-model="code"
                        :class="{ 'p-invalid': codeError }"
                        maxlength="10"
                        placeholder="e.g., BPMS"
                        class="w-full"
                    />
                    <Message
                        v-if="codeError"
                        severity="error"
                        :closable="false"
                        class="mt-2"
                    >
                        {{ codeError }}
                    </Message>
                </div>

                <!-- Location -->
                <div class="w-full">
                    <label for="location" class="mb-2 block font-semibold">
                        Location (Optional)
                    </label>
                    <InputText
                        id="location"
                        v-model="location"
                        :class="{ 'p-invalid': locationError }"
                        placeholder="e.g., Wing B, Floor 3"
                        class="w-full"
                    />
                    <Message
                        v-if="locationError"
                        severity="error"
                        :closable="false"
                        class="mt-2"
                    >
                        {{ locationError }}
                    </Message>
                </div>

                <!-- Status -->
                <div class="w-full">
                    <label for="status" class="mb-2 block font-semibold">
                        Status
                    </label>
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
                        class="mt-2"
                    >
                        {{ statusError }}
                    </Message>
                </div>
            </form>

            <template #footer>
                <Button
                    label="Cancel"
                    icon="pi pi-times"
                    @click="showCreateSectorModal = false"
                    text
                />
                <Button
                    :label="isEdit ? 'Update Sector' : 'Save Sector'"
                    icon="pi pi-check"
                    @click="saveSector"
                    :loading="form.processing"
                    :disabled="form.processing"
                    autofocus
                />
            </template>
        </Dialog>
    </AppLayout>
</template>
