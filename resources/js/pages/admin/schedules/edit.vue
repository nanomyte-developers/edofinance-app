<!-- <script setup lang="ts">
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

// --- Props ---
const props = defineProps({
    schedule: { type: Object, default: () => ({}) },
    administrativeCodes: { type: Array<any>, default: () => [] },
    administrativeSectorCodes: { type: Array<any>, default: () => [] },
    financialYears: { type: Array<any>, default: () => [] },
    economyCodes: { type: Array<any>, default: () => [] },
    economyCodeItems: { type: Array<any>, default: () => [] },
});

const toast = useToast();
const breadcrumbs = [
    { title: 'Schedules', href: '/schedules' },
    { title: 'Edit Schedule', href: '#' },
];

let nextItemId = 1;

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
    schedule_number: '',
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

// --- Computed Options ---
const sectorOptions = computed(() => {
    return props.administrativeCodes.filter(
        (item: any) =>
            item.name.includes('SECTOR') || item.code.endsWith('0000000000'),
    );
});

const mdaOptions = computed(() => {
    if (!selectedSector.value) return [];
    const prefix = selectedSector.value.code.substring(0, 2);
    return props.administrativeCodes.filter(
        (item: any) =>
            item.code.startsWith(prefix) &&
            item.id !== selectedSector.value.id &&
            !item.name.includes('SECTOR') &&
            !item.code.endsWith('0000000000'),
    );
});

const budgetHeadOptions = computed(() => {
    if (!form.mda_id) return [];
    return props.administrativeSectorCodes
        .filter((head: any) => head.administrative_code_id === form.mda_id)
        .map((head: any) => ({
            value: head.id,
            label: `${head.code} - ${head.name}`,
        }));
});

// Economic Code Options
const economyCodeOptions = computed(() => {
    if (!props.economyCodes || props.economyCodes.length === 0) {
        console.warn('No Economic Codes received');
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
            console.warn('Unexpected Economic Code structure:', code);
            return {
                value: code.id || code.value,
                label: code.label || `${code.code} - ${code.name}` || 'Unknown',
            };
        }
    });
});

