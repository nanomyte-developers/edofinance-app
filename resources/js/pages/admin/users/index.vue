<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head title="User Management" />

        <Toast />

        <Card>
            <template #title>
                <div class="justify-content-between align-items-center flex flex-wrap gap-2">
                    <span>User Management ({{ users.total }})</span>

                    <div class="flex gap-2 flex-wrap">
                        <!-- Manage Signatories Button -->
                        <Button
                            label="Manage Signatories"
                            icon="pi pi-users"
                            severity="secondary"
                            outlined
                            @click="openSignatoryModal"
                        />

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

                <!-- DataTable -->
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
                        headerStyle="width: 22%"
                        :sortable="true"
                    >
                        <template #body="slotProps">
                            <div class="align-items-center flex gap-2">
                                <!-- Show passport if exists, otherwise show alphabet -->
                                <div
                                    v-if="slotProps.data.passport_url"
                                    class="passport-avatar"
                                >
                                    <img 
                                        :src="slotProps.data.passport_url" 
                                        :alt="slotProps.data.name"
                                        class="passport-image"
                                        @error="handleImageError(slotProps.data)"
                                    />
                                </div>
                                <div
                                    v-else
                                    class="avatar-placeholder"
                                    :style="{ backgroundColor: getAvatarColor(slotProps.data.id) }"
                                >
                                    <span class="avatar-text">{{
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

                    <!-- ✅ UPDATED: Roles Column - Shows first role with count badge -->
                    <Column
                        field="roles"
                        header="Roles"
                        headerStyle="width: 15%"
                    >
                        <template #body="slotProps">
                            <div 
                                v-if="slotProps.data.roles && slotProps.data.roles.length > 0"
                                class="roles-display cursor-pointer"
                                @click="openRolesModal(slotProps.data)"
                            >
                                <!-- Show first role -->
                                <Tag
                                    :value="slotProps.data.roles[0].name"
                                    severity="info"
                                    class="text-xs"
                                />
                                <!-- Show count badge if more than 1 role -->
                                <Badge
                                    v-if="slotProps.data.roles.length > 1"
                                    :value="`+${slotProps.data.roles.length - 1}`"
                                    severity="primary"
                                    class="roles-count-badge"
                                />
                                <span class="roles-hint text-500 text-xs ml-1">
                                    <i class="pi pi-eye"></i>
                                </span>
                            </div>
                            <span
                                v-else
                                class="text-500 text-sm"
                            >No roles assigned</span>
                        </template>
                    </Column>

                    <Column
                        field="email_verified_at"
                        header="Status"
                        headerStyle="width: 8%"
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
                        headerStyle="width: 12%"
                        :sortable="true"
                    >
                        <template #body="slotProps">
                            {{ slotProps.data.last_login_formatted || 'Never' }}
                        </template>
                    </Column>

                    <Column
                        field="created_at"
                        header="Joined"
                        headerStyle="width: 12%"
                        :sortable="true"
                    >
                        <template #body="slotProps">
                            {{ slotProps.data.created_at_formatted || 'N/A' }}
                        </template>
                    </Column>

                    <!-- Signatory Column -->
                    <Column
                        header="Signatory"
                        headerStyle="width: 8%"
                    >
                        <template #body="slotProps">
                            <Badge
                                :value="slotProps.data.can_be_signatory ? 'Yes' : 'No'"
                                :severity="slotProps.data.can_be_signatory ? 'success' : 'secondary'"
                                class="cursor-pointer"
                                @click="openSignatoryModal"
                                v-tooltip="'Click to manage signatories'"
                            />
                        </template>
                    </Column>

                    <Column
                        header="Actions"
                        headerStyle="width: 15%"
                        bodyClass="text-center"
                    >
                        <template #body="slotProps">
                            <div class="justify-content-center flex gap-2">
                                <Button
                                    icon="pi pi-eye"
                                    text
                                    rounded
                                    severity="info"
                                    v-tooltip.top="'View User Details'"
                                    @click="viewUser(slotProps.data)"
                                />

                                <Button
                                    icon="pi pi-key"
                                    text
                                    rounded
                                    severity="help"
                                    v-tooltip.top="'Manage Roles & Permissions'"
                                    @click="managePermissions(slotProps.data)"
                                />

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
                :all-user-categories="props.allUserCategories"
                :all-users="props.allUsers"
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

        <!-- ✅ NEW: Roles View Dialog -->
        <Dialog
            v-model:visible="rolesDialogVisible"
            header="User Roles"
            :style="{ width: '500px' }"
            :modal="true"
        >
            <div v-if="selectedUserForRoles" class="roles-dialog">
                <div class="flex align-items-center gap-2 mb-3">
                    <div
                        v-if="selectedUserForRoles.passport_url"
                        class="passport-avatar-small"
                    >
                        <img 
                            :src="selectedUserForRoles.passport_url" 
                            :alt="selectedUserForRoles.name"
                            class="passport-image-small"
                        />
                    </div>
                    <div
                        v-else
                        class="avatar-placeholder-small"
                        :style="{ backgroundColor: getAvatarColor(selectedUserForRoles.id) }"
                    >
                        <span class="avatar-text-small">{{
                            selectedUserForRoles.name?.charAt(0)?.toUpperCase() || 'U'
                        }}</span>
                    </div>
                    <div>
                        <div class="font-medium">{{ selectedUserForRoles.name }}</div>
                        <div class="text-sm text-500">{{ selectedUserForRoles.email }}</div>
                    </div>
                </div>

                <Divider />

                <div v-if="selectedUserForRoles.roles && selectedUserForRoles.roles.length > 0">
                    <div class="flex flex-wrap gap-2">
                        <Tag
                            v-for="role in selectedUserForRoles.roles"
                            :key="role.id"
                            :value="role.name"
                            severity="info"
                            class="text-sm"
                        />
                    </div>
                    <div class="mt-2 text-500 text-sm">
                        Total: <strong>{{ selectedUserForRoles.roles.length }}</strong> role(s)
                    </div>
                </div>
                <div v-else class="text-500">
                    No roles assigned to this user.
                </div>
            </div>

            <template #footer>
                <Button
                    label="Close"
                    icon="pi pi-times"
                    @click="rolesDialogVisible = false"
                    text
                />
                <Button
                    label="Manage Roles"
                    icon="pi pi-key"
                    severity="primary"
                    @click="goToRolesManagement"
                />
            </template>
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

        <!-- Signatory Management Modal -->
        <Dialog
            v-model:visible="signatoryModalVisible"
            header="Manage Signatories"
            :style="{ width: '85%', maxWidth: '1000px' }"
            :modal="true"
            :closable="true"
            class="p-fluid"
        >
            <div class="signatory-management">
                <div class="flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
                    <div class="flex align-items-center gap-2">
                        <i class="pi pi-info-circle text-primary"></i>
                        <span class="text-500 text-sm">
                            Toggle the switch to allow users to be selected as signatories for others.
                            Only users marked as "Yes" will appear in the signatory dropdown.
                        </span>
                    </div>
                    <Button
                        icon="pi pi-refresh"
                        label="Refresh"
                        severity="secondary"
                        text
                        size="small"
                        @click="refreshSignatories"
                    />
                </div>

                <DataTable
                    :value="signatoryUsers"
                    :loading="signatoryLoading"
                    paginator
                    :rows="10"
                    :rowsPerPageOptions="[5, 10, 25, 50]"
                    class="p-datatable-sm"
                    stripedRows
                >
                    <Column field="id" header="ID" style="width: 60px" sortable />
                    
                    <Column field="name" header="Name" sortable style="min-width: 180px">
                        <template #body="{ data }">
                            <div class="flex align-items-center gap-2">
                                <div
                                    v-if="data.passport_url"
                                    class="passport-avatar-small"
                                >
                                    <img 
                                        :src="data.passport_url" 
                                        :alt="data.name"
                                        class="passport-image-small"
                                        @error="handleImageError(data)"
                                    />
                                </div>
                                <div
                                    v-else
                                    class="avatar-placeholder-small"
                                    :style="{ backgroundColor: getAvatarColor(data.id) }"
                                >
                                    <span class="avatar-text-small">{{
                                        data.name?.charAt(0)?.toUpperCase() || 'U'
                                    }}</span>
                                </div>
                                <div>
                                    <div class="font-medium">{{ data.name }}</div>
                                    <div class="text-sm text-500">{{ data.email }}</div>
                                </div>
                            </div>
                        </template>
                    </Column>
                    
                    <Column field="email" header="Email" sortable style="min-width: 200px" />
                    
                    <Column field="user_category.name" header="Category" style="min-width: 150px">
                        <template #body="{ data }">
                            <Badge
                                v-if="data.user_category"
                                :value="data.user_category.name"
                                severity="info"
                            />
                            <span v-else class="text-500">Not assigned</span>
                        </template>
                    </Column>
                    
                    <Column header="Can Be Signatory" style="width: 180px">
                        <template #body="{ data }">
                            <div class="flex align-items-center gap-2">
                                <ToggleButton
                                    v-model="data.can_be_signatory"
                                    onLabel="Yes"
                                    offLabel="No"
                                    onIcon="pi pi-check"
                                    offIcon="pi pi-times"
                                    class="w-full sm:w-8rem"
                                    :loading="updatingSignatoryIds.includes(data.id)"
                                    @change="updateSignatoryStatus(data)"
                                />
                                <i 
                                    v-if="updatingSignatoryIds.includes(data.id)"
                                    class="pi pi-spin pi-spinner text-primary"
                                />
                            </div>
                        </template>
                    </Column>
                    
                    <Column header="Status" style="width: 120px">
                        <template #body="{ data }">
                            <Badge
                                :value="data.can_be_signatory ? 'Available' : 'Not Available'"
                                :severity="data.can_be_signatory ? 'success' : 'secondary'"
                            />
                        </template>
                    </Column>
                </DataTable>

                <div class="mt-3 flex justify-content-end gap-2">
                    <Button
                        label="Close"
                        icon="pi pi-times"
                        severity="secondary"
                        @click="signatoryModalVisible = false"
                    />
                    <Button
                        label="Save All Changes"
                        icon="pi pi-save"
                        severity="primary"
                        @click="saveSignatoryChanges"
                        :loading="savingSignatories"
                    />
                </div>
            </div>
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
import Badge from 'primevue/badge';
import Avatar from 'primevue/avatar';
import ToggleButton from 'primevue/togglebutton';
import Divider from 'primevue/divider';
import { useToast } from 'primevue/usetoast';
import { computed, onMounted, ref, watch } from 'vue';
import axios from 'axios';

// Components
import UserDetails from './UserDetails.vue';
import UserForm from './UserForm.vue';
import UserRolesPermissions from './UserRolesPermissions.vue';

const toast = useToast();

// State for Modals
const showConfirmationModal = ref(false);
const currentUser = ref(null);
const currentAction = ref(null);
const userFormDialog = ref(false);
const userFormEditMode = ref(false);
const detailsDialog = ref(false);
const permissionsDialog = ref(false);
const selectedUser = ref(null);
const loading = ref(false);

// ✅ State for Roles Dialog
const rolesDialogVisible = ref(false);
const selectedUserForRoles = ref(null);

// State for Signatory Modal
const signatoryModalVisible = ref(false);
const signatoryLoading = ref(false);
const savingSignatories = ref(false);
const updatingSignatoryIds = ref([]);
const signatoryUsers = ref([]);
const originalSignatoryStates = ref({});

// Menu Reference
const menu = ref(null);

// PROPS: Receive real data from Laravel controller
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
    allUserCategories: { 
        type: Array,
        default: () => [],
    },
    allUsers: { 
        type: Array,
        default: () => [],
    },
});

