<script setup>
import { Head, router, useForm } from '@inertiajs/vue3';
import { computed, onMounted, ref, watch } from 'vue';

// --- Frontend Validation Imports ---
import { useField, useForm as useVeeForm } from 'vee-validate';
import * as yup from 'yup';

// --- PrimeVue Imports ---
import AppLayout from '@/layouts/AppLayout.vue';
import Button from 'primevue/button';
import Card from 'primevue/card';
import Column from 'primevue/column';
import DataTable from 'primevue/datatable';
import Dialog from 'primevue/dialog';
import InputText from 'primevue/inputtext';
import Message from 'primevue/message';
import Paginator from 'primevue/paginator';
import Tag from 'primevue/tag';
import Toast from 'primevue/toast';
import { useToast } from 'primevue/usetoast';

const toast = useToast();

// ---------------------------------------------
// --- PROPS: Using REAL Data from Controller ---
// ---------------------------------------------
const props = defineProps({
    permissions: {
        type: Object,
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
// --- UNIFIED DATA SOURCE ---
// ---------------------------------------------
const permissionData = computed(() => props.permissions);

// ---------------------------------------------
// --- PERMISSION CREATION/EDIT FORM STATE ---
// ---------------------------------------------
const showCreatePermissionModal = ref(false);
const isEdit = ref(false);
const currentPermissionId = ref(null);

// 1. Define the validation schema using Yup
const validationSchema = yup.object({
    name: yup
        .string()
        .required('Permission name is required.')
        .max(255, 'Permission name cannot exceed 255 characters.'),
});

const formDefaults = {
    name: '',
};

// 2. Setup VeeValidate form
const { handleSubmit, resetForm, setErrors } = useVeeForm({
    validationSchema: validationSchema,
    initialValues: formDefaults,
});

// 3. Define fields using useField
const { value: name, errorMessage: nameError } = useField('name');

// 4. Setup Inertia form using VeeValidate field values
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
// --- ACTION HANDLERS ---
// ---------------------------------------------

// Handler for Create Button (Opens Modal)
const handleCreatePermission = () => {
    isEdit.value = false;
    currentPermissionId.value = null;
    resetForm();
    form.reset();
    showCreatePermissionModal.value = true;
};

// Handler for Edit Action (Opens Modal and loads data)
const handleEditPermission = (permission) => {
    isEdit.value = true;
    currentPermissionId.value = permission.id;

    // Set VeeValidate values
    resetForm({
        values: {
            name: permission.name,
        },
    });

    // Set Inertia form values
    form.name = permission.name;

    showCreatePermissionModal.value = true;
};

// Handler for saving the Permission
const savePermission = handleSubmit(async (values) => {
    Object.assign(form, values);

    const options = {
        preserveScroll: true,
        onSuccess: () => {
            showCreatePermissionModal.value = false;
            form.reset();
            resetForm();
            toast.add({
                severity: 'success',
                summary: 'Operation Successful',
                detail: isEdit.value
                    ? 'Permission updated successfully.'
                    : 'New permission created successfully.',
                life: 3000,
            });
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

    if (isEdit.value && currentPermissionId.value) {
        form.put(
            route('permissions.update', {
                permission: currentPermissionId.value,
            }),
            options,
        );
    } else {
        form.post(route('permissions.store'), options);
    }
});

// --- STATE FOR MODALS AND ACTIONS ---
const globalFilter = ref('');
const showConfirmationModal = ref(false);
const currentPermission = ref(null);
const currentAction = ref(null);

// --- COMPUTED PROPERTIES for Paginator ---
const paginatorTotalRecords = computed(() => permissionData.value.total);
const paginatorCurrentPage = computed(() => permissionData.value.current_page);
const paginatorRows = computed(() => permissionData.value.per_page);

const route = (name, params) => {
    if (name === 'permissions.store' || name === 'permissions.index') {
        return '/permissions';
    }

    if (name.includes('.') && params && (params.id || params.permission)) {
        const id = params.id || params.permission;
        return `/permissions/${id}`;
    }

    return `/${name.replace(/\./g, '/')}`;
};

const breadcrumbs = [
    { title: 'Permission Management', href: route('permissions.index') },
];

// --- HELPER FUNCTIONS ---
const formatPermissionName = (name) => {
    return name
        .split('.')
        .map((word) => word.charAt(0).toUpperCase() + word.slice(1))
        .join(' ');
};

const onPageChange = (event) => {
    const url = permissionData.value.links[event.page + 1].url;

    if (url) {
        router.get(url, {}, { preserveState: true, replace: true });
    } else {
        console.warn('Attempted to navigate to a page without a valid URL.');
    }
};

const openConfirmationModal = (permission, action) => {
    currentPermission.value = permission;
    currentAction.value = action;
    showConfirmationModal.value = true;
};

const confirmAction = () => {
    showConfirmationModal.value = false;

    if (!currentPermission.value) return;

    if (currentAction.value === 'delete') {
        router.delete(
            route('permissions.destroy', {
                permission: currentPermission.value.id,
            }),
            {
                preserveScroll: true,
                onSuccess: () => {
                    toast.add({
                        severity: 'success',
                        summary: 'Deleted',
                        detail: `Permission '${currentPermission.value.name}' removed.`,
                        life: 3000,
                    });
                },
                onError: (errors) => {
                    toast.add({
                        severity: 'error',
                        summary: 'Error',
                        detail:
                            errors.message || 'Failed to delete permission.',
                        life: 5000,
                    });
                },
            },
        );
    }
};
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head title="Permission Management" />

        <!-- Global Toast Component -->
        <Toast />

        <Card>
            <template #title>
                <div
                    class="justify-content-between align-items-center flex flex-wrap"
                >
                    <h2 class="text-xl font-bold">
                        Permissions List ({{ permissionData.total }})
                    </h2>
                    <div class="align-items-center mt-2 flex gap-3 sm:mt-0">
                        <!-- Search Input -->
                        <span class="p-input-icon-left">
                            <i class="pi pi-search" />
                            <InputText
                                v-model="globalFilter"
                                placeholder="Search permissions..."
                            />
                        </span>

                        <!-- Create Button -->
                        <Button
                            label="Create New Permission"
                            icon="pi pi-plus"
                            severity="primary"
                            @click="handleCreatePermission"
                        />
                    </div>
                </div>
            </template>

            <template #content>
                <DataTable
                    :value="permissionData.data"
                    dataKey="id"
                    stripedRows
                    responsiveLayout="scroll"
                    class="p-datatable-sm rounded-lg shadow-md"
                    :globalFilterFields="['name']"
                    v-model:filters="globalFilter"
                    filterDisplay="row"
                    :emptyMessage="'No permissions found. Try creating a new one or adjusting your search.'"
                >
                    <Column field="id" header="ID" headerStyle="width: 10%">
                        <template #body="slotProps">
                            <span class="text-500 font-medium">{{
                                slotProps.data.id
                            }}</span>
                        </template>
                    </Column>

                    <Column
                        field="name"
                        header="Permission Name"
                        headerStyle="width: 50%"
                        :sortable="true"
                    >
                        <template #body="slotProps">
                            <div>
                                <span class="font-semibold">{{
                                    formatPermissionName(slotProps.data.name)
                                }}</span>
                                <div class="text-500 mt-1 text-sm">
                                    {{ slotProps.data.name }}
                                </div>
                            </div>
                        </template>
                    </Column>

                    <Column
                        field="guard_name"
                        header="Guard"
                        headerStyle="width: 15%"
                        :sortable="true"
                    >
                        <template #body="slotProps">
                            <Tag
                                :value="slotProps.data.guard_name"
                                severity="info"
                                class="text-xs"
                            />
                        </template>
                    </Column>

                    <Column
                        field="created_at_formatted"
                        header="Created"
                        headerStyle="width: 15%"
                        :sortable="true"
                    />

                    <Column
                        header="Actions"
                        headerStyle="width: 20%"
                        bodyClass="text-center"
                    >
                        <template #body="slotProps">
                            <div class="justify-content-center flex gap-2">
                                <Button
                                    icon="pi pi-pencil"
                                    severity="secondary"
                                    text
                                    rounded
                                    v-tooltip.top="'Edit Permission'"
                                    @click="
                                        handleEditPermission(slotProps.data)
                                    "
                                />

                                <Button
                                    icon="pi pi-trash"
                                    severity="danger"
                                    text
                                    rounded
                                    v-tooltip.top="'Delete Permission'"
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

                <!-- Paginator -->
                <div
                    class="justify-content-end mt-4 flex"
                    v-if="permissionData.total > paginatorRows"
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
        >
            <div class="align-items-center flex">
                <i
                    class="pi pi-exclamation-triangle mr-3 text-red-500"
                    style="font-size: 2rem"
                ></i>

                <span v-if="currentPermission">
                    Are you sure you want to
                    <strong>permanently delete</strong> the permission:
                    <strong
                        >'{{
                            formatPermissionName(currentPermission.name)
                        }}'</strong
                    >? This action cannot be undone and will remove this
                    permission from all roles and users.
                </span>
            </div>

            <template #footer>
                <Button
                    label="No / Cancel"
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

        <!-- 2. CREATE/EDIT PERMISSION DIALOG -->
        <Dialog
            v-model:visible="showCreatePermissionModal"
            :style="{ width: '500px' }"
            :header="isEdit ? 'Edit Permission' : 'Create New Permission'"
            :modal="true"
            class="p-fluid"
        >
            <form
                @submit.prevent="savePermission"
                class="mt-2 flex flex-col gap-4"
            >
                <!-- Permission Name -->
                <div class="w-full">
                    <label for="name" class="mb-2 block font-semibold"
                        >Permission Name</label
                    >
                    <InputText
                        id="name"
                        v-model="name"
                        :class="{ 'p-invalid': nameError }"
                        placeholder="e.g., users.create, posts.delete, admin.access"
                        class="w-full"
                    />
                    <small class="text-500 mt-1 block">
                        Use dot notation for better organization (e.g.,
                        users.create, posts.delete)
                    </small>
                    <Message
                        v-if="nameError"
                        severity="error"
                        :closable="false"
                        class="mt-2"
                        >{{ nameError }}</Message
                    >
                </div>
            </form>

            <template #footer>
                <Button
                    label="Cancel"
                    icon="pi pi-times"
                    @click="showCreatePermissionModal = false"
                    text
                />
                <Button
                    :label="isEdit ? 'Update Permission' : 'Create Permission'"
                    icon="pi pi-check"
                    @click="savePermission"
                    :loading="form.processing"
                    :disabled="form.processing"
                    autofocus
                />
            </template>
        </Dialog>
    </AppLayout>
</template>

<style scoped>
:deep(.p-datatable) {
    .p-column-header-content {
        display: flex;
        align-items: center;
    }
}
</style>
