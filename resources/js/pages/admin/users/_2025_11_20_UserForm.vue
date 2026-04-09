<!-- <template>
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

                    <div v-if="!editMode" class="col-12 md:col-6">
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
                    <div v-if="!editMode" class="col-12 md:col-6">
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
                                onLabel="Roles ON"
                                offLabel="Roles OFF"
                                onIcon="pi pi-lock-open"
                                offIcon="pi pi-lock"
                                class="sm:w-10rem w-full"
                                aria-label="Toggle Roles and Permissions Assignment"
                            />
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
                        </div>
                    </div>
                </div>
            </div>

            <div
                v-if="assignRolesPermissions || editMode"
                class="assignment-section col-12"
            >
                <Divider align="left">
                    <b>Roles & Permissions Management</b>
                </Divider>
                <UserRolesPermissions
                    :user-data="user"
                    :all-roles="allRoles"
                    :all-permissions="allPermissions"
                    form-mode
                    v-model:model-value-roles="form.selectedRoles"
                    v-model:model-value-permissions="form.selectedPermissions"
                />
            </div>

            <div
                v-if="assignMdas || editMode"
                class="assignment-section col-12"
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
                        <template #sourceheader> Available MDA's </template>
                        <template #targetheader>
                            Assigned MDA's ({{ form.selectedMdas.length }})
                        </template>
                        <template #item="slotProps">
                            {{ slotProps.item.name }}
                        </template>
                    </PickList>

                    <small class="p-error">{{
                        form.errors.selectedMdas
                    }}</small>
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
</template> -->

<!-- <script setup>
import { useForm } from '@inertiajs/vue3';
import { onMounted, ref, watch } from 'vue';
// PrimeVue Components
import Button from 'primevue/button';
import Divider from 'primevue/divider';
import InputText from 'primevue/inputtext';
import Password from 'primevue/password';
import PickList from 'primevue/picklist';
import ToggleButton from 'primevue/togglebutton';
// Local Components
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
const assignRolesPermissions = ref(false);
const assignMdas = ref(false);

// --- PICKLIST STATE ---
const mdaList = ref([[], []]);

// --- FORM DATA REFS ---
const form = useForm({
    name: props.user?.name || '',
    email: props.user?.email || '',
    password: '',
    password_confirmation: '',

    // ✅ SOLUTION: Initialize with existing user data if available
    selectedRoles: props.user?.roles?.map((r) => r.name) || [],
    selectedPermissions: props.user?.permissions?.map((p) => p.name) || [],
    selectedMdas: props.user?.mdas?.map((m) => m.id) || [],
});

// --- INITIALIZATION & WATCHERS ---
const initializeMdas = () => {
    const assignedIds = props.user?.selected_mdas || [];
    const target = props.allMdas.filter((mda) => assignedIds.includes(mda.id));
    const source = props.allMdas.filter((mda) => !assignedIds.includes(mda.id));
    mdaList.value = [source, target];
    form.selectedMdas = target.map((mda) => mda.id);
};

watch(
    () => mdaList.value[1],
    (newTarget) => {
        form.selectedMdas = newTarget.map((mda) => mda.id);
    },
    { deep: true },
);

onMounted(() => {
    initializeMdas();

    if (props.editMode) {
        assignRolesPermissions.value = true;
        assignMdas.value = true;

        if (props.user) {
            form.selectedRoles = props.user.roles?.map((r) => r.name) || [];
            form.selectedPermissions =
                props.user.permissions?.map((p) => p.name) || [];
        }
    }
});

// --- SAVE FUNCTION ---
const onSave = () => {
    // 1. Determine which data to submit based on toggles/mode
    // The conditional logic here handles both Create (toggles) and Edit (props.editMode)
    const rolesToSubmit =
        assignRolesPermissions.value || props.editMode
            ? form.selectedRoles
            : [];
    const permissionsToSubmit =
        assignRolesPermissions.value || props.editMode
            ? form.selectedPermissions
            : [];
    const mdasToSubmit =
        assignMdas.value || props.editMode ? form.selectedMdas : [];

    // 2. Get all data from the form object, including internal v-model keys
    const formData = form.data();

    // 3. Clean up internal V-model keys and set final server-expected keys
    // This removes the duplicate/unwanted keys from the final payload
    delete formData.selectedRoles;
    delete formData.selectedPermissions;
    delete formData.selectedMdas;

    // This sets the final, clean arrays for the server
    formData.roles = rolesToSubmit;
    formData.permissions = permissionsToSubmit;
    formData.mdas = mdasToSubmit; // Assuming the server expects 'mdas'

    // 4. Handle Password Deletion (Crucial for Edit mode)
    if (props.editMode && !form.password) {
        delete formData.password;
        delete formData.password_confirmation;
    }

    emit('saved', formData);
};
</script> -->

