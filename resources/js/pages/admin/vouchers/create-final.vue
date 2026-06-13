<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import Badge from 'primevue/badge';
import Button from 'primevue/button';
import Calendar from 'primevue/calendar';
import Card from 'primevue/card';
import Column from 'primevue/column';
import DataTable from 'primevue/datatable';
import Divider from 'primevue/divider';
import Dropdown from 'primevue/dropdown';
import FileUpload from 'primevue/fileupload';
import InputNumber from 'primevue/inputnumber';
import InputText from 'primevue/inputtext';
import Message from 'primevue/message';
import Textarea from 'primevue/textarea';
import Toast from 'primevue/toast';
import { useToast } from 'primevue/usetoast';
import { computed, onMounted, ref, watch } from 'vue';
import axios from 'axios';
import moment from 'moment';

// Layout and types
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';

const toast = useToast();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Final Accounts', href: '/final-accounts/vouchers/create' },
    { title: 'Create Voucher', href: '#' },
];

let nextItemId = 1;

const voucherTypes = [
    {
        label: 'Standard',
        value: 'standard',
    },
    {
        label: 'Prepayment',
        value: 'prepayment',
    },
    {
        label: 'Salary',
        value: 'salary',
    },
];

// Define types for line items
interface LineItem {
    id: number;
    description: string;
    economy_code_id: number | null;
    economy_code_item_id: number | null;
    quantity: number;
    unit_price: number;
    sub_total: number;
    programme_code_id: number | null;
    programme_code: string | null;
    programme_name: string | null;
    budget_code: string | null;
    errors?: {
        description?: string;
        economy_code_id?: string;
        economy_code_item_id?: string;
        quantity?: string;
        unit_price?: string;
        sub_total?: string;
        programme_code_id?: string;
        budget_code?: string;
    };
}

// Document types
interface UploadedDocument {
    id?: number;
    type: string;
    label: string;
    file: File;
    document_type: string;
}

// Props from Laravel controller
const props = defineProps({
    voucherType: {
        type: String,
        required: true,
        default: 'standard',
    },
    schedule: {
        type: Object,
        default: () => null,
    },
    defaultAccount: String,
    mdas: {
        type: Array,
        default: () => [],
    },
    financialYears: {
        type: Array,
        default: () => [],
    },
    economyCodes: {
        type: Array,
        default: () => [],
    },
    economyCodeItems: {
        type: Array,
        default: () => [],
    },
    today: {
        type: String,
        default: new Date().toISOString().split('T')[0],
    },
    voucherNumber: {
        type: String,
        required: true,
        default: '',
    },
    programmeCodes: {
        type: Array,
        default: () => [],
    },
});

// Page title
const pageTitle = computed(() => {
    const type = props.voucherType.charAt(0).toUpperCase() + props.voucherType.slice(1);
    return `Create Final Accounts ${type} Voucher`;
});

// Validation state
const validationErrors = ref({
    year_id: '',
    mda_id: '',
    voucher_date: '',
    narration: '',
    line_items: '',
    documents: '',
    voucher_number: '',
    payee_name: '',
    bank_activity_id: '',
});

// Document management
const optionalDocuments = ref<UploadedDocument[]>([]);
const selectedDocumentType = ref<string>('');
const fileUploadRef = ref();

// Economic Code Options
const economyCodeOptions = computed(() => {
    return props.economyCodes;
});

// Programme Codes Management
const programmeCodeOptions = ref<any[]>([]);
const programmeCodeSearchLoading = ref(false);
const programmeCodeSearchDebounce = ref<any>(null);
const selectedProgrammeCodeMap = ref<Record<number, any>>({});

// Bank Activities
const bankActivities = ref<any[]>([]);
const bankActivitiesLoading = ref(false);
const bankActivitiesSearchDebounce = ref<any>(null);

// Fetch programme codes from API (searchable)
const searchProgrammeCodes = async (search = '') => {
    programmeCodeSearchLoading.value = true;
    try {
        const response = await axios.get('/programme-codes/search', {
            params: {
                q: search,
                financial_year_id: form.year_id,
            }
        });
        
        programmeCodeOptions.value = response.data.map((pc: any) => ({
            id: pc.id,
            code: pc.code,
            name: pc.name,
            budget_code: pc.budget_code,
            remaining_budget: pc.remaining_budget,
            economic_code_id: pc.economic_code_id,
            economic_code_code: pc.economic_code_code,
            economic_code_name: pc.economic_code_name,
            display_text: pc.display_text,
            detail_text: pc.detail_text,
            label: `${pc.code} - ${pc.name} (₦${Number(pc.remaining_budget).toLocaleString()})`,
            value: pc.id
        }));
    } catch (error) {
        console.error('Error searching programme codes:', error);
    } finally {
        programmeCodeSearchLoading.value = false;
    }
};

// Handle programme code search input
const onProgrammeCodeSearch = (event: any) => {
    const query = event.query;
    if (programmeCodeSearchDebounce.value) {
        clearTimeout(programmeCodeSearchDebounce.value);
    }
    programmeCodeSearchDebounce.value = setTimeout(() => {
        searchProgrammeCodes(query);
    }, 300);
};

