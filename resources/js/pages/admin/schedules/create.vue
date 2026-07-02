<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import axios from 'axios';
import Button from 'primevue/button';
import Calendar from 'primevue/calendar';
import Card from 'primevue/card';
import Column from 'primevue/column';
import DataTable from 'primevue/datatable';
import Dropdown from 'primevue/dropdown';
import InputNumber from 'primevue/inputnumber';
import InputText from 'primevue/inputtext';
import Message from 'primevue/message';
import Toast from 'primevue/toast';
import { useToast } from 'primevue/usetoast';
import { computed, onMounted, ref, watch } from 'vue';

import AppLayout from '@/layouts/AppLayout.vue';

const lazyItems: any = ref([]);
const loading = ref(false);
const currentPage = ref(0);
const filterValue = ref('');

const fetchData = async (page: number, filter = '') => {
    loading.value = true;
    try {
        const response = await axios.get(`/payeeList?page=${page}&filter=${filter}`);
        const newItems = response.data.data.map((item: { name: any; id: any; }) => ({ label: item.name, value: item.name }));

        if (page === 1) {
            lazyItems.value = newItems;
        } else {
            lazyItems.value = [...lazyItems.value, ...newItems];
        }
        currentPage.value = page;
    } catch (error) {
        console.error("Error fetching data:", error);
    } finally {
        loading.value = false;
    }
};

const onLazyLoad = (event) => {
    fetchData(currentPage.value + 1, filterValue.value);
};

const onFilter = (event) => {
    filterValue.value = event.value;
    fetchData(1, event.value);
};

// --- Props ---
const props = defineProps({
    administrativeCodes: { type: Array<any>, default: () => [] },
    administrativeSectorCodes: { type: Array<any>, default: () => [] },
    financialYears: { type: Array<any>, default: () => [] },
    economyCodes: { type: Array<any>, default: () => [] },
    economyCodeItems: { type: Array<any>, default: () => [] },
    nextScheduleNumber: { type: String, default: 'SCH/PENDING/000/2025' },
    payees: { type: Array<any>, default: () => [] },
    mdas: { type: Array<any>, default: () => [] },
});

const toast = useToast();
const breadcrumbs = [
    { title: 'Schedules', href: '/schedules' },
    { title: 'Create Schedule', href: '#' },
];

let nextItemId = 1;
let currentSerialCounter = 1;

// --- Types ---
interface ScheduleItem {
    id: number;
    date: string | Date;
    serial_no: string;
    economy_code_id: number | null;
    economy_code_item_id: number | null;
    payee_name: string;
    amount: number;
    errors?: {
        date?: string;
        serial_no?: string;
        economy_code_id?: string;
        economy_code_item_id?: string;
        payee_name?: string;
        amount?: string;
    };
}

// --- Form State ---
const form = useForm({
    year_id: null as number | null,
    mda_id: null as number | null,
    budget_code_id: null as number | null,
    schedule_number: props.nextScheduleNumber,
    status: 'Draft',
    total_amount: 0,
    items: [] as ScheduleItem[],
});

const validationErrors = ref({
    year_id: '',
    mda_id: '',
    budget_code_id: '',
    line_items: '',
});

const selectedSector = ref(null as any);
const isLoadingNumber = ref(false);
const schedulePrefix = ref('SCH/MF');
const baseSerial = ref('1');

// --- Computed Options ---
const sectorOptions = computed(() => {
    return props.administrativeCodes.filter(
        (item: any) =>
            item.name.includes('SECTOR') || item.code.endsWith('0000000000'),
    );
});

const mdaOptions = computed(() => {
    if (!selectedSector.value) return [];
    return props.mdas
        .filter((item: any) => item.administrative_code_id === selectedSector.value.id);
});

const budgetHeadOptions = computed(() => {
    if(selectedSector.value === null) return [];
    return props.administrativeSectorCodes
        .filter((head: any) => head.administrative_code_id === selectedSector.value.id)
        .map((head: any) => ({
            value: head.id,
            label: `${head.code} - ${head.name}`,
        }));
});