<!-- <script setup>
import { useForm } from '@inertiajs/vue3';
import Button from 'primevue/button';
import Divider from 'primevue/divider';
import InputText from 'primevue/inputtext';
import Password from 'primevue/password';
import PickList from 'primevue/picklist';
import { onMounted, ref, watch } from 'vue';
import UserRolesPermissions from './UserRolesPermissions.vue'; // Adjust path if necessary

const props = defineProps({
    user: Object,
    editMode: { type: Boolean, required: true },
    allRoles: { type: Array, default: () => [] },
    allPermissions: { type: Array, default: () => [] },
    allMdas: { type: Array, default: () => [] }, // List of all MDAs (id, name)
});

const emit = defineEmits(['saved', 'cancel']);

// --- ASSIGNMENT TOGGLE STATE (for Create Mode) ---
const assignRolesPermissions = ref(false);
const assignMdas = ref(false);

// --- PICKLIST STATE ---
// mdaList will hold [[source list], [target list]]
const mdaList = ref([[], []]);

// --- FORM DATA REFS (FIXED INITIALIZATION) ---
const form = useForm({
    name: props.user?.name || '',
    email: props.user?.email || '',
    password: '',
    password_confirmation: '',

    // 🔥 FIX 1: Load existing assignments into form object in edit mode
    selectedRoles: props.user?.roles?.map((r) => r.name) || [],
    selectedPermissions: props.user?.permissions?.map((p) => p.name) || [],
    selectedMdas: [], // PickList will initialize this below, keep empty for now
});

// --- INITIALIZATION ---

const initializeMdas = () => {
    // 🔥 FIX 2: Correctly map the array of MDA objects (props.user.mdas) to an array of IDs
    // const assignedIds = props.user?.mdas?.map((mda) => mda.id) || [];
    const assignedIds = props.user?.mdas?.map((mda) => mda.id) || [];

    // 2. Separate all MDAs into source and target lists
    const target = props.allMdas.filter((mda) => assignedIds.includes(mda.id));
    const source = props.allMdas.filter((mda) => !assignedIds.includes(mda.id));

    mdaList.value = [source, target];

    // 3. Sync the initial target IDs to the form data
    form.selectedMdas = target.map((mda) => mda.id);
};

// --- WATCHERS ---

// Watch the target list of the PickList (mdaList[1]) and update the form data array for submission
watch(
    () => mdaList.value[1],
    (newTarget) => {
        // Sync the list of IDs for submission
        form.selectedMdas = newTarget.map((mda) => mda.id);
    },
    { deep: true },
);

// Initialize MDA lists on component mount
onMounted(() => {
    initializeMdas();

    // If in edit mode, initialize toggles to true so the assignment sections show immediately
    if (props.editMode) {
        assignRolesPermissions.value = true;
        assignMdas.value = true;
    }
});

// --- SAVE FUNCTION (FIXED PAYLOAD KEY) ---

const onSave = () => {
    // Collect assignment data only if the toggles are ON (in Create Mode) or if in Edit Mode
    const rolesToSubmit =
        assignRolesPermissions.value || props.editMode
            ? form.selectedRoles
            : [];
    const permissionsToSubmit =
        assignRolesPermissions.value || props.editMode
            ? form.selectedPermissions
            : [];
    const mdasToSubmit =
        assignMdas.value || props.editMode ? form.selectedMdas : [];

    const formData = form.data();

    // Remove the temporary v-model keys
    delete formData.selectedRoles;
    delete formData.selectedPermissions;
    delete formData.selectedMdas; // Remove old key

    // 🔥 FIX 3: Assign the collected arrays to the keys the backend expects
    formData.roles = rolesToSubmit;
    formData.permissions = permissionsToSubmit;
    formData.mdas = mdasToSubmit; // Use the correct key 'mdas'

    // Remove empty passwords if in edit mode and password fields are untouched
    if (props.editMode && !form.password) {
        delete formData.password;
        delete formData.password_confirmation;
    }

    emit('saved', formData);
};
</script> -->

<!-- <script setup>
import { useForm } from '@inertiajs/vue3';
import Button from 'primevue/button';
import Divider from 'primevue/divider';
import InputText from 'primevue/inputtext';
import Password from 'primevue/password';
import PickList from 'primevue/picklist';
import { onMounted, ref, watch } from 'vue';
import UserRolesPermissions from './UserRolesPermissions.vue'; // Adjust path if necessary

const props = defineProps({
    user: Object,
    editMode: { type: Boolean, required: true },
    allRoles: { type: Array, default: () => [] },
    allPermissions: { type: Array, default: () => [] },
    allMdas: { type: Array, default: () => [] }, // List of all MDAs (id, name)
});

const emit = defineEmits(['saved', 'cancel']);

// --- ASSIGNMENT TOGGLE STATE (for Create Mode) ---
const assignRolesPermissions = ref(false);
const assignMdas = ref(false);

