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
    { title: 'Vouchers', href: '/vouchers' },
    { title: 'Create Voucher', href: '#' },
];

let nextItemId = 1;

const voucherTypes = [
    // {
    //     label: 'Standard',
    //     value: 'standard',
    // },
    {
        label: 'Captial',
        value: 'capital',
    },
    {
        label: 'Recurrent',
        value: 'recurrent',
    },
    {
        label: 'Prepayment',
        value: 'prepayment',
    },
    {
        label: 'Salary',
        value: 'salary',
    },
    {
        label: 'Gratuity',
        value: 'gratuity',
    },
    {
        label: 'Pension',
        value: 'pension',
    },
];

// Define types for line items - UPDATED with programme code fields
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
interface RequiredDocument {
    type: string;
    label: string;
    required: boolean;
    uploaded: boolean;
    file?: File;
}

interface UploadedDocument {
    id?: number;
    type: string;
    label: string;
    file: File;
    document_type: string;
}

// Programme code interface
interface ProgrammeCode {
    id: number;
    code: string;
    name: string;
    budget_code: string;
    remaining_budget: number;
    economic_code_id: number;
    label?: string;
    value?: number;
}

// Props from Laravel controller
const props = defineProps({
    voucherType: {
        type: String,
        required: true,
        default: 'capital',
    },
    schedule: {
        type: Object,
        default: () => ({}),
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

// Page title based on voucher type and schedule
const pageTitle = computed(() => {
    const type =
        props.voucherType.charAt(0).toUpperCase() + props.voucherType.slice(1);
    if (props.schedule?.schedule_number) {
        return `Create ${type} Voucher for Schedule ${props.schedule.schedule_number}`;
    }
    return `Create New ${type} Voucher`;
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
});

// Document type options for dropdown
const documentTypeOptions = [
    { label: 'Approval Memo', value: 'approval_memo' },
    { label: 'Release Warrant', value: 'release_warrant' },
    { label: 'Exco Approval/Conclusion', value: 'exco_approval' },
    { label: 'Ministerial Tender Board', value: 'ministerial_tender_board' },
    { label: 'State Tenders Board', value: 'state_tender_board' },
    { label: 'Certificate Of Incorporation', value: 'certificate_of_incorporation' },
    { label: 'Tax Clearance', value: 'tax_clearance' },
    { label: 'Tax Identification Number (TIN)', value: 'tin' },
    { label: 'Procurement Registration', value: 'procurement_registration' },
    { label: 'Advance Payment Guarantee (APG)', value: 'advance_payment_guarantee' },
    { label: 'Receipt', value: 'receipt' },
    { label: 'Delivery Note', value: 'delivery_note' },
    { label: 'Other Document', value: 'other' },
];

// Document management
const requiredDocuments = ref<RequiredDocument[]>([
    {
        type: 'approval_memo',
        label: 'Approval Memo',
        required: true,
        uploaded: false,
    },
]);

const optionalDocuments = ref<UploadedDocument[]>([]);
const selectedDocumentType = ref<string>('');
const fileUploadRef = ref();

// Economic Code Options
const economyCodeOptions = computed(() => {
    return props.economyCodes;
});

// Programme Codes Management - UPDATED with search functionality
const programmeCodes = ref<ProgrammeCode[]>([]);
const programmeCodeLoading = ref(false);
const selectedProgrammeCodeMap = ref<Record<number, ProgrammeCode>>({});

// NEW: Searchable programme code refs
const programmeCodeSearchQuery = ref('');
const programmeCodeOptions = ref([]);
const programmeCodeSearchLoading = ref(false);
const programmeCodeSearchDebounce = ref(null);

// NEW: Fetch programme codes from API (searchable)
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
        toast.add({
            severity: 'error',
            summary: 'Error',
            detail: 'Failed to search programme codes',
            life: 3000,
        });
    } finally {
        programmeCodeSearchLoading.value = false;
    }
};

// NEW: Handle programme code search input
const onProgrammeCodeSearch = (event: any) => {
    const query = event.query;
    
    // Clear previous debounce
    if (programmeCodeSearchDebounce.value) {
        clearTimeout(programmeCodeSearchDebounce.value);
    }
    
    // Debounce search
    programmeCodeSearchDebounce.value = setTimeout(() => {
        searchProgrammeCodes(query);
    }, 300);
};

// NEW: Handle programme code selection (updated version)
const onProgrammeCodeSelect = (item: LineItem, selectedProgramme: number | null) => {
    if (!selectedProgramme) {
        item.programme_code_id = null;
        item.programme_code = null;
        item.programme_name = null;
        item.budget_code = null;
        delete selectedProgrammeCodeMap.value[item.id];
        return;
    }
    
    // Find the selected programme
    const programme = programmeCodeOptions.value.find((pc: any) => pc.id === selectedProgramme);
    
    if (programme) {
        item.programme_code_id = programme.id;
        item.programme_code = programme.code;
        item.programme_name = programme.name;
        item.budget_code = programme.budget_code;
        
        // Also set economic code if available and not already set
        if (programme.economic_code_id && !item.economy_code_id) {
            item.economy_code_id = programme.economic_code_id;
            // Trigger economy code change to load items
            onEconomyCodeChange(item);
        }
        
        selectedProgrammeCodeMap.value[item.id] = programme;
        
        // Validate budget availability
        if (programme.remaining_budget < item.sub_total) {
            toast.add({
                severity: 'warn',
                summary: 'Budget Alert',
                detail: `Insufficient budget for programme ${programme.code}. Available: ₦${programme.remaining_budget.toLocaleString()}`,
                life: 5000,
            });
        }
        
        // Clear any previous errors
        if (item.errors?.programme_code_id) {
            delete item.errors.programme_code_id;
        }
        if (item.errors?.budget_code) {
            delete item.errors.budget_code;
        }
    }
};

// Update fetchProgrammeCodes to work with the search
const fetchProgrammeCodes = async (itemId: number, economicCodeId: number | null, filter = '') => {
    // For searchable dropdown, we don't pre-fetch all
    // Just set empty and let search handle it
    programmeCodes.value = [];
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

// Inertia form setup - UPDATED with programme code fields
const form = useForm({
    schedule_id: props.schedule?.id || null,
    voucher_type: props.voucherType,
    year_id: props.schedule?.year_id || null,
    mda_id: props.schedule?.mda_id || null,
    voucher_date: moment(props.today).format('YYYY-MM-DD'),
    narration: props.schedule?.narration || '',
    status: 'Draft',
    total_amount: props.schedule?.total_amount || 0,
    items: [] as LineItem[],
    documents: [] as File[],
    voucher_number: props.voucherNumber || '',
    payee_name: props.schedule?.payee_name || '',
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
        form.narration = `Payment voucher for ${mdaName} - Schedule ${scheduleNumber}`;
    }
};

// Computed properties for dynamic totals
const voucherSubtotal = computed(() => {
    return form.items.reduce((sum, item) => sum + (item.sub_total || 0), 0);
});

const voucherTotal = computed(() => {
    return voucherSubtotal.value;
});

const voucherTotalMatchesSchedule = computed(() => {
    if (!props.schedule?.total_amount) return true;
    return Math.abs(voucherTotal.value - props.schedule.total_amount) < 0.01;
});

const scheduleTotal = computed(() => {
    return props.schedule?.total_amount || 0;
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
        payee_name: '',
        voucher_number: '',
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
    if (!form.payee_name.trim()) {
        validationErrors.value.payee_name = 'Payee name is required';
        isValid = false;
    }

    return isValid;
};

// FIXED: Validate line items with programme code validation - budget code is optional now
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

        // Description validation
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

        // Economic Code validation
        if (!item.economy_code_id) {
            itemErrors.economy_code_id = 'Economic Code is required';
            isValid = false;
        }

        // Economic Code Item validation
        if (!item.economy_code_item_id) {
            itemErrors.economy_code_item_id = 'Economic Code item is required';
            isValid = false;
        }

        // Programme code validation for series 32 economic codes
        const selectedEconomyCode = props.economyCodes.find((ec: any) => ec.value === item.economy_code_id);
        const isSeries32 = selectedEconomyCode && (selectedEconomyCode.code?.startsWith('32') || selectedEconomyCode.type === 'capital');
        
        if (isSeries32 && !item.programme_code_id) {
            itemErrors.programme_code_id = 'Programme Code is required for series 32 (Capital Expenditure)';
            isValid = false;
        }

        // FIXED: Budget code validation - auto-fill from programme code, don't make it required
        if (!item.budget_code && item.programme_code_id) {
            // Try to auto-fill from selected programme
            const programme = selectedProgrammeCodeMap.value[item.id];
            if (programme && programme.budget_code) {
                item.budget_code = programme.budget_code;
            } else {
                // Budget code is not required for draft/submission
                // It can be auto-filled by the backend if needed
                console.log(`Budget code not set for item ${item.id}, but will be handled by backend`);
            }
        }

        // Quantity validation
        if (!item.quantity || item.quantity <= 0) {
            itemErrors.quantity = 'Quantity must be greater than 0';
            isValid = false;
        }

        // Unit price validation
        if (item.unit_price === null || item.unit_price === undefined || item.unit_price < 0) {
            itemErrors.unit_price = 'Unit price is required and cannot be negative';
            isValid = false;
        }

        // Sub total validation
        if (item.sub_total === null || item.sub_total === undefined || item.sub_total < 0) {
            itemErrors.sub_total = 'Sub total is required and cannot be negative';
            isValid = false;
        }

        // Check if sub_total matches quantity * unit_price
        if (item.quantity && item.unit_price && item.quantity > 0 && item.unit_price >= 0) {
            const calculatedSubTotal = item.quantity * item.unit_price;
            const subTotalDifference = Math.abs(item.sub_total - calculatedSubTotal);
            if (subTotalDifference > 0.01) {
                itemErrors.sub_total = 'Sub total does not match quantity × unit price';
                isValid = false;
            }
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

const validateDocuments = () => {
    validationErrors.value.documents = '';

    if (form.status === 'Draft') {
        return true;
    }

    if (form.status === 'Submitted') {
        const totalUploadedDocuments =
            requiredDocuments.value.filter((doc) => doc.uploaded).length +
            optionalDocuments.value.length;

        if (totalUploadedDocuments === 0) {
            validationErrors.value.documents = 'At least one supporting document is required for submission';
            return false;
        }

        const hasAnyRequiredDocument = requiredDocuments.value.some((doc) => doc.uploaded);
        if (!hasAnyRequiredDocument) {
            validationErrors.value.documents = 'At least one supporting document is required';
            return false;
        }
    }

    const maxFileSize = 10 * 1024 * 1024;
    const allowedTypes = [
        'image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp',
        'application/pdf', 'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'application/vnd.ms-excel',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
    ];

    const allFiles = [
        ...requiredDocuments.value.filter((doc) => doc.uploaded && doc.file).map((doc) => doc.file!),
        ...optionalDocuments.value.map((doc) => doc.file),
        ...form.documents,
    ];

    for (const file of allFiles) {
        if (file.size > maxFileSize) {
            validationErrors.value.documents = `File "${file.name}" exceeds the 10MB size limit`;
            return false;
        }
        if (!allowedTypes.includes(file.type)) {
            validationErrors.value.documents = `File "${file.name}" has an unsupported format`;
            return false;
        }
    }

    return true;
};

const formatCurrency = (value: number) => {
    return new Intl.NumberFormat('en-NG', {
        style: 'currency',
        currency: 'NGN',
    }).format(value);
};

// Update line item sub_total - UPDATED to validate budget
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

    // Check budget availability if programme code is selected
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

// Add new line item - UPDATED with programme code fields
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
        // Clean up programme code map
        delete selectedProgrammeCodeMap.value[id];
    }
};

// Watch for Economic Code changes - UPDATED
const onEconomyCodeChange = (item: LineItem) => {
    item.economy_code_item_id = null;
    // Don't clear programme code when economic code changes
    // Keep it as is, user can search for any programme code
};

// File upload handlers
const isFileAlreadyUploaded = (file: File) => {
    const inRequired = requiredDocuments.value.some(
        (doc) => doc.file && doc.file.name === file.name && doc.file.size === file.size,
    );
    const inOptional = optionalDocuments.value.some(
        (doc) => doc.file.name === file.name && doc.file.size === file.size,
    );
    const inForm = form.documents.some((f) => f.name === file.name && f.size === file.size);
    return inRequired || inOptional || inForm;
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
        if (selectedDocumentType.value && selectedDocumentType.value !== 'Other') {
            const requiredDoc = requiredDocuments.value.find(
                (doc) => doc.type === selectedDocumentType.value,
            );

            if (requiredDoc) {
                if (requiredDoc.uploaded && requiredDoc.file) {
                    form.documents = form.documents.filter((f) => f !== requiredDoc.file);
                }

                requiredDoc.uploaded = true;
                requiredDoc.file = file;

                if (!form.documents.includes(file)) {
                    form.documents.push(file);
                }

                toast.add({
                    severity: 'success',
                    summary: 'Document Added',
                    detail: `${requiredDoc.label} uploaded successfully`,
                    life: 3000,
                });
            }
        } else {
            const existingOptionalDoc = optionalDocuments.value.find(
                (doc) => doc.file.name === file.name && doc.file.size === file.size,
            );

            if (!existingOptionalDoc) {
                optionalDocuments.value.push({
                    type: 'Other',
                    label: 'Additional Document',
                    file: file,
                    document_type: 'Other',
                });

                if (!form.documents.includes(file)) {
                    form.documents.push(file);
                }
            }
        }
    });

    selectedDocumentType.value = '';
};

