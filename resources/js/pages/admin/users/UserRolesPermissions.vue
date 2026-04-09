<template>
    <div class="user-roles-permissions">
        <div v-if="props.userData && !formMode">
            <div class="mb-4">
                <h4>
                    Managing Roles & Permissions for: {{ props.userData.name }}
                </h4>
                <p class="text-color-secondary">{{ props.userData.email }}</p>
            </div>
        </div>

        <div class="grid">
            <div class="col-12 md:col-6">
                <Card>
                    <template #title>
                        <div
                            class="justify-content-between align-items-center flex"
                        >
                            <span>Roles Management</span>
                            <Button
                                v-if="!formMode"
                                label="Save Roles"
                                icon="pi pi-save"
                                @click="saveRoles"
                                :loading="savingRoles"
                                size="small"
                            />
                        </div>
                    </template>
                    <template #content>
                        <div class="mb-3">
                            <label class="mb-2 block font-semibold"
                                >Available Roles (Check to Assign/Uncheck to
                                Remove)</label
                            >
                            <div
                                class="surface-border border-round border-1 p-3"
                                style="max-height: 250px; overflow-y: auto"
                            >
                                <div
                                    v-for="role in allRoles || []"
                                    :key="role"
                                    class="mb-2"
                                >
                                    <div class="align-items-center flex">
                                        <Checkbox
                                            :id="`role_${role}`"
                                            v-model="selectedRoles"
                                            :value="role"
                                            :binary="false"
                                        />
                                        <label
                                            :for="`role_${role}`"
                                            class="ml-2 flex-1 cursor-pointer"
                                        >
                                            <div class="font-medium">
                                                {{ formatPermissionName(role) }}
                                            </div>
                                        </label>
                                    </div>
                                </div>
                                <p
                                    v-if="!allRoles || allRoles.length === 0"
                                    class="text-500 text-sm"
                                >
                                    No roles available in the system.
                                </p>
                            </div>
                            <small class="text-500 mt-1 block">
                                {{ selectedRoles.length }} role(s) currently
                                selected
                            </small>
                        </div>

                        <div v-if="!formMode && userData">
                            <Divider align="left">
                                <span class="p-tag p-tag-info"
                                    >Assigned & Inherited</span
                                >
                            </Divider>

                            <h6 class="mt-4 text-sm font-semibold">
                                Assigned Roles:
                            </h6>
                            <div class="flex flex-wrap gap-2">
                                <Tag
                                    v-for="role in userData.roles || []"
                                    :key="role.name"
                                    :value="formatPermissionName(role.name)"
                                    severity="info"
                                    class="text-xs"
                                />
                                <p
                                    v-if="
                                        !userData.roles ||
                                        userData.roles.length === 0
                                    "
                                    class="text-500 text-sm"
                                >
                                    No roles assigned.
                                </p>
                            </div>

                            <h6 class="mt-4 text-sm font-semibold">
                                All Effective Permissions:
                            </h6>
                            <div
                                class="surface-ground border-round p-2"
                                style="max-height: 150px; overflow-y: auto"
                            >
                                <Tag
                                    v-for="perm in effectivePermissions"
                                    :key="perm"
                                    :value="formatPermissionName(perm)"
                                    severity="success"
                                    class="mr-2 mb-2 text-xs"
                                />
                                <p
                                    v-if="effectivePermissions.length === 0"
                                    class="text-500 text-sm"
                                >
                                    No effective permissions found.
                                </p>
                            </div>
                        </div>
                    </template>
                </Card>
            </div>

            <div class="col-12 md:col-6">
                <Card>
                    <template #title>
                        <div
                            class="justify-content-between align-items-center flex"
                        >
                            <span>Direct Permissions</span>
                            <Button
                                v-if="!formMode"
                                label="Save Permissions"
                                icon="pi pi-save"
                                @click="savePermissions"
                                :loading="savingPermissions"
                                size="small"
                            />
                        </div>
                    </template>
                    <template #content>
                        <div class="mb-3">
                            <label class="mb-2 block font-semibold"
                                >Available Permissions (Check to Assign/Uncheck
                                to Remove)</label
                            >
                            <div
                                class="surface-border border-round border-1 p-3"
                                style="max-height: 250px; overflow-y: auto"
                            >
                                <div
                                    v-for="permission in allPermissions || []"
                                    :key="permission"
                                    class="mb-2"
                                >
                                    <div class="align-items-center flex">
                                        <Checkbox
                                            :id="`perm_${permission}`"
                                            v-model="selectedPermissions"
                                            :value="permission"
                                            :binary="false"
                                        />
                                        <label
                                            :for="`perm_${permission}`"
                                            class="ml-2 flex-1 cursor-pointer"
                                        >
                                            <div class="font-medium">
                                                {{
                                                    formatPermissionName(
                                                        permission,
                                                    )
                                                }}
                                            </div>
                                        </label>
                                    </div>
                                </div>
                                <p
                                    v-if="
                                        !allPermissions ||
                                        allPermissions.length === 0
                                    "
                                    class="text-500 text-sm"
                                >
                                    No permissions available in the system.
                                </p>
                            </div>
                            <small class="text-500 mt-1 block">
                                {{ selectedPermissions.length }} permission(s)
                                currently selected
                            </small>
                        </div>

                        <div v-if="!formMode && userData">
                            <Divider align="left">
                                <span class="p-tag p-tag-warning"
                                    >Currently Direct</span
                                >
                            </Divider>
                            <div
                                class="surface-ground border-round p-2"
                                style="max-height: 150px; overflow-y: auto"
                            >
                                <Tag
                                    v-for="perm in userData.permissions || []"
                                    :key="perm.name"
                                    :value="formatPermissionName(perm.name)"
                                    severity="warning"
                                    class="mr-2 mb-2 text-xs"
                                />
                                <p
                                    v-if="
                                        !userData.permissions ||
                                        userData.permissions.length === 0
                                    "
                                    class="text-500 text-sm"
                                >
                                    No direct permissions assigned.
                                </p>
                            </div>
                        </div>
                    </template>
                </Card>
            </div>
        </div>
    </div>
