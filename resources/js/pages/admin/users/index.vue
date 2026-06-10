<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head title="User Management" />

        <Toast />

        <Card>
            <template #title>
                <div class="justify-content-between align-items-center flex">
                    <span>User Management ({{ users.total }})</span>

                    <div class="relative">
                        <Button
                            label="Add User"
                            icon="pi pi-plus"
                            severity="success"
                            @click="toggleMenu"
                            aria-controls="user_menu"
                            aria-haspopup="true"
                        />

                        <Menu
                            ref="menu"
                            id="user_menu"
                            :model="newUserItems"
                            :popup="true"
                        >
                            <template #item="{ item, props }">
                                <a
                                    v-ripple
                                    class="align-items-center p-menuitem-link flex"
                                    v-bind="props.action"
                                >
                                    <span :class="item.icon" />
                                    <div class="flex-column ml-3 flex">
                                        <span class="font-bold">{{
                                            item.label
                                        }}</span>
                                        <small class="text-500">{{
                                            item.description
                                        }}</small>
                                    </div>
                                </a>
                            </template>
                        </Menu>
                    </div>
                </div>
            </template>

            <template #content>
                <!-- Filters Section -->
                <div class="filters mb-4">
                    <div class="grid">
                        <div class="col-12 md:col-4">
                            <div class="p-inputgroup">
                                <InputText
                                    v-model="filters.search"
                                    placeholder="Search users..."
                                    @input="onFilter"
                                />
                                <Button icon="pi pi-search" />
                            </div>
                        </div>
                        <div class="col-12 md:col-3">
                            <Dropdown
                                v-model="filters.role"
                                :options="roleOptions"
                                optionLabel="label"
                                optionValue="value"
                                placeholder="Filter by Role"
                                @change="onFilter"
                                showClear
                            />
                        </div>
                        <div class="col-12 md:col-3">
                            <Dropdown
                                v-model="filters.status"
                                :options="statusOptions"
                                optionLabel="label"
                                optionValue="value"
                                placeholder="Filter by Status"
                                @change="onFilter"
                                showClear
                            />
                        </div>
                        <div class="col-12 md:col-2">
                            <Button
                                icon="pi pi-filter-slash"
                                label="Clear"
                                severity="secondary"
                                @click="clearFilters"
                                outlined
                            />
                        </div>
                    </div>
                </div>

                <!-- DataTable (keep existing) -->
                <DataTable
                    :value="users.data"
                    dataKey="id"
                    stripedRows
                    responsiveLayout="scroll"
                    class="p-datatable-sm"
                    :loading="loading"
                    :emptyMessage="'No users found.'"
                    :paginator="true"
                    :rows="100"
                    :totalRecords="users.total"
                    :lazy="true"
                    @page="onPage"
                    @sort="onSort"
                    sortField="created_at"
                    :sortOrder="-1"
                    removableSort
                >
                    <!-- Columns (keep existing) -->
                    <Column
                        field="id"
                        header="ID"
                        headerStyle="width: 5%"
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
                        header="User"
                        headerStyle="width: 25%"
                        :sortable="true"
                    >
                        <template #body="slotProps">
                            <div class="align-items-center flex gap-2">
                                <div
                                    class="surface-200 border-circle align-items-center justify-content-center flex"
                                    style="width: 32px; height: 32px"
                                >
                                    <span class="text-500 font-semibold">{{
                                        slotProps.data.name
                                            ?.charAt(0)
                                            ?.toUpperCase() || 'U'
                                    }}</span>
                                </div>
                                <div>
                                    <div class="text-primary-600 font-medium">
                                        {{ slotProps.data.name || 'N/A' }}
                                    </div>
                                    <div class="text-500 text-sm">
                                        {{ slotProps.data.email || 'N/A' }}
                                    </div>
                                </div>
                            </div>
                        </template>
                    </Column>

                    <Column
                        field="roles"
                        header="Roles"
                        headerStyle="width: 20%"
                    >
                        <template #body="slotProps">
                            <div class="flex flex-wrap gap-1">
                                <Tag
                                    v-for="role in slotProps.data.roles || []"
                                    :key="role.id"
                                    :value="role.name"
                                    severity="info"
                                    class="text-xs"
                                />
                                <span
                                    v-if="
                                        !slotProps.data.roles ||
                                        slotProps.data.roles.length === 0
                                    "
                                    class="text-500 text-sm"
                                    >No roles assigned</span
                                >
                            </div>
                        </template>
                    </Column>

                    <Column
                        field="email_verified_at"
                        header="Status"
                        headerStyle="width: 10%"
                        :sortable="true"
                    >
                        <template #body="slotProps">
                            <Tag
                                :value="getStatusText(slotProps.data)"
                                :severity="getStatusSeverity(slotProps.data)"
                            />
                        </template>
                    </Column>

                    <Column
                        field="last_login_at"
                        header="Last Login"
                        headerStyle="width: 15%"
                        :sortable="true"
                    >
                        <template #body="slotProps">
                            {{ slotProps.data.last_login_formatted || 'Never' }}
                        </template>
                    </Column>

                    <Column
                        field="created_at"
                        header="Joined"
                        headerStyle="width: 15%"
                        :sortable="true"
                    >
                        <template #body="slotProps">
                            {{ slotProps.data.created_at_formatted || 'N/A' }}
                        </template>
                    </Column>

                    <Column
                        header="Actions"
                        headerStyle="width: 15%"
                        bodyClass="text-center"
                    >
                        <template #body="slotProps">
                            <div class="justify-content-center flex gap-2">
                                <!-- VIEW BUTTON -->
                                <Button
                                    icon="pi pi-eye"
                                    text
                                    rounded
                                    severity="info"
                                    v-tooltip.top="'View User Details'"
                                    @click="viewUser(slotProps.data)"
                                />

                                <!-- PERMISSIONS BUTTON -->
                                <Button
                                    icon="pi pi-key"
                                    text
                                    rounded
                                    severity="help"
                                    v-tooltip.top="'Manage Roles & Permissions'"
                                    @click="managePermissions(slotProps.data)"
                                />

                                <!-- EDIT BUTTON -->
                                <Button
                                    icon="pi pi-pencil"
                                    text
                                    rounded
                                    severity="secondary"
                                    :disabled="!canEditUser(slotProps.data)"
                                    v-tooltip.top="
                                        canEditUser(slotProps.data)
                                            ? 'Edit User'
                                            : 'Cannot edit this user'
                                    "
                                    @click="openEditModal(slotProps.data)"
                                />

                                <!-- DELETE BUTTON -->
                                <Button
                                    icon="pi pi-trash"
                                    text
                                    rounded
                                    severity="danger"
                                    :disabled="!canDeleteUser(slotProps.data)"
                                    v-tooltip.top="
                                        canDeleteUser(slotProps.data)
                                            ? 'Delete User'
                                            : 'Cannot delete this user'
                                    "
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

                    <template #empty>
                        <div class="py-4 text-center">
                            <i
                                class="pi pi-users text-color-secondary mb-2 text-4xl"
                            ></i>
                            <p class="text-color-secondary">No users found</p>
                        </div>
                    </template>

                    <template #loading>
                        <div class="py-4 text-center">
                            <ProgressSpinner
                                style="width: 50px; height: 50px"
                            />
                            <p class="mt-2">Loading users...</p>
                        </div>
                    </template>
                </DataTable>

                <!-- Pagination -->
                <div class="justify-content-end mt-4 flex">
                    <Paginator
                        :rows="users.per_page"
                        :totalRecords="users.total"
                        :first="(users.current_page - 1) * users.per_page"
                        @page="onPageChange"
                        :template="{
                            '640px':
                                'PrevPageLink CurrentPageReport NextPageLink',
                            '960px':
                                'FirstPageLink PrevPageLink CurrentPageReport NextPageLink LastPageLink',
                            '1300px':
                                'FirstPageLink PrevPageLink PageLinks NextPageLink LastPageLink',
                            default:
                                'FirstPageLink PrevPageLink PageLinks NextPageLink LastPageLink RowsPerPageDropdown',
                        }"
                    />
                </div>
            </template>
        </Card>

        <!-- Create/Edit User Dialog -->
        <Dialog
            v-model:visible="userFormDialog"
            :header="userFormEditMode ? 'Edit User' : 'Create User'"
            :style="{ width: '90vw', maxWidth: '1200px' }"
            :modal="true"
            maximizable
        >
            <UserForm
                :user="selectedUser"
                :editMode="userFormEditMode"
                :all-roles="props.allRoles"
                :all-permissions="props.allPermissions"
                :all-mdas="props.allMdas"
                @saved="onUserFormSaved"
                @cancel="userFormDialog = false"
            />
        </Dialog>

        <!-- User Details Dialog -->
        <Dialog
            v-model:visible="detailsDialog"
            header="User Details"
            :style="{ width: '600px' }"
            :modal="true"
        >
            <UserDetails :user="selectedUser" v-if="selectedUser" />
        </Dialog>

        <!-- Roles & Permissions Dialog -->
        <Dialog
            v-model:visible="permissionsDialog"
            header="Manage Roles & Permissions"
            :style="{ width: '90vw', maxWidth: '1200px' }"
            :modal="true"
            maximizable
        >
            <UserRolesPermissions
                :userId="selectedUser?.id"
                :userData="selectedUser"
                :allRoles="props.allRoles"
                :allPermissions="props.allPermissions"
                @saved="onPermissionsSaved"
                v-if="selectedUser"
            />
        </Dialog>

        <!-- Delete Confirmation Dialog -->
        <Dialog
            v-model:visible="showConfirmationModal"
            :style="{ width: '400px' }"
            header="Confirm Action"
            :modal="true"
        >
            <div class="align-items-center flex">
                <i
                    :class="
                        currentAction === 'delete'
                            ? 'pi pi-exclamation-triangle mr-3 text-red-500'
                            : 'pi pi-question-circle mr-3 text-orange-500'
                    "
                    style="font-size: 2rem"
                ></i>

                <span v-if="currentUser && currentAction === 'delete'">
                    Are you sure you want to
                    <strong>permanently delete</strong> User
                    <strong>{{ currentUser.name }}</strong
                    >? This action cannot be undone.
                </span>
            </div>

            <template #footer>
                <Button
                    label="No"
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
    </AppLayout>