// --- PICKLIST STATE ---
const mdaList = ref([[], []]);

// --- FORM DATA REFS (🔥 ALL INITIALIZATION FIXES ARE HERE) ---
// const form = useForm({
//     // FIX 1: Basic fields are initialized from props.user
//     name: props.user?.name || '',
//     email: props.user?.email || '',
//     password: '',
//     password_confirmation: '',

//     // FIX 2: Selected Roles are initialized from props.user.roles
//     // Must map the array of role objects to an array of role names
//     selectedRoles: props.user?.roles?.map((r) => r.name) || [],

//     // FIX 3: Selected Permissions are initialized from props.user.permissions
//     // Must map the array of permission objects to an array of permission names
//     selectedPermissions: props.user?.permissions?.map((p) => p.name) || [],

//     // PickList initialization will handle this field
//     selectedMdas: [],
// });
// const form = useForm({
//     name: props.user?.name || '',
//     email: props.user?.email || '',
//     password: '',
//     password_confirmation: '',

//     // ⭐ FIX: Initialize selectedRoles and selectedPermissions from existing user data in edit mode
//     selectedRoles: props.user?.roles?.map((r) => r.name) || [],
//     selectedPermissions: props.user?.permissions?.map((p) => p.name) || [],
//     selectedMdas: props.user?.mdas?.map((m) => m.id) || [],
// });

const form = useForm({
    name: props.user?.name || '',
    email: props.user?.email || '',
    password: '',
    password_confirmation: '',

    // ⭐ FIX: Initialize V-MODEL TARGETS with existing user data in edit mode
    // Map role objects to an array of names (e.g., ['admin', 'editor'])
    selectedRoles: props.user?.roles?.map((r) => r.name) || [],
    // Map permission objects to an array of names (e.g., ['users.view', 'posts.create'])
    selectedPermissions: props.user?.permissions?.map((p) => p.name) || [],
    // Map MDA objects to an array of IDs (e.g., [1, 5, 8])
    selectedMdas: props.user?.mdas?.map((m) => m.id) || [],
});

// --- INITIALIZATION ---

const initializeMdas = () => {
    // 1. Get the list of IDs assigned to the user (if in edit mode)
    // 🔥 FIX 4: The correct key from your resource log is 'mdas', NOT 'selected_mdas'
    const assignedIds = props.user?.mdas?.map((mda) => mda.id) || [];

    // 2. Separate all MDAs into source and target lists
    const target = props.allMdas.filter((mda) => assignedIds.includes(mda.id));
    const source = props.allMdas.filter((mda) => !assignedIds.includes(mda.id));

    mdaList.value = [source, target];

    // 3. Sync the initial target IDs to the form data
    form.selectedMdas = target.map((mda) => mda.id);
};

// --- WATCHERS ---

// Watch the target list of the PickList (mdaList[1]) and update the form data array for submission
watch(
    () => mdaList.value[1],
    (newTarget) => {
        // Sync the list of IDs for submission
        form.selectedMdas = newTarget.map((mda) => mda.id);
    },
    { deep: true },
);

// Initialize MDA lists on component mount
onMounted(() => {
    initializeMdas();

    // If in edit mode, initialize toggles to true so the assignment sections show immediately
    if (props.editMode) {
        assignRolesPermissions.value = true;
        assignMdas.value = true;
    }
});

// --- SAVE FUNCTION ---

const onSave = () => {
    // Collect assignment data only if the toggles are ON (in Create Mode) or if in Edit Mode
    const rolesToSubmit =
        assignRolesPermissions.value || props.editMode
            ? form.selectedRoles
            : [];
    const permissionsToSubmit =
        assignRolesPermissions.value || props.editMode
            ? form.selectedPermissions
            : [];
    const mdasToSubmit =
        assignMdas.value || props.editMode ? form.selectedMdas : [];

    // Use spread operator to get current form data and then override the array keys
    const formData = {
        ...form.data(),
        roles: rolesToSubmit, // Use key the server expects
        permissions: permissionsToSubmit, // Use key the server expects
        mdas: mdasToSubmit, // Use key the server expects (mdas, not selectedMdas)
    };

    // Explicitly clean up the internal v-model keys to avoid submitting duplicates
    delete formData.selectedRoles;
    delete formData.selectedPermissions;
    delete formData.selectedMdas;

    // Remove empty passwords if in edit mode and password fields are untouched
    if (props.editMode && !form.password) {
        delete formData.password;
        delete formData.password_confirmation;
    }

    emit('saved', formData);
};
</script> -->

<!-- <script setup>
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
const assignRolesPermissions = ref(false);
const assignMdas = ref(false);

// --- PICKLIST STATE ---
const mdaList = ref([[], []]);

