<script setup>
import AppLayout from '@/layouts/AppLayout.vue';
import { router, usePage } from '@inertiajs/vue3';
import { computed, onMounted, ref, watch } from 'vue';

import { useToast } from 'primevue/usetoast';
import DataTable from 'primevue/datatable';
import Column from 'primevue/column';

import Toast from 'primevue/toast';
import { FilterMatchMode } from '@primevue/core/api';


const FormData = ref({
    start_date: '',
    end_date: '',
    economic_code: '',
    type: '',

});






function submit(economic_code, type, start_date, end_date, voucherIDs, account_number = null, bank_activity_id = null, opening_balance = null, journal_ids = null, retirnment_ids = null) {
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
        retirmentIds: retirnment_ids
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

const dt = ref();
const exportCSV = () => {
    dt.value.exportCSV();
};

// const tableData = computed(() => {
//     return props.data;

// });
// const addedTotal = ref(false);

onMounted(() => {
    let sumDebit = 0;
    let sumCredit = 0;
    let sumOpeningBalance = 0;
    let sumClosingBalance = 0;

    // console.log(props.data);
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

const filters = ref({
    global: { value: null, matchMode: FilterMatchMode.CONTAINS },
    economic_code: { value: null, matchMode: FilterMatchMode.CONTAINS },
    description: { value: null, matchMode: FilterMatchMode.CONTAINS },
    openning_balance: { value: null, matchMode: FilterMatchMode.CONTAINS },
    debits: { value: null, matchMode: FilterMatchMode.CONTAINS },
    credits: { value: null, matchMode: FilterMatchMode.CONTAINS },
    closing_balance: { value: null, matchMode: FilterMatchMode.CONTAINS },

});

</script>

<template>
    <Toast />

    <AppLayout title="Dashboard - General Trial Balance">
        <div class="grid">
            <!-- Main Activity Statistics Cards -->
            <div class="col-12">


                <div class="card">
                    <DataTable :value="finalData" removableSort stripedRows ref="dt" responsiveLayout="scroll"
                        filterDisplay="row" class="p-datatable-sm"
                        :globalFilterFields="['economic_code', 'description']" v-model:filters="filters">
                        <template #header>

                            <div class="flex justify-end">
                                <IconField>
                                    <InputIcon>
                                        <i class="pi pi-search"></i>
                                    </InputIcon>
                                    <InputText v-model="filters['global'].value" placeholder="Keyword Search" />
                                </IconField>
                                <!-- </div>
                            <div class="text-end pb-4"> -->
                                <Button icon="pi pi-external-link" label="Export" @click="exportCSV" />
                            </div>


                        </template>
                        <Column header="Action">
                            <template #body="slotProps">
                                <Button v-if="slotProps.data.economic_code != 'Total'" type="button"
                                    @click.prevent="submit(slotProps.data.economic_code, slotProps.data.type, slotProps.data.start_date, slotProps.data.end_date, slotProps.data.voucher_ids, slotProps.data.account_number, slotProps.data.bank_activity_id, slotProps.data.openning_balance, slotProps.data.journal_ids, slotProps.data.retirmentIds)"
                                    icon="pi pi-eye" class="p-button-text p-button-sm"></Button>
                            </template>
                        </Column>

                        <!-- <Column header="Code">
                            <template #body="slotProps">
                                <span>

                                    {{ slotProps.data.economic_code }} </span>

                            </template>
                        </Column> -->

                        <Column field="economic_code" header="Code" style="min-width: 12rem">
                            <template #body="slotProps">
                                {{ slotProps.data.economic_code }}
                            </template>
                            <template #filter="{ filterModel, filterCallback }">
                                <InputText v-model="filterModel.value" type="text" @input="filterCallback()"
                                    placeholder="Search by Economic Code" />
                            </template>
                        </Column>

                        <!-- <Column field="description" header="Description"></Column> -->

                        <Column field="description" header="Description" style="min-width: 12rem">
                            <template #body="slotProps">
                                {{ slotProps.data.description }}
                            </template>
                            <template #filter="{ filterModel, filterCallback }">
                                <InputText v-model="filterModel.value" type="text" @input="filterCallback()"
                                    placeholder="Search by Description" />
                            </template>
                        </Column>

                        <Column field="openning_balance" header="Opening Balance" dataType="numeric"
                            style="min-width: 12rem">
                            <template #body="slotProps">
                                {{ formatCurrency(slotProps.data.openning_balance) }}
                            </template>
                            <template #filter="{ filterModel, filterCallback }">
                                <InputText v-model="filterModel.value" type="text" @input="filterCallback()"
                                    placeholder="Search by Opening Balance" />
                            </template>
                        </Column>

                        <!-- <Column field="openning_balance" header="Opening Balance">
                            <template #body="slotProps">
                                {{ formatCurrency(slotProps.data.openning_balance) }}
                            </template>
                        </Column> -->

                        <!-- <Column field="debits" header="Debits">
                            <template #body="slotProps">
                                <span class="text-red-600 font-bold">
                                    {{ formatCurrency(slotProps.data.debits) }}
                                </span>
                            </template>
                        </Column> -->

                        <Column field="debits" header="Debits" dataType="numeric" style="min-width: 12rem">
                            <template #body="slotProps">
                                <span class="text-red-600 font-bold">
                                    {{ formatCurrency(slotProps.data.debits) }}
                                </span>
                            </template>
                            <template #filter="{ filterModel, filterCallback }">
                                <InputText v-model="filterModel.value" type="text" @input="filterCallback()"
                                    placeholder="Search by Debit Amount" />
                            </template>
                        </Column>

                        <!-- <Column field="credits" header="Credits">
                            <template #body="slotProps">
                                <span class="text-green-600 font-bold">
                                    {{ formatCurrency(slotProps.data.credits) }}
                                </span>
                            </template>
                        </Column> -->

                        <Column field="credits" header="Credits" dataType="numeric" style="min-width: 12rem">
                            <template #body="slotProps">
                                <span class="text-green-600 font-bold">
                                    {{ formatCurrency(slotProps.data.credits) }}
                                </span>
                            </template>
                            <template #filter="{ filterModel, filterCallback }">
                                <InputText v-model="filterModel.value" type="text" @input="filterCallback()"
                                    placeholder="Search by Credit Amount" />
                            </template>
                        </Column>



                        <!-- <Column field="closing_balance" header="Closing Balance">
                            <template #body="slotProps">
                                <span :class="{ 'text-red-500': slotProps.data.closing_balance < 0 }">
                                    {{ formatCurrency(slotProps.data.closing_balance) }}
                                </span>
                            </template>
                        </Column> -->


                        <Column field="closing_balance" header="CLosing Balance" style="min-width: 12rem">
                            <template #body="slotProps">
                                <span :class="{ 'text-red-500': slotProps.data.closing_balance < 0 }">
                                    {{ formatCurrency(slotProps.data.closing_balance) }}
                                </span>
                            </template>
                            <template #filter="{ filterModel, filterCallback }">
                                <InputText v-model="filterModel.value" type="text" @input="filterCallback()"
                                    placeholder="Search by Closing Balance Amount" />
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
