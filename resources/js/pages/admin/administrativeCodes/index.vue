<script setup>
import { Head, router, useForm } from '@inertiajs/vue3';
import axios from 'axios';
import debounce from 'lodash/debounce';
import { useField, useForm as useVeeForm } from 'vee-validate';
import { onMounted, ref, watch } from 'vue';
import * as yup from 'yup';

// PrimeVue Imports
import AppLayout from '@/layouts/AppLayout.vue';
import Button from 'primevue/button';
import Card from 'primevue/card';
import Column from 'primevue/column';
import DataTable from 'primevue/datatable';
import Dialog from 'primevue/dialog';
import InputSwitch from 'primevue/inputswitch';
import InputText from 'primevue/inputtext';
import Paginator from 'primevue/paginator';
import Tag from 'primevue/tag';
import Toast from 'primevue/toast';
import Tooltip from 'primevue/tooltip';
import { useToast } from 'primevue/usetoast';

// Register directive
const vTooltip = Tooltip;

const props = defineProps({
    administrativeCodes: Object,
    flash: Object,
    filters: {
        type: Object,
        default: () => ({ search: '' }),
    },
});

const toast = useToast();
const showModal = ref(false);
const showMdaModal = ref(false);
const isEdit = ref(false);
const currentId = ref(null);
const selectedMdas = ref([]);
const loadingMda = ref(false);
const searchQuery = ref(props.filters?.search || '');

// --- Validation Schema ---
const validationSchema = yup.object({
    name: yup.string().required('Name is required').max(200),
    code: yup.string().required('Code is required').max(20),
    status: yup.boolean(),
});

const { handleSubmit, resetForm, setErrors } = useVeeForm({
    validationSchema,
    initialValues: { name: '', code: '', status: true },
});

const { value: name, errorMessage: nameError } = useField('name');
const { value: code, errorMessage: codeError } = useField('code');
const { value: status } = useField('status');

const inertiaForm = useForm({});

// Show flash message on mount
onMounted(() => {
    if (props.flash?.message) {
        toast.add({
            severity: 'success',
            summary: 'Success',
            detail: props.flash.message,
            life: 3000,
        });
    }
});

// --- Search Functionality ---
const performSearch = debounce(() => {
    router.get(
        '/administrative-codes',
        { search: searchQuery.value, page: 1 },
        {
            preserveState: true,
            replace: true,
            preserveScroll: true,
        },
    );
}, 500);

watch(searchQuery, (newValue) => {
    performSearch();
});

const clearSearch = () => {
    searchQuery.value = '';
    router.get(
        '/administrative-codes',
        { search: '', page: 1 },
        {
            preserveState: true,
            replace: true,
            preserveScroll: true,
        },
    );
};

// --- Actions ---
const handleCreate = () => {
    isEdit.value = false;
    currentId.value = null;
    resetForm({ values: { name: '', code: '', status: true } });
    showModal.value = true;
};

const handleEdit = (data) => {
    isEdit.value = true;
    currentId.value = data.id;
    resetForm({
        values: {
            name: data.name,
            code: data.code,
            status: data.status,
        },
    });
    showModal.value = true;
};

const viewMdas = async (adminCode) => {
    loadingMda.value = true;
    try {
        const response = await axios.get(
            `/administrative-codes/${adminCode.id}`,
        );
        selectedMdas.value = response.data.mda || [];
        showMdaModal.value = true;
    } catch (error) {
        toast.add({
            severity: 'error',
            summary: 'Error',
            detail: 'Failed to load MDAs',
            life: 3000,
        });
    } finally {
        loadingMda.value = false;
    }
};

