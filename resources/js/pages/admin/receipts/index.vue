<script setup>
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import { computed, onMounted, ref, watch } from 'vue';

// --- Frontend Validation Imports ---
import { useField, useForm as useVeeForm } from 'vee-validate';
import * as yup from 'yup';

// --- PrimeVue Imports ---
import AppLayout from '@/layouts/AppLayout.vue';
import Button from 'primevue/button';
import Calendar from 'primevue/calendar';
import Card from 'primevue/card';
import Column from 'primevue/column';
import DataTable from 'primevue/datatable';
import Dialog from 'primevue/dialog';
import Dropdown from 'primevue/dropdown';
import IconField from 'primevue/iconfield';
import InputIcon from 'primevue/inputicon';
import InputNumber from 'primevue/inputnumber';
import InputText from 'primevue/inputtext';
import Tag from 'primevue/tag';
import Toast from 'primevue/toast';
import { useToast } from 'primevue/usetoast';
import { FilterMatchMode } from '@primevue/core/api';
import axios from 'axios';
// import { route } from 'ziggy-js';


const toast = useToast();

const props = defineProps({
    receipts: { type: Object, required: true },
    mdas: { type: Array, default: () => [] },
    bank_activities: { type: Array, default: () => [] },
    receipt_activities: { type: Array, default: () => [] },
    flash: { type: Object, default: () => ({ message: null }) },
    economyCodes: { type: Array, default: () => [] },
    economyCodeItems: { type: Array, default: () => [] },
});

// --- State Management ---
const showModal = ref(false);
const showImportModal = ref(false);
const isEdit = ref(false);
const currentId = ref(null);
const receiptData = computed(() => props.receipts);

// --- NEW: Save Confirmation Modal State ---
const showSaveConfirmationModal = ref(false);
const saveConfirmationData = ref(null);
const isSaving = ref(false);
const showDuplicateWarning = ref(false);
const duplicateWarningMessage = ref('');

// --- Search State ---
// const searchQuery = ref('');
const searchTimeout = ref(null);

// --- Economic Code State ---
const selected_economy_code_id = ref(null);
const selected_economy_code_item_id = ref(null);
const ecoItemError = ref('');

// --- Form Setup ---
const formDefaults = {
    receipt_number: '',
    mda_name: '',
    eco_code: '', // Will be derived from selected items
    eco_code_item: '', // Will be derived from selected items
    activity: '',
    amount: 0,
    receipt_date: null,
    classification: 'Revenue',
    bank_name: '',
    account_number: '',
    account_name: '',
    economy_code_id: null,
    economy_code_item_id: null,
    status: 'Draft',
    tag: '',
};

// Inertia Forms
const mainForm = useForm(formDefaults);
const importForm = useForm({ file: null });

// VeeValidate Logic
const validationSchema = yup.object({
    receipt_number: yup.string().required('Receipt number is required'),
    mda_name: yup.string().required('MDA name is required'),
    bank_name: yup.string().required('Bank name is required'),
    amount: yup
        .number()
        .required('Amount is required')
        .min(1, 'Amount must be greater than 0')
        .typeError('Amount must be a number'),
    receipt_date: yup.date().required('Receipt date is required'),
    classification: yup.string().required('Classification is required'),
    economy_code_id: yup.number().required('Economic Code is required'),
    economy_code_item_id: yup
        .number()
        .required('Economic Code Item is required'),
});

const { handleSubmit, resetForm, setErrors } = useVeeForm({
    validationSchema: validationSchema,
    initialValues: formDefaults,
});

const { value: receipt_number, errorMessage: numError } =
    useField('receipt_number');
const { value: mda_name, errorMessage: mdaError } = useField('mda_name');
const { value: amount, errorMessage: amountError } = useField('amount');
const { value: receipt_date, errorMessage: dateError } =
    useField('receipt_date');
const { value: classification, errorMessage: classError } =
    useField('classification');
const { value: economy_code_id } = useField('economy_code_id');
const { value: economy_code_item_id } = useField('economy_code_item_id');
const { value: activity, errorMessage: activityError } = useField('activity');
const { value: bank_name } = useField('bank_name');
const { value: account_number } = useField('account_number');
const { value: account_name } = useField('account_name');
const { value: tag } = useField('tag');

// Format bank activities for dropdown
const searchableBankActivities = computed(() => {
    return props.bank_activities.map((item) => ({
        ...item,
        searchLabel: `${item.tag} - ${item.title} (${item.account_number})`,
    }));
});

// Format bank activities for dropdown
const searchableReceiptActivities = computed(() => {
    return props.receipt_activities.map((item) => ({
        ...item,
        searchLabel: `${item.name} - ${item.name}`,
    }));
});

// Economic Code Options
const economyCodeOptions = computed(() => {
    return props.economyCodes.map((code) => ({
        value: code.id,
        label: `${code.code} - ${code.name}`,
        code: code.code, // Store the code separately
        name: code.name, // Store the name separately
    }));
});

// Filtered Economic Code Items
const filteredEconomyCodeItems = computed(() => {
    if (!selected_economy_code_id.value) return [];

    return props.economyCodeItems
        .filter(
            (item) => item.economy_code_id === selected_economy_code_id.value,
        )
        .map((item) => ({
            value: item.id,
            label: `${item.code} - ${item.name}`,
            code: item.code, // Store the code separately
            name: item.name, // Store the name separately
        }));
});

// Find eco code and eco_code_item from selected IDs
const selectedEcoCode = computed(() => {
    if (!selected_economy_code_id.value) return '';

    const code = economyCodeOptions.value.find(
        (ec) => ec.value === selected_economy_code_id.value,
    );
    return code ? code.code : '';
});

const selectedEcoCodeItem = computed(() => {
    if (!selected_economy_code_item_id.value) return '';

    const item = filteredEconomyCodeItems.value.find(
        (eci) => eci.value === selected_economy_code_item_id.value,
    );
    return item ? item.code : '';
});

const selectedEcoCodeString = computed(() => {
    const parentCode = selectedEcoCode.value;
    const itemCode = selectedEcoCodeItem.value;

    if (parentCode && itemCode) {
        return `${parentCode}.${itemCode}`;
    }
    return '';
});

// --- Number to Words Converter ---
const convertNumberToWords = (amount) => {
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

    const convertHundreds = (num) => {
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
    words += words ? ' Naira' : 'Zero Naira';

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
    return convertNumberToWords(amount.value || 0);
});

// Format currency for confirmation modal
const formatCurrencyDisplay = (value) => {
    return new Intl.NumberFormat('en-NG', {
        style: 'currency',
        currency: 'NGN',
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    }).format(value || 0);
};

// Watch for bank selection changes
watch(bank_name, (newId) => {
    if (newId) {
        const selectedActivity = searchableBankActivities.value.find(
            (item) => item.id === newId,
        );
        if (selectedActivity) {
            account_name.value = selectedActivity.title;
            account_number.value = selectedActivity.account_number;
            tag.value = selectedActivity.tag;
        }
    } else {
        // Reset bank-related fields if no bank selected
        account_name.value = '';
        account_number.value = '';
        tag.value = '';
    }
});