// Use the real users data from props
const users = computed(() => props.users);

// Helper: Get avatar color based on user ID
const getAvatarColor = (id) => {
    const colors = [
        '#FF6B6B', '#4ECDC4', '#45B7D1', '#96CEB4', '#FFEAA7',
        '#DDA0DD', '#98D8C8', '#F7DC6F', '#BB8FCE', '#85C1E9',
        '#F8C471', '#82E0AA', '#F1948A', '#85929E', '#73C6B6'
    ];
    return colors[id % colors.length] || '#6C5CE7';
};

// Helper: Handle image error - show alphabet fallback
const handleImageError = (user) => {
    user.passport_url = null;
};

// Helper: Get storage URL
const getStorageUrl = (path) => {
    if (!path) return null;
    if (path.startsWith('http')) return path;
    if (path.startsWith('storage/')) return '/' + path;
    return '/storage/' + path;
};

// ✅ Helper: Open Roles Modal
const openRolesModal = (user) => {
    selectedUserForRoles.value = user;
    rolesDialogVisible.value = true;
};

// ✅ Helper: Go to Roles Management
const goToRolesManagement = () => {
    rolesDialogVisible.value = false;
    if (selectedUserForRoles.value) {
        managePermissions(selectedUserForRoles.value);
    }
};

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

