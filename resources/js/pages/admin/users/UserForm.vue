<script setup>
import { useForm } from '@inertiajs/vue3';
import Button from 'primevue/button';
import Divider from 'primevue/divider';
import InputText from 'primevue/inputtext';
import Password from 'primevue/password';
import PickList from 'primevue/picklist';
import ToggleButton from 'primevue/togglebutton';
import { onMounted, ref, watch, computed } from 'vue';
import Dropdown from 'primevue/dropdown';
import FileUpload from 'primevue/fileupload';
import UserRolesPermissions from './UserRolesPermissions.vue';
import Badge from 'primevue/badge';

const props = defineProps({
    user: Object,
    editMode: { type: Boolean, required: true },
    allRoles: { type: Array, default: () => [] },
    allPermissions: { type: Array, default: () => [] },
    allMdas: { type: Array, default: () => [] },
    allUserCategories: { type: Array, default: () => [] },
    allUsers: { type: Array, default: () => [] },
});

const emit = defineEmits(['saved', 'cancel']);

// --- ASSIGNMENT TOGGLE STATE ---
const assignRolesPermissions = ref(props.editMode);
const assignMdas = ref(props.editMode);

// --- PICKLIST STATE ---
const mdaList = ref([[], []]);

// --- REACTIVE DATA FOR V-MODEL ---
const selectedRoles = ref([]);
const selectedPermissions = ref([]);

// --- SIGNATURE AND PASSPORT SELECTION ---
const signatureFile = ref(null);
const signaturePreview = ref(null);
const signatureExists = ref(false);
const passportFile = ref(null);
const passportPreview = ref(null);
const passportExists = ref(false);

// --- FORM INITIALIZATION ---
const form = useForm({
    name: props.user?.name || '',
    email: props.user?.email || '',
    password: '',
    password_confirmation: '',
    user_category_id: props.user?.user_category_id || null,
    can_be_signatory: props.user?.can_be_signatory || false,
    signatory_id: props.user?.signatory_id || null,
    signature: null,
    passport: null,
});

// --- HELPER METHODS ---
const getUserName = (userId) => {
    if (!userId) return '';
    const user = props.allUsers?.find(u => u.id === userId);
    return user?.name || 'Unknown User';
};

const getUserEmail = (userId) => {
    if (!userId) return '';
    const user = props.allUsers?.find(u => u.id === userId);
    return user?.email || '';
};

// --- COMPUTED: Check if signature is required based on category name ---
const requiresSignature = computed(() => {
    const categoryId = form.user_category_id;
    if (!categoryId) return false;
    
    const category = props.allUserCategories?.find(c => c.id === categoryId);
    if (!category) return false;
    
    const signatureRequiredCategories = [
        'director of finance', 
        'internal audit', 
        'management account section', 
        'treasury cash office'
    ];
    return signatureRequiredCategories.includes(category.name.toLowerCase());
});

// --- COMPUTED: Get available signatories from the database ---
const availableSignatories = computed(() => {
    if (!props.allUsers || props.allUsers.length === 0) {
        return [];
    }
    
    const signatories = props.allUsers.filter(user => {
        if (props.editMode && user.id === props.user?.id) return false;
        return user.can_be_signatory === true;
    });
    
    return signatories;
});

// --- COMPUTED: Get current user category name ---
const currentUserCategory = computed(() => {
    if (!form.user_category_id) return null;
    return props.allUserCategories?.find(c => c.id === form.user_category_id);
});

// --- COMPUTED: Get full URL for storage paths ---
const getStorageUrl = (path) => {
    if (!path) return null;
    // If it's already a full URL, return it
    if (path.startsWith('http')) return path;
    // If it starts with 'storage/', return it as is
    if (path.startsWith('storage/')) return '/' + path;
    // Otherwise, prepend '/storage/'
    return '/storage/' + path;
};