const economyCodeOptions = computed(() => {
    if (!props.economyCodes || props.economyCodes.length === 0) {
        return [];
    }
    return props.economyCodes.map((code: any) => {
        if (code.value && code.label) {
            return code;
        } else if (code.id && code.code && code.name) {
            return {
                value: code.id,
                label: `${code.code} - ${code.name}`,
            };
        } else {
            return {
                value: code.id || code.value,
                label: code.label || `${code.code} - ${code.name}` || 'Unknown',
            };
        }
    });
});

const getEconomyCodeItemOptions = (economyCodeId: number | null) => {
    if (!economyCodeId || !props.economyCodeItems || props.economyCodeItems.length === 0) {
        return [];
    }
    return props.economyCodeItems
        .filter((item: any) => {
            const itemEconomyCodeId = item.economy_code_id || item.economyCodeId || item.economy_code?.id;
            return itemEconomyCodeId === economyCodeId;
        })
        .map((item: any) => {
            if (item.value && item.label) {
                return item;
            } else {
                return {
                    value: item.id || item.value,
                    label: item.label || `${item.code} - ${item.name}`,
                    economy_code_id: item.economy_code_id || item.economyCodeId,
                };
            }
        });
};

// --- Watchers ---
watch(selectedSector, () => {
    form.mda_id = null;
    form.budget_code_id = null;
});

watch(() => form.mda_id, () => {
    form.budget_code_id = null;
});

// --- Helper: Generate Full Schedule Number for Item ---
const generateItemSerialNumber = (serial: string | number): string => {
    const parts = form.schedule_number.split('/');
    if (parts.length >= 3) {
        const prefix = parts[0] + '/' + parts[1];
        const year = parts[3] || new Date().getFullYear().toString();
        return `${prefix}/${serial}/${year}`;
    }
    return `SCH/${serial}/${new Date().getFullYear()}`;
};

// --- API CALL: FETCH DYNAMIC NUMBER ---
const fetchNextScheduleNumber = async () => {
    if (!form.year_id || !form.mda_id) return;

    isLoadingNumber.value = true;

    try {
        const response = await axios.get('/schedules/next-number', {
            params: {
                year_id: form.year_id,
                mda_id: form.mda_id,
            },
        });

        form.schedule_number = response.data.schedule_number;

        const parts = response.data.schedule_number.split('/');
        if (parts.length >= 3 && !isNaN(parseInt(parts[2]))) {
            baseSerial.value = parts[2];
            currentSerialCounter = parseInt(parts[2]);
            schedulePrefix.value = parts[0] + '/' + parts[1];
        } else {
            baseSerial.value = response.data.serial_no || '1';
            currentSerialCounter = parseInt(baseSerial.value);
            schedulePrefix.value = 'SCH/MF';
        }

        updateItemSerials();
    } catch (error) {
        console.error('Error fetching number:', error);
        toast.add({
            severity: 'error',
            summary: 'Error',
            detail: 'Could not load schedule number.',
            life: 3000,
        });
    } finally {
        isLoadingNumber.value = false;
    }
};

// Trigger on Year or MDA change
watch([() => form.year_id, () => form.mda_id], () => {
    fetchNextScheduleNumber();
});

// --- Helper: Update all items with full schedule number format ---
const updateItemSerials = () => {
    let counter = parseInt(baseSerial.value) || 1;
    const year = form.year_id ? 
        (props.financialYears.find(y => y.value === form.year_id)?.label || new Date().getFullYear().toString()) : 
        new Date().getFullYear().toString();
    
    form.items.forEach((item) => {
        item.serial_no = `${schedulePrefix.value}/${counter}/${year}`;
        counter++;
    });
    currentSerialCounter = counter;
};

