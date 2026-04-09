<script setup>
import { ref, computed, onMounted } from 'vue';
import { Head } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import DataTable from 'primevue/datatable';
import Column from 'primevue/column';
import Card from 'primevue/card';
import InputText from 'primevue/inputtext';
import Chart from 'primevue/chart'; // Ensure chart.js is installed

const props = defineProps({
    chartData: Array,
    groupedBalances: Object,
    banks: Array
});

const searchQuery = ref('');
const barChartData = ref(null);
const barChartOptions = ref(null);

onMounted(() => {
    initChart();
});

const initChart = () => {
    barChartData.value = {
        labels: props.chartData.map(item => item.mda_name),
        datasets: [
            {
                label: 'Previous Year',
                backgroundColor: '#94a3b8', // Slate 400
                data: props.chartData.map(item => item.total_prev)
            },
            {
                label: 'Current Year',
                backgroundColor: '#3b82f6', // Blue 500
                data: props.chartData.map(item => item.total_curr)
            }
        ]
    };

    barChartOptions.value = {
        maintainAspectRatio: false,
        aspectRatio: 0.8,
        plugins: {
            legend: { labels: { color: '#495057' } }
        },
        scales: {
            x: { ticks: { color: '#495057' }, grid: { color: '#ebedef' } },
            y: { ticks: { color: '#495057' }, grid: { color: '#ebedef' } }
        }
    };
};

const filteredGroups = computed(() => {
    if (!searchQuery.value) return props.groupedBalances;
    const query = searchQuery.value.toLowerCase();

    return Object.fromEntries(
        Object.entries(props.groupedBalances).filter(([id, balances]) => {
            const mdaName = balances[0]?.mda?.name?.toLowerCase() || '';
            return mdaName.includes(query);
        })
    );
});

const formatCurrency = (val) => {
    return new Intl.NumberFormat('en-NG', { style: 'currency', currency: 'NGN' }).format(val || 0);
};

const calculateMdaTotal = (balances) => {
    return balances.reduce((sum, item) => sum + (parseFloat(item.balance_current_year) || 0), 0);
};
</script>

<template>
    <AppLayout>
        <Head title="MDA Grouped Accounts" />
        <div class="p-6 space-y-6">

            <Card class="shadow-sm border">
                <template #title>Financial Comparison by MDA</template>
                <template #content>
                    <div class="h-80">
                        <Chart type="bar" :data="barChartData" :options="barChartOptions" class="h-full" />
                    </div>
                </template>
            </Card>

            <Card class="shadow-sm border-0 bg-slate-50">
                <template #content>
                    <div class="flex items-center justify-between">
                        <h1 class="text-xl font-bold">MDA Detailed Tables</h1>
                        <span class="p-input-icon-left">
                            <i class="pi pi-search" />
                            <InputText v-model="searchQuery" placeholder="Filter by MDA..." class="w-80" />
                        </span>
                    </div>
                </template>
            </Card>

            <div v-for="(balances, mdaId) in filteredGroups" :key="mdaId" class="mb-8">
                <Card shadow="sm" class="border">
                    <template #title>
                        <div class="flex items-center gap-2">
                            <i class="pi pi-building text-primary" />
                            <span class="uppercase text-lg">{{ balances[0]?.mda?.name || 'N/A' }}</span>
                        </div>
                    </template>
                    <template #content>
                        <DataTable :value="balances" stripedRows class="p-datatable-sm">
                            <Column field="bank.name" header="Bank" />
                            <Column field="account_number" header="Account Number" />
                            <Column header="Previous Year">
                                <template #body="{data}">{{ formatCurrency(data.balance_previous_year) }}</template>
                            </Column>
                            <Column header="Current Year">
                                <template #body="{data}">
                                    <span class="font-bold text-blue-700">{{ formatCurrency(data.balance_current_year) }}</span>
                                </template>
                            </Column>
                            <template #footer>
                                <div class="flex justify-end gap-4 text-lg border-t pt-2">
                                    <span class="font-medium text-gray-500">Current Total:</span>
                                    <span class="font-bold text-primary">{{ formatCurrency(calculateMdaTotal(balances)) }}</span>
                                </div>
                            </template>
                        </DataTable>
                    </template>
                </Card>
            </div>
        </div>
    </AppLayout>
</template>