// --- FORM INITIALIZATION ---
// Initialize form with proper data structure
const initializeForm = () => {
    const formData = {
        name: props.user?.name || '',
        email: props.user?.email || '',
        password: '',
        password_confirmation: '',
        selectedRoles: props.user?.roles?.map((r) => r.name) || [],
        selectedPermissions: props.user?.permissions?.map((p) => p.name) || [],
        selectedMdas: props.user?.mdas?.map((m) => m.id) || [],
    };

    return useForm(formData);
};

const form = initializeForm();

// --- INITIALIZATION FUNCTIONS ---
const initializeMdas = () => {
    const assignedIds = props.user?.mdas?.map((mda) => mda.id) || [];
    const target = props.allMdas.filter((mda) => assignedIds.includes(mda.id));
    const source = props.allMdas.filter((mda) => !assignedIds.includes(mda.id));

    mdaList.value = [source, target];
    form.selectedMdas = target.map((mda) => mda.id);
};

// Watch for PickList changes
watch(
    () => mdaList.value[1],
    (newTarget) => {
        form.selectedMdas = newTarget.map((mda) => mda.id);
    },
    { deep: true },
);

// Initialize component
onMounted(() => {
    console.log('UserForm mounted with user data:', props.user);
    console.log('Roles:', props.user?.roles);
    console.log('Permissions:', props.user?.permissions);
    console.log('MDAs:', props.user?.mdas);

    initializeMdas();

    // Show assignment sections in edit mode
    if (props.editMode) {
        assignRolesPermissions.value = true;
        assignMdas.value = true;

        // Ensure form data is properly set for edit mode
        if (props.user) {
            form.name = props.user.name || '';
            form.email = props.user.email || '';
            form.selectedRoles = props.user.roles?.map((r) => r.name) || [];
            form.selectedPermissions =
                props.user.permissions?.map((p) => p.name) || [];
            form.selectedMdas = props.user.mdas?.map((m) => m.id) || [];
        }
    }
});

// --- SAVE FUNCTION ---
const onSave = () => {
    console.log('Form data before processing:', form.data());

    const rolesToSubmit =
        assignRolesPermissions.value || props.editMode
            ? form.selectedRoles
            : [];
    const permissionsToSubmit =
        assignRolesPermissions.value || props.editMode
            ? form.selectedPermissions
            : [];
    const mdasToSubmit =
        assignMdas.value || props.editMode ? form.selectedMdas : [];

    const formData = {
        ...form.data(),
        roles: rolesToSubmit,
        permissions: permissionsToSubmit,
        mdas: mdasToSubmit,
    };

    // Clean up internal keys
    delete formData.selectedRoles;
    delete formData.selectedPermissions;
    delete formData.selectedMdas;

    // Handle password in edit mode
    if (props.editMode && !form.password) {
        delete formData.password;
        delete formData.password_confirmation;
    }

    console.log('Form data being submitted:', formData);
    emit('saved', formData);
};
</script> -->

<!-- <script setup>
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
const assignRolesPermissions = ref(false);
const assignMdas = ref(false);

// --- PICKLIST STATE ---
const mdaList = ref([[], []]);

// --- FORM INITIALIZATION ---
// Initialize form with proper data structure
const initializeForm = () => {
    const formData = {
        name: props.user?.name || '',
        email: props.user?.email || '',
        password: '',
        password_confirmation: '',
        selectedRoles: props.user?.roles?.map((r) => r.name) || [],
        selectedPermissions: props.user?.permissions?.map((p) => p.name) || [],
        selectedMdas: props.user?.mdas?.map((m) => m.id) || [],
    };

    return useForm(formData);
};

const form = initializeForm();

// --- INITIALIZATION FUNCTIONS ---
const initializeMdas = () => {
    const assignedIds = props.user?.mdas?.map((mda) => mda.id) || [];
    const target = props.allMdas.filter((mda) => assignedIds.includes(mda.id));
    const source = props.allMdas.filter((mda) => !assignedIds.includes(mda.id));

    mdaList.value = [source, target];
    form.selectedMdas = target.map((mda) => mda.id);
};

// Watch for PickList changes
watch(
    () => mdaList.value[1],
    (newTarget) => {
        form.selectedMdas = newTarget.map((mda) => mda.id);
    },
    { deep: true },
);

// Initialize component
onMounted(() => {
    console.log('UserForm mounted with user data:', props.user);
    console.log('Roles:', props.user?.roles);
    console.log('Permissions:', props.user?.permissions);
    console.log('MDAs:', props.user?.mdas);

    initializeMdas();

    // Show assignment sections in edit mode
    if (props.editMode) {
        assignRolesPermissions.value = true;
        assignMdas.value = true;

        // Ensure form data is properly set for edit mode
        if (props.user) {
            form.name = props.user.name || '';
            form.email = props.user.email || '';
            form.selectedRoles = props.user.roles?.map((r) => r.name) || [];
            form.selectedPermissions =
                props.user.permissions?.map((p) => p.name) || [];
            form.selectedMdas = props.user.mdas?.map((m) => m.id) || [];
        }
    }
});

