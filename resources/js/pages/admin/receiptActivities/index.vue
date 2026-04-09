<script setup>
import { Head, router, useForm } from '@inertiajs/vue3';
import debounce from 'lodash/debounce';
import { configure, useField, useForm as useVeeForm } from 'vee-validate';
import { computed, onMounted, ref, watch } from 'vue';
import * as yup from 'yup';

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

const vTooltip = Tooltip;
const toast = useToast();

configure({ validateOnBlur: true, validateOnChange: true });

const props = defineProps({
    receiptActivities: { type: Object, required: true },
    flash: { type: Object, default: () => ({ message: null }) },
    filters: {
        type: Object,
        default: () => ({ search: '' }),
    },
});

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

const activityData = computed(() => props.receiptActivities);
const currentActivity = ref(null);
const showModal = ref(false);
const isEdit = ref(false);
const searchQuery = ref(props.filters?.search || '');
const isSearching = ref(false);

// --- Validation Schema ---
const validationSchema = yup.object({
    name: yup.string().required('Name is required.').max(255),
    status: yup.string().oneOf(['active', 'inactive']).required(),
});

const { handleSubmit, resetForm, setErrors } = useVeeForm({
    validationSchema,
    initialValues: { name: '', status: 'active' },
});

const { value: name, errorMessage: nameError } = useField('name');
const { value: status } = useField('status');

const inertiaForm = useForm({ name: '', status: 'active' });

// --- Search Functionality ---
const performSearch = debounce(() => {
    if (searchQuery.value.trim() === '') {
        router.get(
            '/receipt-activities',
            {},
            {
                preserveState: true,
                preserveScroll: true,
                replace: true,
                onStart: () => (isSearching.value = true),
                onFinish: () => (isSearching.value = false),
            },
        );
        return;
    }

    isSearching.value = true;
    router.get(
        '/receipt-activities',
        { search: searchQuery.value, page: 1 },
        {
            preserveState: true,
            preserveScroll: true,
            replace: true,
            onFinish: () => (isSearching.value = false),
        },
    );
}, 500);

watch(searchQuery, () => {
    performSearch();
});

const clearSearch = () => {
    searchQuery.value = '';
    isSearching.value = true;
    router.get(
        '/receipt-activities',
        {},
        {
            preserveState: true,
            preserveScroll: true,
            replace: true,
            onFinish: () => (isSearching.value = false),
        },
    );
};

// --- Actions ---
const handleCreate = () => {
    isEdit.value = false;
    currentActivity.value = null;
    resetForm({ values: { name: '', status: 'active' } });
    showModal.value = true;
};

const handleEdit = (data) => {
    isEdit.value = true;
    currentActivity.value = data;
    resetForm({
        values: {
            name: data.name,
            status: data.status === 1 ? 'active' : 'inactive',
        },
    });
    showModal.value = true;
};