const assignDocumentType = (file: File, documentType: string) => {
    if (documentType === 'Other') return;

    const requiredDoc = requiredDocuments.value.find((doc) => doc.type === documentType);
    if (requiredDoc) {
        const optionalDocIndex = optionalDocuments.value.findIndex(
            (doc) => doc.file.name === file.name,
        );
        if (optionalDocIndex > -1) {
            optionalDocuments.value.splice(optionalDocIndex, 1);
        }

        if (requiredDoc.uploaded && requiredDoc.file) {
            optionalDocuments.value.push({
                type: 'Other',
                label: 'Replaced Document',
                file: requiredDoc.file,
                document_type: 'Other',
            });
            form.documents = form.documents.filter((f) => f !== requiredDoc.file);
        }

        requiredDoc.uploaded = true;
        requiredDoc.file = file;

        if (!form.documents.includes(file)) {
            form.documents.push(file);
        }

        toast.add({
            severity: 'success',
            summary: 'Document Type Assigned',
            detail: `File assigned as ${requiredDoc.label}`,
            life: 3000,
        });
    }
};

const removeDocumentAssignment = (documentType: string) => {
    const requiredDoc = requiredDocuments.value.find((doc) => doc.type === documentType);
    if (requiredDoc && requiredDoc.file) {
        optionalDocuments.value.push({
            type: 'Other',
            label: 'Additional Document',
            file: requiredDoc.file,
            document_type: 'Other',
        });
        requiredDoc.uploaded = false;
        requiredDoc.file = undefined;
    }
};

