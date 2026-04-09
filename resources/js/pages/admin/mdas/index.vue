<script setup>
import { Head, router, useForm } from '@inertiajs/vue3';
import { useField, useForm as useVeeForm } from 'vee-validate';
import { computed, onMounted, ref, watch } from 'vue';
import * as yup from 'yup';

// PrimeVue Imports
import AppLayout from '@/layouts/AppLayout.vue';
import Badge from 'primevue/badge';
import Button from 'primevue/button';
import Card from 'primevue/card';
import Chip from 'primevue/chip';
import Column from 'primevue/column';
import DataTable from 'primevue/datatable';
import Dialog from 'primevue/dialog';
import Dropdown from 'primevue/dropdown';
import InputText from 'primevue/inputtext';
import Message from 'primevue/message';
import Paginator from 'primevue/paginator';
import Tag from 'primevue/tag';
import Textarea from 'primevue/textarea';
import Toast from 'primevue/toast';
import { useToast } from 'primevue/usetoast';

const toast = useToast();

// ---------------------------------------------
// --- PROPS ---
// ---------------------------------------------
const props = defineProps({
    mdas: {
        type: Object,
        required: true,
    },
    administrativeCodes: {
        type: Array,
        default: () => [],
    },
    administrativeSectors: {
        type: Array,
        default: () => [],
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
// --- UNIFIED DATA SOURCE ---
// ---------------------------------------------
const mdaData = computed(() => props.mdas);

// ---------------------------------------------
// --- STATE FOR MODALS ---
// ---------------------------------------------
const showCreateMdaModal = ref(false);
const showEditMdaModal = ref(false);
const showSectorsModal = ref(false);
const showAdminSectorDetailModal = ref(false);
const showAssignSectorsModal = ref(false);
const showConfirmationModal = ref(false);
const isLoadingSectors = ref(false);
const isLoadingAdminSector = ref(false);

const currentMda = ref(null);
const currentAdminSector = ref(null);
const currentAction = ref(null);
const mdaSectors = ref([]);

// For sector assignment
const selectedSectors = ref([]);
const globalFilter = ref('');

// ---------------------------------------------
// --- FORM VALIDATION & STATE ---
// ---------------------------------------------
const statusOptions = ref([
    { label: 'Active', value: 1 },
    { label: 'Inactive', value: 0 },
]);

// 1. Validation Schema
const validationSchema = yup.object({
    name: yup
        .string()
        .required('Full Name is required.')
        .max(255, 'Full Name cannot exceed 255 characters.'),
    // code: yup
    //     .string()
    //     .required('Short Code is required.')
    //     .max(20, 'Short Code cannot exceed 10 characters.'),
    initials: yup
        .string()
        .required('Initials are required.')
        .max(20, 'Initials cannot exceed 10 characters.'),
    location: yup
        .string()
        .nullable()
        .max(255, 'Location cannot exceed 255 characters.'),
    status: yup
        .number()
        .required('Status is required.')
        .oneOf([1, 0], 'Invalid status value.'),
    administrative_code_id: yup.number().nullable(),
    type: yup
        .string()
        .required('Type is required.')
    // .oneOf([1, 2], 'Invalid type value.')
});

const formDefaults = {
    name: '',
    code: '',
    initials: '',
    location: null,
    status: 1,
    administrative_code_id: null,
    type: null
};

// 2. Setup VeeValidate form
const { handleSubmit, resetForm, setErrors, setFieldValue } = useVeeForm({
    validationSchema: validationSchema,
    initialValues: formDefaults,
});

// 3. Define fields using useField
const { value: name, errorMessage: nameError } = useField('name');
const { value: code, errorMessage: codeError } = useField('code');
const { value: initials, errorMessage: initialsError } = useField('initials');
const { value: location, errorMessage: locationError } = useField('location');
const { value: status, errorMessage: statusError } = useField('status');
const { value: type, errorMessage: typeError } = useField('type');
const { value: administrative_code_id, errorMessage: adminCodeError } =
    useField('administrative_code_id');

// 4. Setup Inertia form
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
// --- COMPUTED PROPERTIES ---
// ---------------------------------------------
const administrativeCodesOptions = computed(() => {
    return props.administrativeCodes.map((code) => ({
        label: `${code.code} - ${code.name}`,
        value: code.id,
        code: code.code,
        name: code.name,
        status: code.status,
    }));
});

const sectorsOptions = computed(() => {
    return props.administrativeSectors.map((sector) => ({
        label: `${sector.code} - ${sector.name}`,
        value: sector.id,
        code: sector.code,
        name: sector.name,
        description: sector.description,
        administrative_code_id: sector.administrative_code_id,
        status: sector.status,
    }));
});

const paginatorTotalRecords = computed(() => mdaData.value.total);
const paginatorCurrentPage = computed(() => mdaData.value.current_page);
const paginatorRows = computed(() => mdaData.value.per_page);

// Function to get administrative sector details for an MDA
const getMdaAdministrativeSector = (mda) => {
    if (!mda.administrative_code_id) return null;

    // Find the administrative code
    const adminCode = props.administrativeCodes.find(
        (code) => code.id === mda.administrative_code_id,
    );
    if (!adminCode) return null;

    // Find sectors belonging to this administrative code
    const sectors = props.administrativeSectors.filter(
        (sector) =>
            sector.administrative_code_id === mda.administrative_code_id,
    );

    return {
        code: adminCode,
        sectors: sectors,
        sectorCount: sectors.length,
    };
};

const route = (name, params) => {
    if (name === 'mdas.store' || name === 'mdas.index') {
        return '/mdas';
    }

    if (name.includes('.') && params && (params.id || params.mda)) {
        const id = params.id || params.mda;
        return `/mdas/${id}`;
    }

    return `/${name.replace(/\./g, '/')}`;
};

const breadcrumbs = [{ title: 'MDA Management', href: route('mdas.index') }];

// ---------------------------------------------
// --- HELPER FUNCTIONS ---
// ---------------------------------------------
const getStatusSeverity = (status) => {
    return status === 1 ? 'success' : 'danger';
};

const getStatusText = (status) => {
    return status === 1 ? 'Active' : 'Inactive';
};

const getAdminCodeName = (mda) => {
    if (mda.administrative_code) {
        return `${mda.administrative_code.code} - ${mda.administrative_code.name}`;
    }
    return 'Not Assigned';
};

const onPageChange = (event) => {
    const url = mdaData.value.links[event.page + 1].url;
    if (url) {
        router.get(url, {}, { preserveState: true, replace: true });
    }
};

// Function to view administrative sector details
const viewAdminSectorDetails = (mda) => {
    currentMda.value = mda;
    currentAdminSector.value = getMdaAdministrativeSector(mda);
    showAdminSectorDetailModal.value = true;
};

// Function to get sector preview
const getSectorPreview = (mda) => {
    const adminSectorInfo = getMdaAdministrativeSector(mda);
    if (!adminSectorInfo || adminSectorInfo.sectors.length === 0) {
        return 'No sectors';
    }

    const sectorNames = adminSectorInfo.sectors.slice(0, 2).map((s) => s.name);
    const remaining = adminSectorInfo.sectors.length - 2;

    if (remaining > 0) {
        return sectorNames.join(', ') + ` +${remaining} more`;
    }

    return sectorNames.join(', ');
};

// ---------------------------------------------
// --- ACTION HANDLERS ---
// ---------------------------------------------
const handleCreateMda = () => {
    resetForm();
    form.reset();
    showCreateMdaModal.value = true;
};

const handleEditMda = (mda) => {
    currentMda.value = mda;
    console.log('Editing MDA:', mda);
    resetForm({
        values: {
            name: mda.name,
            code: mda.code,
            initials: mda.initials,
            location: mda.location,
            status: mda.status,
            administrative_code_id: mda.administrative_code_id,
            type: mda.type,
        },
    });

    Object.assign(form, {
        name: mda.name,
        code: mda.code,
        initials: mda.initials,
        location: mda.location,
        status: mda.status,
        administrative_code_id: mda.administrative_code_id,
        type: mda.type,
    });

    showEditMdaModal.value = true;
};

const saveMda = handleSubmit(async (values) => {
    Object.assign(form, values);

    const options = {
        preserveScroll: true,
        onSuccess: () => {
            showCreateMdaModal.value = false;
            showEditMdaModal.value = false;
            form.reset();
            resetForm();
            toast.add({
                severity: 'success',
                summary: 'Operation Successful',
                detail: currentMda.value
                    ? 'MDA updated successfully.'
                    : 'New MDA created successfully.',
                life: 3000,
            });
            currentMda.value = null;
        },
        onError: (errors) => {
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

    if (currentMda.value) {
        form.put(route('mdas.update', { mda: currentMda.value.id }), options);
    } else {
        form.post(route('mdas.store'), options);
    }
});

const openConfirmationModal = (mda, action) => {
    currentMda.value = mda;
    currentAction.value = action;
    showConfirmationModal.value = true;
};

const confirmAction = () => {
    showConfirmationModal.value = false;

    if (!currentMda.value) return;

    if (currentAction.value === 'delete') {
        router.delete(route('mdas.destroy', { mda: currentMda.value.id }), {
            preserveScroll: true,
            onSuccess: () => {
                toast.add({
                    severity: 'success',
                    summary: 'Deleted',
                    detail: `MDA ${currentMda.value.name} removed.`,
                    life: 3000,
                });
            },
            onError: (errors) => {
                toast.add({
                    severity: 'error',
                    summary: 'Error',
                    detail: errors.message || 'Failed to delete MDA.',
                    life: 5000,
                });
            },
        });
    }
};

const openSectorsModal = async (mda) => {
    currentMda.value = mda;
    showSectorsModal.value = true;

    mdaSectors.value = [];
    isLoadingSectors.value = true;

    try {
        const response = await fetch(`/mdas/${mda.id}/sectors`);
        const data = await response.json();

        mdaSectors.value = data.sectors || [];
    } catch (error) {
        console.error('Error fetching sectors:', error);
        toast.add({
            severity: 'error',
            summary: 'Error',
            detail: 'Could not load sectors.',
            life: 3000,
        });
    } finally {
        isLoadingSectors.value = false;
    }
};

const openAssignSectorsModal = async (mda) => {
    currentMda.value = mda;
    selectedSectors.value = [];

    // Load current sectors if any
    try {
        const response = await fetch(`/mdas/${mda.id}/sectors`);
        const data = await response.json();

        selectedSectors.value = data.sectors
            ? data.sectors.map((sector) => sector.id)
            : [];
    } catch (error) {
        console.error('Error loading current sectors:', error);
    }

    showAssignSectorsModal.value = true;
};

const assignSectors = async () => {
    if (!currentMda.value) return;

    try {
        const response = await fetch(
            `/mdas/${currentMda.value.id}/assign-sectors`,
            {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document
                        .querySelector('meta[name="csrf-token"]')
                        .getAttribute('content'),
                },
                body: JSON.stringify({
                    sector_ids: selectedSectors.value,
                }),
            },
        );

        const data = await response.json();

        if (response.ok) {
            toast.add({
                severity: 'success',
                summary: 'Success',
                detail: 'Sectors assigned successfully.',
                life: 3000,
            });
            showAssignSectorsModal.value = false;

            // Refresh the current MDA sectors display
            if (showSectorsModal.value) {
                const sectorsResponse = await fetch(
                    `/mdas/${currentMda.value.id}/sectors`,
                );
                const sectorsData = await sectorsResponse.json();
                mdaSectors.value = sectorsData.sectors || [];
            }
        } else {
            throw new Error(data.message || 'Failed to assign sectors');
        }
    } catch (error) {
        toast.add({
            severity: 'error',
            summary: 'Error',
            detail: error.message,
            life: 5000,
        });
    }
};

const searchableFields = [
    'name',
    'code',
    'initials',
    'location',
    'administrative_code.name'
];

</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">

        <Head title="MDA Management" />

        <Toast />

        <Card>
            <template #title>
                <div class="justify-content-between align-items-center flex flex-wrap">
                    <h2 class="text-xl font-bold">
                        MDAs List ({{ mdaData.total }})
                    </h2>
                    <div class="align-items-center mt-2 flex gap-3 sm:mt-0">
                        <span class="p-input-icon-left">
                            <i class="pi pi-search" />
                            <InputText v-model="globalFilter" placeholder="Search MDAs..." />
                        </span>

                        <Button label="Create New MDA" icon="pi pi-plus" severity="primary" @click="handleCreateMda" />
                    </div>
                </div>
            </template>

            <template #content>
                <!-- <DataTable :value="mdaData.data" dataKey="id" stripedRows responsiveLayout="scroll"
                    class="p-datatable-sm rounded-lg shadow-md" :globalFilterFields="[
                        'name',
                        'code',
                        'initials',
                        'location',
                        'administrative_code.name',
                    ]" v-model:filters="globalFilter" filterDisplay="row" :emptyMessage="'No MDAs found.'"> -->
                <DataTable :value="mdaData.data" dataKey="id" v-model:filters="filters"
                    :globalFilterFields="searchableFields" stripedRows responsiveLayout="scroll">
                    <Column header="Administrative Parent" field="administrative_code.name" :sortable="true">
                        <template #body="slotProps">
                            <div v-if="slotProps.data.administrative_code">
                                <span class="font-bold text-primary">{{ slotProps.data.administrative_code.name
                                }}</span>
                                <small class="block text-500">{{ slotProps.data.administrative_code.code }}</small>
                            </div>
                            <Tag v-else value="Unassigned" severity="secondary" />
                        </template>
                    </Column>
                    <Column field="id" header="ID" headerStyle="width: 5%">
                        <template #body="slotProps">
                            <span class="text-500 font-medium">{{
                                slotProps.data.id
                            }}</span>
                        </template>
                    </Column>

                    <Column field="name" header="Name" headerStyle="width: 20%" :sortable="true">
                        <template #body="slotProps">
                            <span class="font-semibold">{{
                                slotProps.data.name
                            }}</span>
                        </template>
                    </Column>

                    <Column field="code" header="Code" headerStyle="width: 8%" :sortable="true">
                        <template #body="slotProps">
                            <Tag :value="slotProps.data.code" severity="info" class="font-mono text-sm" />
                        </template>
                    </Column>

                    <Column field="initials" header="Initials" headerStyle="width: 8%" :sortable="true" />

                    <Column field="administrative_code" header="Admin Code" headerStyle="width: 15%" :sortable="true">
                        <template #body="slotProps">
                            <div class="flex-column flex">
                                <span class="text-sm font-semibold">{{
                                    getAdminCodeName(slotProps.data)
                                }}</span>
                            </div>
                        </template>
                    </Column>

                    <Column field="administrative_sectors" header="Admin Sectors" headerStyle="width: 20%"
                        :sortable="true">
                        <template #body="slotProps">
                            <div class="flex-column flex gap-1">
                                <div v-if="
                                    getMdaAdministrativeSector(
                                        slotProps.data,
                                    ) &&
                                    getMdaAdministrativeSector(
                                        slotProps.data,
                                    ).sectorCount > 0
                                ">
                                    <div class="align-items-center flex gap-2">
                                        <Badge :value="getMdaAdministrativeSector(
                                            slotProps.data,
                                        ).sectorCount
                                            " severity="info" class="cursor-pointer" @click="
                                                viewAdminSectorDetails(
                                                    slotProps.data,
                                                )
                                                " />
                                        <span
                                            class="text-700 hover:text-primary cursor-pointer text-sm transition-colors"
                                            @click="
                                                viewAdminSectorDetails(
                                                    slotProps.data,
                                                )
                                                ">
                                            View Sectors
                                        </span>
                                    </div>
                                    <div class="mt-1">
                                        <Chip v-for="sector in getMdaAdministrativeSector(
                                            slotProps.data,
                                        ).sectors.slice(0, 2)" :key="sector.id" :label="sector.code"
                                            class="mr-1 mb-1 text-xs" :title="sector.name" severity="warning" />
                                        <span v-if="
                                            getMdaAdministrativeSector(
                                                slotProps.data,
                                            ).sectors.length > 2
                                        " class="text-500 text-xs">
                                            +{{
                                                getMdaAdministrativeSector(
                                                    slotProps.data,
                                                ).sectors.length - 2
                                            }}
                                            more
                                        </span>
                                    </div>
                                </div>
                                <div v-else class="text-500 text-sm">
                                    No sectors
                                </div>
                            </div>
                        </template>
                    </Column>

                    <Column field="location" header="Location" headerStyle="width: 10%" :sortable="true" />

                    <Column field="status" header="Status" headerStyle="width: 8%" :sortable="true">
                        <template #body="slotProps">
                            <Tag :value="getStatusText(slotProps.data.status)" :severity="getStatusSeverity(slotProps.data.status)
                                " />
                        </template>
                    </Column>

                    <Column header="Actions" headerStyle="width: 10%" bodyClass="text-center">
                        <template #body="slotProps">
                            <div class="justify-content-center flex gap-2">
                                <Button icon="pi pi-list" severity="info" text rounded
                                    v-tooltip.top="'View All Sectors'" @click="openSectorsModal(slotProps.data)" />

                                <Button icon="pi pi-pencil" severity="secondary" text rounded v-tooltip.top="'Edit MDA'"
                                    @click="handleEditMda(slotProps.data)" />

                                <Button icon="pi pi-trash" severity="danger" text rounded v-tooltip.top="'Delete MDA'"
                                    @click="
                                        openConfirmationModal(
                                            slotProps.data,
                                            'delete',
                                        )
                                        " />
                            </div>
                        </template>
                    </Column>
                </DataTable>

                <div class="justify-content-end mt-4 flex" v-if="mdaData.total > paginatorRows">
                    <Paginator :rows="paginatorRows" :totalRecords="paginatorTotalRecords"
                        :first="(paginatorCurrentPage - 1) * paginatorRows" @page="onPageChange" :template="{
                            default:
                                'FirstPageLink PrevPageLink PageLinks NextPageLink LastPageLink',
                        }" />
                </div>
            </template>
        </Card>

        <!-- DELETE CONFIRMATION DIALOG -->
        <Dialog v-model:visible="showConfirmationModal" :style="{ width: '450px' }" header="Confirm Deletion"
            :modal="true">
            <div class="align-items-center flex">
                <i class="pi pi-exclamation-triangle mr-3 text-red-500" style="font-size: 2rem"></i>

                <span v-if="currentMda">
                    Are you sure you want to delete MDA:
                    <strong>{{ currentMda.name }}</strong>?
                </span>
            </div>

            <template #footer>
                <Button label="Cancel" icon="pi pi-times" @click="showConfirmationModal = false" text />
                <Button label="Delete" icon="pi pi-trash" severity="danger" @click="confirmAction" autofocus />
            </template>
        </Dialog>

        <!-- VIEW SECTORS DIALOG -->
        <Dialog v-model:visible="showSectorsModal" :style="{ width: '700px' }"
            :header="`Sectors for ${currentMda?.name || 'MDA'}`" :modal="true">
            <div v-if="isLoadingSectors" class="p-4 text-center">
                <i class="pi pi-spin pi-spinner text-primary mb-2 text-4xl"></i>
                <p class="text-600">Loading sectors...</p>
            </div>

            <div v-else-if="mdaSectors.length > 0">
                <DataTable :value="mdaSectors" stripedRows :scrollable="true" scrollHeight="300px">
                    <Column field="id" header="ID" headerStyle="width: 10%" />
                    <Column field="code" header="Code" headerStyle="width: 15%">
                        <template #body="slotProps">
                            <Tag :value="slotProps.data.code" severity="warning" />
                        </template>
                    </Column>
                    <Column field="name" header="Sector Name" headerStyle="width: 35%" />
                    <Column field="description" header="Description" headerStyle="width: 40%" />
                </DataTable>

                <div class="text-500 mt-3 text-sm">
                    Total: {{ mdaSectors.length }} sector(s)
                </div>
            </div>

            <div v-else class="text-500 p-4 text-center">
                <i class="pi pi-info-circle mb-2 block text-2xl"></i>
                No sectors assigned to this MDA.
            </div>

            <template #footer>
                <Button label="Close" icon="pi pi-times" @click="showSectorsModal = false" />
            </template>
        </Dialog>

        <!-- ADMINISTRATIVE SECTOR DETAILS DIALOG -->
        <Dialog v-model:visible="showAdminSectorDetailModal" :style="{ width: '800px' }"
            :header="`Administrative Sectors for ${currentMda?.name || 'MDA'}`" :modal="true">
            <div v-if="currentAdminSector">
                <div class="mb-6 rounded-lg bg-blue-50 p-4">
                    <div class="justify-content-between align-items-center mb-2 flex">
                        <h3 class="text-lg font-bold text-blue-700">
                            Administrative Code
                        </h3>
                        <Tag :value="currentAdminSector.code.code" severity="info" />
                    </div>
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-semibold text-blue-600">Name</label>
                            <p class="text-700">
                                {{ currentAdminSector.code.name }}
                            </p>
                        </div>
                        <div>
                            <label class="block text-sm font-semibold text-blue-600">Code</label>
                            <p class="text-700 font-mono">
                                {{ currentAdminSector.code.code }}
                            </p>
                        </div>
                        <div>
                            <label class="block text-sm font-semibold text-blue-600">Status</label>
                            <Tag :value="currentAdminSector.code.status
                                ? 'Active'
                                : 'Inactive'
                                " :severity="currentAdminSector.code.status
                                    ? 'success'
                                    : 'danger'
                                    " />
                        </div>
                    </div>
                </div>

                <div class="mb-4">
                    <div class="justify-content-between align-items-center mb-3 flex">
                        <h3 class="text-lg font-bold">Associated Sectors</h3>
                        <Badge :value="currentAdminSector.sectorCount" severity="info" />
                    </div>

                    <DataTable :value="currentAdminSector.sectors" stripedRows :scrollable="true" scrollHeight="300px"
                        class="p-datatable-sm">
                        <Column field="id" header="ID" headerStyle="width: 8%" />
                        <Column field="code" header="Code" headerStyle="width: 15%">
                            <template #body="slotProps">
                                <Tag :value="slotProps.data.code" severity="warning" class="font-mono" />
                            </template>
                        </Column>
                        <Column field="name" header="Sector Name" headerStyle="width: 30%" />
                        <Column field="description" header="Description" headerStyle="width: 35%" />
                        <Column field="status" header="Status" headerStyle="width: 12%">
                            <template #body="slotProps">
                                <Tag :value="slotProps.data.status
                                    ? 'Active'
                                    : 'Inactive'
                                    " :severity="slotProps.data.status
                                        ? 'success'
                                        : 'danger'
                                        " class="text-xs" />
                            </template>
                        </Column>
                    </DataTable>

                    <div class="mt-4 rounded bg-gray-50 p-3">
                        <div class="justify-content-between align-items-center flex">
                            <div>
                                <span class="font-semibold">Total Sectors:</span>
                                <span class="ml-2">{{
                                    currentAdminSector.sectorCount
                                }}</span>
                            </div>
                            <div>
                                <span class="font-semibold">Active:</span>
                                <span class="ml-2">{{
                                    currentAdminSector.sectors.filter(
                                        (s) => s.status,
                                    ).length
                                }}</span>
                            </div>
                            <div>
                                <span class="font-semibold">Inactive:</span>
                                <span class="ml-2">{{
                                    currentAdminSector.sectors.filter(
                                        (s) => !s.status,
                                    ).length
                                }}</span>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="justify-content-end mt-6 flex gap-3">
                    <Button label="Close" icon="pi pi-times" @click="showAdminSectorDetailModal = false" />
                </div>
            </div>

            <div v-else class="p-6 text-center">
                <i class="pi pi-exclamation-circle text-500 mb-3 text-4xl"></i>
                <h3 class="mb-2 text-lg font-semibold">
                    No Administrative Code Assigned
                </h3>
                <p class="text-500 mb-4">
                    This MDA is not linked to any administrative code.
                </p>
                <Button label="Edit MDA to Assign Code" icon="pi pi-pencil" severity="warning" @click="
                    () => {
                        showAdminSectorDetailModal = false;
                        handleEditMda(currentMda);
                    }
                " />
            </div>
        </Dialog>

        <!-- CREATE MDA DIALOG -->
        <Dialog v-model:visible="showCreateMdaModal" :style="{ width: '550px' }" header="Create New MDA" :modal="true"
            class="p-fluid">
            <form @submit.prevent="saveMda" class="mt-2 flex flex-col gap-4">
                <div class="w-full">
                    <label for="name" class="mb-2 block font-semibold">Full Name *</label>
                    <InputText id="name" v-model="name" :class="{ 'p-invalid': nameError }"
                        placeholder="e.g., Ministry of Finance" class="w-full" />
                    <Message v-if="nameError" severity="error" :closable="false" class="mt-2">{{ nameError }}</Message>
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label for="code" class="mb-2 block font-semibold">Short Code *</label>
                        <InputText id="code" v-model="code" :class="{ 'p-invalid': codeError }" maxlength="20"
                            placeholder="e.g., 1001" class="w-full" />
                        <Message v-if="codeError" severity="error" :closable="false" class="mt-2">{{ codeError }}
                        </Message>
                    </div>

                    <div>
                        <label for="initials" class="mb-2 block font-semibold">Initials *</label>
                        <InputText id="initials" v-model="initials" :class="{ 'p-invalid': initialsError }"
                            maxlength="20" placeholder="e.g., MOF" class="w-full" />
                        <Message v-if="initialsError" severity="error" :closable="false" class="mt-2">{{ initialsError
                        }}
                        </Message>
                    </div>
                </div>

                <div class="w-full">
                    <label for="administrative_code_id" class="mb-2 block font-semibold">Administrative Code</label>
                    <Dropdown id="administrative_code_id" v-model="administrative_code_id"
                        :options="administrativeCodesOptions" optionLabel="label" optionValue="value"
                        :class="{ 'p-invalid': adminCodeError }" placeholder="Select Administrative Code" class="w-full"
                        :filter="true" showClear />
                    <Message v-if="adminCodeError" severity="error" :closable="false" class="mt-2">{{ adminCodeError }}
                    </Message>
                    <small class="text-500">Link this MDA to an administrative code for sector
                        assignment</small>
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label for="status" class="mb-2 block font-semibold">Status *</label>
                        <Dropdown id="status" v-model="status" :options="statusOptions" optionLabel="label"
                            optionValue="value" :class="{ 'p-invalid': statusError }" placeholder="Select Status"
                            class="w-full" />
                        <Message v-if="statusError" severity="error" :closable="false" class="mt-2">{{ statusError }}
                        </Message>
                    </div>
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label for="status" class="mb-2 block font-semibold">Type</label>
                        <Dropdown id="type" v-model="type"
                            :options="[{ 'label': 'Board', 'value': 'Board' }, { 'label': 'Commission', 'value': 'Commission' }, { label: 'Ministry', value: 'Ministry' }, { label: 'Agency', value: 'Agency' }]"
                            optionLabel="label" optionValue="value" :class="{ 'p-invalid': typeError }"
                            placeholder="Select Status" class="w-full" />
                        <Message v-if="typeError" severity="error" :closable="false" class="mt-2">{{ typeError }}
                        </Message>
                    </div>
                </div>

                <div class="w-full">
                    <label for="location" class="mb-2 block font-semibold">Location/Address (Optional)</label>
                    <Textarea id="location" v-model="location" :class="{ 'p-invalid': locationError }" rows="3"
                        placeholder="e.g., Block A, State Secretariat" class="w-full" />
                    <Message v-if="locationError" severity="error" :closable="false" class="mt-2">{{ locationError }}
                    </Message>
                </div>
            </form>

            <template #footer>
                <Button label="Cancel" icon="pi pi-times" @click="showCreateMdaModal = false" text />
                <Button label="Create MDA" icon="pi pi-check" @click="saveMda" :loading="form.processing"
                    :disabled="form.processing" severity="primary" />
            </template>
        </Dialog>

        <!-- EDIT MDA DIALOG -->
        <Dialog v-model:visible="showEditMdaModal" :style="{ width: '550px' }"
            :header="`Edit MDA: ${currentMda?.name || ''}`" :modal="true" class="p-fluid">
            <form @submit.prevent="saveMda" class="mt-2 flex flex-col gap-4">
                <div class="w-full">
                    <label for="edit_name" class="mb-2 block font-semibold">Full Name *</label>
                    <InputText id="edit_name" v-model="name" :class="{ 'p-invalid': nameError }" class="w-full" />
                    <Message v-if="nameError" severity="error" :closable="false" class="mt-2">{{ nameError }}</Message>
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label for="edit_code" class="mb-2 block font-semibold">Short Code *</label>
                        <InputText id="edit_code" v-model="code" :class="{ 'p-invalid': codeError }" maxlength="20"
                            class="w-full" />
                        <Message v-if="codeError" severity="error" :closable="false" class="mt-2">{{ codeError }}
                        </Message>
                    </div>

                    <div>
                        <label for="edit_initials" class="mb-2 block font-semibold">Initials *</label>
                        <InputText id="edit_initials" v-model="initials" :class="{ 'p-invalid': initialsError }"
                            maxlength="10" class="w-full" />
                        <Message v-if="initialsError" severity="error" :closable="false" class="mt-2">{{ initialsError
                        }}
                        </Message>
                    </div>
                </div>

                <div class="w-full">
                    <label for="edit_administrative_code_id" class="mb-2 block font-semibold">Administrative
                        Code</label>
                    <Dropdown id="edit_administrative_code_id" v-model="administrative_code_id"
                        :options="administrativeCodesOptions" optionLabel="label" optionValue="value"
                        :class="{ 'p-invalid': adminCodeError }" placeholder="Select Administrative Code" class="w-full"
                        :filter="true" showClear />
                    <Message v-if="adminCodeError" severity="error" :closable="false" class="mt-2">{{ adminCodeError }}
                    </Message>
                    <div class="text-500 mt-1 text-sm">
                        <span v-if="currentMda?.administrative_code">
                            Current: {{ currentMda.administrative_code.code }} -
                            {{ currentMda.administrative_code.name }}
                        </span>
                        <span v-else>No administrative code assigned</span>
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label for="edit_status" class="mb-2 block font-semibold">Status *</label>
                        <Dropdown id="edit_status" v-model="status" :options="statusOptions" optionLabel="label"
                            optionValue="value" :class="{ 'p-invalid': statusError }" class="w-full" />
                        <Message v-if="statusError" severity="error" :closable="false" class="mt-2">{{ statusError }}
                        </Message>
                    </div>
                </div>


                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label for="edit_status" class="mb-2 block font-semibold">Type</label>
                        <Dropdown id="edit_type" v-model="type"
                            :options="[{ 'label': 'Board', 'value': 'Board' }, { 'label': 'Commission', 'value': 'Commission' }, { label: 'Ministry', value: 'Ministry' }, { label: 'Agency', value: 'Agency' }]"
                            optionLabel="label" optionValue="value" :class="{ 'p-invalid': typeError }"
                            placeholder="Select Status" class="w-full" />
                        <Message v-if="typeError" severity="error" :closable="false" class="mt-2">{{ typeError }}
                        </Message>
                    </div>
                </div>

                <div class="w-full">
                    <label for="edit_location" class="mb-2 block font-semibold">Location/Address</label>
                    <Textarea id="edit_location" v-model="location" :class="{ 'p-invalid': locationError }" rows="3"
                        class="w-full" />
                    <Message v-if="locationError" severity="error" :closable="false" class="mt-2">{{ locationError }}
                    </Message>
                </div>
            </form>

            <template #footer>
                <Button label="Cancel" icon="pi pi-times" @click="showEditMdaModal = false" text />
                <Button label="Update MDA" icon="pi pi-check" @click="saveMda" :loading="form.processing"
                    :disabled="form.processing" severity="primary" />
            </template>
        </Dialog>
    </AppLayout>
</template>
