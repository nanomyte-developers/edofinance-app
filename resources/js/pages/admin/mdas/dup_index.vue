<script setup>
import { computed, ref, watch, onMounted } from 'vue'; 
import { Head, router, useForm } from '@inertiajs/vue3'; 

// --- Frontend Validation Imports ---
import { useForm as useVeeForm, useField } from 'vee-validate';
import * as yup from 'yup';

// --- PrimeVue Imports ---
import Card from 'primevue/card';
import DataTable from 'primevue/datatable';
import Column from 'primevue/column';
import Button from 'primevue/button';
import Tag from 'primevue/tag';
import Paginator from 'primevue/paginator';
import InputText from 'primevue/inputtext'; 
import AppLayout from '@/layouts/AppLayout.vue'; 
import Dialog from 'primevue/dialog'; 
import Toast from 'primevue/toast'; 
import { useToast } from 'primevue/usetoast'; 
import Dropdown from 'primevue/dropdown'; 
import Textarea from 'primevue/textarea'; 
import Message from 'primevue/message'; 
import { nextTick } from 'vue';

const toast = useToast();

// ---------------------------------------------
// --- PROPS: Using REAL Data from Controller ---
// ---------------------------------------------
const props = defineProps({
    mdas: { // This MUST match the key passed in MdaController
        type: Object,
        required: true, // Data is now mandatory
    }, 
    flash: { 
        type: Object,
        default: () => ({ message: null }),
    },
});

// --- SUCCESS MESSAGE HANDLER ---
onMounted(() => {
    if (props.flash.message) {
        toast.add({ severity: 'success', summary: 'Success', detail: props.flash.message, life: 3000 });
    }
});


// ---------------------------------------------
// --- UNIFIED DATA SOURCE (Now just using props) ---
// ---------------------------------------------

// This computed property directly uses the paginated data structure from Laravel/Inertia.
const mdaData = computed(() => props.mdas);


// ---------------------------------------------
// --- MDA CREATION/EDIT FORM STATE (Inertia & VeeValidate) ---
// ---------------------------------------------

const showCreateMdaModal = ref(false); 
const isEdit = ref(false); // New state to track if we are editing
const currentMdaId = ref(null); // New state to hold the ID of the MDA being edited

const statusOptions = ref([
    { label: 'Active', value: 1 },
    { label: 'Inactive', value: 0 }
]);

// 1. Define the validation schema using Yup
const validationSchema = yup.object({
    name: yup.string().required('Full Name is required.').max(255, 'Full Name cannot exceed 255 characters.'),
    code: yup.string().required('Short Code is required.').max(10, 'Short Code cannot exceed 10 characters.'),
    initials: yup.string().required('Initials are required.').max(10, 'Initials cannot exceed 10 characters.'),
    location: yup.string().nullable().max(255, 'Location cannot exceed 255 characters.'),
    status: yup.number().required('Status is required.').oneOf([1, 0], 'Invalid status value.'),
});

const formDefaults = {
    name: '',
    code: '',
    initials: '',
    location: null,
    status: 1,
};

// 2. Setup VeeValidate form
const { handleSubmit, resetForm, setErrors } = useVeeForm({
    validationSchema: validationSchema,
    initialValues: formDefaults,
});

// 3. Define fields using useField
const { value: name, errorMessage: nameError } = useField('name');
const { value: code, errorMessage: codeError } = useField('code');
const { value: initials, errorMessage: initialsError } = useField('initials');
const { value: location, errorMessage: locationError } = useField('location');
const { value: status, errorMessage: statusError } = useField('status');

// 4. Setup Inertia form using VeeValidate field values
const form = useForm(formDefaults);

// Watch for Inertia server-side errors and pass them to VeeValidate
watch(() => form.errors, (newErrors) => {
    if (Object.keys(newErrors).length > 0) {
        setErrors(newErrors);
        // We handle general server errors with a toast here, validation errors are shown on the fields
        toast.add({ severity: 'error', summary: 'Server Error', detail: 'A server error occurred. Please check the form fields.', life: 5000 });
    }
}, { deep: true });