const onRemove = (event: any) => {
    const fileToRemove = event.file;

    const requiredDoc = requiredDocuments.value.find(
        (doc) => doc.file && doc.file.name === fileToRemove.name,
    );

    if (requiredDoc) {
        requiredDoc.uploaded = false;
        requiredDoc.file = undefined;
    }

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
    requiredDocuments.value.forEach((doc) => {
        doc.uploaded = false;
        doc.file = undefined;
    });
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

    requiredDocuments.value
        .filter((doc) => doc.uploaded && doc.file)
        .forEach((doc) => {
            documentTypesData.push({
                type: doc.type,
                label: doc.label,
                file_name: doc.file?.name || '',
            });
        });

    optionalDocuments.value.forEach((doc) => {
        documentTypesData.push({
            type: doc.document_type,
            label: doc.label,
            file_name: doc.file.name,
        });
    });

    form.voucher_date = moment(form.voucher_date).format('YYYY-MM-DD');

    // Ensure budget_code is set for all items before submission
    form.items.forEach(item => {
        if (item.programme_code_id && !item.budget_code) {
            const programme = selectedProgrammeCodeMap.value[item.id];
            if (programme && programme.budget_code) {
                item.budget_code = programme.budget_code;
            }
        }
        // If still no budget code, set a default or leave empty (backend will handle)
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
    };

    form.post('/vouchers', {
        data: submitData,
        preserveScroll: true,
        onSuccess: (response) => {
            let message = '';
            if (form.status === 'Draft') {
                message = 'Voucher saved as draft successfully! You can edit it later.';
            } else {
                message = 'Voucher submitted for approval successfully!';
            }
            toast.add({
                severity: 'success',
                summary: 'Success',
                detail: message,
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

const saveDraft = () => {
    form.status = 'Draft';
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

const submitForApproval = () => {
    form.status = 'Submitted';
    if (!validateForm()) {
        toast.add({
            severity: 'error',
            summary: 'Validation Error',
            detail: 'Please fix all validation errors before submitting for approval.',
            life: 5000,
        });
        return;
    }
    submitVoucher();
};

const validateForm = () => {
    const isHeaderValid = validateHeader();
    const areLineItemsValid = validateLineItems();

    if (form.status === 'Submitted') {
        const areDocumentsValid = validateDocuments();
        return isHeaderValid && areLineItemsValid && areDocumentsValid;
    }

    return isHeaderValid && areLineItemsValid;
};

// Payee lazy loading
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

const onFilter = (event: any) => {
    filterValue.value = event.value;
    fetchData(1, event.value);
};

fetchData(1);

// Initialize
onMounted(() => {
    console.log('Schedule data received:', props.schedule);
    autoSelectMdaAndYear();
    generateNarrationFromSchedule();
    if (form.items.length === 0) {
        addItem();
    }
    // Initialize programme code options from props
    if (props.programmeCodes && props.programmeCodes.length > 0) {
        programmeCodeOptions.value = props.programmeCodes;
    }
    // Load initial programme codes for search
    searchProgrammeCodes('');
});
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head :title="pageTitle" />
        <Toast />

        <Card class="voucher-card">
            <template #title>
                {{ pageTitle }}
                <span class="text-500 ml-3 text-sm">{{ defaultAccount }}</span>
            </template>

            <template #content>
                <!-- Schedule Information Banner -->
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
                                        {{
                                            formatCurrency(
                                                scheduleInfo.total_amount,
                                            )
                                        }}</span>
                                </div>
                                <!-- NEW: Total validation message -->
                                <div v-if="!voucherTotalMatchesSchedule" class="mt-2">
                                    <Message severity="warn" :closable="false">
                                        <div class="align-items-center flex gap-2">
                                            <i class="pi pi-exclamation-triangle"></i>
                                            <span>
                                                <strong>Total Mismatch:</strong>
                                                Voucher total ({{
                                                    formatCurrency(
                                                        voucherTotal,
                                                    )
                                                }}) must match schedule total
                                                ({{
                                                    formatCurrency(
                                                        scheduleTotal,
                                                    )
                                                }})
                                            </span>
                                        </div>
                                    </Message>
                                </div>
                            </div>
                        </div>
                    </Message>
                </div>

                <!-- Status Information -->
                <div class="mb-4">
                    <Message severity="info" :closable="false">
                        <div class="align-items-center flex gap-2">
                            <i class="pi pi-info-circle"></i>
                            <span>
                                <strong>Workflow:</strong> Vouchers are created
                                as <strong>Drafts</strong> by default. You can
                                save as draft and edit later, or submit directly
                                for approval to Internal Audit.
                            </span>
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
                    <div class="col-4">
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
                    <div class="col-4">
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
                </div>

                <!-- Voucher Type Display -->
                <div class="mb-4 grid">
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
                            <small class="text-500">Type: {{ voucherType }}</small>
                        </div>
                    </div>
                    <div class="col-4" v-if="scheduleInfo">
                        <div class="field">
                            <label class="text-500 mb-1 block text-sm font-semibold">
                                Source Schedule
                            </label>
                            <InputText :modelValue="scheduleInfo.schedule_number" class="w-full" disabled />
                            <small class="text-500">Auto-populated from schedule</small>
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
                                @filter="onFilter" class="w-full" />
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

                    <DataTable :value="form.items" class="p-datatable-sm fixed-column-table" responsiveLayout="scroll">
                        <Column field="description" header="Description" headerStyle="width: 25%; min-width: 200px"
                            bodyStyle="width: 25%; min-width: 200px">
                            <template #body="slotProps">
                                <div class="flex-column flex">
                                    <Textarea v-model="slotProps.data.description" rows="2" autoResize
                                        placeholder="Enter item description..." class="w-full" :class="{
                                            'p-invalid':
                                                slotProps.data.errors
                                                    ?.description,
                                        }" @input="
                                            if (slotProps.data.errors)
                                            delete slotProps.data.errors
                                                .description;
                                            " />
                                    <small v-if="
                                        slotProps.data.errors?.description
                                    " class="p-error mt-1">
                                        {{ slotProps.data.errors.description }}
                                    </small>
                                </div>
                            </template>
                        </Column>

                        <!-- Economic Code Column -->
                        <Column field="economy_code_id" header="Economic Code"
                            headerStyle="width: 150px; min-width: 150px; max-width: 150px"
                            bodyStyle="width: 150px; min-width: 150px; max-width: 150px">
                            <template #body="slotProps">
                                <div class="flex-column fixed-dropdown-container flex">
                                    <Dropdown v-model="slotProps.data.economy_code_id" :options="economyCodeOptions"
                                        optionLabel="label" optionValue="value" placeholder="Select Code"
                                        class="fixed-economy-dropdown w-full" :filter="true"
                                        filterPlaceholder="Search Economic Codes..." :showClear="true" :class="{
                                            'p-invalid':
                                                slotProps.data.errors
                                                    ?.economy_code_id,
                                        }" @change="
                                            onEconomyCodeChange(slotProps.data)
                                            " />
                                    <small v-if="
                                        slotProps.data.errors
                                            ?.economy_code_id
                                    " class="p-error mt-1">
                                        {{
                                            slotProps.data.errors
                                                .economy_code_id
                                        }}
                                    </small>
                                </div>
                            </template>
                        </Column>

                        <!-- Economic Code Item Column -->
                        <Column field="economy_code_item_id" header="Code Item"
                            headerStyle="width: 150px; min-width: 150px; max-width: 150px"
                            bodyStyle="width: 150px; min-width: 150px; max-width: 150px">
                            <template #body="slotProps">
                                <div class="flex-column fixed-dropdown-container flex">
                                    <Dropdown v-model="slotProps.data.economy_code_item_id
                                        " :options="getEconomyCodeItemOptions(
                                            slotProps.data.economy_code_id,
                                        )
                                            " optionLabel="label" optionValue="value" placeholder="Select Item"
                                        class="fixed-economy-dropdown w-full" :disabled="!slotProps.data.economy_code_id
                                            " :filter="true" filterPlaceholder="Search code items..." :showClear="true"
                                        :class="{
                                            'p-invalid':
                                                slotProps.data.errors
                                                    ?.economy_code_item_id,
                                        }" />
                                    <small v-if="
                                        slotProps.data.errors
                                            ?.economy_code_item_id
                                    " class="p-error mt-1">
                                        {{
                                            slotProps.data.errors
                                                .economy_code_item_id
                                        }}
                                    </small>
                                    <small v-else-if="
                                        !slotProps.data.economy_code_id
                                    " class="text-500 mt-1">
                                        Select Code first
                                    </small>
                                </div>
                            </template>
                        </Column>

                        <!-- Programme Code Column - SEARCHABLE VERSION -->
                        <Column field="programme_code_id" header="Programme Code"
                            headerStyle="width: 280px; min-width: 280px; max-width: 280px"
                            bodyStyle="width: 280px; min-width: 280px; max-width: 280px">
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
                                        :class="{
                                            'p-invalid': slotProps.data.errors?.programme_code_id,
                                        }" 
                                        @filter="onProgrammeCodeSearch"
                                        @change="onProgrammeCodeSelect(slotProps.data, $event.value)"
                                    >
                                        <template #option="slotProps">
                                            <div class="flex flex-column">
                                                <span class="font-medium">{{ slotProps.option.display_text || slotProps.option.label }}</span>
                                                <small class="text-500">{{ slotProps.option.detail_text || `Budget Code: ${slotProps.option.budget_code} | Remaining: ₦${Number(slotProps.option.remaining_budget).toLocaleString()}` }}</small>
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

                        <Column field="quantity" header="Qty"
                            headerStyle="width: 80px; min-width: 80px; max-width: 80px"
                            bodyStyle="width: 80px; min-width: 80px; max-width: 80px">
                            <template #body="slotProps">
                                <div class="flex-column flex">
                                    <InputNumber v-model="slotProps.data.quantity" @update:modelValue="
                                        updateItemSubTotal(
                                            slotProps.data,
                                            'quantity',
                                        )
                                        " :min="1" :max-fraction-digits="2" inputClass="w-full text-center" :class="{
                                            'p-invalid':
                                                slotProps.data.errors?.quantity,
                                        }" @input="
                                            if (slotProps.data.errors)
                                            delete slotProps.data.errors
                                                .quantity;
                                            " />
                                    <small v-if="slotProps.data.errors?.quantity" class="p-error mt-1">
                                        {{ slotProps.data.errors.quantity }}
                                    </small>
                                </div>
                            </template>
                        </Column>

                        <Column field="unit_price" header="Unit Price"
                            headerStyle="width: 130px; min-width: 130px; max-width: 130px"
                            bodyStyle="width: 130px; min-width: 130px; max-width: 130px">
                            <template #body="slotProps">
                                <div class="flex-column flex">
                                    <InputNumber v-model="slotProps.data.unit_price" @update:modelValue="
                                        updateItemSubTotal(
                                            slotProps.data,
                                            'unit_price',
                                        )
                                        " mode="currency" currency="NGN" locale="en-NG" :min="0"
                                        inputClass="w-full text-right" :class="{
                                            'p-invalid':
                                                slotProps.data.errors
                                                    ?.unit_price,
                                        }" @input="
                                            if (slotProps.data.errors)
                                            delete slotProps.data.errors
                                                .unit_price;
                                            " />
                                    <small v-if="slotProps.data.errors?.unit_price" class="p-error mt-1">
                                        {{ slotProps.data.errors.unit_price }}
                                    </small>
                                </div>
                            </template>
                        </Column>

                        <Column field="sub_total" header="Sub Total"
                            headerStyle="width: 130px; min-width: 130px; max-width: 130px"
                            bodyStyle="width: 130px; min-width: 130px; max-width: 130px"
                            bodyClass="font-bold text-right">
                            <template #body="slotProps">
                                <div class="flex-column flex">
                                    <InputNumber v-model="slotProps.data.sub_total" @update:modelValue="
                                        updateItemSubTotal(
                                            slotProps.data,
                                            'sub_total',
                                        )
                                        " mode="currency" currency="NGN" locale="en-NG" :min="0"
                                        inputClass="w-full text-right" :class="{
                                            'p-invalid':
                                                slotProps.data.errors
                                                    ?.sub_total,
                                        }" @input="
                                            if (slotProps.data.errors)
                                            delete slotProps.data.errors
                                                .sub_total;
                                            " readonly />
                                    <small v-if="slotProps.data.errors?.sub_total" class="p-error mt-1">
                                        {{ slotProps.data.errors.sub_total }}
                                    </small>
                                </div>
                            </template>
                        </Column>

                        <Column headerStyle="width: 60px; min-width: 60px; max-width: 60px"
                            bodyStyle="width: 60px; min-width: 60px; max-width: 60px" bodyClass="text-center">
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

                <!-- Documents and Totals Section -->
                <div class="mb-4 grid">
                    <div class="col-6">
                        <!-- Enhanced Documents Section -->
                        <div class="field-group">
                            <div class="justify-content-between align-items-center mb-3 flex">
                                <h4 class="m-0">
                                    Supporting Documents
                                    <span class="text-500 ml-2 text-sm">
                                        (At least 1 document required for
                                        submission)
                                    </span>
                                </h4>
                                <Button v-if="
                                    form.documents.length > 0 ||
                                    requiredDocuments.some(
                                        (doc) => doc.uploaded,
                                    ) ||
                                    optionalDocuments.length > 0
                                " label="Clear All" icon="pi pi-times" severity="secondary" text
                                    @click="clearAllDocuments" />
                            </div>

                            <!-- Document Type Selection -->
                            <div class="surface-50 border-round mb-4 p-3">
                                <h5 class="mt-0 mb-2">
                                    Document Type Selection
                                </h5>
                                <div class="align-items-end flex gap-2">
                                    <div class="flex-1">
                                        <label class="text-500 mb-1 block text-sm font-semibold">
                                            Select document type before
                                            uploading:
                                        </label>
                                        <Dropdown v-model="selectedDocumentType" :options="documentTypeOptions"
                                            optionLabel="label" optionValue="value"
                                            placeholder="Choose document type..." class="w-full" />
                                    </div>
                                </div>
                                <small class="text-500 mt-2 block">
                                    <i class="pi pi-info-circle mr-1"></i>
                                    Choose the document type from dropdown
                                    before selecting files.
                                    <strong>At least one document is required for
                                        submission.</strong>
                                </small>
                            </div>

                            <!-- Required Documents Status -->
                            <div class="mb-4">
                                <h5 class="mb-2">
                                    Required Document:
                                </h5>
                                <div class="grid">
                                    <div v-for="doc in requiredDocuments" :key="doc.type" class="col-12 mb-3">
                                        <div class="surface-100 border-round border-1 p-3">
                                            <div class="align-items-center justify-content-between mb-2 flex">
                                                <div class="align-items-center flex gap-2">
                                                    <i :class="doc.uploaded
                                                        ? 'pi pi-check-circle text-green-500'
                                                        : 'pi pi-exclamation-circle text-orange-500'
                                                        "></i>
                                                    <span :class="doc.uploaded
                                                        ? 'text-700 font-semibold'
                                                        : 'text-500'
                                                        ">
                                                        {{ doc.label }}
                                                    </span>
                                                    <Badge v-if="
                                                        doc.required &&
                                                        !doc.uploaded
                                                    " value="Required" severity="warning" size="small" />
                                                    <Badge v-else-if="doc.uploaded" value="Uploaded" severity="success"
                                                        size="small" />
                                                </div>
                                                <Button v-if="doc.uploaded" icon="pi pi-times" severity="danger" text
                                                    rounded size="small" @click="
                                                        removeDocumentAssignment(
                                                            doc.type,
                                                        )
                                                        " title="Remove assignment" />
                                            </div>
                                            <div v-if="doc.uploaded" class="mt-2">
                                                <small class="text-500 block">
                                                    <i class="pi pi-file mr-1"></i>
                                                    {{ doc.file?.name }}
                                                </small>
                                                <small class="text-500">
                                                    {{
                                                        (
                                                            doc.file!.size /
                                                            1024
                                                        ).toFixed(2)
                                                    }}
                                                    KB
                                                </small>
                                            </div>
                                            <div v-else class="mt-1">
                                                <small class="text-500">
                                                    Required for submission
                                                </small>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Document Upload Stats -->
                            <div class="surface-50 border-round mb-3 p-3">
                                <div class="align-items-center justify-content-between flex">
                                    <span class="font-semibold">Upload Summary:</span>
                                    <span class="font-bold" :class="requiredDocuments.filter(
                                        (doc) => doc.uploaded,
                                    ).length +
                                        optionalDocuments.length >
                                        0
                                        ? 'text-green-500'
                                        : 'text-orange-500'
                                        ">
                                        {{
                                            requiredDocuments.filter(
                                                (doc) => doc.uploaded,
                                            ).length + optionalDocuments.length
                                        }}
                                        document(s) uploaded
                                    </span>
                                </div>
                                <small class="text-500">
                                    Minimum 1 document required for submission
                                </small>
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
                                :customUpload="true" :disabled="form.processing" :class="{
                                    'p-invalid': validationErrors.documents,
                                }">
                                <template #empty>
                                    <p class="text-500">
                                        Drag and drop files here or click to
                                        browse
                                    </p>
                                    <small class="text-500">
                                        Supported formats: Images, PDF, Word,
                                        Excel (Max: 10MB per file)
                                    </small>
                                    <div class="mt-2" v-if="!selectedDocumentType">
                                        <Message severity="warn" :closable="false" class="text-sm">
                                            <div class="align-items-center flex gap-2">
                                                <i class="pi pi-exclamation-triangle"></i>
                                                <span>Please select a document
                                                    type above before
                                                    uploading</span>
                                            </div>
                                        </Message>
                                    </div>
                                    <div class="mt-2" v-else>
                                        <Message severity="info" :closable="false" class="text-sm">
                                            <div class="align-items-center flex gap-2">
                                                <i class="pi pi-info-circle"></i>
                                                <span>Uploading as:
                                                    <strong>{{
                                                        documentTypeOptions.find(
                                                            (opt) =>
                                                                opt.value ===
                                                                selectedDocumentType,
                                                        )?.label
                                                    }}</strong></span>
                                            </div>
                                        </Message>
                                    </div>
                                    <div class="mt-1">
                                        <small class="text-500">
                                            <strong>Document Requirements:</strong>
                                            At least one supporting document is
                                            required for submission.
                                        </small>
                                    </div>
                                    <div v-if="validationErrors.documents" class="mt-2">
                                        <small class="p-error">{{
                                            validationErrors.documents
                                        }}</small>
                                    </div>
                                </template>
                            </FileUpload>

                            <!-- Uploaded Files Display with Type Assignment -->
                            <div v-if="
                                form.documents.length > 0 ||
                                requiredDocuments.some(
                                    (doc) => doc.uploaded,
                                ) ||
                                optionalDocuments.length > 0
                            " class="mt-3">
                                <h5 class="mb-2">
                                    Uploaded Files ({{
                                        requiredDocuments.filter(
                                            (doc) => doc.uploaded,
                                        ).length + optionalDocuments.length
                                    }}):
                                </h5>

                                <!-- Unassigned/Optional Documents -->
                                <div v-if="optionalDocuments.length > 0" class="mb-4">
                                    <h6 class="mb-2 text-blue-600">
                                        Additional Documents:
                                    </h6>
                                    <div class="grid">
                                        <div v-for="(
