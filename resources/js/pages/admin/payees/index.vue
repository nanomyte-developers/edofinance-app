<script setup>
import { computed, ref, watch, onMounted } from 'vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import { useForm as useVeeForm, useField, configure } from 'vee-validate';
import * as yup from 'yup';

// PrimeVue components
import Card from 'primevue/card';
import DataTable from 'primevue/datatable';
import Column from 'primevue/column';
import Button from 'primevue/button';
import Tag from 'primevue/tag';
import Paginator from 'primevue/paginator';
import InputText from 'primevue/inputtext';
import InputSwitch from 'primevue/inputswitch';
import AppLayout from '@/layouts/AppLayout.vue';
import Dialog from 'primevue/dialog';
import Toast from 'primevue/toast';
import { useToast } from 'primevue/usetoast';
import Tooltip from 'primevue/tooltip';

const vTooltip = Tooltip;
const toast = useToast();

const props = defineProps({
    payees: Object,
    flash: Object,
    filters: Object, // Added filters prop
});

const searchQuery = ref(props.filters.search || '');

// Watch search query and update the list
watch(searchQuery, (value) => {
    router.get('/payees',
        { search: value },
        { preserveState: true, replace: true }
    );
});

onMounted(() => {
    if (props.flash?.message) {
        toast.add({ severity: 'success', summary: 'Success', detail: props.flash.message, life: 3000 });
    }
});

// --- State & Validation ---
const showModal = ref(false);
const isEdit = ref(false);
const currentPayee = ref(null);

const schema = yup.object({
    name: yup.string().required('Name is required.').max(255),
    status: yup.boolean().required(),
});

const { handleSubmit, resetForm, setErrors } = useVeeForm({
    validationSchema: schema,
    initialValues: { name: '', status: true },
});

const { value: name, errorMessage: nameError } = useField('name');
const { value: status } = useField('status');
const inertiaForm = useForm({ name: '', status: true });

// --- Handlers ---
const handleCreate = () => {
    isEdit.value = false;
    resetForm({ values: { name: '', status: true } });
    showModal.value = true;
};

const handleEdit = (payee) => {
    isEdit.value = true;
    currentPayee.value = payee;
    resetForm({ values: { name: payee.name, status: payee.status } });
    showModal.value = true;
};

const savePayee = handleSubmit((values) => {
    Object.assign(inertiaForm, values);
    const options = {
        onSuccess: () => {
            showModal.value = false;
            toast.add({ severity: 'success', summary: 'Success', detail: 'Action completed', life: 3000 });
        },
        onError: (err) => setErrors(err)
    };

    if (isEdit.value) {
        inertiaForm.put(`/payees/${currentPayee.value.id}`, options);
    } else {
        inertiaForm.post('/payees', options);
    }
});

const toggleStatus = (payee) => {
    router.patch(`/payees/${payee.id}/toggle-status`, {}, { preserveScroll: true });
};

const onPageChange = (e) => router.get(`/payees?page=${e.page + 1}`, {}, { preserveState: true });

const breadcrumbs = [{ title: 'Finance' }, { title: 'Payees' }];
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head title="Payee Management" />
        <Toast />

        <Card class="shadow-sm border-0">
            <template #title>
                <div class="flex flex-col md:flex-row justify-between items-start md:items-center px-2 gap-4">
                    <div>
                        <h2 class="text-xl font-bold text-gray-900 m-0">Payees</h2>
                        <small class="text-gray-500">Manage payment recipients</small>
                    </div>

                    <div class="flex items-center gap-3 w-full md:w-auto">
                        <span class="p-input-icon-left w-full md:w-80">
                            <i class="pi pi-search" />
                            <InputText
                                v-model="searchQuery"
                                placeholder="Search by name, status, or ID..."
                                class="w-full p-inputtext-sm"
                            />
                        </span>

                        <Button
                            label="Add Payee"
                            icon="pi pi-plus"
                            @click="handleCreate"
                            class="p-button-sm"
                        />
                    </div>
                </div>
            </template>

            <template #content>
                <DataTable :value="payees.data" class="p-datatable-sm mt-3" stripedRows>
                    <Column field="name" header="Payee Name" />
                    <Column header="Status" bodyClass="text-center">
                        <template #body="{ data }">
                            <div class="flex flex-col items-center gap-2">
                                <InputSwitch v-model="data.status" @change="toggleStatus(data)" class="scale-90" />
                                <Tag :severity="data.status ? 'success' : 'danger'" :value="data.status ? 'Active' : 'Inactive'" />
                            </div>
                        </template>
                    </Column>
                    <Column header="Actions" bodyClass="text-right">
                        <template #body="{ data }">
                            <Button icon="pi pi-pencil" text rounded v-tooltip.left="'Edit'" @click="handleEdit(data)" />
                        </template>
                    </Column>
                </DataTable>

                <Paginator
                    :rows="payees.meta.per_page"
                    :totalRecords="payees.meta.total"
                    :first="(payees.meta.current_page - 1) * payees.meta.per_page"
                    @page="onPageChange"
                    class="mt-4"
                />
            </template>
        </Card>

        <Dialog v-model:visible="showModal" :header="isEdit ? 'Edit Payee' : 'New Payee'" modal :style="{ width: '400px' }">
            <div class="flex flex-col gap-4 pt-2">
                <div class="field">
                    <label class="font-bold block mb-2">Name</label>
                    <InputText v-model="name" :class="{'p-invalid': nameError}" class="w-full" />
                    <small class="text-red-500" v-if="nameError">{{ nameError }}</small>
                </div>
                <div class="field flex items-center justify-between p-3 bg-gray-50 rounded-lg border">
                    <span class="font-bold">Active Status</span>
                    <InputSwitch v-model="status" />
                </div>
            </div>
            <template #footer>
                <Button label="Cancel" text @click="showModal = false" />
                <Button label="Save" @click="savePayee" :loading="inertiaForm.processing" />
            </template>
        </Dialog>
    </AppLayout>
</template>
