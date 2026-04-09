<script setup>
import { Head, router, useForm } from '@inertiajs/vue3';
import debounce from 'lodash/debounce';
import { configure, useField, useForm as useVeeForm } from 'vee-validate';
import { computed, onMounted, ref, watch } from 'vue';
import * as yup from 'yup';

// PrimeVue
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
    banks: { type: Object, required: true },
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

const bankData = computed(() => props.banks);
const currentBank = ref(null);
const showModal = ref(false);
const isEdit = ref(false);
const searchQuery = ref(props.filters?.search || '');
const isSearching = ref(false);

// --- Validation Schema ---
const validationSchema = yup.object({
    name: yup.string().required('Bank Name is required.').max(255),
    code: yup.string().required('Bank Code is required.').max(20),
    initials: yup.string().nullable().max(10),
    status: yup.number().required(),
});

const { handleSubmit, resetForm, setErrors } = useVeeForm({
    validationSchema: validationSchema,
    initialValues: { name: '', code: '', initials: '', status: 1 },
});

const { value: name, errorMessage: nameError } = useField('name');
const { value: code, errorMessage: codeError } = useField('code');
const { value: initials, errorMessage: initialsError } = useField('initials');
const { value: status } = useField('status');

const bankForm = useForm({ name: '', code: '', initials: '', status: 1 });

watch(
    () => bankForm.errors,
    (newErrors) => {
        if (Object.keys(newErrors).length > 0) {
            setErrors(newErrors);
            toast.add({
                severity: 'error',
                summary: 'Error',
                detail: 'Validation failed.',
                life: 5000,
            });
        }
    },
    { deep: true },
);