</template>

<script setup>
import { useForm } from '@inertiajs/vue3';
import Button from 'primevue/button';
import Card from 'primevue/card';
import Checkbox from 'primevue/checkbox';
import Divider from 'primevue/divider';
import Tag from 'primevue/tag';
import { useToast } from 'primevue/usetoast';
import { computed, onMounted, ref, watch } from 'vue';

const props = defineProps({
    userData: Object,
    allRoles: { type: Array, required: true },
    allPermissions: { type: Array, required: true },
    effectivePermissionsList: { type: Array, default: () => [] },
    formMode: { type: Boolean, default: false },
    modelValueRoles: { type: Array, default: () => [] },
    modelValuePermissions: { type: Array, default: () => [] },
});

const emit = defineEmits([
    'saved',
    'update:modelValueRoles',
    'update:modelValuePermissions',
]);

const toast = useToast();
const selectedRoles = ref([]);
const selectedPermissions = ref([]);
const savingRoles = ref(false);
const savingPermissions = ref(false);

// Forms with ONLY roles/permissions data - no user profile fields
const rolesForm = useForm({
    roles: [],
    // NO name, email, password fields here
});

const permissionsForm = useForm({
    permissions: [],
    // NO name, email, password fields here
});

// Initialize component data
const initializeData = () => {
    if (props.formMode) {
        selectedRoles.value = [...props.modelValueRoles];
        selectedPermissions.value = [...props.modelValuePermissions];
    } else if (props.userData) {
        selectedRoles.value =
            props.userData.roles?.map((role) => role.name) || [];
        selectedPermissions.value =
            props.userData.permissions?.map((perm) => perm.name) || [];
    }
};