// Watch for bank selection changes
watch(activity, (newId) => {
    if (newId) {
        const selectedReceiptActivity = searchableReceiptActivities.value.find(
            (item) => item.id === newId,
        );
        if (selectedReceiptActivity) {
            activity.value = selectedReceiptActivity.name;
        }
    } else {
        // Reset bank-related fields if no bank selected
        activity.value = '';
    }
});

// Watch selected economy code values and update form fields
watch(selected_economy_code_id, (newValue) => {
    economy_code_id.value = newValue;
});

watch(selected_economy_code_item_id, (newValue) => {
    economy_code_item_id.value = newValue;
});

// Handle economy code change
const onEconomyCodeChange = () => {
    // Reset economy code item when parent code changes
    selected_economy_code_item_id.value = null;
    ecoItemError.value = '';
};

// --- Search Functionality ---
// const performSearch = () => {
//     if (searchTimeout.value) {
//         clearTimeout(searchTimeout.value);
//     }

//     searchTimeout.value = setTimeout(() => {
//         router.get(
//             route('receipts.index'),
//             { search: searchQuery.value },
//             {
//                 preserveState: true,
//                 preserveScroll: true,
//                 replace: true,
//             },
//         );
//     }, 500); // 500ms delay
// };
const performSearch = () => {
    if (searchTimeout.value) {
        clearTimeout(searchTimeout.value);
    }

    searchTimeout.value = setTimeout(() => {
        router.get(
            '/receipts',
            {
                search: searchQuery.value,
                page: 1, // Reset to first page when searching
            },
            {
                preserveState: true,
                preserveScroll: true,
                replace: true,
            },
        );
    }, 500);
};

const clearSearch = () => {
    searchQuery.value = '';
    router.get(
        route('receipts.index'),
        {},
        {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        },
    );
};

// --- Handlers ---
const handleCreate = () => {
    isEdit.value = false;
    currentId.value = null;

    // Reset selected economy code and item
    selected_economy_code_id.value = null;
    selected_economy_code_item_id.value = null;
    ecoItemError.value = '';

    resetForm({ values: formDefaults });
    showModal.value = true;
};

const handleEdit = async (data) => {
    isEdit.value = true;
    currentId.value = data.id;

    console.log('=== EDIT RECEIPT DEBUG ===');
    console.log('Receipt data:', data);

    // Reset selected economy code and item first
    selected_economy_code_id.value = null;
    selected_economy_code_item_id.value = null;

    // Find economy code by eco_code string
    if (data.eco_code) {
        const foundCode = economyCodeOptions.value.find(
            (ec) => ec.code === data.eco_code,
        );
        console.log('Found economy code:', foundCode);
        if (foundCode) {
            selected_economy_code_id.value = foundCode.value;
        }
    }

    // Find bank by name or ID
    let bankValue = '';
    if (data.bank_name) {
        // Try to find bank by name first
        const foundBank = searchableBankActivities.value.find(
            (bank) =>
                bank.bank_name === data.bank_name ||
                bank.title === data.bank_name,
        );
        console.log('Found bank:', foundBank);
        if (foundBank) {
            bankValue = foundBank.id; // Use the ID for dropdown
        } else {
            bankValue = data.bank_name; // Fallback to string
        }
    }

    // Find receipt activity
    let activityValue = '';
    if (data.activity) {
        const foundActivity = searchableReceiptActivities.value.find(
            (item) => item.name === data.activity,
        );
        activityValue = foundActivity ? foundActivity.name : data.activity;
    }

    const values = {
        receipt_number: data.receipt_number || '',
        mda_name: data.mda_name || '',
        amount: data.amount || 0,
        receipt_date: data.receipt_date ? new Date(data.receipt_date) : null,
        classification: data.classification || 'Revenue',
        eco_code: data.eco_code || '',
        eco_code_item: data.eco_code_item || '',
        activity: activityValue,
        bank_name: bankValue, // This should be the ID for dropdown
        account_name: data.account_name || '',
        account_number: data.account_number || '',
        economy_code_id: selected_economy_code_id.value,
        economy_code_item_id: null, // Will be set after timeout
        status: data.status || 'Draft',
        tag: data.tag || '',
    };

    console.log('Form values for edit:', values);

    resetForm({ values });
    mainForm.receipt_number = data.receipt_number;
    mainForm.mda_name = data.mda_name;
    mainForm.amount = data.amount;
    mainForm.receipt_date = data.receipt_date
        ? new Date(data.receipt_date)
        : null;
    mainForm.classification = data.classification;
    mainForm.activity = activityValue;
    mainForm.bank_name = bankValue;
    mainForm.account_name = data.account_name;
    mainForm.account_number = data.account_number;
    mainForm.status = data.status;
    mainForm.tag = data.tag;

    // Wait a bit for the form to reset, then find the economy code item
    setTimeout(() => {
        if (data.eco_code_item && selected_economy_code_id.value) {
            const foundItem = filteredEconomyCodeItems.value.find(
                (item) => item.code === data.eco_code_item,
            );
            console.log('Found economy code item:', foundItem);
            if (foundItem) {
                selected_economy_code_item_id.value = foundItem.value;
                economy_code_item_id.value = foundItem.value;
            }
        }
    }, 100);

    showModal.value = true;
};

const handlePrint = (id) => {
    if (!id) {
        console.error('No ID provided for printing');
        toast.add({
            severity: 'error',
            summary: 'Error',
            detail: 'No receipt ID provided for printing',
            life: 3000,
        });
        return;
    }

    // Use the direct URL that we know works
    const printUrl = `/receipts/${id}/print`;

    console.log('Opening print URL:', printUrl);

    // Open in new tab
    const printWindow = window.open(printUrl, '_blank');

    if (!printWindow) {
        toast.add({
            severity: 'warn',
            summary: 'Popup Blocked',
            detail: 'Please allow popups for this site to print receipts.',
            life: 3000,
        });
        return;
    }

    // The print page should handle auto-printing
    console.log('Print window opened, page should auto-print');
};

// --- NEW: Save Confirmation Logic ---
const confirmSaveReceipt = (formData) => {
    // Store the form data for confirmation
    saveConfirmationData.value = {
        ...formData,
        amount: parseFloat(formData.amount),
        eco_code: selectedEcoCode.value,
        eco_code_item: selectedEcoCodeItem.value,
        receipt_date: receipt_date.value
            ? new Date(receipt_date.value).toLocaleDateString('en-GB')
            : 'Not set',
    };

    // Show confirmation modal
    showSaveConfirmationModal.value = true;
};

