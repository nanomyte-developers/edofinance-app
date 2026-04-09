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
import Checkbox from 'primevue/checkbox';
import Column from 'primevue/column';
import DataTable from 'primevue/datatable';
import Dialog from 'primevue/dialog';
import InputText from 'primevue/inputtext';
import Message from 'primevue/message';
import Paginator from 'primevue/paginator';
import Tag from 'primevue/tag';
import Textarea from 'primevue/textarea';
import Toast from 'primevue/toast';
import { useToast } from 'primevue/usetoast';

const toast = useToast();

// ---------------------------------------------
// --- PROPS: Using REAL Data from Controller ---
// ---------------------------------------------
const props = defineProps({
    roles: {
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
    all_permissions: {
        type: Array,
        default: () => [],
    },
    flash: {
        type: Object,
        default: () => ({ message: null }),
    },
    filters: {
        type: Object,
        default: () => ({
            search: '',
            sort: 'name',
            order: 'asc',
        }),
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
const roleData = computed(() => props.roles);

// ---------------------------------------------
// --- ROLE CREATION/EDIT FORM STATE ---
// ---------------------------------------------
const showCreateRoleModal = ref(false);
const isEdit = ref(false);
const currentRoleId = ref(null);
const loading = ref(false);

// 1. Define the validation schema using Yup
const validationSchema = yup.object({
    name: yup
        .string()
        .required('Role name is required.')
        .max(255, 'Role name cannot exceed 255 characters.'),
    description: yup
        .string()
        .nullable()
        .max(500, 'Description cannot exceed 500 characters.'),
    permissions: yup.array().of(yup.string()),
});

const formDefaults = {
    name: '',
    description: '',
    permissions: [],
};

// 2. Setup VeeValidate form
const { handleSubmit, resetForm, setErrors } = useVeeForm({
    validationSchema: validationSchema,
    initialValues: formDefaults,
});

// 3. Define fields using useField
const { value: name, errorMessage: nameError } = useField('name');
const { value: description, errorMessage: descriptionError } =
    useField('description');
const { value: permissions, errorMessage: permissionsError } =
    useField('permissions');

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
const handleCreateRole = () => {
    isEdit.value = false;
    currentRoleId.value = null;
    resetForm();
    form.reset();
    showCreateRoleModal.value = true;
};

// Handler for Edit Action (Opens Modal and loads data)
const handleEditRole = (role) => {
    isEdit.value = true;
    currentRoleId.value = role.id;

    // Ensure permissions is always an array
    const rolePermissions = role.permissions
        ? role.permissions.map((p) => p.name)
        : [];

    // Set VeeValidate values
    resetForm({
        values: {
            name: role.name,
            description: role.description || '',
            permissions: rolePermissions,
        },
    });

    // Set Inertia form values
    form.name = role.name;
    form.description = role.description || '';
    form.permissions = rolePermissions;

    showCreateRoleModal.value = true;
};

// Handler for saving the Role - UPDATED
const saveRole = handleSubmit(async (values) => {
    loading.value = true;

    // Ensure permissions is always an array and filter out any empty values
    const formData = {
        ...values,
        permissions: Array.isArray(values.permissions)
            ? values.permissions.filter((p) => p && typeof p === 'string')
            : [],
    };

    Object.assign(form, formData);

    const options = {
        preserveScroll: true,
        onSuccess: () => {
            showCreateRoleModal.value = false;
            form.reset();
            resetForm();
            loading.value = false;
            toast.add({
                severity: 'success',
                summary: 'Operation Successful',
                detail: isEdit.value
                    ? 'Role updated successfully.'
                    : 'New role created successfully.',
                life: 3000,
            });
        },
        onError: (errors) => {
            loading.value = false;
            toast.add({
                severity: 'error',
                summary: 'Validation Failed',
                detail: 'Please fix the errors shown in the form fields.',
                life: 5000,
            });
        },
        onFinish: () => {
            loading.value = false;
            form.processing = false;
        },
    };

    if (isEdit.value && currentRoleId.value) {
        form.put(route('roles.update', { role: currentRoleId.value }), options);
    } else {
        form.post(route('roles.store'), options);
    }
});

// Helper function to format permission names
const formatPermissionName = (name) => {
    return name
        .split('.')
        .map((word) => word.charAt(0).toUpperCase() + word.slice(1))
        .join(' ');
};

// Group permissions by module/prefix for better organization
const groupedPermissions = computed(() => {
    const groups = {};

    props.all_permissions.forEach((permission) => {
        const parts = permission.name.split('.');
        const module = parts[0] || 'general';

        if (!groups[module]) {
            groups[module] = {
                name: module.charAt(0).toUpperCase() + module.slice(1),
                permissions: [],
            };
        }

        groups[module].permissions.push(permission);
    });

    return groups;
});

// Select all permissions in a group
const selectAllInGroup = (groupName) => {
    const groupPermissions =
        groupedPermissions.value[groupName]?.permissions || [];
    const permissionNames = groupPermissions.map((p) => p.name);

    // Add all permissions from this group that aren't already selected
    const newPermissions = [
        ...new Set([...permissions.value, ...permissionNames]),
    ];
    permissions.value = newPermissions;
};

// Deselect all permissions in a group
const deselectAllInGroup = (groupName) => {
    const groupPermissions =
        groupedPermissions.value[groupName]?.permissions || [];
    const permissionNames = groupPermissions.map((p) => p.name);

    // Remove all permissions from this group
    const newPermissions = permissions.value.filter(
        (p) => !permissionNames.includes(p),
    );
    permissions.value = newPermissions;
};

// Check if all permissions in a group are selected
const isGroupFullySelected = (groupName) => {
    const groupPermissions =
        groupedPermissions.value[groupName]?.permissions || [];
    const permissionNames = groupPermissions.map((p) => p.name);
    return permissionNames.every((p) => permissions.value.includes(p));
};

// Check if any permission in a group is selected
const isGroupPartiallySelected = (groupName) => {
    const groupPermissions =
        groupedPermissions.value[groupName]?.permissions || [];
    const permissionNames = groupPermissions.map((p) => p.name);
    return (
        permissionNames.some((p) => permissions.value.includes(p)) &&
        !isGroupFullySelected(groupName)
    );
};

// --- STATE FOR MODALS AND ACTIONS ---
const globalFilter = ref(props.filters.search || '');
const showConfirmationModal = ref(false);
const currentRole = ref(null);
const currentAction = ref(null);

// --- COMPUTED PROPERTIES for Paginator ---
const paginatorTotalRecords = computed(() => roleData.value.total);
const paginatorCurrentPage = computed(() => roleData.value.current_page);
const paginatorRows = computed(() => roleData.value.per_page);

// --- FILTERS ---
const filters = ref({
    search: props.filters.search || '',
    page: props.roles.current_page || 1,
    sort: props.filters.sort || 'name',
    order: props.filters.order || 'asc',
});

const route = (name, params) => {
    if (name === 'roles.store' || name === 'roles.index') {
        return '/roles';
    }

    if (name.includes('.') && params && (params.id || params.role)) {
        const id = params.id || params.role;
        return `/roles/${id}`;
    }

    return `/${name.replace(/\./g, '/')}`;
};

const breadcrumbs = [{ title: 'Role Management', href: route('roles.index') }];

// --- HELPER FUNCTIONS ---
const onPageChange = (event) => {
    filters.value.page = event.page + 1;
    loadRoles();
};

const loadRoles = () => {
    loading.value = true;
    router.get(route('roles.index'), filters.value, {
        preserveState: true,
        preserveScroll: true,
        onFinish: () => {
            loading.value = false;
        },
    });
};

const onFilter = () => {
    filters.value.page = 1;
    loadRoles();
};

const clearFilters = () => {
    filters.value = {
        search: '',
        page: 1,
        sort: 'name',
        order: 'asc',
    };
    globalFilter.value = '';
    loadRoles();
};

const openConfirmationModal = (role, action) => {
    currentRole.value = role;
    currentAction.value = action;
    showConfirmationModal.value = true;
};

const confirmAction = () => {
    showConfirmationModal.value = false;

    if (!currentRole.value) return;

    if (currentAction.value === 'delete') {
        loading.value = true;
        router.delete(route('roles.destroy', { role: currentRole.value.id }), {
            preserveScroll: true,
            onSuccess: () => {
                loading.value = false;
                toast.add({
                    severity: 'success',
                    summary: 'Deleted',
                    detail: `Role '${currentRole.value.name}' removed.`,
                    life: 3000,
                });
            },
            onError: (errors) => {
                loading.value = false;
                toast.add({
                    severity: 'error',
                    summary: 'Error',
                    detail: errors.message || 'Failed to delete role.',
                    life: 5000,
                });
            },
        });
    }
};

// Check if role can be deleted (protect admin role)
const canDeleteRole = (role) => {
    return role.name !== 'admin' && role.name !== 'super-admin';
};

// Get role badge severity
const getRoleSeverity = (roleName) => {
    if (roleName === 'admin' || roleName === 'super-admin') return 'danger';
    if (roleName === 'manager' || roleName === 'moderator') return 'warning';
    return 'info';
};
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head title="Role Management" />

        <!-- Global Toast Component -->
        <Toast />

        <Card>
            <template #title>
                <div
                    class="justify-content-between align-items-center flex flex-wrap"
                >
                    <h2 class="text-xl font-bold">
                        Roles Management ({{ roleData.total }})
                    </h2>
                    <div class="align-items-center mt-2 flex gap-3 sm:mt-0">
                        <!-- Search Input -->
                        <span class="p-input-icon-left">
                            <i class="pi pi-search" />
                            <InputText
                                v-model="globalFilter"
                                placeholder="Search roles..."
                                @input="onFilter"
                                class="w-20rem"
                            />
                        </span>

                        <!-- Clear Filters Button -->
                        <Button
                            icon="pi pi-filter-slash"
                            label="Clear"
                            severity="secondary"
                            @click="clearFilters"
                            outlined
                        />

                        <!-- Create Button -->
                        <Button
                            label="Create Role"
                            icon="pi pi-plus"
                            severity="primary"
                            @click="handleCreateRole"
                        />
                    </div>
                </div>
            </template>

            <template #content>
                <DataTable
                    :value="roleData.data"
                    dataKey="id"
                    stripedRows
                    responsiveLayout="scroll"
                    class="p-datatable-sm"
                    :loading="loading"
                    :globalFilterFields="['name', 'description']"
                    :emptyMessage="'No roles found. Try creating a new one or adjusting your search.'"
                    :paginator="false"
                >
                    <Column
                        field="id"
                        header="ID"
                        headerStyle="width: 8%"
                        :sortable="true"
                    >
                        <template #body="slotProps">
                            <span class="text-color-secondary font-mono"
                                >#{{ slotProps.data.id }}</span
                            >
                        </template>
                    </Column>

                    <Column
                        field="name"
                        header="Role Name"
                        headerStyle="width: 20%"
                        :sortable="true"
                    >
                        <template #body="slotProps">
                            <div class="align-items-center flex gap-2">
                                <Tag
                                    :value="slotProps.data.name"
                                    :severity="
                                        getRoleSeverity(slotProps.data.name)
                                    "
                                    class="font-semibold"
                                />
                                <div
                                    v-if="slotProps.data.description"
                                    class="text-500 hidden text-sm md:block"
                                >
                                    {{ slotProps.data.description }}
                                </div>
                            </div>
                        </template>
                    </Column>

                    <Column
                        field="permissions_count"
                        header="Permissions"
                        headerStyle="width: 15%"
                        :sortable="true"
                    >
                        <template #body="slotProps">
                            <div class="align-items-center flex gap-2">
                                <Tag
                                    v-if="slotProps.data.permissions_count > 0"
                                    :value="slotProps.data.permissions_count"
                                    severity="info"
                                    class="text-xs font-semibold"
                                />
                                <span v-else class="text-500 text-sm"
                                    >No permissions</span
                                >

                                <!-- View Permissions Button -->
                                <Button
                                    v-if="slotProps.data.permissions_count > 0"
                                    icon="pi pi-eye"
                                    text
                                    rounded
                                    severity="help"
                                    size="small"
                                    v-tooltip.top="'View Permissions'"
                                    @click="handleEditRole(slotProps.data)"
                                />
                            </div>
                        </template>
                    </Column>

                    <Column
                        field="description"
                        header="Description"
                        headerStyle="width: 30%"
                    >
                        <template #body="slotProps">
                            <span class="text-900">{{
                                slotProps.data.description || 'No description'
                            }}</span>
                        </template>
                    </Column>

                    <Column
                        field="guard_name"
                        header="Guard"
                        headerStyle="width: 12%"
                        :sortable="true"
                    >
                        <template #body="slotProps">
                            <Tag
                                :value="slotProps.data.guard_name"
                                severity="warning"
                                class="text-xs"
                            />
                        </template>
                    </Column>

                    <Column
                        field="created_at_formatted"
                        header="Created"
                        headerStyle="width: 10%"
                        :sortable="true"
                    />

                    <Column
                        header="Actions"
                        headerStyle="width: 15%"
                        bodyClass="text-center"
                    >
                        <template #body="slotProps">
                            <div class="justify-content-center flex gap-1">
                                <Button
                                    icon="pi pi-pencil"
                                    severity="secondary"
                                    text
                                    rounded
                                    size="small"
                                    v-tooltip.top="'Edit Role'"
                                    @click="handleEditRole(slotProps.data)"
                                />

                                <Button
                                    icon="pi pi-trash"
                                    severity="danger"
                                    text
                                    rounded
                                    size="small"
                                    v-tooltip.top="
                                        canDeleteRole(slotProps.data)
                                            ? 'Delete Role'
                                            : 'Cannot delete system role'
                                    "
                                    @click="
                                        openConfirmationModal(
                                            slotProps.data,
                                            'delete',
                                        )
                                    "
                                    :disabled="!canDeleteRole(slotProps.data)"
                                />
                            </div>
                        </template>
                    </Column>

                    <template #empty>
                        <div class="py-6 text-center">
                            <i
                                class="pi pi-users text-color-secondary mb-3 text-4xl"
                            ></i>
                            <h3 class="text-900 mb-2 text-xl font-medium">
                                No Roles Found
                            </h3>
                            <p class="text-600 mb-4">
                                Get started by creating your first role.
                            </p>
                            <Button
                                label="Create New Role"
                                icon="pi pi-plus"
                                severity="primary"
                                @click="handleCreateRole"
                            />
                        </div>
                    </template>

                    <template #loading>
                        <div class="py-4 text-center">
                            <i
                                class="pi pi-spin pi-spinner text-primary mb-2 text-2xl"
                            ></i>
                            <p class="text-600">Loading roles...</p>
                        </div>
                    </template>
                </DataTable>

                <!-- Paginator -->
                <div
                    class="justify-content-between align-items-center mt-4 flex"
                >
                    <div class="text-500 text-sm">
                        Showing
                        {{ Math.min(roleData.data.length, paginatorRows) }} of
                        {{ roleData.total }} roles
                    </div>
                    <Paginator
                        :rows="paginatorRows"
                        :totalRecords="paginatorTotalRecords"
                        :first="(paginatorCurrentPage - 1) * paginatorRows"
                        @page="onPageChange"
                        :template="{
                            '640px':
                                'PrevPageLink CurrentPageReport NextPageLink',
                            '960px':
                                'FirstPageLink PrevPageLink CurrentPageReport NextPageLink LastPageLink',
                            default:
                                'FirstPageLink PrevPageLink PageLinks NextPageLink LastPageLink RowsPerPageDropdown',
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
            <div class="align-items-center flex">
                <i
                    class="pi pi-exclamation-triangle mr-3 text-red-500"
                    style="font-size: 2rem"
                ></i>

                <span v-if="currentRole">
                    Are you sure you want to
                    <strong>permanently delete</strong> the role:
                    <strong>'{{ currentRole.name }}'</strong>? <br /><br />
                    This action cannot be undone and will remove this role from
                    all users.
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
                    :loading="loading"
                />
            </template>
        </Dialog>

        <!-- CREATE/EDIT ROLE DIALOG -->
        <Dialog
            v-model:visible="showCreateRoleModal"
            :style="{ width: '800px', maxWidth: '90vw' }"
            :header="isEdit ? 'Edit Role' : 'Create New Role'"
            :modal="true"
            class="p-fluid"
            :closable="!loading"
        >
            <form @submit.prevent="saveRole" class="flex flex-col gap-4">
                <!-- Role Name -->
                <div class="field">
                    <label for="name" class="mb-2 font-semibold"
                        >Role Name <span class="text-red-500">*</span></label
                    >
                    <InputText
                        id="name"
                        v-model="name"
                        :class="{ 'p-invalid': nameError }"
                        placeholder="e.g., admin, manager, user"
                        class="w-full"
                        :disabled="loading"
                    />
                    <small class="text-500 mt-1 block">
                        Use lowercase letters and avoid spaces (e.g.,
                        content-manager)
                    </small>
                    <Message
                        v-if="nameError"
                        severity="error"
                        :closable="false"
                        class="mt-2"
                        >{{ nameError }}</Message
                    >
                </div>

                <!-- Description -->
                <div class="field">
                    <label for="description" class="mb-2 font-semibold"
                        >Description</label
                    >
                    <Textarea
                        id="description"
                        v-model="description"
                        :class="{ 'p-invalid': descriptionError }"
                        rows="3"
                        placeholder="Describe the purpose and responsibilities of this role..."
                        class="w-full"
                        :disabled="loading"
                    />
                    <Message
                        v-if="descriptionError"
                        severity="error"
                        :closable="false"
                        class="mt-2"
                        >{{ descriptionError }}</Message
                    >
                </div>

                <!-- Permissions Section -->
                <div class="field">
                    <div
                        class="justify-content-between align-items-center mb-3 flex"
                    >
                        <label class="font-semibold">Permissions</label>
                        <div class="flex gap-2">
                            <span class="text-500 text-sm">
                                {{ permissions.length }} permission(s) selected
                            </span>
                        </div>
                    </div>

                    <div
                        v-if="Object.keys(groupedPermissions).length > 0"
                        class="surface-border border-round border-1"
                    >
                        <div
                            class="surface-ground border-bottom-1 surface-border p-3"
                        >
                            <div
                                class="justify-content-between align-items-center flex"
                            >
                                <span class="font-semibold"
                                    >Available Permissions</span
                                >
                                <div class="flex gap-2">
                                    <Button
                                        label="Select All"
                                        size="small"
                                        text
                                        @click="
                                            permissions = all_permissions.map(
                                                (p) => p.name,
                                            )
                                        "
                                        :disabled="loading"
                                    />
                                    <Button
                                        label="Clear All"
                                        size="small"
                                        text
                                        @click="permissions = []"
                                        :disabled="loading"
                                    />
                                </div>
                            </div>
                        </div>

                        <div
                            style="max-height: 400px; overflow-y: auto"
                            class="p-3"
                        >
                            <div
                                v-for="(group, groupName) in groupedPermissions"
                                :key="groupName"
                                class="mb-4"
                            >
                                <!-- Group Header -->
                                <div
                                    class="justify-content-between align-items-center surface-50 border-round mb-2 flex p-2"
                                >
                                    <div class="align-items-center flex gap-2">
                                        <Checkbox
                                            :modelValue="
                                                isGroupFullySelected(groupName)
                                            "
                                            @update:modelValue="
                                                isGroupFullySelected(groupName)
                                                    ? deselectAllInGroup(
                                                          groupName,
                                                      )
                                                    : selectAllInGroup(
                                                          groupName,
                                                      )
                                            "
                                            :binary="true"
                                            :disabled="loading"
                                        />
                                        <span class="text-lg font-semibold">{{
                                            group.name
                                        }}</span>
                                        <Tag
                                            :value="group.permissions.length"
                                            severity="info"
                                            class="text-xs"
                                        />
                                    </div>
                                    <div class="flex gap-2">
                                        <Button
                                            label="Select All"
                                            size="small"
                                            text
                                            @click="selectAllInGroup(groupName)"
                                            :disabled="
                                                loading ||
                                                isGroupFullySelected(groupName)
                                            "
                                        />
                                        <Button
                                            label="Clear"
                                            size="small"
                                            text
                                            @click="
                                                deselectAllInGroup(groupName)
                                            "
                                            :disabled="
                                                loading ||
                                                (!isGroupPartiallySelected(
                                                    groupName,
                                                ) &&
                                                    !isGroupFullySelected(
                                                        groupName,
                                                    ))
                                            "
                                        />
                                    </div>
                                </div>

                                <!-- Permissions Grid -->
                                <div class="grid">
                                    <div
                                        v-for="permission in group.permissions"
                                        :key="permission.id"
                                        class="col-12 mb-2 md:col-6 lg:col-4"
                                    >
                                        <div
                                            class="align-items-center surface-border border-round hover:surface-100 transition-duration-150 flex border-1 p-2"
                                        >
                                            <Checkbox
                                                :id="`permission_${permission.id}`"
                                                v-model="permissions"
                                                :value="permission.name"
                                                :binary="false"
                                                :disabled="loading"
                                            />
                                            <label
                                                :for="`permission_${permission.id}`"
                                                class="ml-2 flex-1 cursor-pointer text-sm"
                                            >
                                                <div class="font-medium">
                                                    {{
                                                        formatPermissionName(
                                                            permission.name,
                                                        )
                                                    }}
                                                </div>
                                                <div
                                                    class="text-500 font-mono text-xs"
                                                >
                                                    {{ permission.name }}
                                                </div>
                                            </label>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div
                        v-else
                        class="text-500 surface-border border-round border-1 p-4 text-center"
                    >
                        <i class="pi pi-info-circle mb-2 block text-2xl"></i>
                        No permissions available. <br />
                        <router-link
                            to="/permissions"
                            class="text-primary-600 hover:underline"
                        >
                            Create some permissions first
                        </router-link>
                    </div>

                    <Message
                        v-if="permissionsError"
                        severity="error"
                        :closable="false"
                        class="mt-2"
                        >{{ permissionsError }}</Message
                    >
                </div>
            </form>

            <template #footer>
                <Button
                    label="Cancel"
                    icon="pi pi-times"
                    @click="showCreateRoleModal = false"
                    text
                    :disabled="loading"
                />
                <Button
                    :label="isEdit ? 'Update Role' : 'Create Role'"
                    icon="pi pi-check"
                    @click="saveRole"
                    :loading="loading"
                    :disabled="loading"
                    severity="primary"
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

.field {
    margin-bottom: 1.5rem;
}

.permission-group {
    border: 1px solid var(--surface-border);
    border-radius: 6px;
    margin-bottom: 1rem;
}

.permission-group-header {
    background: var(--surface-ground);
    padding: 0.75rem 1rem;
    border-bottom: 1px solid var(--surface-border);
    border-radius: 6px 6px 0 0;
}

.permission-item {
    padding: 0.5rem;
    border: 1px solid var(--surface-border);
    border-radius: 4px;
    transition: all 0.15s;
}

.permission-item:hover {
    background: var(--surface-hover);
}
</style>