// --- SAVE FUNCTION ---
const onSave = () => {
    console.log('Form data before processing:', form.data());

    const rolesToSubmit =
        assignRolesPermissions.value || props.editMode
            ? form.selectedRoles
            : [];
    const permissionsToSubmit =
        assignRolesPermissions.value || props.editMode
            ? form.selectedPermissions
            : [];
    const mdasToSubmit =
        assignMdas.value || props.editMode ? form.selectedMdas : [];

    const formData = {
        ...form.data(),
        roles: rolesToSubmit,
        permissions: permissionsToSubmit,
        mdas: mdasToSubmit,
    };

    // Clean up internal keys
    delete formData.selectedRoles;
    delete formData.selectedPermissions;
    delete formData.selectedMdas;

    // Handle password in edit mode
    if (props.editMode && !form.password) {
        delete formData.password;
        delete formData.password_confirmation;
    }

    console.log('Form data being submitted:', formData);
    emit('saved', formData);
};
</script> -->

<!-- <script setup>
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
const assignRolesPermissions = ref(false);
const assignMdas = ref(false);

// --- PICKLIST STATE ---
const mdaList = ref([[], []]);

// --- FORM INITIALIZATION ---
const form = useForm({
    name: props.user?.name || '',
    email: props.user?.email || '',
    password: '',
    password_confirmation: '',
    selectedRoles: [],
    selectedPermissions: [],
    selectedMdas: [],
});

// --- INITIALIZATION FUNCTIONS ---
const initializeFormData = () => {
    console.log('Initializing form with user data:', props.user);

    if (props.user) {
        // Basic user info
        form.name = props.user.name || '';
        form.email = props.user.email || '';

        // Roles and permissions
        form.selectedRoles = props.user.roles?.map((r) => r.name) || [];
        form.selectedPermissions =
            props.user.permissions?.map((p) => p.name) || [];
        form.selectedMdas = props.user.mdas?.map((m) => m.id) || [];

        console.log('Form initialized with:', {
            roles: form.selectedRoles,
            permissions: form.selectedPermissions,
            mdas: form.selectedMdas,
        });
    }
};

const initializeMdas = () => {
    const assignedIds = props.user?.mdas?.map((mda) => mda.id) || [];
    const target = props.allMdas.filter((mda) => assignedIds.includes(mda.id));
    const source = props.allMdas.filter((mda) => !assignedIds.includes(mda.id));

    mdaList.value = [source, target];
    form.selectedMdas = target.map((mda) => mda.id);
};

// Watch for PickList changes
watch(
    () => mdaList.value[1],
    (newTarget) => {
        form.selectedMdas = newTarget.map((mda) => mda.id);
    },
    { deep: true },
);

// Initialize component
onMounted(() => {
    console.log('UserForm mounted - Edit Mode:', props.editMode);
    console.log('User data received:', props.user);

    // Initialize form data
    initializeFormData();
    initializeMdas();

    // Show assignment sections in edit mode
    if (props.editMode) {
        assignRolesPermissions.value = true;
        assignMdas.value = true;
    }
});

// --- SAVE FUNCTION ---
const onSave = () => {
    console.log('Form data before processing:', form.data());

    const rolesToSubmit =
        assignRolesPermissions.value || props.editMode
            ? form.selectedRoles
            : [];
    const permissionsToSubmit =
        assignRolesPermissions.value || props.editMode
            ? form.selectedPermissions
            : [];
    const mdasToSubmit =
        assignMdas.value || props.editMode ? form.selectedMdas : [];

    const formData = {
        ...form.data(),
        roles: rolesToSubmit,
        permissions: permissionsToSubmit,
        mdas: mdasToSubmit,
    };

    // Clean up internal keys
    delete formData.selectedRoles;
    delete formData.selectedPermissions;
    delete formData.selectedMdas;

    // Handle password in edit mode
    if (props.editMode && !form.password) {
        delete formData.password;
        delete formData.password_confirmation;
    }

    console.log('Form data being submitted:', formData);
    emit('saved', formData);
};
</script> -->

<!-- <template>
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

                    <div v-if="!editMode" class="col-12 md:col-6">
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
                    <div v-if="!editMode" class="col-12 md:col-6">
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
                                onLabel="Roles ON"
                                offLabel="Roles OFF"
                                onIcon="pi pi-lock-open"
                                offIcon="pi pi-lock"
                                class="sm:w-10rem w-full"
                                aria-label="Toggle Roles and Permissions Assignment"
                            />
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
                        </div>
                    </div>
                </div>
            </div>

            <div
                v-if="assignRolesPermissions || editMode"
                class="assignment-section col-12"
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

            <div
                v-if="assignMdas || editMode"
                class="assignment-section col-12"
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
                        <template #sourceheader> Available MDA's </template>
                        <template #targetheader>
                            Assigned MDA's ({{ mdaList[1]?.length || 0 }})
                        </template>
                        <template #item="slotProps">
                            {{ slotProps.item.name }}
                        </template>
                    </PickList>

                    <small class="p-error">{{
                        form.errors.selectedMdas
                    }}</small>
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
</template> -->

