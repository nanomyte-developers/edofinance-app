<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import axios from 'axios';
import Badge from 'primevue/badge';
import Button from 'primevue/button';
import Calendar from 'primevue/calendar';
import Card from 'primevue/card';
import Column from 'primevue/column';
import ConfirmDialog from 'primevue/confirmdialog';
import DataTable from 'primevue/datatable';
import Dialog from 'primevue/dialog';
import Divider from 'primevue/divider';
import Dropdown from 'primevue/dropdown';
import FileUpload from 'primevue/fileupload';
import InputNumber from 'primevue/inputnumber';
import InputText from 'primevue/inputtext';
import Message from 'primevue/message';
import Select from 'primevue/select';
import Textarea from 'primevue/textarea';
import Toast from 'primevue/toast';
import { useConfirm } from 'primevue/useconfirm';
import { useToast } from 'primevue/usetoast';
import { computed, onMounted, ref, watch } from 'vue';
import moment from 'moment';
// Layout and types
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';

const toast = useToast();
const confirm = useConfirm();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Vouchers', href: '/vouchers' },
    { title: 'Edit Voucher', href: '#' },
];

// Document viewer state
const documentViewerVisible = ref(false);
const currentDocument = ref<any>(null);
const documentViewerTitle = ref('');

// Add confirmation dialog visibility
const showSubmitConfirmation = ref(false);

// File upload ref
const fileUploadRef = ref<any>(null);

// Add this computed property to check if editing is allowed
const canEditVoucher = computed(() => {
    const nonEditableStatuses = ['approved', 'paid', 'processed'];
    return !nonEditableStatuses.includes(props.voucher.status?.toLowerCase());
});

let nextItemId = 1;