const saveActivity = handleSubmit((values) => {
    const formattedValues = {
        name: values.name,
        status: values.status === 'active' ? 1 : 0,
    };

    const options = {
        preserveScroll: true,
        onSuccess: () => {
            showModal.value = false;
            toast.add({
                severity: 'success',
                summary: 'Success',
                detail: isEdit.value
                    ? 'Activity updated successfully'
                    : 'Activity created successfully',
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
    };

    if (isEdit.value && currentActivity.value?.id) {
        router.put(
            `/receipt-activities/${currentActivity.value.id}`,
            formattedValues,
            options,
        );
    } else {
        router.post('/receipt-activities', formattedValues, options);
    }
});

const toggleStatus = (data) => {
    const newStatus = data.status === 1 ? 0 : 1;

    router.post(
        `/receipt-activities/${data.id}/toggle-status`,
        {
            status: newStatus,
        },
        {
            preserveScroll: true,
            onSuccess: () => {
                toast.add({
                    severity: 'success',
                    summary: 'Updated',
                    detail: `Status changed to ${newStatus === 1 ? 'Active' : 'Inactive'}`,
                    life: 2000,
                });
            },
            onError: () => {
                toast.add({
                    severity: 'error',
                    summary: 'Error',
                    detail: 'Failed to update status',
                    life: 2000,
                });
            },
        },
    );
};

const onPageChange = (event) => {
    const params = { page: event.page + 1 };
    if (searchQuery.value) {
        params.search = searchQuery.value;
    }

    router.get('/receipt-activities', params, {
        preserveState: true,
        preserveScroll: true,
    });
};

const breadcrumbs = [{ title: 'Admin' }, { title: 'Receipt Activities' }];

// Helper functions
const getStatusText = (statusValue) => {
    return statusValue === 1 ? 'active' : 'inactive';
};

const getStatusSeverity = (statusValue) => {
    return statusValue === 1 ? 'success' : 'danger';
};
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head title="Receipt Activities" />
        <Toast />

        <Card class="border-0 shadow-sm">
            <template #title>
                <div
                    class="flex flex-col justify-between gap-4 md:flex-row md:items-center"
                >
                    <div>
                        <h2 class="m-0 text-xl font-bold text-gray-900">
                            Receipt Activities
                        </h2>
                        <small class="font-normal text-gray-500"
                            >Manage receipt types and statuses</small
                        >
                    </div>
                    <div class="flex items-center gap-3">
                        <div class="relative">
                            <span class="p-input-icon-left">
                                <i class="pi pi-search text-gray-400" />
                                <InputText
                                    v-model="searchQuery"
                                    placeholder="Search by name..."
                                    class="w-full md:w-64"
                                    :disabled="isSearching"
                                />
                            </span>
                            <div
                                v-if="isSearching"
                                class="absolute top-2.5 right-3"
                            >
                                <i
                                    class="pi pi-spin pi-spinner text-sm text-gray-400"
                                ></i>
                            </div>
                            <Button
                                v-else-if="searchQuery"
                                icon="pi pi-times"
                                text
                                rounded
                                size="small"
                                @click="clearSearch"
                                class="absolute top-1 right-1"
                                v-tooltip="'Clear search'"
                            />
                        </div>
                        <Button
                            label="New Activity"
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
                                :value="`${activityData.meta.total} result${activityData.meta.total !== 1 ? 's' : ''}`"
                                severity="info"
                                class="text-xs"
                            />
                            <i
                                v-if="isSearching"
                                class="pi pi-spin pi-spinner text-sm text-blue-500"
                            ></i>
                        </div>
                        <Button
                            label="Clear Search"
                            icon="pi pi-times"
                            severity="secondary"
                            text
                            size="small"
                            @click="clearSearch"
                            :disabled="isSearching"
                        />
                    </div>
                </div>

                <DataTable
                    :value="activityData.data"
                    class="p-datatable-sm mt-3"
                    stripedRows
                    :loading="isSearching"
                    dataKey="id"
                    :rows="activityData.meta.per_page"
                    responsiveLayout="scroll"
                >
                    <template #empty>
                        <div class="p-8 text-center">
                            <i
                                v-if="searchQuery"
                                class="pi pi-search mb-3 text-3xl text-gray-300"
                            ></i>
                            <i
                                v-else
                                class="pi pi-database mb-3 text-3xl text-gray-300"
                            ></i>
                            <p class="text-lg text-gray-500">
                                {{
                                    searchQuery
                                        ? 'No results found for your search.'
                                        : 'No receipt activities found.'
                                }}
                            </p>
                            <p
                                v-if="searchQuery"
                                class="mt-1 text-sm text-gray-400"
                            >
                                Try different keywords or clear your search
                            </p>
                            <p v-else class="mt-1 text-sm text-gray-400">
                                Click "New Activity" to create your first
                                receipt activity
                            </p>
                        </div>
                    </template>

                    <template #loading>
                        <div class="p-8 text-center">
                            <i
                                class="pi pi-spin pi-spinner text-2xl text-gray-400"
                            ></i>
                            <p class="mt-2 text-gray-500">Loading data...</p>
                        </div>
                    </template>

                    <Column field="name" header="Name" style="min-width: 20rem">
                        <template #body="{ data }">
                            <div class="flex flex-col">
                                <span class="font-semibold">{{
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
                    <Column header="Status" style="width: 10rem">
                        <template #body="{ data }">
                            <div class="flex flex-col items-center gap-1">
                                <InputSwitch
                                    :modelValue="data.status === 1"
                                    @update:modelValue="toggleStatus(data)"
                                    class="scale-75"
                                    :disabled="isSearching"
                                />
                                <Tag
                                    :severity="getStatusSeverity(data.status)"
                                    :value="getStatusText(data.status)"
                                    class="text-[10px] uppercase"
                                />
                            </div>
                        </template>
                    </Column>
                    <Column
                        header="Actions"
                        bodyClass="text-right"
                        style="width: 8rem"
                    >
                        <template #body="{ data }">
                            <div class="flex justify-end">
                                <Button
                                    icon="pi pi-pencil"
                                    severity="secondary"
                                    text
                                    rounded
                                    v-tooltip.top="'Edit'"
                                    @click="handleEdit(data)"
                                    :disabled="isSearching"
                                />
                            </div>
                        </template>
                    </Column>
                </DataTable>

                <div
                    class="mt-4 flex flex-col items-center justify-between gap-4 border-t pt-4 md:flex-row"
                    v-if="activityData.meta.total > 0"
                >
                    <div class="text-sm text-gray-500">
                        Showing {{ activityData.meta.from }} to
                        {{ activityData.meta.to }} of
                        {{ activityData.meta.total }} entries
                        <span v-if="searchQuery" class="ml-2 text-blue-600">
                            (Filtered by search)
                        </span>
                    </div>
                    <Paginator
                        v-if="
                            activityData.meta.total > activityData.meta.per_page
                        "
                        :rows="activityData.meta.per_page"
                        :totalRecords="activityData.meta.total"
                        :first="
                            (activityData.meta.current_page - 1) *
                            activityData.meta.per_page
                        "
                        @page="onPageChange"
                        template="FirstPageLink PrevPageLink PageLinks NextPageLink LastPageLink"
                        class="p-paginator-sm"
                        :disabled="isSearching"
                    />
                </div>
            </template>
        </Card>

        <Dialog
            v-model:visible="showModal"
            :header="isEdit ? 'Edit Activity' : 'New Activity'"
            modal
            :style="{ width: '450px' }"
            class="p-fluid"
        >
            <form
                @submit.prevent="saveActivity"
                class="flex flex-col gap-4 pt-2"
            >
                <div class="field">
                    <label class="mb-1 block font-medium text-gray-700">
                        Activity Name <span class="text-red-500">*</span>
                    </label>
                    <InputText
                        v-model="name"
                        :class="{ 'p-invalid': nameError }"
                        class="w-full"
                        placeholder="e.g. Cash Receipt"
                    />
                    <small
                        class="mt-1 block text-xs text-red-500"
                        v-if="nameError"
                    >
                        {{ nameError }}
                    </small>
                </div>
                <div
                    class="field flex items-center justify-between rounded-lg border bg-gray-50 p-3"
                >
                    <div>
                        <span class="font-medium text-gray-700"
                            >Active Status</span
                        >
                        <p class="mt-1 text-xs text-gray-500">
                            When active, this activity will be available for
                            selection
                        </p>
                    </div>
                    <InputSwitch
                        v-model="status"
                        trueValue="active"
                        falseValue="inactive"
                    />
                </div>
            </form>
            <template #footer>
                <div class="flex justify-end gap-2">
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
                        @click="saveActivity"
                        :loading="inertiaForm.processing"
                        :disabled="inertiaForm.processing"
                    />
                </div>
            </template>
        </Dialog>
    </AppLayout>
</template>