<!-- <script setup>
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
const assignRolesPermissions = ref(false);
const assignMdas = ref(false);

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
    console.log('UserForm - Initializing with user data:', props.user);

    if (props.user) {
        // Basic user info
        form.name = props.user.name || '';
        form.email = props.user.email || '';

        // Initialize v-model data for roles and permissions
        selectedRoles.value = props.user.roles?.map((r) => r.name) || [];
        selectedPermissions.value =
            props.user.permissions?.map((p) => p.name) || [];

        console.log('UserForm - Initialized v-model data:', {
            roles: selectedRoles.value,
            permissions: selectedPermissions.value,
        });
    }
};

const initializeMdas = () => {
    const assignedIds = props.user?.mdas?.map((mda) => mda.id) || [];
    const target = props.allMdas.filter((mda) => assignedIds.includes(mda.id));
    const source = props.allMdas.filter((mda) => !assignedIds.includes(mda.id));

    mdaList.value = [source, target];
    form.selectedMdas = target.map((mda) => mda.id);
};

// Watch for PickList changes
watch(
    () => mdaList.value[1],
    (newTarget) => {
        form.selectedMdas = newTarget.map((mda) => mda.id);
    },
    { deep: true },
);

// Watch for changes in roles and permissions and update form for submission
watch(
    [selectedRoles, selectedPermissions],
    ([newRoles, newPermissions]) => {
        console.log('UserForm - v-model data changed:', {
            roles: newRoles,
            permissions: newPermissions,
        });
    },
    { deep: true },
);

// Initialize component
onMounted(() => {
    console.log('UserForm mounted - Edit Mode:', props.editMode);
    console.log('User data received:', props.user);

    // Initialize form data
    initializeFormData();
    initializeMdas();

    // Show assignment sections in edit mode
    if (props.editMode) {
        assignRolesPermissions.value = true;
        assignMdas.value = true;
    }
});

// --- SAVE FUNCTION ---
const onSave = () => {
    console.log('UserForm - Saving data:', {
        selectedRoles: selectedRoles.value,
        selectedPermissions: selectedPermissions.value,
        formData: form.data(),
    });

    const rolesToSubmit =
        assignRolesPermissions.value || props.editMode
            ? selectedRoles.value
            : [];
    const permissionsToSubmit =
        assignRolesPermissions.value || props.editMode
            ? selectedPermissions.value
            : [];
    const mdasToSubmit =
        assignMdas.value || props.editMode ? form.selectedMdas : [];

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

    console.log('UserForm - Final form data being submitted:', formData);
    emit('saved', formData);
};
</script> -->

<!-- <script setup>
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
const assignRolesPermissions = ref(false);
const assignMdas = ref(false);

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
    console.log('UserForm - Initializing with user data:', props.user);

    if (props.user) {
        // Basic user info
        form.name = props.user.name || '';
        form.email = props.user.email || '';

        // Initialize v-model data for roles and permissions
        selectedRoles.value = props.user.roles?.map((r) => r.name) || [];
        selectedPermissions.value =
            props.user.permissions?.map((p) => p.name) || [];

        console.log('UserForm - Initialized v-model data:', {
            roles: selectedRoles.value,
            permissions: selectedPermissions.value,
            userRoles: props.user.roles,
            userPermissions: props.user.permissions,
        });
    }
};

const initializeMdas = () => {
    const assignedIds = props.user?.mdas?.map((mda) => mda.id) || [];
    const target = props.allMdas.filter((mda) => assignedIds.includes(mda.id));
    const source = props.allMdas.filter((mda) => !assignedIds.includes(mda.id));

    mdaList.value = [source, target];

    console.log('UserForm - MDA initialization:', {
        assignedIds: assignedIds,
        target: target,
        source: source,
        mdaList: mdaList.value,
    });
};

// Watch for PickList changes
watch(
    () => mdaList.value[1],
    (newTarget) => {
        console.log('UserForm - MDA selection changed:', newTarget);
    },
    { deep: true },
);

// Initialize component
onMounted(() => {
    console.log('UserForm mounted - Edit Mode:', props.editMode);
    console.log('User data received:', props.user);
    console.log('All Roles:', props.allRoles);
    console.log('All Permissions:', props.allPermissions);
    console.log('All MDAs:', props.allMdas);

    // Initialize form data
    initializeFormData();
    initializeMdas();

    // Show assignment sections in edit mode
    if (props.editMode) {
        assignRolesPermissions.value = true;
        assignMdas.value = true;
    }
});