doc, index
                                            ) in optionalDocuments" :key="index" class="col-12 mb-3">
                                            <div
                                                class="align-items-center justify-content-between surface-50 border-round flex p-3">
                                                <div class="align-items-center flex gap-3">
                                                    <i class="pi pi-file text-2xl"></i>
                                                    <div>
                                                        <div class="font-medium">
                                                            {{ doc.file.name }}
                                                        </div>
                                                        <small class="text-500">
                                                            {{
                                                                (
                                                                    doc.file
                                                                        .size /
                                                                    1024
                                                                ).toFixed(2)
                                                            }}
                                                            KB
                                                        </small>
                                                    </div>
                                                </div>
                                                <div class="align-items-center flex gap-2">
                                                    <Dropdown v-model="doc.document_type
                                                        " :options="documentTypeOptions"
                                                        optionLabel="label" optionValue="value"
                                                        placeholder="Assign type..." class="w-10rem" @change="
                                                            assignDocumentType(
                                                                doc.file,
                                                                doc.document_type,
                                                            )
                                                            " />
                                                    <Button icon="pi pi-times" severity="danger" text rounded @click="
                                                        onRemove({
                                                            file: doc.file,
                                                        })
                                                        " />
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Required Documents Summary -->
                                <div v-if="
                                    requiredDocuments.some(
                                        (doc) => doc.uploaded,
                                    )
                                " class="mb-3">
                                    <h6 class="mb-2 text-green-600">
                                        Required Document:
                                    </h6>
                                    <ul class="m-0 list-none p-0">
                                        <li v-for="doc in requiredDocuments.filter(
                                            (d) => d.uploaded,
                                        )" :key="doc.type"
                                            class="align-items-center justify-content-between surface-50 border-round mb-2 flex p-2">
                                            <div class="align-items-center flex">
                                                <i class="pi pi-check-circle mr-2 text-green-500"></i>
                                                <div>
                                                    <span class="font-medium">{{
                                                        doc.label
                                                    }}</span>
                                                    <small class="text-500 block">{{
                                                        doc.file?.name
                                                        }}</small>
                                                </div>
                                            </div>
                                            <div class="align-items-center flex gap-2">
                                                <Badge value="Required" severity="success" size="small" />
                                                <small class="text-500">
                                                    {{
                                                        (
                                                            doc.file!.size /
                                                            1024
                                                        ).toFixed(2)
                                                    }}
                                                    KB
                                                </small>
                                                <Button icon="pi pi-times" severity="danger" text rounded @click="
                                                    removeDocumentAssignment(
                                                        doc.type,
                                                    )
                                                    " />
                                            </div>
                                        </li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-6">
                        <div class="totals-section">
                            <h4 class="mb-3">Voucher Summary</h4>

                            <!-- Schedule Total Reference -->
                            <div v-if="scheduleInfo" class="surface-50 border-round mb-3 p-3">
                                <div class="justify-content-between align-items-center flex">
                                    <span class="text-500 font-semibold">Schedule Total:</span>
                                    <span class="text-primary font-bold">{{
                                        formatCurrency(scheduleTotal)
                                    }}</span>
                                </div>
                                <small class="text-500">Reference amount from schedule</small>
                            </div>

                            <div class="justify-content-between total-row mb-2 flex">
                                <span class="text-500">Number of Vouchers Raised:</span>
                                <span class="font-semibold">{{ props.schedule.voucher_count || 0 }}</span>
                            </div>
                            <div class="justify-content-between total-row mb-2 flex">
                                <span class="text-500">Total Amount Raised:</span>
                                <span class="font-semibold">{{ formatCurrency(props.schedule.amount_posted || 0) }}</span>
                            </div>
                            <div class="justify-content-between total-row mb-2 flex">
                                <span class="text-500">Outstanding Balance:</span>
                                <span class="font-semibold">{{ formatCurrency(scheduleTotal -
                                    (props.schedule.amount_posted || 0)) }}</span>
                            </div>
                            <div class="justify-content-between total-row mb-2 flex">
                                <span class="text-500">Voucher Subtotal:</span>
                                <span class="font-semibold" :class="{
                                    'text-green-500':
                                        scheduleTotal - ((props.schedule.amount_posted || 0) + voucherSubtotal) >= 0,
                                    'text-red-500':
                                        scheduleTotal - ((props.schedule.amount_posted || 0) + voucherSubtotal) < 0,
                                }">{{ formatCurrency(voucherSubtotal) }}</span>
                            </div>
                            <Divider />
                            <div class="justify-content-between total-row flex text-xl font-bold" :class="{
                                'text-green-500':
                                    scheduleTotal - ((props.schedule.amount_posted || 0) + voucherSubtotal) >= 0,
                                'text-orange-500':
                                    scheduleTotal - ((props.schedule.amount_posted || 0) + voucherSubtotal) < 0,
                            }">
                                <span>Voucher Total:</span>
                                <span>{{ formatCurrency(voucherTotal) }}</span>
                            </div>

                            <!-- Validation Status -->
                            <div v-if="scheduleInfo" class="mt-2">
                                <div v-if="scheduleTotal - ((props.schedule.amount_posted || 0) + voucherSubtotal) == 0"
                                    class="align-items-center flex gap-2 text-green-500">
                                    <i class="pi pi-check-circle"></i>
                                    <small class="font-semibold">Total amount on raised vouchers now matches schedule
                                        total</small>
                                </div>
                                <div v-if="scheduleTotal - ((props.schedule.amount_posted || 0) + voucherSubtotal) > 0 && voucherTotal > 0"
                                    class="align-items-center flex gap-2 text-orange-400">
                                    <i class="pi pi-exclamation-triangle"></i>
                                    <small class="font-semibold">Total amount on raised vouchers is below the schedule
                                        total.
                                        <br />Please adjust the line items to match the schedule total.
                                        <br />Alternatively, you may
                                        have to add another voucher to this schedule.</small>
                                </div>
                                <div v-if="scheduleTotal - ((props.schedule.amount_posted || 0) + voucherSubtotal) < 0"
                                    class="align-items-center flex gap-2 text-red-500">
                                    <i class="pi pi-exclamation-triangle"></i>
                                    <small class="font-semibold">You have exceeded the total amount on the schedule
                                        total. <br />Please
                                        adjust the line items to match schedule
                                        total.</small>
                                </div>
                            </div>

                            <InputNumber v-model="form.total_amount" mode="currency" currency="NGN" locale="en-NG"
                                class="mt-2 hidden w-full" readonly />
                        </div>
                    </div>
                </div>

                <!-- Action Buttons -->
                <div class="justify-content-end mt-5 flex gap-2">
                    <Button label="Save as Draft" icon="pi pi-save" severity="secondary" :loading="form.processing"
                        @click="saveDraft" title="Save as draft (documents optional, can edit later)" />
                    <Button label="Submit for Approval" icon="pi pi-send" severity="success" :loading="form.processing"
                        @click="submitForApproval"
                        title="Submit for approval to Internal Audit (requires all documents)" />
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

