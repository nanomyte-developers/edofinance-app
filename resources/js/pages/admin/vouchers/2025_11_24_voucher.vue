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

// Layout and types
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';

const toast = useToast();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Vouchers', href: '/vouchers' },
    { title: 'Create Voucher', href: '#' },
];

let nextItemId = 1;

// Define types for line items
interface LineItem {
    id: number;
    description: string;
    quantity: number;
    unit_price: number;
    sub_total: number;
    errors?: {
        description?: string;
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
    defaultAccount: String,
    mdas: {
        type: Array,
        default: () => [],
    },
    financialYears: {
        type: Array,
        default: () => [],
    },
    today: {
        type: String,
        default: new Date().toISOString().split('T')[0],
    },
});

// Page title based on voucher type
const pageTitle = `Create New ${props.voucherType.charAt(0).toUpperCase() + props.voucherType.slice(1)} Voucher`;

// Validation state
const validationErrors = ref({
    year_id: '',
    mda_id: '',
    voucher_date: '',
    narration: '',
    line_items: '',
    documents: '',
});

// Document type options for dropdown
const documentTypeOptions = [
    { label: 'Approval Form', value: 'approval_form' },
    { label: 'Invoice', value: 'invoice' },
    { label: 'Receipt', value: 'receipt' },
    { label: 'Delivery Note', value: 'delivery_note' },
    { label: 'Other Document', value: 'other' },
];

// Document management
const requiredDocuments = ref<RequiredDocument[]>([
    {
        type: 'approval_form',
        label: 'Approval Form',
        required: true,
        uploaded: false,
    },
    { type: 'invoice', label: 'Invoice', required: true, uploaded: false },
    { type: 'receipt', label: 'Receipt', required: true, uploaded: false },
    {
        type: 'delivery_note',
        label: 'Delivery Note',
        required: true,
        uploaded: false,
    },
]);

const optionalDocuments = ref<UploadedDocument[]>([]);
const selectedDocumentType = ref<string>(''); // For the dropdown
const fileUploadRef = ref(); // Ref for FileUpload component