</template>

<script setup>
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, router } from '@inertiajs/vue3';
import Button from 'primevue/button';
import Card from 'primevue/card';
import Column from 'primevue/column';
import DataTable from 'primevue/datatable';
import Dialog from 'primevue/dialog';
import Dropdown from 'primevue/dropdown';
import InputText from 'primevue/inputtext';
import Menu from 'primevue/menu';
import Paginator from 'primevue/paginator';
import ProgressSpinner from 'primevue/progressspinner';
import Tag from 'primevue/tag';
import Toast from 'primevue/toast';
import { useToast } from 'primevue/usetoast';
import { computed, onMounted, ref } from 'vue';

// Components
import UserDetails from './UserDetails.vue';
import UserForm from './UserForm.vue';
import UserRolesPermissions from './UserRolesPermissions.vue';

const toast = useToast();

// ðŸ’¡ State for Modals
const showConfirmationModal = ref(false);
const currentUser = ref(null);
const currentAction = ref(null);
const userFormDialog = ref(false);
const userFormEditMode = ref(false);
const detailsDialog = ref(false);
const permissionsDialog = ref(false);
const selectedUser = ref(null);
const loading = ref(false);

// ðŸ’¡ Menu Reference
const menu = ref(null);

// ðŸ’¡ PROPS: Receive real data from Laravel controller
const props = defineProps({
    users: {
        type: Object,
        required: true,
        default: () => ({
            data: [],
            total: 0,
            current_page: 1,
            per_page: 100,
            links: [],
        }),
    },
    filters: {
        type: Object,
        default: () => ({
            search: '',
            role: null,
            status: null,
            sort: 'created_at',
            order: 'desc',
            per_page: 100,
        }),
    },
    allRoles: {
        type: Array,
        default: () => [],
    },
    allPermissions: {
        type: Array,
        default: () => [],
    },
    allMdas: {
        type: Array,
        default: () => [],
    },
});