// Define types for line items
interface LineItem {
    id: number;
    description: string;
    quantity: number;
    unit_price: number;
    sub_total: number;
    economy_code_id: number | null;
    economy_code_item_id: number | null;
    programme_code_id: number | null;
    programme_code: string | null;
    programme_name: string | null;
    budget_code: string | null;
    errors?: {
        description?: string;
        quantity?: string;
        unit_price?: string;
        sub_total?: string;
        economy_code_id?: string;
        economy_code_item_id?: string;
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
    existing_document_id?: number;
    existing_file_name?: string;
}

interface UploadedDocument {
    id?: number;
    type: string;
    label: string;
    file: File;
    document_type: string;
}

interface ExistingDocument {
    id: number;
    file_name: string;
    file_path: string;
    file_size: number;
    mime_type: string;
    document_type: string;
    document_label: string;
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

// DFA Permissions interface
interface DFAPermissions {
    can_submit_for_approval: boolean;
    can_save_as_draft: boolean;
    is_subordinate: boolean;
    is_dfa_main: boolean;
    can_view: boolean;
}

const voucherTypes = [
    {
        label: 'Capital',
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

// Props from Laravel controller
const props = defineProps({
    voucher: {
        type: Object,
        required: true,
    },
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
    existingDocuments: {
        type: Array,
        default: () => [],
    },
    today: {
        type: String,
        default: new Date().toISOString().split('T')[0],
    },
    schedule: {
        type: Object,
        default: () => ({}),
    },
    programmeCodes: {
        type: Array,
        default: () => [],
    },
    dfaPermissions: {
        type: Object as () => DFAPermissions,
        default: () => ({
            can_submit_for_approval: false,
            can_save_as_draft: false,
            is_subordinate: false,
            is_dfa_main: false,
            can_view: false,
        }),
    },
});

// Page title
const pageTitle = `Edit Voucher - ${props.voucher.voucher_number}`;

// Validation state
const validationErrors = ref({
    year_id: '',
    mda_id: '',
    voucher_date: '',
    narration: '',
    line_items: '',
    documents: '',
    voucher_type: '',
    voucher_number: '',
    payee_name: '',
    bank_activity_id: '',
});

// Document type options for dropdown
const documentTypeOptions = [
    { label: 'Approval Memo', value: 'approval_memo' },
    { label: 'Release Warrant', value: 'release_warrant' },
    { label: 'Exco Approval/Conclusion', value: 'exco_approval' },
    { label: 'Ministerial Tender Board', value: 'ministerial_tender_board' },
    { label: 'State Tenders Board', value: 'state_tender_board' },
    {
        label: 'Certificate Of Incorporation',
        value: 'certificate_of_incorporation',
    },
    { label: 'Tax Clearance', value: 'tax_clearance' },
    { label: 'Tax Identification Number (TIN)', value: 'tin' },
    { label: 'Procurement Registration', value: 'procurement_registration' },
    {
        label: 'Advance Payment Guarantee (APG)',
        value: 'advance_payment_guarantee',
    },
    { label: 'Receipt', value: 'receipt' },
    { label: 'Delivery Note', value: 'delivery_note' },
    { label: 'Other Document', value: 'other' },
];

// Document management
const requiredDocuments = ref<RequiredDocument[]>([]);
const optionalDocuments = ref<UploadedDocument[]>([]);
const selectedDocumentType = ref<string>('');
const allUploadedFiles = ref<File[]>([]);
const existingDocuments = ref<ExistingDocument[]>([]);
const documentsToDelete = ref<number[]>([]);

// Programme Codes Management
const programmeCodes = ref<ProgrammeCode[]>([]);
const programmeCodeLoading = ref(false);
const selectedProgrammeCodeMap = ref<Record<number, ProgrammeCode>>({});

// Searchable programme code refs
const programmeCodeSearchQuery = ref('');
const programmeCodeOptions = ref<any[]>([]);
const programmeCodeSearchLoading = ref(false);
const programmeCodeSearchDebounce = ref<any>(null);

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

// Clear field error helper
const clearFieldError = (item: LineItem, field: string) => {
    if (item.errors && item.errors[field]) {
        delete item.errors[field];
    }
};

// ✅ FIXED: Initialize required documents from existing documents
const initializeRequiredDocuments = () => {
    // Define required document types - Approval Memo is the only truly required one
    const requiredTypes = [
        { type: 'approval_memo', label: 'Approval Memo', required: true },
        { type: 'release_warrant', label: 'Release Warrant', required: false },
        { type: 'exco_approval', label: 'Exco Approval/Conclusion', required: false },
    ];
    
    // Initialize required documents
    requiredDocuments.value = requiredTypes.map(rt => ({
        ...rt,
        uploaded: false,
        file: undefined,
        existing_document_id: undefined,
        existing_file_name: undefined,
    }));

    // Get existing documents from props - ensure it's an array
    const existingDocs = Array.isArray(props.existingDocuments) ? props.existingDocuments : [];
    existingDocuments.value = existingDocs;

    // Map existing documents to required documents
    existingDocs.forEach((doc: any) => {
        // Try to find matching required document by type
        const requiredDoc = requiredDocuments.value.find(
            (rd) => rd.type === doc.document_type
        );
        
        if (requiredDoc) {
            requiredDoc.uploaded = true;
            requiredDoc.existing_document_id = doc.id;
            requiredDoc.existing_file_name = doc.file_name;
        } else {
            // If not a required type, add to optional with a flag
            optionalDocuments.value.push({
                type: doc.document_type || 'other',
                label: doc.document_label || doc.document_type || 'Additional Document',
                file: new File([], doc.file_name), // Placeholder for existing docs
                document_type: doc.document_type || 'other',
            });
        }
    });
    
    // Debug: Log the state after initialization
    console.log('📄 Required documents after initialization:', requiredDocuments.value);
    console.log('📄 Total existing documents:', existingDocuments.value.length);
    console.log('📄 Optional documents:', optionalDocuments.value.length);
};

// Economic Code Options
const economyCodeOptions = computed(() => {
    return props.economyCodes;
});

// Filter Economic Code items based on selected Economic Code for each row
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
    _method: 'PUT',
    voucher_type: props.voucher.voucher_type?.toLowerCase() || 'standard',
    year_id: props.voucher.year_id,
    mda_id: props.voucher.mda_id,
    voucher_date: props.voucher.voucher_date ? moment(props.voucher.voucher_date).format('YYYY-MM-DD') : moment().format('YYYY-MM-DD'),
    narration: props.voucher.narration || '',
    status: props.voucher.status || 'Draft',
    total_amount: props.voucher.total_amount || 0,
    items: [] as LineItem[],
    documents: [] as File[],
    documents_to_delete: [] as number[],
    voucher_number: props.voucher.voucher_number || '',
    payee_name: props.voucher.payee_name || '',
    bank_activity_id: props.voucher.bank_activity_id || null,
});

// Initialize form items from props
const initializeFormItems = () => {
    if (props.voucher.items && props.voucher.items.length > 0) {
        form.items = props.voucher.items.map((item: any) => ({
            id: nextItemId++,
            description: item.description || '',
            quantity: Number(item.quantity) || 1,
            unit_price: Number(item.unit_price) || 0,
            sub_total: Number(item.sub_total) || 0,
            economy_code_id: item.economy_code_id || null,
            economy_code_item_id: item.economy_code_item_id || null,
            programme_code_id: item.programme_code_id || null,
            programme_code: item.programme_code || null,
            programme_name: item.programme_name || null,
            budget_code: item.budget_code || null,
        }));

        form.total_amount = form.items.reduce((sum, item) => sum + (Number(item.sub_total) || 0), 0);
        
        // Initialize programme code map for existing items
        form.items.forEach(item => {
            if (item.programme_code_id && item.programme_code) {
                selectedProgrammeCodeMap.value[item.id] = {
                    id: item.programme_code_id,
                    code: item.programme_code,
                    name: item.programme_name || '',
                    budget_code: item.budget_code || '',
                    remaining_budget: 0,
                    economic_code_id: item.economy_code_id || 0,
                };
            }
        });
    } else {
        addItem();
    }
};

// Computed properties for dynamic totals
const voucherSubtotal = computed(() => {
    const total = form.items.reduce((sum, item) => {
        const subTotal = Number(item.sub_total) || 0;
        return sum + subTotal;
    }, 0);
    return isNaN(total) ? 0 : total;
});

const voucherTotal = computed(() => {
    return voucherSubtotal.value;
});

watch(voucherTotal, (newTotal) => {
    form.total_amount = newTotal;
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

const formatCurrency = (value: number) => {
    if (isNaN(value) || value === null || value === undefined) {
        return '₦0.00';
    }
    return new Intl.NumberFormat('en-NG', {
        style: 'currency',
        currency: 'NGN',
    }).format(value);
};

// Document viewer functions
const viewDocument = (document: any, type: 'existing' | 'required' | 'optional' = 'existing') => {
    if (type === 'existing') {
        let documentUrl = document.file_path;
        if (documentUrl && !documentUrl.startsWith('http') && !documentUrl.startsWith('/')) {
            documentUrl = `/storage/${documentUrl}`;
        } else if (documentUrl && !documentUrl.startsWith('http') && documentUrl.startsWith('/')) {
            documentUrl = `${window.location.origin}${documentUrl}`;
        }
        currentDocument.value = {
            name: document.file_name,
            url: documentUrl,
            type: document.mime_type,
            label: document.document_label,
            size: document.file_size,
        };
        documentViewerTitle.value = `${document.document_label} - ${document.file_name}`;
    } else if (type === 'required' && document.file) {
        const fileUrl = URL.createObjectURL(document.file);
        currentDocument.value = {
            name: document.file.name,
            url: fileUrl,
            type: document.file.type,
            label: document.label,
            size: document.file.size,
        };
        documentViewerTitle.value = `${document.label} - ${document.file.name}`;
    } else if (type === 'optional' && document.file) {
        const fileUrl = URL.createObjectURL(document.file);
        currentDocument.value = {
            name: document.file.name,
            url: fileUrl,
            type: document.file.type,
            label: document.label,
            size: document.file.size,
        };
        documentViewerTitle.value = `${document.label} - ${document.file.name}`;
    }
    documentViewerVisible.value = true;
};

const closeDocumentViewer = () => {
    documentViewerVisible.value = false;
    if (currentDocument.value?.url?.startsWith('blob:')) {
        URL.revokeObjectURL(currentDocument.value.url);
    }
    currentDocument.value = null;
};

const downloadDocument = () => {
    if (currentDocument.value) {
        const link = document.createElement('a');
        link.href = currentDocument.value.url;
        link.download = currentDocument.value.name;
        link.target = '_blank';
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
    }
};

const isViewable = (mimeType: string) => {
    const viewableTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp', 'application/pdf', 'text/plain'];
    return viewableTypes.includes(mimeType);
};

const getDocumentIcon = (mimeType: string, fileName: string) => {
    if (mimeType.startsWith('image/')) return 'pi pi-image';
    if (mimeType === 'application/pdf') return 'pi pi-file-pdf';
    if (mimeType.includes('word') || fileName.endsWith('.doc') || fileName.endsWith('.docx')) return 'pi pi-file-word';
    if (mimeType.includes('excel') || fileName.endsWith('.xls') || fileName.endsWith('.xlsx')) return 'pi pi-file-excel';
    return 'pi pi-file';
};

// Line item calculation
const updateItemSubTotal = (item: LineItem, field: 'quantity' | 'unit_price' | 'sub_total') => {
    const quantity = Number(item.quantity) || 0;
    const unit_price = Number(item.unit_price) || 0;
    const sub_total = Number(item.sub_total) || 0;

    if (field === 'quantity' || field === 'unit_price') {
        const calculatedSubTotal = quantity * unit_price;
        item.sub_total = parseFloat(calculatedSubTotal.toFixed(2));
    } else if (field === 'sub_total') {
        if (quantity > 0) {
            const calculatedUnitPrice = sub_total / quantity;
            item.unit_price = parseFloat(calculatedUnitPrice.toFixed(2));
        } else {
            item.unit_price = sub_total;
        }
    }

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

    form.items = [...form.items];
};

// Add new line item
const addItem = () => {
    const newItem: LineItem = {
        id: nextItemId++,
        description: '',
        quantity: 1,
        unit_price: 0,
        sub_total: 0,
        economy_code_id: null,
        economy_code_item_id: null,
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

// Watch for Economic Code changes
const onEconomyCodeChange = (item: LineItem) => {
    item.economy_code_item_id = null;
};

// ✅ FIXED: File upload handlers
const onSelect = (event: any) => {
    console.log('📎 File selected:', event);
    
    // Get files from the event - handle both formats
    let newFiles = [];
    if (event.files) {
        newFiles = [...event.files];
    } else if (event.target && event.target.files) {
        newFiles = [...event.target.files];
    } else if (Array.isArray(event)) {
        newFiles = event;
    }
    
    if (newFiles.length === 0) {
        console.warn('No files found in selection event');
        return;
    }
    
    console.log('📎 Processing files:', newFiles.map((f: File) => f.name));
    validationErrors.value.documents = '';

    // Filter out duplicates
    const uniqueNewFiles = newFiles.filter(
        (newFile: File) => !allUploadedFiles.value.some(
            (existingFile) => existingFile.name === newFile.name && existingFile.size === newFile.size,
        ),
    );

    if (uniqueNewFiles.length !== newFiles.length) {
        toast.add({
            severity: 'warn',
            summary: 'Duplicate Files',
            detail: 'Some files were already uploaded and were skipped.',
            life: 3000,
        });
    }

    if (uniqueNewFiles.length === 0) return;

    // Process each file
    uniqueNewFiles.forEach((file: File) => {
        // Validate file size
        const maxSize = 10 * 1024 * 1024; // 10MB
        if (file.size > maxSize) {
            toast.add({
                severity: 'error',
                summary: 'File Too Large',
                detail: `${file.name} exceeds the 10MB limit.`,
                life: 5000,
            });
            return;
        }

        // Validate file type
        const allowedTypes = [
            'image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp',
            'application/pdf', 'application/msword',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'application/vnd.ms-excel',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ];
        
        if (!allowedTypes.includes(file.type)) {
            toast.add({
                severity: 'error',
                summary: 'Unsupported File Type',
                detail: `${file.name} has an unsupported format.`,
                life: 5000,
            });
            return;
        }

        // Add to uploaded files
        allUploadedFiles.value.push(file);

        // If no document type selected or 'other', add as optional
        if (!selectedDocumentType.value || selectedDocumentType.value === 'other') {
            optionalDocuments.value.push({
                type: 'other',
                label: 'Additional Document',
                file: file,
                document_type: 'other',
            });
            toast.add({
                severity: 'success',
                summary: 'Document Added',
                detail: `${file.name} uploaded as additional document.`,
                life: 3000,
            });
        } else {
            // Find existing required document of this type
            const requiredDoc = requiredDocuments.value.find(
                (doc) => doc.type === selectedDocumentType.value,
            );
            
            if (requiredDoc) {
                // If there's already a file for this document type, move it to optional
                if (requiredDoc.uploaded && requiredDoc.file) {
                    optionalDocuments.value.push({
                        type: 'other',
                        label: 'Replaced Document',
                        file: requiredDoc.file,
                        document_type: 'other',
                    });
                    // Remove from allUploadedFiles if not already removed
                    const oldFileIndex = allUploadedFiles.value.findIndex(
                        (f) => f.name === requiredDoc.file?.name,
                    );
                    if (oldFileIndex > -1) {
                        allUploadedFiles.value.splice(oldFileIndex, 1);
                    }
                }
                
                // Assign the new file to this document type
                requiredDoc.uploaded = true;
                requiredDoc.file = file;
                requiredDoc.existing_document_id = undefined; // Clear any existing document reference
                requiredDoc.existing_file_name = undefined;

                toast.add({
                    severity: 'success',
                    summary: 'Document Added',
                    detail: `${requiredDoc.label} uploaded successfully.`,
                    life: 3000,
                });
            } else {
                // If document type not found in required, add as optional
                optionalDocuments.value.push({
                    type: selectedDocumentType.value,
                    label: documentTypeOptions.find(opt => opt.value === selectedDocumentType.value)?.label || 'Document',
                    file: file,
                    document_type: selectedDocumentType.value,
                });
                toast.add({
                    severity: 'success',
                    summary: 'Document Added',
                    detail: `${file.name} uploaded as ${selectedDocumentType.value}.`,
                    life: 3000,
                });
            }
        }
    });

    // Update form documents
    form.documents = [...allUploadedFiles.value];
    
    // Clear selection for next upload
    selectedDocumentType.value = '';
    
    // If using fileUploadRef, clear it
    if (fileUploadRef.value) {
        fileUploadRef.value.clear();
    }
};

// ✅ FIXED: onRemove handler
const onRemove = (event: any) => {
    console.log('🗑️ File removed:', event);
    
    let fileToRemove = null;
    if (event.file) {
        fileToRemove = event.file;
    } else if (event.target && event.target.files) {
        fileToRemove = event.target.files[0];
    }

    if (!fileToRemove) {
        console.warn('No file to remove');
        return;
    }

    // Remove from required documents
    const requiredDoc = requiredDocuments.value.find(
        (doc) => doc.file && doc.file.name === fileToRemove.name && doc.file.size === fileToRemove.size
    );
    if (requiredDoc) {
        requiredDoc.uploaded = false;
        requiredDoc.file = undefined;
        requiredDoc.existing_document_id = undefined;
        requiredDoc.existing_file_name = undefined;
    }

    // Remove from optional documents
    const optionalDocIndex = optionalDocuments.value.findIndex(
        (doc) => doc.file.name === fileToRemove.name && doc.file.size === fileToRemove.size
    );
    if (optionalDocIndex > -1) {
        optionalDocuments.value.splice(optionalDocIndex, 1);
    }

    // Remove from all uploaded files
    const allFilesIndex = allUploadedFiles.value.findIndex(
        (file) => file.name === fileToRemove.name && file.size === fileToRemove.size
    );
    if (allFilesIndex > -1) {
        allUploadedFiles.value.splice(allFilesIndex, 1);
    }

    // Update form documents
    form.documents = [...allUploadedFiles.value];
    
    if (form.documents.length > 0) {
        validationErrors.value.documents = '';
    }
};

// ✅ FIXED: clearAllDocuments
const clearAllDocuments = () => {
    // Clear required documents
    requiredDocuments.value.forEach((doc) => {
        doc.uploaded = false;
        doc.file = undefined;
        doc.existing_document_id = undefined;
        doc.existing_file_name = undefined;
    });
    
    // Clear optional documents
    optionalDocuments.value = [];
    allUploadedFiles.value = [];
    form.documents = [];
    
    // Mark existing documents for deletion
    existingDocuments.value.forEach((doc) => {
        if (!documentsToDelete.value.includes(doc.id)) {
            documentsToDelete.value.push(doc.id);
        }
    });
    existingDocuments.value = [];
    form.documents_to_delete = documentsToDelete.value;
    
    validationErrors.value.documents = '';
    selectedDocumentType.value = '';
    
    // Clear file upload component
    if (fileUploadRef.value) {
        fileUploadRef.value.clear();
    }
    
    toast.add({
        severity: 'info',
        summary: 'Cleared',
        detail: 'All documents cleared.',
        life: 3000,
    });
};

const assignDocumentType = (file: File, documentType: string) => {
    if (documentType === 'other') return;

    const requiredDoc = requiredDocuments.value.find((doc) => doc.type === documentType);
    if (requiredDoc) {
        const optionalDocIndex = optionalDocuments.value.findIndex((doc) => doc.file.name === file.name);
        if (optionalDocIndex > -1) {
            optionalDocuments.value.splice(optionalDocIndex, 1);
        }

        if (requiredDoc.uploaded && requiredDoc.file) {
            optionalDocuments.value.push({
                type: 'other',
                label: 'Replaced Document',
                file: requiredDoc.file,
                document_type: 'other',
            });
        }

        requiredDoc.uploaded = true;
        requiredDoc.file = file;
        requiredDoc.existing_document_id = undefined;
        requiredDoc.existing_file_name = undefined;

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
            type: 'other',
            label: 'Additional Document',
            file: requiredDoc.file,
            document_type: 'other',
        });
        requiredDoc.uploaded = false;
        requiredDoc.file = undefined;
        requiredDoc.existing_document_id = undefined;
        requiredDoc.existing_file_name = undefined;
    }
};

const removeExistingDocument = (documentId: number) => {
    const docIndex = existingDocuments.value.findIndex((doc) => doc.id === documentId);
    if (docIndex > -1) {
        const document = existingDocuments.value[docIndex];
        const requiredDoc = requiredDocuments.value.find((rd) => rd.type === document.document_type);
        if (requiredDoc && requiredDoc.uploaded && requiredDoc.existing_document_id === documentId) {
            requiredDoc.uploaded = false;
            requiredDoc.existing_document_id = undefined;
            requiredDoc.existing_file_name = undefined;
        }
        existingDocuments.value.splice(docIndex, 1);
        documentsToDelete.value.push(documentId);
        form.documents_to_delete = documentsToDelete.value;
        toast.add({
            severity: 'info',
            summary: 'Document Removed',
            detail: 'Document will be deleted when you save changes',
            life: 3000,
        });
    }
};

const onUpload = (event: any) => {
    console.log('Upload event triggered:', event);
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
        voucher_type: '',
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
    if (!form.voucher_type || form.voucher_type === '') {
        validationErrors.value.voucher_type = 'Voucher type is required';
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
    if (!form.bank_activity_id || form.bank_activity_id < 1) {
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
    }

    return isValid;
};

// ✅ FIXED: Validate documents - properly checks existing documents
const validateDocuments = () => {
    validationErrors.value.documents = '';
    
    // If status is Draft, documents are optional
    if (form.status === 'Draft') {
        return true;
    }
    
    // If status is Submitted, validate documents
    if (form.status === 'Submitted') {
        // Get all document IDs from existing documents
        const existingDocIds = existingDocuments.value.map(doc => doc.id);
        const requiredDocIds = requiredDocuments.value
            .filter(doc => doc.existing_document_id)
            .map(doc => doc.existing_document_id);
        
        // Check if any required document exists (either uploaded or existing)
        const hasRequiredDocument = requiredDocuments.value.some(
            (doc) => {
                // Check if the document is required and either:
                // 1. Has a newly uploaded file
                // 2. Has an existing document ID that's still in the list
                if (!doc.required) return false;
                
                const hasNewFile = doc.uploaded && doc.file;
                const hasExisting = doc.existing_document_id && 
                    existingDocIds.includes(doc.existing_document_id);
                
                return hasNewFile || hasExisting;
            }
        );
        
        // Count total documents (existing + new uploads)
        const totalExisting = existingDocuments.value.length;
        const totalNewUploads = allUploadedFiles.value.length;
        const totalDocuments = totalExisting + totalNewUploads;
        
        console.log('🔍 Document validation debug:');
        console.log('  - Existing documents:', totalExisting);
        console.log('  - New uploads:', totalNewUploads);
        console.log('  - Total documents:', totalDocuments);
        console.log('  - Has required document:', hasRequiredDocument);
        console.log('  - Required docs details:', requiredDocuments.value.map(d => ({
            type: d.type,
            required: d.required,
            uploaded: d.uploaded,
            has_file: !!d.file,
            existing_id: d.existing_document_id,
            existing_file: d.existing_file_name
        })));
        
        // Validation rules:
        // 1. Must have at least one document (existing or new)
        if (totalDocuments === 0) {
            validationErrors.value.documents = 'At least one supporting document is required for submission. Please upload a document.';
            return false;
        }
        
        // 2. Must have at least one required document (Approval Memo)
        if (!hasRequiredDocument) {
            validationErrors.value.documents = 'At least one required document (e.g., Approval Memo) is required for submission.';
            return false;
        }
        
        return true;
    }

    return true;
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
                file_name: doc.file.name,
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
    
    const submitData = {
        ...form.data(),
        items: form.items.map((item) => ({
            id: item.id,
            description: item.description,
            economy_code_id: item.economy_code_id,
            economy_code_item_id: item.economy_code_item_id,
            programme_code_id: item.programme_code_id,
            programme_code: item.programme_code,
            programme_name: item.programme_name,
            budget_code: item.budget_code,
            quantity: item.quantity,
            unit_price: item.unit_price,
            sub_total: item.sub_total,
        })),
        document_types: documentTypesData,
        documents_to_delete: documentsToDelete.value,
    };

    form.post(`/vouchers/${props.voucher.id}`, {
        preserveScroll: true,
        onSuccess: () => {
            let message = 'Voucher updated successfully!';
            if (form.status === 'Draft') {
                message = 'Voucher saved as draft successfully!';
            } else if (form.status === 'Submitted') {
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

const showCustomConfirmation = ref(false);
const mdaLabel = computed(() => {
    return props.mdas.find((m: any) => m.value === form.mda_id)?.label || 'N/A';
});

const confirmationValidationErrors = ref<string[]>([]);

const selectedBankLabel = computed(() => {
    if (!form.bank_activity_id) return '';
    const selectedBank = lazyItemsBank.value.find((item: any) => item.value === form.bank_activity_id);
    return selectedBank?.label || 'Bank selected';
});

const validateConfirmation = () => {
    confirmationValidationErrors.value = [];
    if (!form.bank_activity_id || form.bank_activity_id < 1) {
        confirmationValidationErrors.value.push('Please select a destination bank before submitting.');
    }
    if (!voucherTotal.value || voucherTotal.value <= 0) {
        confirmationValidationErrors.value.push('Voucher amount must be greater than 0.');
    }
    return confirmationValidationErrors.value.length === 0;
};

const submitForApprovalWithConfirmation = () => {
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
    if (!validateConfirmation()) {
        showCustomConfirmation.value = true;
        return;
    }
    showCustomConfirmation.value = true;
};

const handleConfirmedSubmission = () => {
    if (!validateConfirmation()) {
        toast.add({
            severity: 'error',
            summary: 'Validation Error',
            detail: 'Please fix all validation errors before submitting.',
            life: 5000,
        });
        return;
    }
    showCustomConfirmation.value = false;
    submitVoucher();
};

watch(() => form.bank_activity_id, () => {
    if (showCustomConfirmation.value) {
        validateConfirmation();
    }
});

watch(voucherTotal, () => {
    if (showCustomConfirmation.value) {
        validateConfirmation();
    }
});

const validateForm = () => {
    const isHeaderValid = validateHeader();
    const areLineItemsValid = validateLineItems();
    if (form.status === 'Submitted') {
        const areDocumentsValid = validateDocuments();
        return isHeaderValid && areLineItemsValid && areDocumentsValid;
    }
    return isHeaderValid && areLineItemsValid;
};

// Auto-complete data fetching
const lazyItems: any = ref([]);
const loading = ref(false);
const currentPage = ref(0);
const filterValue = ref('');

const fetchData = async (page: number, filter = '') => {
    loading.value = true;
    try {
        const response = await axios.get(`/payeeList?page=${page}&filter=${filter}`);
        const newItems = response.data.data.map((item: { name: any; id: any }) => ({
            label: item.name,
            value: item.name,
        }));
        if (page === 1) {
            lazyItems.value = newItems;
        } else {
            lazyItems.value = [...lazyItems.value, ...newItems];
        }
        currentPage.value = page;
    } catch (error) {
        console.error('Error fetching data:', error);
    } finally {
        loading.value = false;
    }
};

const onFilter = (event: any) => {
    filterValue.value = event.value;
    fetchData(1, event.value);
};

// Bank activity data fetching
const lazyItemsBank: any = ref([]);
const loadingBank = ref(false);
const currentPageBank = ref(0);
const filterValueBank = ref('');

const fetchBankActivityData = async (page: number, filter = '') => {
    loadingBank.value = true;
    try {
        const response = await axios.get(`/bankActivityList?page=${page}&filter=${filter}`);
        const newItems = response.data.data.map((item: any) => ({
            label: `${item.tag} - ${item.bank_name} - ${item.title} - ${item.account_number}`,
            value: item.id,
        }));
        if (page === 1) {
            lazyItemsBank.value = newItems;
        } else {
            lazyItemsBank.value = [...lazyItemsBank.value, ...newItems];
        }
        currentPageBank.value = page;
    } catch (error) {
        console.error('Error fetching data:', error);
    } finally {
        loadingBank.value = false;
    }
};

const onFilterBank = (event: any) => {
    filterValueBank.value = event.value;
    fetchBankActivityData(1, event.value);
};

// ✅ Debug helper function
const debugDocuments = () => {
    console.log('--- DOCUMENT DEBUG ---');
    console.log('Existing documents:', existingDocuments.value);
    console.log('Required documents:', requiredDocuments.value);
    console.log('Optional documents:', optionalDocuments.value);
    console.log('All uploaded files:', allUploadedFiles.value);
    
    const hasRequired = requiredDocuments.value.some(
        (doc) => doc.required && (doc.uploaded || doc.existing_document_id)
    );
    console.log('Has required document:', hasRequired);
    console.log('Form status:', form.status);
    console.log('--- END DEBUG ---');
    
    const total = existingDocuments.value.length + allUploadedFiles.value.length;
    toast.add({
        severity: 'info',
        summary: 'Document Count',
        detail: `Total: ${total} documents (${existingDocuments.value.length} existing, ${allUploadedFiles.value.length} new)`,
        life: 5000,
    });
};

// Initialize
onMounted(() => {
    initializeFormItems();
    initializeRequiredDocuments();
    fetchData(1);
    fetchBankActivityData(1);
    searchProgrammeCodes('');
    
    // Debug after initialization
    setTimeout(() => {
        debugDocuments();
    }, 500);
});
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head :title="pageTitle" />
        <Toast />
        <ConfirmDialog />

        <Card class="voucher-card">
            <template #title>
                {{ pageTitle }}
                <span class="text-500 ml-3 text-sm">Editing Mode</span>
            </template>

            <template #content>
                <!-- Status Information -->
                <div class="mb-4">
                    <Message severity="info" :closable="false">
                        <div class="align-items-center flex gap-2">
                            <i class="pi pi-info-circle"></i>
                            <span>
                                <strong>Editing Voucher:</strong> You are
                                editing voucher {{ voucher.voucher_number }}.
                                Changes will be saved when you click "Update
                                Voucher".
                            </span>
                        </div>
                    </Message>
                </div>

                <!-- Error Messages -->
                <div v-if="Object.keys(form.errors).length > 0" class="mb-4">
                    <Message severity="error" :closable="false">
                        <div class="flex-column flex">
                            <div v-for="(error, field) in form.errors" :key="field" class="align-items-center flex gap-2">
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
                            <label for="year_id" class="text-500 mb-1 block text-sm font-semibold">Financial Year *</label>
                            <Dropdown id="year_id" v-model="form.year_id" :options="financialYears" optionLabel="label"
                                optionValue="value" placeholder="Select Financial Year" class="w-full"
                                :class="{ 'p-invalid': form.errors.year_id || validationErrors.year_id }"
                                @change="validationErrors.year_id = ''" />
                            <small v-if="validationErrors.year_id" class="p-error">{{ validationErrors.year_id }}</small>
                        </div>
                    </div>
                    <div class="col-4">
                        <div class="field">
                            <label for="mda_id" class="text-500 mb-1 block text-sm font-semibold">MDA *</label>
                            <Dropdown id="mda_id" v-model="form.mda_id" :options="mdas" optionLabel="label"
                                optionValue="value" placeholder="Select MDA" class="w-full" :filter="true"
                                filterPlaceholder="Search MDA..." :class="{ 'p-invalid': form.errors.mda_id || validationErrors.mda_id }"
                                @change="validationErrors.mda_id = ''" />
                            <small v-if="validationErrors.mda_id" class="p-error">{{ validationErrors.mda_id }}</small>
                        </div>
                    </div>
                    <div class="col-4">
                        <div class="field">
                            <label for="voucher_date" class="text-500 mb-1 block text-sm font-semibold">Voucher Date *</label>
                            <Calendar id="voucher_date" v-model="form.voucher_date" dateFormat="yy-mm-dd" class="w-full"
                                :class="{ 'p-invalid': form.errors.voucher_date || validationErrors.voucher_date }"
                                @date-select="validationErrors.voucher_date = ''" />
                            <small v-if="validationErrors.voucher_date" class="p-error">{{ validationErrors.voucher_date }}</small>
                        </div>
                    </div>
                </div>

                <!-- Voucher Type Display -->
                <div class="mb-4 grid">
                    <div class="col-4">
                        <div class="field">
                            <label class="text-500 mb-1 block text-sm font-semibold">Voucher Type</label>
                            <Select v-model="form.voucher_type" :options="voucherTypes" optionLabel="label"
                                optionValue="value" placeholder="Select Voucher Type" class="w-full"
                                :class="{ 'p-invalid': form.errors.voucher_type || validationErrors.voucher_type }"
                                @change="validationErrors.voucher_type = ''" />
                        </div>
                    </div>
                    <div class="col-4">
                        <div class="field">
                            <label class="text-500 mb-1 block text-sm font-semibold">Current Status</label>
                            <InputText :modelValue="voucher.status" class="w-full" disabled />
                        </div>
                    </div>
                    <div class="col-4">
                        <div class="field">
                            <label class="text-500 mb-1 block text-sm font-semibold">Voucher Number</label>
                            <InputText v-model="form.voucher_number" class="uppercase-input w-full"
                                :class="{ 'p-invalid': form.errors.voucher_number || validationErrors.voucher_number }"
                                @input="validationErrors.voucher_number = ''" />
                            <small v-if="validationErrors.voucher_number" class="p-error">{{ validationErrors.voucher_number }}</small>
                        </div>
                    </div>
                </div>

                <!-- Narration and Payee -->
                <div class="mb-4 grid">
                    <div class="col-6">
                        <div class="field">
                            <label for="narration" class="text-500 mb-1 block text-sm font-semibold">Narration *</label>
                            <Textarea id="narration" v-model="form.narration" rows="3" class="w-full"
                                placeholder="Enter voucher description or purpose..."
                                :class="{ 'p-invalid': form.errors.narration || validationErrors.narration }"
                                @input="validationErrors.narration = ''" />
                            <div class="justify-content-between mt-1 flex">
                                <small v-if="validationErrors.narration" class="p-error">{{ validationErrors.narration }}</small>
                                <small :class="form.narration.length > 500 ? 'p-error' : 'text-500'">{{ form.narration.length }}/500</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="field">
                            <label for="payee_name" class="text-500 mb-1 block text-sm font-semibold">Payee Name/Beneficiary Name *</label>
                            <Dropdown editable v-model="form.payee_name" :options="lazyItems" optionLabel="label"
                                optionValue="value" :loading="loading" placeholder="Select who is being paid"
                                filter @filter="onFilter" class="w-full" :class="{ 'p-invalid': validationErrors.payee_name }" />
                            <small v-if="validationErrors.payee_name" class="p-error">{{ validationErrors.payee_name }}</small>
                        </div>
                    </div>
                </div>

                <!-- Line Items Table -->
                <div class="mb-4">
                    <div class="justify-content-between align-items-center mb-3 flex">
                        <h4 class="m-0">Line Items</h4>
                        <div class="align-items-center flex gap-3">
                            <span class="text-500 text-sm" v-if="validationErrors.line_items">{{ validationErrors.line_items }}</span>
                            <Button label="Add Line Item" icon="pi pi-plus" severity="success" outlined @click="addItem()" />
                        </div>
                    </div>

                    <DataTable :value="form.items" class="p-datatable-sm" responsiveLayout="scroll" style="min-width: 1200px">
                        <Column field="description" header="Description" headerStyle="min-width: 250px" bodyStyle="min-width: 250px">
                            <template #body="slotProps">
                                <div class="flex-column flex">
                                    <Textarea 
                                        v-model="slotProps.data.description" 
                                        rows="2" 
                                        autoResize
                                        placeholder="Enter item description..." 
                                        class="w-full"
                                        :class="{ 'p-invalid': slotProps.data.errors?.description }"
                                        @input="clearFieldError(slotProps.data, 'description')" 
                                    />
                                    <small v-if="slotProps.data.errors?.description" class="p-error mt-1">{{ slotProps.data.errors.description }}</small>
                                </div>
                            </template>
                        </Column>

                        <!-- Economic Code Column -->
                        <Column field="economy_code_id" header="Economic Code" headerStyle="min-width: 180px" bodyStyle="min-width: 180px">
                            <template #body="slotProps">
                                <div class="flex-column flex">
                                    <Dropdown 
                                        v-model="slotProps.data.economy_code_id" 
                                        :options="economyCodeOptions"
                                        optionLabel="label" 
                                        optionValue="value" 
                                        placeholder="Select Code" 
                                        class="w-full"
                                        :filter="true" 
                                        filterPlaceholder="Search Economic Codes..." 
                                        :showClear="true"
                                        :class="{ 'p-invalid': slotProps.data.errors?.economy_code_id }"
                                        @change="onEconomyCodeChange(slotProps.data)" 
                                    />
                                    <small v-if="slotProps.data.errors?.economy_code_id" class="p-error mt-1">{{ slotProps.data.errors.economy_code_id }}</small>
                                </div>
                            </template>
                        </Column>

                        <!-- Economic Code Item Column -->
                        <Column field="economy_code_item_id" header="Code Item" headerStyle="min-width: 180px" bodyStyle="min-width: 180px">
                            <template #body="slotProps">
                                <div class="flex-column flex">
                                    <Dropdown 
                                        v-model="slotProps.data.economy_code_item_id"
                                        :options="getEconomyCodeItemOptions(slotProps.data.economy_code_id)"
                                        optionLabel="label" 
                                        optionValue="value" 
                                        placeholder="Select Item" 
                                        class="w-full"
                                        :disabled="!slotProps.data.economy_code_id" 
                                        :filter="true"
                                        filterPlaceholder="Search code items..." 
                                        :showClear="true"
                                        :class="{ 'p-invalid': slotProps.data.errors?.economy_code_item_id }" 
                                    />
                                    <small v-if="slotProps.data.errors?.economy_code_item_id" class="p-error mt-1">{{ slotProps.data.errors.economy_code_item_id }}</small>
                                    <small v-else-if="!slotProps.data.economy_code_id" class="text-500 mt-1">Select Code first</small>
                                </div>
                            </template>
                        </Column>

                        <!-- Programme Code Column -->
                        <Column field="programme_code_id" header="Programme Code" headerStyle="min-width: 220px" bodyStyle="min-width: 220px">
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
                                    <small v-if="slotProps.data.errors?.programme_code_id" class="p-error mt-1">{{ slotProps.data.errors.programme_code_id }}</small>
                                    <small v-else-if="slotProps.data.programme_code_id && selectedProgrammeCodeMap[slotProps.data.id]" class="text-500 mt-1">
                                        <i class="pi pi-info-circle mr-1"></i> Remaining: ₦{{ Number(selectedProgrammeCodeMap[slotProps.data.id].remaining_budget).toLocaleString() }}
                                    </small>
                                </div>
                            </template>
                        </Column>

                        <!-- Quantity Column -->
                        <Column field="quantity" header="Qty" headerStyle="min-width: 100px" bodyStyle="min-width: 100px">
                            <template #body="slotProps">
                                <div class="flex-column flex">
                                    <InputNumber 
                                        :modelValue="slotProps.data.quantity"
                                        @update:modelValue="handleQuantityChange(slotProps.data, $event)"
                                        :min="1" 
                                        :max-fraction-digits="2" 
                                        inputClass="w-full text-center"
                                        :class="{ 'p-invalid': slotProps.data.errors?.quantity }" 
                                    />
                                    <small v-if="slotProps.data.errors?.quantity" class="p-error mt-1">{{ slotProps.data.errors.quantity }}</small>
                                </div>
                            </template>
                        </Column>

                        <!-- Unit Price Column -->
                        <Column field="unit_price" header="Unit Price" headerStyle="min-width: 150px" bodyStyle="min-width: 150px">
                            <template #body="slotProps">
                                <div class="flex-column flex">
                                    <InputNumber 
                                        :modelValue="slotProps.data.unit_price"
                                        @update:modelValue="handleUnitPriceChange(slotProps.data, $event)"
                                        mode="currency" 
                                        currency="NGN" 
                                        locale="en-NG" 
                                        :min="0" 
                                        inputClass="w-full text-right"
                                        :class="{ 'p-invalid': slotProps.data.errors?.unit_price }" 
                                    />
                                    <small v-if="slotProps.data.errors?.unit_price" class="p-error mt-1">{{ slotProps.data.errors.unit_price }}</small>
                                </div>
                            </template>
                        </Column>

                        <!-- Sub Total Column -->
                        <Column field="sub_total" header="Sub Total" headerStyle="min-width: 150px" bodyStyle="min-width: 150px" bodyClass="font-bold text-right">
                            <template #body="slotProps">
                                <div class="flex-column flex">
                                    <InputNumber 
                                        :modelValue="slotProps.data.sub_total"
                                        @update:modelValue="handleSubTotalChange(slotProps.data, $event)"
                                        mode="currency" 
                                        currency="NGN" 
                                        locale="en-NG" 
                                        :min="0" 
                                        inputClass="w-full text-right"
                                        :class="{ 'p-invalid': slotProps.data.errors?.sub_total }" 
                                    />
                                    <small v-if="slotProps.data.errors?.sub_total" class="p-error mt-1">{{ slotProps.data.errors.sub_total }}</small>
                                </div>
                            </template>
                        </Column>

                        <!-- Actions Column -->
                        <Column header="Actions" headerStyle="min-width: 100px" bodyStyle="min-width: 80px" bodyClass="text-center">
                            <template #body="slotProps">
                                <Button 
                                    icon="pi pi-trash" 
                                    severity="danger" 
                                    text 
                                    rounded
                                    :disabled="form.items.length === 1" 
                                    @click="deleteItem(slotProps.data.id)" 
                                />
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
                        <div class="field-group">
                            <div class="justify-content-between align-items-center mb-3 flex">
                                <h4 class="m-0">Supporting Documents</h4>
                                <div class="flex gap-2">
                                    <Button 
                                        v-if="allUploadedFiles.length > 0 || existingDocuments.length > 0"
                                        label="Clear All" 
                                        icon="pi pi-times" 
                                        severity="secondary" 
                                        text 
                                        @click="clearAllDocuments" 
                                    />
                                    <Button 
                                        label="Debug Docs" 
                                        icon="pi pi-bug" 
                                        severity="info" 
                                        text 
                                        @click="debugDocuments" 
                                    />
                                </div>
                            </div>

                            <!-- ✅ Required Documents Status -->
                            <div class="mb-4">
                                <h5 class="mb-2">Required Documents:</h5>
                                <div class="grid">
                                    <div v-for="doc in requiredDocuments" :key="doc.type" class="col-12 mb-2">
                                        <div class="surface-100 border-round border-1 p-3">
                                            <div class="align-items-center justify-content-between mb-1 flex">
                                                <div class="align-items-center flex gap-2">
                                                    <i :class="(doc.uploaded || doc.existing_document_id)
                                                        ? 'pi pi-check-circle text-green-500' 
                                                        : doc.required ? 'pi pi-exclamation-circle text-red-500' : 'pi pi-circle text-gray-400'
                                                    "></i>
                                                    <span :class="(doc.uploaded || doc.existing_document_id)
                                                        ? 'text-700 font-semibold' 
                                                        : 'text-500'
                                                    ">
                                                        {{ doc.label }}
                                                        <span v-if="doc.required" class="text-red-500 text-sm">*</span>
                                                    </span>
                                                    <Badge v-if="doc.required && !(doc.uploaded || doc.existing_document_id)" 
                                                        value="Required" severity="warning" size="small" />
                                                    <Badge v-else-if="(doc.uploaded || doc.existing_document_id)" 
                                                        value="Uploaded" severity="success" size="small" />
                                                </div>
                                                <div class="flex gap-1">
                                                    <Button v-if="doc.existing_document_id" 
                                                        icon="pi pi-eye" 
                                                        severity="info" 
                                                        text 
                                                        rounded 
                                                        size="small"
                                                        @click="viewDocument(existingDocuments.find(d => d.id === doc.existing_document_id), 'existing')" 
                                                        title="View document" 
                                                    />
                                                    <Button v-if="doc.existing_document_id" 
                                                        icon="pi pi-times" 
                                                        severity="danger" 
                                                        text 
                                                        rounded 
                                                        size="small"
                                                        @click="removeExistingDocument(doc.existing_document_id)" 
                                                        title="Remove document" 
                                                    />
                                                    <Button v-if="doc.uploaded && doc.file && !doc.existing_document_id"
                                                        icon="pi pi-eye"
                                                        severity="info"
                                                        text
                                                        rounded
                                                        size="small"
                                                        @click="viewDocument(doc, 'required')"
                                                        title="View uploaded document"
                                                    />
                                                    <Button v-if="doc.uploaded && doc.file && !doc.existing_document_id"
                                                        icon="pi pi-times"
                                                        severity="danger"
                                                        text
                                                        rounded
                                                        size="small"
                                                        @click="() => {
                                                            const removeEvent = { file: doc.file };
                                                            onRemove(removeEvent);
                                                        }"
                                                        title="Remove uploaded file"
                                                    />
                                                </div>
                                            </div>
                                            <div v-if="doc.existing_document_id" class="mt-1">
                                                <small class="text-500 block">
                                                    <i class="pi pi-file mr-1"></i>
                                                    {{ existingDocuments.find(d => d.id === doc.existing_document_id)?.file_name || 'Existing file' }}
                                                </small>
                                            </div>
                                            <div v-if="doc.uploaded && doc.file && !doc.existing_document_id" class="mt-1">
                                                <small class="text-500 block">
                                                    <i class="pi pi-file mr-1"></i>
                                                    {{ doc.file.name }}
                                                </small>
                                            </div>
                                            <div v-else-if="!doc.uploaded && !doc.existing_document_id && doc.required" class="mt-1">
                                                <small class="text-red-500">Required for submission</small>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="surface-50 border-round mb-4 p-3">
                                <h5 class="mt-0 mb-2">Document Type Selection</h5>
                                <div class="align-items-end flex gap-2">
                                    <div class="flex-1">
                                        <label class="text-500 mb-1 block text-sm font-semibold">Select document type before uploading:</label>
                                        <Dropdown v-model="selectedDocumentType" :options="documentTypeOptions"
                                            optionLabel="label" optionValue="value" placeholder="Choose document type..." class="w-full" />
                                    </div>
                                </div>
                            </div>

                            <div class="mb-2" v-if="validationErrors.documents">
                                <Message severity="error" :closable="false">{{ validationErrors.documents }}</Message>
                            </div>

                            <!-- ✅ FIXED: FileUpload with proper ref -->
                            <FileUpload 
                                ref="fileUploadRef"
                                mode="advanced" 
                                name="documents" 
                                :multiple="true" 
                                :maxFileSize="10000000"
                                accept="image/*,.pdf,.doc,.docx,.xls,.xlsx" 
                                chooseLabel="Attach Documents"
                                uploadLabel="Upload" 
                                cancelLabel="Cancel" 
                                @select="onSelect" 
                                @remove="onRemove"
                                @clear="clearAllDocuments"
                                @upload="onUpload" 
                                :auto="false" 
                                :customUpload="true" 
                                :disabled="form.processing"
                                :class="{ 'p-invalid': validationErrors.documents }"
                            >
                                <template #empty>
                                    <p class="text-500">Drag and drop files here or click to browse</p>
                                    <small class="text-500">Supported formats: Images, PDF, Word, Excel (Max: 10MB per file)</small>
                                    <div class="mt-2" v-if="!selectedDocumentType">
                                        <Message severity="warn" :closable="false" class="text-sm">
                                            <div class="align-items-center flex gap-2">
                                                <i class="pi pi-exclamation-triangle"></i>
                                                <span>Please select a document type above before uploading</span>
                                            </div>
                                        </Message>
                                    </div>
                                    <div v-else class="mt-2">
                                        <Message severity="info" :closable="false" class="text-sm">
                                            <div class="align-items-center flex gap-2">
                                                <i class="pi pi-info-circle"></i>
                                                <span>Uploading as: <strong>{{ documentTypeOptions.find(opt => opt.value === selectedDocumentType)?.label || selectedDocumentType }}</strong></span>
                                            </div>
                                        </Message>
                                    </div>
                                    <div class="mt-1">
                                        <small class="text-500">Max file size: 10MB per file</small>
                                    </div>
                                </template>
                            </FileUpload>

                            <!-- ✅ FIXED: Uploaded Files Display -->
                            <div v-if="allUploadedFiles.length > 0 || existingDocuments.length > 0" class="mt-4">
                                <h5 class="mb-2">Uploaded Files ({{ allUploadedFiles.length + existingDocuments.length }}):</h5>
                                
                                <!-- Existing Documents -->
                                <div v-if="existingDocuments.length > 0" class="mb-3">
                                    <h6 class="mb-2 text-blue-600">Existing Documents:</h6>
                                    <ul class="m-0 list-none p-0">
                                        <li v-for="doc in existingDocuments" :key="doc.id"
                                            class="align-items-center justify-content-between surface-50 border-round mb-2 flex p-2">
                                            <div class="align-items-center flex">
                                                <i :class="getDocumentIcon(doc.mime_type, doc.file_name)" class="mr-2"></i>
                                                <div>
                                                    <span class="font-medium">{{ doc.file_name }}</span>
                                                    <small class="text-500 block">{{ doc.document_label }} • {{ (doc.file_size / 1024).toFixed(2) }} KB</small>
                                                </div>
                                            </div>
                                            <div class="align-items-center flex gap-2">
                                                <Button icon="pi pi-eye" severity="info" text rounded @click="viewDocument(doc, 'existing')" title="View document" />
                                                <Button icon="pi pi-times" severity="danger" text rounded @click="removeExistingDocument(doc.id)" title="Remove document" />
                                            </div>
                                        </li>
                                    </ul>
                                </div>

                                <!-- Newly Uploaded Documents -->
                                <div v-if="allUploadedFiles.length > 0">
                                    <h6 class="mb-2 text-green-600">Newly Uploaded:</h6>
                                    <ul class="m-0 list-none p-0">
                                        <li v-for="file in allUploadedFiles" :key="file.name + file.size"
                                            class="align-items-center justify-content-between surface-50 border-round mb-2 flex p-2">
                                            <div class="align-items-center flex">
                                                <i :class="getDocumentIcon(file.type, file.name)" class="mr-2"></i>
                                                <div>
                                                    <span class="font-medium">{{ file.name }}</span>
                                                    <small class="text-500 block">{{ (file.size / 1024).toFixed(2) }} KB</small>
                                                </div>
                                            </div>
                                            <div class="align-items-center flex gap-2">
                                                <Button icon="pi pi-eye" severity="info" text rounded 
                                                    @click="() => {
                                                        const requiredDoc = requiredDocuments.value.find(d => d.file === file);
                                                        if (requiredDoc) {
                                                            viewDocument(requiredDoc, 'required');
                                                        }
                                                    }" 
                                                    title="View file" 
                                                />
                                                <Button icon="pi pi-times" severity="danger" text rounded 
                                                    @click="() => {
                                                        const removeEvent = { file: file };
                                                        onRemove(removeEvent);
                                                    }" 
                                                    title="Remove file" 
                                                />
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

                            <div v-if="scheduleInfo" class="surface-50 border-round mb-3 p-3">
                                <div class="justify-content-between align-items-center flex">
                                    <span class="text-500 font-semibold">Schedule Total:</span>
                                    <span class="text-primary font-bold">{{ formatCurrency(scheduleTotal) }}</span>
                                </div>
                            </div>

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

                        <div class="mt-4">
                            <label for="bank_activity_id" class="text-500 mb-1 block text-sm font-semibold">Select destination bank *</label>
                            <Dropdown id="bank_activity_id" v-model="form.bank_activity_id" :options="lazyItemsBank"
                                optionLabel="label" optionValue="value" :loading="loadingBank" placeholder="Select destination bank"
                                filter @filter="onFilterBank" class="w-full"
                                :class="{ 'p-invalid': form.errors?.bank_activity_id || validationErrors.bank_activity_id }" />
                            <small v-if="validationErrors.bank_activity_id" class="p-error">{{ validationErrors.bank_activity_id }}</small>
                            <small v-if="selectedBankLabel" class="text-green-600">✓ Bank selected</small>
                        </div>
                    </div>
                </div>

                <!-- Action Buttons -->
                <div class="justify-content-end mt-5 flex gap-2">
                    <Button label="Update Draft" icon="pi pi-save" severity="secondary" :loading="form.processing"
                        @click="saveDraft" title="Update as draft (documents optional, can edit later)" />
                    <Button label="Submit for Approval" icon="pi pi-send" severity="success" :loading="form.processing"
                        @click="submitForApprovalWithConfirmation" title="Submit for approval to Internal Audit" />
                </div>
            </template>
        </Card>

        <!-- Document Viewer Dialog -->
        <Dialog v-model:visible="documentViewerVisible" :style="{ width: '90vw', maxWidth: '1200px' }"
            :maximizable="true" modal :header="documentViewerTitle" @hide="closeDocumentViewer">
            <div v-if="currentDocument" class="document-viewer">
                <div class="justify-content-between align-items-center mb-3 flex">
                    <div class="align-items-center flex gap-2">
                        <i :class="getDocumentIcon(currentDocument.type, currentDocument.name)" class="text-primary"></i>
                        <span class="font-semibold">{{ currentDocument.name }}</span>
                    </div>
                    <div class="flex gap-2">
                        <Button v-if="isViewable(currentDocument.type) && currentDocument.url" icon="pi pi-external-link"
                            label="Open in New Tab" severity="secondary" @click="window.open(currentDocument.url, '_blank')" />
                        <Button icon="pi pi-download" label="Download" severity="info" @click="downloadDocument" />
                    </div>
                </div>
                <div class="document-content border-round surface-50 p-3" style="min-height: 400px; max-height: 70vh">
                    <div v-if="!currentDocument.url" class="flex-column align-items-center justify-content-center flex h-full text-center">
                        <i class="pi pi-spin pi-spinner text-500 mb-3 text-6xl"></i>
                        <h4 class="text-900 mb-2">Loading Document...</h4>
                    </div>
                    <div v-else-if="currentDocument.type === 'application/pdf'" class="h-full w-full">
                        <iframe :src="currentDocument.url" class="h-full w-full border-none" style="min-height: 400px" frameborder="0"></iframe>
                    </div>
                    <div v-else-if="currentDocument.type.startsWith('image/')" class="justify-content-center flex">
                        <img :src="currentDocument.url" :alt="currentDocument.name" class="max-h-full max-w-full" style="max-height: 70vh; object-fit: contain" />
                    </div>
                    <div v-else class="flex-column align-items-center justify-content-center flex h-full text-center">
                        <i class="pi pi-file text-500 mb-3 text-6xl"></i>
                        <h4 class="text-900 mb-2">Preview Not Available</h4>
                        <Button icon="pi pi-download" label="Download to View" severity="info" @click="downloadDocument" />
                    </div>
                </div>
            </div>
        </Dialog>

        <!-- Custom Confirmation Dialog -->
        <Dialog v-model:visible="showCustomConfirmation" :style="{ width: '500px' }" header="Voucher Submission Confirmation" :modal="true">
            <div class="confirmation-content">
                <div class="mb-4">
                    <div class="mb-2 text-xl font-semibold text-gray-900">Submit Voucher for Approval</div>
                    <div class="text-sm text-gray-600">Confirm submission of voucher for approval processing</div>
                </div>
                <div v-if="confirmationValidationErrors.length > 0" class="mb-4">
                    <Message severity="error" :closable="false">
                        <div class="flex-column flex">
                            <div v-for="error in confirmationValidationErrors" :key="error" class="align-items-center flex gap-2">
                                <i class="pi pi-exclamation-circle"></i><span>{{ error }}</span>
                            </div>
                        </div>
                    </Message>
                </div>
                <div class="surface-50 border-round mb-4 p-4">
                    <div class="grid">
                        <div class="col-6">
                            <div class="mb-3">
                                <div class="text-500 mb-1 text-xs font-medium">Voucher Number</div>
                                <div class="text-primary text-sm font-semibold">{{ props.voucher.voucher_number }}</div>
                            </div>
                            <div class="mb-3">
                                <div class="text-500 mb-1 text-xs font-medium">Amount</div>
                                <div class="text-sm font-semibold">{{ formatCurrency(voucherTotal) }}</div>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="mb-3">
                                <div class="text-500 mb-1 text-xs font-medium">Payee Name</div>
                                <div class="text-sm font-semibold">{{ form.payee_name }}</div>
                            </div>
                            <div class="mb-3">
                                <div class="text-500 mb-1 text-xs font-medium">MDA</div>
                                <div class="text-sm">{{ mdaLabel }}</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <template #footer>
                <Button label="Cancel" icon="pi pi-times" @click="showCustomConfirmation = false" class="p-button-text" />
                <Button label="Submit for Approval" icon="pi pi-check" @click="handleConfirmedSubmission" autofocus
                    class="p-button-success" :disabled="confirmationValidationErrors.length > 0" />
            </template>
        </Dialog>
    </AppLayout>
</template>

<style scoped>
.voucher-card { min-height: 100vh; }
.field-group h4 { margin: 0 0 0.5rem 0; font-size: 1rem; }
.totals-section { background: var(--p-surface-50); padding: 1rem; border-radius: 6px; border: 1px solid var(--p-surface-200); }
.total-row { padding: 0.25rem 0; }
.hidden { display: none; }
:deep(.p-datatable) { border: 1px solid var(--p-surface-200); border-radius: 6px; width: 100%; overflow-x: auto; }
:deep(.p-datatable-thead > tr > th) { background: var(--p-surface-100); font-weight: 600; }
:deep(.p-invalid) { border-color: var(--p-error-color) !important; }
.p-error { color: var(--p-error-color); font-size: 0.875rem; }
.text-green-500 { color: #22c55e; }
.text-red-500 { color: #ef4444; }
.text-green-600 { color: #16a34a; }
.text-blue-600 { color: #2563eb; }
.uppercase-input { text-transform: uppercase; }
.document-viewer { min-height: 500px; }
.document-content { background: var(--p-surface-0); border: 1px solid var(--p-surface-200); }
</style>