.hidden {
    display: none;
}

:deep(.p-datatable) {
    border: 1px solid var(--p-surface-200);
    border-radius: 6px;
    width: 100%;
    overflow-x: auto;
}

:deep(.p-datatable-thead > tr > th) {
    background: var(--p-surface-100);
    color: var(--p-text-color);
    font-weight: 600;
    border-color: var(--p-surface-200);
}

:deep(.p-datatable-tbody > tr) {
    background: var(--p-surface-0);
    transition: background-color 0.2s;
}

:deep(.p-datatable-tbody > tr:hover) {
    background: var(--p-surface-50);
}

:deep(.p-invalid) {
    border-color: var(--p-error-color) !important;
}

:deep(.p-fileupload.p-invalid) {
    border: 1px solid var(--p-error-color) !important;
    border-radius: 6px;
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

.amount-in-words {
    background: var(--p-surface-0);
    border: 1px solid var(--p-surface-200);
    border-radius: 4px;
    padding: 0.75rem;
    margin-top: 1rem;
}

.amount-in-words-label {
    color: var(--p-primary-color);
    font-weight: 600;
    margin-bottom: 0.5rem;
}

:deep(.p-datatable-table) {
    table-layout: fixed;
    width: 100%;
}

:deep(.p-datatable-thead > tr > th),
:deep(.p-datatable-tbody > tr > td) {
    overflow: hidden;
    text-overflow: ellipsis;
}
</style>