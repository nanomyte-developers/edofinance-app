<script setup>
import AppLayout from '@/layouts/AppLayout.vue';
import { router, usePage, Link } from '@inertiajs/vue3';
import { computed, onMounted, ref, watch } from 'vue';

import { useToast } from 'primevue/usetoast';
import DataTable from 'primevue/datatable';
import Column from 'primevue/column';

import Toast from 'primevue/toast';
import { title } from '@primeuix/themes/aura/card';
import * as XLSX from 'xlsx';
import Button from 'primevue/button';



const toast = useToast();

const page = usePage();
const user = computed(() => page.props.auth?.user);

const props = defineProps({
    data: {
        type: Object,
        default: () => ({}),
    },
    title: {
        type: String,
        default: '',
    },
    EconomicCode: {
        type: String,

    },
    EconomicCodeItem: {
        type: String,

    },
    start_date: {
        type: String,

    },
    end_date: {
        type: String,

    },
    opening_balance: {
        type: String,
    },
    ledger_type: {
        type: String,
    }
});

const tableData = computed(() => {
    return props.data;
});

onMounted(() => {
    // console.log(props);


})
// Helper function to format numbers as currency
const formatCurrency = (value) => {
    return new Intl.NumberFormat('en-NG', {
        style: 'currency',
        currency: 'NGN', // Change to 'NGN' or your preferred currency
        minimumFractionDigits: 2
    }).format(value);
};


const entries = ref([]);

const newEntry = ref({
    date: '',
    description: '',
    amount: 0,
    type: 'credit',
});

const totalCredits = computed(() => {
    let a = 0;
    for (let i = 0; i < props.data.length; i++) {
        const entry = props.data[i];
        const amount = parseFloat(entry.credit);

        a += amount;

    }
    if (props.opening_balance < 0) {
        a += parseFloat( Math.abs( props.opening_balance));
    }
    return a;
});

const totalDebits = computed(() => {
    let b = 0;
    for (let i = 0; i < props.data.length; i++) {
        const entry = props.data[i];
        const amount = parseFloat(entry.debit);

        b += amount;

    }
    if (props.opening_balance > 0) {
        b += parseFloat(Math.abs(props.opening_balance));
    }
    return b;
});

const netBalance = computed(() => {
    return totalCredits.value - totalDebits.value;
});



const formatDate = (dateString) => {
    if (!dateString) return 'N/A';
    return new Date(dateString).toLocaleDateString('en-GB');
};


const exportHtmlTableToExcel = () => {
    // Access the HTML table element
    const table = document.getElementById('my_ledger');

    // Convert the HTML table element to a worksheet
    const worksheet = XLSX.utils.table_to_sheet(table);

    // Create a new workbook and append the worksheet
    const workbook = XLSX.utils.book_new();
    XLSX.utils.book_append_sheet(workbook, worksheet, 'Ledger for ' + props.EconomicCodeItem);

    // Trigger download
    XLSX.writeFile(workbook, props.EconomicCode + "_" + props.EconomicCodeItem + "_" + props.start_date + "_" + props.end_date + '.xlsx');
};

const goBack = () => {
    window.history.back();
};
</script>

