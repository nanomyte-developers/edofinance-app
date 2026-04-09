<script setup>
import { computed, ref, watch, onMounted } from 'vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import { useForm as useVeeForm, useField, configure } from 'vee-validate';
import * as yup from 'yup';

// PrimeVue
import Card from 'primevue/card';
import DataTable from 'primevue/datatable';
import Column from 'primevue/column';
import Button from 'primevue/button';
import Paginator from 'primevue/paginator';
import InputText from 'primevue/inputtext';
import InputNumber from 'primevue/inputnumber';
import Dropdown from 'primevue/dropdown';
import AppLayout from '@/layouts/AppLayout.vue';
import Dialog from 'primevue/dialog';
import Toast from 'primevue/toast';
import { useToast } from 'primevue/usetoast';
import Tooltip from 'primevue/tooltip';

const vTooltip = Tooltip;
const toast = useToast();

configure({ validateOnBlur: true, validateOnChange: true });

const props = defineProps({
    balances: { type: Object, required: true },
    mdas: { type: Array, required: true },
    banks: { type: Array, required: true },
    flash: { type: Object, default: () => ({ message: null }) },
});

onMounted(() => {
    if (props.flash.message) {
        toast.add({ severity: 'success', summary: 'Success', detail: props.flash.message, life: 3000 });
    }
});

// FIX: Handle Laravel Resource nesting (data) and Pagination (meta)
const balanceData = computed(() => props.balances.data || []);
const pagination = computed(() => props.balances.meta || {});

const currentBalance = ref(null);
const showModal = ref(false);
const isEdit = ref(false);

// --- Validation Schema ---
const validationSchema = yup.object({
    mda_id: yup.number().required('MDA is required.'),
    bank_id: yup.number().required('Bank is required.'),
    account_number: yup.string().required('Account Number is required.').max(50),
    balance_previous_year: yup.number().nullable().transform((value) => (isNaN(value) ? 0 : value)),
    balance_current_year: yup.number().nullable().transform((value) => (isNaN(value) ? 0 : value)),
});

const { handleSubmit, resetForm, setErrors } = useVeeForm({
    validationSchema: validationSchema,
    initialValues: {
        mda_id: null,
        bank_id: null,
        account_number: '',
        balance_previous_year: 0,
        balance_current_year: 0
    },
});

const { value: mda_id, errorMessage: mdaError } = useField('mda_id');
const { value: bank_id, errorMessage: bankError } = useField('bank_id');
const { value: account_number, errorMessage: accountError } = useField('account_number');
const { value: balance_previous_year } = useField('balance_previous_year');
const { value: balance_current_year } = useField('balance_current_year');

const balanceForm = useForm({
    mda_id: null,
    bank_id: null,
    account_number: '',
    balance_previous_year: 0,
    balance_current_year: 0
});

watch(() => balanceForm.errors, (newErrors) => {
    if (Object.keys(newErrors).length > 0) {
        setErrors(newErrors);
        toast.add({ severity: 'error', summary: 'Error', detail: 'Validation failed.', life: 5000 });
    }
}, { deep: true });

// --- Handlers ---
const handleCreate = () => {
    isEdit.value = false;
    currentBalance.value = null;
    resetForm({ values: { mda_id: null, bank_id: null, account_number: '', balance_previous_year: 0, balance_current_year: 0 } });
    balanceForm.reset();
    showModal.value = true;
};

const handleEdit = (balance) => {
    isEdit.value = true;
    currentBalance.value = balance;
    const initialData = {
        mda_id: balance.mda_id,
        bank_id: balance.bank_id,
        account_number: balance.account_number,
        balance_previous_year: parseFloat(balance.balance_previous_year) || 0,
        balance_current_year: parseFloat(balance.balance_current_year) || 0
    };
    resetForm({ values: initialData });
    balanceForm.setData(initialData);
    showModal.value = true;
};

const saveBalance = handleSubmit((values) => {
    balanceForm.setData(values);
    const options = {
        preserveScroll: true,
        onSuccess: () => {
            showModal.value = false;
            toast.add({ severity: 'success', summary: 'Success', detail: isEdit.value ? 'Balance updated' : 'Balance recorded' });
        }
    };

    if (isEdit.value) {
        balanceForm.put(route('mda-bank-balances.update', currentBalance.value.id), options);
    } else {
        balanceForm.post(route('mda-bank-balances.store'), options);
    }
});

const onPageChange = (event) => {
    router.get(route('mda-bank-balances.index'), { page: event.page + 1 }, { preserveState: true });
};

