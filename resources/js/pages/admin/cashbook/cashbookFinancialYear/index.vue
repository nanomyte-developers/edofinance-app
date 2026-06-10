<script setup>
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import { useToast } from 'primevue/usetoast';
import { onMounted, ref } from 'vue';

import Button from 'primevue/button';
import Calendar from 'primevue/calendar';
import Column from 'primevue/column';
import DataTable from 'primevue/datatable';
import Dialog from 'primevue/dialog';
import Dropdown from 'primevue/dropdown';
import InputNumber from 'primevue/inputnumber';
import InputText from 'primevue/inputtext';
import Tag from 'primevue/tag';
import Toast from 'primevue/toast';
import Toolbar from 'primevue/toolbar';

const props = defineProps({
    years: Array,
    globalFinancialYears: Array,
});

const toast = useToast();
const yearDialog = ref(false);
const isEdit = ref(false);
const loading = ref(false);

const form = useForm({
    id: null,
    name: '',
    financial_year_id: null,
    start_date: null,
    end_date: null,
    opening_balance: 0,
    is_active: true,
});

const openNew = () => {
    form.reset();
    isEdit.value = false;
    yearDialog.value = true;
};

const editYear = (data) => {
    form.id = data.id;
    form.name = data.name;
    form.financial_year_id = data.financial_year_id;
    form.start_date = new Date(data.start_date);
    form.end_date = new Date(data.end_date);
    form.opening_balance = data.opening_balance;
    form.is_active = !!data.is_active;
    isEdit.value = true;
    yearDialog.value = true;
};

const saveYear = () => {
    loading.value = true;

    if (isEdit.value) {
        // Use direct URL for PUT
        form.put(`/cashbook-years/${form.id}`, {
            preserveScroll: true,
            onSuccess: () => {
                yearDialog.value = false;
                loading.value = false;
                toast.add({
                    severity: 'success',
                    summary: 'Updated',
                    detail: 'Year Updated',
                    life: 3000,
                });
            },
            onError: (errors) => {
                loading.value = false;
                toast.add({
                    severity: 'error',
                    summary: 'Error',
                    detail: Object.values(errors)[0] || 'Failed to update year',
                    life: 3000,
                });
            },
            onFinish: () => {
                loading.value = false;
            },
        });
    } else {
        // Use direct URL for POST
        form.post('/cashbook-years', {
            preserveScroll: true,
            onSuccess: () => {
                yearDialog.value = false;
                loading.value = false;
                toast.add({
                    severity: 'success',
                    summary: 'Initialized',
                    detail: 'Year and 12 Months Created',
                    life: 3000,
                });
            },
            onError: (errors) => {
                loading.value = false;
                toast.add({
                    severity: 'error',
                    summary: 'Error',
                    detail: Object.values(errors)[0] || 'Failed to create year',
                    life: 3000,
                });
            },
            onFinish: () => {
                loading.value = false;
            },
        });
    }
};

const formatCurrency = (val) =>
    new Intl.NumberFormat('en-NG', {
        style: 'currency',
        currency: 'NGN',
    }).format(val);

const viewMonths = (id) => {
    // Use direct URL
    const url = `/cashbook-years/${id}/months`;
    console.log('Navigating to:', url);

    router.visit(url, {
        preserveScroll: true,
        preserveState: true,
        onError: (error) => {
            console.error('Navigation error:', error);
            // Fallback to window.location
            window.location.href = url;
        },
    });
};

// Check if we're in development mode
const isDevelopment = import.meta.env.MODE === 'development';

// Add this for debugging
onMounted(() => {
    if (isDevelopment) {
        console.log('Base URL:', import.meta.env.VITE_APP_URL);

        // Test direct URLs
        console.log('Test direct URLs:');
        console.log('- POST to:', '/cashbook-years');
        console.log('- Months URL for ID 1:', '/cashbook-years/1/months');
    }
});
</script>