// Use the real users data from props
const users = computed(() => props.users);

// Filters
const filters = ref({
    search: props.filters.search || '',
    role: props.filters.role || null,
    status: props.filters.status || null,
    page: props.users.current_page || 1,
    sort: props.filters.sort || 'created_at',
    order: props.filters.order || 'desc',
});

// Options for filters
const roleOptions = ref([
    { label: 'Admin', value: 'admin' },
    { label: 'Manager', value: 'manager' },
    { label: 'User', value: 'user' },
]);

const statusOptions = ref([
    { label: 'Verified', value: 'verified' },
    { label: 'Unverified', value: 'unverified' },
]);

// --- NEW USER MENU ---
const newUserItems = ref([
    {
        label: 'Create User',
        icon: 'pi pi-user-plus',
        description: 'Create a new user with basic information',
        command: () => {
            openCreateModal();
        },
    },
    {
        label: 'Import Users',
        icon: 'pi pi-upload',
        description: 'Import multiple users from CSV file',
        command: () => {
            // You can implement import functionality here
            toast.add({
                severity: 'info',
                summary: 'Import Users',
                detail: 'Import functionality coming soon...',
                life: 3000,
            });
        },
    },
]);

const toggleMenu = (event) => {
    if (menu.value) {
        menu.value.toggle(event);
    }
};