// Handle programme code selection
const onProgrammeCodeSelect = (item: LineItem, selectedProgramme: number | null) => {
    if (!selectedProgramme) {
        item.programme_code_id = null;
        item.programme_code = null;
        item.programme_name = null;
        item.budget_code = null;
        delete selectedProgrammeCodeMap.value[item.id];
        return;
    }
    
    const programme = programmeCodeOptions.value.find((pc: any) => pc.id === selectedProgramme);
    
    if (programme) {
        item.programme_code_id = programme.id;
        item.programme_code = programme.code;
        item.programme_name = programme.name;
        item.budget_code = programme.budget_code;
        
        if (programme.economic_code_id && !item.economy_code_id) {
            item.economy_code_id = programme.economic_code_id;
            onEconomyCodeChange(item);
        }
        
        selectedProgrammeCodeMap.value[item.id] = programme;
        
        if (programme.remaining_budget < item.sub_total) {
            toast.add({
                severity: 'warn',
                summary: 'Budget Alert',
                detail: `Insufficient budget for programme ${programme.code}. Available: ₦${programme.remaining_budget.toLocaleString()}`,
                life: 5000,
            });
        }
        
        if (item.errors?.programme_code_id) {
            delete item.errors.programme_code_id;
        }
        if (item.errors?.budget_code) {
            delete item.errors.budget_code;
        }
    }
};

// Fetch bank activities
const fetchBankActivities = async (search = '') => {
    bankActivitiesLoading.value = true;
    try {
        const response = await axios.get('/bankActivityList', {
            params: {
                filter: search,
                page: 1
            }
        });
        
        bankActivities.value = response.data.data.map((item: any) => ({
            label: `${item.tag} - ${item.bank_name} - ${item.title} - ${item.account_number}`,
            value: item.id,
            bank_name: item.bank_name,
            account_number: item.account_number,
            tag: item.tag,
        }));
    } catch (error) {
        console.error('Error fetching bank activities:', error);
    } finally {
        bankActivitiesLoading.value = false;
    }
};

// Handle bank activity search
const onBankActivitySearch = (event: any) => {
    const query = event.query;
    if (bankActivitiesSearchDebounce.value) {
        clearTimeout(bankActivitiesSearchDebounce.value);
    }
    bankActivitiesSearchDebounce.value = setTimeout(() => {
        fetchBankActivities(query);
    }, 300);
};

// Filter Economic Code items
const getEconomyCodeItemOptions = (economyCodeId: number | null) => {
    if (!economyCodeId || !props.economyCodeItems || props.economyCodeItems.length === 0) {
        return [];
    }
    return props.economyCodeItems.filter((item: any) => {
        return item.economy_code_id === economyCodeId;
    });
};

// Inertia form setup
const form = useForm({
    schedule_id: props.schedule?.id || null,
    voucher_type: props.voucherType,
    year_id: props.schedule?.year_id || null,
    mda_id: props.schedule?.mda_id || null,
    voucher_date: moment(props.today).format('YYYY-MM-DD'),
    narration: props.schedule?.narration || '',
    status: 'Approved',
    total_amount: props.schedule?.total_amount || 0,
    items: [] as LineItem[],
    documents: [] as File[],
    voucher_number: props.voucherNumber || '',
    payee_name: props.schedule?.payee_name || '',
    bank_activity_id: null,
    is_final_accounts: true,
});

// Auto-select MDA and Financial Year based on schedule
const autoSelectMdaAndYear = () => {
    if (props.schedule?.mda_id && props.mdas.length > 0) {
        const mdaExists = props.mdas.some(
            (mda: any) => mda.value === props.schedule.mda_id,
        );
        if (mdaExists) {
            form.mda_id = props.schedule.mda_id;
        }
    }

    if (props.schedule?.year_id && props.financialYears.length > 0) {
        const yearExists = props.financialYears.some(
            (year: any) => year.value === props.schedule.year_id,
        );
        if (yearExists) {
            form.year_id = props.schedule.year_id;
        }
    }
};

// Generate narration from schedule
const generateNarrationFromSchedule = () => {
    if (props.schedule && !form.narration) {
        const mdaName = props.schedule.mda?.name || 'MDA';
        const scheduleNumber = props.schedule.schedule_number || '';
        form.narration = `Final Accounts payment voucher for ${mdaName} - Schedule ${scheduleNumber}`;
    }
};

// Computed properties for dynamic totals
const voucherSubtotal = computed(() => {
    return form.items.reduce((sum, item) => sum + (item.sub_total || 0), 0);
});

const voucherTotal = computed(() => {
    return voucherSubtotal.value;
});

// Number to words converter
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
    let koboAmount = Math.round((amount - nairaAmount) * 100);

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

    words = words.trim();
    words += words ? 'Naira' : 'Zero Naira';

    if (koboAmount > 0) {
        words += ' and ';
        if (koboAmount >= 20) {
            words += tens[Math.floor(koboAmount / 10)] + ' ';
            koboAmount %= 10;
        } else if (koboAmount >= 10) {
            words += teens[koboAmount - 10] + ' ';
            koboAmount = 0;
        }
        if (koboAmount > 0) {
            words += units[koboAmount] + ' ';
        }
        words += 'Kobo';
    }

    return words.trim() + ' Only';
};

const amountInWords = computed(() => {
    return convertNumberToWords(voucherTotal.value);
});

const scheduleInfo = computed(() => {
    if (!props.schedule) return null;
    return {
        schedule_number: props.schedule.schedule_number,
        mda: props.schedule.mda?.name,
        budget_code: props.schedule.budget_code,
        total_amount: props.schedule.total_amount,
    };
});

// Format currency
const formatCurrency = (value: number) => {
    if (isNaN(value) || value === null || value === undefined) {
        return '₦0.00';
    }
    return new Intl.NumberFormat('en-NG', {
        style: 'currency',
        currency: 'NGN',
    }).format(value);
};

const clearFieldError = (item: LineItem, field: string) => {
    if (item.errors && item.errors[field]) {
        delete item.errors[field];
    }
};