const proceedWithSave = () => {
    showSaveConfirmationModal.value = false;
    isSaving.value = true;

    // Get current form values
    const formData = {
        receipt_number: receipt_number.value,
        mda_name: mda_name.value,
        amount: parseFloat(amount.value),
        receipt_date: receipt_date.value,
        classification: classification.value,
        economy_code_id: selected_economy_code_id.value,
        economy_code_item_id: selected_economy_code_item_id.value,
        eco_code: selectedEcoCode.value,
        eco_code_item: selectedEcoCodeItem.value,
        activity: activity.value,
        bank_name: bank_name.value,
        account_number: account_number.value || '',
        account_name: account_name.value || '',
        status: status.value,
    };

    // Create a fresh form instance for this submission
    const submitForm = useForm(formData);

    const options = {
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => {
            showModal.value = false;
            isSaving.value = false;
            toast.add({
                severity: 'success',
                summary: 'Success',
                detail: isEdit.value
                    ? 'Receipt updated successfully'
                    : 'Receipt created successfully',
                life: 3000,
            });
        },
        onError: (errors) => {
            console.log('Form errors:', errors);
            isSaving.value = false;
            if (errors.economy_code_id || errors.economy_code_item_id) {
                ecoItemError.value = 'Economic Code fields are required';
            }
            toast.add({
                severity: 'error',
                summary: 'Validation Error',
                detail: 'Please check the form for errors',
                life: 3000,
            });
        },
    };

    if (isEdit.value && currentId.value) {
        const updateUrl = `/receipts/${currentId.value}`;
        submitForm
            .transform((data) => ({
                ...data,
                _method: 'PUT',
            }))
            .post(updateUrl, options);
        lazyParams.value.page = 1;
        // loadReceipts();
        router.reload();
    } else {
        const storeUrl = '/receipts';
        submitForm.post(storeUrl, options);
        lazyParams.value.page = 1;
        router.reload();
    }
};
const proceedWithSaveDraft = () => {
    showSaveConfirmationModal.value = false;
    isSaving.value = true;

    // Get current form values
    const formData = {
        receipt_number: receipt_number.value,
        mda_name: mda_name.value,
        amount: parseFloat(amount.value),
        receipt_date: receipt_date.value,
        classification: classification.value,
        economy_code_id: selected_economy_code_id.value,
        economy_code_item_id: selected_economy_code_item_id.value,
        eco_code: selectedEcoCode.value,
        eco_code_item: selectedEcoCodeItem.value,
        activity: activity.value,
        bank_name: bank_name.value,
        account_number: account_number.value || '',
        account_name: account_name.value || '',
        status: 'Draft',
    };

    // Create a fresh form instance for this submission
    const submitForm = useForm(formData);

    const options = {
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => {
            showModal.value = false;
            isSaving.value = false;
            toast.add({
                severity: 'success',
                summary: 'Success',
                detail: isEdit.value
                    ? 'Receipt updated successfully'
                    : 'Receipt created successfully',
                life: 3000,
            });
        },
        onError: (errors) => {
            console.log('Form errors:', errors);
            isSaving.value = false;
            if (errors.economy_code_id || errors.economy_code_item_id) {
                ecoItemError.value = 'Economic Code fields are required';
            }
            toast.add({
                severity: 'error',
                summary: 'Validation Error',
                detail: 'Please check the form for errors',
                life: 3000,
            });
        },
    };

    if (isEdit.value && currentId.value) {
        // console.log(submitForm);
        const updateUrl = `/receipts/${currentId.value}`;
        submitForm
            .transform((data) => ({
                ...data,
                status: 'Draft',
                _method: 'PUT',
            }))
            .post(updateUrl, options);
        lazyParams.value.page = 1;
        // router.reload();
        window.location.reload();
    } else {
        const storeUrl = '/receipts';
        submitForm.post(storeUrl, options);
        lazyParams.value.page = 1;
        // router.reload();
        window.location.reload();
    }
};

const saveReceipt = handleSubmit(async (values) => {
    console.log('=== SAVE RECEIPT DEBUG ===');
    console.log('isEdit:', isEdit.value);
    console.log('currentId:', currentId.value);

    // Get the selected eco code and item details
    const selectedParentCode = economyCodeOptions.value.find(
        (ec) => ec.value === selected_economy_code_id.value,
    );
    const selectedItem = filteredEconomyCodeItems.value.find(
        (item) => item.value === selected_economy_code_item_id.value,
    );

    // Create form data
    const formData = {
        receipt_number: values.receipt_number,
        mda_name: values.mda_name,
        amount: parseFloat(values.amount),
        receipt_date: values.receipt_date,
        classification: values.classification,
        eco_code: selectedParentCode ? selectedParentCode.code : '',
        eco_code_item: selectedItem ? selectedItem.code : '',
        activity: values.activity || '',
        bank_name: values.bank_name,
        account_number: values.account_number || '',
        account_name: values.account_name || '',
        tag: values.tag || '',
    };

    console.log('Form data to submit:', formData);

    // Show confirmation modal instead of directly saving
    confirmSaveReceipt(formData);
});
const saveReceiptDraft = handleSubmit(async (values) => {
    console.log('=== SAVE RECEIPT DEBUG ===');
    console.log('isEdit:', isEdit.value);
    console.log('currentId:', currentId.value);

    // Get the selected eco code and item details
    const selectedParentCode = economyCodeOptions.value.find(
        (ec) => ec.value === selected_economy_code_id.value,
    );
    const selectedItem = filteredEconomyCodeItems.value.find(
        (item) => item.value === selected_economy_code_item_id.value,
    );

    // Create form data
    const formData = {
        receipt_number: values.receipt_number,
        mda_name: values.mda_name,
        amount: parseFloat(values.amount),
        receipt_date: values.receipt_date,
        classification: values.classification,
        eco_code: selectedParentCode ? selectedParentCode.code : '',
        eco_code_item: selectedItem ? selectedItem.code : '',
        activity: values.activity || '',
        bank_name: values.bank_name,
        account_number: values.account_number || '',
        account_name: values.account_name || '',
        tag: values.tag || '',
        status: 'Draft',
    };

    console.log('Form data to submit:', formData);

    // Show confirmation modal instead of directly saving
    confirmSaveReceipt(formData);
});

// Add this method to handle file selection with validation
const handleFileSelect = (event) => {
    const file = event.target.files[0];

    // Clear any previous errors
    if (importForm.errors.file) {
        importForm.clearErrors('file');
    }

    if (file) {
        // Validate file size (2MB max)
        const maxSize = 2 * 1024 * 1024; // 2MB in bytes
        if (file.size > maxSize) {
            toast.add({
                severity: 'error',
                summary: 'File Too Large',
                detail: 'File size must be less than 2MB',
                life: 3000,
            });
            event.target.value = ''; // Clear the input
            importForm.file = null;
            return;
        }

        // Validate file type
        const fileName = file.name.toLowerCase();
        const validExtensions = ['.csv'];
        const hasValidExtension = validExtensions.some((ext) =>
            fileName.endsWith(ext),
        );

        if (!hasValidExtension) {
            toast.add({
                severity: 'error',
                summary: 'Invalid File Type',
                detail: 'Please select a CSV file (.csv)',
                life: 3000,
            });
            event.target.value = ''; // Clear the input
            importForm.file = null;
            return;
        }

        // Validate MIME type as an additional check
        const validMimeTypes = ['text/csv', 'application/vnd.ms-excel'];
        if (!validMimeTypes.includes(file.type) && file.type !== '') {
            toast.add({
                severity: 'warn',
                summary: 'File Type Warning',
                detail: 'The file may not be a valid CSV. Please ensure it contains comma-separated values.',
                life: 4000,
            });
        }

        importForm.file = file;
    } else {
        importForm.file = null;
    }
};