// ---------------------------------------------
// --- ACTION HANDLERS ---
// ---------------------------------------------

// Handler for Create Button (Opens Modal)
const handleCreateMda = () => {
    isEdit.value = false;
    currentMdaId.value = null;
    resetForm(); // Reset VeeValidate state
    form.reset(); // Reset Inertia form state
    showCreateMdaModal.value = true;
};

// Handler for Edit Action (Opens Modal and loads data)
const handleEditMda = (mda) => {
    isEdit.value = true;
    currentMdaId.value = mda.id;
    
    // Set VeeValidate values
    resetForm({
        values: {
            name: mda.name,
            code: mda.code,
            initials: mda.initials,
            location: mda.location,
            status: mda.status,
        }
    });

    // Set Inertia form values (required for the PUT submission)
    form.name = mda.name;
    form.code = mda.code;
    form.initials = mda.initials;
    form.location = mda.location;
    form.status = mda.status;

    showCreateMdaModal.value = true;
};

// Handler for saving the MDA (Uses VeeValidate's handleSubmit wrapper)
const saveMda = handleSubmit(async (values) => {
    // Explicitly update Inertia form with latest validated values before POST/PUT
    Object.assign(form, values);

    const options = {
        preserveScroll: true,
        // CRITICAL: Only close the modal on success and give success feedback
        onSuccess: () => {
            // Close the modal and reset state only upon successful save/update
            showCreateMdaModal.value = false; 
            form.reset(); 
            resetForm();
            toast.add({ 
                severity: 'success', 
                summary: 'Operation Successful', 
                detail: isEdit.value ? 'MDA updated successfully.' : 'New MDA created successfully.', 
                life: 3000 
            });
        },
        // IMPORTANT: Keep the modal open on error
        onError: (errors) => {
            // Errors are mapped to fields via the watch handler above
            toast.add({ 
                severity: 'error', 
                summary: 'Validation Failed', 
                detail: 'Please fix the errors shown in the form fields.', 
                life: 5000 
            });
        },
        onFinish: () => {
            // Ensure processing state is always cleared
            form.processing = false; 
        }
    };

    if (isEdit.value && currentMdaId.value) {
        // UPDATE: PUT request to /mdas/{id}
        form.put(route('mdas.update', { mda: currentMdaId.value }), options);
    } else {
        // CREATE: POST request to /mdas
        form.post(route('mdas.store'), options); 
    }
});


// --- STATE FOR MODALS AND ACTIONS ---
const globalFilter = ref(''); 
const showConfirmationModal = ref(false);
const currentMda = ref(null);
const currentAction = ref(null);
const showSectorsModal = ref(false);
const mdaSectors = ref([]);
const isLoadingSectors = ref(false); // New state for loading indicator

// --- COMPUTED PROPERTIES for Paginator ---
const paginatorTotalRecords = computed(() => mdaData.value.total);
const paginatorCurrentPage = computed(() => mdaData.value.current_page);
const paginatorRows = computed(() => mdaData.value.per_page);

const route = (name, params) => {
    // Resource Route Helper (Necessary because Inertia requires Ziggy, which isn't available here)
    if (name === 'mdas.store' || name === 'mdas.index') {
        return '/mdas';
    } 
    
    if (name.includes('.') && params && (params.id || params.mda)) {
        const id = params.id || params.mda;
        return `/mdas/${id}`;
    }

    return `/${name.replace(/\./g, '/')}`;
};

const breadcrumbs = [ { title: 'MDA Management', href: route('mdas.index') } ];

// --- HELPER FUNCTIONS ---
const getStatusSeverity = (status) => {
    return status === 1 ? 'success' : 'danger';
};

const getStatusText = (status) => {
    return status === 1 ? 'Active' : 'Inactive';
};


const onPageChange = (event) => { 
    // Uses the link provided by Laravel's pagination structure
    const url = mdaData.value.links[event.page + 1].url;
    
    // Check if the URL exists and navigate
    if (url) {
        router.get(url, {}, { preserveState: true, replace: true });
    } else {
        console.warn("Attempted to navigate to a page without a valid URL.");
    }
};

