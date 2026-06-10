<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import Badge from 'primevue/badge';
import Button from 'primevue/button';
import Calendar from 'primevue/calendar';
import Card from 'primevue/card';
import Column from 'primevue/column';
import DataTable from 'primevue/datatable';
import Dialog from 'primevue/dialog';
import Divider from 'primevue/divider';
import Dropdown from 'primevue/dropdown';
import Select from 'primevue/select';
import FileUpload from 'primevue/fileupload';
import InputNumber from 'primevue/inputnumber';
import InputText from 'primevue/inputtext';
import Message from 'primevue/message';
import Textarea from 'primevue/textarea';
import Toast from 'primevue/toast';
import { useToast } from 'primevue/usetoast';
import { computed, onMounted, ref, watch } from 'vue';
import axios from 'axios';


// Layout and types
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import Label from '@/components/ui/label/Label.vue';
import Tab from 'primevue/tab';
import { Tag } from 'lucide-vue-next';

const toast = useToast();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Vouchers', href: '/vouchers' },
    { title: 'Edit Voucher', href: '#' },
];

// Document viewer state
const documentViewerVisible = ref(false);
const currentDocument = ref<any>(null);
const documentViewerTitle = ref('');

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
    errors?: {
        description?: string;
        quantity?: string;
        unit_price?: string;
        sub_total?: string;
    };

    economy_code_id: number | null;
    economy_code_item_id: number | null;
}

// Document types
interface RequiredDocument {
    type: string;
    label: string;
    required: boolean;
    uploaded: boolean;
    file?: File;
    existing_document_id?: number;
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


const voucherTypes = [{
    label: 'Standard',
    value: 'standard'
}, {
    label: 'Prepayment',
    value: 'prepayment'
},
{
    label: 'Salary',
    value: 'salary'
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
    // {
    //     type: 'approval_form',
    //     label: 'Approval Form',
    //     required: true,
    //     uploaded: false,
    // },
    // { type: 'invoice', label: 'Invoice', required: true, uploaded: false },
    // { type: 'receipt', label: 'Receipt', required: true, uploaded: false },
    // {
    //     type: 'delivery_note',
    //     label: 'Delivery Note',
    //     required: true,
    //     uploaded: false,
    // },
]);

const optionalDocuments = ref<UploadedDocument[]>([]);
const selectedDocumentType = ref<string>('');
const allUploadedFiles = ref<File[]>([]);
const existingDocuments = ref<ExistingDocument[]>(
    props.existingDocuments || [],
);
const documentsToDelete = ref<number[]>([]);

// Initialize required documents from existing documents
const initializeRequiredDocuments = () => {
    existingDocuments.value.forEach((doc) => {
        const requiredDoc = requiredDocuments.value.find(
            (rd) => rd.type === doc.document_type,
        );
        if (requiredDoc) {
            requiredDoc.uploaded = true;
            requiredDoc.existing_document_id = doc.id;
        }
    });
};


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
    _method: 'PUT', // Important for update
    voucher_type: props.voucher.voucher_type.toLowerCase(),
    year_id: props.voucher.year_id,
    mda_id: props.voucher.mda_id,
    voucher_date: props.voucher.voucher_date,
    narration: props.voucher.narration,
    status: props.voucher.status,
    total_amount: props.voucher.total_amount || 0,
    items: [] as LineItem[],
    documents: [] as File[],
    documents_to_delete: [] as number[],
    voucher_number: props.voucher.voucher_number,
    payee_name: props.voucher.payee_name,
    bank_activity_id: props.voucher.bank_activity_id,
});

// Initialize form items from props - FIXED
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
        }));

        // Calculate initial total
        form.total_amount = form.items.reduce(
            (sum, item) => sum + (Number(item.sub_total) || 0),
            0,
        );
    } else {
        // Add one empty item if no items exist
        addItem();
    }
};

// Computed properties for dynamic totals - FIXED
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

// Watch for changes and update form total
watch(voucherTotal, (newTotal) => {
    form.total_amount = newTotal;
});