// Filter Economic Code items based on selected Economic Code for each row
const getEconomyCodeItemOptions = (economyCodeId: number | null) => {
    if (
        !economyCodeId ||
        !props.economyCodeItems ||
        props.economyCodeItems.length === 0
    ) {
        return [];
    }

    return props.economyCodeItems
        .filter((item: any) => {
            const itemEconomyCodeId =
                item.economy_code_id ||
                item.economyCodeId ||
                item.economy_code?.id;
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

watch(
    () => form.mda_id,
    () => {
        form.budget_code_id = null;
    },
);

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
        // Don't sync serials for edit - keep existing serial numbers
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

// --- Financial Helpers ---
const formatCurrency = (value: number) => {
    return new Intl.NumberFormat('en-NG', {
        style: 'currency',
        currency: 'NGN',
    }).format(value);
};

const convertNumberToWords = (amount: number): string => {
    if (isNaN(amount) || amount === 0) return 'Zero Naira';
    const units = [
        '',
        'One',
        'Two',
        'Three',
        'Four',
        'Five',
        'Six',
        'Seven',
        'Eight',
        'Nine',
    ];
    const teens = [
        'Ten',
        'Eleven',
        'Twelve',
        'Thirteen',
        'Fourteen',
        'Fifteen',
        'Sixteen',
        'Seventeen',
        'Eighteen',
        'Nineteen',
    ];
    const tens = [
        '',
        '',
        'Twenty',
        'Thirty',
        'Forty',
        'Fifty',
        'Sixty',
        'Seventy',
        'Eighty',
        'Ninety',
    ];

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
    let koboAmount = Math.round((amount - nairaAmount) * 100);

    if (nairaAmount >= 1000000) {
        words +=
            convertHundreds(Math.floor(nairaAmount / 1000000)) + ' Million ';
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
    let currentSerial = '1';

    if (form.items.length > 0) {
        currentSerial = form.items[0].serial_no;
    } else if (form.schedule_number) {
        const parts = form.schedule_number.split('/');
        if (parts.length >= 3 && !isNaN(parseInt(parts[2]))) {
            currentSerial = parts[2];
        }
    }

    form.items.push({
        id: nextItemId++,
        date: new Date(),
        serial_no: currentSerial,
        economy_code_id: null,
        economy_code_item_id: null,
        payee_name: '',
        amount: 0,
        errors: {},
    });
};

const deleteItem = (id: number) => {
    if (form.items.length > 1) {
        form.items = form.items.filter((item) => item.id !== id);
    } else {
        toast.add({
            severity: 'warn',
            summary: 'Warning',
            detail: 'Must have at least one row',
            life: 3000,
        });
    }
};

// Watch for Economic Code changes to reset Economic Code item
const onEconomyCodeChange = (item: ScheduleItem) => {
    item.economy_code_item_id = null;
};

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

    form.put(`/schedules/${props.schedule.id}`, {
        preserveScroll: true,
        onSuccess: () => {
            toast.add({
                severity: 'success',
                summary: 'Success',
                detail: 'Schedule updated successfully!',
                life: 3000,
            });
        },
        onError: (errors) => {
            console.error('Backend validation errors:', errors);

            if (errors.items) {
                form.items.forEach((item, index) => {
                    if (errors.items && errors.items[index]) {
                        item.errors = {
                            ...item.errors,
                            ...errors.items[index],
                        };
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
        },
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

// --- Initialize form with schedule data ---
const initializeForm = () => {
    console.log('Initializing form with schedule data:', props.schedule);
    console.log('Administrative Codes:', props.administrativeCodes);
    console.log(
        'Administrative Sector Codes:',
        props.administrativeSectorCodes,
    );

    if (props.schedule && props.schedule.id) {
        form.year_id = props.schedule.year_id;
        form.mda_id = props.schedule.mda_id;
        form.budget_code_id = props.schedule.budget_code_id;
        form.schedule_number = props.schedule.schedule_number;
        form.status = props.schedule.status;
        form.total_amount = props.schedule.total_amount;

        // Initialize items - PRESERVE EXISTING SERIAL NUMBERS
        if (props.schedule.items && props.schedule.items.length > 0) {
            form.items = props.schedule.items.map((item: any) => ({
                id: item.id || nextItemId++,
                date: item.date ? new Date(item.date) : new Date(),
                serial_no: item.serial_no || item.serial_number || '', // Use existing serial number
                economy_code_id: item.economy_code_id,
                economy_code_item_id: item.economy_code_item_id,
                payee_name: item.payee_name || '',
                amount: item.amount || 0,
                errors: {},
            }));
            console.log('Items initialized with serial numbers:', form.items);
        } else {
            console.log('No items found in schedule data');
        }

        // Find and set selected sector based on MDA
        if (props.schedule.mda_id) {
            const mda = props.administrativeCodes.find(
                (code: any) => code.id === props.schedule.mda_id,
            );
            console.log('Found MDA:', mda);

            if (mda) {
                const mdaCodePrefix = mda.code.substring(0, 2);
                console.log('MDA Code Prefix:', mdaCodePrefix);

                // Find the sector that matches the MDA code prefix
                selectedSector.value = props.administrativeCodes.find(
                    (sector: any) =>
                        sector.code.startsWith(mdaCodePrefix) &&
                        (sector.name.includes('SECTOR') ||
                            sector.code.endsWith('0000000000')),
                );
                console.log('Selected Sector:', selectedSector.value);
            }
        }

        // Log current form state for debugging
        console.log('Form state after initialization:', {
            year_id: form.year_id,
            mda_id: form.mda_id,
            budget_code_id: form.budget_code_id,
            selectedSector: selectedSector.value,
            mdaOptions: mdaOptions.value,
            budgetHeadOptions: budgetHeadOptions.value,
        });
    } else {
        console.error('No schedule data provided');
    }
};

// --- MOUNT: Initialize form ---
onMounted(() => {
    console.log('Edit component mounted');
    console.log('Schedule props:', props.schedule);

    initializeForm();

    // Add initial row if no items exist
    if (form.items.length === 0) {
        console.log('No items found, adding initial row');
        addItem();
    }
});

// Watch for sector changes to update MDA options
watch(selectedSector, (newSector) => {
    console.log('Sector changed:', newSector);
    if (newSector) {
        // If the current MDA doesn't belong to the selected sector, clear it
        const mdaBelongsToSector = mdaOptions.value.some(
            (mda: any) => mda.id === form.mda_id,
        );
        if (!mdaBelongsToSector) {
            form.mda_id = null;
            form.budget_code_id = null;
        }
    }
});

// Watch for MDA changes to update budget head options
watch(
    () => form.mda_id,
    (newMdaId) => {
        console.log('MDA changed:', newMdaId);
        console.log('Available budget heads:', budgetHeadOptions.value);

        // If the current budget head doesn't belong to the selected MDA, clear it
        if (newMdaId && form.budget_code_id) {
            const budgetHeadBelongsToMda = budgetHeadOptions.value.some(
                (head: any) => head.value === form.budget_code_id,
            );
            if (!budgetHeadBelongsToMda) {
                form.budget_code_id = null;
            }
        }
    },
);
</script> -->
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
        // Use an API endpoint (e.g., /api/items) instead of an Inertia endpoint for XHR requests
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


const onFilter = (event) => {
    filterValue.value = event.value;
    // Reset page to 1 and fetch filtered data
    fetchData(1, event.value);
};



// --- Props ---
const props = defineProps({
    schedule: { type: Object, default: () => ({}) },
    administrativeCodes: { type: Array<any>, default: () => [] },
    administrativeSectorCodes: { type: Array<any>, default: () => [] },
    financialYears: { type: Array<any>, default: () => [] },
    economyCodes: { type: Array<any>, default: () => [] },
    economyCodeItems: { type: Array<any>, default: () => [] },
    mdas: { type: Array<any>, default: () => [] },
});

const toast = useToast();
const breadcrumbs = [
    { title: 'Schedules', href: '/schedules' },
    { title: 'Edit Schedule', href: '#' },
];

let nextItemId = 1;

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
    schedule_number: '',
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
const isInitializing = ref(true); // Add flag to track initialization

// --- Computed Options ---
const sectorOptions = computed(() => {
    return props.administrativeCodes.filter(
        (item: any) =>
            item.name.includes('SECTOR') || item.code.endsWith('0000000000'),
    );
});

// const mdaOptions = computed(() => {
//     if (!selectedSector.value) return [];
//     const prefix = selectedSector.value.code.substring(0, 2);
//     return props.administrativeCodes.filter(
//         (item: any) =>
//             item.code.startsWith(prefix) &&
//             item.id !== selectedSector.value.id &&
//             !item.name.includes('SECTOR') &&
//             !item.code.endsWith('0000000000')
//     );
// });

const mdaOptions = computed(() => {
    console.log(props.mdas, selectedSector.value);
    if (!selectedSector.value) return [];


    const mdaz =  props.mdas
        .filter((item: any) => item.administrative_code_id === selectedSector.value.id);

        console.log(mdaz, selectedSector.value.id);

        return mdaz;
});


// const budgetHeadOptions = computed(() => {
//     if (!form.mda_id) return [];
//     return props.administrativeSectorCodes
//         .filter((head: any) => head.administrative_code_id === form.mda_id)
//         .map((head: any) => ({
//             value: head.id,
//             label: `${head.code} - ${head.name}`,
//         }));
// });
const budgetHeadOptions = computed(() => {
    // if (!form.mda_id) return [];
    if(selectedSector.value === null) return [];

    return props.administrativeSectorCodes
        .filter((head: any) => head.administrative_code_id === selectedSector.value.id)
        .map((head: any) => ({
            value: head.id,
            label: `${head.code} - ${head.name}`,
        }));
});
// Economic Code Options
const economyCodeOptions = computed(() => {
    if (!props.economyCodes || props.economyCodes.length === 0) {
        console.warn('No Economic Codes received');
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
            console.warn('Unexpected Economic Code structure:', code);
            return {
                value: code.id || code.value,
                label: code.label || `${code.code} - ${code.name}` || 'Unknown',
            };
        }
    });
});

// Filter Economic Code items based on selected Economic Code for each row
const getEconomyCodeItemOptions = (economyCodeId: number | null) => {
    if (
        !economyCodeId ||
        !props.economyCodeItems ||
        props.economyCodeItems.length === 0
    ) {
        return [];
    }

    return props.economyCodeItems
        .filter((item: any) => {
            const itemEconomyCodeId =
                item.economy_code_id ||
                item.economyCodeId ||
                item.economy_code?.id;
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
    if (isInitializing.value) return; // Don't clear during initialization

    form.mda_id = null;
    form.budget_code_id = null;
});

watch(
    () => form.mda_id,
    (newMdaId, oldMdaId) => {
        if (isInitializing.value) return; // Don't clear during initialization

        // Only clear budget code if MDA actually changed (not during init)
        if (newMdaId !== oldMdaId) {
            form.budget_code_id = null;
        }
    },
);

// --- API CALL: FETCH DYNAMIC NUMBER ---
const fetchNextScheduleNumber = async () => {
    if (!form.year_id || !form.mda_id || isInitializing.value) return;

    isLoadingNumber.value = true;

    try {
        const response = await axios.get('/schedules/next-number', {
            params: {
                year_id: form.year_id,
                mda_id: form.mda_id,
            },
        });

        // Only update schedule number if we're not in edit mode with existing number
        if (!props.schedule?.schedule_number) {
            form.schedule_number = response.data.schedule_number;
        }
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

// Trigger on Year or MDA change (but not during initialization)
watch([() => form.year_id, () => form.mda_id], () => {
    if (isInitializing.value) return;
    fetchNextScheduleNumber();
});

// --- Financial Helpers ---
const formatCurrency = (value: number) => {
    return new Intl.NumberFormat('en-NG', {
        style: 'currency',
        currency: 'NGN',
    }).format(value);
};

const convertNumberToWords = (amount: number): string => {
    if (isNaN(amount) || amount === 0) return 'Zero Naira';
    const units = [
        '',
        'One',
        'Two',
        'Three',
        'Four',
        'Five',
        'Six',
        'Seven',
        'Eight',
        'Nine',
    ];
    const teens = [
        'Ten',
        'Eleven',
        'Twelve',
        'Thirteen',
        'Fourteen',
        'Fifteen',
        'Sixteen',
        'Seventeen',
        'Eighteen',
        'Nineteen',
    ];
    const tens = [
        '',
        '',
        'Twenty',
        'Thirty',
        'Forty',
        'Fifty',
        'Sixty',
        'Seventy',
        'Eighty',
        'Ninety',
    ];

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
    let koboAmount = Math.round((amount - nairaAmount) * 100);

    // Billions
    if (nairaAmount >= 1000000000) {
        words +=
            convertHundreds(Math.floor(nairaAmount / 1000000000)) + ' Billion ';
        nairaAmount %= 1000000000;
    }

    if (nairaAmount >= 1000000) {
        words +=
            convertHundreds(Math.floor(nairaAmount / 1000000)) + ' Million ';
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
    let currentSerial = '1';

    if (form.items.length > 0) {
        currentSerial = form.items[0].serial_no;
    } else if (form.schedule_number) {
        const parts = form.schedule_number.split('/');
        if (parts.length >= 3 && !isNaN(parseInt(parts[2]))) {
            currentSerial = parts[2];
        }
    }

    form.items.push({
        id: nextItemId++,
        date: new Date(),
        serial_no: currentSerial,
        economy_code_id: null,
        economy_code_item_id: null,
        payee_name: '',
        amount: 0,
        errors: {},
    });
};

const deleteItem = (id: number) => {
    if (form.items.length > 1) {
        form.items = form.items.filter((item) => item.id !== id);
    } else {
        toast.add({
            severity: 'warn',
            summary: 'Warning',
            detail: 'Must have at least one row',
            life: 3000,
        });
    }
};

// Watch for Economic Code changes to reset Economic Code item
const onEconomyCodeChange = (item: ScheduleItem) => {
    item.economy_code_item_id = null;
};

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

    form.put(`/schedules/${props.schedule.id}`, {
        preserveScroll: true,
        onSuccess: () => {
            toast.add({
                severity: 'success',
                summary: 'Success',
                detail: 'Schedule updated successfully!',
                life: 3000,
            });
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

// --- Initialize form with schedule data ---
const initializeForm = () => {
    console.log('Initializing form with schedule data:', props.schedule);
    console.log('Administrative Codes:', props.administrativeCodes);
    console.log('Administrative Sector Codes:', props.administrativeSectorCodes);


    selectedSector.value =   props.administrativeCodes.find((code: any) => code.id === props.schedule.sector);
    if (props.schedule && props.schedule.id) {
        // Set the flag to prevent watchers from firing during initialization
        isInitializing.value = true;

        // Set basic form data
        form.year_id = props.schedule.year_id;
        form.mda_id = props.schedule.mda_id;
        form.budget_code_id = props.schedule.budget_code_id;
        form.schedule_number = props.schedule.schedule_number; // Keep original schedule number
        form.status = props.schedule.status;
        form.total_amount = props.schedule.total_amount;

        console.log('Form basic data set:', {
            year_id: form.year_id,
            mda_id: form.mda_id,
            budget_code_id: form.budget_code_id,
            schedule_number: form.schedule_number
        });

        // Find and set selected sector based on MDA
        if (props.schedule.mda_id) {
            const mda = props.mdas.find((code: any) => code.id === props.schedule.mda_id);
            console.log('Found MDA:', mda);

            // if (mda) {
            //     const mdaCodePrefix = mda.code.substring(0, 2);
            //     console.log('MDA Code Prefix:', mdaCodePrefix);

            //     // Find the sector that matches the MDA code prefix
            //     selectedSector.value = props.administrativeCodes.find(
            //         (sector: any) =>
            //             sector.code.startsWith(mdaCodePrefix) &&
            //             (sector.name.includes('SECTOR') || sector.code.endsWith('0000000000'))
            //     );
            //     console.log('Selected Sector:', selectedSector.value);
            // }
        }

        // Initialize items - PRESERVE EXISTING SERIAL NUMBERS
        if (props.schedule.items && props.schedule.items.length > 0) {
            form.items = props.schedule.items.map((item: any) => ({
                id: item.id || nextItemId++,
                date: item.date ? new Date(item.date) : new Date(),
                serial_no: item.serial_no || item.serial_number || '', // Use existing serial number
                economy_code_id: item.economy_code_id,
                economy_code_item_id: item.economy_code_item_id,
                payee_name: item.payee_name || '',
                amount: item.amount || 0,
                errors: {},
            }));
            console.log('Items initialized with serial numbers:', form.items);
        } else {
            console.log('No items found in schedule data');
        }

        // Log current form state for debugging
        console.log('Form state after initialization:', {
            year_id: form.year_id,
            mda_id: form.mda_id,
            budget_code_id: form.budget_code_id,
            selectedSector: selectedSector.value,
            mdaOptions: mdaOptions.value,
            budgetHeadOptions: budgetHeadOptions.value,
            schedule_number: form.schedule_number
        });

        // Set a small timeout to ensure Vue has updated the DOM, then clear the initialization flag
        setTimeout(() => {
            isInitializing.value = false;
            console.log('Initialization complete - watchers are now active');
        }, 100);
    } else {
        console.error('No schedule data provided');
        isInitializing.value = false;
    }
};

// --- MOUNT: Initialize form ---
onMounted(() => {
    console.log('Edit component mounted');
    console.log('Schedule props:', props.schedule);

    initializeForm();

    // Add initial row if no items exist
    if (form.items.length === 0) {
        console.log('No items found, adding initial row');
        addItem();
    }
});

// Watch for sector changes to update MDA options (only after initialization)
watch(selectedSector, (newSector) => {
    if (isInitializing.value) {
        console.log('Sector change during initialization - ignoring');
        return;
    }
    console.log('Sector changed:', newSector);
    if (newSector) {
        // If the current MDA doesn't belong to the selected sector, clear it
        const mdaBelongsToSector = mdaOptions.value.some((mda: any) => mda.id === form.mda_id);
        if (!mdaBelongsToSector) {
            form.mda_id = null;
            form.budget_code_id = null;
        }
    }
});

// Watch for MDA changes to update budget head options (only after initialization)
watch(() => form.mda_id, (newMdaId) => {
    if (isInitializing.value) {
        console.log('MDA change during initialization - ignoring');
        return;
    }
    console.log('MDA changed:', newMdaId);
    console.log('Available budget heads:', budgetHeadOptions.value);

    // If the current budget head doesn't belong to the selected MDA, clear it
    if (newMdaId && form.budget_code_id) {
        const budgetHeadBelongsToMda = budgetHeadOptions.value.some(
            (head: any) => head.value === form.budget_code_id
        );
        if (!budgetHeadBelongsToMda) {
            form.budget_code_id = null;
        }
    }
});


fetchData(1);
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">

        <Head :title="`Edit Payment Schedule - ${form.schedule_number}`" />
        <Toast />

        <Card class="schedule-card">
            <template #title>
                <div class="flex items-center justify-between">
                    <span class="text-900 text-xl font-bold">Edit Payment Schedule</span>
                    <div class="flex items-center gap-2">
                        <span class="text-500 text-sm">Schedule No:</span>
                        <span
                            class="border-round flex items-center bg-blue-50 px-3 py-1 font-mono font-bold text-blue-700">
                            <i v-if="isLoadingNumber" class="pi pi-spin pi-spinner mr-2 text-xs"></i>
                            {{ form.schedule_number }}
                        </span>
                    </div>
                </div>
            </template>

            <template #content>
                <div class="surface-50 border-round mb-5 border-1 border-200 p-4">
                    <h5 class="text-600 mt-0 mb-3 font-semibold">
                        Schedule Details
                    </h5>

                    <div class="p-fluid grid">
                        <div class="col-2">
                            <div class="field">
                                <label for="year" class="text-500 mb-1 block text-sm font-semibold">
                                    Financial Year *
                                </label>
                                <Dropdown id="year" v-model="form.year_id" :options="financialYears" optionLabel="label"
                                    optionValue="value" placeholder="Select Year" :class="{
                                        'p-invalid': validationErrors.year_id,
                                    }" @change="validationErrors.year_id = ''" />
                                <small class="p-error" v-if="validationErrors.year_id">{{ validationErrors.year_id
                                    }}</small>
                                <small v-else class="text-500 mt-1 block">
                                    Selected: {{form.year_id ? financialYears.find(f => f.value ===
                                    form.year_id)?.label : 'None' }}
                                </small>
                            </div>
                        </div>

                        <div class="col-3">
                            <div class="field">
                                <label for="sector" class="text-500 mb-1 block text-sm font-semibold">
                                    Sector
                                </label>
                                <Dropdown id="sector" v-model="selectedSector" :options="sectorOptions"
                                    optionLabel="name" placeholder="Select Sector" class="w-full" />
                                <small class="text-500 mt-1 block" v-if="selectedSector">
                                    Selected: {{ selectedSector.name }}
                                </small>
                                <small v-else class="text-500 mt-1 block">
                                    {{ sectorOptions.length }} sectors available
                                </small>
                            </div>
                        </div>

                        <div class="col-7">
                            <div class="field">
                                <label for="mda" class="text-500 mb-1 block text-sm font-semibold">
                                    Ministry / Agency (MDA) *
                                </label>
                                <Dropdown id="mda" v-model="form.mda_id" :options="mdaOptions" optionLabel="name"
                                    optionValue="id" placeholder="Select Ministry" filter :disabled="!selectedSector"
                                    :class="{
                                        'p-invalid': validationErrors.mda_id,
                                    }" @change="validationErrors.mda_id = ''" />
                                <small class="p-error" v-if="validationErrors.mda_id">{{ validationErrors.mda_id
                                    }}</small>
                                <small v-else-if="!selectedSector" class="text-500 mt-1 block">Select a Sector
                                    first</small>
                                <small v-else class="text-500 mt-1 block">
                                    {{ mdaOptions.length }} MDAs available
                                    <span v-if="form.mda_id"> | Selected: {{mdaOptions.find(m => m.id ===
                                        form.mda_id)?.name }}</span>
                                </small>
                            </div>
                        </div>

                        <div class="col-12">
                            <div class="field">
                                <label for="budget_code" class="text-500 mb-1 block text-sm font-semibold">
                                    Administrative Code *
                                </label>
                                <Dropdown id="budget_code" v-model="form.budget_code_id" :options="budgetHeadOptions"
                                    optionLabel="label" optionValue="value" placeholder="Select Code" filter
                                    :disabled="!selectedSector" :class="{
                                        'p-invalid':
                                            validationErrors.budget_code_id,
                                    }" @change="
                                        validationErrors.budget_code_id = ''
                                        " />
                                <small class="p-error" v-if="validationErrors.budget_code_id">{{
                                    validationErrors.budget_code_id
                                }}</small>
                                <small class="text-500 mt-1 block" v-else-if="!form.mda_id">Select an MDA first</small>
                                <small class="text-500 mt-1 block" v-else>
                                    {{ budgetHeadOptions.length }} administrative codes available
                                    <span v-if="form.budget_code_id"> | Selected: {{budgetHeadOptions.find(b => b.value
                                        === form.budget_code_id)?.label }}</span>
                                </small>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Rest of the template remains the same -->
                <div class="mb-5 px-4 text-center">
                    <p class="text-700 line-height-3 text-sm font-medium uppercase"
                        style="max-width: 800px; margin: 0 auto">
                        The Treasury Cash Officer at the Treasury Cash Office,
                        Benin City is authorized to make the following payments
                        chargeable to the above head of expenditure.
                    </p>
                </div>

                <div class="mb-4">
                    <div class="mb-2 flex items-center justify-between">
                        <h5 class="text-900 m-0">
                            Payment Line Items ({{ form.items.length }})
                        </h5>
                        <Button label="Add Item" icon="pi pi-plus" size="small" outlined severity="primary"
                            @click="addItem" />
                    </div>

                    <DataTable :value="form.items" class="p-datatable-sm border-round " responsiveLayout="scroll"
                        :scrollable="true" scrollHeight="flex">
                        <Column header="Date" style="width: 12%; min-width: 120px">
                            <template #body="slotProps">
                                <Calendar v-model="slotProps.data.date" dateFormat="dd/mm/yy" class="w-full" :class="{
                                    'p-invalid':
                                        slotProps.data.errors?.date,
                                }" placeholder="Select Date" />
                            </template>
                        </Column>

                        <Column header="Serial No." style="width: 8%; min-width: 80px">
                            <template #body="slotProps">
                                <InputText v-model="slotProps.data.serial_no" class="w-full text-center font-bold"
                                    placeholder="Serial" :class="{
                                        'p-invalid':
                                            slotProps.data.errors?.serial_no,
                                    }" />
                                <small class="p-error block" v-if="slotProps.data.errors?.serial_no">{{
                                    slotProps.data.errors.serial_no
                                }}</small>
                            </template>
                        </Column>

                        <Column header="Economic Code" style="width: 18%; min-width: 200px">
                            <template #body="slotProps">
                                <Dropdown v-model="slotProps.data.economy_code_id" :options="economyCodeOptions"
                                    optionLabel="label" optionValue="value" placeholder="Select Economic Code"
                                    class="w-full" filter :class="{
                                        'p-invalid':
                                            slotProps.data.errors
                                                ?.economy_code_id,
                                    }" @change="
                                        onEconomyCodeChange(slotProps.data)
                                        " />
                                <small class="p-error block" v-if="
                                    slotProps.data.errors?.economy_code_id
                                ">{{
                                        slotProps.data.errors.economy_code_id
                                    }}</small>
                            </template>
                        </Column>

                        <Column header="Economic Code Item" style="width: 18%; min-width: 200px">
                            <template #body="slotProps">
                                <Dropdown v-model="slotProps.data.economy_code_item_id
                                    " :options="getEconomyCodeItemOptions(
                                        slotProps.data.economy_code_id,
                                    )
                                        " optionLabel="label" optionValue="value" placeholder="Select Item" class="w-full"
                                    filter :disabled="!slotProps.data.economy_code_id" :class="{
                                        'p-invalid':
                                            slotProps.data.errors
                                                ?.economy_code_item_id,
                                    }" />
                                <small class="p-error block" v-if="
                                    slotProps.data.errors
                                        ?.economy_code_item_id
                                ">{{
                                        slotProps.data.errors
                                            .economy_code_item_id
                                    }}</small>
                                <small v-else-if="!slotProps.data.economy_code_id" class="text-500 mt-1 block">Select
                                    Economic Code first</small>
                            </template>
                        </Column>

                        <Column header="Name of Payee" style="width: 28%; min-width: 300px">
                            <template #body="slotProps">
                                <!-- <InputText v-model="slotProps.data.payee_name" class="payee-input w-full"
                                    placeholder="Who is being paid?" :class="{
                                        'p-invalid':
                                            slotProps.data.errors?.payee_name,
                                    }" /> -->


                                    <Dropdown editable v-model="slotProps.data.payee_name" :options="lazyItems"
                                    optionLabel="label" optionValue="value" 
                                    :loading="loading"
                                    placeholder="Select who is being paid" filter @filter="onFilter"
                                    class="w-full" />


                                <small class="p-error block" v-if="slotProps.data.errors?.payee_name">{{
                                    slotProps.data.errors.payee_name
                                }}</small>
                            </template>
                        </Column>

                        <Column header="Amount (₦)" style="width: 16%; min-width: 150px">
                            <template #body="slotProps">
                                <InputNumber v-model="slotProps.data.amount" mode="currency" currency="NGN"
                                    locale="en-NG" class="w-full" inputClass="text-right font-bold" :min="0" :class="{
                                        'p-invalid':
                                            slotProps.data.errors?.amount,
                                    }" />
                                <small class="p-error block" v-if="slotProps.data.errors?.amount">{{
                                    slotProps.data.errors.amount }}</small>
                            </template>
                        </Column>

                        <Column style="width: 6%; min-width: 60px" bodyClass="text-center">
                            <template #body="slotProps">
                                <Button icon="pi pi-trash" text severity="danger" rounded
                                    @click="deleteItem(slotProps.data.id)" :disabled="form.items.length <= 1" />
                            </template>
                        </Column>
                    </DataTable>
                    <div v-if="validationErrors.line_items" class="mt-2">
                        <Message severity="error" :closable="false">{{
                            validationErrors.line_items
                            }}</Message>
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
                                <span class="text-900">{{
                                    amountInWords
                                    }}</span>
                            </div>
                        </div>
                    </div>
                    <div class="col-12 md:col-6">
                        <div class="totals-section">
                            <h4 class="text-900 mt-0 mb-3">Schedule Summary</h4>
                            <div class="total-row flex justify-between text-xl font-bold">
                                <span>Total Amount:</span>
                                <span class="text-primary">{{
                                    formatCurrency(scheduleTotal)
                                    }}</span>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="mt-5 flex justify-end gap-2">
                    <Button label="Update Draft" icon="pi pi-save" severity="secondary" :loading="form.processing"
                        @click="saveDraft" />
                    <Button label="Update & Raise Voucher" icon="pi pi-arrow-right" iconPos="right" severity="success"
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

:deep(.p-inputnumber-input) {
    text-align: right;
}

/* Make payee input field expandable */
:deep(.payee-input) {
    min-width: 100%;
}

/* Ensure table columns are properly sized */
:deep(.p-datatable-table) {
    table-layout: fixed;
}

:deep(.p-datatable-tbody > tr > td) {
    word-wrap: break-word;
    overflow-wrap: break-word;
}

/* Make payee column content wrap properly */
:deep(.p-datatable-tbody > tr > td:nth-child(5)) {
    min-width: 300px;
    max-width: 400px;
    word-break: break-word;
}
</style>