// --- Financial Helpers ---
const formatCurrency = (value: number) => {
    return new Intl.NumberFormat('en-NG', {
        style: 'currency',
        currency: 'NGN',
    }).format(value);
};

const convertNumberToWords = (amount: number): string => {
    if (isNaN(amount) || amount === 0) return 'Zero Naira';
    const units = ['', 'One', 'Two', 'Three', 'Four', 'Five', 'Six', 'Seven', 'Eight', 'Nine'];
    const teens = ['Ten', 'Eleven', 'Twelve', 'Thirteen', 'Fourteen', 'Fifteen', 'Sixteen', 'Seventeen', 'Eighteen', 'Nineteen'];
    const tens = ['', '', 'Twenty', 'Thirty', 'Forty', 'Fifty', 'Sixty', 'Seventy', 'Eighty', 'Ninety'];

    const convertHundreds = (num: number): string => {
        let result = '';
        if (num >= 100) {
            result += units[Math.floor(num / 100)] + ' Hundred ';
            num %= 100;
        }
        if (num >= 20) {
            result += tens[Math.floor(num / 10)] + ' ';
            num %= 10;
        } else if (num >= 10) {
            result += teens[num - 10] + ' ';
            num = 0;
        }
        if (num > 0) {
            result += units[num] + ' ';
        }
        return result.trim();
    };

    let words = '';
    let nairaAmount = Math.floor(amount);
    const koboAmount = Math.round((amount - nairaAmount) * 100);

    if (nairaAmount >= 1000000000) {
        words += convertHundreds(Math.floor(nairaAmount / 1000000000)) + ' Billion ';
        nairaAmount %= 1000000000;
    }
    if (nairaAmount >= 1000000) {
        words += convertHundreds(Math.floor(nairaAmount / 1000000)) + ' Million ';
        nairaAmount %= 1000000;
    }
    if (nairaAmount >= 1000) {
        words += convertHundreds(Math.floor(nairaAmount / 1000)) + ' Thousand ';
        nairaAmount %= 1000;
    }
    if (nairaAmount > 0) {
        words += convertHundreds(nairaAmount) + ' ';
    }

    words += words ? 'Naira' : 'Zero Naira';
    if (koboAmount > 0) {
        words += ' and ' + convertHundreds(koboAmount) + ' Kobo';
    }

    return words.trim() + ' Only';
};

// --- Computed Totals ---
const scheduleTotal = computed(() => {
    return form.items.reduce((sum, item) => sum + (item.amount || 0), 0);
});

watch(scheduleTotal, (newVal) => {
    form.total_amount = newVal;
});

const amountInWords = computed(() => {
    return convertNumberToWords(scheduleTotal.value);
});

// --- Validation ---
const validateHeader = () => {
    let isValid = true;
    validationErrors.value = {
        year_id: '',
        mda_id: '',
        budget_code_id: '',
        line_items: '',
    };
    if (!form.year_id) {
        validationErrors.value.year_id = 'Required';
        isValid = false;
    }
    if (!form.mda_id) {
        validationErrors.value.mda_id = 'Required';
        isValid = false;
    }
    if (!form.budget_code_id) {
        validationErrors.value.budget_code_id = 'Required';
        isValid = false;
    }
    return isValid;
};

const validateLineItems = () => {
    let isValid = true;
    if (form.items.length === 0) {
        validationErrors.value.line_items = 'Row required';
        return false;
    }
    form.items.forEach((item) => {
        const itemErrors: any = {};
        if (item.date && typeof item.date !== 'string')
            item.date = new Date(item.date).toISOString().split('T')[0];
        if (!item.date) itemErrors.date = 'Required';
        if (!item.serial_no) itemErrors.serial_no = 'Required';
        if (!item.economy_code_id) itemErrors.economy_code_id = 'Required';
        if (!item.economy_code_item_id)
            itemErrors.economy_code_item_id = 'Required';
        if (!item.payee_name) itemErrors.payee_name = 'Required';
        if (!item.amount || item.amount <= 0) itemErrors.amount = 'Invalid';

        if (Object.keys(itemErrors).length > 0) {
            item.errors = itemErrors;
            isValid = false;
        } else {
            item.errors = {};
        }
    });
    return isValid;
};