const onSave = handleSubmit((values) => {
    if (isEdit.value && currentId.value) {
        // For update - use PUT with ID
        router.put(`/administrative-codes/${currentId.value}`, values, {
            preserveScroll: true,
            onSuccess: () => {
                showModal.value = false;
                toast.add({
                    severity: 'success',
                    summary: 'Success',
                    detail: 'Record updated successfully',
                    life: 3000,
                });
            },
            onError: (errors) => {
                setErrors(errors);
                if (errors.response?.status === 404) {
                    toast.add({
                        severity: 'error',
                        summary: 'Error',
                        detail: 'Record not found. It may have been deleted.',
                        life: 3000,
                    });
                } else {
                    toast.add({
                        severity: 'error',
                        summary: 'Error',
                        detail: 'Please check the form for errors',
                        life: 3000,
                    });
                }
            },
        });
    } else {
        // For create - use POST without ID
        router.post('/administrative-codes', values, {
            preserveScroll: true,
            onSuccess: () => {
                showModal.value = false;
                toast.add({
                    severity: 'success',
                    summary: 'Success',
                    detail: 'Record created successfully',
                    life: 3000,
                });
            },
            onError: (errors) => {
                setErrors(errors);
                toast.add({
                    severity: 'error',
                    summary: 'Error',
                    detail: 'Please check the form for errors',
                    life: 3000,
                });
            },
        });
    }
});

const toggleStatus = (data) => {
    const newStatus = !data.status;
    router.patch(
        `/administrative-codes/${data.id}/toggle-status`,
        {
            status: newStatus,
        },
        {
            preserveScroll: true,
            onSuccess: () => {
                toast.add({
                    severity: 'success',
                    summary: 'Status Updated',
                    detail: `Status changed to ${newStatus ? 'Active' : 'Inactive'}`,
                    life: 3000,
                });
            },
            onError: () => {
                data.status = !newStatus; // Revert on error
                toast.add({
                    severity: 'error',
                    summary: 'Error',
                    detail: 'Failed to update status',
                    life: 3000,
                });
            },
        },
    );
};

const onPageChange = (event) => {
    const params = {
        page: event.page + 1,
    };

    if (searchQuery.value) {
        params.search = searchQuery.value;
    }

    router.get('/administrative-codes', params, {
        preserveState: true,
        preserveScroll: true,
    });
};