// --- INITIALIZATION FUNCTIONS ---
const initializeFormData = () => {
    if (props.user) {
        form.name = props.user.name || '';
        form.email = props.user.email || '';
        form.user_category_id = props.user.user_category_id || null;
        form.signatory_id = props.user.signatory_id || null;
        form.can_be_signatory = props.user.can_be_signatory || false;
        
        selectedRoles.value = props.user.roles?.map((r) => r.name) || [];
        selectedPermissions.value = props.user.permissions?.map((p) => p.name) || [];
        
        // ✅ FIX: Handle signature preview
        if (props.user.signature) {
            const signaturePath = getStorageUrl(props.user.signature);
            signaturePreview.value = signaturePath;
            signatureExists.value = true;
            console.log('📸 Signature loaded:', signaturePath);
        } else {
            signaturePreview.value = null;
            signatureExists.value = false;
        }
        
        // ✅ FIX: Handle passport preview
        if (props.user.passport) {
            const passportPath = getStorageUrl(props.user.passport);
            passportPreview.value = passportPath;
            passportExists.value = true;
            console.log('📸 Passport loaded:', passportPath);
        } else {
            passportPreview.value = null;
            passportExists.value = false;
        }
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

// --- PASSPORT FILE HANDLING ---
const handlePassportUpload = (event) => {
    const file = event.files[0];
    if (!file) return;
    
    const validTypes = ['image/jpeg', 'image/png', 'image/gif'];
    if (!validTypes.includes(file.type)) {
        form.errors.passport = 'Please upload a valid image file (JPEG, PNG, or GIF)';
        return;
    }
    
    const maxSize = 2 * 1024 * 1024;
    if (file.size > maxSize) {
        form.errors.passport = 'File size must be less than 2MB';
        return;
    }
    
    form.errors.passport = null;
    passportFile.value = file;
    form.passport = file;
    passportExists.value = false; // New file uploaded, so existing is replaced
    
    const reader = new FileReader();
    reader.onload = (e) => {
        passportPreview.value = e.target.result;
    };
    reader.readAsDataURL(file);
};

const removePassport = () => {
    passportFile.value = null;
    form.passport = null;
    passportPreview.value = null;
    passportExists.value = false;
    form.errors.passport = null;
};

// --- SIGNATURE FILE HANDLING ---
const handleSignatureUpload = (event) => {
    const file = event.files[0];
    if (!file) return;
    
    const validTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/svg+xml'];
    if (!validTypes.includes(file.type)) {
        form.errors.signature = 'Please upload a valid image file (JPEG, PNG, GIF, or SVG)';
        return;
    }
    
    const maxSize = 2 * 1024 * 1024;
    if (file.size > maxSize) {
        form.errors.signature = 'File size must be less than 2MB';
        return;
    }
    
    form.errors.signature = null;
    signatureFile.value = file;
    form.signature = file;
    signatureExists.value = false; // New file uploaded, so existing is replaced
    
    const reader = new FileReader();
    reader.onload = (e) => {
        signaturePreview.value = e.target.result;
    };
    reader.readAsDataURL(file);
};

const removeSignature = () => {
    signatureFile.value = null;
    form.signature = null;
    signaturePreview.value = null;
    signatureExists.value = false;
    form.errors.signature = null;
};

// --- WATCHERS ---
watch(() => form.user_category_id, (newCategoryId) => {
    if (!requiresSignature.value) {
        form.signatory_id = null;
        form.errors.signatory_id = null;
        // Don't auto-remove signature when category changes
        // User might want to keep it
    }
});

// --- SAVE FUNCTION ---
// const onSave = () => {
//     // Validate signature if required
//     if (requiresSignature.value) {
//         if (!form.signatory_id) {
//             form.errors.signatory_id = 'Please select a signatory for this user category';
//             return;
//         }
//         // Check if signature exists either as new file or existing
//         if (!form.signature && !signatureExists.value) {
//             form.errors.signature = 'Please upload a signature image for this user';
//             return;
//         }
//     }

//     const rolesToSubmit = (assignRolesPermissions.value || props.editMode) ? selectedRoles.value : [];
//     const permissionsToSubmit = (assignRolesPermissions.value || props.editMode) ? selectedPermissions.value : [];
//     const mdasToSubmit = (assignMdas.value || props.editMode) ? mdaList.value[1].map((mda) => mda.id) : [];

//     // Check if we have new files to upload
//     const hasFiles = form.signature instanceof File || form.passport instanceof File;

//     if (hasFiles) {
//         // Use FormData for file uploads
//         const formData = new FormData();

//         // Add text fields
//         formData.append('name', form.name);
//         formData.append('email', form.email);
//         formData.append('user_category_id', form.user_category_id || '');
//         formData.append('signatory_id', form.signatory_id || '');
//         formData.append('can_be_signatory', form.can_be_signatory ? '1' : '0');

//         // Add arrays as multiple fields with same name
//         rolesToSubmit.forEach(role => {
//             formData.append('roles[]', role);
//         });

//         permissionsToSubmit.forEach(permission => {
//             formData.append('permissions[]', permission);
//         });

//         mdasToSubmit.forEach(mda => {
//             formData.append('mdas[]', mda);
//         });

//         // Add _method for edit mode
//         if (props.editMode) {
//             formData.append('_method', 'PUT');
//         }

//         // Add password if provided
//         if (form.password) {
//             formData.append('password', form.password);
//             formData.append('password_confirmation', form.password_confirmation);
//         }

//         // Add files if they exist (new uploads)
//         if (form.signature instanceof File) {
//             formData.append('signature', form.signature);
//         }

//         if (form.passport instanceof File) {
//             formData.append('passport', form.passport);
//         }

//         emit('saved', formData);
//     } else {
//         // No new files to upload, use regular object
//         const formData = {
//             name: form.name,
//             email: form.email,
//             user_category_id: form.user_category_id || '',
//             signatory_id: form.signatory_id || '',
//             can_be_signatory: form.can_be_signatory,
//             roles: rolesToSubmit,
//             permissions: permissionsToSubmit,
//             mdas: mdasToSubmit,
//             // Don't send signature/passport if they haven't changed
//             // This tells the backend to keep existing files
//         };

//         // Handle password
//         if (form.password) {
//             formData.password = form.password;
//             formData.password_confirmation = form.password_confirmation;
//         }

//         emit('saved', formData);
//     }
// };

// --- SAVE FUNCTION ---
const onSave = () => {
    console.log('💾 UserForm - Save button clicked!');
    console.log('💾 UserForm - Edit Mode:', props.editMode);
    console.log('💾 UserForm - Form values:', {
        name: form.name,
        email: form.email,
        user_category_id: form.user_category_id,
        signatory_id: form.signatory_id,
        can_be_signatory: form.can_be_signatory,
    });

    // Validate signature if required
    if (requiresSignature.value) {
        if (!form.signatory_id) {
            form.errors.signatory_id = 'Please select a signatory for this user category';
            return;
        }
        if (!form.signature && !signatureExists.value) {
            form.errors.signature = 'Please upload a signature image for this user';
            return;
        }
    }

    const rolesToSubmit = (assignRolesPermissions.value || props.editMode) ? selectedRoles.value : [];
    const permissionsToSubmit = (assignRolesPermissions.value || props.editMode) ? selectedPermissions.value : [];
    const mdasToSubmit = (assignMdas.value || props.editMode) ? mdaList.value[1].map((mda) => mda.id) : [];

    // Check if we have new files to upload
    const hasFiles = form.signature instanceof File || form.passport instanceof File;
    
    if (hasFiles) {
        // Use FormData for file uploads
        const formData = new FormData();
        
        // ✅ IMPORTANT: Make sure name and email are not empty
        const nameValue = form.name?.trim() || '';
        const emailValue = form.email?.trim() || '';
        
        // Add text fields - ensure they're not empty
        formData.append('name', nameValue);
        formData.append('email', emailValue);
        formData.append('user_category_id', form.user_category_id || '');
        formData.append('signatory_id', form.signatory_id || '');
        formData.append('can_be_signatory', form.can_be_signatory ? '1' : '0');
        
        // Add arrays as multiple fields with same name
        rolesToSubmit.forEach(role => {
            formData.append('roles[]', role);
        });
        
        permissionsToSubmit.forEach(permission => {
            formData.append('permissions[]', permission);
        });
        
        mdasToSubmit.forEach(mda => {
            formData.append('mdas[]', mda);
        });
        
        // Add _method for edit mode
        if (props.editMode) {
            formData.append('_method', 'PUT');
        }
        
        // Add password if provided
        if (form.password) {
            formData.append('password', form.password);
            formData.append('password_confirmation', form.password_confirmation);
        }
        
        // Add files if they exist (new uploads)
        if (form.signature instanceof File) {
            formData.append('signature', form.signature);
        }
        
        if (form.passport instanceof File) {
            formData.append('passport', form.passport);
        }

        // ✅ Log FormData contents for debugging
        console.log('💾 UserForm - FormData contents:');
        for (let pair of formData.entries()) {
            console.log(pair[0] + ': ' + (pair[1] instanceof File ? pair[1].name : pair[1]));
        }

        emit('saved', formData);
    } else {
        // No new files to upload, use regular object
        const formData = {
            name: form.name?.trim() || '',
            email: form.email?.trim() || '',
            user_category_id: form.user_category_id || '',
            signatory_id: form.signatory_id || '',
            can_be_signatory: form.can_be_signatory,
            roles: rolesToSubmit,
            permissions: permissionsToSubmit,
            mdas: mdasToSubmit,
        };

        // Handle password
        if (form.password) {
            formData.password = form.password;
            formData.password_confirmation = form.password_confirmation;
        }

        console.log('💾 UserForm - Emitting saved event with data:', formData);
        emit('saved', formData);
    }
};

// --- LIFECYCLE HOOKS ---
onMounted(() => {
    console.log('📸 UserForm mounted - User data:', props.user);
    initializeFormData();
    initializeMdas();
});
</script>

<template>
    <form @submit.prevent="onSave">
        <div class="p-fluid grid">
            <div class="user-details-section col-12">
                <Divider align="left">
                    <b>User Details</b>
                </Divider>

                <div class="formgrid mb-4 grid">
                    <!-- Passport Upload Section -->
                    <div class="justify-content-center col-12 flex">
                        <div class="passport-upload-container text-center">
                            <!-- Show existing passport or preview -->
                            <div v-if="passportPreview" class="passport-preview">
                                <img :src="passportPreview" alt="Passport Photo" class="passport-image" />
                                <div class="passport-status">
                                    <Badge 
                                        v-if="passportExists && !passportFile"
                                        value="Existing"
                                        severity="info"
                                        class="status-badge"
                                    />
                                    <Badge 
                                        v-if="passportFile"
                                        value="New"
                                        severity="success"
                                        class="status-badge"
                                    />
                                </div>
                                <Button
                                    icon="pi pi-times"
                                    severity="danger"
                                    size="small"
                                    rounded
                                    outlined
                                    class="remove-passport-btn"
                                    @click="removePassport"
                                    type="button"
                                    title="Remove passport"
                                />
                            </div>
                            
                            <!-- Upload area -->
                            <div v-else class="passport-upload-area">
                                <FileUpload
                                    mode="basic"
                                    accept="image/*"
                                    :maxFileSize="2097152"
                                    chooseLabel="Upload Passport"
                                    icon="pi pi-upload"
                                    @uploader="handlePassportUpload"
                                    @select="handlePassportUpload"
                                    :auto="true"
                                    customUpload
                                    class="w-full"
                                />
                                <small class="text-500 mt-2 block">
                                    Upload passport photo (JPEG, PNG, GIF)
                                </small>
                                <small class="text-500 block">
                                    Max file size: 2MB
                                </small>
                                <small v-if="form.errors.passport" class="p-error block">
                                    {{ form.errors.passport }}
                                </small>
                            </div>
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
                            <small class="p-error">{{ form.errors.name }}</small>
                        </div>
                    </div>

                    <div class="col-12 md:col-6">
                        <div class="flex-column flex gap-2">
                            <label for="email" class="font-semibold">Email</label>
                            <InputText
                                id="email"
                                v-model="form.email"
                                :class="{ 'p-invalid': form.errors.email }"
                                class="w-full"
                                required
                                type="email"
                            />
                            <small class="p-error">{{ form.errors.email }}</small>
                        </div>
                    </div>

                    <div class="col-12 md:col-6">
                        <div class="flex-column flex gap-2">
                            <label for="password" class="font-semibold">Password</label>
                            <Password
                                id="password"
                                v-model="form.password"
                                :class="{ 'p-invalid': form.errors.password }"
                                class="w-full"
                                toggleMask
                                :required="!editMode"
                                :feedback="false"
                            />
                            <small class="p-error">{{ form.errors.password }}</small>
                            <small v-if="editMode" class="text-500">
                                Leave blank to keep current password
                            </small>
                        </div>
                    </div>

                    <div class="col-12 md:col-6">
                        <div class="flex-column flex gap-2">
                            <label for="password_confirmation" class="font-semibold">Confirm Password</label>
                            <Password
                                id="password_confirmation"
                                v-model="form.password_confirmation"
                                class="w-full"
                                toggleMask
                                :required="!editMode"
                                :feedback="false"
                            />
                        </div>
                    </div>

                    <!-- User Category -->
                    <div class="col-12 md:col-12">
                        <div class="flex-column flex gap-2">
                            <label for="user_category" class="font-semibold">User Category</label>
                            <Dropdown
                                id="user_category"
                                v-model="form.user_category_id"
                                :options="allUserCategories"
                                optionLabel="name"
                                optionValue="id"
                                placeholder="Select a Category"
                                class="w-full"
                                filter
                            />
                            <small v-if="currentUserCategory" class="text-500">
                                Current Category: <strong>{{ currentUserCategory.name }}</strong>
                            </small>
                        </div>
                    </div>

                    <!-- Signatory Selection -->
                    <div v-if="requiresSignature" class="col-12">
                        <Divider align="left" type="dotted">
                            <b>Signatory Assignment <span class="text-red-500">*</span></b>
                        </Divider>
                        
                        <div class="grid">
                            <!-- Can Be Signatory Toggle -->
                            <div class="col-12 md:col-4">
                                <div class="flex-column flex gap-2">
                                    <label for="canBeSignatory" class="font-semibold">
                                        Can Be Signatory
                                        <i class="pi pi-info-circle text-500" 
                                           >
                                        </i>
                                    </label>
                                    <ToggleButton
                                        v-model="form.can_be_signatory"
                                        onLabel="Yes"
                                        offLabel="No"
                                        onIcon="pi pi-check"
                                        offIcon="pi pi-times"
                                        class="w-full sm:w-10rem"
                                        aria-label="Toggle signatory capability"
                                        title="Enable this to allow the user to be selected as a signatory for other users"
                                    />
                                    <small class="text-500">
                                        When enabled, this user will appear in the signatory selection list for other users
                                    </small>
                                </div>
                            </div>

                            <!-- Select Signatory Dropdown -->
                            <div class="col-12 md:col-4">
                                <div class="flex-column flex gap-2">
                                    <label for="signatory" class="font-semibold">
                                        Select Signatory
                                        <span class="text-red-500">*</span>
                                    </label>
                                    <Dropdown
                                        id="signatory"
                                        v-model="form.signatory_id"
                                        :options="availableSignatories"
                                        optionLabel="name"
                                        optionValue="id"
                                        placeholder="Select a signatory for this user"
                                        class="w-full"
                                        filter
                                        :class="{ 'p-invalid': form.errors.signatory_id }"
                                    >
                                        <!-- Value Template - Shows selected signatory -->
                                        <template #value="slotProps">
                                            <div v-if="slotProps.value" class="flex flex-column">
                                                <span class="font-medium">{{ getUserName(slotProps.value) }}</span>
                                                <small class="text-color-secondary">{{ getUserEmail(slotProps.value) }}</small>
                                            </div>
                                            <span v-else class="text-color-secondary">Select a signatory...</span>
                                        </template>
                                        
                                        <!-- Option Template - Shows each option in dropdown -->
                                        <template #option="slotProps">
                                            <div class="flex flex-column">
                                                <div class="flex align-items-center gap-2">
                                                    <span class="font-medium">{{ slotProps.option.name }}</span>
                                                    <Badge 
                                                        v-if="slotProps.option.can_be_signatory"
                                                        value="Signatory"
                                                        severity="success"
                                                        size="small"
                                                    />
                                                </div>
                                                <small class="text-color-secondary">{{ slotProps.option.email }}</small>
                                                <small v-if="slotProps.option.user_category" class="text-color-secondary">
                                                    Category: {{ slotProps.option.user_category.name }}
                                                </small>
                                            </div>
                                        </template>
                                    </Dropdown>
                                    
                                    <small class="text-500">
                                        Available signatories: <strong>{{ availableSignatories.length }}</strong>
                                        <span v-if="availableSignatories.length === 0" class="text-red-500">
                                            (No users are marked as signatories yet)
                                        </span>
                                    </small>
                                    
                                    <small class="text-500">
                                        This user will be able to sign documents on behalf of the 
                                        <strong>{{ 
                                            allUserCategories?.find(c => c.id === form.user_category_id)?.name 
                                        }}</strong> department
                                    </small>
                                    <small v-if="form.errors.signatory_id" class="p-error">
                                        {{ form.errors.signatory_id }}
                                    </small>
                                </div>
                            </div>

                            <!-- Signature Upload -->
                            <div class="col-12 md:col-4">
                                <div class="flex-column flex gap-2">
                                    <label for="signature" class="font-semibold">
                                        Signature Image
                                        <span class="text-red-500">*</span>
                                    </label>
                                    
                                    <div class="signature-upload-container">
                                        <!-- Show existing signature or preview -->
                                        <div v-if="signaturePreview" class="signature-preview">
                                            <img :src="signaturePreview" alt="Signature Preview" class="signature-image" />
                                            <div class="signature-status">
                                                <Badge 
                                                    v-if="signatureExists && !signatureFile"
                                                    value="Existing"
                                                    severity="info"
                                                    class="status-badge"
                                                />
                                                <Badge 
                                                    v-if="signatureFile"
                                                    value="New"
                                                    severity="success"
                                                    class="status-badge"
                                                />
                                            </div>
                                            <Button
                                                icon="pi pi-times"
                                                severity="danger"
                                                size="small"
                                                rounded
                                                outlined
                                                class="remove-signature-btn"
                                                @click="removeSignature"
                                                type="button"
                                                v-tooltip="'Remove signature'"
                                            />
                                        </div>
                                        
                                        <!-- Upload area -->
                                        <div v-else class="upload-area">
                                            <FileUpload
                                                mode="basic"
                                                accept="image/*"
                                                :maxFileSize="2097152"
                                                chooseLabel="Upload Signature"
                                                icon="pi pi-upload"
                                                @uploader="handleSignatureUpload"
                                                @select="handleSignatureUpload"
                                                :auto="true"
                                                customUpload
                                                class="w-full"
                                            />
                                            <small class="text-500">
                                                Upload a clear image of the signature (JPEG, PNG, GIF, SVG)
                                            </small>
                                            <small class="text-500">
                                                Max file size: 2MB
                                            </small>
                                        </div>
                                        
                                        <small v-if="form.errors.signature" class="p-error">
                                            {{ form.errors.signature }}
                                        </small>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ASSIGNMENT TOGGLES SECTION -->
            <div v-if="!editMode" class="assignment-toggles-section col-12">
                <Divider align="left" type="dotted">
                    <b>Assignments (Optional)</b>
                </Divider>

                <div class="formgrid mt-3 grid">
                    <div class="col-12 md:col-6">
                        <div class="flex-column flex gap-2">
                            <label for="assignRolesPermissions" class="font-semibold">Assign Roles & Permissions</label>
                            <ToggleButton
                                v-model="assignRolesPermissions"
                                onLabel="Roles & Permissions ON"
                                offLabel="Roles & Permissions OFF"
                                onIcon="pi pi-lock-open"
                                offIcon="pi pi-lock"
                                class="sm:w-10rem w-full"
                                aria-label="Toggle Roles and Permissions Assignment"
                            />
                            <small class="text-500">Toggle to assign roles and permissions to the new user</small>
                        </div>
                    </div>

                    <div class="col-12 md:col-6">
                        <div class="flex-column flex gap-2">
                            <label for="assignMdas" class="font-semibold">Assign MDA(s)</label>
                            <ToggleButton
                                v-model="assignMdas"
                                onLabel="MDAs ON"
                                offLabel="MDAs OFF"
                                onIcon="pi pi-lock-open"
                                offIcon="pi pi-lock"
                                class="sm:w-10rem w-full"
                                aria-label="Toggle MDA Assignment"
                            />
                            <small class="text-500">Toggle to assign MDAs to the new user</small>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ROLES & PERMISSIONS SECTION -->
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

            <!-- MDA ASSIGNMENT SECTION -->
            <div
                v-if="assignMdas || editMode"
                class="assignment-section col-12"
                :class="{ 'mt-4': !editMode && assignMdas }"
            >
                <Divider align="left">
                    <b>MDA Assignment</b>
                </Divider>

                <div class="p-fluid">
                    <label class="mb-2 block font-semibold">Select MDA(s) for User</label>

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
                                <span class="font-medium">{{ slotProps.item.name }}</span>
                            </div>
                        </template>
                    </PickList>

                    <small class="p-error">{{ form.errors.mdas }}</small>
                </div>
            </div>
        </div>

        <!-- Form Actions -->
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

.assignment-section.mt-4 {
    margin-top: 1rem;
}

.text-red-500 {
    color: #ef4444;
}

.text-500 {
    color: var(--text-color-secondary);
}

/* Passport Styles */
.passport-upload-container {
    position: relative;
    width: 150px;
    height: 150px;
    margin: 0 auto;
}

.passport-preview {
    position: relative;
    width: 100%;
    height: 100%;
    border-radius: 50%;
    overflow: hidden;
    border: 3px solid var(--surface-border);
    background-color: var(--surface-ground);
}

.passport-image {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.remove-passport-btn {
    position: absolute;
    top: -10px;
    right: -10px;
    background: white !important;
}

.remove-signature-btn {
    position: absolute;
    top: -10px;
    right: -10px;
    background: white !important;
}

.passport-status,
.signature-status {
    position: absolute;
    bottom: 5px;
    left: 50%;
    transform: translateX(-50%);
}

.status-badge {
    font-size: 0.7rem;
    padding: 2px 8px;
}

.passport-upload-area {
    width: 100%;
    height: 100%;
    border: 3px dashed var(--surface-border);
    border-radius: 50%;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    padding: 1rem;
    background-color: var(--surface-ground);
    transition: all 0.3s ease;
    cursor: pointer;
}

.passport-upload-area:hover {
    border-color: var(--primary-color);
}

.passport-upload-area :deep(.p-fileupload) {
    width: 100%;
}

.passport-upload-area :deep(.p-fileupload .p-button) {
    width: 100%;
    padding: 0.5rem;
    font-size: 0.8rem;
}

/* Signature Styles */
.signature-upload-container {
    border: 2px dashed var(--surface-border);
    border-radius: 8px;
    padding: 1rem;
    background-color: var(--surface-ground);
    transition: all 0.3s ease;
    min-height: 100px;
    display: flex;
    align-items: center;
    justify-content: center;
    position: relative;
}

.signature-upload-container:hover {
    border-color: var(--primary-color);
}

.signature-preview {
    position: relative;
    display: inline-block;
    width: 100%;
    max-width: 200px;
    margin: 0 auto;
}

.signature-image {
    width: 100%;
    height: auto;
    max-height: 150px;
    object-fit: contain;
    border: 1px solid var(--surface-border);
    border-radius: 4px;
    padding: 4px;
    background: white;
}

.upload-area {
    text-align: center;
    padding: 0.5rem;
    width: 100%;
}

.upload-area :deep(.p-fileupload) {
    display: flex;
    justify-content: center;
    width: 100%;
}

.upload-area :deep(.p-fileupload .p-button) {
    width: 100%;
    max-width: 200px;
}

.upload-area small {
    display: block;
    margin-top: 0.5rem;
}

@media (max-width: 768px) {
    .signature-preview {
        max-width: 150px;
    }
    
    .passport-upload-container {
        width: 120px;
        height: 120px;
    }
}
</style>