// --- Actions ---
const addItem = () => {
    const year = form.year_id ? 
        (props.financialYears.find(y => y.value === form.year_id)?.label || new Date().getFullYear().toString()) : 
        new Date().getFullYear().toString();
    
    const newSerial = `${schedulePrefix.value}/${currentSerialCounter}/${year}`;
    
    form.items.push({
        id: nextItemId++,
        date: new Date(),
        serial_no: newSerial,
        economy_code_id: null,
        economy_code_item_id: null,
        payee_name: '',
        amount: 0,
        errors: {},
    });
    
    currentSerialCounter++;
};

const deleteItem = (id: number) => {
    if (form.items.length > 1) {
        form.items = form.items.filter((item) => item.id !== id);
        
        let counter = parseInt(baseSerial.value) || 1;
        const year = form.year_id ? 
            (props.financialYears.find(y => y.value === form.year_id)?.label || new Date().getFullYear().toString()) : 
            new Date().getFullYear().toString();
            
        form.items.forEach((item) => {
            item.serial_no = `${schedulePrefix.value}/${counter}/${year}`;
            counter++;
        });
        currentSerialCounter = counter;
    } else {
        toast.add({
            severity: 'warn',
            summary: 'Warning',
            detail: 'Must have at least one row',
            life: 3000,
        });
    }
};

const onEconomyCodeChange = (item: ScheduleItem) => {
    item.economy_code_item_id = null;
};

// --- ✅ FIXED: Submit Methods - Always redirect to index ---
const submitSchedule = () => {
    if (!validateHeader() || !validateLineItems()) {
        toast.add({
            severity: 'error',
            summary: 'Error',
            detail: 'Please fix all validation errors before submitting.',
            life: 3000,
        });
        return;
    }

    // ✅ Always redirect to schedules.index on success
    form.post('/schedules', {
        preserveScroll: true,
        onSuccess: (response) => {
            toast.add({
                severity: 'success',
                summary: 'Success',
                detail: 'Schedule saved successfully!',
                life: 3000,
            });
            // ✅ Redirect to schedule index
            window.location.href = '/schedules';
        },
        onError: (errors) => {
            console.error('Backend validation errors:', errors);
            if (errors.items) {
                form.items.forEach((item, index) => {
                    if (errors.items && errors.items[index]) {
                        item.errors = { ...item.errors, ...errors.items[index] };
                    }
                });
            }
            let errorMessage = 'Please fix the validation errors.';
            if (errors.message) {
                errorMessage = errors.message;
            }
            toast.add({
                severity: 'error',
                summary: 'Validation Error',
                detail: errorMessage,
                life: 5000,
            });
        }
    });
};

const saveDraft = () => {
    form.status = 'Draft';
    submitSchedule();
};

const saveAndRaiseVoucher = () => {
    form.status = 'Processed';
    submitSchedule();
};