// --- SAVE FUNCTION ---
const onSave = () => {
    console.log('UserForm - Saving data:', {
        selectedRoles: selectedRoles.value,
        selectedPermissions: selectedPermissions.value,
        formData: form.data(),
    });

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

    console.log('UserForm - Final form data being submitted:', formData);
    emit('saved', formData);
};
</script> -->

<!-- <template>
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

                    <div v-if="!editMode" class="col-12 md:col-6">
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
                    <div v-if="!editMode" class="col-12 md:col-6">
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
                                onLabel="Roles ON"
                                offLabel="Roles OFF"
                                onIcon="pi pi-lock-open"
                                offIcon="pi pi-lock"
                                class="sm:w-10rem w-full"
                                aria-label="Toggle Roles and Permissions Assignment"
                            />
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
                        </div>
                    </div>
                </div>
            </div>

            <div
                v-if="assignRolesPermissions || editMode"
                class="assignment-section col-12"
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

            <div
                v-if="assignMdas || editMode"
                class="assignment-section col-12"
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
</template> -->

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
                                :feedback="false"
                            />
                            <small class="p-error">{{
                                form.errors.password
                            }}</small>
                            <small class="text-500" v-if="editMode"
                                >Leave blank to keep current password</small
                            >
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
                                :feedback="false"
                            />
                        </div>
                    </div>
                </div>
            </div>

            <div
                v-if="assignRolesPermissions || editMode"
                class="assignment-section col-12"
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

            <div
                v-if="assignMdas || editMode"
                class="assignment-section col-12"
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

        <!-- DEBUG BUTTONS - Temporary for testing -->
        <!-- <div class="border-round surface-ground mb-3 border-1 p-3">
            <h4>Debug Buttons</h4>
            <div class="flex gap-2">
                <Button
                    label="Test Button"
                    icon="pi pi-bug"
                    severity="secondary"
                    @click="testButton"
                />
                <Button
                    label="Force Save"
                    icon="pi pi-save"
                    severity="warning"
                    @click="onSave"
                />
            </div>
        </div> -->

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
        <!-- Add this after your form fields -->
        <!-- <div
            v-if="form.hasErrors"
            class="border-round surface-red mb-4 border-1 p-3"
        >
            <h4 class="mb-2 text-red-500">Form Errors:</h4>
            <ul class="ml-4 list-disc">
                <li
                    v-for="error in form.errors"
                    :key="error"
                    class="text-red-500"
                >
                    {{ error }}
                </li>
            </ul>
        </div> -->
    </form>
</template>

<script setup>
import { useForm } from '@inertiajs/vue3';
import Button from 'primevue/button';
import Divider from 'primevue/divider';
import InputText from 'primevue/inputtext';
import Password from 'primevue/password';
import PickList from 'primevue/picklist';
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
const assignRolesPermissions = ref(false);
const assignMdas = ref(false);

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
    console.log('UserForm - Initializing with user data:', props.user);

    if (props.user) {
        // Basic user info
        form.name = props.user.name || '';
        form.email = props.user.email || '';

        // Initialize v-model data for roles and permissions
        selectedRoles.value = props.user.roles?.map((r) => r.name) || [];
        selectedPermissions.value =
            props.user.permissions?.map((p) => p.name) || [];

        console.log('UserForm - Initialized v-model data:', {
            roles: selectedRoles.value,
            permissions: selectedPermissions.value,
        });
    }
};

const initializeMdas = () => {
    console.log('UserForm - Initializing MDAs:', {
        allMdas: props.allMdas,
        userMdas: props.user?.mdas,
    });

    if (!props.allMdas || props.allMdas.length === 0) {
        console.warn('UserForm - No MDAs available to initialize');
        mdaList.value = [[], []];
        return;
    }

    // Get the IDs of MDAs assigned to the user
    const assignedMdaIds = props.user?.mdas?.map((mda) => mda.id) || [];

    console.log('UserForm - Assigned MDA IDs:', assignedMdaIds);

    // Separate all MDAs into source (available) and target (assigned)
    const source = props.allMdas.filter(
        (mda) => !assignedMdaIds.includes(mda.id),
    );
    const target = props.allMdas.filter((mda) =>
        assignedMdaIds.includes(mda.id),
    );

    console.log('UserForm - MDA separation:', {
        source: source,
        target: target,
    });

    mdaList.value = [source, target];
};

// Watch for PickList changes
watch(
    () => mdaList.value[1],
    (newTarget) => {
        console.log('UserForm - MDA selection changed:', newTarget);
    },
    { deep: true },
);