// New User Menu
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

// --- SIGNATORY MANAGEMENT FUNCTIONS ---
const openSignatoryModal = () => {
    signatoryModalVisible.value = true;
    loadSignatoryUsers();
};

const loadSignatoryUsers = () => {
    signatoryLoading.value = true;
    signatoryUsers.value = (props.allUsers || []).map(user => ({
        ...user,
        can_be_signatory: user.can_be_signatory === true,
        passport_url: user.passport ? getStorageUrl(user.passport) : null,
    }));
    originalSignatoryStates.value = {};
    signatoryUsers.value.forEach(user => {
        originalSignatoryStates.value[user.id] = user.can_be_signatory;
    });
    signatoryLoading.value = false;
};

const refreshSignatories = () => {
    loadSignatoryUsers();
    toast.add({
        severity: 'info',
        summary: 'Refreshed',
        detail: 'Signatory list updated',
        life: 2000,
    });
};

const updateSignatoryStatus = async (user) => {
    updatingSignatoryIds.value.push(user.id);
    
    try {
        // ✅ Use full URL with admin prefix
        const url = `/users/${user.id}/signatory`;
        // const url = route('users.update.signatory', user.id);
        console.log('🔄 Updating signatory status for user:', user.id, 'URL:', url);

        await axios.put(url, {
            can_be_signatory: user.can_be_signatory,
        });
        
        toast.add({
            severity: 'success',
            summary: 'Success',
            detail: `${user.name} signatory status updated successfully`,
            life: 3000,
        });
        
        const mainUserIndex = users.value.data.findIndex(u => u.id === user.id);
        if (mainUserIndex !== -1) {
            users.value.data[mainUserIndex].can_be_signatory = user.can_be_signatory;
        }
        
        originalSignatoryStates.value[user.id] = user.can_be_signatory;
        
    } catch (error) {
        user.can_be_signatory = originalSignatoryStates.value[user.id] || false;
        toast.add({
            severity: 'error',
            summary: 'Error',
            detail: error.response?.data?.message || 'Failed to update signatory status',
            life: 5000,
        });
    } finally {
        const index = updatingSignatoryIds.value.indexOf(user.id);
        if (index !== -1) {
            updatingSignatoryIds.value.splice(index, 1);
        }
    }
};