const submitImport = () => {
    if (!importForm.file) {
        toast.add({
            severity: 'warn',
            summary: 'Warning',
            detail: 'Please select a file to import',
            life: 3000,
        });
        return;
    }

    // Clear any previous errors
    if (importForm.errors.file) {
        importForm.clearErrors('file');
    }

    // Use the direct URL
    const importUrl = '/receipts/import';

    importForm.post(importUrl, {
        preserveScroll: true,
        preserveState: true,
        onSuccess: (page) => {
            // Changed from 'response' to 'page'
            console.log('Import successful, page data:', page);

            showImportModal.value = false;
            importForm.reset();

            // Clear file input
            const fileInput = document.getElementById('fileInput');
            if (fileInput) {
                fileInput.value = '';
            }

            // In Inertia, data is passed in page.props
            // Check for duplicates in the response
            if (page.props?.duplicates && page.props.duplicates.length > 0) {
                duplicateWarningMessage.value = `Found ${page.props.duplicates.length} duplicate receipt numbers. First few: ${page.props.duplicates.slice(0, 5).join(', ')}`;
                showDuplicateWarning.value = true;
            }

            // Check for import errors
            if (
                page.props?.import_errors &&
                page.props.import_errors.length > 0
            ) {
                duplicateWarningMessage.value = `Import completed with ${page.props.import_errors.length} errors. Check logs for details.`;
                showDuplicateWarning.value = true;
            }

            // Show success toast - get message from flash or page props
            let successMessage = 'CSV data imported successfully';

            if (page.props?.flash?.message) {
                successMessage = page.props.flash.message;
            } else if (page.props?.message) {
                successMessage = page.props.message;
            }

            toast.add({
                severity: 'success',
                summary: 'Import Successful',
                detail: successMessage,
                life: 5000,
            });

            // Show import stats if available
            if (page.props?.import_stats) {
                const stats = page.props.import_stats;
                console.log('Import statistics:', stats);

                // You can show additional toast with stats if needed
                if (stats.duplicates > 0 || stats.skipped > 0) {
                    setTimeout(() => {
                        toast.add({
                            severity: 'info',
                            summary: 'Import Details',
                            detail: `Imported: ${stats.imported}, Skipped: ${stats.skipped}, Duplicates: ${stats.duplicates}`,
                            life: 6000,
                        });
                    }, 1000);
                }
            }
        },
        onError: (errors) => {
            console.error('Import errors:', errors);

            let errorMessage = 'Failed to import CSV file';

            // Try different ways to get the error message
            if (errors.message) {
                errorMessage = errors.message;
            } else if (errors.file && errors.file[0]) {
                errorMessage = errors.file[0];
            } else if (typeof errors === 'string') {
                errorMessage = errors;
            } else if (
                errors.errors &&
                errors.errors.file &&
                errors.errors.file[0]
            ) {
                errorMessage = errors.errors.file[0];
            }

            toast.add({
                severity: 'error',
                summary: 'Import Failed',
                detail: errorMessage,
                life: 5000,
            });

            // Reset file input on error
            const fileInput = document.getElementById('fileInput');
            if (fileInput) {
                fileInput.value = '';
            }
        },
        onFinish: () => {
            // Optional: Any cleanup after request completes
        },
    });
};

// Add cancelImport method
const cancelImport = () => {
    showImportModal.value = false;
    importForm.reset();

    // Clear file input
    const fileInput = document.getElementById('fileInput');
    if (fileInput) {
        fileInput.value = '';
    }

    // Clear any errors
    if (importForm.errors.file) {
        importForm.clearErrors('file');
    }
};

const deleteReceipt = (id) => {
    if (
        confirm(
            'Are you sure you want to delete this receipt? This action cannot be undone.',
        )
    ) {
        router.delete(route('receipts.destroy', id), {
            preserveScroll: true,
            onSuccess: () => {
                toast.add({
                    severity: 'info',
                    summary: 'Deleted',
                    detail: 'Receipt deleted successfully',
                    life: 3000,
                });
            },
            onError: () => {
                toast.add({
                    severity: 'error',
                    summary: 'Error',
                    detail: 'Failed to delete receipt',
                    life: 3000,
                });
            },
        });
    }
};

const onPageChange = (event) => {
    console.log('Page change event:', event);

    // Calculate the page number
    const page = Math.floor(event.first / event.rows) + 1;

    // Prepare query parameters
    const queryParams = {
        page: page,
        per_page: event.rows, // Add rows per page
    };

    // Add search query if exists
    if (searchQuery.value) {
        queryParams.search = searchQuery.value;
    }

    console.log('Navigating to page:', page, 'with params:', queryParams);

    router.get(route('receipts.index'), queryParams, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    });
};

const onRowsPerPageChange = (event) => {
    console.log('Rows per page changed:', event.value);

    const queryParams = {
        page: 1, // Reset to first page when changing rows per page
        per_page: event.value,
    };

    // Add search query if exists
    if (searchQuery.value) {
        queryParams.search = searchQuery.value;
    }

    router.get(route('receipts.index'), queryParams, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    });
};

const formatCurrency = (val) => {
    return new Intl.NumberFormat('en-NG', {
        style: 'currency',
        currency: 'NGN',
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    }).format(val || 0);
};

const formatDate = (d) => {
    if (!d) return '';
    return new Date(d).toLocaleDateString('en-NG', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
    });
};

const breadcrumbs = [{ title: 'Receipts', href: '#' }];


const getStatusSeverity = (status) => {
    switch (status) {
        case 'Submitted':
            return 'success';
        case 'Draft':
        default:
            return 'info';
    }
};




const receipts = ref([]);

const searchQuery = ref(""); // Search input

const filters = ref({

    global: { value: null, matchMode: FilterMatchMode.CONTAINS },

    receipt_number: { value: null, matchMode: FilterMatchMode.CONTAINS },

    receipt_date: { value: null, matchMode: FilterMatchMode.CONTAINS },

    mda_name: { value: null, matchMode: FilterMatchMode.CONTAINS },
    eco_code: { value: null, matchMode: FilterMatchMode.CONTAINS },
    eco_code_item: { value: null, matchMode: FilterMatchMode.CONTAINS },
    activity: { value: null, matchMode: FilterMatchMode.CONTAINS },
    classification: { value: null, matchMode: FilterMatchMode.CONTAINS },
    bank_name: { value: null, matchMode: FilterMatchMode.CONTAINS },
    account_name: { value: null, matchMode: FilterMatchMode.CONTAINS },
    account_number: { value: null, matchMode: FilterMatchMode.CONTAINS },
    amount: { value: null, matchMode: FilterMatchMode.CONTAINS },
    tag: { value: null, matchMode: FilterMatchMode.CONTAINS },

    // mda.name: { value: null, matchMode: FilterMatchMode.CONTAINS },

    date: { value: null, matchMode: FilterMatchMode.CONTAINS },
    status: { value: null, matchMode: FilterMatchMode.CONTAINS }
});


const lazyParams = ref({
    first: 0,
    rows: 10,
    page: 1,
});

const totalRecords = ref(0);
const loading = ref(false);
let debounceTimer = null; // Timer for debounce



const loadReceipts = async () => {
    loading.value = true;
    try {
        const response = await axios.get('arsearch', { params: { per_page: lazyParams.value.rows, page: lazyParams.value.page, search: searchQuery.value }, });
        console.log(response.data);
        receipts.value = response.data.receipts;
        totalRecords.value = response.data.paginator.total;
    } catch (error) {
        toast.add({ severity: "error", summary: "Error", detail: "Failed to load data", life: 3000 });
        console.error(error);

    }
    loading.value = false;
};