const route = (name, id = null) => {
    const routes = {
        'mda-bank-balances.index': '/mda-bank-balances',
        'mda-bank-balances.store': '/mda-bank-balances',
        'mda-bank-balances.update': `/mda-bank-balances/${id}`,
    };
    return routes[name];
};

// FIX: Prevent NaN by ensuring the value is a valid number before formatting
const formatCurrency = (value) => {
    const amount = parseFloat(value);
    return new Intl.NumberFormat('en-NG', {
        style: 'currency',
        currency: 'NGN'
    }).format(isNaN(amount) ? 0 : amount);
};

const breadcrumbs = [{ title: 'Finance' }, { title: 'MDA Bank Balances' }];
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head title="MDA Bank Balances" />
        <Toast />

        <Card class="shadow-sm border-0">
            <template #title>
                <div class="flex justify-between items-center px-2">
                    <div>
                        <h2 class="text-xl font-bold text-gray-900 m-0">MDA Bank Balances</h2>
                        <small class="text-gray-500">Track year-on-year bank account balances</small>
                    </div>
                    <Button label="Add Balance" icon="pi pi-plus" @click="handleCreate" />
                </div>
            </template>

            <template #content>
                <DataTable :value="balanceData" class="p-datatable-sm mt-3" stripedRows responsiveLayout="stack">
                    <Column header="MDA">
                        <template #body="{ data }">
                            {{ data.mda?.name || data.mda_name || 'N/A' }}
                        </template>
                    </Column>
                    <Column header="Title">
                        <template #body="{ data }">
                            {{ data.title || 'N/A' }}
                        </template>
                    </Column>
                    <Column header="Bank">
                        <template #body="{ data }">
                            {{ data.bank?.name || data.bank_name || 'N/A' }}
                        </template>
                    </Column>

                    <Column field="account_number" header="Account Number" />

                    <Column header="Prev. Year Balance">
                        <template #body="{ data }">
                            {{ formatCurrency(data.balance_previous_year) }}
                        </template>
                    </Column>

                    <Column header="Curr. Year Balance">
                        <template #body="{ data }">
                            <span class="font-bold text-primary">{{ formatCurrency(data.balance_current_year) }}</span>
                        </template>
                    </Column>

                    <Column header="Actions" bodyClass="text-right">
                        <template #body="{ data }">
                            <Button icon="pi pi-pencil" text rounded v-tooltip="'Edit'" @click="handleEdit(data)" />
                        </template>
                    </Column>
                </DataTable>

                <Paginator
                    v-if="pagination.total > pagination.per_page"
                    :rows="pagination.per_page"
                    :totalRecords="pagination.total"
                    @page="onPageChange"
                    class="mt-4"
                />
            </template>
        </Card>

        <Dialog v-model:visible="showModal" :header="isEdit ? 'Edit Balance Record' : 'Add New Balance Record'" modal :style="{ width: '500px' }">
            <form @submit.prevent="saveBalance" class="flex flex-col gap-4">
                <div class="field">
                    <label class="font-bold">Select MDA</label>
                    <Dropdown v-model="mda_id" :options="mdas" optionLabel="name" optionValue="id" filter placeholder="Search MDA" :class="{'p-invalid': mdaError}" class="w-full" />
                    <small class="p-error">{{ mdaError }}</small>
                </div>

                <div class="field">
                    <label class="font-bold">Select Bank</label>
                    <Dropdown v-model="bank_id" :options="banks" optionLabel="name" optionValue="id" filter placeholder="Search Bank" :class="{'p-invalid': bankError}" class="w-full" />
                    <small class="p-error">{{ bankError }}</small>
                </div>

                <div class="field">
                    <label class="font-bold">Account Number</label>
                    <InputText v-model="account_number" :class="{'p-invalid': accountError}" class="w-full" placeholder="e.g. 1010020202" />
                    <small class="p-error">{{ accountError }}</small>
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div class="field">
                        <label class="font-bold">Previous Year Balance</label>
                        <InputNumber v-model="balance_previous_year" mode="decimal" :minFractionDigits="2" class="w-full" />
                    </div>
                    <div class="field">
                        <label class="font-bold">Current Year Balance</label>
                        <InputNumber v-model="balance_current_year" mode="decimal" :minFractionDigits="2" class="w-full" />
                    </div>
                </div>
            </form>
            <template #footer>
                <Button label="Cancel" text @click="showModal = false" />
                <Button :label="isEdit ? 'Update Balance' : 'Save Balance'" :loading="balanceForm.processing" @click="saveBalance" />
            </template>
        </Dialog>
    </AppLayout>
</template>
