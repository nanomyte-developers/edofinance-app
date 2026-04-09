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
    errors?: {
        description?: string;
        economy_code_id?: string;
        economy_code_item_id?: string;
        quantity?: string;
        unit_price?: string;
        sub_total?: string;
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

// Props from Laravel controller
const props = defineProps({
    voucherType: {
        type: String,
        required: true,
        default: 'standard',
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

// Document management - Only one required document is compulsory
const requiredDocuments = ref<RequiredDocument[]>([
    {
        type: 'approval_memo',
        label: 'Approval Memo',
        required: true, // This one is compulsory
        uploaded: false,
    },

    // {
    //     type: 'invoice',
    //     label: 'Invoice',
    //     required: false, // Changed to optional
    //     uploaded: false,
    // },
    // {
    //     type: 'receipt',
    //     label: 'Receipt',
    //     required: false, // Changed to optional
    //     uploaded: false,
    // },
    // {
    //     type: 'Delivery Note',
    //     label: 'Delivery Note',
    //     required: false, // Changed to optional
    //     uploaded: false,
    // },
]);

const optionalDocuments = ref<UploadedDocument[]>([]);
const selectedDocumentType = ref<string>(''); // For the dropdown
const fileUploadRef = ref(); // Ref for FileUpload component

// Economic Code Options
const economyCodeOptions = computed(() => {
    return props.economyCodes;
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

    return props.economyCodeItems.filter((item: any) => {
        return item.economy_code_id === economyCodeId;
    });
};

// Inertia form setup
const form = useForm({
    schedule_id: props.schedule?.id || null, // This should already be there
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

// Add computed property to show retirement info for prepayment vouchers
// const isPrepaymentVoucher = computed(() => {
//     return props.voucherType === 'prepayment';
// });

// const retirementInfo = computed(() => {
//     if (!isPrepaymentVoucher.value) return null;

//     return {
//         requires_retirement: true,
//         message:
//             'This is a prepayment voucher and will require retirement after approval.',
//     };
// });

// NEW: Auto-select MDA and Financial Year based on schedule
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

// NEW: Generate narration from schedule
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

// NEW: Computed property to check if voucher total matches schedule total
const voucherTotalMatchesSchedule = computed(() => {
    if (!props.schedule?.total_amount) return true;
    return Math.abs(voucherTotal.value - props.schedule.total_amount) < 0.01;
});

// NEW: Computed property for schedule total
const scheduleTotal = computed(() => {
    return props.schedule?.total_amount || 0;
});

// Fixed Number to words converter
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

        // Hundreds
        if (num >= 100) {
            result += units[Math.floor(num / 100)] + ' Hundred ';
            num %= 100;
        }

        // Tens and units
        if (num >= 20) {
            result += tens[Math.floor(num / 10)] + ' ';
            num %= 10;
        } else if (num >= 10) {
            result += teens[num - 10] + ' ';
            num = 0;
        }

        // Units
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

    // Millions
    if (nairaAmount >= 1000000) {
        words +=
            convertHundreds(Math.floor(nairaAmount / 1000000)) + ' Million ';
        nairaAmount %= 1000000;
    }

    // Thousands
    if (nairaAmount >= 1000) {
        words += convertHundreds(Math.floor(nairaAmount / 1000)) + ' Thousand ';
        nairaAmount %= 1000;
    }

    // Hundreds
    if (nairaAmount > 0) {
        words += convertHundreds(nairaAmount) + ' ';
    }

    words = words.trim();
    words += words ? 'Naira' : 'Zero Naira';

    // Convert Kobo part
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

// Computed property for amount in words
const amountInWords = computed(() => {
    return convertNumberToWords(voucherTotal.value);
});

// NEW: Computed property to show schedule info
const scheduleInfo = computed(() => {
    if (!props.schedule) return null;

    return {
        schedule_number: props.schedule.schedule_number,
        mda: props.schedule.mda?.name,
        budget_code: props.schedule.budget_code,
        total_amount: props.schedule.total_amount,
    };
});

// Debug helper
const documentStats = computed(() => {
    return {
        formDocuments: form.documents.length,
        requiredDocuments: requiredDocuments.value.filter((d) => d.uploaded)
            .length,
        optionalDocuments: optionalDocuments.value.length,
        totalUniqueFiles: new Set([
            ...form.documents.map((f) => f.name),
            ...requiredDocuments.value
                .filter((d) => d.uploaded)
                .map((d) => d.file?.name)
                .filter(Boolean),
            ...optionalDocuments.value.map((d) => d.file.name),
        ]).size,
    };
});

// Log changes
watch(
    documentStats,
    (newStats) => {
        console.log('Document Stats Updated:', newStats);
    },
    { deep: true },
);

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
            validationErrors.value.voucher_date =
                'Voucher date cannot be in the future';
            isValid = false;
        }
    }

    if (!form.narration.trim()) {
        validationErrors.value.narration = 'Narration is required';
        isValid = false;
    } else if (form.narration.length < 10) {
        validationErrors.value.narration =
            'Narration must be at least 10 characters';
        isValid = false;
    } else if (form.narration.length > 500) {
        validationErrors.value.narration =
            'Narration cannot exceed 500 characters';
        isValid = false;
    }
    if (form.voucher_number.length < 5) {
        console.log(form.voucher_number.length);
        validationErrors.value.voucher_number = 'Voucher number is required';
        isValid = false;
    }

    if (!form.payee_name.trim()) {
        validationErrors.value.payee_name = 'Payee name is required';
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
        validationErrors.value.line_items =
            'At least one line item is required';
        return false;
    }

    form.items.forEach((item) => {
        const itemErrors: any = {};

        if (!item.description?.trim()) {
            itemErrors.description = 'Description is required';
            isValid = false;
        } else if (item.description.length < 3) {
            itemErrors.description =
                'Description must be at least 3 characters';
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

        if (!item.economy_code_item_id) {
            itemErrors.economy_code_item_id = 'Economic Code item is required';
            isValid = false;
        }

        if (!item.quantity || item.quantity <= 0) {
            itemErrors.quantity = 'Quantity must be greater than 0';
            isValid = false;
        // } else if (item.quantity > 999999) {
        //     itemErrors.quantity = 'Quantity is too large';
        //     isValid = false;
        }

        if (!item.unit_price && item.unit_price !== 0) {
            itemErrors.unit_price = 'Unit price is required';
            isValid = false;
        } else if (item.unit_price < 0) {
            itemErrors.unit_price = 'Unit price cannot be negative';
            isValid = false;
        // } else if (item.unit_price > 99999999.99) {
        //     itemErrors.unit_price = 'Unit price is too large';
        //     isValid = false;
        }

        if (!item.sub_total && item.sub_total !== 0) {
            itemErrors.sub_total = 'Sub total is required';
            isValid = false;
        } else if (item.sub_total < 0) {
            itemErrors.sub_total = 'Sub total cannot be negative';
            isValid = false;
        // } else if (item.sub_total > 99999999.99) {
        //     itemErrors.sub_total = 'Sub total is too large';
        //     isValid = false;
        }

        const calculatedSubTotal = item.quantity * item.unit_price;
        const subTotalDifference = Math.abs(
            item.sub_total - calculatedSubTotal,
        );
        if (subTotalDifference > 0.01) {
            itemErrors.sub_total =
                'Sub total does not match quantity × unit price';
            isValid = false;
        }

        if (Object.keys(itemErrors).length > 0) {
            item.errors = itemErrors;
        }
    });

    if (voucherTotal.value <= 0) {
        validationErrors.value.line_items =
            'Total voucher amount must be greater than 0';
        isValid = false;
    }

    // NEW: Validate that voucher total matches schedule total
    // if (props.schedule?.total_amount && !voucherTotalMatchesSchedule.value) {
    //     validationErrors.value.line_items = `Voucher total (${formatCurrency(voucherTotal.value)}) must match schedule total (${formatCurrency(props.schedule.total_amount)})`;
    //     isValid = false;
    // }

    return isValid;
};

// Enhanced document validation - Only require at least one document for submission
const validateDocuments = () => {
    validationErrors.value.documents = '';

    // For draft status, documents are optional
    if (form.status === 'Draft') {
        return true;
    }

    // For submission, require at least ONE document (any type)
    if (form.status === 'Submitted') {
        const totalUploadedDocuments =
            requiredDocuments.value.filter((doc) => doc.uploaded).length +
            optionalDocuments.value.length;

        if (totalUploadedDocuments === 0) {
            validationErrors.value.documents =
                'At least one supporting document is required for submission';
            return false;
        }

        // Optional: You can make specific documents required if needed
        // For example, if you want at least one of the required documents:
        const hasAnyRequiredDocument = requiredDocuments.value.some(
            (doc) => doc.uploaded,
        );
        if (!hasAnyRequiredDocument) {
            validationErrors.value.documents =
                'At least one supporting document (Approval Memo, Release Warrant, Exco Approval/Conclusion, Ministerial Tender Board, State Tenders Board, Certificate Of Incorporation, Tax Clearance, Tax Identification Number (TIN), Procurement Registration Certificate, Advance Payment Guarantee (APG), Invoice, Receipt, or Delivery Note) is required';
            return false;
        }
    }

    // Validate file sizes and types for all uploaded documents
    const maxFileSize = 10 * 1024 * 1024;
    const allowedTypes = [
        'image/jpeg',
        'image/jpg',
        'image/png',
        'image/gif',
        'image/webp',
        'application/pdf',
        'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'application/vnd.ms-excel',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
    ];

    // Check all uploaded files
    const allFiles = [
        ...requiredDocuments.value
            .filter((doc) => doc.uploaded && doc.file)
            .map((doc) => doc.file!),
        ...optionalDocuments.value.map((doc) => doc.file),
        ...form.documents,
    ];

    for (const file of allFiles) {
        if (file.size > maxFileSize) {
            validationErrors.value.documents = `File "${file.name}" exceeds the 10MB size limit`;
            return false;
        }

        if (!allowedTypes.includes(file.type)) {
            validationErrors.value.documents = `File "${file.name}" has an unsupported format. Allowed formats: Images, PDF, Word, Excel`;
            return false;
        }
    }

    return true;
};

// Methods
const formatCurrency = (value: number) => {
    return new Intl.NumberFormat('en-NG', {
        style: 'currency',
        currency: 'NGN',
    }).format(value);
};

// Update line item sub_total
const updateItemSubTotal = (
    item: LineItem,
    field: 'quantity' | 'unit_price' | 'sub_total',
) => {
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
    };
    form.items.push(newItem);
    validationErrors.value.line_items = '';
};

// Delete line item
const deleteItem = (id: number) => {
    if (form.items.length > 1) {
        form.items = form.items.filter((item) => item.id !== id);
        form.total_amount = voucherTotal.value;
    }
};

// Watch for Economic Code changes to reset Economic Code item
const onEconomyCodeChange = (item: LineItem) => {
    item.economy_code_item_id = null; // Reset the item when parent code changes
};

// FIXED: File upload handler - prevents duplicate uploads
const isFileAlreadyUploaded = (file: File) => {
    // Check required documents
    const inRequired = requiredDocuments.value.some(
        (doc) =>
            doc.file &&
            doc.file.name === file.name &&
            doc.file.size === file.size,
    );

    // Check optional documents
    const inOptional = optionalDocuments.value.some(
        (doc) => doc.file.name === file.name && doc.file.size === file.size,
    );

    // Check form documents
    const inForm = form.documents.some(
        (f) => f.name === file.name && f.size === file.size,
    );

    return inRequired || inOptional || inForm;
};

const onSelect = (event: any) => {
    const newFiles = [...event.files];
    validationErrors.value.documents = '';

    console.log(
        'Files selected:',
        newFiles.map((f) => f.name),
    );
    console.log('Selected document type:', selectedDocumentType.value);

    // Filter out duplicates
    const uniqueNewFiles = newFiles.filter(
        (file) => !isFileAlreadyUploaded(file),
    );

    if (uniqueNewFiles.length !== newFiles.length) {
        toast.add({
            severity: 'warn',
            summary: 'Duplicate Files',
            detail: 'Some files were already uploaded and were skipped.',
            life: 3000,
        });
    }

    if (uniqueNewFiles.length === 0) {
        // Clear the FileUpload component
        if (fileUploadRef.value) {
            fileUploadRef.value.clear();
        }
        return;
    }

    // Clear the FileUpload component to prevent duplicates
    if (fileUploadRef.value) {
        fileUploadRef.value.clear();
    }

    // Process unique files
    uniqueNewFiles.forEach((file) => {
        if (
            selectedDocumentType.value &&
            selectedDocumentType.value !== 'Other'
        ) {
            // Assign to required document
            const requiredDoc = requiredDocuments.value.find(
                (doc) => doc.type === selectedDocumentType.value,
            );

            if (requiredDoc) {
                // Remove any existing file for this document type
                if (requiredDoc.uploaded && requiredDoc.file) {
                    // Remove old file from form.documents
                    form.documents = form.documents.filter(
                        (f) => f !== requiredDoc.file,
                    );
                }

                requiredDoc.uploaded = true;
                requiredDoc.file = file;

                // Add to form.documents if not already there
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
            // Add as optional document
            const existingOptionalDoc = optionalDocuments.value.find(
                (doc) =>
                    doc.file.name === file.name && doc.file.size === file.size,
            );

            if (!existingOptionalDoc) {
                optionalDocuments.value.push({
                    type: 'Other',
                    label: 'Additional Document',
                    file: file,
                    document_type: 'Other',
                });

                // Add to form.documents if not already there
                if (!form.documents.includes(file)) {
                    form.documents.push(file);
                }
            }
        }
    });

    // Reset document type selection
    selectedDocumentType.value = '';
};

// FIXED: Manual document type assignment for already uploaded files
const assignDocumentType = (file: File, documentType: string) => {
    if (documentType === 'Other') return; // Don't reassign optional documents

    const requiredDoc = requiredDocuments.value.find(
        (doc) => doc.type === documentType,
    );
    if (requiredDoc) {
        // Remove from optional documents if it's there
        const optionalDocIndex = optionalDocuments.value.findIndex(
            (doc) => doc.file.name === file.name,
        );
        if (optionalDocIndex > -1) {
            optionalDocuments.value.splice(optionalDocIndex, 1);
        }

        if (requiredDoc.uploaded && requiredDoc.file) {
            // Move existing required document file to optional
            optionalDocuments.value.push({
                type: 'Other',
                label: 'Replaced Document',
                file: requiredDoc.file,
                document_type: 'Other',
            });

            // Remove old file from form.documents
            form.documents = form.documents.filter(
                (f) => f !== requiredDoc.file,
            );
        }

        requiredDoc.uploaded = true;
        requiredDoc.file = file;

        // Ensure file is in form.documents
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

// FIXED: Remove document assignment
const removeDocumentAssignment = (documentType: string) => {
    const requiredDoc = requiredDocuments.value.find(
        (doc) => doc.type === documentType,
    );
    if (requiredDoc && requiredDoc.file) {
        // Move to optional documents
        optionalDocuments.value.push({
            type: 'Other',
            label: 'Additional Document',
            file: requiredDoc.file,
            document_type: 'Other',
        });

        // Remove from required
        requiredDoc.uploaded = false;
        requiredDoc.file = undefined;
    }
};

// FIXED: Enhanced document removal
const onRemove = (event: any) => {
    const fileToRemove = event.file;

    console.log('Removing file:', fileToRemove.name);

    // Check if this is a required document
    const requiredDoc = requiredDocuments.value.find(
        (doc) => doc.file && doc.file.name === fileToRemove.name,
    );

    if (requiredDoc) {
        console.log('Removing from required documents:', requiredDoc.label);
        requiredDoc.uploaded = false;
        requiredDoc.file = undefined;
    }

    // Remove from optional documents
    const optionalDocIndex = optionalDocuments.value.findIndex(
        (doc) => doc.file.name === fileToRemove.name,
    );
    if (optionalDocIndex > -1) {
        console.log(
            'Removing from optional documents at index:',
            optionalDocIndex,
        );
        optionalDocuments.value.splice(optionalDocIndex, 1);
    }

    // Remove from form documents
    const formDocIndex = form.documents.findIndex(
        (file) => file.name === fileToRemove.name,
    );
    if (formDocIndex > -1) {
        console.log('Removing from form documents at index:', formDocIndex);
        form.documents.splice(formDocIndex, 1);
    }

    if (form.documents.length > 0) {
        validationErrors.value.documents = '';
    }

    console.log(
        'After removal - Form docs:',
        form.documents.length,
        'Required docs:',
        requiredDocuments.value.filter((d) => d.uploaded).length,
        'Optional docs:',
        optionalDocuments.value.length,
    );
};

// FIXED: Enhanced clear all documents
const clearAllDocuments = () => {
    console.log('Clearing all documents');

    // Reset required documents
    requiredDocuments.value.forEach((doc) => {
        doc.uploaded = false;
        doc.file = undefined;
    });

    // Clear optional documents
    optionalDocuments.value = [];

    // Clear form documents
    form.documents = [];

    // Clear the FileUpload component
    if (fileUploadRef.value) {
        fileUploadRef.value.clear();
    }

    validationErrors.value.documents = '';
    selectedDocumentType.value = '';

    console.log('All documents cleared');
};

// Track upload events
const onUpload = (event: any) => {
    console.log('Upload event triggered:', event);
};

// FIXED: Form submission with proper document type assignment
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

    // Prepare document types data - SIMPLIFIED AND CLEAR
    const documentTypesData: { type: string; label: string; file_name: string; }[] = [];

    console.log('=== PREPARING DOCUMENT TYPES ===');

    // Add required documents that have files - CRITICAL: Include filename
    requiredDocuments.value
        .filter((doc) => doc.uploaded && doc.file)
        .forEach((doc) => {
            console.log('Adding REQUIRED document:', {
                type: doc.type,
                label: doc.label,
                file_name: doc.file?.name, // THIS IS CRITICAL
            });

            documentTypesData.push({
                type: doc.type,
                label: doc.label,
                file_name: doc.file?.name || '', // MUST INCLUDE FILENAME
            });
        });

    // Add optional documents with their assigned types
    optionalDocuments.value.forEach((doc) => {
        console.log('Adding OPTIONAL document:', {
            type: doc.document_type,
            label: doc.label,
            file_name: doc.file.name, // THIS IS CRITICAL
        });

        documentTypesData.push({
            type: doc.document_type,
            label: doc.label,
            file_name: doc.file.name, // MUST INCLUDE FILENAME
        });
    });

    console.log('Final document types to send:', documentTypesData);
    console.log(
        'Files to upload:',
        form.documents.map((f) => f.name),
    );
    
    form.voucher_date = moment(form.voucher_date).format('YYYY-MM-DD');

    const submitData = {
        ...form.data(),
        items: form.items.map((item) => ({
            ...item,
            amount: item.sub_total,
        })),
        document_types: documentTypesData, // Include all document types with filenames
    };

    console.log('=== SUBMITTING VOUCHER ===');
    console.log('Document types being sent:', documentTypesData);
    console.log(
        'Files being sent:',
        form.documents.map((f) => f.name),
    );

    form.post('/vouchers', {
        data: submitData,
        preserveScroll: true,
        onSuccess: (response) => {
            console.log(response);
            let message = '';
            if (form.status === 'Draft') {
                message =
                    'Voucher saved as draft successfully! You can edit it later.';
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

// Save as draft - CHANGED: This is now the default behavior
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

// Submit for approval - CHANGED: Now requires documents
const submitForApproval = () => {
    form.status = 'Submitted';

    // Validate all fields including documents
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

    // Only validate documents when submitting for approval
    if (form.status === 'Submitted') {
        const areDocumentsValid = validateDocuments();
        return isHeaderValid && areLineItemsValid && areDocumentsValid;
    }

    return isHeaderValid && areLineItemsValid;
};

// Initialize with schedule data
onMounted(() => {
    console.log('Schedule data received:', props.schedule);

    // Auto-populate form with schedule header data only
    autoSelectMdaAndYear();
    generateNarrationFromSchedule();

    // Add initial empty line item (NOT populated from schedule)
    if (form.items.length === 0) {
        addItem();
    }
});


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

const onLazyLoad = (event) => {
    // Check if we need to load more items based on scroll position
    // The event object provides information about the first and last visible indices
    // You need logic to determine the next page
    // A simple approach is to load the next page every time the event fires (if more data is available)
    fetchData(currentPage.value + 1, filterValue.value);
};

// Handle filtering (if filter="true" is used)
const onFilter = (event) => {
    filterValue.value = event.value;
    // Reset page to 1 and fetch filtered data
    fetchData(1, event.value);
};


fetchData(1);
</script>

<style scoped>
.uppercase-input {
    text-transform: uppercase;
}
</style>

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
                            <Calendar id="voucher_date" :v-model="form.voucher_date" dateFormat="yy-mm-dd"
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
                            <!-- <InputText :modelValue="voucherType.toUpperCase()" class="w-full" disabled /> -->

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
                                <small :class="form.voucher_number.length > 500
                                    ? 'p-error'
                                    : 'text-500'
                                    " />
                                <small class="text-500">Voucher Number: {{ voucherNumber }}</small>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Narration -->
                <div class="field mb-4">
                    <div class="col-6">
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

                    <div class="col-6">
                        
                            <label for="payee_name" class="text-500 mb-1 block text-sm font-semibold">
                                Payee Name/Beneficiary Name
                            </label>
                            <Dropdown editable v-model="form.payee_name" :options="lazyItems" optionLabel="label"
                                optionValue="value" :loading="loading" placeholder="Select who is being paid" filter
                                @filter="onFilter" class="w-full" />

                            <small class="p-error block" v-if="form.errors?.payee_name">{{
                                form.errors.payee_name
                                }}</small>

                        </div>

                    </div>

                

                <!-- Line Items Table - EMPTY for user to fill -->
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
                        <Column field="description" header="Description" headerStyle="width: 30%; min-width: 200px"
                            bodyStyle="width: 30%; min-width: 200px">
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

                        <!-- Economic Code Column - FIXED WIDTH WITH SEARCH -->
                        <Column field="economy_code_id" header="Economic Code"
                            headerStyle="width: 180px; min-width: 180px; max-width: 180px"
                            bodyStyle="width: 180px; min-width: 180px; max-width: 180px">
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

                        <!-- Economic Code Item Column - FIXED WIDTH WITH SEARCH -->
                        <Column field="economy_code_item_id" header="Code Item"
                            headerStyle="width: 180px; min-width: 180px; max-width: 180px"
                            bodyStyle="width: 180px; min-width: 180px; max-width: 180px">
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

                        <Column field="quantity" header="Qty"
                            headerStyle="width: 100px; min-width: 100px; max-width: 100px"
                            bodyStyle="width: 100px; min-width: 100px; max-width: 100px">
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
                            headerStyle="width: 150px; min-width: 150px; max-width: 150px"
                            bodyStyle="width: 150px; min-width: 150px; max-width: 150px">
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
                            headerStyle="width: 150px; min-width: 150px; max-width: 150px"
                            bodyStyle="width: 150px; min-width: 150px; max-width: 150px"
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

                        <Column headerStyle="width: 70px; min-width: 70px; max-width: 70px"
                            bodyStyle="width: 70px; min-width: 70px; max-width: 70px" bodyClass="text-center">
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
                                    Supporting Documents Status:
                                </h5>
                                <div class="grid">
                                    <div v-for="doc in requiredDocuments" :key="doc.type" class="col-6 mb-3">
                                        <div class="surface-100 border-round border-1 p-3">
                                            <div class="align-items-center justify-content-between mb-2 flex">
                                                <div class="align-items-center flex gap-2">
                                                    <i :class="doc.uploaded
                                                        ? 'pi pi-check-circle text-green-500'
                                                        : doc.required
                                                            ? 'pi pi-exclamation-circle text-orange-500'
                                                            : 'pi pi-circle text-500'
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
                                                    <Badge v-else value="Optional" severity="info" size="small" />
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
                                                    {{
                                                        doc.required
                                                            ? 'Required for submission'
                                                            : 'Optional document'
                                                    }}
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
                                            required for submission. Approval
                                            Form is recommended.
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
                                        Documents to Assign:
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
                                                        " :options="documentTypeOptions.filter(
                                                            (opt) =>
                                                                opt.value ===
                                                                'other' ||
                                                                !requiredDocuments.find(
                                                                    (rd) =>
                                                                        rd.type ===
                                                                        opt.value &&
                                                                        rd.uploaded,
                                                                ),
                                                        )
                                                            " optionLabel="label" optionValue="value"
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
                                        Assigned Required Documents:
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

                            <!-- NEW: Schedule Total Reference -->
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
                                <span class="font-semibold">{{ props.schedule.voucher_count }}</span>
                            </div>
                            <div class="justify-content-between total-row mb-2 flex">
                                <span class="text-500">Total Amount Raised:</span>
                                <span class="font-semibold">{{ formatCurrency(props.schedule.amount_posted) }}</span>
                            </div>
                            <div class="justify-content-between total-row mb-2 flex">
                                <span class="text-500">Outstanding Balance:</span>
                                <span class="font-semibold">{{ formatCurrency(scheduleTotal -
                                    props.schedule.amount_posted) }}</span>
                            </div>
                            <div class="justify-content-between total-row mb-2 flex">
                                <span class="text-500">Voucher Subtotal:</span>
                                <span class="font-semibold" :class="{
                                    'text-green-500':
                                        scheduleTotal - (props.schedule.amount_posted + voucherSubtotal) >= 0,
                                    'text-red-500':
                                        scheduleTotal - (props.schedule.amount_posted + voucherSubtotal) < 0,
                                }">{{ formatCurrency(voucherSubtotal) }}</span>
                            </div>
                            <Divider />
                            <div class="justify-content-between total-row flex text-xl font-bold" :class="{
                                'text-green-500':
                                    scheduleTotal - (props.schedule.amount_posted + voucherSubtotal) >= 0,
                                'text-orange-500':
                                    scheduleTotal - (props.schedule.amount_posted + voucherSubtotal) < 0,
                            }">
                                <span>Voucher Total:</span>
                                <span>{{ formatCurrency(voucherTotal) }}</span>
                            </div>

                            <!-- NEW: Validation Status -->
                            <div v-if="scheduleInfo" class="mt-2">
                                <div v-if="scheduleTotal - (props.schedule.amount_posted + voucherSubtotal) == 0"
                                    class="align-items-center flex gap-2 text-green-500">
                                    <i class="pi pi-check-circle"></i>
                                    <small class="font-semibold">Total amount on raised vouchers now matches schedule
                                        total</small>
                                </div>
                                <div v-if="scheduleTotal - (props.schedule.amount_posted + voucherSubtotal) > 0 && voucherTotal > 0"
                                    class="align-items-center flex gap-2 text-orange-400">
                                    <i class="pi pi-exclamation-triangle"></i>
                                    <small class="font-semibold">Total amount on raised vouchers is below the schedule
                                        total.
                                        <br />Please adjust the line items to match the schedule total.
                                        <br />Alternatively, you may
                                        have to add another voucher to this schedule.</small>
                                </div>
                                <div v-if="scheduleTotal - (props.schedule.amount_posted + voucherSubtotal) < 0"
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
                        title="Submit for approval to Internal Audit (requires all documents)" disabled />
                </div>
            </template>
        </Card>
    </AppLayout>
</template>

<style scoped>
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
    overflow-x: hidden;
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

/* Ensure table columns maintain consistent widths */
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