// --- Search Functionality ---
const performSearch = debounce(() => {
    if (searchQuery.value.trim() === '') {
        router.get(
            '/banks',
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
        '/banks',
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
        '/banks',
        {},
        {
            preserveState: true,
            preserveScroll: true,
            replace: true,
            onFinish: () => (isSearching.value = false),
        },
    );
};

// --- Handlers ---
const handleCreate = () => {
    isEdit.value = false;
    currentBank.value = null;
    resetForm({ values: { name: '', code: '', initials: '', status: 1 } });
    bankForm.reset();
    showModal.value = true;
};

const handleEdit = (bank) => {
    isEdit.value = true;
    currentBank.value = bank;
    resetForm({ values: { ...bank } });
    Object.assign(bankForm, bank);
    showModal.value = true;
};

const saveBank = handleSubmit((values) => {
    Object.assign(bankForm, values);
    const options = {
        preserveScroll: true,
        onSuccess: () => {
            showModal.value = false;
            toast.add({
                severity: 'success',
                summary: 'Success',
                detail: isEdit.value
                    ? 'Bank updated successfully'
                    : 'Bank created successfully',
                life: 3000,
            });
        },
        onError: (errors) => {
            toast.add({
                severity: 'error',
                summary: 'Error',
                detail: 'Please check the form for errors',
                life: 3000,
            });
        },
    };

    if (isEdit.value && currentBank.value?.id) {
        bankForm.put(`/banks/${currentBank.value.id}`, options);
    } else {
        bankForm.post('/banks', options);
    }
});

const toggleStatus = (bank) => {
    const newStatus = bank.status === 1 ? 0 : 1;
    router.patch(
        `/banks/${bank.id}/toggle-status`,
        {
            status: newStatus,
        },
        {
            preserveScroll: true,
            onSuccess: () => {
                toast.add({
                    severity: 'success',
                    summary: 'Status Updated',
                    detail: `Status changed to ${newStatus === 1 ? 'Active' : 'Inactive'}`,
                    life: 2000,
                });
            },
            onError: () => {
                bank.status = bank.status === 1 ? 0 : 1;
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

    router.get('/banks', params, {
        preserveState: true,
        preserveScroll: true,
    });
};

const breadcrumbs = [{ title: 'Finance' }, { title: 'Bank Management' }];

// Helper functions
const getStatusText = (statusValue) => {
    return statusValue === 1 ? 'Active' : 'Inactive';
};

const getStatusSeverity = (statusValue) => {
    return statusValue === 1 ? 'success' : 'danger';
};
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head title="Bank Management" />
        <Toast />

        <Card class="border-0 shadow-sm">
            <template #title>
                <div
                    class="flex flex-col justify-between gap-4 md:flex-row md:items-center"
                >
                    <div>
                        <h2 class="m-0 text-xl font-bold text-gray-900">
                            Bank Management
                        </h2>
                        <small class="font-normal text-gray-500"
                            >Manage financial institutions and their
                            details</small
                        >
                    </div>
                    <div class="flex items-center gap-3">
                        <div class="relative">
                            <span class="p-input-icon-left">
                                <i class="pi pi-search text-gray-400" />
                                <InputText
                                    v-model="searchQuery"
                                    placeholder="Search by name, code or initials..."
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
                            label="Add Bank"
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
                                :value="`${bankData.meta.total} result${bankData.meta.total !== 1 ? 's' : ''}`"
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
                    :value="bankData.data"
                    class="p-datatable-sm mt-3"
                    stripedRows
                    :loading="isSearching"
                    dataKey="id"
                    :rows="bankData.meta.per_page"
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
                                        : 'No banks found.'
                                }}
                            </p>
                            <p
                                v-if="searchQuery"
                                class="mt-1 text-sm text-gray-400"
                            >
                                Try different keywords or clear your search
                            </p>
                            <p v-else class="mt-1 text-sm text-gray-400">
                                Click "Add Bank" to create your first bank
                                record
                            </p>
                        </div>
                    </template>

                    <template #loading>
                        <div class="p-8 text-center">
                            <i
                                class="pi pi-spin pi-spinner text-2xl text-gray-400"
                            ></i>
                            <p class="mt-2 text-gray-500">Loading banks...</p>
                        </div>
                    </template>

                    <Column
                        field="name"
                        header="Bank Name"
                        style="min-width: 200px"
                    >
                        <template #body="{ data }">
                            <div class="flex flex-col">
                                <span class="font-semibold text-gray-800">{{
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
                    <Column field="code" header="Code" style="min-width: 100px">
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
                    <Column
                        field="initials"
                        header="Initials"
                        style="min-width: 100px"
                    >
                        <template #body="{ data }">
                            <span class="font-medium text-gray-700">{{
                                data.initials || '-'
                            }}</span>
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
                                    :modelValue="data.status === 1"
                                    @update:modelValue="toggleStatus(data)"
                                    class="scale-90"
                                    :disabled="isSearching"
                                />
                                <Tag
                                    :severity="getStatusSeverity(data.status)"
                                    :value="getStatusText(data.status)"
                                    class="text-xs uppercase"
                                />
                            </div>
                        </template>
                    </Column>
                    <Column
                        header="Actions"
                        bodyClass="text-right"
                        style="width: 80px"
                    >
                        <template #body="{ data }">
                            <Button
                                icon="pi pi-pencil"
                                severity="secondary"
                                text
                                rounded
                                v-tooltip.top="'Edit Bank'"
                                @click="handleEdit(data)"
                                :disabled="isSearching"
                            />
                        </template>
                    </Column>
                </DataTable>

                <div
                    class="mt-6 flex flex-col items-center justify-between gap-4 border-t pt-4 md:flex-row"
                    v-if="bankData.meta.total > 0"
                >
                    <div class="text-sm text-gray-500">
                        Showing {{ bankData.meta.from }} to
                        {{ bankData.meta.to }} of
                        {{ bankData.meta.total }} entries
                        <span v-if="searchQuery" class="ml-2 text-blue-600">
                            (Filtered by search)
                        </span>
                    </div>
                    <Paginator
                        v-if="bankData.meta.total > bankData.meta.per_page"
                        :rows="bankData.meta.per_page"
                        :totalRecords="bankData.meta.total"
                        :first="
                            (bankData.meta.current_page - 1) *
                            bankData.meta.per_page
                        "
                        @page="onPageChange"
                        template="FirstPageLink PrevPageLink PageLinks NextPageLink LastPageLink"
                        class="p-paginator-sm"
                        :disabled="isSearching"
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
            :header="isEdit ? 'Edit Bank Details' : 'Add New Bank'"
            class="p-fluid"
        >
            <form @submit.prevent="saveBank" class="flex flex-col gap-5 pt-2">
                <div class="field">
                    <label
                        for="name"
                        class="mb-1 block font-medium text-gray-700"
                    >
                        Bank Name <span class="text-red-500">*</span>
                    </label>
                    <InputText
                        id="name"
                        v-model="name"
                        :class="{ 'p-invalid': nameError }"
                        placeholder="e.g., First Bank of Nigeria"
                        class="w-full"
                    />
                    <small
                        class="mt-1 block text-xs text-red-500"
                        v-if="nameError"
                    >
                        {{ nameError }}
                    </small>
                </div>

                <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                    <div class="field">
                        <label
                            for="code"
                            class="mb-1 block font-medium text-gray-700"
                        >
                            Bank Code <span class="text-red-500">*</span>
                        </label>
                        <InputText
                            id="code"
                            v-model="code"
                            :class="{ 'p-invalid': codeError }"
                            placeholder="e.g., 011"
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
                            for="initials"
                            class="mb-1 block font-medium text-gray-700"
                        >
                            Bank Initials
                        </label>
                        <InputText
                            id="initials"
                            v-model="initials"
                            :class="{ 'p-invalid': initialsError }"
                            placeholder="e.g., FBN"
                            class="w-full"
                        />
                        <small
                            class="mt-1 block text-xs text-red-500"
                            v-if="initialsError"
                        >
                            {{ initialsError }}
                        </small>
                    </div>
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
                            When active, this bank will be available for
                            selection
                        </p>
                    </div>
                    <InputSwitch
                        id="status"
                        v-model="status"
                        trueValue="1"
                        falseValue="0"
                    />
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
                        @click="saveBank"
                        :loading="bankForm.processing"
                        :disabled="bankForm.processing"
                    />
                </div>
            </template>
        </Dialog>
    </AppLayout>
</template>