// Watchers (keep your existing watchers)
watch(
    selectedRoles,
    (newRoles) => {
        if (props.formMode) {
            emit('update:modelValueRoles', [...newRoles]);
        }
    },
    { deep: true },
);

watch(
    selectedPermissions,
    (newPermissions) => {
        if (props.formMode) {
            emit('update:modelValuePermissions', [...newPermissions]);
        }
    },
    { deep: true },
);

watch(
    () => props.modelValueRoles,
    (newRoles) => {
        if (
            props.formMode &&
            JSON.stringify(newRoles) !== JSON.stringify(selectedRoles.value)
        ) {
            selectedRoles.value = [...newRoles];
        }
    },
    { deep: true },
);

watch(
    () => props.modelValuePermissions,
    (newPermissions) => {
        if (
            props.formMode &&
            JSON.stringify(newPermissions) !==
                JSON.stringify(selectedPermissions.value)
        ) {
            selectedPermissions.value = [...newPermissions];
        }
    },
    { deep: true },
);

// Initialize on component mount
onMounted(() => {
    initializeData();
});

// --- COMPUTED / FUNCTIONS ---
const effectivePermissions = computed(() => {
    return (
        props.effectivePermissionsList || props.userData?.allPermissions || []
    );
});

const formatPermissionName = (name) => {
    if (typeof name !== 'string' || !name) return '';
    return name
        .split('.')
        .map((word) => word.charAt(0).toUpperCase() + word.slice(1))
        .join(' ');
};

// --- FIXED SAVE METHODS ---
const saveRoles = async () => {
    console.log("about ot save roles");
    if (props.formMode || !props.userData?.id) {
        return;
    }

    // Only set roles data
    rolesForm.roles = selectedRoles.value;
    savingRoles.value = true;

    try {
        // await rolesForm.post(route('userz.roles.update', props.userData.id), {
        await rolesForm.post(`/users/${props.userData.id}/roles`, {
            preserveScroll: true,
            preserveState: true,
            onSuccess: () => {
                toast.add({
                    severity: 'success',
                    summary: 'Success',
                    detail: 'User roles updated successfully',
                    life: 3000,
                });
                emit('saved', 'roles');
            },
            onError: (errors) => {
                console.error('Roles update error:', errors);
                toast.add({
                    severity: 'error',
                    summary: 'Error',
                    detail: 'Failed to update roles. Please check console for details.',
                    life: 5000,
                });
            },
            onFinish: () => {
                savingRoles.value = false;
            },
        });
    } catch (error) {
        console.error('Save roles exception:', error);
        savingRoles.value = false;
        toast.add({
            severity: 'error',
            summary: 'Error',
            detail: 'Failed to update roles: ' + error.message,
            life: 5000,
        });
    }
};

const savePermissions = async () => {
    if (props.formMode || !props.userData?.id) {
        return;
    }

    // Only set permissions data
    permissionsForm.permissions = selectedPermissions.value;
    savingPermissions.value = true;

    try {
        await permissionsForm.post(
            `/users/${props.userData.id}/permissions`,
            {
                preserveScroll: true,
                preserveState: true,
                onSuccess: () => {
                    toast.add({
                        severity: 'success',
                        summary: 'Success',
                        detail: 'User permissions updated successfully',
                        life: 3000,
                    });
                    emit('saved', 'permissions');
                },
                onError: (errors) => {
                    console.error('Permissions update error:', errors);
                    toast.add({
                        severity: 'error',
                        summary: 'Error',
                        detail: 'Failed to update permissions. Please check console for details.',
                        life: 5000,
                    });
                },
                onFinish: () => {
                    savingPermissions.value = false;
                },
            },
        );
    } catch (error) {
        console.error('Save permissions exception:', error);
        savingPermissions.value = false;
        toast.add({
            severity: 'error',
            summary: 'Error',
            detail: 'Failed to update permissions: ' + error.message,
            life: 5000,
        });
    }
};
</script>