// NEW: Computed property for schedule total
const scheduleTotal = computed(() => {
    return props.schedule?.total_amount || 0;
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


const scheduleInfo = computed(() => {
    if (!props.schedule) return null;

    return {
        schedule_number: props.schedule.schedule_number,
        mda: props.schedule.mda?.name,
        budget_code: props.schedule.budget_code,
        total_amount: props.schedule.total_amount,
    };
});

// Format currency function - FIXED
const formatCurrency = (value: number) => {
    if (isNaN(value) || value === null || value === undefined) {
        return 'â‚¦0.00';
    }
    return new Intl.NumberFormat('en-NG', {
        style: 'currency',
        currency: 'NGN',
    }).format(value);
};

// Document viewer functions
// const viewDocument = (
//     document: any,
//     type: 'existing' | 'required' | 'optional' = 'existing',
// ) => {
//     if (type === 'existing') {
//         // For existing documents stored on server
//         currentDocument.value = {
//             name: document.file_name,
//             url: document.file_path,
//             type: document.mime_type,
//             label: document.document_label,
//             size: document.file_size,
//         };
//         documentViewerTitle.value = `${document.document_label} - ${document.file_name}`;
//     } else if (type === 'required') {
//         // For required documents with uploaded files
//         if (document.file) {
//             const fileUrl = URL.createObjectURL(document.file);
//             currentDocument.value = {
//                 name: document.file.name,
//                 url: fileUrl,
//                 type: document.file.type,
//                 label: document.label,
//                 size: document.file.size,
//             };
//             documentViewerTitle.value = `${document.label} - ${document.file.name}`;
//         }
//     } else if (type === 'optional') {
//         // For optional documents
//         const fileUrl = URL.createObjectURL(document.file);
//         currentDocument.value = {
//             name: document.file.name,
//             url: fileUrl,
//             type: document.file.type,
//             label: document.label,
//             size: document.file.size,
//         };
//         documentViewerTitle.value = `${document.label} - ${document.file.name}`;
//     }

//     documentViewerVisible.value = true;
// };

// Fixed document viewer function
const viewDocument = (
    document: any,
    type: 'existing' | 'required' | 'optional' = 'existing',
) => {
    console.log('Opening document:', { document, type });

    if (type === 'existing') {
        // For existing documents stored on server
        let documentUrl = document.file_path;

        // If file_path is not a full URL, construct it
        if (
            documentUrl &&
            !documentUrl.startsWith('http') &&
            !documentUrl.startsWith('/')
        ) {
            documentUrl = `/storage/${documentUrl}`;
        } else if (
            documentUrl &&
            !documentUrl.startsWith('http') &&
            documentUrl.startsWith('/')
        ) {
            documentUrl = `${window.location.origin}${documentUrl}`;
        }

        console.log('Document URL:', documentUrl);

        currentDocument.value = {
            name: document.file_name,
            url: documentUrl,
            type: document.mime_type,
            label: document.document_label,
            size: document.file_size,
        };
        documentViewerTitle.value = `${document.document_label} - ${document.file_name}`;
    } else if (type === 'required') {
        // For required documents with uploaded files
        if (document.file) {
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
    } else if (type === 'optional') {
        // For optional documents
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
    // Clean up object URLs to prevent memory leaks
    if (
        currentDocument.value &&
        currentDocument.value.url &&
        currentDocument.value.url.startsWith('blob:')
    ) {
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

// Check if document is viewable in browser
const isViewable = (mimeType: string) => {
    const viewableTypes = [
        'image/jpeg',
        'image/jpg',
        'image/png',
        'image/gif',
        'image/webp',
        'application/pdf',
        'text/plain',
    ];
    return viewableTypes.includes(mimeType);
};

// Get document icon based on file type
const getDocumentIcon = (mimeType: string, fileName: string) => {
    if (mimeType.startsWith('image/')) return 'pi pi-image';
    if (mimeType === 'application/pdf') return 'pi pi-file-pdf';
    if (
        mimeType.includes('word') ||
        fileName.endsWith('.doc') ||
        fileName.endsWith('.docx')
    )
        return 'pi pi-file-word';
    if (
        mimeType.includes('excel') ||
        fileName.endsWith('.xls') ||
        fileName.endsWith('.xlsx')
    )
        return 'pi pi-file-excel';
    return 'pi pi-file';
};

// Fixed line item calculation - ENHANCED
const updateItemSubTotal = (
    item: LineItem,
    field: 'quantity' | 'unit_price' | 'sub_total',
) => {
    // Ensure numeric values with proper fallbacks
    const quantity = Number(item.quantity) || 0;
    const unit_price = Number(item.unit_price) || 0;
    const sub_total = Number(item.sub_total) || 0;

    console.log(`Updating ${field}:`, { quantity, unit_price, sub_total });

    if (field === 'quantity' || field === 'unit_price') {
        // Calculate sub_total from quantity Ã— unit_price
        const calculatedSubTotal = quantity * unit_price;
        item.sub_total = parseFloat(calculatedSubTotal.toFixed(2));
        console.log('Calculated new sub_total:', item.sub_total);
    } else if (field === 'sub_total') {
        // Calculate unit_price from sub_total Ã· quantity
        if (quantity > 0) {
            const calculatedUnitPrice = sub_total / quantity;
            item.unit_price = parseFloat(calculatedUnitPrice.toFixed(2));
            console.log('Calculated new unit_price:', item.unit_price);
        } else {
            // If quantity is 0, set unit_price equal to sub_total
            item.unit_price = sub_total;
            console.log('Set unit_price to sub_total:', item.unit_price);
        }
    }

    // Clear validation errors for the updated field
    if (item.errors) {
        delete item.errors[field];
        if (field === 'quantity' || field === 'unit_price') {
            delete item.errors.sub_total;
        }
    }

    // Force Vue reactivity update
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

// Enhanced input handlers for immediate calculation
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



// Watch for Economic Code changes to reset Economic Code item
const onEconomyCodeChange = (item: LineItem) => {
    item.economy_code_item_id = null; // Reset the item when parent code changes
};

// Rest of your existing methods (document handling, validation, submission) remain the same...
// Fixed File upload handler - prevents duplicate uploads
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
        (newFile) =>
            !allUploadedFiles.value.some(
                (existingFile) =>
                    existingFile.name === newFile.name &&
                    existingFile.size === newFile.size,
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

    if (uniqueNewFiles.length === 0) {
        return;
    }

    // Process unique files
    uniqueNewFiles.forEach((file) => {
        allUploadedFiles.value.push(file);

        if (
            !selectedDocumentType.value ||
            selectedDocumentType.value === 'other'
        ) {
            console.log('Adding as optional document:', file.name);
            optionalDocuments.value.push({
                type: 'other',
                label: 'Additional Document',
                file: file,
                document_type: 'other',
            });
        } else {
            const requiredDoc = requiredDocuments.value.find(
                (doc) => doc.type === selectedDocumentType.value,
            );
            if (requiredDoc) {
                if (requiredDoc.uploaded && requiredDoc.file) {
                    console.log(
                        'Replacing existing required document:',
                        requiredDoc.label,
                    );

                    // Move old file to optional
                    optionalDocuments.value.push({
                        type: 'other',
                        label: 'Replaced Document',
                        file: requiredDoc.file,
                        document_type: 'other',
                    });

                    // Remove old file from allUploadedFiles
                    const oldFileIndex = allUploadedFiles.value.findIndex(
                        (f) => f.name === requiredDoc.file?.name,
                    );
                    if (oldFileIndex > -1) {
                        allUploadedFiles.value.splice(oldFileIndex, 1);
                    }
                }

                console.log(
                    'Assigning to required document:',
                    requiredDoc.label,
                    file.name,
                );
                requiredDoc.uploaded = true;
                requiredDoc.file = file;

                toast.add({
                    severity: 'success',
                    summary: 'Document Added',
                    detail: `${requiredDoc.label} uploaded successfully`,
                    life: 3000,
                });
            }
        }
    });

    // Update form.documents from our single source
    form.documents = [...allUploadedFiles.value];
    selectedDocumentType.value = '';
};

// Manual document type assignment for already uploaded files
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
        }

        requiredDoc.uploaded = true;
        requiredDoc.file = file;

        toast.add({
            severity: 'success',
            summary: 'Document Type Assigned',
            detail: `File assigned as ${requiredDoc.label}`,
            life: 3000,
        });
    }
};

// Remove document assignment
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

// Remove existing document
const removeExistingDocument = (documentId: number) => {
    const docIndex = existingDocuments.value.findIndex(
        (doc) => doc.id === documentId,
    );
    if (docIndex > -1) {
        const document = existingDocuments.value[docIndex];

        // Check if this is a required document
        const requiredDoc = requiredDocuments.value.find(
            (rd) => rd.type === document.document_type,
        );
        if (
            requiredDoc &&
            requiredDoc.uploaded &&
            requiredDoc.existing_document_id === documentId
        ) {
            requiredDoc.uploaded = false;
            requiredDoc.existing_document_id = undefined;
        }

        // Remove from existing documents
        existingDocuments.value.splice(docIndex, 1);

        // Add to delete list
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

// Enhanced document removal
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

    // Remove from all uploaded files
    const allFilesIndex = allUploadedFiles.value.findIndex(
        (file) => file.name === fileToRemove.name,
    );
    if (allFilesIndex > -1) {
        console.log(
            'Removing from all uploaded files at index:',
            allFilesIndex,
        );
        allUploadedFiles.value.splice(allFilesIndex, 1);
    }

    // Update form.documents from our single source
    form.documents = [...allUploadedFiles.value];

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

// Enhanced clear all documents
const clearAllDocuments = () => {
    console.log('Clearing all documents');

    // Reset required documents
    requiredDocuments.value.forEach((doc) => {
        doc.uploaded = false;
        doc.file = undefined;
        doc.existing_document_id = undefined;
    });

    // Clear optional documents
    optionalDocuments.value = [];

    // Clear all uploaded files
    allUploadedFiles.value = [];

    // Clear form documents
    form.documents = [];

    // Clear existing documents and mark all for deletion
    existingDocuments.value.forEach((doc) => {
        documentsToDelete.value.push(doc.id);
    });
    existingDocuments.value = [];
    form.documents_to_delete = documentsToDelete.value;

    validationErrors.value.documents = '';

    // Reset document type selection
    selectedDocumentType.value = '';

    console.log('All documents cleared');
};

// Track upload events
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

    if (!form.voucher_type || form.voucher_type === '') {
        validationErrors.value.voucher_type = 'Voucher type is required';
        isValid = false;
    }

    if (form.voucher_number.length < 5) {
        console.log(form.voucher_number.length);
        validationErrors.value.voucher_number = 'Voucher number is required';
        isValid = false;
    }

    if (form.bank_activity_id < 1) {
        validationErrors.value.year_id = 'Bank Activity is required';
        isValid = false;
        alert('caught here');
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
                'Sub total does not match quantity Ã— unit price';
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
        const hasExistingDocs = existingDocuments.value.length > 0;
        if (
            form.documents.length === 0 &&
            !hasRequiredDocs &&
            !hasExistingDocs
        ) {
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

// Form submission for edit - FIXED document types
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

    // Prepare document types data - include ALL required documents that are uploaded
    const documentTypesData = [];

    // Add required documents that have NEW files (not existing ones)
    requiredDocuments.value
        .filter((doc) => doc.uploaded && doc.file) // Only include newly uploaded files
        .forEach((doc) => {
            documentTypesData.push({
                type: doc.type,
                label: doc.label,
                file_name: doc.file.name,
            });
        });

    // Add optional documents
    optionalDocuments.value.forEach((doc) => {
        documentTypesData.push({
            type: doc.document_type,
            label: doc.label,
            file_name: doc.file.name,
        });
    });

    const submitData = {
        ...form.data(),
        items: form.items.map((item) => ({
            ...item,
            amount: item.sub_total,
        })),
        document_types: documentTypesData, // Include all document types for new files
        documents_to_delete: documentsToDelete.value, // Include documents to delete
    };


    // console.log(form);
    console.log('Edit form data being submitted:', {
        voucher_id: props.voucher.id,
        year_id: form.year_id,
        mda_id: form.mda_id,
        voucher_date: form.voucher_date,
        narration: form.narration,
        voucher_type: form.voucher_type,
        voucher_bank: form.bank_activity_id,
        status: form.status,
        total_amount: form.total_amount,
        items_count: form.items.length,
        documents_count: form.documents.length,
        document_types: documentTypesData,
        documents_to_delete: documentsToDelete.value,
        required_documents: requiredDocuments.value
            .filter((doc) => doc.uploaded)
            .map((doc) => doc.type),
    });

    form.post(`/vouchers/${props.voucher.id}`, {
        preserveScroll: true,
        onSuccess: () => {
            // alert('here');
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

// Save as draft
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

// Submit for approval
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

const debugDocumentData = () => {
    console.log('=== DOCUMENT DEBUG INFO ===');
    console.log('Existing Documents:', existingDocuments.value);
    console.log('Required Documents:', requiredDocuments.value);
    console.log('Optional Documents:', optionalDocuments.value);

    if (existingDocuments.value.length > 0) {
        existingDocuments.value.forEach((doc, index) => {
            console.log(`Existing Doc ${index + 1}:`, {
                id: doc.id,
                file_name: doc.file_name,
                file_path: doc.file_path,
                mime_type: doc.mime_type,
                document_type: doc.document_type,
                document_label: doc.document_label,
                file_size: doc.file_size,
            });
        });
    }
};

// Initialize
onMounted(() => {
    initializeFormItems();
    initializeRequiredDocuments();
    debugDocumentData(); // Add this line
    console.log('Edit form initialized:', {
        voucher: props.voucher,
        existingDocuments: existingDocuments.value,
        requiredDocuments: requiredDocuments.value,
        formItems: form.items,
        initialTotal: form.total_amount,
    });

    // console.log(props.voucher);
    // console.log(props.mdas);

    if (!canEditVoucher.value) {
        toast.add({
            severity: 'error',
            summary: 'Access Denied',
            detail: `Cannot edit voucher with status: ${props.voucher.status}. Only draft, rejected, or returned vouchers can be edited.`,
            life: 5000,
        });
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



const lazyItemsBank: any = ref([]);
const loadingBank = ref(false);
const currentPageBank = ref(0);
const filterValueBank = ref('');

const fetchBankActivityData = async (page: number, filter = '') => {
    loading.value = true;
    try {
        // Use an API endpoint (e.g., /api/items) instead of an Inertia endpoint for XHR requests
        const response = await axios.get(`/bankActivityList?page=${page}&filter=${filter}`);
        const newItems = response.data.data.map((item: { name: any; tag: any; bank_name: any; title: any; account_number: any; id: any; }) => ({ label: item.tag + ' - ' + item.bank_name + ' - ' + item.title + ' - ' + item.account_number, value: item.id }));

        if (page === 1) {
            lazyItemsBank.value = newItems;
        } else {
            lazyItemsBank.value = [...lazyItemsBank.value, ...newItems];
        }
        currentPageBank.value = page;
    } catch (error) {
        console.error("Error fetching data:", error);
    } finally {
        loadingBank.value = false;
    }
};

const onLazyLoadBank = (event) => {
    // Check if we need to load more items based on scroll position
    // The event object provides information about the first and last visible indices
    // You need logic to determine the next page
    // A simple approach is to load the next page every time the event fires (if more data is available)
    fetchBankActivityData(currentPageBank.value + 1, filterValueBank.value);
};

// Handle filtering (if filter="true" is used)
const onFilterBank = (event) => {
    filterValueBank.value = event.value;
    // Reset page to 1 and fetch filtered data
    fetchBankActivityData(1, event.value);
};


fetchBankActivityData(1);


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
                            <Calendar id="voucher_date" v-model="form.voucher_date" dateFormat="yy-mm-dd" class="w-full"
                                :class="{
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
                            <!-- <InputText v-model="form.voucher_type" class="w-full"  /> -->
                            <Select v-model="form.voucher_type" :options="voucherTypes" optionLabel="label"
                                optionValue="value" placeholder="Select Voucher Type" class="w-full" :class="{
                                    'p-invalid':
                                        form.errors.voucher_type ||
                                        validationErrors.voucher_type,
                                }" @change="validationErrors.voucher_type = ''" />

                            <small class="text-500">Type: {{ form.voucher_type }}</small>
                        </div>
                    </div>
                    <div class="col-4">
                        <div class="field">
                            <label class="text-500 mb-1 block text-sm font-semibold">
                                Current Status
                            </label>
                            <InputText :modelValue="voucher.status" class="w-full" disabled />
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
                                <small class="text-500">Voucher Number: {{ form.voucher_number }}</small>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Narration -->
                <div class="field mb-4 grid">
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

                    <DataTable :value="form.items" class="p-datatable-sm" responsiveLayout="scroll">
                        <Column field="description" header="Description" headerStyle="width: 45%">
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

                        <Column field="quantity" header="Qty" headerStyle="width: 15%">
                            <template #body="slotProps">
                                <div class="flex-column flex">
                                    <InputNumber :modelValue="slotProps.data.quantity" @update:modelValue="
                                        handleQuantityChange(
                                            slotProps.data,
                                            $event,
                                        )
                                        " :min="1"  :max-fraction-digits="2"
                                        inputClass="w-full text-center" :class="{
                                            'p-invalid':
                                                slotProps.data.errors?.quantity,
                                        }" />
                                    <small v-if="slotProps.data.errors?.quantity" class="p-error mt-1">
                                        {{ slotProps.data.errors.quantity }}
                                    </small>
                                </div>
                            </template>
                        </Column>

                        <Column field="unit_price" header="Unit Price" headerStyle="width: 20%">
                            <template #body="slotProps">
                                <div class="flex-column flex">
                                    <InputNumber :modelValue="slotProps.data.unit_price" @update:modelValue="
                                        handleUnitPriceChange(
                                            slotProps.data,
                                            $event,
                                        )
                                        " mode="currency" currency="NGN" locale="en-NG" :min="0" 
                                        inputClass="w-full text-right" :class="{
                                            'p-invalid':
                                                slotProps.data.errors
                                                    ?.unit_price,
                                        }" />
                                    <small v-if="slotProps.data.errors?.unit_price" class="p-error mt-1">
                                        {{ slotProps.data.errors.unit_price }}
                                    </small>
                                </div>
                            </template>
                        </Column>

                        <Column field="sub_total" header="Sub Total" headerStyle="width: 15%"
                            bodyClass="font-bold text-right">
                            <template #body="slotProps">
                                <div class="flex-column flex">
                                    <InputNumber :modelValue="slotProps.data.sub_total" @update:modelValue="
                                        handleSubTotalChange(
                                            slotProps.data,
                                            $event,
                                        )
                                        " mode="currency" currency="NGN" locale="en-NG" :min="0" 
                                        inputClass="w-full text-right" :class="{
                                            'p-invalid':
                                                slotProps.data.errors
                                                    ?.sub_total,
                                        }" />
                                    <small v-if="slotProps.data.errors?.sub_total" class="p-error mt-1">
                                        {{ slotProps.data.errors.sub_total }}
                                    </small>
                                </div>
                            </template>
                        </Column>

                        <Column headerStyle="width: 5%" bodyClass="text-center">
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
                                        (4 Required for submission)
                                    </span>
                                </h4>
                                <Button v-if="
                                    form.documents.length > 0 ||
                                    existingDocuments.length > 0
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
                                    <div v-for="doc in requiredDocuments" :key="doc.type" class="col-6 mb-3">
                                        <div class="surface-100 border-round border-1 p-3">
                                            <div class="align-items-center justify-content-between mb-2 flex">
                                                <div class="align-items-center flex gap-2">
                                                    <i :class="doc.uploaded
                                                        ? 'pi pi-check-circle text-green-500'
                                                        : 'pi pi-times-circle text-red-500'
                                                        "></i>
                                                    <span :class="doc.uploaded
                                                        ? 'text-700 font-semibold'
                                                        : 'text-500'
                                                        ">
                                                        {{ doc.label }}
                                                    </span>
                                                    <Badge v-if="!doc.uploaded" value="Required" severity="danger"
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
                                                    {{
                                                        doc.file?.name ||
                                                        'Existing document'
                                                    }}
                                                </small>
                                                <small v-if="doc.file" class="text-500">
                                                    {{
                                                        (
                                                            doc.file!.size /
                                                            1024
                                                        ).toFixed(2)
                                                    }}
                                                    KB
                                                </small>
                                                <div class="mt-2 flex gap-2">
                                                    <Button v-if="doc.file" icon="pi pi-eye" severity="info" text
                                                        size="small" label="View" @click="
                                                            viewDocument(
                                                                doc,
                                                                'required',
                                                            )
                                                            " />
                                                </div>
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

                            <FileUpload mode="advanced" name="documents" :multiple="true" :maxFileSize="10000000"
                                accept="image/*,.pdf,.doc,.docx,.xls,.xlsx" chooseLabel="Attach New Documents"
                                uploadLabel="Upload" cancelLabel="Cancel" @select="onSelect" @remove="onRemove"
                                @upload="onUpload" :auto="false" :customUpload="true" :disabled="form.processing"
                                :class="{
                                    'p-invalid': validationErrors.documents,
                                }">
                                <template #empty>
                                    <p class="text-500">
                                        Drag and drop new files here or click to
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
                                            <strong>Required for
                                                submission:</strong>
                                            Approval Form, Invoice, Receipt,
                                            Delivery Note
                                        </small>
                                    </div>
                                    <div v-if="validationErrors.documents" class="mt-2">
                                        <small class="p-error">{{
                                            validationErrors.documents
                                        }}</small>
                                    </div>
                                </template>
                            </FileUpload>

                            <!-- Existing Documents -->
                            <div v-if="existingDocuments.length > 0" class="mt-4">
                                <h5 class="mb-2 text-blue-600">
                                    Existing Documents:
                                </h5>
                                <ul class="m-0 list-none p-0">
                                    <li v-for="doc in existingDocuments" :key="doc.id"
                                        class="align-items-center justify-content-between surface-50 border-round mb-2 flex p-2">
                                        <div class="align-items-center flex">
                                            <i :class="getDocumentIcon(
                                                doc.mime_type,
                                                doc.file_name,
                                            )
                                                " class="mr-2"></i>
                                            <div>
                                                <span class="font-medium">{{
                                                    doc.file_name
                                                }}</span>
                                                <small class="text-500 block">
                                                    {{ doc.document_label }} â€¢
                                                    {{
                                                        (
                                                            doc.file_size / 1024
                                                        ).toFixed(2)
                                                    }}
                                                    KB
                                                </small>
                                            </div>
                                        </div>
                                        <div class="align-items-center flex gap-2">
                                            <Button icon="pi pi-eye" severity="info" text rounded @click="
                                                viewDocument(
                                                    doc,
                                                    'existing',
                                                )
                                                " title="View document" />
                                            <Button icon="pi pi-times" severity="danger" text rounded @click="
                                                removeExistingDocument(
                                                    doc.id,
                                                )
                                                " title="Remove document" />
                                        </div>
                                    </li>
                                </ul>
                            </div>

                            <!-- Uploaded Files Display with Type Assignment -->
                            <div v-if="form.documents.length > 0" class="mt-3">
                                <h5 class="mb-2">
                                    New Uploaded Files ({{
                                        form.documents.length
                                    }}):
                                </h5>

                                <!-- Unassigned/Optional Documents -->
                                <div v-if="optionalDocuments.length > 0" class="mb-4">
                                    <h6 class="mb-2 text-blue-600">
                                        New Documents to Assign:
                                    </h6>
                                    <div class="grid">
                                        <div v-for="(
doc, index
                                            ) in optionalDocuments" :key="index" class="col-12 mb-3">
                                            <div
                                                class="align-items-center justify-content-between surface-50 border-round flex p-3">
                                                <div class="align-items-center flex gap-3">
                                                    <i :class="getDocumentIcon(
                                                        doc.file.type,
                                                        doc.file.name,
                                                    )
                                                        " class="text-2xl"></i>
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
                                                    <Button icon="pi pi-eye" severity="info" text size="small" @click="
                                                        viewDocument(
                                                            doc,
                                                            'optional',
                                                        )
                                                        " title="View document" />
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
                                        (doc) => doc.uploaded && doc.file,
                                    )
                                " class="mb-3">
                                    <h6 class="mb-2 text-green-600">
                                        New Assigned Required Documents:
                                    </h6>
                                    <ul class="m-0 list-none p-0">
                                        <li v-for="doc in requiredDocuments.filter(
                                            (d) => d.uploaded && d.file,
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
                                                <Button icon="pi pi-eye" severity="info" text size="small" @click="
                                                    viewDocument(
                                                        doc,
                                                        'required',
                                                    )
                                                    " title="View document" />
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

                    <!-- <div class="col-6">
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
                    </div> -->


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
                                <span class="font-semibold">{{ props.schedule?.voucher_count }}</span>
                            </div>
                            <div class="justify-content-between total-row mb-2 flex">
                                <span class="text-500">Total Amount Raised:</span>
                                <span class="font-semibold">{{ formatCurrency(props.schedule?.amount_posted) }}</span>
                            </div>
                            <div class="justify-content-between total-row mb-2 flex">
                                <span class="text-500">Outstanding Balance:</span>
                                <span class="font-semibold">{{ formatCurrency(scheduleTotal -
                                    props.schedule?.amount_posted) }}</span>
                            </div>
                            <div class="justify-content-between total-row mb-2 flex">
                                <span class="text-500">Voucher Subtotal:</span>
                                <span class="font-semibold" :class="{
                                    'text-green-500':
                                        scheduleTotal - (props.schedule?.amount_posted + voucherSubtotal) >= 0,
                                    'text-red-500':
                                        scheduleTotal - (props.schedule?.amount_posted + voucherSubtotal) < 0,
                                }">{{ formatCurrency(voucherSubtotal) }}</span>
                            </div>
                            <Divider />
                            <div class="justify-content-between total-row flex text-xl font-bold" :class="{
                                'text-green-500':
                                    scheduleTotal - (props.schedule?.amount_posted + voucherSubtotal) >= 0,
                                'text-orange-500':
                                    scheduleTotal - (props.schedule?.amount_posted + voucherSubtotal) < 0,
                            }">
                                <span>Voucher Total:</span>
                                <span>{{ formatCurrency(voucherTotal) }}</span>
                            </div>

                            <!-- NEW: Validation Status -->
                            <div v-if="scheduleInfo" class="mt-2">
                                <div v-if="scheduleTotal - (props.schedule?.amount_posted + voucherSubtotal) == 0"
                                    class="align-items-center flex gap-2 text-green-500">
                                    <i class="pi pi-check-circle"></i>
                                    <small class="font-semibold">Total amount on raised vouchers now matches schedule
                                        total</small>
                                </div>
                                <div v-if="scheduleTotal - (props.schedule?.amount_posted + voucherSubtotal) > 0 && voucherTotal > 0"
                                    class="align-items-center flex gap-2 text-orange-400">
                                    <i class="pi pi-exclamation-triangle"></i>
                                    <small class="font-semibold">Total amount on raised vouchers is below the schedule
                                        total.
                                        <br />Please adjust the line items to match the schedule total.
                                        <br />Alternatively, you may
                                        have to add another voucher to this schedule.</small>
                                </div>
                                <div v-if="scheduleTotal - (props.schedule?.amount_posted + voucherSubtotal) < 0"
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
                        <div class="mt-4">
                        <label for="bank_activity_id" class="text-500 mb-1 block text-sm font-semibold">
                            Select destination bank
                        </label>
                        <Dropdown id="bank_activity_id" v-model="form.bank_activity_id" :options="lazyItemsBank" optionLabel="label"
                            optionValue="value" :loading="loadingBank" placeholder="Select destination bank" filter
                            @filter="onFilterBank" class="w-full" />

                        <small class="p-error block" v-if="form.errors?.bank_activity_id">{{
                            form.errors.bank_activity_id
                        }}</small>

                    </div>
                    </div>


                </div>

                <!-- Action Buttons -->
                <div class="justify-content-end mt-5 flex gap-2">
                    <Button label="Update Draft" icon="pi pi-save" severity="secondary" :loading="form.processing"
                        @click="saveDraft" title="Update as draft (documents optional, can edit later)" />
                    <Button label="Submit for Approval" icon="pi pi-send" severity="success" :loading="form.processing"
                        @click="submitForApproval"
                        title="Submit for approval to Internal Audit (requires all documents)" />
                </div>
            </template>
        </Card>

        <!-- Document Viewer Dialog -->
        <!-- Enhanced Document Viewer Dialog -->
        <Dialog v-model:visible="documentViewerVisible" :style="{ width: '90vw', maxWidth: '1200px' }"
            :maximizable="true" modal :header="documentViewerTitle" @hide="closeDocumentViewer">
            <div v-if="currentDocument" class="document-viewer">
                <div class="justify-content-between align-items-center mb-3 flex">
                    <div class="align-items-center flex gap-2">
                        <i :class="getDocumentIcon(
                            currentDocument.type,
                            currentDocument.name,
                        )
                            " class="text-primary"></i>
                        <span class="font-semibold">{{
                            currentDocument.name
                        }}</span>
                    </div>
                    <div class="flex gap-2">
                        <Button v-if="
                            isViewable(currentDocument.type) &&
                            currentDocument.url
                        " icon="pi pi-external-link" label="Open in New Tab" severity="secondary"
                            @click="window.open(currentDocument.url, '_blank')" />
                        <Button icon="pi pi-download" label="Download" severity="info" @click="downloadDocument"
                            :disabled="!currentDocument.url" />
                    </div>
                </div>

                <div class="document-content border-round surface-50 p-3" style="min-height: 400px; max-height: 70vh">
                    <!-- Loading State -->
                    <div v-if="!currentDocument.url"
                        class="flex-column align-items-center justify-content-center flex h-full text-center">
                        <i class="pi pi-spin pi-spinner text-500 mb-3 text-6xl"></i>
                        <h4 class="text-900 mb-2">Loading Document...</h4>
                        <p class="text-600">
                            Please wait while we load the document.
                        </p>
                    </div>

                    <!-- PDF Viewer -->
                    <div v-else-if="currentDocument.type === 'application/pdf'" class="h-full w-full">
                        <iframe :src="currentDocument.url" class="h-full w-full border-none" style="min-height: 400px"
                            frameborder="0" @load="console.log('PDF loaded successfully')"
                            @error="console.error('PDF failed to load')"></iframe>
                    </div>

                    <!-- Image Viewer -->
                    <div v-else-if="currentDocument.type.startsWith('image/')" class="justify-content-center flex">
                        <img :src="currentDocument.url" :alt="currentDocument.name" class="max-h-full max-w-full"
                            style="max-height: 70vh; object-fit: contain"
                            @load="console.log('Image loaded successfully')"
                            @error="console.error('Image failed to load')" />
                    </div>

                    <!-- Text Files -->
                    <div v-else-if="currentDocument.type === 'text/plain'" class="h-full w-full">
                        <iframe :src="currentDocument.url" class="h-full w-full border-none" style="min-height: 400px"
                            frameborder="0"></iframe>
                    </div>

                    <!-- Unsupported File Types -->
                    <div v-else class="flex-column align-items-center justify-content-center flex h-full text-center">
                        <i class="pi pi-file-excel text-500 mb-3 text-6xl"
                            v-if="currentDocument.type.includes('excel')"></i>
                        <i class="pi pi-file-word text-500 mb-3 text-6xl"
                            v-else-if="currentDocument.type.includes('word')"></i>
                        <i class="pi pi-file text-500 mb-3 text-6xl" v-else></i>
                        <h4 class="text-900 mb-2">Preview Not Available</h4>
                        <p class="text-600 mb-4">
                            This file type cannot be previewed in the browser.
                        </p>
                        <Button icon="pi pi-download" label="Download to View" severity="info" @click="downloadDocument"
                            :disabled="!currentDocument.url" />
                    </div>
                </div>

                <div class="justify-content-between align-items-center text-500 mt-3 flex text-sm">
                    <div>
                        <span>File size:
                            {{ (currentDocument.size / 1024).toFixed(2) }}
                            KB</span>
                    </div>
                    <div>
                        <span>Type: {{ currentDocument.type }}</span>
                    </div>
                    <div v-if="currentDocument.url">
                        <span>Status: Loaded</span>
                    </div>
                    <div v-else>
                        <span class="text-red-500">Status: Unavailable</span>
                    </div>
                </div>

                <!-- Debug Info (remove in production) -->
                <div v-if="currentDocument.url" class="surface-100 border-round mt-2 p-2">
                    <small class="text-500">Debug URL: {{ currentDocument.url }}</small>
                </div>
            </div>
        </Dialog>
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

.document-viewer {
    min-height: 500px;
}

.document-content {
    background: var(--p-surface-0);
    border: 1px solid var(--p-surface-200);
}
</style>