// Validation functions
const validateHeader = () => {
    let isValid = true;
    validationErrors.value = {
        year_id: '',
        mda_id: '',
        voucher_date: '',
        narration: '',
        line_items: '',
        documents: '',
        voucher_number: '',
        payee_name: '',
        bank_activity_id: '',
    };

    if (!form.year_id) {
        validationErrors.value.year_id = 'Financial Year selection is required';
        isValid = false;
    }
    if (!form.mda_id) {
        validationErrors.value.mda_id = 'MDA selection is required';
        isValid = false;
    }
    if (!form.voucher_date) {
        validationErrors.value.voucher_date = 'Voucher date is required';
        isValid = false;
    } else {
        const selectedDate = new Date(form.voucher_date);
        const today = new Date();
        if (selectedDate > today) {
            validationErrors.value.voucher_date = 'Voucher date cannot be in the future';
            isValid = false;
        }
    }
    if (!form.narration.trim()) {
        validationErrors.value.narration = 'Narration is required';
        isValid = false;
    } else if (form.narration.length < 10) {
        validationErrors.value.narration = 'Narration must be at least 10 characters';
        isValid = false;
    } else if (form.narration.length > 500) {
        validationErrors.value.narration = 'Narration cannot exceed 500 characters';
        isValid = false;
    }
    if (!form.voucher_number || form.voucher_number.length < 5) {
        validationErrors.value.voucher_number = 'Voucher number is required';
        isValid = false;
    }
    if (!form.payee_name || !form.payee_name.trim()) {
        validationErrors.value.payee_name = 'Payee name is required';
        isValid = false;
    }
    // // Bank activity is optional for final accounts
    // if (!form.bank_activity_id) {
    //     // No error, just optional
    //     console.log('Bank activity is optional for final accounts');
    // }
    
    // Bank activity is now REQUIRED for final accounts
    if (!form.bank_activity_id || form.bank_activity_id === null) {
        validationErrors.value.bank_activity_id = 'Destination bank selection is required';
        isValid = false;
    }

    return isValid;
};

const validateLineItems = () => {
    let isValid = true;

    form.items.forEach((item) => {
        if (item.errors) {
            item.errors = {};
        }
    });

    if (form.items.length === 0) {
        validationErrors.value.line_items = 'At least one line item is required';
        return false;
    }

    form.items.forEach((item) => {
        const itemErrors: any = {};

        if (!item.description?.trim()) {
            itemErrors.description = 'Description is required';
            isValid = false;
        } else if (item.description.length < 3) {
            itemErrors.description = 'Description must be at least 3 characters';
            isValid = false;
        } else if (item.description.length > 255) {
            itemErrors.description = 'Description cannot exceed 255 characters';
            isValid = false;
        }

        if (!item.economy_code_id) {
            itemErrors.economy_code_id = 'Economic Code is required';
            isValid = false;
        }

        if (!item.economy_code_item_id) {
            itemErrors.economy_code_item_id = 'Economic Code item is required';
            isValid = false;
        }

        const selectedEconomyCode = props.economyCodes.find((ec: any) => ec.value === item.economy_code_id);
        const isSeries32 = selectedEconomyCode && (selectedEconomyCode.code?.startsWith('32') || selectedEconomyCode.type === 'capital');
        
        if (isSeries32 && !item.programme_code_id) {
            itemErrors.programme_code_id = 'Programme Code is required for series 32 (Capital Expenditure)';
            isValid = false;
        }

        if (!item.budget_code && item.programme_code_id) {
            const programme = selectedProgrammeCodeMap.value[item.id];
            if (programme && programme.budget_code) {
                item.budget_code = programme.budget_code;
            }
        }

        if (!item.quantity || item.quantity <= 0) {
            itemErrors.quantity = 'Quantity must be greater than 0';
            isValid = false;
        }

        if (item.unit_price === null || item.unit_price === undefined || item.unit_price < 0) {
            itemErrors.unit_price = 'Unit price is required and cannot be negative';
            isValid = false;
        }

        if (item.sub_total === null || item.sub_total === undefined || item.sub_total < 0) {
            itemErrors.sub_total = 'Sub total is required and cannot be negative';
            isValid = false;
        }

        const calculatedSubTotal = item.quantity * item.unit_price;
        const subTotalDifference = Math.abs(item.sub_total - calculatedSubTotal);
        if (subTotalDifference > 0.01) {
            itemErrors.sub_total = 'Sub total does not match quantity × unit price';
            isValid = false;
        }

        if (Object.keys(itemErrors).length > 0) {
            item.errors = itemErrors;
        }
    });

    if (voucherTotal.value <= 0) {
        validationErrors.value.line_items = 'Total voucher amount must be greater than 0';
        isValid = false;
    } else {
        validationErrors.value.line_items = '';
    }

    return isValid;
};

// Update line item sub_total
const updateItemSubTotal = (item: LineItem, field: 'quantity' | 'unit_price' | 'sub_total') => {
    const quantity = parseFloat(item.quantity?.toString() || '0');
    const unit_price = parseFloat(item.unit_price?.toString() || '0');
    let sub_total = parseFloat(item.sub_total?.toString() || '0');

    if (field === 'quantity' || field === 'unit_price') {
        sub_total = quantity * unit_price;
        item.sub_total = parseFloat(sub_total.toFixed(2));
    } else if (field === 'sub_total') {
        if (quantity > 0) {
            item.unit_price = parseFloat((sub_total / quantity).toFixed(2));
        } else {
            item.unit_price = sub_total;
        }
    }

    form.total_amount = voucherTotal.value;

    if (item.programme_code_id && selectedProgrammeCodeMap.value[item.id]) {
        const programme = selectedProgrammeCodeMap.value[item.id];
        if (programme.remaining_budget < item.sub_total) {
            toast.add({
                severity: 'warn',
                summary: 'Budget Alert',
                detail: `Insufficient budget for programme ${programme.code}. Available: ₦${programme.remaining_budget.toLocaleString()}`,
                life: 5000,
            });
        }
    }

    if (item.errors) {
        delete item.errors[field];
        if (field === 'quantity' || field === 'unit_price') {
            delete item.errors.sub_total;
        }
    }
};

