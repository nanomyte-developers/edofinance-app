<script setup>
import AppLayout from '@/layouts/AppLayout.vue';
import { router, usePage } from '@inertiajs/vue3';
import { computed, onMounted, ref, watch } from 'vue';

import { useToast } from 'primevue/usetoast';
import DataTable from 'primevue/datatable';
import Column from 'primevue/column';

import Toast from 'primevue/toast';


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

const finalData = ref([]);

// const tableData = computed(() => {
//     return props.data;

// });
// const addedTotal = ref(false);

onMounted(() => {
    let sumDebit = 0;
    let sumCredit = 0;
    let sumOpeningBalance = 0;
    let sumClosingBalance = 0;

    console.log(props.data);
    props.data.forEach(data => {
        sumDebit += data.debits;
        sumCredit += data.credits;
        sumOpeningBalance += parseFloat(data.openning_balance);
        sumClosingBalance += data.closing_balance;

    });
    console.log(sumOpeningBalance);
    console.log(sumDebit);
    console.log(sumCredit);
    console.log(sumClosingBalance);

    finalData.value = props.data;
    const eCodes = finalData.value.map(data => data.economic_code);
    if (!eCodes.includes('Total')) {
        finalData.value.push({
            economic_code: 'Total',
            debits: sumDebit,
            credits: sumCredit,
            openning_balance: sumOpeningBalance,
            closing_balance: sumClosingBalance
        });
    }
    // finalData.value.push({
    //     economic_code: 'Total',
    //     debits: sumDebit,
    //     credits: sumCredit,
    //     openning_balance: sumOpeningBalance,
    //     closing_balance: sumClosingBalance
    // });



    // console.log(props.dat);
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
            <!-- Main Activity Statistics Cards -->
            <div class="col-12">


                <div class="card">
                    <DataTable :value="finalData" responsiveLayout="scroll" class="p-datatable-sm">
                        <Column header="Action">
                            <template #body="slotProps">
                                <Button v-if="slotProps.data.economic_code != 'Total'" type="button"
                                    @click.prevent="submit(slotProps.data.economic_code, slotProps.data.type, slotProps.data.start_date, slotProps.data.end_date, slotProps.data.voucher_ids, slotProps.data.account_number, slotProps.data.bank_activity_id, slotProps.data.openning_balance, slotProps.data.journal_ids)"
                                    icon="pi pi-eye" class="p-button-text p-button-sm"></Button>
                            </template>
                        </Column>
                        <Column header="Code">
                            <template #body="slotProps">
                                <span>

                                    {{ slotProps.data.economic_code }} </span>

                            </template>
                        </Column>
                        <Column field="description" header="Description"></Column>

                        <Column header="Opening Balance">
                            <template #body="slotProps">
                                {{ formatCurrency(slotProps.data.openning_balance) }}
                            </template>
                        </Column>

                        <Column header="Debits">
                            <template #body="slotProps">
                                <span class="text-red-600 font-bold">
                                    {{ formatCurrency(slotProps.data.debits) }}
                                </span>
                            </template>
                        </Column>

                        <Column header="Credits">
                            <template #body="slotProps">
                                <span class="text-green-600 font-bold">
                                    {{ formatCurrency(slotProps.data.credits) }}
                                </span>
                            </template>
                        </Column>



                        <Column header="Closing Balance">
                            <template #body="slotProps">
                                <span :class="{ 'text-red-500': slotProps.data.closing_balance < 0 }">
                                    {{ formatCurrency(slotProps.data.closing_balance) }}
                                </span>
                            </template>
                        </Column>
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