// User permission functions
const canEditUser = (user) => {
    if (!user) return false;
    return user.id !== 1;
};

const canDeleteUser = (user) => {
    if (!user) return false;
    return user.id !== 1;
};

// Status functions
const getStatusSeverity = (user) => {
    if (!user) return 'info';
    return user.is_verified ? 'success' : 'warning';
};

const getStatusText = (user) => {
    if (!user) return 'Unknown';
    return user.is_verified ? 'Verified' : 'Unverified';
};

// --- MODAL HANDLING FUNCTIONS ---
const openCreateModal = () => {
    selectedUser.value = null;
    userFormEditMode.value = false;
    userFormDialog.value = true;
};

const openEditModal = (user) => {
    if (!canEditUser(user)) {
        toast.add({
            severity: 'error',
            summary: 'Cannot Edit',
            detail: `User ${user.name} cannot be edited.`,
            life: 5000,
        });
        return;
    }

    selectedUser.value = user;
    userFormEditMode.value = true;
    userFormDialog.value = true;
};

const openConfirmationModal = (user, action) => {
    if (action === 'delete' && !canDeleteUser(user)) {
        toast.add({
            severity: 'error',
            summary: 'Cannot Delete',
            detail: `User ${user.name} cannot be deleted. This user may be a system administrator.`,
            life: 5000,
        });
        return;
    }

    currentUser.value = user;
    currentAction.value = action;
    showConfirmationModal.value = true;
};

const confirmAction = () => {
    showConfirmationModal.value = false;

    if (!currentUser.value) return;

    const id = currentUser.value.id;

    if (currentAction.value === 'delete') {
        router.delete(route('users.destroy', id), {
            preserveScroll: true,
            onSuccess: () => {
                toast.add({
                    severity: 'success',
                    summary: 'Deleted',
                    detail: `User ${currentUser.value.name} successfully deleted.`,
                    life: 3000,
                });
            },
            onError: (errors) => {
                const detail = errors.message || 'Failed to delete the user.';
                toast.add({
                    severity: 'error',
                    summary: 'Error',
                    detail: detail,
                    life: 5000,
                });
            },
        });
    }
};

// --- USER MANAGEMENT FUNCTIONS ---
const viewUser = (user) => {
    selectedUser.value = user;
    detailsDialog.value = true;
};

const managePermissions = (user) => {
    selectedUser.value = user;
    permissionsDialog.value = true;
};

// --- FILTERING AND PAGINATION ---
const onFilter = () => {
    filters.value.page = 1;
    loadUsers();
};

const clearFilters = () => {
    filters.value = {
        search: '',
        role: null,
        status: null,
        page: 1,
        sort: 'created_at',
        order: 'desc',
    };
    loadUsers();
};

const onPage = (event) => {
    filters.value.page = event.page + 1;
    loadUsers();
};

const onPageChange = (event) => {
    filters.value.page = event.page + 1;
    loadUsers();
};