const breadcrumbs = [{ title: 'Setup' }, { title: 'Administrative Codes' }];
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head title="Administrative Codes" />
        <Toast />

        <Card class="border-0 shadow-sm">
            <template #title>
                <div
                    class="flex flex-col justify-between gap-4 md:flex-row md:items-center"
                >
                    <div>
                        <h2 class="m-0 text-xl font-bold text-gray-900">
                            Administrative Codes
                        </h2>
                        <small class="font-normal text-gray-500"
                            >Manage sectors and their associated MDAs</small
                        >
                    </div>
                    <div class="flex items-center gap-3">
                        <div class="relative">
                            <span class="p-input-icon-left">
                                <i class="pi pi-search text-gray-400" />
                                <InputText
                                    v-model="searchQuery"
                                    placeholder="Search by code or name..."
                                    class="w-full md:w-64"
                                />
                            </span>
                            <Button
                                v-if="searchQuery"
                                icon="pi pi-times"
                                text
                                rounded
                                @click="clearSearch"
                                class="absolute top-1 right-1"
                                v-tooltip="'Clear search'"
                            />
                        </div>
                        <Button
                            label="Add New Code"
                            icon="pi pi-plus"
                            severity="primary"
                            @click="handleCreate"
                            class="p-button-raised"
                        />
                    </div>
                </div>
            </template>

            <template #content>
                <!-- Search Results Summary -->
                <div
                    v-if="searchQuery"
                    class="mb-4 rounded-lg border border-blue-100 bg-blue-50 p-3"
                >
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-2">
                            <i class="pi pi-search text-blue-500"></i>
                            <span class="text-sm font-medium text-blue-700">
                                Search results for: "<span class="font-bold">{{
                                    searchQuery
                                }}</span
                                >"
                            </span>
                            <Tag
                                :value="`${administrativeCodes.meta.total} result${administrativeCodes.meta.total !== 1 ? 's' : ''}`"
                                severity="info"
                                class="text-xs"
                            />
                        </div>
                        <Button
                            label="Clear Search"
                            icon="pi pi-times"
                            severity="secondary"
                            text
                            size="small"
                            @click="clearSearch"
                        />
                    </div>
                </div>

                <DataTable
                    :value="administrativeCodes.data"
                    stripedRows
                    class="p-datatable-sm"
                    dataKey="id"
                    :rows="administrativeCodes.meta.per_page"
                    responsiveLayout="scroll"
                >
                    <template #empty>
                        <div class="p-8 text-center">
                            <i
                                class="pi pi-search mb-3 text-3xl text-gray-300"
                            ></i>
                            <p class="text-lg text-gray-500">
                                {{
                                    searchQuery
                                        ? 'No results found for your search.'
                                        : 'No administrative codes found.'
                                }}
                            </p>
                            <p
                                v-if="searchQuery"
                                class="mt-1 text-sm text-gray-400"
                            >
                                Try different keywords or clear your search
                            </p>
                        </div>
                    </template>

                    <Column field="code" header="Code" style="min-width: 120px">
                        <template #body="{ data }">
                            <div class="flex items-center gap-2">
                                <span
                                    class="rounded bg-gray-100 px-2 py-1 font-mono text-sm"
                                    >{{ data.code }}</span
                                >
                                <span
                                    v-if="
                                        searchQuery &&
                                        data.code
                                            .toLowerCase()
                                            .includes(searchQuery.toLowerCase())
                                    "
                                    class="rounded bg-yellow-100 px-1 py-0.5 text-xs text-yellow-800"
                                >
                                    Match
                                </span>
                            </div>
                        </template>
                    </Column>

                    <Column field="name" header="Name" style="min-width: 300px">
                        <template #body="{ data }">
                            <div class="flex flex-col">
                                <span class="font-medium text-gray-800">{{
                                    data.name
                                }}</span>
                                <span
                                    v-if="
                                        searchQuery &&
                                        data.name
                                            .toLowerCase()
                                            .includes(searchQuery.toLowerCase())
                                    "
                                    class="mt-1 text-xs text-green-600"
                                >
                                    <i
                                        class="pi pi-check-circle mr-1 text-xs"
                                    ></i>
                                    Search match
                                </span>
                            </div>
                        </template>
                    </Column>

                    <Column
                        header="Status"
                        headerClass="text-center"
                        bodyClass="text-center"
                        style="width: 120px"
                    >
                        <template #body="{ data }">
                            <div class="flex flex-col items-center gap-2">
                                <InputSwitch
                                    v-model="data.status"
                                    @change="toggleStatus(data)"
                                    class="scale-90"
                                />
                                <Tag
                                    :severity="
                                        data.status ? 'success' : 'danger'
                                    "
                                    :value="data.status ? 'Active' : 'Inactive'"
                                    class="text-xs uppercase"
                                />
                            </div>
                        </template>
                    </Column>

                    <Column
                        header="Actions"
                        bodyClass="text-right"
                        style="width: 140px"
                    >
                        <template #body="{ data }">
                            <div class="flex justify-end gap-2">
                                <Button
                                    icon="pi pi-eye"
                                    severity="info"
                                    text
                                    rounded
                                    v-tooltip.top="'View MDAs'"
                                    @click="viewMdas(data)"
                                />
                                <Button
                                    icon="pi pi-pencil"
                                    severity="secondary"
                                    text
                                    rounded
                                    v-tooltip.top="'Edit'"
                                    @click="handleEdit(data)"
                                />
                            </div>
                        </template>
                    </Column>
                </DataTable>

                <div
                    class="mt-6 flex flex-col items-center justify-between gap-4 border-t pt-4 md:flex-row"
                    v-if="administrativeCodes.meta.total > 0"
                >
                    <div class="text-sm text-gray-500">
                        Showing {{ administrativeCodes.meta.from }} to
                        {{ administrativeCodes.meta.to }} of
                        {{ administrativeCodes.meta.total }} entries
                        <span v-if="searchQuery" class="ml-2 text-blue-600">
                            (Filtered by search)
                        </span>
                    </div>
                    <Paginator
                        v-if="
                            administrativeCodes.meta.total >
                            administrativeCodes.meta.per_page
                        "
                        :rows="administrativeCodes.meta.per_page"
                        :totalRecords="administrativeCodes.meta.total"
                        :first="
                            (administrativeCodes.meta.current_page - 1) *
                            administrativeCodes.meta.per_page
                        "
                        @page="onPageChange"
                        template="FirstPageLink PrevPageLink PageLinks NextPageLink LastPageLink"
                        class="p-paginator-sm"
                    />
                </div>
            </template>
        </Card>

        <!-- Create/Edit Modal -->
        <Dialog
            v-model:visible="showModal"
            :modal="true"
            :draggable="false"
            :style="{ width: '500px' }"
            :header="
                isEdit
                    ? 'Edit Administrative Code'
                    : 'Create New Administrative Code'
            "
            class="p-fluid"
        >
            <form @submit.prevent="onSave" class="flex flex-col gap-5 pt-2">
                <div class="field">
                    <label
                        for="code"
                        class="mb-1 block font-medium text-gray-700"
                    >
                        Code <span class="text-red-500">*</span>
                    </label>
                    <InputText
                        id="code"
                        v-model="code"
                        :class="{ 'p-invalid': codeError }"
                        placeholder="e.g., 010000"
                        class="w-full"
                    />
                    <small
                        class="mt-1 block text-xs text-red-500"
                        v-if="codeError"
                    >
                        {{ codeError }}
                    </small>
                </div>

                <div class="field">
                    <label
                        for="name"
                        class="mb-1 block font-medium text-gray-700"
                    >
                        Name <span class="text-red-500">*</span>
                    </label>
                    <InputText
                        id="name"
                        v-model="name"
                        :class="{ 'p-invalid': nameError }"
                        placeholder="e.g., Federal Ministry of..."
                        class="w-full"
                    />
                    <small
                        class="mt-1 block text-xs text-red-500"
                        v-if="nameError"
                    >
                        {{ nameError }}
                    </small>
                </div>

                <div
                    class="field mt-2 flex items-center justify-between rounded-lg border bg-gray-50 p-3"
                >
                    <div>
                        <label
                            for="status"
                            class="cursor-pointer font-medium text-gray-700"
                            >Active Status</label
                        >
                        <p class="mt-1 text-xs text-gray-500">
                            When active, this code will be available for
                            selection
                        </p>
                    </div>
                    <InputSwitch id="status" v-model="status" />
                </div>
            </form>

            <template #footer>
                <div class="flex justify-end gap-2 border-t pt-3">
                    <Button
                        label="Cancel"
                        icon="pi pi-times"
                        severity="secondary"
                        outlined
                        @click="showModal = false"
                    />
                    <Button
                        :label="isEdit ? 'Update' : 'Create'"
                        icon="pi pi-check"
                        severity="primary"
                        @click="onSave"
                        :loading="inertiaForm.processing"
                        :disabled="inertiaForm.processing"
                    />
                </div>
            </template>
        </Dialog>

        <!-- MDAs Modal -->
        <Dialog
            v-model:visible="showMdaModal"
            :modal="true"
            :draggable="false"
            :style="{ width: '700px' }"
            header="Associated MDAs"
        >
            <div class="flex flex-col gap-4">
                <div class="mb-2 text-sm text-gray-600">
                    Showing MDAs associated with this administrative code
                </div>

                <DataTable
                    :value="selectedMdas"
                    :loading="loadingMda"
                    scrollable
                    scrollHeight="400px"
                    class="p-datatable-sm"
                    stripedRows
                >
                    <template #empty>
                        <div class="p-8 text-center text-gray-500">
                            <i class="pi pi-info-circle mb-2 text-2xl"></i>
                            <p>No MDAs assigned to this sector</p>
                        </div>
                    </template>

                    <template #loading>
                        <div class="p-4 text-center">
                            <i class="pi pi-spin pi-spinner text-2xl"></i>
                            <p class="mt-2">Loading MDAs...</p>
                        </div>
                    </template>

                    <Column
                        field="code"
                        header="MDA Code"
                        style="min-width: 120px"
                    >
                        <template #body="{ data }">
                            <span class="font-mono text-sm">{{
                                data.code
                            }}</span>
                        </template>
                    </Column>

                    <Column
                        field="name"
                        header="MDA Name"
                        style="min-width: 300px"
                    >
                        <template #body="{ data }">
                            <span class="font-medium">{{ data.name }}</span>
                        </template>
                    </Column>

                    <Column field="type" header="Type" style="min-width: 150px">
                        <template #body="{ data }">
                            <Tag
                                :value="data.type || 'N/A'"
                                severity="info"
                                class="text-xs"
                            />
                        </template>
                    </Column>
                </DataTable>
            </div>

            <template #footer>
                <div class="flex justify-end">
                    <Button
                        label="Close"
                        icon="pi pi-times"
                        severity="secondary"
                        @click="showMdaModal = false"
                    />
                </div>
            </template>
        </Dialog>
    </AppLayout>
</template>
