<script setup>
import { Head, router, useForm } from '@inertiajs/vue3';
import { configure, useField, useForm as useVeeForm } from 'vee-validate';
import { onMounted, ref, watch } from 'vue';
import * as yup from 'yup';

// PrimeVue Components
import Button from 'primevue/button';
import Card from 'primevue/card';
import Column from 'primevue/column';
import DataTable from 'primevue/datatable';
import Dialog from 'primevue/dialog';
import Dropdown from 'primevue/dropdown';
import InputNumber from 'primevue/inputnumber';
import InputSwitch from 'primevue/inputswitch';
import InputText from 'primevue/inputtext';
import Paginator from 'primevue/paginator';
import Tag from 'primevue/tag';
import Toast from 'primevue/toast';
import { useToast } from 'primevue/usetoast';

// Layout & Utils
import AppLayout from '@/layouts/AppLayout.vue';
import { debounce } from 'lodash';

// --- PROPS DEFINITION (Only one call allowed) ---
const props = defineProps({
    balances: {
        type: Object,
        default: () => ({ data: [], meta: { total: 0, per_page: 10 } }),
    },
    financialYears: {
        type: Array,
        default: () => [],
    },
    flash: {
        type: Object,
        default: () => ({ message: null }),
    },
});

const toast = useToast();
const search = ref('');

// --- Search Logic ---
watch(
    search,
    debounce((value) => {
        router.get(
            '/cash-book',
            { search: value },
            { preserveState: true, replace: true, preserveScroll: true },
        );
    }, 300),
);

configure({ validateOnBlur: true, validateOnChange: true });

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

// --- State Management ---
const showModal = ref(false);
const showGenerateModal = ref(false);
const currentId = ref(null);
const selectedBankInfo = ref({});

// Form for Generate Year Balances
const generateForm = useForm({
    financial_year: null,
});

// Form for Editing Individual Balance
const mainForm = useForm({
    bank_activity_id: null,
    amount: 0,
    status: 1,
});

// --- Validation (Vee-Validate) ---
const validationSchema = yup.object({
    bank_activity_id: yup.number().required(),
    amount: yup.number().required(), //.min(0, 'Amount cannot be negative'),
    status: yup.number().required(),
});

const { handleSubmit, resetForm, setErrors } = useVeeForm({
    validationSchema: validationSchema,
});

const { value: amount, errorMessage: amountError } = useField('amount');

// --- Methods / Handlers ---

const handleOpenGenerate = () => {
    generateForm.reset();
    generateForm.clearErrors();
    showGenerateModal.value = true;
};

const submitGenerate = () => {
    generateForm.post('/cash-book/generate', {
        preserveScroll: true,
        onSuccess: () => {
            showGenerateModal.value = false;
            toast.add({
                severity: 'success',
                summary: 'Success',
                detail: 'Balances generated',
            });
        },
    });
};

const handleEdit = (data) => {
    currentId.value = data.id;
    selectedBankInfo.value = data;
    resetForm({
        values: {
            bank_activity_id: data.bank_activity_id,
            amount: data.amount,
            status: data.status,
        },
    });
    showModal.value = true;
};

const saveRecord = handleSubmit((values) => {
    Object.assign(mainForm, values);
    mainForm.put(`/cash-book/${currentId.value}`, {
        onSuccess: () => {
            showModal.value = false;
            toast.add({
                severity: 'success',
                summary: 'Updated',
                detail: 'Balance updated',
            });
        },
        onError: (errs) => setErrors(errs),
    });
});

const toggleStatus = (data) => {
    router.patch(
        `/cash-book/${data.id}/toggle-status`,
        {},
        { preserveScroll: true },
    );
};

const onPageChange = (event) => {
    router.get(
        '/cash-book',
        { page: event.page + 1, search: search.value },
        { preserveState: true },
    );
};

// --- Currency Formatter ---
const formatCurrency = (val) => {
    return new Intl.NumberFormat('en-NG', {
        style: 'currency',
        currency: 'NGN',
    }).format(val);
};