const saveSignatoryChanges = () => {
    const changedUsers = signatoryUsers.value.filter(
        user => originalSignatoryStates.value[user.id] !== user.can_be_signatory
    );
    
    if (changedUsers.length === 0) {
        toast.add({
            severity: 'info',
            summary: 'No Changes',
            detail: 'No signatory status changes to save',
            life: 3000,
        });
        signatoryModalVisible.value = false;
        return;
    }
    
    savingSignatories.value = true;

    const url = `/users/${user.id}/signatory`;
        // const url = route('users.update.signatory', user.id);
    
    const promises = changedUsers.map(user => {
        return axios.put(url, {
            can_be_signatory: user.can_be_signatory,
        });
    });
    
    Promise.all(promises)
        .then(() => {
            toast.add({
                severity: 'success',
                summary: 'Success',
                detail: `Updated ${changedUsers.length} signatory status(es) successfully`,
                life: 3000,
            });
            
            changedUsers.forEach(user => {
                originalSignatoryStates.value[user.id] = user.can_be_signatory;
                const mainUserIndex = users.value.data.findIndex(u => u.id === user.id);
                if (mainUserIndex !== -1) {
                    users.value.data[mainUserIndex].can_be_signatory = user.can_be_signatory;
                }
            });
            
            signatoryModalVisible.value = false;
        })
        .catch((error) => {
            toast.add({
                severity: 'error',
                summary: 'Error',
                detail: error.response?.data?.message || 'Failed to save signatory changes',
                life: 5000,
            });
        })
        .finally(() => {
            savingSignatories.value = false;
        });
};