const onSort = (event) => {
    filters.value.sort = event.sortField;
    filters.value.order = event.sortOrder === 1 ? 'asc' : 'desc';
    loadUsers();
};

const loadUsers = () => {
    loading.value = true;
    router.get(route('users.index'), filters.value, {
        preserveState: true,
        preserveScroll: true,
        onFinish: () => {
            loading.value = false;
        },
    });
};

// --- EVENT HANDLERS ---
// const onUserFormSaved = () => {
//     userFormDialog.value = false;
//     loadUsers();
//     toast.add({
//         severity: 'success',
//         summary: 'Success',
//         detail: `User ${userFormEditMode.value ? 'updated' : 'created'} successfully`,
//         life: 3000,
//     });
// };

// In index.vue - update the onUserFormSaved method
const onUserFormSaved = (formData) => {
    console.log('âœ… index.vue - onUserFormSaved received data:', formData);
    console.log('âœ… index.vue - UserFormEditMode:', userFormEditMode.value);
    console.log('âœ… index.vue - Selected User ID:', selectedUser.value?.id);

    userFormDialog.value = false;

    if (userFormEditMode.value) {
        // Editing existing user
        console.log(
            'ðŸ”„ index.vue - Sending PUT request for user ID:',
            selectedUser.value.id,
        );

        router.put(`/users/${selectedUser.value.id}`, formData, {
            preserveScroll: true,
            onSuccess: (page) => {
                console.log('âœ… index.vue - PUT request successful');
                console.log('âœ… index.vue - Response:', page);
                router.reload({
                    preserveScroll: true,
                    onSuccess: () => {
                        toast.add({
                            severity: 'success',
                            summary: 'Success',
                            detail: 'User updated successfully',
                            life: 3000,
                        });
                    },
                });
            },
            onError: (errors) => {
                console.error('âŒ index.vue - PUT request failed:', errors);
                toast.add({
                    severity: 'error',
                    summary: 'Error',
                    detail:
                        'Failed to update user: ' +
                        (errors.message || 'Unknown error'),
                    life: 5000,
                });
            },
            onFinish: () => {
                console.log('ðŸ”„ index.vue - PUT request finished');
            },
        });
    } else {
        // Creating new user
        console.log('ðŸ”„ index.vue - Sending POST request for new user');

        router.post('/users', formData, {
            preserveScroll: true,
            onSuccess: (page) => {
                console.log('âœ… index.vue - POST request successful');
                console.log('âœ… index.vue - Response:', page);
                router.reload({
                    preserveScroll: true,
                    onSuccess: () => {
                        toast.add({
                            severity: 'success',
                            summary: 'Success',
                            detail: 'User created successfully',
                            life: 3000,
                        });
                    },
                });
            },
            onError: (errors) => {
                console.error('âŒ index.vue - POST request failed:', errors);
                toast.add({
                    severity: 'error',
                    summary: 'Error',
                    detail:
                        'Failed to create user: ' +
                        (errors.message || 'Unknown error'),
                    life: 5000,
                });
            },
            onFinish: () => {
                console.log('ðŸ”„ index.vue - POST request finished');
            },
        });
    }
};

const onPermissionsSaved = (type) => {
    permissionsDialog.value = false;
    loadUsers();

    let message = 'User data updated successfully';
    if (type === 'roles') {
        message = 'User roles updated successfully';
    } else if (type === 'permissions') {
        message = 'User direct permissions updated successfully';
    }

    toast.add({
        severity: 'success',
        summary: 'Success',
        detail: message,
        life: 3000,
    });
};

// Breadcrumbs
const breadcrumbs = [{ title: 'Users', href: '#' }];

// Call loadUsers on mount
onMounted(() => {
    // Initial data is loaded via props
});
</script>

<style scoped>
.filters {
    background: var(--surface-ground);
    padding: 1rem;
    border-radius: 6px;
}

:deep(.p-datatable) {
    .p-column-header-content {
        display: flex;
        align-items: center;
    }
}

.user-avatar {
    width: 32px;
    height: 32px;
    border-radius: 50%;
    background-color: var(--surface-200);
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 600;
    color: var(--text-color-secondary);
}
</style>