const breadcrumbs = [{ title: 'Finance' }, { title: 'Cash Book Balance Bfw' }];
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head title="Cash Book Balance" />
        <Toast />

        <Card class="border-0 shadow-sm">
            <template #title>
                <div
                    class="flex-column flex items-center justify-between gap-3 px-2 md:flex-row"
                >
                    <div>
                        <h2 class="m-0 text-xl font-bold text-gray-900">
                            Cash Book Balances
                        </h2>
                        <small class="text-gray-500"
                            >Manage Balance Brought Forward</small
                        >
                    </div>

                    <div class="flex items-center gap-3">
                        <span class="p-input-icon-left">
                            <i class="pi pi-search" />
                            <InputText
                                v-model="search"
                                placeholder="Search..."
                                class="p-inputtext-sm"
                            />
                        </span>
                        <Button
                            label="Generate Year Balances"
                            icon="pi pi-plus-circle"
                            severity="success"
                            @click="handleOpenGenerate"
                        />
                    </div>
                </div>
            </template>

            <template #content>
                <DataTable
                    :value="props.balances?.data || []"
                    class="p-datatable-sm mt-3"
                    stripedRows
                    responsiveLayout="scroll"
                >
                    <Column field="financial_year" header="Financial Year" />
                    <Column field="bank_name" header="Bank Name" />
                    <Column field="title" header="Account Title." />
                    <Column field="account_number" header="Account No." />
                    <Column field="amount" header="Amount">
                        <template #body="{ data }">
                            <span class="font-bold text-gray-800">{{
                                formatCurrency(data.amount)
                            }}</span>
                        </template>
                    </Column>
                    <Column header="Status">
                        <template #body="{ data }">
                            <div class="flex items-center gap-2">
                                <InputSwitch
                                    v-model="data.status"
                                    :trueValue="1"
                                    :falseValue="0"
                                    @change="toggleStatus(data)"
                                />
                                <Tag
                                    :severity="
                                        data.status === 1 ? 'success' : 'danger'
                                    "
                                    :value="
                                        data.status === 1
                                            ? 'Active'
                                            : 'Inactive'
                                    "
                                />
                            </div>
                        </template>
                    </Column>
                    <Column header="Actions" bodyClass="text-right">
                        <template #body="{ data }">
                            <Button
                                icon="pi pi-pencil"
                                class="p-button-rounded p-button-text p-button-info"
                                @click="handleEdit(data)"
                            />
                        </template>
                    </Column>
                </DataTable>

                <Paginator
                    v-if="
                        props.balances?.meta?.total >
                        props.balances?.meta?.per_page
                    "
                    :rows="props.balances.meta.per_page"
                    :totalRecords="props.balances.meta.total"
                    @page="onPageChange"
                    class="mt-4"
                />
            </template>
        </Card>

        <Dialog
            v-model:visible="showGenerateModal"
            header="Create Year Accounts Balance Bfw"
            modal
            :style="{ width: '400px' }"
        >
            <div class="mt-2 flex flex-col gap-4">
                <div class="field">
                    <label class="mb-2 block text-sm font-bold"
                        >Select Financial Year</label
                    >
                    <Dropdown
                        v-model="generateForm.financial_year"
                        :options="props.financialYears"
                        optionLabel="name"
                        optionValue="name"
                        placeholder="Select a Year"
                        class="w-full"
                        :class="{
                            'p-invalid': generateForm.errors.financial_year,
                        }"
                    />
                    <small
                        class="p-error"
                        v-if="generateForm.errors.financial_year"
                    >
                        {{ generateForm.errors.financial_year }}
                    </small>
                </div>
            </div>
            <template #footer>
                <Button
                    label="Cancel"
                    text
                    @click="showGenerateModal = false"
                />
                <Button
                    label="Generate"
                    :loading="generateForm.processing"
                    @click="submitGenerate"
                />
            </template>
        </Dialog>

        <Dialog
            v-model:visible="showModal"
            header="Edit Balance"
            modal
            :style="{ width: '450px' }"
        >
            <div
                class="mb-4 rounded-lg border border-gray-200 bg-gray-50 p-3 text-sm"
            >
                <p><strong>Bank:</strong> {{ selectedBankInfo.bank_name }}</p>
                <p>
                    <strong>Account Title:</strong> {{ selectedBankInfo.title }}
                </p>
                <p>
                    <strong>Account No:</strong>
                    {{ selectedBankInfo.account_number }}
                </p>
            </div>

            <div class="field">
                <label class="mb-1 block text-sm font-bold"
                    >Balance Amount</label
                >
                <InputNumber
                    v-model="amount"
                    mode="currency"
                    currency="NGN"
                    locale="en-NG"
                    class="w-full"
                    autofocus
                />
                <small class="p-error" v-if="amountError">{{
                    amountError
                }}</small>
            </div>
            <template #footer>
                <Button label="Cancel" text @click="showModal = false" />
                <Button
                    label="Update Balance"
                    :loading="mainForm.processing"
                    @click="saveRecord"
                />
            </template>
        </Dialog>
    </AppLayout>
</template>