// Add new line item
const addItem = () => {
    const newItem: LineItem = {
        id: nextItemId++,
        description: '',
        economy_code_id: null,
        economy_code_item_id: null,
        quantity: 1,
        unit_price: 0,
        sub_total: 0,
        programme_code_id: null,
        programme_code: null,
        programme_name: null,
        budget_code: null,
    };
    form.items.push(newItem);
    validationErrors.value.line_items = '';
};

const deleteItem = (id: number) => {
    if (form.items.length > 1) {
        form.items = form.items.filter((item) => item.id !== id);
        form.total_amount = voucherTotal.value;
        delete selectedProgrammeCodeMap.value[id];
    }
};

const handleQuantityChange = (item: LineItem, newValue: number) => {
    item.quantity = newValue || 0;
    updateItemSubTotal(item, 'quantity');
};

const handleUnitPriceChange = (item: LineItem, newValue: number) => {
    item.unit_price = newValue || 0;
    updateItemSubTotal(item, 'unit_price');
};

const handleSubTotalChange = (item: LineItem, newValue: number) => {
    item.sub_total = newValue || 0;
    updateItemSubTotal(item, 'sub_total');
};

const onEconomyCodeChange = (item: LineItem) => {
    item.economy_code_item_id = null;
};

// File upload handlers
const isFileAlreadyUploaded = (file: File) => {
    const inOptional = optionalDocuments.value.some(
        (doc) => doc.file.name === file.name && doc.file.size === file.size,
    );
    const inForm = form.documents.some((f) => f.name === file.name && f.size === file.size);
    return inOptional || inForm;
};

const onSelect = (event: any) => {
    const newFiles = [...event.files];
    validationErrors.value.documents = '';

    const uniqueNewFiles = newFiles.filter((file) => !isFileAlreadyUploaded(file));

    if (uniqueNewFiles.length !== newFiles.length) {
        toast.add({
            severity: 'warn',
            summary: 'Duplicate Files',
            detail: 'Some files were already uploaded and were skipped.',
            life: 3000,
        });
    }

    if (uniqueNewFiles.length === 0) {
        if (fileUploadRef.value) {
            fileUploadRef.value.clear();
        }
        return;
    }

    if (fileUploadRef.value) {
        fileUploadRef.value.clear();
    }

    uniqueNewFiles.forEach((file) => {
        optionalDocuments.value.push({
            type: 'Other',
            label: 'Supporting Document',
            file: file,
            document_type: 'Other',
        });

        if (!form.documents.includes(file)) {
            form.documents.push(file);
        }
    });
};

const onRemove = (event: any) => {
    const fileToRemove = event.file;

    const optionalDocIndex = optionalDocuments.value.findIndex(
        (doc) => doc.file.name === fileToRemove.name,
    );
    if (optionalDocIndex > -1) {
        optionalDocuments.value.splice(optionalDocIndex, 1);
    }

    const formDocIndex = form.documents.findIndex((file) => file.name === fileToRemove.name);
    if (formDocIndex > -1) {
        form.documents.splice(formDocIndex, 1);
    }

    if (form.documents.length > 0) {
        validationErrors.value.documents = '';
    }
};

const clearAllDocuments = () => {
    optionalDocuments.value = [];
    form.documents = [];
    if (fileUploadRef.value) {
        fileUploadRef.value.clear();
    }
    validationErrors.value.documents = '';
    selectedDocumentType.value = '';
};

const onUpload = (event: any) => {
    console.log('Upload event triggered:', event);
};

const submitVoucher = () => {
    if (!validateForm()) {
        toast.add({
            severity: 'error',
            summary: 'Validation Error',
            detail: 'Please fix all validation errors before submitting.',
            life: 5000,
        });
        return;
    }

    const documentTypesData: { type: string; label: string; file_name: string; }[] = [];

    optionalDocuments.value.forEach((doc) => {
        documentTypesData.push({
            type: doc.document_type,
            label: doc.label,
            file_name: doc.file.name,
        });
    });

    form.voucher_date = moment(form.voucher_date).format('YYYY-MM-DD');

    form.items.forEach(item => {
        if (item.programme_code_id && !item.budget_code) {
            const programme = selectedProgrammeCodeMap.value[item.id];
            if (programme && programme.budget_code) {
                item.budget_code = programme.budget_code;
            }
        }
        if (!item.budget_code) {
            item.budget_code = '';
        }
    });

    const submitData = {
        ...form.data(),
        items: form.items.map((item) => ({
            ...item,
            amount: item.sub_total,
        })),
        document_types: documentTypesData,
        is_final_accounts: true,
    };

    console.log('Submitting Final Accounts Voucher:', submitData);

    form.post('/final-accounts/vouchers', {
        data: submitData,
        preserveScroll: true,
        onSuccess: (response) => {
            toast.add({
                severity: 'success',
                summary: 'Success',
                detail: 'Final Accounts voucher created and approved successfully!',
                life: 5000,
            });
        },
        onError: (errors) => {
            console.log('Form submission failed with errors:', errors);
            toast.add({
                severity: 'error',
                summary: 'Error',
                detail: 'Please check the form for errors.',
                life: 5000,
            });
        },
    });
};

const saveVoucher = () => {
    if (!validateHeader() || !validateLineItems()) {
        toast.add({
            severity: 'error',
            summary: 'Validation Error',
            detail: 'Please fill in all required fields.',
            life: 5000,
        });
        return;
    }
    submitVoucher();
};

const validateForm = () => {
    const isHeaderValid = validateHeader();
    const areLineItemsValid = validateLineItems();
    return isHeaderValid && areLineItemsValid;
};

// Payee lazy loading
const lazyItems: any = ref([]);
const loading = ref(false);
const currentPage = ref(0);
const filterValue = ref('');