onMounted(() => {
    // debugVoucherStatuses();
    // console.log( "This is it " + filteredEconomyCodeItems.value);
    // console.log(props.economyCodeItems);
    console.log('=== END DEBUG ===');
    console.log(props);
    console.log('=== END DEBUG ===');
    lazyParams.value.page = 1;

    if (props.flash.message) {
        toast.add({
            severity: 'success',
            summary: 'Success',
            detail: props.flash.message,
            life: 3000,
        });
    }

    loadReceipts();
});


watch(searchQuery, () => {
    clearTimeout(debounceTimer);
    debounceTimer = setTimeout(() => {
        lazyParams.value.page = 1; // Reset to first page when searching
        loadReceipts();
    }, 2000); // 500ms debounce delay

});

const onPage = (event) => {
    lazyParams.value.page = event.page + 1; // Laravel pagination starts at 1
    lazyParams.value.first = event.first;
    lazyParams.value.rows = event.rows;
    loadReceipts();
};


const dt = ref();


const exportCSV = () => {
    dt.value.exportCSV();
};

</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">

        <Head title="Receipt Management" />
        <Toast />

        <Card class="shadow-sm">
            <template #title>
                <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
                    <div>
                        <h2 class="text-2xl font-bold text-gray-800">
                            Receipt Management
                        </h2>
                        <p class="mt-1 text-gray-600">
                            Total: {{ totalRecords }} receipts
                        </p>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <Button label="Import CSV" icon="pi pi-upload" severity="help" @click="showImportModal = true"
                            class="flex-1 md:flex-none" />
                            <Button icon="  pi pi-external-link" label="Export" @click="exportCSV($event)" />
                        <Button label="New Receipt" icon="pi pi-plus" @click="handleCreate"
                            class="flex-1 md:flex-none" />
                    </div>
                </div>
            </template>

            <template #content>
                <!-- Search Bar -->
                <div class="mb-6">
                    <div class="flex flex-col gap-4 md:flex-row md:items-center">
                        <IconField iconPosition="left" class="flex-1">
                            <InputIcon class="pi pi-search text-gray-400" />
                            <InputText v-model="searchQuery" @input="performSearch"
                                placeholder="Search receipts by number, MDA, eco code, amount, bank..."
                                class="w-full" />
                        </IconField>
                        <div class="flex gap-2">
                            <Button label="Clear" icon="pi pi-times" severity="secondary" @click="clearSearch"
                                :disabled="!searchQuery" outlined />
                            <Button label="Search" icon="pi pi-search" @click="performSearch" severity="info" />
                        </div>
                    </div>
                    <small class="mt-2 text-gray-500">
                        Search by: Receipt Number, MDA Name, Eco Code, Amount,
                        Bank Name, Account Name, Account Number
                    </small>
                </div>

                <div class="overflow-x-auto">
                    <DataTable v-model:filters="filters" :value="receipts" dataKey="id" stripedRows
                        responsiveLayout="scroll" class="p-datatable-sm" :emptyMessage="'No vouchers found.'"
                        :paginator="true" :rowsPerPageOptions="[5, 10, 20, 50, 100]" :loading="loading"
                        :rows="lazyParams.rows" :totalRecords="totalRecords" @page="onPage" removableSort
                        :globalFilterFields="['receipt_number', 'mda_name', 'eco_code', 'eco_code_item', 'activity', 'classification', 'bank_name', 'account_name', 'account_number', 'amount', 'receipt_date', 'tag', 'date', 'status']"
                        lazy size="small"
                        paginatorTemplate="RowsPerPageDropdown FirstPageLink PrevPageLink CurrentPageReport NextPageLink LastPageLink"
                        currentPageReportTemplate="{first} to {last} of {totalRecords}"
                        ref="dt"
                        >
                        <Column field="receipt_number" header="Receipt #" sortable :style="{ minWidth: '120px' }"
                        
                        >
                            <template #body="slotProps">
                                <a :href="`/receipts/${slotProps.data.id}`"
                                    class="cursor-pointer font-semibold text-blue-600 hover:text-blue-800 hover:underline">
                                    {{ slotProps.data.receipt_number }}


                                </a>
                            </template>
                        </Column>
                        <!-- <Column
                            field="mda_name"
                            header="MDA"
                            sortable
                            :style="{ minWidth: '180px' }"
                        /> -->
                        <Column field="mda_name" header="MDA" headerStyle="width: 25%">
                            <template #body="slotProps">
                                <span v-if="slotProps.data.mda_name">{{
                                    slotProps.data.mda_name
                                }}</span>
                                <span v-else class="text-500">N/A</span>
                            </template>
                        </Column>
                        <Column field="eco_code" header="Eco Code" sortable :style="{ minWidth: '120px' }" />
                        <Column field="eco_code_item" header="Eco Code Item" sortable :style="{ minWidth: '180px' }" />
                        <Column field="activity" header="Activity" :style="{ minWidth: '380px' }" />
                        <Column field="classification" header="Classification" sortable>
                            <template #body="slotProps">
                                <Tag :value="slotProps.data.classification" :severity="slotProps.data.classification ===
                                    'Revenue'
                                    ? 'success'
                                    : 'info'
                                    " />
                            </template>
                        </Column>
                        <Column field="bank_name" header="Bank" sortable :style="{ minWidth: '150px' }" />
                        <Column field="account_name" header="Account Name" sortable :style="{ minWidth: '180px' }" />
                        <Column field="account_number" header="Account Number" sortable
                            :style="{ minWidth: '180px' }" />
                        <Column field="amount" header="Amount" sortable>
                            <template #body="slotProps">
                                <div class="flex flex-col">
                                    <span class="font-bold">{{
                                        formatCurrency(slotProps.data.amount)
                                    }}</span>
                                    <small v-if="slotProps.data.amount > 0" class="text-xs text-gray-500">
                                        {{
                                            convertNumberToWords(
                                                slotProps.data.amount,
                                            )
                                        }}
                                    </small>
                                </div>
                            </template>
                        </Column>
                        <Column field="receipt_date" header="Date" sortable>
                            <template #body="slotProps">
                                {{ formatDate(slotProps.data.receipt_date) }}
                            </template>
                        </Column>
                        <Column field="status" header="Status" sortable>
                            <template #body="slotProps">
                                <Tag :severity="getStatusSeverity(slotProps.data.status)"
                                    :value="slotProps.data.status"></Tag>
                            </template>
                        </Column>
                        <Column header="Actions" :frozen="true" alignFrozen="right">
                            <template #body="slotProps">
                                <div class="flex gap-1">
                                    <Button icon="pi pi-print" text rounded severity="info"
                                        @click="handlePrint(slotProps.data.id)" title="Print Receipt"
                                        v-tooltip="'Print'" />
                                    <Link :href="'/receipts/' + slotProps.data.id">
                                        <Button icon="pi pi-eye" text rounded severity="secondary"
                                            v-tooltip="'View Details'" />
                                    </Link>
                                    <Button
                                        v-if="slotProps.data.status === 'Draft' || usePage().props.auth.userRoles.includes('Admin') || usePage().props.auth.userRoles.includes('admin')"
                                        icon="pi pi-pencil" text rounded severity="warning"
                                        @click="handleEdit(slotProps.data)" v-tooltip="'Edit'" />
                                    <Button v-if="usePage().props.auth.userRoles.includes('Admin') || usePage().props.auth.userRoles.includes('admin')"  icon="pi pi-trash" text rounded severity="danger" @click="
                                        deleteReceipt(slotProps.data.id)
                                        " v-tooltip="'Delete'" />
                                </div>
                            </template>
                        </Column>
                        <template #empty>
                            <div class="py-8 text-center text-gray-500">
                                <i class="pi pi-inbox mb-2 text-4xl"></i>
                                <p v-if="searchQuery">
                                    No receipts found for "{{ searchQuery }}"
                                </p>
                                <p v-else>No receipts found</p>
                            </div>
                        </template>
                    </DataTable>
                </div>
            </template>
        </Card>

        <!-- Import Modal -->
        <Dialog v-model:visible="showImportModal" header="Import Receipts" modal :style="{ width: '30vw' }"
            :breakpoints="{ '960px': '75vw', '641px': '90vw' }">
            <div class="flex flex-col gap-4">
                <p class="text-sm text-gray-600">
                    Select a CSV file with the following columns:
                </p>
                <div class="rounded bg-gray-50 p-3 text-xs">
                    <code class="mb-1 block">receipt_number, mda_name, eco_code, eco_code_item,
                activity, amount, receipt_date, classification,
                bank_name, account_name, account_number</code>
                    <p class="mt-2 text-gray-500">
                        Note: Date format should be YYYY-MM-DD
                    </p>
                    <p class="mt-1 text-xs text-amber-600">
                        ⚠️ Important: Your CSV should NOT include an 'id'
                        column. The first column should be receipt_number.
                    </p>
                </div>

                <!-- Error display -->
                <div v-if="importForm.errors.file" class="rounded-lg border border-red-200 bg-red-50 p-3">
                    <div class="flex items-start gap-2">
                        <i class="pi pi-exclamation-circle mt-0.5 text-red-500"></i>
                        <p class="text-sm text-red-600">
                            {{ importForm.errors.file }}
                        </p>
                    </div>
                </div>

                <!-- File upload area -->
                <div class="rounded-lg border-2 border-dashed p-4 text-center transition-colors" :class="{
                    'hover:border-primary border-gray-300':
                        !importForm.errors.file,
                    'border-red-300': importForm.errors.file,
                }">
                    <input type="file" accept=".csv" @change="handleFileSelect" class="hidden" id="fileInput"
                        ref="fileInput" />
                    <label for="fileInput" class="cursor-pointer">
                        <i class="pi pi-cloud-upload mb-2 text-4xl" :class="{
                            'text-gray-400': !importForm.file,
                            'text-primary': importForm.file,
                        }"></i>
                        <p class="text-sm" :class="{
                            'text-gray-600': !importForm.file,
                            'text-primary font-medium': importForm.file,
                        }">
                            {{
                                importForm.file
                                    ? importForm.file.name
                                    : 'Click to select CSV file'
                            }}
                        </p>
                        <p class="mt-1 text-xs text-gray-500">
                            Maximum file size: 2MB • CSV format only
                        </p>
                        <p v-if="importForm.file" class="mt-1 text-xs text-green-600">
                            ✓ File selected:
                            {{ (importForm.file.size / 1024).toFixed(1) }} KB
                        </p>
                    </label>
                </div>

                <!-- Progress bar -->
                <div v-if="importForm.progress" class="space-y-1">
                    <div class="flex justify-between text-xs text-gray-600">
                        <span>Uploading...</span>
                        <span>{{ importForm.progress.percentage }}%</span>
                    </div>
                    <progress :value="importForm.progress.percentage" max="100"
                        class="h-2 w-full rounded-full [&::-moz-progress-bar]:rounded-full [&::-webkit-progress-bar]:rounded-full [&::-webkit-progress-value]:rounded-full" />
                </div>
            </div>

            <template #footer>
                <div class="flex w-full justify-between">
                    <Button label="Cancel" text @click="cancelImport" :disabled="importForm.processing" outlined />
                    <Button label="Upload" icon="pi pi-upload" @click="submitImport" :loading="importForm.processing"
                        :disabled="!importForm.file || importForm.processing" severity="info" />
                </div>
            </template>
        </Dialog>
        <!-- Receipt Form Modal -->
        <Dialog v-model:visible="showModal" :header="isEdit ? 'Edit Receipt' : 'Create New Receipt'" modal
            :style="{ width: '50vw', maxWidth: '800px' }" :breakpoints="{
                '960px': '75vw',
                '641px': '100vw',
                '480px': '100vw',
            }">
            <form @submit.prevent="saveReceipt" class="space-y-6 py-2">
                <!-- Transaction Information -->
                <div class="rounded-lg border border-gray-200 bg-gray-50 p-4">
                    <h3
                        class="mb-4 flex items-center gap-2 text-sm font-semibold tracking-wider text-gray-600 uppercase">
                        <i class="pi pi-receipt"></i>
                        Transaction Information
                    </h3>

                    <div class="grid grid-cols-1 gap-4 md:grid-cols-6">
                        <!-- Receipt Number -->
                        <div class="space-y-2">
                            <label class="block text-sm font-medium text-gray-700">
                                Receipt Number
                                <span class="text-red-500">*</span>
                            </label>
                            <IconField iconPosition="left" class="w-full">
                                <InputIcon class="pi pi-hashtag text-gray-400" />
                                <InputText v-model="receipt_number" placeholder="e.g., B638799"
                                    :class="{ 'p-invalid': numError }" class="w-full" />
                            </IconField>
                            <small class="p-error block text-xs" v-if="numError">{{ numError }}</small>
                        </div>

                        <!-- Receipt Date -->
                        <div class="space-y-2">
                            <label class="block text-sm font-medium text-gray-700">
                                Receipt Date <span class="text-red-500">*</span>
                            </label>
                            <Calendar v-model="receipt_date" dateFormat="dd/mm/yy" :class="{ 'p-invalid': dateError }"
                                showIcon placeholder="Select Date" class="w-full" :maxDate="new Date()" />
                            <small class="p-error block text-xs" v-if="dateError">{{ dateError }}</small>
                        </div>
                    </div>
                </div>

                <!-- Financial Details -->
                <div class="rounded-lg border border-gray-200 bg-gray-50 p-4">
                    <h3
                        class="mb-4 flex items-center gap-2 text-sm font-semibold tracking-wider text-gray-600 uppercase">
                        <i class="pi pi-money-bill"></i>
                        Financial Details
                    </h3>

                    <div class="grid grid-cols-1 gap-4 md:grid-cols-4">
                        <!-- Economic Code -->
                        <div class="space-y-2">
                            <label class="block text-sm font-medium text-gray-700">
                                Economic Code
                                <span class="text-red-500">*</span>
                            </label>
                            <Dropdown v-model="selected_economy_code_id" :options="economyCodeOptions"
                                optionLabel="label" optionValue="value" placeholder="Select Economic Code" :class="{
                                    'p-invalid':
                                        ecoItemError &&
                                        !selected_economy_code_id,
                                }" class="w-full" :filter="true" filterPlaceholder="Search Economic Codes..." showClear
                                @change="onEconomyCodeChange" />
                            <small class="p-error block text-xs" v-if="ecoItemError && !selected_economy_code_id">{{
                                ecoItemError }}</small>
                        </div>

                        <!-- Economic Code Item -->
                        <div class="space-y-2">
                            <label class="block text-sm font-medium text-gray-700">
                                Economic Code Item
                                <span class="text-red-500">*</span>
                            </label>
                            <Dropdown v-model="selected_economy_code_item_id" :options="filteredEconomyCodeItems"
                                optionLabel="label" optionValue="value" placeholder="Select Code Item" :class="{
                                    'p-invalid':
                                        ecoItemError &&
                                        selected_economy_code_id &&
                                        !selected_economy_code_item_id,
                                }" class="w-full" :disabled="!selected_economy_code_id" :filter="true"
                                filterPlaceholder="Search code items..." showClear />
                            <small class="p-error block text-xs" v-if="
                                ecoItemError &&
                                selected_economy_code_id &&
                                !selected_economy_code_item_id
                            ">{{ ecoItemError }}</small>
                            <small v-else-if="!selected_economy_code_id" class="text-xs text-gray-500">
                                Select Economic Code first
                            </small>
                        </div>

                        <div class="space-y-2">
                            <label class="block text-sm font-medium text-gray-700">
                                Classification
                                <span class="text-red-500">*</span>
                            </label>
                            <Dropdown v-model="classification" :options="['Revenue']"
                                placeholder="Select Classification" :class="{ 'p-invalid': classError }"
                                class="w-full" />
                            <small class="p-error block text-xs" v-if="classError">{{ classError }}</small>
                        </div>

                        <div class="space-y-2">
                            <label class="block text-sm font-medium text-gray-700">
                                Amount (₦) <span class="text-red-500">*</span>
                            </label>
                            <InputNumber v-model="amount" mode="currency" currency="NGN" locale="en-NG"
                                :class="{ 'p-invalid': amountError }" placeholder="₦ 0.00" class="w-full" :min="0" />
                            <small class="p-error block text-xs" v-if="amountError">{{ amountError }}</small>
                        </div>
                    </div>

                    <!-- Display selected eco codes -->
                    <div v-if="selectedEcoCode && selectedEcoCodeItem" class="mt-3 rounded bg-green-50 p-2">
                        <div class="flex items-center gap-2 text-green-700">
                            <i class="pi pi-check-circle"></i>
                            <div class="text-sm">
                                <div>
                                    <strong>Economic Code:</strong>
                                    {{ selectedEcoCode }}
                                </div>
                                <div>
                                    <strong>Economic Code Item:</strong>
                                    {{ selectedEcoCodeItem }}
                                </div>
                                <div>
                                    <strong>Combined:</strong>
                                    {{ selectedEcoCodeString }}
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Amount in Words Display -->
                    <div class="mt-4">
                        <div class="mb-2 flex items-center gap-2">
                            <i class="pi pi-info-circle text-blue-500"></i>
                            <label class="block text-sm font-medium text-gray-700">
                                Amount in Words:
                            </label>
                        </div>
                        <div class="rounded-md bg-gray-100 p-3">
                            <p class="text-sm font-medium text-gray-800">
                                {{ amountInWords }}
                            </p>
                        </div>
                        <small class="mt-1 block text-xs text-gray-500">
                            Amount will be automatically converted to words as
                            you type
                        </small>
                    </div>
                </div>

                <!-- MDA & Activity -->
                <div class="rounded-lg border border-gray-200 bg-gray-50 p-4">
                    <h3
                        class="mb-4 flex items-center gap-2 text-sm font-semibold tracking-wider text-gray-600 uppercase">
                        <i class="pi pi-building-columns"></i>
                        Ministry & Activity
                    </h3>

                    <div class="space-y-4">
                        <div class="space-y-2">
                            <label class="block text-sm font-medium text-gray-700">
                                MDA Name <span class="text-red-500">*</span>
                            </label>
                            <Dropdown v-model="mda_name" :options="props.mdas" optionLabel="name" optionValue="name"
                                placeholder="Select MDA" :filter="true" :class="{ 'p-invalid': mdaError }"
                                class="w-full" filterPlaceholder="Search MDA..." />
                            <small class="p-error block text-xs" v-if="mdaError">{{ mdaError }}</small>
                        </div>

                        <div class="space-y-2">
                            <label class="block text-sm font-medium text-gray-700">
                                Activity / Description
                                <span class="text-red-500">*</span>
                            </label>
                            <Dropdown v-model="activity" :options="props.receipt_activities" optionLabel="name"
                                optionValue="name" placeholder="Select Activity" :filter="true"
                                :class="{ 'p-invalid': activityError }" class="w-full"
                                filterPlaceholder="Search MDA..." />
                            <small class="p-error block text-xs" v-if="activityError">{{ activityError }}</small>
                        </div>
                    </div>
                </div>

                <!-- Banking Information -->
                <div class="rounded-lg border border-blue-200 bg-blue-50 p-4">
                    <h3
                        class="mb-4 flex items-center gap-2 text-sm font-semibold tracking-wider text-blue-600 uppercase">
                        <i class="pi pi-building"></i>
                        Banking Information
                    </h3>

                    <div class="space-y-4">
                        <div class="space-y-2">
                            <label class="block text-sm font-medium text-gray-700">
                                Bank Name <span class="text-red-500">*</span>
                            </label>
                            <Dropdown v-model="bank_name" :options="searchableBankActivities" optionLabel="searchLabel"
                                optionValue="id" placeholder="Select Bank" :filter="true" class="w-full"
                                filterPlaceholder="Search bank..." showClear>
                                <template #option="slotProps">
                                    <div class="flex items-center gap-3 py-2">
                                        <Tag :value="slotProps.option.tag
                                            .substring(0, 4)
                                            .toUpperCase()
                                            " severity="info" size="small" />
                                        <div class="flex flex-col">
                                            <span class="font-medium">{{
                                                slotProps.option.bank_name
                                            }}</span>
                                            <span class="text-xs text-gray-500">
                                                {{ slotProps.option.title }}
                                                •
                                                {{
                                                    slotProps.option
                                                        .account_number
                                                }}
                                            </span>
                                        </div>
                                    </div>
                                </template>
                                <template #value="slotProps">
                                    <div v-if="slotProps.value" class="flex items-center gap-3">
                                        <Tag :value="searchableBankActivities
                                            .find(
                                                (b) =>
                                                    b.id ===
                                                    slotProps.value,
                                            )
                                            ?.tag.substring(0, 4)
                                            .toUpperCase()
                                            " severity="info" size="small" />
                                        <div>
                                            <span class="font-medium">
                                                {{
                                                    searchableBankActivities.find(
                                                        (b) =>
                                                            b.id ===
                                                            slotProps.value,
                                                    )?.bank_name
                                                }}
                                            </span>
                                        </div>
                                    </div>
                                    <span v-else class="text-gray-400">{{
                                        slotProps.placeholder
                                    }}</span>
                                </template>
                            </Dropdown>
                        </div>

                        <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                            <div class="space-y-2">
                                <label class="block text-sm font-medium text-gray-700">
                                    Account Name
                                </label>
                                <InputText v-model="account_name" readonly class="w-full bg-gray-100"
                                    placeholder="Auto-filled" />
                            </div>

                            <div class="space-y-2">
                                <label class="block text-sm font-medium text-gray-700">
                                    Account Number
                                </label>
                                <InputText v-model="account_number" readonly class="w-full bg-gray-100"
                                    placeholder="Auto-filled" />
                            </div>
                        </div>
                    </div>
                </div>
            </form>

            <template #footer>
                <div
                    class="flex flex-col justify-between gap-3 border-t border-gray-200 pt-4 sm:flex-row sm:items-center">
                    <div class="text-sm text-gray-500">
                        <span class="text-red-500">*</span> Required fields
                    </div>
                    <div class="flex gap-3">
                        <Button label="Cancel" icon="pi pi-times" severity="secondary" @click="showModal = false"
                            :disabled="mainForm.processing || isSaving" outlined />

                        <!-- <Button label="Save Draft" icon="pi pi-times" severity="secondary"
                            @click="proceedWithSaveDraft" :loading="isSaving" :disabled="isSaving" outlined /> -->
                        <Button :label="isEdit ? 'Update Receipt' : 'Create Receipt'
                            " :icon="isEdit ? 'pi pi-save' : 'pi pi-plus'" @click="saveReceipt"
                            :loading="mainForm.processing || isSaving" class="px-6"
                            :disabled="mainForm.processing || isSaving" />
                    </div>
                </div>
            </template>
        </Dialog>

        <!-- Save Confirmation Modal -->
        <Dialog v-model:visible="showSaveConfirmationModal" :style="{ width: '500px' }" :header="isEdit ? 'Confirm Receipt Update' : 'Confirm Receipt Creation'
            " :modal="true" :closable="false">
            <div class="space-y-4">
                <div class="flex items-start gap-4">
                    <i class="pi pi-exclamation-circle mt-1 text-2xl text-blue-500"></i>
                    <div class="flex-1">
                        <p class="mb-2 font-semibold text-gray-700">
                            {{
                                isEdit
                                    ? 'Please confirm the following receipt updates:'
                                    : 'Please confirm the following receipt details:'
                            }}
                        </p>

                        <div class="space-y-3 rounded-lg bg-gray-50 p-4">
                            <div class="grid grid-cols-2 gap-2">
                                <div>
                                    <span class="text-sm text-gray-500">Receipt Number:</span>
                                    <p class="font-medium">
                                        {{
                                            saveConfirmationData?.receipt_number
                                        }}
                                    </p>
                                </div>
                                <div>
                                    <span class="text-sm text-gray-500">Date:</span>
                                    <p class="font-medium">
                                        {{ saveConfirmationData?.receipt_date }}
                                    </p>
                                </div>
                            </div>

                            <div>
                                <span class="text-sm text-gray-500">MDA:</span>
                                <p class="font-medium">
                                    {{ saveConfirmationData?.mda_name }}
                                </p>
                            </div>

                            <div>
                                <span class="text-sm text-gray-500">Economic Codes:</span>
                                <p class="font-medium">
                                    {{
                                        saveConfirmationData?.eco_code || 'N/A'
                                    }}
                                    <span v-if="
                                        saveConfirmationData?.eco_code_item
                                    ">
                                        .{{
                                            saveConfirmationData?.eco_code_item
                                        }}
                                    </span>
                                </p>
                            </div>

                            <div>
                                <span class="text-sm text-gray-500">Activity:</span>
                                <p class="font-medium text-gray-700">
                                    {{
                                        saveConfirmationData?.activity || 'N/A'
                                    }}
                                </p>
                            </div>

                            <div>
                                <span class="text-sm text-gray-500">Bank:</span>
                                <p class="font-medium">


                                    {{
                                        searchableBankActivities.find(
                                            (b) =>
                                                b.id ===
                                                saveConfirmationData?.bank_name,
                                        )?.bank_name
                                    }} - {{
                                        searchableBankActivities.find(
                                            (b) =>
                                                b.id ===
                                                saveConfirmationData?.bank_name,
                                        )?.tag
                                    }}


                                </p>
                            </div>

                            <div class="mt-3 rounded border border-blue-200 bg-blue-50 p-3">
                                <div class="flex items-center justify-between">
                                    <span class="text-sm text-gray-600">Total Amount:</span>
                                    <span class="text-xl font-bold text-blue-700">
                                        {{
                                            formatCurrencyDisplay(
                                                saveConfirmationData?.amount,
                                            )
                                        }}
                                    </span>
                                </div>
                            </div>

                            <div class="mt-2 text-sm text-gray-500">
                                <i class="pi pi-info-circle mr-1"></i>
                                {{
                                    isEdit
                                        ? 'This will update the existing receipt.'
                                        : 'A new receipt will be created with these details.'
                                }}
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <template #footer>
                <div class="flex justify-end gap-2">
                    <Button label="Cancel" icon="pi pi-times" severity="secondary"
                        @click="showSaveConfirmationModal = false" :disabled="isSaving" outlined />

                    <Button label="Save Draft" icon="pi pi-times" severity="secondary"
                        @click="proceedWithSaveDraft" :loading="isSaving" :disabled="isSaving" outlined />

                    <Button :label="isEdit ? 'Update Receipt' : 'Create Receipt'"
                        :icon="isEdit ? 'pi pi-save' : 'pi pi-plus'" :severity="isEdit ? 'warning' : 'success'"
                        @click="proceedWithSave" :loading="isSaving" :disabled="isSaving" autofocus />

                </div>
            </template>
        </Dialog>
        <!-- Duplicate Warning Modal -->
        <Dialog v-model:visible="showDuplicateWarning" header="Import Warnings" :style="{ width: '50vw' }"
            :modal="true">
            <div class="space-y-4">
                <div class="flex items-start gap-3">
                    <i class="pi pi-exclamation-triangle mt-1 text-xl text-yellow-500"></i>
                    <div class="flex-1">
                        <p class="mb-2 text-sm text-gray-700">
                            {{ duplicateWarningMessage }}
                        </p>
                        <p class="text-xs text-gray-500">
                            Duplicate receipt numbers were skipped during
                            import.
                        </p>
                    </div>
                </div>
            </div>
            <template #footer>
                <Button label="OK" @click="showDuplicateWarning = false" autofocus />
            </template>
        </Dialog>
    </AppLayout>
</template>

<style scoped>
:deep(.p-datatable .p-column-header-content) {
    font-weight: 600;
    font-size: 0.875rem;
    text-transform: uppercase;
}

:deep(.p-datatable-tbody tr:hover) {
    background-color: #f8fafc !important;
}

:deep(.p-dialog .p-dialog-header) {
    border-bottom: 1px solid #e5e7eb;
    padding: 1.25rem 1.5rem;
}

:deep(.p-dialog .p-dialog-content) {
    padding: 1.5rem;
}

:deep(.p-dialog .p-dialog-footer) {
    border-top: 1px solid #e5e7eb;
    padding: 1rem 1.5rem;
}

.progress-bar {
    background: linear-gradient(90deg, #3b82f6, #8b5cf6);
}
</style>
