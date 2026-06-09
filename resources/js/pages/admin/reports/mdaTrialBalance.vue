<script setup>
import AppLayout from '@/layouts/AppLayout.vue';
import { router, usePage } from '@inertiajs/vue3';
import { computed, onMounted, ref, watch } from 'vue';

import { useToast } from 'primevue/usetoast';
import DataTable from 'primevue/datatable';
import Column from 'primevue/column';

import Toast from 'primevue/toast';
const dt = ref();
const exportCSV = () => {
    dt.value.exportCSV();
};
const FormData = ref({
    start_date: '',
    end_date: '',
    economic_code: '',
    type: '',

});






function submit(economic_code, type, start_date, end_date, voucherIDs, account_number = null, bank_activity_id = null, opening_balance = null, journal_ids = null) {
    router.post('/reports/trialbalanceDetails', {
        start_date: start_date,
        end_date: end_date,
        economic_code: economic_code,
        type: type,
        voucher_ids: voucherIDs,
        account_number: account_number,
        bank_activity_id: bank_activity_id,
        opening_balance: opening_balance,
        journal_ids: journal_ids,
    });
}
const toast = useToast();

const page = usePage();
const user = computed(() => page.props.auth?.user);

const props = defineProps({
    data: {
        type: Object,
        default: () => ({}),
    },
});


// const tableData = computed(() => {
//     return props.data;

// });
// const addedTotal = ref(false);

onMounted(() => {
    // console.log(props.data);


});
// Helper function to format numbers as currency
const formatCurrency = (value) => {
    return new Intl.NumberFormat('en-NG', {
        style: 'currency',
        currency: 'NGN', // Change to 'NGN' or your preferred currency
        minimumFractionDigits: 2
    }).format(value);
};
</script>

<template>
    <Toast />

    <AppLayout title="Dashboard - General Trial Balance">
        <div class="grid">
            <div class="col-12">

                <div class="card">
                    <DataTable :value="props.data" responsiveLayout="scroll" class="p-datatable-sm" ref="dt">
                        <template #header>
                            <div class="text-end pb-4">
                                <Button icon="pi pi-external-link" label="Export" @click="exportCSV" />
                            </div>
                        </template>
                        
                        <Column header="Action" :exportable="false">
                            <template #body="slotProps">
                                </template>
                        </Column>

                        <Column field="economic_code" header="Economic Code">
                            <template #body="slotProps">
                                <span>{{ slotProps.data.economic_code }}</span>
                            </template>
                        </Column>
                        
                        <Column field="description" header="Description"></Column>
                        <Column field="mda_code" header="Admin Code"></Column>
                        <Column field="mda_name" header="Ministries"></Column>

                        <Column field="openning_balance" header="Opening Balance" :exportFunction="exportRawNumber">
                            <template #body="slotProps">
                                {{ formatCurrency(slotProps.data.openning_balance) }}
                            </template>
                        </Column>

                        <Column field="debits" header="Debits" :exportFunction="exportRawNumber">
                            <template #body="slotProps">
                                <span class="text-red-600 font-bold">
                                    {{ formatCurrency(slotProps.data.debits) }}
                                </span>
                            </template>
                        </Column>

                        <Column field="credits" header="Credits" :exportFunction="exportRawNumber">
                            <template #body="slotProps">
                                <span class="text-green-600 font-bold">
                                    {{ formatCurrency(slotProps.data.credits) }}
                                </span>
                            </template>
                        </Column>

                        <Column field="closing_balance" header="Closing Balance" :exportFunction="exportRawNumber">
                            <template #body="slotProps">
                                <span :class="{ 'text-red-500': slotProps.data.closing_balance < 0 }">
                                    {{ formatCurrency(slotProps.data.closing_balance) }}
                                </span>
                            </template>
                        </Column>

                        <Column field="month" header="Month"></Column>

                    </DataTable>
                </div>

            </div>
        </div>
    </AppLayout>
</template>

<style scoped>
/* Optional: Adding colors for financial values */
.text-green-600 {
    color: #16a34a;
}

.text-red-600 {
    color: #dc2626;
}

.font-bold {
    font-weight: 700;
}
</style>