// --- EVENT HANDLERS ---
// In index.vue - complete onUserFormSaved method
const onUserFormSaved = (formData) => {
    console.log('✅ index.vue - onUserFormSaved received data:', formData);
    console.log('✅ index.vue - UserFormEditMode:', userFormEditMode.value);
    console.log('✅ index.vue - Selected User ID:', selectedUser.value?.id);

    // Debug: Log FormData contents if it's FormData
    if (formData instanceof FormData) {
        console.log('📤 FormData contents:');
        for (let pair of formData.entries()) {
            console.log(pair[0] + ': ' + (pair[1] instanceof File ? pair[1].name : pair[1]));
        }
    }

    // Close dialog
    userFormDialog.value = false;

    if (userFormEditMode.value) {
        // Editing existing user
        const userId = selectedUser.value.id;
        console.log('🔄 index.vue - Sending PUT request for user ID:', userId);
        
        // ✅ If it's FormData, use POST with _method=PUT
        if (formData instanceof FormData) {
            formData.append('_method', 'PUT');
            router.post(`/users/${userId}`, formData, {
                forceFormData: true,
                preserveScroll: true,
                onSuccess: () => {
                    console.log('✅ index.vue - Update successful');
                    toast.add({
                        severity: 'success',
                        summary: 'Success',
                        detail: 'User updated successfully',
                        life: 3000,
                    });
                    // Reload the page to refresh data
                    router.reload({ 
                        preserveScroll: true,
                        onSuccess: () => {
                            console.log('✅ index.vue - Page reloaded successfully');
                        },
                        onError: (error) => {
                            console.error('❌ index.vue - Page reload failed:', error);
                            toast.add({
                                severity: 'error',
                                summary: 'Error',
                                detail: 'Failed to refresh the page. Please reload manually.',
                                life: 5000,
                            });
                        }
                    });
                },
                onError: (errors) => {
                    console.error('❌ index.vue - Update failed:', errors);
                    
                    // Parse and display validation errors
                    let errorMessage = 'Failed to update user';
                    if (errors && typeof errors === 'object') {
                        const errorMessages = [];
                        for (const [field, messages] of Object.entries(errors)) {
                            if (Array.isArray(messages)) {
                                errorMessages.push(`${field}: ${messages.join(', ')}`);
                            } else {
                                errorMessages.push(`${field}: ${messages}`);
                            }
                        }
                        if (errorMessages.length > 0) {
                            errorMessage = errorMessages.join('; ');
                        }
                    }
                    
                    toast.add({
                        severity: 'error',
                        summary: 'Error',
                        detail: errorMessage,
                        life: 5000,
                    });
                    
                    // ✅ Reopen dialog to fix errors
                    userFormDialog.value = true;
                },
                onFinish: () => {
                    console.log('🔄 index.vue - Update request finished');
                }
            });
        } else {
            // Regular object - use PUT directly
            router.put(`/users/${userId}`, formData, {
                preserveScroll: true,
                onSuccess: () => {
                    console.log('✅ index.vue - Update successful');
                    toast.add({
                        severity: 'success',
                        summary: 'Success',
                        detail: 'User updated successfully',
                        life: 3000,
                    });
                    router.reload({ 
                        preserveScroll: true,
                        onSuccess: () => {
                            console.log('✅ index.vue - Page reloaded successfully');
                        },
                        onError: (error) => {
                            console.error('❌ index.vue - Page reload failed:', error);
                            toast.add({
                                severity: 'error',
                                summary: 'Error',
                                detail: 'Failed to refresh the page. Please reload manually.',
                                life: 5000,
                            });
                        }
                    });
                },
                onError: (errors) => {
                    console.error('❌ index.vue - Update failed:', errors);
                    
                    let errorMessage = 'Failed to update user';
                    if (errors && typeof errors === 'object') {
                        const errorMessages = [];
                        for (const [field, messages] of Object.entries(errors)) {
                            if (Array.isArray(messages)) {
                                errorMessages.push(`${field}: ${messages.join(', ')}`);
                            } else {
                                errorMessages.push(`${field}: ${messages}`);
                            }
                        }
                        if (errorMessages.length > 0) {
                            errorMessage = errorMessages.join('; ');
                        }
                    }
                    
                    toast.add({
                        severity: 'error',
                        summary: 'Error',
                        detail: errorMessage,
                        life: 5000,
                    });
                    
                    userFormDialog.value = true;
                },
                onFinish: () => {
                    console.log('🔄 index.vue - Update request finished');
                }
            });
        }
    } else {
        // Creating new user
        console.log('🔄 index.vue - Sending POST request for new user');

        if (formData instanceof FormData) {
            router.post('/users', formData, {
                forceFormData: true,
                preserveScroll: true,
                onSuccess: () => {
                    console.log('✅ index.vue - Create successful');
                    toast.add({
                        severity: 'success',
                        summary: 'Success',
                        detail: 'User created successfully',
                        life: 3000,
                    });
                    router.reload({ 
                        preserveScroll: true,
                        onSuccess: () => {
                            console.log('✅ index.vue - Page reloaded successfully');
                        },
                        onError: (error) => {
                            console.error('❌ index.vue - Page reload failed:', error);
                            toast.add({
                                severity: 'error',
                                summary: 'Error',
                                detail: 'Failed to refresh the page. Please reload manually.',
                                life: 5000,
                            });
                        }
                    });
                },
                onError: (errors) => {
                    console.error('❌ index.vue - Create failed:', errors);
                    
                    let errorMessage = 'Failed to create user';
                    if (errors && typeof errors === 'object') {
                        const errorMessages = [];
                        for (const [field, messages] of Object.entries(errors)) {
                            if (Array.isArray(messages)) {
                                errorMessages.push(`${field}: ${messages.join(', ')}`);
                            } else {
                                errorMessages.push(`${field}: ${messages}`);
                            }
                        }
                        if (errorMessages.length > 0) {
                            errorMessage = errorMessages.join('; ');
                        }
                    }
                    
                    toast.add({
                        severity: 'error',
                        summary: 'Error',
                        detail: errorMessage,
                        life: 5000,
                    });
                    
                    userFormDialog.value = true;
                },
                onFinish: () => {
                    console.log('🔄 index.vue - Create request finished');
                }
            });
        } else {
            router.post('/users', formData, {
                preserveScroll: true,
                onSuccess: () => {
                    console.log('✅ index.vue - Create successful');
                    toast.add({
                        severity: 'success',
                        summary: 'Success',
                        detail: 'User created successfully',
                        life: 3000,
                    });
                    router.reload({ 
                        preserveScroll: true,
                        onSuccess: () => {
                            console.log('✅ index.vue - Page reloaded successfully');
                        },
                        onError: (error) => {
                            console.error('❌ index.vue - Page reload failed:', error);
                            toast.add({
                                severity: 'error',
                                summary: 'Error',
                                detail: 'Failed to refresh the page. Please reload manually.',
                                life: 5000,
                            });
                        }
                    });
                },
                onError: (errors) => {
                    console.error('❌ index.vue - Create failed:', errors);
                    
                    let errorMessage = 'Failed to create user';
                    if (errors && typeof errors === 'object') {
                        const errorMessages = [];
                        for (const [field, messages] of Object.entries(errors)) {
                            if (Array.isArray(messages)) {
                                errorMessages.push(`${field}: ${messages.join(', ')}`);
                            } else {
                                errorMessages.push(`${field}: ${messages}`);
                            }
                        }
                        if (errorMessages.length > 0) {
                            errorMessage = errorMessages.join('; ');
                        }
                    }
                    
                    toast.add({
                        severity: 'error',
                        summary: 'Error',
                        detail: errorMessage,
                        life: 5000,
                    });
                    
                    userFormDialog.value = true;
                },
                onFinish: () => {
                    console.log('🔄 index.vue - Create request finished');
                }
            });
        }
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

// Watch for changes to allUsers
watch(() => props.allUsers, (newVal) => {
    console.log('🔄 Index - allUsers updated:', newVal?.length || 0);
    // console.log(props.allUsers.map(user => ({ can_be_signatory: user.can_be_signatory })));
}, { immediate: true });

// Mounted
onMounted(() => {
    // console.log('📊 Index mounted - allUsers count:', props.allUsers?.length || 0);
    // console.log('📊 Index mounted - users count:', props.allUsers);
    // console.log('📊 Index mounted - filters:', filters.value);
    // console.log('📊 Index mounted - allRoles count:', props.allRoles?.length || 0);
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

/* Passport Avatar Styles - Main Table */
.passport-avatar {
    width: 36px;
    height: 36px;
    border-radius: 50%;
    overflow: hidden;
    flex-shrink: 0;
    border: 2px solid var(--surface-border);
    background-color: var(--surface-ground);
}

.passport-image {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.avatar-placeholder {
    width: 36px;
    height: 36px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
    color: white;
    font-weight: 600;
    font-size: 14px;
}

.avatar-text {
    font-size: 14px;
    font-weight: 600;
    color: white;
}

/* Passport Avatar Styles - Signatory Modal & Roles Dialog */
.passport-avatar-small {
    width: 32px;
    height: 32px;
    border-radius: 50%;
    overflow: hidden;
    flex-shrink: 0;
    border: 2px solid var(--surface-border);
    background-color: var(--surface-ground);
}

.passport-image-small {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.avatar-placeholder-small {
    width: 32px;
    height: 32px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
    color: white;
    font-weight: 600;
    font-size: 12px;
}

.avatar-text-small {
    font-size: 12px;
    font-weight: 600;
    color: white;
}

/* ✅ Roles Column Styles */
.roles-display {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    padding: 2px 4px;
    border-radius: 4px;
    transition: background-color 0.2s;
}

.roles-display:hover {
    background-color: var(--surface-hover);
}

.roles-count-badge {
    font-size: 0.7rem;
    padding: 2px 6px;
    margin-left: 2px;
}

.roles-hint {
    opacity: 0.5;
    transition: opacity 0.2s;
}

.roles-display:hover .roles-hint {
    opacity: 1;
}

/* Roles Dialog Styles */
.roles-dialog {
    padding: 0.5rem 0;
}

.roles-dialog :deep(.p-divider) {
    margin: 1rem 0;
}

/* Signatory Modal Styles */
.signatory-management {
    padding: 0.5rem;
}

.signatory-management :deep(.p-datatable .p-datatable-thead > tr > th) {
    background: var(--surface-ground);
}

.signatory-management :deep(.p-togglebutton) {
    min-width: 100px;
}

:deep(.p-datatable .p-datatable-tbody > tr) {
    transition: background-color 0.2s;
}

:deep(.p-datatable .p-datatable-tbody > tr:hover) {
    background: var(--surface-hover);
}

.cursor-pointer {
    cursor: pointer;
}

.cursor-pointer:hover {
    opacity: 0.8;
}
</style>