<template>
    <AppLayout>
        <Head title="Treasury Years" />
        <Toast />

        <div class="grid">
            <div class="col-12">
                <div class="card">
                    <Toolbar class="mb-4">
                        <template #start>
                            <h5 class="m-0">Cashbook Financial Years</h5>
                        </template>
                        <template #end>
                            <Button
                                label="New Treasury Year"
                                icon="pi pi-plus"
                                class="p-button-success mr-2"
                                @click="openNew"
                                :disabled="loading"
                            />
                        </template>
                    </Toolbar>

                    <DataTable
                        :value="years"
                        paginator
                        :rows="10"
                        responsiveLayout="scroll"
                        :loading="loading"
                    >
                        <Column
                            field="name"
                            header="Reference Name"
                            sortable
                        ></Column>
                        <Column field="start_date" header="Period">
                            <template #body="slotProps">
                                <span
                                    v-if="
                                        slotProps.data.start_date &&
                                        slotProps.data.end_date
                                    "
                                >
                                    {{
                                        new Date(
                                            slotProps.data.start_date,
                                        ).getFullYear()
                                    }}
                                    -
                                    {{
                                        new Date(
                                            slotProps.data.end_date,
                                        ).getFullYear()
                                    }}
                                </span>
                                <span v-else>N/A</span>
                            </template>
                        </Column>

                        <Column header="Financial Year Link">
                            <template #body="slotProps">
                                {{
                                    slotProps.data.financial_year
                                        ? slotProps.data.financial_year.name
                                        : 'Not Linked'
                                }}
                            </template>
                        </Column>
                        <Column
                            field="opening_balance"
                            header="Opening Balance"
                        >
                            <template #body="slotProps">{{
                                formatCurrency(slotProps.data.opening_balance)
                            }}</template>
                        </Column>
                        <Column field="is_active" header="Status">
                            <template #body="slotProps">
                                <Tag
                                    :value="
                                        slotProps.data.is_active
                                            ? 'Active'
                                            : 'Closed'
                                    "
                                    :severity="
                                        slotProps.data.is_active
                                            ? 'success'
                                            : 'danger'
                                    "
                                />
                            </template>
                        </Column>
                        <Column header="Actions">
                            <template #body="slotProps">
                                <Button
                                    icon="pi pi-pencil"
                                    class="p-button-rounded p-button-warning mr-2"
                                    @click="editYear(slotProps.data)"
                                    :disabled="loading"
                                />

                                <!-- Use direct link -->
                                <a
                                    :href="`/cashbook-years/${slotProps.data.id}/months`"
                                    class="p-button p-button-info p-button-rounded"
                                    @click.prevent="
                                        viewMonths(slotProps.data.id)
                                    "
                                >
                                    <i class="pi pi-list mr-2"></i>
                                    <span>View Months</span>
                                </a>
                            </template>
                        </Column>
                    </DataTable>
                </div>
            </div>
        </div>

        <Dialog
            v-model:visible="yearDialog"
            :style="{ width: '450px' }"
            :header="isEdit ? 'Edit Year' : 'Initialize Treasury Year'"
            :modal="true"
            class="p-fluid"
            :closable="!loading"
        >
            <div class="field">
                <label for="name">Name</label>
                <InputText
                    id="name"
                    v-model="form.name"
                    required
                    autofocus
                    :class="{ 'p-invalid': form.errors.name }"
                    :disabled="loading"
                />
                <small class="p-error" v-if="form.errors.name">{{
                    form.errors.name
                }}</small>
            </div>
            <div class="field">
                <label>Linked Financial Year</label>
                <Dropdown
                    v-model="form.financial_year_id"
                    :options="globalFinancialYears"
                    optionLabel="name"
                    optionValue="id"
                    placeholder="Select Global Year"
                    :disabled="loading"
                />
            </div>
            <div class="formgrid grid">
                <div class="field col">
                    <label>Start Date</label>
                    <Calendar
                        v-model="form.start_date"
                        dateFormat="yy-mm-dd"
                        :disabled="loading"
                    />
                </div>
                <div class="field col">
                    <label>End Date</label>
                    <Calendar
                        v-model="form.end_date"
                        dateFormat="yy-mm-dd"
                        :disabled="loading"
                    />
                </div>
            </div>
            <div class="field">
                <label>Opening Balance (â‚¦)</label>
                <InputNumber
                    v-model="form.opening_balance"
                    mode="decimal"
                    :minFractionDigits="2"
                    :disabled="loading"
                />
            </div>
            <template #footer>
                <Button
                    label="Cancel"
                    icon="pi pi-times"
                    class="p-button-text"
                    @click="yearDialog = false"
                    :disabled="loading"
                />
                <Button
                    label="Save & Generate Months"
                    icon="pi pi-check"
                    class="p-button-text"
                    @click="saveYear"
                    :loading="loading"
                />
            </template>
        </Dialog>
    </AppLayout>
</template>
<style scoped>
/* Your existing styles remain the same */
.field {
    margin-bottom: 1.5rem;
    display: flex;
    flex-direction: column;
}

.field label {
    display: block;
    margin-bottom: 0.5rem;
    font-weight: 600;
    color: #4b5563;
}

.formgrid.grid {
    display: flex;
    flex-wrap: wrap;
    margin-right: -0.5rem;
    margin-left: -0.5rem;
    margin-top: -0.5rem;
}

.field.col {
    flex-grow: 1;
    flex-basis: 0;
    padding: 0.5rem;
}

:deep(.p-inputtext),
:deep(.p-dropdown),
:deep(.p-calendar),
:deep(.p-inputnumber) {
    width: 100% !important;
}

.p-error {
    margin-top: 0.25rem;
    font-size: 0.875rem;
}

:deep(.p-dialog-content) {
    padding-top: 1.5rem !important;
}

/* Style for the direct link button */
a.p-button.p-button-info.p-button-rounded {
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
}

a.p-button.p-button-info.p-button-rounded:hover {
    opacity: 0.9;
}
</style>