const fetchPayees = async (page: number, filter = '') => {
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

const onFilterPayee = (event: any) => {
    filterValue.value = event.value;
    fetchPayees(1, event.value);
};

// Initialize
onMounted(() => {
    console.log('Final Accounts Create Mounted');
    console.log('Programme Codes from props:', props.programmeCodes?.length || 0);
    
    autoSelectMdaAndYear();
    generateNarrationFromSchedule();
    
    if (form.items.length === 0) {
        addItem();
    }
    
    // Initialize programme codes from props or API
    if (props.programmeCodes && props.programmeCodes.length > 0) {
        programmeCodeOptions.value = props.programmeCodes;
    }
    
    // Fetch initial data
    fetchPayees(1);
    fetchBankActivities('');
    searchProgrammeCodes('');
});
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head :title="pageTitle" />
        <Toast />

        <Card class="voucher-card">
            <template #title>
                <div class="flex justify-content-between align-items-center">
                    <div>
                        {{ pageTitle }}
                        <span class="text-500 ml-3 text-sm">{{ defaultAccount }}</span>
                        <Badge value="Final Accounts - Direct Approval" severity="success" class="ml-3" />
                    </div>
                    <div class="flex gap-2">
                        <Button 
                            label="Standard" 
                            :severity="form.voucher_type === 'standard' ? 'primary' : 'secondary'"
                            :outlined="form.voucher_type !== 'standard'"
                            @click="form.voucher_type = 'standard'"
                        />
                        <Button 
                            label="Prepayment" 
                            :severity="form.voucher_type === 'prepayment' ? 'primary' : 'secondary'"
                            :outlined="form.voucher_type !== 'prepayment'"
                            @click="form.voucher_type = 'prepayment'"
                        />
                        <Button 
                            label="Salary" 
                            :severity="form.voucher_type === 'salary' ? 'primary' : 'secondary'"
                            :outlined="form.voucher_type !== 'salary'"
                            @click="form.voucher_type = 'salary'"
                        />
                    </div>
                </div>
            </template>

            <template #content>
                <!-- Final Accounts Information Banner -->
                <div class="mb-4">
                    <Message severity="success" :closable="false">
                        <div class="align-items-center flex gap-2">
                            <i class="pi pi-check-circle"></i>
                            <span>
                                <strong>Final Accounts Mode:</strong> Vouchers created here will be 
                                <strong>automatically approved</strong> without going through the normal 
                                approval workflow. This is for final accounts adjustments only.
                            </span>
                        </div>
                    </Message>
                </div>

                <!-- Schedule Information Banner (Optional) -->
                <div v-if="scheduleInfo" class="mb-4">
                    <Message severity="info" :closable="false">
                        <div class="align-items-center flex gap-3">
                            <i class="pi pi-info-circle"></i>
                            <div class="flex-column flex">
                                <span class="font-semibold">Creating voucher from schedule:</span>
                                <div class="align-items-center mt-1 flex gap-4">
                                    <span><strong>Schedule No:</strong>
                                        {{ scheduleInfo.schedule_number }}</span>
                                    <span><strong>MDA:</strong>
                                        {{ scheduleInfo.mda }}</span>
                                    <span><strong>Administrative Code:</strong>
                                        {{ scheduleInfo.budget_code }}</span>
                                    <span><strong>Schedule Total:</strong>
                                        {{ formatCurrency(scheduleInfo.total_amount) }}</span>
                                </div>
                            </div>
                        </div>
                    </Message>
                </div>

                <!-- Error Messages -->
                <div v-if="Object.keys(form.errors).length > 0" class="mb-4">
                    <Message severity="error" :closable="false">
                        <div class="flex-column flex">
                            <div v-for="(error, field) in form.errors" :key="field"
                                class="align-items-center flex gap-2">
                                <i class="pi pi-exclamation-circle"></i>
                                <span>{{ error }}</span>
                            </div>
                        </div>
                    </Message>
                </div>

                <!-- Basic Voucher Information -->
                <div class="mb-4 grid">
                    <div class="col-6">
                        <div class="field">
                            <label for="year_id" class="text-500 mb-1 block text-sm font-semibold">
                                Financial Year *
                            </label>
                            <Dropdown id="year_id" v-model="form.year_id" :options="financialYears" optionLabel="label"
                                optionValue="value" placeholder="Select Financial Year" class="w-full" :class="{
                                    'p-invalid':
                                        form.errors.year_id ||
                                        validationErrors.year_id,
                                }" @change="validationErrors.year_id = ''" />
                            <small v-if="validationErrors.year_id" class="p-error">
                                {{ validationErrors.year_id }}
                            </small>
                            <small v-if="form.errors.year_id" class="p-error">
                                {{ form.errors.year_id }}
                            </small>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="field">
                            <label for="mda_id" class="text-500 mb-1 block text-sm font-semibold">
                                MDA *
                            </label>
                            <Dropdown id="mda_id" v-model="form.mda_id" :options="mdas" optionLabel="label"
                                optionValue="value" placeholder="Select MDA" class="w-full" :class="{
                                    'p-invalid':
                                        form.errors.mda_id ||
                                        validationErrors.mda_id,
                                }" :filter="true" filterPlaceholder="Search MDA..."
                                @change="validationErrors.mda_id = ''" />
                            <small v-if="validationErrors.mda_id" class="p-error">
                                {{ validationErrors.mda_id }}
                            </small>
                            <small v-if="form.errors.mda_id" class="p-error">
                                {{ form.errors.mda_id }}
                            </small>
                        </div>
                    </div>
                </div>

                <div class="mb-4 grid">
                    <div class="col-4">
                        <div class="field">
                            <label for="voucher_date" class="text-500 mb-1 block text-sm font-semibold">
                                Voucher Date *
                            </label>
                            <Calendar id="voucher_date" v-model="form.voucher_date" dateFormat="yy-mm-dd"
                                class="w-full" :class="{
                                    'p-invalid':
                                        form.errors.voucher_date ||
                                        validationErrors.voucher_date,
                                }" @date-select="
                                    validationErrors.voucher_date = ''
                                    " />
                            <small v-if="validationErrors.voucher_date" class="p-error">
                                {{ validationErrors.voucher_date }}
                            </small>
                            <small v-if="form.errors.voucher_date" class="p-error">
                                {{ form.errors.voucher_date }}
                            </small>
                        </div>
                    </div>
                    <div class="col-4">
                        <div class="field">
                            <label class="text-500 mb-1 block text-sm font-semibold">
                                Voucher Number
                            </label>
                            <InputText v-model="form.voucher_number" class="w-full uppercase-input" :class="{
                                'p-invalid':
                                    form.errors.voucher_number ||
                                    validationErrors.voucher_number,
                            }" @input="validationErrors.voucher_number = ''" />
                            <div class="justify-content-between mt-1 flex">
                                <small v-if="validationErrors.voucher_number" class="p-error">
                                    {{ validationErrors.voucher_number }}
                                </small>
                                <small v-if="form.errors.voucher_number" class="p-error">
                                    {{ form.errors.voucher_number }}
                                </small>
                                <small class="text-500">Voucher Number: {{ voucherNumber }}</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-4">
                        <div class="field">
                            <label class="text-500 mb-1 block text-sm font-semibold">
                                Voucher Type
                            </label>
                            <Dropdown
                                v-model="form.voucher_type"
                                :options="voucherTypes"
                                optionLabel="label"
                                optionValue="value"
                                placeholder="Select Voucher Type"
                                class="w-full"
                                :class="{
                                    'p-invalid':
                                        form.errors.voucher_type ||
                                        validationErrors.voucher_type,
                                }"
                                @change="validationErrors.voucher_type = ''"
                            />
                            <small class="text-500">Type: {{ form.voucher_type }}</small>
                        </div>
                    </div>
                </div>

                <!-- Narration and Payee -->
                <div class="mb-4 grid">
                    <div class="col-6">
                        <div class="field">
                            <label for="narration" class="text-500 mb-1 block text-sm font-semibold">
                                Narration *
                            </label>
                            <Textarea id="narration" v-model="form.narration" rows="3" class="w-full"
                                placeholder="Enter voucher description or purpose..." :class="{
                                    'p-invalid':
                                        form.errors.narration ||
                                        validationErrors.narration,
                                }" @input="validationErrors.narration = ''" />
                            <div class="justify-content-between mt-1 flex">
                                <small v-if="validationErrors.narration" class="p-error">
                                    {{ validationErrors.narration }}
                                </small>
                                <small v-if="form.errors.narration" class="p-error">
                                    {{ form.errors.narration }}
                                </small>
                                <small :class="form.narration.length > 500
                                    ? 'p-error'
                                    : 'text-500'
                                    ">
                                    {{ form.narration.length }}/500
                                </small>
                            </div>
                        </div>
                    </div>

                    <div class="col-6">
                        <div class="field">
                            <label for="payee_name" class="text-500 mb-1 block text-sm font-semibold">
                                Payee Name/Beneficiary Name *
                            </label>
                            <Dropdown editable v-model="form.payee_name" :options="lazyItems" optionLabel="label"
                                optionValue="value" :loading="loading" placeholder="Select who is being paid" filter
                                @filter="onFilterPayee" class="w-full" />
                            <small class="p-error block" v-if="form.errors?.payee_name">{{
                                form.errors.payee_name
                                }}</small>
                        </div>
                    </div>
                </div>

                <!-- Line Items Table -->
                <div class="mb-4">
                    <div class="justify-content-between align-items-center mb-3 flex">
                        <h4 class="m-0">Line Items</h4>
                        <div class="align-items-center flex gap-3">
                            <span class="text-500 text-sm" v-if="validationErrors.line_items">
                                {{ validationErrors.line_items }}
                            </span>
                            <Button label="Add Line Item" icon="pi pi-plus" severity="success" outlined
                                @click="addItem()" />
                        </div>
                    </div>

                    <DataTable :value="form.items" class="p-datatable-sm" responsiveLayout="scroll" style="min-width: 1200px">
                        <Column field="description" header="Description" headerStyle="min-width: 250px" bodyStyle="min-width: 250px">
                            <template #body="slotProps">
                                <div class="flex-column flex">
                                    <Textarea v-model="slotProps.data.description" rows="2" autoResize
                                        placeholder="Enter item description..." class="w-full"
                                        :class="{ 'p-invalid': slotProps.data.errors?.description }"
                                        @input="clearFieldError(slotProps.data, 'description')" />
                                    <small v-if="slotProps.data.errors?.description" class="p-error mt-1">
                                        {{ slotProps.data.errors.description }}
                                    </small>
                                </div>
                            </template>
                        </Column>

                        <!-- Economic Code Column -->
                        <Column field="economy_code_id" header="Economic Code" headerStyle="min-width: 180px" bodyStyle="min-width: 180px">
                            <template #body="slotProps">
                                <div class="flex-column flex">
                                    <Dropdown v-model="slotProps.data.economy_code_id" :options="economyCodeOptions"
                                        optionLabel="label" optionValue="value" placeholder="Select Code"
                                        class="w-full" :filter="true"
                                        filterPlaceholder="Search Economic Codes..." :showClear="true"
                                        :class="{ 'p-invalid': slotProps.data.errors?.economy_code_id }"
                                        @change="onEconomyCodeChange(slotProps.data)" />
                                    <small v-if="slotProps.data.errors?.economy_code_id" class="p-error mt-1">
                                        {{ slotProps.data.errors.economy_code_id }}
                                    </small>
                                </div>
                            </template>
                        </Column>

                        <!-- Economic Code Item Column -->
                        <Column field="economy_code_item_id" header="Code Item" headerStyle="min-width: 180px" bodyStyle="min-width: 180px">
                            <template #body="slotProps">
                                <div class="flex-column flex">
                                    <Dropdown v-model="slotProps.data.economy_code_item_id"
                                        :options="getEconomyCodeItemOptions(slotProps.data.economy_code_id)"
                                        optionLabel="label" optionValue="value" placeholder="Select Item"
                                        class="w-full" :disabled="!slotProps.data.economy_code_id"
                                        :filter="true" filterPlaceholder="Search code items..." :showClear="true"
                                        :class="{ 'p-invalid': slotProps.data.errors?.economy_code_item_id }" />
                                    <small v-if="slotProps.data.errors?.economy_code_item_id" class="p-error mt-1">
                                        {{ slotProps.data.errors.economy_code_item_id }}
                                    </small>
                                    <small v-else-if="!slotProps.data.economy_code_id" class="text-500 mt-1">
                                        Select Code first
                                    </small>
                                </div>
                            </template>
                        </Column>

                        <!-- Programme Code Column -->
                        <Column field="programme_code_id" header="Programme Code" headerStyle="min-width: 250px" bodyStyle="min-width: 250px">
                            <template #body="slotProps">
                                <div class="flex-column flex">
                                    <Dropdown 
                                        v-model="slotProps.data.programme_code_id" 
                                        :options="programmeCodeOptions" 
                                        optionLabel="label" 
                                        optionValue="value" 
                                        placeholder="Search Programme Code..." 
                                        class="w-full" 
                                        :loading="programmeCodeSearchLoading"
                                        :filter="true"
                                        filterPlaceholder="Type to search by code or name..."
                                        :showClear="true"
                                        :class="{ 'p-invalid': slotProps.data.errors?.programme_code_id }"
                                        @filter="onProgrammeCodeSearch" 
                                        @change="onProgrammeCodeSelect(slotProps.data, $event.value)"
                                    >
                                        <template #option="optionProps">
                                            <div class="flex flex-column">
                                                <span class="font-medium">{{ optionProps.option.display_text || optionProps.option.label }}</span>
                                                <small class="text-500">{{ optionProps.option.detail_text || `Budget Code: ${optionProps.option.budget_code} | Remaining: ₦${Number(optionProps.option.remaining_budget).toLocaleString()}` }}</small>
                                            </div>
                                        </template>
                                    </Dropdown>
                                    <small v-if="slotProps.data.errors?.programme_code_id" class="p-error mt-1">
                                        {{ slotProps.data.errors.programme_code_id }}
                                    </small>
                                    <small v-else-if="slotProps.data.programme_code_id && selectedProgrammeCodeMap[slotProps.data.id]" class="text-500 mt-1">
                                        <i class="pi pi-info-circle mr-1"></i>
                                        Remaining: ₦{{ Number(selectedProgrammeCodeMap[slotProps.data.id].remaining_budget).toLocaleString() }}
                                    </small>
                                </div>
                            </template>
                        </Column>

                        <Column field="quantity" header="Qty" headerStyle="min-width: 100px" bodyStyle="min-width: 100px">
                            <template #body="slotProps">
                                <div class="flex-column flex">
                                    <InputNumber :modelValue="slotProps.data.quantity"
                                        @update:modelValue="handleQuantityChange(slotProps.data, $event)"
                                        :min="1" :max-fraction-digits="2" inputClass="w-full text-center"
                                        :class="{ 'p-invalid': slotProps.data.errors?.quantity }" />
                                    <small v-if="slotProps.data.errors?.quantity" class="p-error mt-1">
                                        {{ slotProps.data.errors.quantity }}
                                    </small>
                                </div>
                            </template>
                        </Column>

                        <Column field="unit_price" header="Unit Price" headerStyle="min-width: 150px" bodyStyle="min-width: 150px">
                            <template #body="slotProps">
                                <div class="flex-column flex">
                                    <InputNumber :modelValue="slotProps.data.unit_price"
                                        @update:modelValue="handleUnitPriceChange(slotProps.data, $event)"
                                        mode="currency" currency="NGN" locale="en-NG" :min="0"
                                        inputClass="w-full text-right"
                                        :class="{ 'p-invalid': slotProps.data.errors?.unit_price }" />
                                    <small v-if="slotProps.data.errors?.unit_price" class="p-error mt-1">
                                        {{ slotProps.data.errors.unit_price }}
                                    </small>
                                </div>
                            </template>
                        </Column>

                        <Column field="sub_total" header="Sub Total" headerStyle="min-width: 150px" bodyStyle="min-width: 150px"
                            bodyClass="font-bold text-right">
                            <template #body="slotProps">
                                <div class="flex-column flex">
                                    <InputNumber :modelValue="slotProps.data.sub_total"
                                        @update:modelValue="handleSubTotalChange(slotProps.data, $event)"
                                        mode="currency" currency="NGN" locale="en-NG" :min="0"
                                        inputClass="w-full text-right"
                                        :class="{ 'p-invalid': slotProps.data.errors?.sub_total }" readonly />
                                    <small v-if="slotProps.data.errors?.sub_total" class="p-error mt-1">
                                        {{ slotProps.data.errors.sub_total }}
                                    </small>
                                </div>
                            </template>
                        </Column>

                        <Column headerStyle="min-width: 80px" bodyStyle="min-width: 80px" bodyClass="text-center">
                            <template #body="slotProps">
                                <Button icon="pi pi-trash" severity="danger" text rounded
                                    :disabled="form.items.length === 1" @click="deleteItem(slotProps.data.id)" />
                            </template>
                        </Column>
                    </DataTable>
                </div>

                <!-- Amount in Words Section -->
                <div class="surface-50 border-round mb-4 p-3">
                    <div class="align-items-center mb-2 flex gap-2">
                        <i class="pi pi-info-circle text-primary"></i>
                        <span class="font-semibold">Amount in Words:</span>
                    </div>
                    <div class="surface-0 border-round p-2">
                        <span class="text-900">{{ amountInWords }}</span>
                    </div>
                </div>

                <!-- Documents and Bank Section -->
                <div class="mb-4 grid">
                    <div class="col-6">
                        <div class="field-group">
                            <div class="justify-content-between align-items-center mb-3 flex">
                                <h4 class="m-0">Supporting Documents (Optional)</h4>
                                <Button v-if="form.documents.length > 0" label="Clear All" icon="pi pi-times"
                                    severity="secondary" text @click="clearAllDocuments" />
                            </div>

                            <div class="mb-2" v-if="validationErrors.documents">
                                <Message severity="error" :closable="false">
                                    {{ validationErrors.documents }}
                                </Message>
                            </div>

                            <FileUpload ref="fileUploadRef" mode="advanced" name="documents" :multiple="true"
                                :maxFileSize="10000000" accept="image/*,.pdf,.doc,.docx,.xls,.xlsx"
                                chooseLabel="Attach Documents" uploadLabel="Upload" cancelLabel="Cancel"
                                @select="onSelect" @remove="onRemove" @upload="onUpload" :auto="false"
                                :customUpload="true" :disabled="form.processing">
                                <template #empty>
                                    <p class="text-500">
                                        Drag and drop files here or click to browse (Optional)
                                    </p>
                                    <small class="text-500">
                                        Supported formats: Images, PDF, Word, Excel (Max: 10MB per file)
                                    </small>
                                </template>
                            </FileUpload>

                            <!-- Uploaded Files Display -->
                            <div v-if="form.documents.length > 0" class="mt-3">
                                <h5 class="mb-2">Uploaded Files ({{ form.documents.length }}):</h5>
                                <div v-if="optionalDocuments.length > 0" class="mb-4">
                                    <div class="grid">
                                        <div v-for="(doc, index) in optionalDocuments" :key="index" class="col-12 mb-3">
                                            <div class="align-items-center justify-content-between surface-50 border-round flex p-3">
                                                <div class="align-items-center flex gap-3">
                                                    <i class="pi pi-file text-2xl"></i>
                                                    <div>
                                                        <div class="font-medium">{{ doc.file.name }}</div>
                                                        <small class="text-500">{{ (doc.file.size / 1024).toFixed(2) }} KB</small>
                                                    </div>
                                                </div>
                                                <Button icon="pi pi-times" severity="danger" text rounded
                                                    @click="onRemove({ file: doc.file })" />
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-6">
                        <div class="totals-section">
                            <h4 class="mb-3">Voucher Summary</h4>

                            <div class="justify-content-between total-row mb-2 flex">
                                <span class="text-500">Voucher Subtotal:</span>
                                <span class="font-semibold">{{ formatCurrency(voucherSubtotal) }}</span>
                            </div>
                            <Divider />
                            <div class="justify-content-between total-row flex text-xl font-bold">
                                <span>Voucher Total:</span>
                                <span>{{ formatCurrency(voucherTotal) }}</span>
                            </div>
                        </div>

                        <!-- Bank Selection (Optional for Final Accounts) -->
                        <div class="mt-4">
                            <label for="bank_activity_id" class="text-500 mb-1 block text-sm font-semibold">
                                Select Destination Bank (Optional)
                            </label>
                            <Dropdown 
                                id="bank_activity_id" 
                                v-model="form.bank_activity_id" 
                                :options="bankActivities"
                                optionLabel="label" 
                                optionValue="value" 
                                :loading="bankActivitiesLoading" 
                                placeholder="Select destination bank (Optional)" 
                                filter 
                                @filter="onBankActivitySearch" 
                                class="w-full"
                                :class="{ 'p-invalid': form.errors?.bank_activity_id || validationErrors.bank_activity_id }" 
                            />
                            <small class="text-500 mt-1 block">
                                <i class="pi pi-info-circle mr-1 text-500"></i>
                                Bank selection is optional for Final Accounts vouchers
                            </small>
                            <small v-if="validationErrors.bank_activity_id" class="p-error">
                                {{ validationErrors.bank_activity_id }}
                            </small>
                        </div>
                    </div>
                </div>

                <!-- Action Buttons -->
                <div class="justify-content-end mt-5 flex gap-2">
                    <Button label="Create & Approve Voucher" icon="pi pi-check-circle" severity="success"
                        :loading="form.processing" @click="saveVoucher"
                        title="Create voucher with immediate approval (No workflow)" />
                </div>
            </template>
        </Card>
    </AppLayout>
</template>

<style scoped>
.uppercase-input {
    text-transform: uppercase;
}

.voucher-card {
    min-height: 100vh;
}

.field-group h4 {
    margin: 0 0 0.5rem 0;
    color: var(--p-text-color);
    font-size: 1rem;
}

.totals-section {
    background: var(--p-surface-50);
    padding: 1rem;
    border-radius: 6px;
    border: 1px solid var(--p-surface-200);
}

.total-row {
    padding: 0.25rem 0;
}

:deep(.p-datatable) {
    border: 1px solid var(--p-surface-200);
    border-radius: 6px;
    width: 100%;
    overflow-x: auto;
}

:deep(.p-datatable-thead > tr > th) {
    background: var(--p-surface-100);
    font-weight: 600;
}

:deep(.p-invalid) {
    border-color: var(--p-error-color) !important;
}

.p-error {
    color: var(--p-error-color);
    font-size: 0.875rem;
}

.text-green-500 {
    color: #22c55e;
}

.text-red-500 {
    color: #ef4444;
}

.text-orange-500 {
    color: #f97316;
}

.text-green-600 {
    color: #16a34a;
}

.text-blue-600 {
    color: #2563eb;
}

.w-10rem {
    width: 10rem;
}
</style>