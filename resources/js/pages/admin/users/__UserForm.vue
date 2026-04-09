<template>
    <form @submit.prevent="onSave">
        <div class="p-fluid grid">
            <div class="field col-12 md:col-6">
                <label for="name">Name</label>
                <InputText
                    id="name"
                    v-model="form.name"
                    :class="{ 'p-invalid': form.errors.name }"
                    required
                />
                <small class="p-error">{{ form.errors.name }}</small>
            </div>

            <div class="field col-12 md:col-6">
                <label for="email">Email</label>
                <InputText
                    id="email"
                    v-model="form.email"
                    :class="{ 'p-invalid': form.errors.email }"
                    required
                    type="email"
                />
                <small class="p-error">{{ form.errors.email }}</small>
            </div>

            <div v-if="!editMode" class="field col-12 md:col-6">
                <label for="password">Password</label>
                <Password
                    id="password"
                    v-model="form.password"
                    :class="{ 'p-invalid': form.errors.password }"
                    toggleMask
                    required
                />
                <small class="p-error">{{ form.errors.password }}</small>
            </div>
            <div v-if="!editMode" class="field col-12 md:col-6">
                <label for="password_confirmation">Confirm Password</label>
                <Password
                    id="password_confirmation"
                    v-model="form.password_confirmation"
                    toggleMask
                    required
                />
                <small class="p-error">{{
                    form.errors.password_confirmation
                }}</small>
            </div>
        </div>

        <div v-if="!editMode" class="mt-5">
            <Divider align="center">
                <span class="p-tag">Initial Assignments</span>
            </Divider>

            <div class="p-fluid grid">
                <div class="field align-items-center col-12 flex gap-3">
                    <ToggleSwitch
                        v-model="assignRolesPermissions"
                        inputId="assign-rp"
                    />
                    <label for="assign-rp" class="font-semibold"
                        >Assign Roles & Permissions Now</label
                    >
                </div>
            </div>

            <div v-if="assignRolesPermissions" class="card p-fluid mt-3 grid">
                <UserRolesPermissions
                    :user-data="null"
                    :all-roles="allRoles"
                    :all-permissions="allPermissions"
                    :form-mode="true"
                    v-model:modelValueRoles="form.selectedRoles"
                    v-model:modelValuePermissions="form.selectedPermissions"
                />
            </div>

            <div class="p-fluid mt-5 grid">
                <div class="field align-items-center col-12 flex gap-3">
                    <ToggleSwitch v-model="assignMdas" inputId="assign-mdas" />
                    <label for="assign-mdas" class="font-semibold"
                        >Assign MDA(s) Now</label
                    >
                </div>
            </div>

            <div v-if="assignMdas" class="card p-fluid mt-3 grid">
                <MdaAssignment
                    :all-mdas="allMdas"
                    v-model="form.selectedMdas"
                />
            </div>
        </div>

        <div class="justify-content-end mt-5 flex">
            <Button
                label="Cancel"
                severity="secondary"
                outlined
                @click="emit('cancel')"
                class="mr-2"
            />
            <Button
                :label="editMode ? 'Update User' : 'Create User'"
                type="submit"
                :loading="form.processing"
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
import ToggleSwitch from 'primevue/toggleswitch';
import { ref } from 'vue';

// Assuming you have these components/paths correctly aliased
import MdaAssignment from '@/pages/admin/users/MdaAssignment.vue';
import UserRolesPermissions from '@/pages/admin/users/UserRolesPermissions.vue';

const props = defineProps({
    user: Object,
    editMode: { type: Boolean, required: true },
    // These props are passed from Create.vue (which gets them from UsersController.php)
    allRoles: { type: Array, default: () => [] },
    allPermissions: { type: Array, default: () => [] },
    allMdas: { type: Array, default: () => [] },
});

const emit = defineEmits(['saved', 'cancel']);

// --- ASSIGNMENT TOGGLE STATE ---
const assignRolesPermissions = ref(false);
const assignMdas = ref(false);

// --- Form Data Refs ---
const form = useForm({
    name: props.user?.name || '',
    email: props.user?.email || '',
    password: '',
    password_confirmation: '',

    // V-MODEL TARGETS (for new user creation - empty arrays)
    selectedRoles: [],
    selectedPermissions: [],
    selectedMdas: [],
});

const onSave = () => {
    // Collect assignment data only if the toggles are ON
    const formData = {
        ...form.data(),
        roles: assignRolesPermissions.value ? form.selectedRoles : [],
        permissions: assignRolesPermissions.value
            ? form.selectedPermissions
            : [],
        mdas: assignMdas.value ? form.selectedMdas : [],
    };

    emit('saved', formData);
};
</script>