const openConfirmationModal = (mda, action) => {
    currentMda.value = mda;
    currentAction.value = action;
    showConfirmationModal.value = true;
};

const confirmAction = () => {
    showConfirmationModal.value = false;
    
    if (!currentMda.value) return;

    if (currentAction.value === 'delete') {
        // --- DELETE ACTION ---
        router.delete(route('mdas.destroy', { mda: currentMda.value.id }), { 
            preserveScroll: true,
            onSuccess: () => {
                toast.add({ severity: 'success', summary: 'Deleted', detail: `MDA ${currentMda.value.name} removed.`, life: 3000 });
            },
            onError: (errors) => {
                toast.add({ severity: 'error', summary: 'Error', detail: errors.message || 'Failed to delete MDA.', life: 5000 });
            }
        });
    }
    // Edit action is now handled directly by handleEditMda, bypassing this confirmation
};

// const openSectorsModal = (mda) => {
//     currentMda.value = mda;
//     mdaSectors.value = [
//         { id: 101, name: 'Budget Planning', code: 'BUD' },
//         { id: 102, name: 'Revenue Monitoring', code: 'REV' },
//     ];
//     showSectorsModal.value = true;
// };

// --- New/Updated ACTION HANDLER ---
const openSectorsModal = (mda) => {
    currentMda.value = mda;
    showSectorsModal.value = true;
    
    // Clear previous sectors and set loading state
    mdaSectors.value = [];
    isLoadingSectors.value = true;

    // Use router.get with a partial reload/visit to the JSON endpoint
    router.get(
        // Use your helper to construct the URL for the new route
        route('mdas.sectors.fetch', { mda: mda.id }),
        {}, // No data payload
        {
            preserveState: true, // Keep the current page's state (filters, etc.)
            preserveScroll: true, // Keep scroll position
            replace: true, // Replace history entry instead of pushing a new one
            
            // This is CRITICAL: Tell Inertia to expect a standard JSON response
            // (Note: Inertia handles JSON responses by resolving the promise, but for cleaner code and error handling,
            // we'll rely on a direct fetch since the Laravel route returns JSON, not an Inertia response).
            
            // --- Since your backend returns JSON, we should use a standard fetch API ---
            onBefore: () => {
                // If the route helper can't be modified to handle standard URL, use the full path:
                const url = `/mdas/${mda.id}/sectors`; // Adjust this path if your route is different
                
                fetch(url)
                    .then(response => {
                        if (!response.ok) {
                            throw new Error('Network response was not ok');
                        }
                        return response.json();
                    })
                    .then(data => {
                        mdaSectors.value = data.sectors; // Assign the fetched sector data
                        // The modal header will use currentMda.initials which is already set
                    })
                    .catch(error => {
                        console.error("Error fetching sectors:", error);
                        toast.add({ severity: 'error', summary: 'Error', detail: 'Could not load sectors.', life: 3000 });
                    })
                    .finally(() => {
                        isLoadingSectors.value = false;
                    });
                
                // Return false to prevent Inertia from attempting a full page visit
                return false; 
            },
        }
    );
};
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head title="MDA Management" />
        
        <!-- Global Toast Component -->
        <Toast /> 

        <Card>
            <template #title>
                <div class="flex justify-content-between align-items-center flex-wrap">
                    <h2 class="text-xl font-bold">MDAs List ({{ mdaData.total }})</h2>
                    <div class="flex gap-3 align-items-center mt-2 sm:mt-0">
                        <!-- Search Input -->
                        <span class="p-input-icon-left">
                            <i class="pi pi-search" />
                            <InputText v-model="globalFilter" placeholder="Search MDAs..." />
                        </span>
                        
                        <!-- Create Button -->
                        <Button 
                            label="Create New MDA" 
                            icon="pi pi-plus" 
                            severity="primary" 
                            @click="handleCreateMda" 
                        />
                    </div>
                </div>
            </template>
            
            <template #content>
                <DataTable 
                    :value="mdaData.data" 
                    dataKey="id" 
                    stripedRows 
                    responsiveLayout="scroll"
                    class="p-datatable-sm shadow-md rounded-lg"
                    :globalFilterFields="['name', 'code', 'initials', 'location']"
                    v-model:filters="globalFilter"
                    filterDisplay="row"
                    :emptyMessage="'No MDAs found. Try creating a new one or adjusting your search.'"
                >
                    
                    <Column field="id" header="ID" headerStyle="width: 5%">
                        <template #body="slotProps">
                            <span class="font-medium text-500">{{ slotProps.data.id }}</span>
                        </template>
                    </Column>

                    <Column field="name" header="Name" headerStyle="width: 30%" :sortable="true">
                        <template #body="slotProps">
                            <span class="font-semibold">{{ slotProps.data.name }}</span>
                        </template>
                    </Column>
                    
                    <Column field="code" header="Code" headerStyle="width: 10%" :sortable="true">
                        <template #body="slotProps">
                            <Tag :value="slotProps.data.code" severity="info" class="font-mono text-sm" />
                        </template>
                    </Column>

                    <Column field="initials" header="Initials" headerStyle="width: 10%" :sortable="true" />
                    
                    <Column field="location" header="Location" headerStyle="width: 20%" :sortable="true" />
                    
                    <Column field="status" header="Status" headerStyle="width: 10%" :sortable="true">
                        <template #body="slotProps">
                            <Tag 
                                :value="getStatusText(slotProps.data.status)" 
                                :severity="getStatusSeverity(slotProps.data.status)"
                            />
                        </template>
                    </Column>

                    <Column header="Actions" headerStyle="width: 15%" bodyClass="text-center">
                        <template #body="slotProps">
                            <div class="flex gap-2 justify-content-center">
                                
                                <Button 
                                    icon="pi pi-list" 
                                    severity="info"
                                    text rounded
                                    v-tooltip.top="'View Sectors (Mock Data)'"
                                    @click="openSectorsModal(slotProps.data)"
                                />

                                <Button 
                                    icon="pi pi-pencil" 
                                    severity="secondary"
                                    text rounded
                                    v-tooltip.top="'Edit MDA'"
                                    @click="handleEditMda(slotProps.data)"
                                />
                                
                                <Button 
                                    icon="pi pi-trash" 
                                    severity="danger" 
                                    text rounded
                                    v-tooltip.top="'Delete MDA'"
                                    @click="openConfirmationModal(slotProps.data, 'delete')" 
                                />
                            </div>
                        </template>
                    </Column>
                </DataTable>

                <!-- Paginator -->
                <div class="mt-4 flex justify-content-end" v-if="mdaData.total > paginatorRows">
                    <Paginator 
                       :rows="paginatorRows" 
                        :totalRecords="paginatorTotalRecords" 
                        :first="(paginatorCurrentPage - 1) * paginatorRows"
                        @page="onPageChange"
                        :template="{ 
                            default: 'FirstPageLink PrevPageLink PageLinks NextPageLink LastPageLink' 
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
            <div class="flex align-items-center">
                <i class="pi pi-exclamation-triangle mr-3 text-red-500" style="font-size: 2rem"></i>
                
                <span v-if="currentMda">
                    Are you sure you want to **permanently delete** MDA: **{{ currentMda.name }}**? This action cannot be undone and will affect all related sectors.
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
        
        <!-- 2. VIEW SECTORS DIALOG (Still using mock sector data) -->
        <Dialog 
            v-model:visible="showSectorsModal" 
            :style="{ width: '600px' }" 
            :header="`Sectors within ${currentMda?.initials || 'MDA'}`" 
            :modal="true"
        >
            <div v-if="isLoadingSectors" class="p-4 text-center">
                <i class="pi pi-spin pi-spinner text-4xl text-primary mb-2"></i>
                <p class="text-600">Loading sectors...</p>
            </div>
            
            <div v-else-if="mdaSectors.length > 0">
                <DataTable :value="mdaSectors" stripedRows :scrollable="true" scrollHeight="300px">
                    <Column field="id" header="ID" headerStyle="width: 15%" />
                    <Column field="name" header="Sector Name" headerStyle="width: 55%" />
                    <Column field="code" header="Code" headerStyle="width: 30%">
                        <template #body="slotProps">
                            <Tag :value="slotProps.data.code" severity="warning" />
                        </template>
                    </Column>
                </DataTable>
            </div>
            
            <div v-else class="p-4 text-center text-500">
                <i class="pi pi-info-circle text-2xl block mb-2"></i>
                No specific sectors defined yet for {{ currentMda?.name }}.
            </div>
            
            <template #footer>
                <Button label="Close" icon="pi an-check" @click="showSectorsModal = false" />
            </template>
        </Dialog>

        <!-- 3. CREATE/EDIT MDA DIALOG -->
        <Dialog 
            v-model:visible="showCreateMdaModal" 
            :style="{ width: '500px' }" 
            :header="isEdit ? 'Edit MDA Details' : 'Create New MDA'" 
            :modal="true"
            class="p-fluid"
        >
            <!-- CRITICAL: The form wrapper uses @submit.prevent to link to saveMda -->
            <form @submit.prevent="saveMda" class="flex flex-col gap-4 mt-2"> 
                
                <!-- Full Name -->
                <div class="w-full">
                    <label for="name" class="block font-semibold mb-2">Full Name</label>
                    <InputText 
                        id="name" 
                        v-model="name" 
                        :class="{'p-invalid': nameError}" 
                        placeholder="e.g., Ministry of Finance"
                        class="w-full"
                    />
                    <Message v-if="nameError" severity="error" :closable="false" class="mt-2">{{ nameError }}</Message>
                </div>
                
                <!-- Short Code -->
                <div class="w-full">
                    <label for="code" class="block font-semibold mb-2">Short Code</label>
                    <InputText 
                        id="code" 
                        v-model="code" 
                        :class="{'p-invalid': codeError}" 
                        maxlength="10"
                        placeholder="e.g., 1001"
                        class="w-full"
                    />
                    <Message v-if="codeError" severity="error" :closable="false" class="mt-2">{{ codeError }}</Message>
                </div>

                <!-- Initials -->
                <div class="w-full">
                    <label for="initials" class="block font-semibold mb-2">Initials (Abbreviation)</label>
                    <InputText 
                        id="initials" 
                        v-model="initials" 
                        :class="{'p-invalid': initialsError}" 
                        maxlength="10"
                        placeholder="e.g., MOF"
                        class="w-full"
                    />
                    <Message v-if="initialsError" severity="error" :closable="false" class="mt-2">{{ initialsError }}</Message>
                </div>

                <!-- Status -->
                <div class="w-full">
                    <label for="status" class="block font-semibold mb-2">Status</label>
                    <Dropdown 
                        id="status" 
                        v-model="status" 
                        :options="statusOptions" 
                        optionLabel="label" 
                        optionValue="value"
                        :class="{'p-invalid': statusError}"
                        placeholder="Select Status"
                        class="w-full"
                    />
                    <Message v-if="statusError" severity="error" :closable="false" class="mt-2">{{ statusError }}</Message>
                </div>

                <!-- Location -->
                <div class="w-full">
                    <label for="location" class="block font-semibold mb-2">Location/Address (Optional)</label>
                    <Textarea 
                        id="location" 
                        v-model="location" 
                        :class="{'p-invalid': locationError}" 
                        rows="3" 
                        placeholder="e.g., Block A, State Secretariat"
                        class="w-full"
                    />
                    <Message v-if="locationError" severity="error" :closable="false" class="mt-2">{{ locationError }}</Message>
                </div>
            </form>

            <template #footer>
                <Button 
                    label="Cancel" 
                    icon="pi pi-times" 
                    @click="showCreateMdaModal = false" 
                    text 
                />
                <!-- CRITICAL: Instead of @click, we need to programmatically submit the form. 
                   We will keep the current @click and rely on saveMda calling handleSubmit which handles validation, 
                   but we need to make sure the submission method is robust. -->
                <Button 
                    :label="isEdit ? 'Update MDA' : 'Save MDA'"
                    icon="pi an-check" 
                    @click="saveMda" 
                    :loading="form.processing"
                    :disabled="form.processing"
                    autofocus 
                />
            </template>
        </Dialog>
        
    </AppLayout>
</template>