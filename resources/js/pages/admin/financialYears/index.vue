<script setup>
import { Head, router, useForm } from '@inertiajs/vue3';
import { computed, onMounted, ref, watch } from 'vue';

// --- Frontend Validation Imports ---
import { configure, useField, useForm as useVeeForm } from 'vee-validate';
import * as yup from 'yup';

// --- PrimeVue Imports ---
import AppLayout from '@/layouts/AppLayout.vue';
import Button from 'primevue/button';
import Calendar from 'primevue/calendar';
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

// Register PrimeVue directives
const vTooltip = Tooltip;
const toast = useToast();

configure({
    validateOnBlur: true,
    validateOnChange: true,
});

// ---------------------------------------------
// --- PROPS & INITIAL SETUP ---
// ---------------------------------------------
const props = defineProps({
    financialYears: {
        type: Object,
        required: true,
    },
    flash: {
        type: Object,
        default: () => ({ message: null }),
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

const yearData = computed(() => props.financialYears);
const currentYear = ref(null);

// ---------------------------------------------
// --- FINANCIAL YEAR CRUD FORM LOGIC ---
// ---------------------------------------------

const showCreateYearModal = ref(false);
const isEdit = ref(false);

const yearFormDefaults = {
    name: '',
    start_date: null,
    end_date: null,
    is_active: true,
};

const yearValidationSchema = yup.object({
    name: yup
        .string()
        .required('Financial Year Name is required.')
        .max(50, 'Name cannot exceed 50 characters.'),
    start_date: yup
        .date()
        .required('Start Date is required.')
        .typeError('Invalid date format.'),
    end_date: yup
        .date()
        .required('End Date is required.')
        .min(yup.ref('start_date'), 'End date must be after start date.')
        .typeError('Invalid date format.'),
    is_active: yup.boolean().required(),
});

const {
    handleSubmit: handleYearSubmit,
    resetForm: resetYearForm,
    setErrors: setYearErrors,
} = useVeeForm({
    validationSchema: yearValidationSchema,
    initialValues: yearFormDefaults,
});

const { value: name, errorMessage: nameError } = useField('name');
const { value: start_date, errorMessage: startDateError } =
    useField('start_date');
const { value: end_date, errorMessage: endDateError } = useField('end_date');
const { value: is_active } = useField('is_active');

const yearForm = useForm(yearFormDefaults);

watch(
    () => yearForm.errors,
    (newErrors) => {
        if (Object.keys(newErrors).length > 0) {
            setYearErrors(newErrors);
            toast.add({
                severity: 'error',
                summary: 'Server Error',
                detail: 'Please check the form fields.',
                life: 5000,
            });
        }
    },
    { deep: true },
);

// --- ACTION HANDLERS ---

const handleCreateYear = () => {
    isEdit.value = false;
    currentYear.value = null;
    resetYearForm({ values: yearFormDefaults });
    yearForm.reset();
    showCreateYearModal.value = true;
};

const handleEditYear = (year) => {
    isEdit.value = true;
    currentYear.value = year;

    resetYearForm({
        values: {
            name: year.name,
            start_date: year.start_date ? new Date(year.start_date) : null,
            end_date: year.end_date ? new Date(year.end_date) : null,
            is_active: !!year.is_active,
        },
    });

    Object.assign(yearForm, year);
    showCreateYearModal.value = true;
};

const saveYear = handleYearSubmit((values) => {
    Object.assign(yearForm, values);

    const options = {
        preserveScroll: true,
        onSuccess: () => {
            showCreateYearModal.value = false;
            yearForm.reset();
            resetYearForm();
            toast.add({
                severity: 'success',
                summary: 'Success',
                detail: isEdit.value
                    ? 'Financial Year updated.'
                    : 'Financial Year created.',
                life: 3000,
            });
        },
    };

    if (isEdit.value && currentYear.value.id) {
        yearForm.put(
            route('financial-years.update', {
                financial_year: currentYear.value.id,
            }),
            options,
        );
    } else {
        yearForm.post(route('financial-years.store'), options);
    }
});

// ---------------------------------------------
// --- HELPERS & ROUTING ---
// ---------------------------------------------

const formatDate = (date) => {
    if (!date) return '-';
    return new Date(date).toLocaleDateString('en-GB', {
        day: '2-digit',
        month: 'short',
        year: 'numeric',
    });
};

const onPageChange = (event) => {
    const page = event.page + 1;
    router.get(
        route('financial-years.index', { page: page }),
        {},
        { preserveState: true, replace: true },
    );
};

const route = (name, params) => {
    const id = params && (params.id || params.financial_year);
    if (name === 'financial-years.index')
        return `/financial-years${params?.page ? '?page=' + params.page : ''}`;
    if (name === 'financial-years.store') return '/financial-years';
    if (name === 'financial-years.update' && id)
        return `/financial-years/${id}`;
    if (name === 'financial-years.toggle-status' && id)
        return `/financial-years/${id}/toggle-status`;
    return `/${name.replace(/\./g, '/')}`;
};

const breadcrumbs = [{ title: 'Finance' }, { title: 'Financial Years' }];

const paginatorTotalRecords = computed(() => yearData.value.total || 0);
const paginatorCurrentPage = computed(() => yearData.value.current_page || 1);
const paginatorRows = computed(() => yearData.value.per_page || 10);
const paginatorFirst = computed(
    () => (paginatorCurrentPage.value - 1) * paginatorRows.value,
);

const toggleStatus = (year) => {
    router.patch(
        route('financial-years.toggle-status', { financial_year: year.id }),
        {
            is_active: year.is_active,
        },
        {
            preserveScroll: true,
            onSuccess: () => {
                toast.add({
                    severity: 'success',
                    summary: 'Status Updated',
                    detail: `${year.name} status modified.`,
                    life: 3000,
                });
            },
            onError: () => {
                year.is_active = !year.is_active;
                toast.add({
                    severity: 'error',
                    summary: 'Error',
                    detail: 'Update failed.',
                    life: 3000,
                });
            },
        },
    );
};
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head title="Financial Year Management" />
        <Toast />

        <Card class="border-0 shadow-sm">
            <template #title>
                <div class="flex items-center justify-between px-2">
                    <div>
                        <h2 class="m-0 text-xl font-bold text-gray-900">
                            Financial Years
                        </h2>
                        <small class="font-normal text-gray-500"
                            >Manage accounting periods and active status</small
                        >
                    </div>
                    <Button
                        label="Add New Year"
                        icon="pi pi-plus"
                        severity="primary"
                        @click.stop="handleCreateYear"
                        class="p-button-raised"
                    />
                </div>
            </template>

            <template #content>
                <DataTable
                    :value="yearData.data"
                    dataKey="id"
                    class="p-datatable-sm mt-3"
                    responsiveLayout="scroll"
                    :rows="paginatorRows"
                    stripedRows
                >
                    <template #empty>
                        <div class="p-4 text-center text-gray-500">
                            No financial years found.
                        </div>
                    </template>

                    <Column
                        field="name"
                        header="Year Name"
                        style="min-width: 15rem"
                    >
                        <template #body="{ data }">
                            <span class="font-semibold text-gray-800">{{
                                data.name
                            }}</span>
                        </template>
                    </Column>

                    <Column
                        field="start_date"
                        header="Start Date"
                        style="min-width: 10rem"
                    >
                        <template #body="{ data }">
                            <div class="flex items-center gap-2">
                                <i
                                    class="pi pi-calendar text-xs text-blue-500"
                                ></i>
                                <span>{{ formatDate(data.start_date) }}</span>
                            </div>
                        </template>
                    </Column>

                    <Column
                        field="end_date"
                        header="End Date"
                        style="min-width: 10rem"
                    >
                        <template #body="{ data }">
                            <div class="flex items-center gap-2 text-gray-600">
                                <i
                                    class="pi pi-calendar-times text-xs text-orange-400"
                                ></i>
                                <span>{{ formatDate(data.end_date) }}</span>
                            </div>
                        </template>
                    </Column>

                    <Column
                        field="is_active"
                        header="Status"
                        headerClass="text-center"
                        bodyClass="text-center"
                        style="width: 12rem"
                    >
                        <template #body="{ data }">
                            <div class="flex flex-col items-center gap-2">
                                <InputSwitch
                                    v-model="data.is_active"
                                    @change="toggleStatus(data)"
                                    class="scale-90"
                                />
                                <Tag
                                    :severity="
                                        data.is_active ? 'success' : 'danger'
                                    "
                                    :value="
                                        data.is_active ? 'Active' : 'Inactive'
                                    "
                                    class="text-[10px] tracking-wider uppercase"
                                />
                            </div>
                        </template>
                    </Column>

                    <Column
                        header="Actions"
                        bodyClass="text-right"
                        style="width: 6rem"
                    >
                        <template #body="{ data }">
                            <Button
                                icon="pi pi-pencil"
                                severity="secondary"
                                text
                                rounded
                                v-tooltip.left="'Edit Details'"
                                @click="handleEditYear(data)"
                            />
                        </template>
                    </Column>
                </DataTable>

                <div
                    class="mt-6 flex items-center justify-between border-t pt-4"
                    v-if="paginatorTotalRecords > 0"
                >
                    <span class="text-sm text-gray-500">
                        Showing {{ paginatorFirst + 1 }} to
                        {{
                            Math.min(
                                paginatorFirst + paginatorRows,
                                paginatorTotalRecords,
                            )
                        }}
                        of {{ paginatorTotalRecords }} entries
                    </span>
                    <Paginator
                        v-if="paginatorTotalRecords > paginatorRows"
                        :rows="paginatorRows"
                        :totalRecords="paginatorTotalRecords"
                        :first="paginatorFirst"
                        @page="onPageChange"
                        template="FirstPageLink PrevPageLink PageLinks NextPageLink LastPageLink"
                        class="p-paginator-sm"
                    />
                </div>
            </template>
        </Card>

        <Dialog
            v-model:visible="showCreateYearModal"
            :style="{ width: '500px' }"
            :header="
                isEdit ? 'Update Financial Year' : 'Create New Financial Year'
            "
            :modal="true"
            :draggable="false"
            class="p-fluid"
        >
            <form @submit.prevent="saveYear" class="flex flex-col gap-4">
                <div class="field mb-3">
                    <label
                        for="name"
                        class="mb-1 block font-medium text-gray-700"
                        >Financial Year Name
                        <span class="text-red-500">*</span></label
                    >
                    <InputText
                        id="name"
                        v-model="name"
                        :class="{ 'p-invalid': nameError }"
                        placeholder="e.g., FY-2024/2025"
                        class="w-full"
                    />
                    <small
                        class="mt-1 block text-xs text-red-500"
                        v-if="nameError"
                        >{{ nameError }}</small
                    >
                </div>

                <div class="mb-3 grid grid-cols-1 gap-4 md:grid-cols-2">
                    <div class="field">
                        <label
                            for="start_date"
                            class="mb-1 block font-medium text-gray-700"
                            >Start Date
                            <span class="text-red-500">*</span></label
                        >
                        <Calendar
                            id="start_date"
                            v-model="start_date"
                            :class="{ 'p-invalid': startDateError }"
                            dateFormat="dd/mm/yy"
                            showIcon
                            showButtonBar
                            class="w-full"
                        />
                        <small
                            class="mt-1 block text-xs text-red-500"
                            v-if="startDateError"
                            >{{ startDateError }}</small
                        >
                    </div>

                    <div class="field">
                        <label
                            for="end_date"
                            class="mb-1 block font-medium text-gray-700"
                            >End Date <span class="text-red-500">*</span></label
                        >
                        <Calendar
                            id="end_date"
                            v-model="end_date"
                            :class="{ 'p-invalid': endDateError }"
                            dateFormat="dd/mm/yy"
                            showIcon
                            showButtonBar
                            class="w-full"
                        />
                        <small
                            class="mt-1 block text-xs text-red-500"
                            v-if="endDateError"
                            >{{ endDateError }}</small
                        >
                    </div>
                </div>

                <div
                    class="field mt-2 flex items-center justify-between rounded-lg border bg-gray-50 p-3"
                >
                    <div>
                        <label
                            for="is_active"
                            class="cursor-pointer font-medium text-gray-700"
                            >Set as Active Year</label
                        >
                        <p class="mt-1 text-xs text-gray-500">
                            When active, this will be the default year for
                            transactions
                        </p>
                    </div>
                    <InputSwitch id="is_active" v-model="is_active" />
                </div>
            </form>

            <template #footer>
                <div class="flex justify-end gap-2 border-t pt-3">
                    <Button
                        label="Cancel"
                        icon="pi pi-times"
                        severity="secondary"
                        outlined
                        @click="showCreateYearModal = false"
                    />
                    <Button
                        :label="isEdit ? 'Update' : 'Create'"
                        icon="pi pi-check"
                        severity="primary"
                        @click="saveYear"
                        :loading="yearForm.processing"
                        :disabled="yearForm.processing"
                    />
                </div>
            </template>
        </Dialog>
    </AppLayout>
</template>