<template>
    <Toast />

    <AppLayout title="Dashboard - General Trial Balance Details">
        <div class="grid">
            <!-- Main Activity Statistics Cards -->
            <div class="col-12 ">


                <div class="card text-center">
                    

                    <!-- Ledger Table -->
                    <table class="ledger-table zebra-table" id="my_ledger">
                        <thead class="bg-green-950">
                            <tr>
                                <th colspan="11"><span class=" text-4xl w-full font-extrabold">THE {{ props.title.toUpperCase() }}
                        ECONOMIC CODE {{ props.EconomicCode }}/{{ props.EconomicCodeItem }} FOR THE PERIOD {{
                            props.start_date }} TO {{ props.end_date }}</span> </th>
                                <th v-if="props.ledger_type== 'bank'"></th>
                            </tr>
                            <tr>
                                <th>Serial</th>
                                <th>Reference Number</th>
                                <th>Economic Code Item</th>
                                <th v-if="props.ledger_type== 'bank'">Code Item</th>
                                <th>Date</th>
                                <th>MDA Name</th>
                                <th>Bank Name</th>
                                <th>Account Number</th>
                                <th>Bank Economic Code</th>
                                <th>Narration</th>
                                <th>Debit</th>
                                <th>Credit</th>

                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td colspan="7"></td>
                                <td v-if="props.ledger_type== 'bank'"></td>
                                <td colspan="2">Opening Balance</td>
                                <td class="text-right text-red-600 text-bold">
                                    <div v-if="props.opening_balance >= 0">{{ formatCurrency( Math.abs( props.opening_balance)) ||
                                        Math.abs(props.opening_balance) }}</div>
                                </td>
                                <td class="text-right text-green-500 text-bold">
                                    <div v-if="props.opening_balance < 0">{{ formatCurrency( Math.abs(props.opening_balance)) ||
                                        Math.abs(props.opening_balance) }}</div>
                                </td>
                            </tr>
                            <tr v-for="(entry, index) in props.data" :key="index">
                                <td>{{ index + 1 }}</td>
                                <td>
                                    <Link v-if="entry.type == 'remittance'" icon="external-link" class="text-green-600"
                                        :href="'/remittances/' + entry.id"> {{ entry.reference }}</Link>
                                    <Link v-if="entry.type == 'voucher'" icon="external-link" class="text-green-600"
                                        :href="'/vouchers/' + entry.id"> {{ entry.reference }} </Link>
                                    <Link v-if="entry.type == 'receipt'" icon="external-link" class="text-green-600"
                                        :href="'/receipts/' + entry.id"> {{ entry.reference }} </Link>
                                    <Link v-if="entry.type == 'journal'" icon="external-link" class="text-green-600"
                                        :href="'/journals/' + entry.id"> {{ entry.reference }} </Link>
                                    <!-- <Link v-if="entry.type == 'retirement'" icon="external-link" class="text-green-600"
                                        :href="'/receipts/' + entry.id"> {{ entry.reference }} </Link> -->
                                </td>
                                <td>{{ props.EconomicCodeItem }}</td>
                                <td v-if="props.ledger_type == 'bank'">{{ entry.item_code }}</td>
                                <td>{{ formatDate(entry.date) || entry.date }}</td>
                                <td>{{ entry.mda_name }}</td>
                                <td>{{ entry.bank_name }}</td>
                                <td>{{ entry.account_number }}</td>
                                <td>{{ entry.bank_economic_code }}</td>
                                <td>{{ entry.description }}</td>


                                <td class="text-right text-red-600 text-extrabold">{{ formatCurrency(entry.debit) ||
                                    entry.debit }}</td>
                                <td class="text-right text-green-500 text-extrabold">{{ formatCurrency(entry.credit) ||
                                    entry.credit }}</td>


                            </tr>
                            <tr>
                                <td></td>
                                <td></td>
                                <td></td>
                                <td></td>
                                <td></td>
                                <td></td>
                                <td></td>
                                <td v-if="props.ledger_type== 'bank'"></td>
                                <td></td>
                                <td colspan="1" class="text-right font-bold">Balance Carried Down</td>
                                <td class="text-right text-red-600 text-extrabold">
                                    <div v-if="totalCredits > totalDebits">{{ formatCurrency(Math.abs(netBalance)) }}
                                    </div>
                                </td>
                                <td class="text-right text-green-500 text-extrabold">
                                    <div v-if="totalDebits > totalCredits">{{ formatCurrency(Math.abs(netBalance)) }}
                                    </div>
                                </td>

                            </tr>
                            <tr>
                                <td></td>
                                <td></td>
                                <td></td>
                                <td></td>
                                <td></td>
                                <td></td>
                                <td></td>
                                <td v-if="props.ledger_type== 'bank'"></td>
                                <td></td>
                                <td colspan="1" class="text-right font-bold">Total</td>
                                <td class="text-right text-red-600 text-extrabold">
                                    <div v-if="totalDebits >= totalCredits">{{ formatCurrency(totalDebits) ||
                                        totalDebits.toFixed(2) }}</div>
                                    <div v-else>{{ formatCurrency(totalCredits) || totalCredits.toFixed(2) }}</div>
                                </td>
                                <td class="text-right text-green-500 text-extrabold">
                                    <div v-if="totalDebits >= totalCredits">{{ formatCurrency(totalDebits) ||
                                        totalDebits.toFixed(2) }}</div>
                                    <div v-else>{{ formatCurrency(totalCredits) || totalCredits.toFixed(2) }}</div>
                                </td>
                            </tr>

                            <tr>
                                <td></td>
                                <td></td>
                                <td></td>
                                <td></td>
                                <td></td>
                                <td></td>
                                <td></td>
                                <td v-if="props.ledger_type== 'bank'"></td>
                                <td></td>
                                <td colspan="1" class="text-right font-bold">Balance Brought Down</td>
                                <td class="text-right text-red-600 text-extrabold">
                                    <div v-if="totalCredits < totalDebits">{{ formatCurrency(Math.abs(netBalance)) }}
                                    </div>
                                </td>
                                <td class="text-right text-green-500 text-extrabold">
                                    <div v-if="totalDebits < totalCredits">{{ formatCurrency(Math.abs(netBalance)) }}
                                    </div>
                                </td>

                            </tr>


                        </tbody>
                    </table>

                    <!-- Summary -->
                    <!-- <div class="summary">
                        <p class="text-green-500">Total Credits: {{ formatCurrency(totalCredits) ||
                            totalCredits.toFixed(2) }}</p>
                        <p class="text-red-600">Total Debits: {{ formatCurrency(totalDebits) || totalDebits.toFixed(2)
                        }}</p>
                        <p>Net Balance: {{ formatCurrency(netBalance) || netBalance.toFixed(2) }}</p>
                    </div> -->
                    <div class=" w-full"><Button icon="pi pi-arrow-left" text rounded severity="warning" @click="goBack"
                            v-tooltip="'Go Back'" /> <Button @click="exportHtmlTableToExcel">Export to Excel</button></div>
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


.ledger-page {
    max-width: 800px;
    margin: 0 auto;
    padding: 20px;
}

.entry-form {
    display: flex;
    gap: 10px;
    margin-bottom: 20px;
}

.entry-form div {
    display: flex;
    flex-direction: column;
}

.ledger-table {
    width: 100%;
    border-collapse: collapse;
    margin-bottom: 20px;
}

.ledger-table th,
.ledger-table td {
    border: 1px solid #ccc;
    padding: 8px;
    text-align: left;
}

.summary {
    font-weight: bold;
}



/* Target the table rows within the tbody */
.zebra-table tbody tr:nth-child(odd) {
    background-color: #f9f9f9;
    /* Light gray for odd rows */
}

.zebra-table tbody tr:nth-child(even) {
    background-color: #e9e9e9;
    /* Slightly darker gray for even rows */
}

/* Optional: Add some basic table styling for better presentation */
.zebra-table {
    width: 100%;
    border-collapse: collapse;
}

.zebra-table th,
.zebra-table td {
    padding: 10px;
    border: 1px solid #ddd;
    text-align: left;
}

.zebra-table thead th {
    background-color: #4CAF50;
    color: white;
}
</style>