// Initialize component
onMounted(() => {
    console.log('UserForm mounted - Edit Mode:', props.editMode);
    console.log('User data received:', props.user);
    console.log('All Roles:', props.allRoles);
    console.log('All Permissions:', props.allPermissions);
    console.log('All MDAs:', props.allMdas);

    // Initialize form data
    initializeFormData();
    initializeMdas();

    // Show assignment sections in edit mode
    if (props.editMode) {
        assignRolesPermissions.value = true;
        assignMdas.value = true;
    }
});

// --- SAVE FUNCTION ---
// const onSave = () => {
//     console.log('UserForm - Saving data:', {
//         selectedRoles: selectedRoles.value,
//         selectedPermissions: selectedPermissions.value,
//         mdaList: mdaList.value,
//     });

//     const rolesToSubmit =
//         assignRolesPermissions.value || props.editMode
//             ? selectedRoles.value
//             : [];
//     const permissionsToSubmit =
//         assignRolesPermissions.value || props.editMode
//             ? selectedPermissions.value
//             : [];

//     // Get MDA IDs from the target list (second array in mdaList)
//     const mdasToSubmit =
//         assignMdas.value || props.editMode
//             ? mdaList.value[1].map((mda) => mda.id)
//             : [];

//     const formData = {
//         ...form.data(),
//         roles: rolesToSubmit,
//         permissions: permissionsToSubmit,
//         mdas: mdasToSubmit,
//     };

//     // Handle password in edit mode
//     if (props.editMode && !form.password) {
//         delete formData.password;
//         delete formData.password_confirmation;
//     }

//     console.log('UserForm - Final form data being submitted:', formData);
//     emit('saved', formData);
// };
// const onSaved = (formData) => {
//     console.log('💾 Frontend - Sending data to backend:', formData);

//     if (editMode.value) {
//         // Make sure we're using the correct user ID and method
//         console.log('💾 Frontend - Updating user ID:', props.user.id);

//         router.put(`/users/${props.user.id}`, formData, {
//             preserveScroll: true,
//             onSuccess: () => {
//                 console.log('✅ Frontend - Update successful');
//                 router.reload({
//                     preserveScroll: true,
//                     onSuccess: () => {
//                         toast.add({
//                             severity: 'success',
//                             summary: 'Success',
//                             detail: 'User updated successfully',
//                             life: 3000,
//                         });
//                     },
//                 });
//             },
//             onError: (errors) => {
//                 console.error('❌ Frontend - Update failed:', errors);
//                 toast.add({
//                     severity: 'error',
//                     summary: 'Error',
//                     detail:
//                         'Failed to update user: ' +
//                         (errors.message || 'Unknown error'),
//                     life: 5000,
//                 });
//             },
//         });
//     } else {
//         router.post('/users', formData, {
//             preserveScroll: true,
//             onSuccess: () => {
//                 console.log('✅ Frontend - Create successful');
//                 router.reload({
//                     preserveScroll: true,
//                     onSuccess: () => {
//                         toast.add({
//                             severity: 'success',
//                             summary: 'Success',
//                             detail: 'User created successfully',
//                             life: 3000,
//                         });
//                     },
//                 });
//             },
//             onError: (errors) => {
//                 console.error('❌ Frontend - Create failed:', errors);
//                 toast.add({
//                     severity: 'error',
//                     summary: 'Error',
//                     detail:
//                         'Failed to create user: ' +
//                         (errors.message || 'Unknown error'),
//                     life: 5000,
//                 });
//             },
//         });
//     }
// };

// --- SAVE FUNCTION ---
const onSave = () => {
    console.log('💾 UserForm - Save button clicked!');
    console.log('💾 UserForm - Edit Mode:', props.editMode);
    console.log('💾 UserForm - Form data:', form.data());
    console.log('💾 UserForm - Selected Roles:', selectedRoles.value);
    console.log(
        '💾 UserForm - Selected Permissions:',
        selectedPermissions.value,
    );
    console.log(
        '💾 UserForm - MDAs:',
        mdaList.value[1].map((m) => m.id),
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

    if (props.editMode && !form.password) {
        delete formData.password;
        delete formData.password_confirmation;
    }

    console.log('💾 UserForm - Emitting saved event with data:', formData);
    emit('saved', formData);
};

// Add a test function to check if the button is working
const testButton = () => {
    console.log('✅ Test button clicked!');
};
</script>

<style scoped>
/* Minor structural/spacing adjustments */
.col-12.user-details-section,
.col-12.assignment-toggles-section,
.col-12.assignment-section {
    padding-top: 1rem;
    padding-bottom: 0.5rem;
}

.form-actions {
    margin-top: 1.5rem;
}

/* Note on PrimeVue/PrimeFlex styling: 
The .p-fluid and grid/formgrid classes are responsible for most of the layout.
The explicit use of 'flex flex-column gap-2' inside the column containers achieves the desired vertical field stacking (label over input).
*/
</style>
