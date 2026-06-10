<template>
    <form @submit.prevent="onSave">
        <div class="p-fluid grid">
            <div class="user-details-section col-12">
                <Divider align="left">
                    <b>User Details</b>
                </Divider>

                <div class="formgrid mb-4 grid">
                    <div class="justify-content-center col-12 flex">
                        <div
                            class="surface-ground border-round p-5 text-center"
                            style="width: 150px; height: 150px"
                        >
                            <p class="text-color-secondary mt-2 text-sm">
                                Upload Image
                            </p>
                        </div>
                    </div>
                </div>

                <div class="formgrid grid">
                    <div class="col-12 md:col-6">
                        <div class="flex-column flex gap-2">
                            <label for="name" class="font-semibold">Name</label>
                            <InputText
                                id="name"
                                v-model="form.name"
                                :class="{ 'p-invalid': form.errors.name }"
                                class="w-full"
                                required
                            />
                            <small class="p-error">{{
                                form.errors.name
                            }}</small>
                        </div>
                    </div>

                    <div class="col-12 md:col-6">
                        <div class="flex-column flex gap-2">
                            <label for="email" class="font-semibold"
                                >Email</label
                            >
                            <InputText
                                id="email"
                                v-model="form.email"
                                :class="{ 'p-invalid': form.errors.email }"
                                class="w-full"
                                required
                                type="email"
                            />
                            <small class="p-error">{{
                                form.errors.email
                            }}</small>
                        </div>
                    </div>

                    <div class="col-12 md:col-6">
                        <div class="flex-column flex gap-2">
                            <label for="password" class="font-semibold"
                                >Password</label
                            >
                            <Password
                                id="password"
                                v-model="form.password"
                                :class="{ 'p-invalid': form.errors.password }"
                                class="w-full"
                                toggleMask
                                required
                                :feedback="false"
                            />
                            <small class="p-error">{{
                                form.errors.password
                            }}</small>
                        </div>
                    </div>
                    <div class="col-12 md:col-6">
                        <div class="flex-column flex gap-2">
                            <label
                                for="password_confirmation"
                                class="font-semibold"
                                >Confirm Password</label
                            >
                            <Password
                                id="password_confirmation"
                                v-model="form.password_confirmation"
                                class="w-full"
                                toggleMask
                                required
                                :feedback="false"
                            />
                        </div>
                    </div>
                </div>
            </div>

            <!-- ASSIGNMENT TOGGLES SECTION - ONLY SHOW IN CREATE MODE -->
            <div v-if="!editMode" class="assignment-toggles-section col-12">
                <Divider align="left" type="dotted">
                    <b>Assignments (Optional)</b>
                </Divider>

                <div class="formgrid mt-3 grid">
                    <div class="col-12 md:col-6">
                        <div class="flex-column flex gap-2">
                            <label
                                for="assignRolesPermissions"
                                class="font-semibold"
                                >Assign Roles & Permissions</label
                            >
                            <ToggleButton
                                v-model="assignRolesPermissions"
                                onLabel="Roles & Permissions ON"
                                offLabel="Roles & Permissions OFF"
                                onIcon="pi pi-lock-open"
                                offIcon="pi pi-lock"
                                class="sm:w-10rem w-full"
                                aria-label="Toggle Roles and Permissions Assignment"
                            />
                            <small class="text-500"
                                >Toggle to assign roles and permissions to the
                                new user</small
                            >
                        </div>
                    </div>

                    <div class="col-12 md:col-6">
                        <div class="flex-column flex gap-2">
                            <label for="assignMdas" class="font-semibold"
                                >Assign MDA(s)</label
                            >
                            <ToggleButton
                                v-model="assignMdas"
                                onLabel="MDAs ON"
                                offLabel="MDAs OFF"
                                onIcon="pi pi-lock-open"
                                offIcon="pi pi-lock"
                                class="sm:w-10rem w-full"
                                aria-label="Toggle MDA Assignment"
                            />
                            <small class="text-500"
                                >Toggle to assign MDAs to the new user</small
                            >
                        </div>
                    </div>
                </div>
            </div>

            <!-- ROLES & PERMISSIONS SECTION - SHOW WHEN TOGGLED OR IN EDIT MODE -->
            <div
                v-if="assignRolesPermissions || editMode"
                class="assignment-section col-12"
                :class="{ 'mt-4': !editMode && assignRolesPermissions }"
            >
                <Divider align="left">
                    <b>Roles & Permissions Management</b>
                </Divider>
                <UserRolesPermissions
                    :user-data="user"
                    :all-roles="allRoles"
                    :all-permissions="allPermissions"
                    :form-mode="true"
                    v-model:model-value-roles="selectedRoles"
                    v-model:model-value-permissions="selectedPermissions"
                />
            </div>

            <!-- MDA ASSIGNMENT SECTION - SHOW WHEN TOGGLED OR IN EDIT MODE -->
            <div
                v-if="assignMdas || editMode"
                class="assignment-section col-12"
                :class="{ 'mt-4': !editMode && assignMdas }"
            >
                <Divider align="left">
                    <b>MDA Assignment</b>
                </Divider>

                <div class="p-fluid">
                    <label class="mb-2 block font-semibold"
                        >Select MDA(s) for User</label
                    >

                    <PickList
                        v-model="mdaList"
                        listStyle="height:300px"
                        dataKey="id"
                    >
                        <template #sourceheader>
                            Available MDA's ({{ mdaList[0]?.length || 0 }})
                        </template>
                        <template #targetheader>
                            Assigned MDA's ({{ mdaList[1]?.length || 0 }})
                        </template>
                        <template #item="slotProps">
                            <div class="flex-column flex">
                                <span class="font-medium">{{
                                    slotProps.item.name
                                }}</span>
                            </div>
                        </template>
                    </PickList>

                    <small class="p-error">{{ form.errors.mdas }}</small>
                </div>
            </div>
        </div>

        <div class="form-actions justify-content-end mt-4 flex gap-2">
            <Button
                label="Cancel"
                severity="secondary"
                icon="pi pi-times"
                @click="emit('cancel')"
                type="button"
            />
            <Button
                :label="editMode ? 'Update User' : 'Create User'"
                icon="pi pi-check"
                type="submit"
                :loading="form.processing"
                severity="success"
            />
        </div>
    </form>