// --- MOUNT ---
onMounted(() => {
    const year = form.year_id ? 
        (props.financialYears.find(y => y.value === form.year_id)?.label || new Date().getFullYear().toString()) : 
        new Date().getFullYear().toString();
    
    const parts = form.schedule_number.split('/');
    if (parts.length >= 3) {
        schedulePrefix.value = parts[0] + '/' + parts[1];
        baseSerial.value = parts[2];
        currentSerialCounter = parseInt(parts[2]) + 1;
    }
    
    if (form.items.length === 0) {
        const initialSerial = `${schedulePrefix.value}/${baseSerial.value}/${year}`;
        form.items.push({
            id: nextItemId++,
            date: new Date(),
            serial_no: initialSerial,
            economy_code_id: null,
            economy_code_item_id: null,
            payee_name: '',
            amount: 0,
            errors: {},
        });
        currentSerialCounter = parseInt(baseSerial.value) + 1;
    }

    const currentYear = new Date().getFullYear().toString();
    const foundYear = props.financialYears.find(
        (y: any) => y.label === currentYear,
    );
    if (foundYear) {
        form.year_id = foundYear.value;
    }
    
    fetchData(1);
});
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head title="Create Payment Schedule" />
        <Toast />

        <Card class="schedule-card">
            <template #title>
                <div class="flex items-center justify-between">
                    <span class="text-900 text-xl font-bold">Create Payment Schedule</span>
                    <div class="flex items-center gap-2">
                        <span class="text-500 text-sm">Schedule No:</span>
                        <span class="border-round flex items-center bg-blue-50 px-3 py-1 font-mono font-bold text-blue-700">
                            <i v-if="isLoadingNumber" class="pi pi-spin pi-spinner mr-2 text-xs"></i>
                            {{ form.schedule_number }}
                        </span>
                        <span v-if="baseSerial" class="text-500 text-xs ml-2">
                            Items: {{ form.items.length }}
                        </span>
                    </div>
                </div>
            </template>

            <template #content>
                <div class="surface-50 border-round mb-5 border-1 border-200 p-4">
                    <h5 class="text-600 mt-0 mb-3 font-semibold">Schedule Details</h5>

                    <div class="p-fluid grid">
                        <div class="col-2">
                            <div class="field">
                                <label for="year" class="text-500 mb-1 block text-sm font-semibold">Financial Year *</label>
                                <Dropdown id="year" v-model="form.year_id" :options="financialYears" optionLabel="label"
                                    optionValue="value" placeholder="Select Year" :class="{
                                        'p-invalid': validationErrors.year_id,
                                    }" @change="validationErrors.year_id = ''" />
                                <small class="p-error" v-if="validationErrors.year_id">{{ validationErrors.year_id }}</small>
                            </div>
                        </div>

                        <div class="col-3">
                            <div class="field">
                                <label for="sector" class="text-500 mb-1 block text-sm font-semibold">Sector</label>
                                <Dropdown id="sector" v-model="selectedSector" :options="sectorOptions"
                                    optionLabel="name" placeholder="Select Sector" class="w-full" />
                            </div>
                        </div>

                        <div class="col-7">
                            <div class="field">
                                <label for="mda" class="text-500 mb-1 block text-sm font-semibold">Ministry / Agency (MDA) *</label>
                                <Dropdown id="mda" v-model="form.mda_id" :options="mdaOptions" optionLabel="name"
                                    optionValue="id" placeholder="Select Ministry" filter :disabled="!selectedSector"
                                    :class="{ 'p-invalid': validationErrors.mda_id }" @change="validationErrors.mda_id = ''" />
                                <small class="p-error" v-if="validationErrors.mda_id">{{ validationErrors.mda_id }}</small>
                                <small v-else-if="!selectedSector" class="text-500 mt-1 block">Select a Sector first</small>
                            </div>
                        </div>

                        <div class="col-12">
                            <div class="field">
                                <label for="budget_code" class="text-500 mb-1 block text-sm font-semibold">Administrative Code *</label>
                                <Dropdown id="budget_code" v-model="form.budget_code_id" :options="budgetHeadOptions"
                                    optionLabel="label" optionValue="value" placeholder="Select Code" filter
                                    :disabled="!selectedSector" :class="{
                                        'p-invalid': validationErrors.budget_code_id,
                                    }" @change="validationErrors.budget_code_id = ''" />
                                <small class="p-error" v-if="validationErrors.budget_code_id">{{
                                    validationErrors.budget_code_id }}</small>
                                <small class="text-500 mt-1 block" v-else-if="!form.mda_id">Select an MDA first</small>
                                <small class="text-500 mt-1 block" v-else>Source of funds (Top left of document)</small>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="mb-5 px-4 text-center">
                    <p class="text-700 line-height-3 text-sm font-medium uppercase"
                        style="max-width: 800px; margin: 0 auto">
                        The Treasury Cash Officer at the Treasury Cash Office, Benin City is authorized to make the following payments
                        chargeable to the above head of expenditure.
                    </p>
                </div>

                <div class="mb-4">
                    <div class="mb-2 flex items-center justify-between">
                        <h5 class="text-900 m-0">Payment Line Items</h5>
                        <div class="flex gap-2">
                            <span class="text-500 text-xs self-center">
                                Next Serial: <strong>{{ schedulePrefix.value }}/{{ currentSerialCounter }}/{{ form.year_id ? (financialYears.find(y => y.value === form.year_id)?.label || new Date().getFullYear()) : new Date().getFullYear() }}</strong>
                            </span>
                            <Button label="Add Item" icon="pi pi-plus" size="small" outlined severity="primary"
                                @click="addItem" />
                        </div>
                    </div>

                    <DataTable :value="form.items" class="p-datatable-sm border-round" responsiveLayout="scroll">
                        <Column header="#" style="width: 5%">
                            <template #body="slotProps">
                                {{ slotProps.index + 1 }}
                            </template>
                        </Column>

                        <Column header="Date" style="width: 12%">
                            <template #body="slotProps">
                                <Calendar v-model="slotProps.data.date" dateFormat="dd/mm/yy" class="w-full" :class="{
                                    'p-invalid': slotProps.data.errors?.date,
                                }" placeholder="Select Date" />
                            </template>
                        </Column>

                        <Column header="Serial No." style="width: 18%">
                            <template #body="slotProps">
                                <InputText v-model="slotProps.data.serial_no" class="w-full font-mono text-sm font-bold"
                                    placeholder="Auto" readonly :class="{
                                        'p-invalid': slotProps.data.errors?.serial_no,
                                    }" />
                                <small class="p-error block" v-if="slotProps.data.errors?.serial_no">{{
                                    slotProps.data.errors.serial_no }}</small>
                            </template>
                        </Column>

                        <Column header="Economic Code" style="width: 14%">
                            <template #body="slotProps">
                                <Dropdown v-model="slotProps.data.economy_code_id" :options="economyCodeOptions"
                                    optionLabel="label" optionValue="value" placeholder="Select Economic Code"
                                    class="w-full" filter :class="{
                                        'p-invalid': slotProps.data.errors?.economy_code_id,
                                    }" @change="onEconomyCodeChange(slotProps.data)" />
                                <small class="p-error block" v-if="slotProps.data.errors?.economy_code_id">{{
                                    slotProps.data.errors.economy_code_id }}</small>
                            </template>
                        </Column>

                        <Column header="Economic Code Item" style="width: 14%">
                            <template #body="slotProps">
                                <Dropdown v-model="slotProps.data.economy_code_item_id"
                                    :options="getEconomyCodeItemOptions(slotProps.data.economy_code_id)"
                                    optionLabel="label" optionValue="value" placeholder="Select Item"
                                    class="w-full" filter :disabled="!slotProps.data.economy_code_id" :class="{
                                        'p-invalid': slotProps.data.errors?.economy_code_item_id,
                                    }" />
                                <small class="p-error block" v-if="slotProps.data.errors?.economy_code_item_id">{{
                                    slotProps.data.errors.economy_code_item_id }}</small>
                                <small v-else-if="!slotProps.data.economy_code_id" class="text-500 mt-1 block">Select
                                    Economic Code first</small>
                            </template>
                        </Column>

                        <Column header="Name of Payee" style="width: 16%">
                            <template #body="slotProps">
                                <Dropdown editable v-model="slotProps.data.payee_name" :options="lazyItems"
                                    optionLabel="label" optionValue="value" 
                                    :loading="loading"
                                    placeholder="Select who is being paid" filter @filter="onFilter"
                                    class="w-full" />
                                <small class="p-error block" v-if="slotProps.data.errors?.payee_name">{{
                                    slotProps.data.errors.payee_name }}</small>
                            </template>
                        </Column>

                        <Column header="Amount (₦)" style="width: 14%">
                            <template #body="slotProps">
                                <InputNumber v-model="slotProps.data.amount" mode="currency" currency="NGN"
                                    locale="en-NG" class="w-full" inputClass="text-right font-bold" :min="0" :class="{
                                        'p-invalid': slotProps.data.errors?.amount,
                                    }" />
                                <small class="p-error block" v-if="slotProps.data.errors?.amount">{{
                                    slotProps.data.errors.amount }}</small>
                            </template>
                        </Column>

                        <Column style="width: 6%" bodyClass="text-center">
                            <template #body="slotProps">
                                <Button icon="pi pi-trash" text severity="danger" rounded
                                    @click="deleteItem(slotProps.data.id)" :disabled="form.items.length <= 1" />
                            </template>
                        </Column>
                    </DataTable>
                    <div v-if="validationErrors.line_items" class="mt-2">
                        <Message severity="error" :closable="false">{{ validationErrors.line_items }}</Message>
                    </div>
                </div>

                <div class="mb-4 grid">
                    <div class="col-12 md:col-6">
                        <div class="surface-50 border-round h-full border-1 border-200 p-3">
                            <div class="mb-2 flex items-center gap-2">
                                <i class="pi pi-info-circle text-primary"></i>
                                <span class="font-semibold">Amount in Words:</span>
                            </div>
                            <div class="surface-0 border-round border-1 border-200 p-2">
                                <span class="text-900">{{ amountInWords }}</span>
                            </div>
                            <div class="mt-2 text-sm text-500">
                                Total Items: {{ form.items.length }}
                            </div>
                        </div>
                    </div>
                    <div class="col-12 md:col-6">
                        <div class="totals-section">
                            <h4 class="text-900 mt-0 mb-3">Schedule Summary</h4>
                            <div class="total-row flex justify-between text-xl font-bold">
                                <span>Total Amount:</span>
                                <span class="text-primary">{{ formatCurrency(scheduleTotal) }}</span>
                            </div>
                            <div class="total-row flex justify-between text-sm text-500 mt-2">
                                <span>Number of Items:</span>
                                <span>{{ form.items.length }}</span>
                            </div>
                            <div class="total-row flex justify-between text-sm text-500">
                                <span>Serial Range:</span>
                                <span v-if="form.items.length > 0" class="font-mono text-xs">
                                    {{ form.items[0]?.serial_no }} <span v-if="form.items.length > 1">→ {{ form.items[form.items.length - 1]?.serial_no }}</span>
                                </span>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="mt-5 flex justify-end gap-2">
                    <Button label="Save as Draft" icon="pi pi-save" severity="secondary" :loading="form.processing"
                        @click="saveDraft" />
                    <Button label="Save & Raise Voucher" icon="pi pi-arrow-right" iconPos="right" severity="success"
                        :loading="form.processing" @click="saveAndRaiseVoucher" />
                </div>
            </template>
        </Card>
    </AppLayout>
</template>

<style scoped>
.schedule-card {
    min-height: 100vh;
}

.totals-section {
    background: var(--surface-50);
    padding: 1rem;
    border-radius: 6px;
    border: 1px solid var(--surface-200);
    height: 100%;
}

.total-row {
    padding: 0.25rem 0;
}

:deep(.p-dropdown-label) {
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

:deep(.p-datatable .p-datatable-thead > tr > th) {
    background-color: var(--surface-100);
    color: var(--text-color);
    font-weight: 600;
}

.p-inputnumber-input {
    text-align: right;
}

.font-mono {
    font-family: 'Courier New', monospace;
}
</style>