<script setup>
import { computed, ref, onMounted } from 'vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import { useForm as useVeeForm, useField, configure } from 'vee-validate';
import * as yup from 'yup';

import Card from 'primevue/card';
import DataTable from 'primevue/datatable';
import Column from 'primevue/column';
import Button from 'primevue/button'
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

configure({ validateOnBlur: true, validateOnChange: true });

const props = defineProps({
    receiptActivities: { type: Object, required: true },
    flash: { type: Object, default: () => ({ message: null }) },
});

onMounted(() => {
    if (props.flash.message) {
        toast.add({ severity: 'success', summary: 'Success', detail: props.flash.message, life: 3000 });
    }
});

const activityData = computed(() => props.receiptActivities);
const currentActivity = ref(null);
const showModal = ref(false);
const isEdit = ref(false);

const validationSchema = yup.object({
    name: yup.string().required('Name is required.').max(255),
    status: yup.number().required(),
});

const { handleSubmit, resetForm, setErrors } = useVeeForm({
    validationSchema,
    initialValues: { name: '', status: 1 },
});

const { value: name, errorMessage: nameError } = useField('name');
const { value: status } = useField('status');

const inertiaForm = useForm({ name: '', status: 1 });

const handleCreate = () => {
    isEdit.value = false;
    currentActivity.value = null;
    resetForm({ values: { name: '', status: 1 } });
    showModal.value = true;
};

const handleEdit = (data) => {
    isEdit.value = true;
    currentActivity.value = data;
    resetForm({ values: { name: data.name, status: data.status } });
    showModal.value = true;
};

const saveActivity = handleSubmit((values) => {
    const options = {
        preserveScroll: true,
        onSuccess: () => {
            showModal.value = false;
            toast.add({ severity: 'success', summary: 'Success', detail: 'Saved successfully', life: 3000 });
        },
        onError: (errors) => setErrors(errors)
    };

    if (isEdit.value) {
        router.put(route('receipt-activities.update', currentActivity.value.id), values, options);
    } else {
        router.post(route('receipt-activities.store'), values, options);
    }
});

const toggleStatus = (data) => {
    router.patch(route('receipt-activities.toggle-status', data.id), {}, { preserveScroll: true });
};

const onPageChange = (event) => {
    router.get(route('receipt-activities.index'), { page: event.page + 1 }, { preserveState: true });
};

const breadcrumbs = [ { title: 'Admin' }, { title: 'Receipt Activities' } ];
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head title="Receipt Activities" />
        <Toast />

        <Card class="shadow-sm border-0">
            <template #title>
                <div class="flex justify-between items-center px-2">
                    <div>
                        <h2 class="text-xl font-bold text-gray-900 m-0">Receipt Activities</h2>
                        <small class="text-gray-500 font-normal">Manage receipt types and statuses</small>
                    </div>
                    <Button label="New Activity" icon="pi pi-plus" @click="handleCreate" />
                </div>
            </template>

            <template #content>
                <DataTable :value="activityData.data" class="p-datatable-sm mt-3" stripedRows>
                    <Column field="name" header="Name" style="min-width: 20rem">
                        <template #body="{ data }">
                            <span class="font-semibold">{{ data.name }}</span>
                        </template>
                    </Column>
                    <Column header="Status" style="width: 10rem">
                        <template #body="{ data }">
                            <div class="flex flex-col items-center gap-1">
                                <InputSwitch v-model="data.status" :trueValue="1" :falseValue="0" @change="toggleStatus(data)" class="scale-75" />
                                <Tag :severity="data.status ? 'success' : 'danger'" :value="data.status ? 'Active' : 'Inactive'" class="text-[10px]" />
                            </div>
                        </template>
                    </Column>
                    <Column header="Actions" bodyClass="text-right" style="width: 8rem">
                        <template #body="{ data }">
                            <Button icon="pi pi-pencil" severity="secondary" text rounded @click="handleEdit(data)" />
                        </template>
                    </Column>
                </DataTable>

                <div class="mt-4 flex justify-between items-center" v-if="activityData.meta.total > 0">
                    <span class="text-sm text-gray-500">Total: {{ activityData.meta.total }} entries</span>
                    <Paginator
                        :rows="activityData.meta.per_page"
                        :totalRecords="activityData.meta.total"
                        :first="(activityData.meta.current_page - 1) * activityData.meta.per_page"
                        @page="onPageChange"
                    />
                </div>
            </template>
        </Card>

        <Dialog v-model:visible="showModal" :header="isEdit ? 'Edit Activity' : 'New Activity'" modal :style="{ width: '400px' }">
            <div class="flex flex-col gap-4 pt-2">
                <div class="field">
                    <label class="font-bold block mb-2">Activity Name</label>
                    <InputText v-model="name" :class="{'p-invalid': nameError}" class="w-full" placeholder="e.g. Cash Receipt" />
                    <small class="text-red-500" v-if="nameError">{{ nameError }}</small>
                </div>
                <div class="field flex items-center justify-between p-3 bg-gray-50 rounded-lg border">
                    <span class="font-bold">Active Status</span>
                    <InputSwitch v-model="status" :trueValue="1" :falseValue="0" />
                </div>
            </div>
            <template #footer>
                <Button label="Cancel" text @click="showModal = false" />
                <Button label="Save Changes" icon="pi pi-check" @click="saveActivity" />
            </template>
        </Dialog>
    </AppLayout>
</template>