</template>

<script setup>
import { useForm } from '@inertiajs/vue3';
import Button from 'primevue/button';
import Divider from 'primevue/divider';
import InputText from 'primevue/inputtext';
import Password from 'primevue/password';
import PickList from 'primevue/picklist';
import ToggleButton from 'primevue/togglebutton';
import { onMounted, ref, watch } from 'vue';
import UserRolesPermissions from './UserRolesPermissions.vue';

const props = defineProps({
    user: Object,
    editMode: { type: Boolean, required: true },
    allRoles: { type: Array, default: () => [] },
    allPermissions: { type: Array, default: () => [] },
    allMdas: { type: Array, default: () => [] },
});

const emit = defineEmits(['saved', 'cancel']);

// --- ASSIGNMENT TOGGLE STATE ---
// In create mode, start with toggles OFF. In edit mode, they're always ON.
const assignRolesPermissions = ref(props.editMode);
const assignMdas = ref(props.editMode);

// --- PICKLIST STATE ---
const mdaList = ref([[], []]);

// --- REACTIVE DATA FOR V-MODEL ---
const selectedRoles = ref([]);
const selectedPermissions = ref([]);

// --- FORM INITIALIZATION ---
const form = useForm({
    name: props.user?.name || '',
    email: props.user?.email || '',
    password: '',
    password_confirmation: '',
});

// --- INITIALIZATION FUNCTIONS ---
const initializeFormData = () => {
    console.log('ðŸ” UserForm - Initializing with user data:', props.user);
    console.log('ðŸ” UserForm - Edit mode:', props.editMode);
    console.log(
        'ðŸ” UserForm - Toggle states - Roles:',
        assignRolesPermissions.value,
        'MDAs:',
        assignMdas.value,
    );

    if (props.user) {
        form.name = props.user.name || '';
        form.email = props.user.email || '';
        selectedRoles.value = props.user.roles?.map((r) => r.name) || [];
        selectedPermissions.value =
            props.user.permissions?.map((p) => p.name) || [];
    }
};

const initializeMdas = () => {
    const assignedMdaIds = props.user?.mdas?.map((mda) => mda.id) || [];
    const source = props.allMdas.filter(
        (mda) => !assignedMdaIds.includes(mda.id),
    );
    const target = props.allMdas.filter((mda) =>
        assignedMdaIds.includes(mda.id),
    );
    mdaList.value = [source, target];
};

// Watch for PickList changes
watch(
    () => mdaList.value[1],
    (newTarget) => {
        console.log(
            'ðŸ” UserForm - MDA selection changed:',
            newTarget.map((m) => m.name),
        );
    },
    { deep: true },
);

// Watch toggle changes for debugging
watch([assignRolesPermissions, assignMdas], ([newRoles, newMdas]) => {
    console.log('ðŸ” UserForm - Toggle states changed:');
    console.log('  - Roles & Permissions:', newRoles);
    console.log('  - MDAs:', newMdas);
});

// Initialize component
onMounted(() => {
    console.log('ðŸ” UserForm mounted:');
    console.log('  - Edit Mode:', props.editMode);
    console.log(
        '  - Toggle states - Roles:',
        assignRolesPermissions.value,
        'MDAs:',
        assignMdas.value,
    );

    initializeFormData();
    initializeMdas();
});

// --- SAVE FUNCTION ---
const onSave = () => {
    console.log('ðŸ’¾ UserForm - Save button clicked!');
    console.log('ðŸ’¾ UserForm - Edit Mode:', props.editMode);
    console.log(
        'ðŸ’¾ UserForm - Toggle states - Roles:',
        assignRolesPermissions.value,
        'MDAs:',
        assignMdas.value,
    );

    const rolesToSubmit =
        assignRolesPermissions.value || props.editMode
            ? selectedRoles.value
            : [];
    const permissionsToSubmit =
        assignRolesPermissions.value || props.editMode
            ? selectedPermissions.value
            : [];
    const mdasToSubmit =
        assignMdas.value || props.editMode
            ? mdaList.value[1].map((mda) => mda.id)
            : [];

    const formData = {
        ...form.data(),
        roles: rolesToSubmit,
        permissions: permissionsToSubmit,
        mdas: mdasToSubmit,
    };

    // Handle password in edit mode
    if (props.editMode && !form.password) {
        delete formData.password;
        delete formData.password_confirmation;
    }

    console.log('ðŸ’¾ UserForm - Emitting saved event with data:', formData);
    emit('saved', formData);
};
</script>

<style scoped>
.assignment-toggles-section {
    background-color: var(--surface-ground);
    padding: 1rem;
    border-radius: 6px;
    border: 1px solid var(--surface-border);
}

.assignment-section {
    background-color: var(--surface-card);
    padding: 1rem;
    border-radius: 6px;
    border: 1px solid var(--surface-border);
    border-left: 4px solid var(--primary-color);
}

/* Add some visual separation when sections appear due to toggles */
.assignment-section.mt-4 {
    margin-top: 1rem;
}
</style>
ss