// Inertia form setup - CHANGED status to 'Draft' by default
const form = useForm({
    voucher_type: props.voucherType,
    year_id: null as number | null,
    mda_id: null as number | null,
    voucher_date: props.today,
    narration: '',
    status: 'Draft', // CHANGED from 'Pending' to 'Draft'
    total_amount: 0,
    items: [] as LineItem[],
    documents: [] as File[],
});

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

    // Convert Naira part
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

    form.items.forEach((item, index) => {
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

        if (!item.quantity || item.quantity <= 0) {
            itemErrors.quantity = 'Quantity must be greater than 0';
            isValid = false;
        } else if (item.quantity > 999999) {
            itemErrors.quantity = 'Quantity is too large';
            isValid = false;
        }

        if (!item.unit_price && item.unit_price !== 0) {
            itemErrors.unit_price = 'Unit price is required';
            isValid = false;
        } else if (item.unit_price < 0) {
            itemErrors.unit_price = 'Unit price cannot be negative';
            isValid = false;
        } else if (item.unit_price > 99999999.99) {
            itemErrors.unit_price = 'Unit price is too large';
            isValid = false;
        }

        if (!item.sub_total && item.sub_total !== 0) {
            itemErrors.sub_total = 'Sub total is required';
            isValid = false;
        } else if (item.sub_total < 0) {
            itemErrors.sub_total = 'Sub total cannot be negative';
            isValid = false;
        } else if (item.sub_total > 99999999.99) {
            itemErrors.sub_total = 'Sub total is too large';
            isValid = false;
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

    return isValid;
};

// Enhanced document validation
const validateDocuments = () => {
    validationErrors.value.documents = '';

    // For draft status, only basic validation
    if (form.status === 'Draft') {
        // Check if any documents are uploaded (optional for drafts)
        const hasRequiredDocs = requiredDocuments.value.some(
            (doc) => doc.uploaded,
        );
        if (form.documents.length === 0 && !hasRequiredDocs) {
            return true; // No documents for draft is acceptable
        }
    }

    // For submission, validate all required documents
    if (form.status === 'Submitted') {
        const missingRequired = requiredDocuments.value.filter(
            (doc) => doc.required && !doc.uploaded,
        );

        if (missingRequired.length > 0) {
            validationErrors.value.documents = `Missing required documents: ${missingRequired.map((doc) => doc.label).join(', ')}`;
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

    for (const file of form.documents) {
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
            selectedDocumentType.value !== 'other'
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
                    type: 'other',
                    label: 'Additional Document',
                    file: file,
                    document_type: 'other',
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
    if (documentType === 'other') return; // Don't reassign optional documents

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
                type: 'other',
                label: 'Replaced Document',
                file: requiredDoc.file,
                document_type: 'other',
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
            type: 'other',
            label: 'Additional Document',
            file: requiredDoc.file,
            document_type: 'other',
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
    const documentTypesData = [];

    console.log('=== PREPARING DOCUMENT TYPES ===');

    // Add required documents that have files - CRITICAL: Include filename
    requiredDocuments.value
        .filter((doc) => doc.uploaded && doc.file)
        .forEach((doc) => {
            console.log('Adding REQUIRED document:', {
                type: doc.type,
                label: doc.label,
                file_name: doc.file.name, // THIS IS CRITICAL
            });

            documentTypesData.push({
                type: doc.type,
                label: doc.label,
                file_name: doc.file.name, // MUST INCLUDE FILENAME
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
        onSuccess: () => {
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

// Initialize with one line item
onMounted(() => {
    if (form.items.length === 0) {
        addItem();
    }
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
                            <div
                                v-for="(error, field) in form.errors"
                                :key="field"
                                class="align-items-center flex gap-2"
                            >
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
                            <label
                                for="year_id"
                                class="text-500 mb-1 block text-sm font-semibold"
                            >
                                Financial Year *
                            </label>
                            <Dropdown
                                id="year_id"
                                v-model="form.year_id"
                                :options="financialYears"
                                optionLabel="label"
                                optionValue="value"
                                placeholder="Select Financial Year"
                                class="w-full"
                                :class="{
                                    'p-invalid':
                                        form.errors.year_id ||
                                        validationErrors.year_id,
                                }"
                                @change="validationErrors.year_id = ''"
                            />
                            <small
                                v-if="validationErrors.year_id"
                                class="p-error"
                            >
                                {{ validationErrors.year_id }}
                            </small>
                            <small v-if="form.errors.year_id" class="p-error">
                                {{ form.errors.year_id }}
                            </small>
                        </div>
                    </div>
                    <div class="col-4">
                        <div class="field">
                            <label
                                for="mda_id"
                                class="text-500 mb-1 block text-sm font-semibold"
                            >
                                MDA *
                            </label>
                            <Dropdown
                                id="mda_id"
                                v-model="form.mda_id"
                                :options="mdas"
                                optionLabel="label"
                                optionValue="value"
                                placeholder="Select MDA"
                                class="w-full"
                                :class="{
                                    'p-invalid':
                                        form.errors.mda_id ||
                                        validationErrors.mda_id,
                                }"
                                :filter="true"
                                filterPlaceholder="Search MDA..."
                                @change="validationErrors.mda_id = ''"
                            />
                            <small
                                v-if="validationErrors.mda_id"
                                class="p-error"
                            >
                                {{ validationErrors.mda_id }}
                            </small>
                            <small v-if="form.errors.mda_id" class="p-error">
                                {{ form.errors.mda_id }}
                            </small>
                        </div>
                    </div>
                    <div class="col-4">
                        <div class="field">
                            <label
                                for="voucher_date"
                                class="text-500 mb-1 block text-sm font-semibold"
                            >
                                Voucher Date *
                            </label>
                            <Calendar
                                id="voucher_date"
                                v-model="form.voucher_date"
                                dateFormat="yy-mm-dd"
                                class="w-full"
                                :class="{
                                    'p-invalid':
                                        form.errors.voucher_date ||
                                        validationErrors.voucher_date,
                                }"
                                @date-select="
                                    validationErrors.voucher_date = ''
                                "
                            />
                            <small
                                v-if="validationErrors.voucher_date"
                                class="p-error"
                            >
                                {{ validationErrors.voucher_date }}
                            </small>
                            <small
                                v-if="form.errors.voucher_date"
                                class="p-error"
                            >
                                {{ form.errors.voucher_date }}
                            </small>
                        </div>
                    </div>
                </div>

                <!-- Voucher Type Display -->
                <div class="mb-4 grid">
                    <div class="col-6">
                        <div class="field">
                            <label
                                class="text-500 mb-1 block text-sm font-semibold"
                            >
                                Voucher Type
                            </label>
                            <InputText
                                :modelValue="voucherType.toUpperCase()"
                                class="w-full"
                                disabled
                            />
                            <small class="text-500"
                                >Type: {{ voucherType }}</small
                            >
                        </div>
                    </div>
                </div>

                <!-- Narration -->
                <div class="field mb-4">
                    <label
                        for="narration"
                        class="text-500 mb-1 block text-sm font-semibold"
                    >
                        Narration *
                    </label>
                    <Textarea
                        id="narration"
                        v-model="form.narration"
                        rows="3"
                        class="w-full"
                        placeholder="Enter voucher description or purpose..."
                        :class="{
                            'p-invalid':
                                form.errors.narration ||
                                validationErrors.narration,
                        }"
                        @input="validationErrors.narration = ''"
                    />
                    <div class="justify-content-between mt-1 flex">
                        <small
                            v-if="validationErrors.narration"
                            class="p-error"
                        >
                            {{ validationErrors.narration }}
                        </small>
                        <small v-if="form.errors.narration" class="p-error">
                            {{ form.errors.narration }}
                        </small>
                        <small
                            :class="
                                form.narration.length > 500
                                    ? 'p-error'
                                    : 'text-500'
                            "
                        >
                            {{ form.narration.length }}/500
                        </small>
                    </div>
                </div>

                <!-- Line Items Table -->
                <div class="mb-4">
                    <div
                        class="justify-content-between align-items-center mb-3 flex"
                    >
                        <h4 class="m-0">Line Items</h4>
                        <div class="align-items-center flex gap-3">
                            <span
                                class="text-500 text-sm"
                                v-if="validationErrors.line_items"
                            >
                                {{ validationErrors.line_items }}
                            </span>
                            <Button
                                label="Add Line Item"
                                icon="pi pi-plus"
                                severity="success"
                                outlined
                                @click="addItem()"
                            />
                        </div>
                    </div>

                    <DataTable
                        :value="form.items"
                        class="p-datatable-sm"
                        responsiveLayout="scroll"
                    >
                        <Column
                            field="description"
                            header="Description"
                            headerStyle="width: 45%"
                        >
                            <template #body="slotProps">
                                <div class="flex-column flex">
                                    <Textarea
                                        v-model="slotProps.data.description"
                                        rows="2"
                                        autoResize
                                        placeholder="Enter item description..."
                                        class="w-full"
                                        :class="{
                                            'p-invalid':
                                                slotProps.data.errors
                                                    ?.description,
                                        }"
                                        @input="
                                            if (slotProps.data.errors)
                                                delete slotProps.data.errors
                                                    .description;
                                        "
                                    />
                                    <small
                                        v-if="
                                            slotProps.data.errors?.description
                                        "
                                        class="p-error mt-1"
                                    >
                                        {{ slotProps.data.errors.description }}
                                    </small>
                                </div>
                            </template>
                        </Column>

                        <Column
                            field="quantity"
                            header="Qty"
                            headerStyle="width: 15%"
                        >
                            <template #body="slotProps">
                                <div class="flex-column flex">
                                    <InputNumber
                                        v-model="slotProps.data.quantity"
                                        @update:modelValue="
                                            updateItemSubTotal(
                                                slotProps.data,
                                                'quantity',
                                            )
                                        "
                                        :min="1"
                                        :max-fraction-digits="2"
                                        inputClass="w-full text-center"
                                        :class="{
                                            'p-invalid':
                                                slotProps.data.errors?.quantity,
                                        }"
                                        @input="
                                            if (slotProps.data.errors)
                                                delete slotProps.data.errors
                                                    .quantity;
                                        "
                                    />
                                    <small
                                        v-if="slotProps.data.errors?.quantity"
                                        class="p-error mt-1"
                                    >
                                        {{ slotProps.data.errors.quantity }}
                                    </small>
                                </div>
                            </template>
                        </Column>

                        <Column
                            field="unit_price"
                            header="Unit Price"
                            headerStyle="width: 20%"
                        >
                            <template #body="slotProps">
                                <div class="flex-column flex">
                                    <InputNumber
                                        v-model="slotProps.data.unit_price"
                                        @update:modelValue="
                                            updateItemSubTotal(
                                                slotProps.data,
                                                'unit_price',
                                            )
                                        "
                                        mode="currency"
                                        currency="NGN"
                                        locale="en-NG"
                                        :min="0"
                                        inputClass="w-full text-right"
                                        :class="{
                                            'p-invalid':
                                                slotProps.data.errors
                                                    ?.unit_price,
                                        }"
                                        @input="
                                            if (slotProps.data.errors)
                                                delete slotProps.data.errors
                                                    .unit_price;
                                        "
                                    />
                                    <small
                                        v-if="slotProps.data.errors?.unit_price"
                                        class="p-error mt-1"
                                    >
                                        {{ slotProps.data.errors.unit_price }}
                                    </small>
                                </div>
                            </template>
                        </Column>

                        <Column
                            field="sub_total"
                            header="Sub Total"
                            headerStyle="width: 15%"
                            bodyClass="font-bold text-right"
                        >
                            <template #body="slotProps">
                                <div class="flex-column flex">
                                    <InputNumber
                                        v-model="slotProps.data.sub_total"
                                        @update:modelValue="
                                            updateItemSubTotal(
                                                slotProps.data,
                                                'sub_total',
                                            )
                                        "
                                        mode="currency"
                                        currency="NGN"
                                        locale="en-NG"
                                        :min="0"
                                        inputClass="w-full text-right"
                                        :class="{
                                            'p-invalid':
                                                slotProps.data.errors
                                                    ?.sub_total,
                                        }"
                                        @input="
                                            if (slotProps.data.errors)
                                                delete slotProps.data.errors
                                                    .sub_total;
                                        "
                                        readonly
                                    />
                                    <small
                                        v-if="slotProps.data.errors?.sub_total"
                                        class="p-error mt-1"
                                    >
                                        {{ slotProps.data.errors.sub_total }}
                                    </small>
                                </div>
                            </template>
                        </Column>

                        <Column headerStyle="width: 5%" bodyClass="text-center">
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
                        <!-- Enhanced Documents Section -->
                        <div class="field-group">
                            <div
                                class="justify-content-between align-items-center mb-3 flex"
                            >
                                <h4 class="m-0">
                                    Supporting Documents
                                    <span class="text-500 ml-2 text-sm">
                                        (4 Required for submission)
                                    </span>
                                </h4>
                                <Button
                                    v-if="form.documents.length > 0"
                                    label="Clear All"
                                    icon="pi pi-times"
                                    severity="secondary"
                                    text
                                    @click="clearAllDocuments"
                                />
                            </div>

                            <!-- Document Type Selection -->
                            <div class="surface-50 border-round mb-4 p-3">
                                <h5 class="mt-0 mb-2">
                                    Document Type Selection
                                </h5>
                                <div class="align-items-end flex gap-2">
                                    <div class="flex-1">
                                        <label
                                            class="text-500 mb-1 block text-sm font-semibold"
                                        >
                                            Select document type before
                                            uploading:
                                        </label>
                                        <Dropdown
                                            v-model="selectedDocumentType"
                                            :options="documentTypeOptions"
                                            optionLabel="label"
                                            optionValue="value"
                                            placeholder="Choose document type..."
                                            class="w-full"
                                        />
                                    </div>
                                    <div class="flex-none">
                                        <small class="text-500 block text-sm">
                                            Select type then upload file
                                        </small>
                                    </div>
                                </div>
                                <small class="text-500 mt-2 block">
                                    <i class="pi pi-info-circle mr-1"></i>
                                    Choose the document type from dropdown
                                    before selecting files
                                </small>
                            </div>

                            <!-- Required Documents Status -->
                            <div class="mb-4">
                                <h5 class="mb-2">Required Documents Status:</h5>
                                <div class="grid">
                                    <div
                                        v-for="doc in requiredDocuments"
                                        :key="doc.type"
                                        class="col-6 mb-3"
                                    >
                                        <div
                                            class="surface-100 border-round border-1 p-3"
                                        >
                                            <div
                                                class="align-items-center justify-content-between mb-2 flex"
                                            >
                                                <div
                                                    class="align-items-center flex gap-2"
                                                >
                                                    <i
                                                        :class="
                                                            doc.uploaded
                                                                ? 'pi pi-check-circle text-green-500'
                                                                : 'pi pi-times-circle text-red-500'
                                                        "
                                                    ></i>
                                                    <span
                                                        :class="
                                                            doc.uploaded
                                                                ? 'text-700 font-semibold'
                                                                : 'text-500'
                                                        "
                                                    >
                                                        {{ doc.label }}
                                                    </span>
                                                    <Badge
                                                        v-if="!doc.uploaded"
                                                        value="Required"
                                                        severity="danger"
                                                        size="small"
                                                    />
                                                </div>
                                                <Button
                                                    v-if="doc.uploaded"
                                                    icon="pi pi-times"
                                                    severity="danger"
                                                    text
                                                    rounded
                                                    size="small"
                                                    @click="
                                                        removeDocumentAssignment(
                                                            doc.type,
                                                        )
                                                    "
                                                    title="Remove assignment"
                                                />
                                            </div>
                                            <div
                                                v-if="doc.uploaded"
                                                class="mt-2"
                                            >
                                                <small class="text-500 block">
                                                    <i
                                                        class="pi pi-file mr-1"
                                                    ></i>
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
                                                    Not uploaded yet
                                                </small>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="mb-2" v-if="validationErrors.documents">
                                <Message severity="error" :closable="false">
                                    {{ validationErrors.documents }}
                                </Message>
                            </div>

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
                                @upload="onUpload"
                                :auto="false"
                                :customUpload="true"
                                :disabled="form.processing"
                                :class="{
                                    'p-invalid': validationErrors.documents,
                                }"
                            >
                                <template #empty>
                                    <p class="text-500">
                                        Drag and drop files here or click to
                                        browse
                                    </p>
                                    <small class="text-500">
                                        Supported formats: Images, PDF, Word,
                                        Excel (Max: 10MB per file)
                                    </small>
                                    <div
                                        class="mt-2"
                                        v-if="!selectedDocumentType"
                                    >
                                        <Message
                                            severity="warn"
                                            :closable="false"
                                            class="text-sm"
                                        >
                                            <div
                                                class="align-items-center flex gap-2"
                                            >
                                                <i
                                                    class="pi pi-exclamation-triangle"
                                                ></i>
                                                <span
                                                    >Please select a document
                                                    type above before
                                                    uploading</span
                                                >
                                            </div>
                                        </Message>
                                    </div>
                                    <div class="mt-2" v-else>
                                        <Message
                                            severity="info"
                                            :closable="false"
                                            class="text-sm"
                                        >
                                            <div
                                                class="align-items-center flex gap-2"
                                            >
                                                <i
                                                    class="pi pi-info-circle"
                                                ></i>
                                                <span
                                                    >Uploading as:
                                                    <strong>{{
                                                        documentTypeOptions.find(
                                                            (opt) =>
                                                                opt.value ===
                                                                selectedDocumentType,
                                                        )?.label
                                                    }}</strong></span
                                                >
                                            </div>
                                        </Message>
                                    </div>
                                    <div class="mt-1">
                                        <small class="text-500">
                                            <strong
                                                >Required for
                                                submission:</strong
                                            >
                                            Approval Form, Invoice, Receipt,
                                            Delivery Note
                                        </small>
                                    </div>
                                    <div
                                        v-if="validationErrors.documents"
                                        class="mt-2"
                                    >
                                        <small class="p-error">{{
                                            validationErrors.documents
                                        }}</small>
                                    </div>
                                </template>
                            </FileUpload>

                            <!-- Uploaded Files Display with Type Assignment -->
                            <div v-if="form.documents.length > 0" class="mt-3">
                                <h5 class="mb-2">
                                    Uploaded Files ({{
                                        form.documents.length
                                    }}):
                                </h5>

                                <!-- Unassigned/Optional Documents -->
                                <div
                                    v-if="optionalDocuments.length > 0"
                                    class="mb-4"
                                >
                                    <h6 class="mb-2 text-blue-600">
                                        Documents to Assign:
                                    </h6>
                                    <div class="grid">
                                        <div
                                            v-for="(
                                                doc, index
                                            ) in optionalDocuments"
                                            :key="index"
                                            class="col-12 mb-3"
                                        >
                                            <div
                                                class="align-items-center justify-content-between surface-50 border-round flex p-3"
                                            >
                                                <div
                                                    class="align-items-center flex gap-3"
                                                >
                                                    <i
                                                        class="pi pi-file text-2xl"
                                                    ></i>
                                                    <div>
                                                        <div
                                                            class="font-medium"
                                                        >
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
                                                <div
                                                    class="align-items-center flex gap-2"
                                                >
                                                    <Dropdown
                                                        v-model="
                                                            doc.document_type
                                                        "
                                                        :options="
                                                            documentTypeOptions.filter(
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
                                                        "
                                                        optionLabel="label"
                                                        optionValue="value"
                                                        placeholder="Assign type..."
                                                        class="w-10rem"
                                                        @change="
                                                            assignDocumentType(
                                                                doc.file,
                                                                doc.document_type,
                                                            )
                                                        "
                                                    />
                                                    <Button
                                                        icon="pi pi-times"
                                                        severity="danger"
                                                        text
                                                        rounded
                                                        @click="
                                                            onRemove({
                                                                file: doc.file,
                                                            })
                                                        "
                                                    />
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Required Documents Summary -->
                                <div
                                    v-if="
                                        requiredDocuments.some(
                                            (doc) => doc.uploaded,
                                        )
                                    "
                                    class="mb-3"
                                >
                                    <h6 class="mb-2 text-green-600">
                                        Assigned Required Documents:
                                    </h6>
                                    <ul class="m-0 list-none p-0">
                                        <li
                                            v-for="doc in requiredDocuments.filter(
                                                (d) => d.uploaded,
                                            )"
                                            :key="doc.type"
                                            class="align-items-center justify-content-between surface-50 border-round mb-2 flex p-2"
                                        >
                                            <div
                                                class="align-items-center flex"
                                            >
                                                <i
                                                    class="pi pi-check-circle mr-2 text-green-500"
                                                ></i>
                                                <div>
                                                    <span class="font-medium">{{
                                                        doc.label
                                                    }}</span>
                                                    <small
                                                        class="text-500 block"
                                                        >{{
                                                            doc.file?.name
                                                        }}</small
                                                    >
                                                </div>
                                            </div>
                                            <div
                                                class="align-items-center flex gap-2"
                                            >
                                                <Badge
                                                    value="Required"
                                                    severity="success"
                                                    size="small"
                                                />
                                                <small class="text-500">
                                                    {{
                                                        (
                                                            doc.file!.size /
                                                            1024
                                                        ).toFixed(2)
                                                    }}
                                                    KB
                                                </small>
                                                <Button
                                                    icon="pi pi-times"
                                                    severity="danger"
                                                    text
                                                    rounded
                                                    @click="
                                                        removeDocumentAssignment(
                                                            doc.type,
                                                        )
                                                    "
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
                            <div
                                class="justify-content-between total-row mb-2 flex"
                            >
                                <span class="text-500">Subtotal:</span>
                                <span class="font-semibold">{{
                                    formatCurrency(voucherSubtotal)
                                }}</span>
                            </div>
                            <Divider />
                            <div
                                class="justify-content-between total-row flex text-xl font-bold"
                            >
                                <span>Total Amount:</span>
                                <span class="text-primary">{{
                                    formatCurrency(voucherTotal)
                                }}</span>
                            </div>
                            <InputNumber
                                v-model="form.total_amount"
                                mode="currency"
                                currency="NGN"
                                locale="en-NG"
                                class="mt-2 hidden w-full"
                                readonly
                            />
                        </div>
                    </div>
                </div>

                <!-- Action Buttons -->
                <div class="justify-content-end mt-5 flex gap-2">
                    <Button
                        label="Save as Draft"
                        icon="pi pi-save"
                        severity="secondary"
                        :loading="form.processing"
                        @click="saveDraft"
                        title="Save as draft (documents optional, can edit later)"
                    />
                    <Button
                        label="Submit for Approval"
                        icon="pi pi-send"
                        severity="success"
                        :loading="form.processing"
                        @click="submitForApproval"
                        title="Submit for approval to Internal Audit (requires all documents)"
                    />
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
</style>
