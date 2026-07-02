<script setup>
import AppLayout from '@/layouts/AppLayout.vue';
import {
    canApproveVoucher,
    canRetireVoucher,
    hasPermission,
    hasRole,
    isDevelopmentMode,
} from '@/lib/utils/permissions';
import { Head, router, usePage } from '@inertiajs/vue3';
import axios from 'axios';
import Button from 'primevue/button';
import Card from 'primevue/card';
import Column from 'primevue/column';
import DataTable from 'primevue/datatable';
import Dialog from 'primevue/dialog';
import Dropdown from 'primevue/dropdown';
import InputNumber from 'primevue/inputnumber';
import InputText from 'primevue/inputtext';
import Timeline from 'primevue/timeline';
import Message from 'primevue/message';
import ProgressSpinner from 'primevue/progressspinner';
import Tag from 'primevue/tag';
import Textarea from 'primevue/textarea';
import Toast from 'primevue/toast';
import { useToast } from 'primevue/usetoast';
import { computed, onMounted, ref, watch } from 'vue';

const toast = useToast();

// State for Retirement Modal
const showRetirementModal = ref(false);
const showConfirmationModal = ref(false);
const showConfirmationModalDraft = ref(false);
const currentAction = ref(null);
const showRetirementHistoryModal = ref(false);
const retirementHistory = ref([]);
const loadingRetirementHistory = ref(false);

// API Loading States
const loadingEconomicCodes = ref(false);
const loadingCodeItems = ref(false);
const economicCodes = ref([]);
const codeItems = ref({});

// Add these refs with your other refs
const showDocumentViewer = ref(false);
const documentUrl = ref('');
const currentDocument = ref(null);

// Add these functions
// const viewDocument = (document) => {
//     if (!document) {
//         toast.add({
//             severity: 'info',
//             summary: 'No Document',
//             detail: 'No document attached to this voucher.',
//             life: 3000,
//         });
//         return;
//     }
    
//     let docUrl = document.file_path;
//     if (docUrl && !docUrl.startsWith('http') && !docUrl.startsWith('/')) {
//         docUrl = `/storage/${docUrl}`;
//     } else if (docUrl && !docUrl.startsWith('http') && docUrl.startsWith('/')) {
//         docUrl = `${window.location.origin}${docUrl}`;
//     }
    
//     currentDocument.value = document;
//     documentUrl.value = docUrl;
//     showDocumentViewer.value = true;
// };

// Debug document data
const debugDocument = (document) => {
    console.log('=== DOCUMENT DEBUG ===');
    console.log('Document object:', document);
    console.log('file_path:', document.file_path);
    console.log('file_name:', document.file_name);
    console.log('url:', document.url);
    console.log('storage_path:', `/storage/${document.file_path}`);
    console.log('======================');
};

const viewDocument = (document) => {
    console.log('=== VIEW DOCUMENT CALLED ===');
    console.log('Document:', document);
    
    if (!document) {
        toast.add({
            severity: 'info',
            summary: 'No Document',
            detail: 'No document attached to this voucher.',
            life: 3000,
        });
        return;
    }
    
    // Debug the document
    debugDocument(document);
    
    // Try multiple possible URL formats
    let docUrl = '';
    
    // Check if there's a direct URL
    if (document.url) {
        docUrl = document.url;
        console.log('Using document.url:', docUrl);
    } 
    // Check if there's a file_path
    else if (document.file_path) {
        // Check if it already starts with /storage or http
        if (document.file_path.startsWith('/storage') || document.file_path.startsWith('http')) {
            docUrl = document.file_path;
        } else {
            // Add /storage/ prefix
            docUrl = `/storage/${document.file_path}`;
        }
        console.log('Using file_path with storage prefix:', docUrl);
    }
    // Check if there's a path property
    else if (document.path) {
        docUrl = document.path;
        console.log('Using document.path:', docUrl);
    }
    // Fallback - try to construct from file_name
    else if (document.file_name) {
        docUrl = `/storage/documents/${document.file_name}`;
        console.log('Fallback using file_name:', docUrl);
    }
    
    // Ensure URL is absolute if it's a relative path
    if (docUrl && !docUrl.startsWith('http') && !docUrl.startsWith('data:')) {
        docUrl = window.location.origin + (docUrl.startsWith('/') ? '' : '/') + docUrl;
        console.log('Made absolute URL:', docUrl);
    }
    
    console.log('Final document URL:', docUrl);
    
    currentDocument.value = document;
    documentUrl.value = docUrl;
    showDocumentViewer.value = true;
};

// Download document
const downloadDocument = () => {
    if (documentUrl.value) {
        window.open(documentUrl.value, '_blank');
    } else {
        toast.add({
            severity: 'error',
            summary: 'Error',
            detail: 'Document URL not available',
            life: 3000,
        });
    }
};

// Open in new tab
const openInNewTab = () => {
    if (documentUrl.value) {
        window.open(documentUrl.value, '_blank');
    } else {
        toast.add({
            severity: 'error',
            summary: 'Error',
            detail: 'Document URL not available',
            life: 3000,
        });
    }
};

// Handle iframe errors
const handleIframeError = (event) => {
    console.error('Iframe error:', event);
    toast.add({
        severity: 'warn',
        summary: 'Preview Unavailable',
        detail: 'The document could not be previewed. Please download it to view.',
        life: 5000,
    });
};

// Handle image errors
const handleImageError = (event) => {
    console.error('Image error:', event);
    toast.add({
        severity: 'warn',
        summary: 'Image Preview Unavailable',
        detail: 'The image could not be loaded. Please download it to view.',
        life: 5000,
    });
};

// Handle image load
const handleImageLoad = () => {
    console.log('Image loaded successfully');
};

const closeDocumentViewer = () => {
    showDocumentViewer.value = false;
    documentUrl.value = '';
    currentDocument.value = null;
};

// Helper to determine document icon
const getDocumentIcon = (fileName) => {
    if (!fileName) return 'pi pi-file';
    const ext = fileName.split('.').pop()?.toLowerCase();
    const icons = {
        pdf: 'pi pi-file-pdf text-red-500',
        doc: 'pi pi-file-word text-blue-500',
        docx: 'pi pi-file-word text-blue-500',
        xls: 'pi pi-file-excel text-green-500',
        xlsx: 'pi pi-file-excel text-green-500',
        ppt: 'pi pi-file-powerpoint text-orange-500',
        pptx: 'pi pi-file-powerpoint text-orange-500',
        jpg: 'pi pi-image text-purple-500',
        jpeg: 'pi pi-image text-purple-500',
        png: 'pi pi-image text-purple-500',
        gif: 'pi pi-image text-purple-500',
        svg: 'pi pi-image text-purple-500',
        txt: 'pi pi-file text-gray-500',
        zip: 'pi pi-file-archive text-yellow-500',
        rar: 'pi pi-file-archive text-yellow-500',
    };
    return icons[ext] || 'pi pi-file text-gray-500';
};

// Helper to get document type label
const getDocumentTypeLabel = (document) => {
    return document.document_label || document.document_type || 'Document';
};

// Retirement form state
const retirementForm = ref({
    line_items: [],
    total_amount: 0,
    comment: '',
});

// Retirement status data from API
const retirementStatusData = ref(null);

// Props: Receive voucher data from Laravel controller
const props = defineProps({
    voucher: {
        type: Object,
        required: true,
        default: () => ({}),
    },
});

// Format datetime
const formatDateTime = (date) => {
    if (!date) return 'N/A';
    return new Date(date).toLocaleString('en-GB', {
        day: '2-digit',
        month: 'short',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
};

// Get approval action severity
const getApprovalActionSeverity = (action) => {
    const severities = {
        'Approved': 'success',
        'Paid': 'success',
        'Closed': 'success',
        'MAS Approved': 'success',
        'TCO Approved': 'success',
        'Declined': 'danger',
        'Rejected': 'danger',
        'Forwarded': 'info',
        'Submitted': 'info',
        'Sent Back': 'warning',
        'Returned': 'warning',
        'Created': 'info',
        'Saved': 'secondary',
        'Updated': 'secondary',
    };
    return severities[action] || 'info';
};

const isLocallyApproved = ref(props.voucher.status === 'Approved');
const emit = defineEmits(['approve', 'retire']);
const page = usePage();

// User info from Laravel backend
const currentUser = computed(() => page.props.auth?.user || {});
const currentUserId = computed(() => currentUser.value?.id);

// Show conditions for buttons - SIMPLIFIED
const showApproveButton = computed(() => {
    return (
        props.voucher.voucher_type === 'prepayment' &&
        props.voucher.status === 'Submitted' &&
        !isLocallyApproved.value
    );
});

const showRetireButton = computed(() => {
    return (
        props.voucher.voucher_type === 'prepayment' &&
        (props.voucher.status === 'Approved' || isLocallyApproved.value)
    );
});

// Permission checks - SIMPLIFIED WITH DEVELOPMENT MODE
const canApprove = computed(() => {
    if (!showApproveButton.value) return false;
    if (isDevelopmentMode() && currentUserId.value) {
        console.log('DEV MODE: Allowing approval');
        return true;
    }
    return canApproveVoucher(props.voucher);
});

const canRetire = computed(() => {
    if (!showRetireButton.value) return false;
    if (isDevelopmentMode() && currentUserId.value) {
        console.log('DEV MODE: Checking retirement status only');
        if (!retirementStatusData.value) return false;
        return (
            !retirementStatusData.value.already_retired &&
            retirementStatusData.value.can_retire
        );
    }
    return canRetireVoucher(props.voucher, retirementStatusData.value);
});

// Add debug logging
watch(
    () => retirementStatusData.value,
    (newVal) => {
        console.log('Retirement status updated:', newVal);
        console.log('Show Approve Button:', showApproveButton.value);
        console.log('Show Retire Button:', showRetireButton.value);
        console.log('Can Approve:', canApprove.value);
        console.log('Can Retire:', canRetire.value);
    },
    { immediate: true },
);

// Tooltip messages - SIMPLIFIED
const approveTooltip = computed(() => {
    if (!canApprove.value) {
        const reasons = [];
        if (props.voucher.voucher_type !== 'prepayment') {
            reasons.push('Not a prepayment voucher');
        }
        if (props.voucher.status !== 'Submitted') {
            reasons.push(
                `Status: ${props.voucher.status} (needs to be Submitted)`,
            );
        }
        if (!isDevelopmentMode()) {
            if (!hasRole(['Final Account', 'admin'])) {
                reasons.push(
                    `Insufficient role (needs: Final Account or admin)`,
                );
            }
            if (!hasPermission('approve_vouchers')) {
                reasons.push('No approval permission');
            }
        }
        return reasons.join(' • ') || 'Cannot approve voucher';
    }
    return 'Approve this prepayment voucher for retirement';
});

const retireTooltip = computed(() => {
    if (!canRetire.value) {
        const reasons = [];
        if (!retirementStatusData.value) {
            return 'Loading retirement status...';
        }
        if (retirementStatusData.value.already_retired) {
            return 'Voucher already retired';
        }
        if (props.voucher.status !== 'Approved') {
            return `Status: ${props.voucher.status} (needs to be Approved)`;
        }
        if (!retirementStatusData.value.can_retire) {
            return 'Voucher not ready for retirement';
        }
        if (!isDevelopmentMode()) {
            if (!hasRole(['Final Account', 'admin'])) {
                reasons.push(
                    `Insufficient role (needs: Final Account or admin)`,
                );
            }
            if (!hasPermission('retire_vouchers')) {
                reasons.push('No retirement permission');
            }
        }
        return reasons.join(' • ') || 'Cannot retire voucher';
    }
    return 'Retire prepayment voucher';
});

// Approval function
const approveVoucher = async () => {
    try {
        toast.add({
            severity: 'info',
            summary: 'Approving...',
            detail: 'Please wait while we approve the voucher',
            life: 5000,
        });

        await axios.put(
            `/vouchers/${props.voucher.id}/approve`,
            {
                approved_by: currentUserId.value,
                approved_at: new Date().toISOString(),
                status: 'Approved',
            },
        ).then((response) => {
            if (response.data.success === 'true' || response.data.success === true) {
                console.log('Response:', response.data);
                canRetire.value = true;
                retirementStatusData.value = {
                    can_retire: true,
                    already_retired: false,
                    retired_amount: 0,
                    available_balance: props.voucher.total_amount || 0,
                };
            }
            router.reload({ only: ['voucher'] });
        }).catch((error) => {
            console.error('Failed to approve voucher:', error);
        });

        isLocallyApproved.value = true;
        if (retirementStatusData.value) {
            retirementStatusData.value = {
                ...retirementStatusData.value,
                voucher_status: 'Approved',
                can_retire: true,
                can_be_approved: false,
            };
        }

        toast.add({
            severity: 'success',
            summary: 'Voucher Approved',
            detail: 'Prepayment voucher has been approved for retirement.',
            life: 3000,
        });
    } catch (error) {
        console.error('Failed to approve voucher:', error);
        let errorMessage = 'Failed to approve voucher.';
        if (error.response?.data?.message) {
            errorMessage = error.response.data.message;
        } else if (error.response?.data?.errors) {
            errorMessage = Object.values(error.response.data.errors)
                .flat()
                .join(', ');
        }
        toast.add({
            severity: 'error',
            summary: 'Approval Failed',
            detail: errorMessage,
            life: 5000,
        });
    }
};

const confirmDraftVoucher = async () => {
    showConfirmationModalDraft.value = false;
    try {
        toast.add({
            severity: 'info',
            summary: 'saving as draft...',
            detail: 'Please wait while we save the voucher as draft',
            life: 5000,
        });

        await axios.put(
            `/vouchers/${props.voucher.id}/draft`,
            {
                approved_by: currentUserId.value,
                approved_at: new Date().toISOString(),
                status: 'Draft',
            },
        ).then((response) => {
            if (response.data.success === 'true' || response.data.success === true) {
                console.log('Response:', response.data);
            }
            goBack();
            router.reload({ only: ['voucher'] });
        }).catch((error) => {
            console.error('Failed to save voucher as draft:', error);
        });

        toast.add({
            severity: 'success',
            summary: 'Voucher Saved as Draft',
            detail: 'Voucher has been saved as draft.',
            life: 3000,
        });
    } catch (error) {
        console.error('Failed to save voucher as draft:', error);
        let errorMessage = 'Failed to save voucher as draft.';
        if (error.response?.data?.message) {
            errorMessage = error.response.data.message;
        }
        toast.add({
            severity: 'error',
            summary: 'Save as Draft Failed',
            detail: errorMessage,
            life: 5000,
        });
    }
};

// Retirement function for opening the modal
const openRetirementModal = async () => {
    if (!canRetire.value) return;
    if (!retirementStatusData.value || !retirementStatusData.value.can_retire) {
        toast.add({
            severity: 'error',
            summary: 'Cannot Retire',
            detail: retirementStatusData.value?.already_retired
                ? `Voucher ${props.voucher.voucher_number} is already fully retired.`
                : `Voucher ${props.voucher.voucher_number} cannot be retired. Only approved prepayment vouchers with available balance can be retired.`,
            life: 5000,
        });
        return;
    }
    try {
        await initializeRetirementForm();
        showRetirementModal.value = true;
    } catch (error) {
        console.error('Error opening retirement modal:', error);
        toast.add({
            severity: 'error',
            summary: 'Error',
            detail: 'Failed to open retirement form',
            life: 5000,
        });
    }
};

// --- LIFECYCLE HOOKS ---
onMounted(() => {
    fetchRetirementStatus();
    loadApprovals();
});

// --- API FUNCTIONS ---
const fetchRetirementStatus = async () => {
    try {
        const response = await axios.get(
            `/api/vouchers/${props.voucher.id}/retirement-status`,
        );
        retirementStatusData.value = response.data;
        console.log('Retirement status fetched:', retirementStatusData.value);
    } catch (error) {
        console.error('Error fetching retirement status:', error);
        toast.add({
            severity: 'warn',
            summary: 'Warning',
            detail: 'Could not load retirement status',
            life: 3000,
        });
    }
};

const fetchEconomicCodes = async () => {
    try {
        loadingEconomicCodes.value = true;
        const response = await axios.get('/economy-codes', {
            params: {
                mda_id: props.voucher.mda_id || props.voucher.mda?.id,
            },
        });

        if (response.data && response.data.data) {
            economicCodes.value = response.data.data.map((item) => ({
                id: item.id,
                code: item.code,
                name: item.name,
                label: item.code + ' - ' + item.name,
            }));
        } else if (Array.isArray(response.data)) {
            economicCodes.value = response.data.map((item) => ({
                id: item.id || item.value,
                code: item.code || item.label,
                name: item.name || item.label,
                label: item.code || item.label,
            }));
        } else {
            economicCodes.value = [];
        }

        console.log('Fetched economic codes:', economicCodes.value);
    } catch (error) {
        console.error('Error fetching economic codes:', error);
        toast.add({
            severity: 'error',
            summary: 'Error',
            detail: 'Failed to load economic codes',
            life: 5000,
        });
        economicCodes.value = [];
    } finally {
        loadingEconomicCodes.value = false;
    }
};

const fetchCodeItems = async (economicCodeId) => {
    if (!economicCodeId) return [];
    try {
        loadingCodeItems.value = true;
        if (codeItems.value[economicCodeId]) {
            return codeItems.value[economicCodeId];
        }

        const response = await axios.get(
            `/economy-code-items/${economicCodeId}`,
            {
                params: {
                    mda_id: props.voucher.mda_id || props.voucher.mda?.id,
                },
            },
        );

        let items = [];
        if (response.data && response.data.data) {
            items = response.data.data.map((item) => ({
                id: item.id,
                code: item.code,
                name: item.name,
                economy_code_id: item.economy_code_id,
                label: item.code + ' - ' + item.name,
            }));
        } else if (Array.isArray(response.data)) {
            items = response.data.map((item) => ({
                id: item.id || item.value,
                code: item.code || item.label,
                name: item.name || item.label,
                economy_code_id: item.economy_code_id || economicCodeId,
                label: item.code || item.label,
            }));
        }

        codeItems.value[economicCodeId] = items;
        return items;
    } catch (error) {
        console.error('Error fetching code items:', error);
        toast.add({
            severity: 'warn',
            summary: 'Warning',
            detail: 'Failed to load code items for selected economic code',
            life: 3000,
        });
        return [];
    } finally {
        loadingCodeItems.value = false;
    }
};

// --- RETIREMENT FORM FUNCTIONS ---
const initializeRetirementForm = async () => {
    console.log('Initializing retirement form for voucher:', props.voucher.id);
    try {
        await fetchEconomicCodes();
        retirementForm.value.line_items = [
            {
                temp_id: Date.now(),
                description: '',
                economic_code_id: null,
                code_item_id: null,
                quantity: 1,
                unit_price: 0,
                sub_total: 0,
            },
        ];
        retirementForm.value.total_amount = 0;
        retirementForm.value.comment = '';
        calculateTotals();
        console.log('Retirement form initialized:', retirementForm.value);
    } catch (error) {
        console.error('Error initializing retirement form:', error);
        toast.add({
            severity: 'error',
            summary: 'Error',
            detail: 'Failed to initialize retirement form',
            life: 5000,
        });
        retirementForm.value = {
            line_items: [
                {
                    temp_id: Date.now(),
                    description: '',
                    economic_code_id: null,
                    code_item_id: null,
                    quantity: 1,
                    unit_price: 0,
                    sub_total: 0,
                },
            ],
            total_amount: 0,
            comment: '',
        };
    }
};

const submitRetirement = () => {
    const availableBalance = retirementStatusData.value?.available_balance || 0;

    console.log('=== BALANCE CHECK ===');
    console.log('Available balance:', availableBalance);
    console.log('Trying to retire:', retirementForm.value.total_amount);

    if (availableBalance <= 0) {
        const alreadyRetired = retirementStatusData.value?.retired_amount || 0;
        toast.add({
            severity: 'error',
            summary: 'No Balance Available',
            detail: `This voucher has no available balance to retire. Already retired: ${formatCurrency(alreadyRetired)}`,
            life: 5000,
        });
        return;
    }

    if (retirementForm.value.total_amount > availableBalance) {
        toast.add({
            severity: 'error',
            summary: 'Amount Exceeds Balance',
            detail: `Cannot retire ${formatCurrency(retirementForm.value.total_amount)}. Available balance: ${formatCurrency(availableBalance)}`,
            life: 5000,
        });
        return;
    }

    if (!isRetirementValid.value) {
        toast.add({
            severity: 'error',
            summary: 'Invalid Retirement',
            detail: 'Selected amount exceeds voucher total or is zero.',
            life: 5000,
        });
        return;
    }

    const retirementData = {
        line_items: retirementForm.value.line_items
            .filter((item) => item.unit_price > 0 && item.quantity > 0)
            .map((item) => ({
                description: item.description,
                economic_code_id: item.economic_code_id,
                code_item_id: item.code_item_id,
                quantity: item.quantity,
                unit_price: item.unit_price,
                sub_total: item.sub_total,
            })),
        total_amount: retirementForm.value.total_amount,
        comment: retirementForm.value.comment,
        remaining_balance: remainingBalance.value,
        is_partial: remainingBalance.value > 0,
        voucher_id: props.voucher.id,
        schedule_id: props.voucher.schedule_id || null,
        year_id: props.voucher.year_id || null,
        mda_id: props.voucher.mda_id || null,
        bank_activity_id: props.voucher.bank_activity_id || null,
    };

    console.log('Submitting retirement with data:', retirementData);

    router.post(`/vouchers/${props.voucher.id}/retire`, retirementData, {
        preserveScroll: true,
        onSuccess: () => {
            toast.add({
                severity: 'success',
                summary: 'Retired',
                detail:
                    remainingBalance.value === 0
                        ? `Prepayment voucher ${props.voucher.voucher_number} fully retired.`
                        : `Partial retirement submitted for ${props.voucher.voucher_number}. ${formatCurrency(remainingBalance.value)} remaining.`,
                life: 5000,
            });
            showRetirementModal.value = false;
            router.reload({ only: ['voucher'] });
        },
        onError: (errors) => {
            console.error('Error response:', errors);
            let errorDetail = 'Failed to retire the voucher.';
            if (errors.response?.data?.errors?.total_amount) {
                errorDetail = errors.response.data.errors.total_amount[0];
            } else if (errors.message) {
                errorDetail = errors.message;
            }
            toast.add({
                severity: 'error',
                summary: 'Error',
                detail: errorDetail,
                life: 5000,
            });
        },
    });
};

// --- RETIREMENT HELPER FUNCTIONS ---
const getCodeItems = (economicCodeId) => {
    if (!economicCodeId) return [];
    return codeItems.value[economicCodeId] || [];
};

const onEconomicCodeChange = async (item, economicCodeId) => {
    console.log('Economic code changed to:', economicCodeId, 'for item:', item);
    item.code_item_id = null;
    if (economicCodeId) {
        const items = await fetchCodeItems(economicCodeId);
        console.log('Code items fetched:', items);
        if (!item.description && economicCodes.value.length > 0) {
            const selectedCode = economicCodes.value.find(
                (ec) => ec.id == economicCodeId,
            );
            if (selectedCode) {
                item.description = selectedCode.name || selectedCode.label;
                console.log('Auto-populated description:', item.description);
            }
        }
    }
    calculateTotals();
};

const addNewItem = () => {
    retirementForm.value.line_items.push({
        temp_id: Date.now(),
        description: '',
        economic_code_id: null,
        code_item_id: null,
        quantity: 1,
        unit_price: 0,
        sub_total: 0,
    });
};

const removeItem = (index) => {
    if (retirementForm.value.line_items.length > 1) {
        retirementForm.value.line_items.splice(index, 1);
        calculateTotals();
    }
};

const clearAllItems = () => {
    if (confirm('Are you sure you want to clear all items?')) {
        retirementForm.value.line_items = [
            {
                temp_id: Date.now(),
                description: '',
                economic_code_id: null,
                code_item_id: null,
                quantity: 1,
                unit_price: 0,
                sub_total: 0,
            },
        ];
        calculateTotals();
    }
};

const incrementQty = (item) => {
    item.quantity++;
    calculateSubTotal(item);
    calculateTotals();
};

const decrementQty = (item) => {
    if (item.quantity > 1) {
        item.quantity--;
        calculateSubTotal(item);
        calculateTotals();
    }
};

const calculateSubTotal = (item) => {
    item.sub_total = item.quantity * item.unit_price;
};

const calculateTotals = () => {
    let total = 0;
    retirementForm.value.line_items.forEach((item) => {
        calculateSubTotal(item);
        total += item.sub_total;
    });
    retirementForm.value.total_amount = total.toFixed(2) || total;
    console.log('This is the computed totals');
    console.log(retirementForm.value.total_amount);
};

const autoBalanceToZero = () => {
    if (remainingBalance.value > 0) {
        const itemsWithAmounts = retirementForm.value.line_items.filter(
            (item) => item.sub_total > 0,
        );
        if (itemsWithAmounts.length > 0) {
            const distribution =
                remainingBalance.value / itemsWithAmounts.length;
            itemsWithAmounts.forEach((item) => {
                if (item.quantity > 0) {
                    item.unit_price += distribution / item.quantity;
                }
            });
            calculateTotals();
            toast.add({
                severity: 'success',
                summary: 'Auto-balanced',
                detail: `Distributed ${formatCurrency(remainingBalance.value)} across ${itemsWithAmounts.length} items`,
                life: 3000,
            });
        }
    }
};

// Add these computed properties for retirement history
const totalRetiredAmount = computed(() => {
    return retirementHistory.value.reduce(
        (sum, retirement) => sum + (retirement.retired_amount || 0),
        0,
    );
});

const averageRetirementAmount = computed(() => {
    if (retirementHistory.value.length === 0) return 0;
    return totalRetiredAmount.value / retirementHistory.value.length;
});

const viewRetirementHistory = async () => {
    try {
        loadingRetirementHistory.value = true;
        const response = await axios.get(
            `/api/vouchers/${props.voucher.id}/retirement-history`,
        );
        console.log('Retirement history:', response.data);
        retirementHistory.value = response.data.data || [];
        showRetirementHistoryModal.value = true;
        if (retirementHistory.value.length > 0) {
            toast.add({
                severity: 'info',
                summary: 'Retirement History',
                detail: `${retirementHistory.value.length} retirement entries found`,
                life: 3000,
            });
        }
    } catch (error) {
        console.error('Error fetching retirement history:', error);
        toast.add({
            severity: 'error',
            summary: 'Error',
            detail: 'Failed to load retirement history',
            life: 5000,
        });
    } finally {
        loadingRetirementHistory.value = false;
    }
};

const exportRetirementHistory = () => {
    if (retirementHistory.value.length === 0) {
        toast.add({
            severity: 'warn',
            summary: 'No Data',
            detail: 'No retirement history to export',
            life: 3000,
        });
        return;
    }
    toast.add({
        severity: 'success',
        summary: 'Export Started',
        detail: `Exporting ${retirementHistory.value.length} retirement records`,
        life: 3000,
    });
    console.log('Export retirement history:', retirementHistory.value);
};

// --- COMPUTED PROPERTIES ---
const retirementProgress = computed(() => {
    if (!retirementStatusData.value) return 0;
    const total = props.voucher.amount || props.voucher.total_amount || 0;
    if (total === 0) return 0;
    const progress = (retirementStatusData.value.retired_amount / total) * 100;
    return Math.min(Math.max(progress, 0), 100);
});

const getRetirementStatusText = () => {
    if (!retirementStatusData.value) return 'Loading...';
    if (
        retirementStatusData.value.already_retired ||
        retirementProgress.value === 100
    ) {
        return 'Fully Retired';
    } else if (retirementStatusData.value.retired_amount > 0) {
        return 'Partially Retired';
    } else if (retirementStatusData.value.can_retire) {
        return 'Ready to Retire';
    } else {
        return 'Cannot Retire';
    }
};

const getRetirementStatusSeverity = (status) => {
    if (!status) return 'info';
    const normalizedStatus = status.toLowerCase().trim();
    switch (normalizedStatus) {
        case 'approved':
        case 'completed':
        case 'retired':
        case 'fully retired':
            return 'success';
        case 'pending':
        case 'submitted':
        case 'partially retired':
        case 'ready to retire':
            return 'warning';
        case 'declined':
        case 'rejected':
        case 'cancelled':
        case 'cannot retire':
            return 'danger';
        case 'draft':
        case 'saved':
            return 'info';
        default:
            return 'info';
    }
};

const remainingBalance = computed(() => {
    const voucherTotal = props.voucher.total_amount || 0;
    return voucherTotal - retirementForm.value.total_amount;
});

const isRetirementValid = computed(() => {
    return remainingBalance.value >= 0 && retirementForm.value.total_amount > 0;
});

const amountInWords = computed(() => {
    const amount = props.voucher.amount || props.voucher.total_amount || 0;
    return convertNumberToWords(amount);
});

const totalItems = computed(() => {
    return props.voucher.items?.length || 0;
});

const totalDocuments = computed(() => {
    return props.voucher.documents?.length || 0;
});

const breadcrumbs = computed(() => [
    { title: 'Vouchers', href: '/vouchers' },
    { title: props.voucher.voucher_number || 'Voucher Details', href: '#' },
]);

// --- VOUCHER HELPER FUNCTIONS ---
const getStatusSeverity = (status) => {
    if (!status) return 'info';
    const normalizedStatus = status.toLowerCase().trim();
    switch (normalizedStatus) {
        case 'approved':
        case 'paid':
        case 'closed':
        case 'retired':
            return 'success';
        case 'declined':
        case 'rejected':
        case 'decline and close':
            return 'danger';
        case 'sent back':
        case 'returned':
        case 'cancelled':
            return 'warning';
        case 'submitted':
        case 'pending':
        case 'forwarded':
            return 'secondary';
        case 'draft':
        case 'saved':
            return 'info';
        default:
            return 'info';
    }
};

const canEditVoucher = (voucher) => {
    if (!voucher || !voucher.status) return false;
    const status = voucher.status.toLowerCase().trim();
    const editableStatuses = [
        'draft',
        'saved',
        'sent back',
        'returned',
        'declined',
        'rejected',
        'audit_rejected',
    ];
    return editableStatuses.includes(status);
};

const canDeleteVoucher = (voucher) => {
    if (!voucher || !voucher.status) return false;
    const status = voucher.status.toLowerCase().trim();
    const deletableStatuses = ['draft', 'saved'];
    return deletableStatuses.includes(status);
};

const canDraftVoucher = (voucher) => {
    console.log(usePage().props.auth.userRoles);
    if (!voucher || !voucher.status || voucher.status.toLowerCase().trim() === 'draft') return true;
    if (usePage().props.auth.userRoles.includes('admin') ||
        usePage().props.auth.userRoles.includes('supervisor') ||
        usePage().props.auth.userRoles.includes('Admin') ||
        usePage().props.auth.userRoles.includes('Supervisor')) {
        return false;
    }
    return true;
};

// --- FORMATTERS ---
const formatCurrency = (value) => {
    return new Intl.NumberFormat('en-NG', {
        style: 'currency',
        currency: 'NGN',
    }).format(value || 0);
};

const formatDate = (dateString) => {
    if (!dateString) return 'N/A';
    return new Date(dateString).toLocaleDateString('en-GB');
};

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

// --- VOUCHER ACTIONS ---
const printVoucher = () => {
    const printUrl = `/vouchers/${props.voucher.id}/print`;
    window.open(printUrl, '_blank');
};

const editVoucher = () => {
    if (!canEditVoucher(props.voucher)) {
        toast.add({
            severity: 'error',
            summary: 'Cannot Edit',
            detail: `Voucher ${props.voucher.voucher_number} is "${props.voucher.status}" and cannot be edited.`,
            life: 5000,
        });
        return;
    }
    router.visit(`/vouchers/${props.voucher.id}/edit`);
};

const deleteVoucher = () => {
    if (!canDeleteVoucher(props.voucher)) {
        toast.add({
            severity: 'error',
            summary: 'Cannot Delete',
            detail: `Voucher ${props.voucher.voucher_number} is "${props.voucher.status}" and cannot be deleted.`,
            life: 5000,
        });
        return;
    }
    currentAction.value = 'delete';
    showConfirmationModal.value = true;
};

const draftVoucher = () => {
    if (canDraftVoucher(props.voucher)) {
        toast.add({
            severity: 'error',
            summary: 'Cannot save this voucher to draft',
            detail: `Voucher ${props.voucher.voucher_number} is "${props.voucher.status}" and cannot be made draft.`,
            life: 5000,
        });
        return;
    }
    currentAction.value = 'draft';
    showConfirmationModalDraft.value = true;
};

const goBack = () => {
    window.history.back();
};

const confirmDelete = () => {
    showConfirmationModal.value = false;
    router.delete(route('vouchers.destroy', props.voucher.id), {
        preserveScroll: true,
        onSuccess: () => {
            toast.add({
                severity: 'success',
                summary: 'Deleted',
                detail: `Voucher ${props.voucher.voucher_number} successfully deleted.`,
                life: 3000,
            });
            router.visit(route('vouchers.index'));
        },
        onError: (errors) => {
            const detail = errors.message || 'Failed to delete the voucher.';
            toast.add({
                severity: 'error',
                summary: 'Error',
                detail: detail,
                life: 5000,
            });
        },
    });
};

// Approvals data from API
const approvals = ref([]);
const loadingApprovals = ref(false);

// Load approvals from API
const loadApprovals = async () => {
    try {
        loadingApprovals.value = true;
        const response = await axios.get(`/vouchers/${props.voucher.id}/approvals`);
        approvals.value = response.data || [];
        console.log('Approvals loaded via API:', approvals.value);
    } catch (error) {
        console.error('Error loading approvals:', error);
        approvals.value = [];
    } finally {
        loadingApprovals.value = false;
    }
};

// --- WATCHERS ---
watch(() => retirementForm.value.line_items, calculateTotals, { deep: true });

watch(
    () => economicCodes.value,
    (newCodes) => {
        if (newCodes.length > 0) {
            retirementForm.value.line_items.forEach(async (item) => {
                if (item.economic_code_id) {
                    await fetchCodeItems(item.economic_code_id);
                }
            });
        }
    },
    { immediate: true },
);
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head :title="`Voucher - ${voucher.voucher_number}`" />
        <Toast />

        <div class="grid">
            <!-- Header Actions -->
            <div class="col-12">
                <div class="justify-content-between align-items-center mb-4 flex">
                    <div class="align-items-center flex gap-3">
                        <Button icon="pi pi-arrow-left" text rounded severity="secondary" @click="goBack"
                            v-tooltip="'Go Back'" />
                        <div>
                            <h1 class="mb-1 text-2xl font-bold">
                                {{ voucher.voucher_number }}
                            </h1>
                            <div class="align-items-center flex gap-3">
                                <Tag :value="voucher.status" :severity="getStatusSeverity(voucher.status)" />
                                <Tag v-if="retirementStatusData" :value="retirementStatusData.already_retired
                                    ? 'FULLY RETIRED'
                                    : 'PENDING RETIREMENT'
                                    " :severity="retirementStatusData.already_retired
                                        ? 'success'
                                        : 'warning'
                                        " :icon="retirementStatusData.already_retired
                                            ? 'pi pi-check-circle'
                                            : 'pi pi-clock'
                                            " v-tooltip.top="retirementStatusData.already_retired
                                                ? 'Voucher is fully retired'
                                                : `Available balance: ${formatCurrency(retirementStatusData.available_balance)}`
                                                " />
                                <span class="text-500">
                                    Date: {{ formatDate(voucher.voucher_date) }}
                                </span>
                                <span v-if="
                                    retirementStatusData &&
                                    retirementStatusData.retired_amount > 0
                                " class="text-500">
                                    | Retired:
                                    {{
                                        formatCurrency(
                                            retirementStatusData.retired_amount,
                                        )
                                    }}
                                </span>
                            </div>
                        </div>
                    </div>

                    <div class="flex gap-2">
                        <Button v-if="showApproveButton" label="Approve Prepayment Voucher" icon="pi pi-check"
                            severity="success" :disabled="!canApprove" v-tooltip="approveTooltip"
                            @click="approveVoucher" />
                        <Button v-if="showRetireButton" label="Approve Prepayment Voucher For Retirement"
                            icon="pi pi-check-circle" severity="warn" :disabled="!canRetire" v-tooltip="retireTooltip"
                            @click="openRetirementModal" />
                        <Button label="Print" icon="pi pi-print" severity="info" @click="printVoucher" />
                        <Button label="Edit Voucher" icon="pi pi-pencil" severity="secondary"
                            :disabled="!canEditVoucher(voucher)" v-tooltip="canEditVoucher(voucher)
                                ? 'Edit Voucher'
                                : 'Cannot edit this voucher'
                                " @click="editVoucher" />
                        <Button label="Delete" icon="pi pi-trash" severity="danger"
                            :disabled="!canDeleteVoucher(voucher)" v-tooltip="canDeleteVoucher(voucher)
                                ? 'Delete Voucher'
                                : 'Cannot delete this voucher'
                                " @click="deleteVoucher" />
                        <Button label="Draft" icon="pi pi-save" severity="info" :disabled="canDraftVoucher(voucher)"
                            v-tooltip="canDraftVoucher(voucher)
                                ? 'Save as Draft'
                                : 'Cannot save as Draft'
                                " @click="draftVoucher" />
                    </div>
                </div>
            </div>

            <!-- Basic Information -->
            <div class="col-12 md:col-6">
                <Card>
                    <template #title>
                        <div class="align-items-center flex gap-2">
                            <i class="pi pi-info-circle text-primary"></i>
                            <span>Basic Information</span>
                        </div>
                    </template>
                    <template #content>
                        <div class="grid">
                            <div class="col-6">
                                <div class="field">
                                    <label class="text-500 font-semibold">Voucher Number</label>
                                    <div class="text-900 text-lg font-medium">
                                        {{ voucher.voucher_number }}
                                    </div>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="field">
                                    <label class="text-500 font-semibold">Voucher Date</label>
                                    <div class="text-900">
                                        {{ formatDate(voucher.voucher_date) }}
                                    </div>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="field">
                                    <label class="text-500 font-semibold">Voucher Type</label>
                                    <div class="text-900 font-medium">
                                        <Tag :value="voucher.voucher_type?.toUpperCase() ||
                                            'N/A'
                                            " :severity="voucher.voucher_type ===
                                                'prepayment'
                                                ? 'warning'
                                                : 'info'
                                                " />
                                    </div>
                                </div>
                            </div>

                            <div class="col-6" v-if="retirementStatusData">
                                <div class="field">
                                    <label class="text-500 font-semibold">Retirement Status</label>
                                    <div class="text-900 font-medium">
                                        <Tag :value="retirementStatusData.already_retired
                                            ? 'Fully Retired'
                                            : 'Not Retired'
                                            " :severity="retirementStatusData.already_retired
                                                ? 'success'
                                                : 'warning'
                                                " :icon="retirementStatusData.already_retired
                                                    ? 'pi pi-check-circle'
                                                    : 'pi pi-clock'
                                                    " />
                                        <div class="text-500 text-xs">
                                            {{
                                                voucher.retired_at
                                                    ? `(Last retired on ${formatDate(voucher.retired_at)})`
                                                    : ''
                                            }}
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="col-12" v-if="retirementStatusData">
                                <div class="field">
                                    <label class="text-500 font-semibold">Retirement Details</label>
                                    <div class="grid">
                                        <div class="col-4">
                                            <div class="text-500 text-xs">
                                                Original Amount
                                            </div>
                                            <div class="text-900 font-medium">
                                                {{
                                                    formatCurrency(
                                                        voucher.amount ||
                                                        voucher.total_amount,
                                                    )
                                                }}
                                            </div>
                                        </div>
                                        <div class="col-4">
                                            <div class="text-500 text-xs">
                                                Already Retired
                                            </div>
                                            <div class="text-900 font-medium">
                                                {{
                                                    formatCurrency(
                                                        retirementStatusData.retired_amount,
                                                    )
                                                }}
                                            </div>
                                        </div>
                                        <div class="col-4">
                                            <div class="text-500 text-xs">
                                                Available Balance
                                            </div>
                                            <div class="text-900 font-medium" :class="retirementStatusData.available_balance >
                                                0
                                                ? 'text-green-600'
                                                : 'text-gray-600'
                                                ">
                                                {{
                                                    formatCurrency(
                                                        retirementStatusData.available_balance,
                                                    )
                                                }}
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="col-12">
                                <div class="field">
                                    <label class="text-500 font-semibold">MDA</label>
                                    <div class="text-900 font-medium">
                                        <span v-if="voucher.mda">
                                            {{ voucher.mda.name }}
                                            <span class="text-500 ml-2">({{ voucher.mda.code }})</span>
                                        </span>
                                        <span v-else class="text-500">N/A</span>
                                    </div>
                                </div>
                            </div>

                            <div class="col-12" v-if="
                                retirementStatusData &&
                                retirementStatusData.retired_amount > 0
                            ">
                                <div class="field">
                                    <Button label="View Retirement History" icon="pi pi-history" severity="info"
                                        size="small" outlined @click="viewRetirementHistory" />
                                </div>
                            </div>
                        </div>
                    </template>
                </Card>
            </div>

            <!-- Financial Information -->
            <div class="col-12 md:col-6">
                <Card>
                    <template #title>
                        <div class="align-items-center flex gap-2">
                            <i class="pi pi-wallet text-primary"></i>
                            <span>Financial Information</span>
                        </div>
                    </template>
                    <template #content>
                        <div class="grid">
                            <div class="col-12" v-if="voucher.payee_name">
                                <div class="field">
                                    <label class="text-500 font-semibold">Payee/Beneficiary</label>
                                    <div class="text-900 text-primary text-xl font-bold">
                                        {{ voucher.payee_name }}
                                    </div>
                                </div>
                            </div>

                            <template v-if="voucher.bankActivity">
                                <div class="col-12">
                                    <div class="field">
                                        <label class="text-500 font-semibold">Payment Title</label>
                                        <div class="text-900 text-primary text-lg font-medium">
                                            {{ voucher.bankActivity.title }}
                                        </div>
                                    </div>
                                </div>
                                <div class="col-4">
                                    <div class="field">
                                        <label class="text-500 font-semibold">Bank Name</label>
                                        <div class="text-900 font-medium">
                                            {{ voucher.bankActivity.bank_name }}
                                        </div>
                                    </div>
                                </div>
                                <div class="col-4">
                                    <div class="field">
                                        <label class="text-500 font-semibold">Account Number</label>
                                        <div class="text-900 font-medium">
                                            {{
                                                voucher.bankActivity
                                                    .account_number
                                            }}
                                        </div>
                                    </div>
                                </div>
                                <div class="col-4">
                                    <div class="field">
                                        <label class="text-500 font-semibold">Bank Economic Code</label>
                                        <div class="text-900 font-medium">
                                            {{
                                                voucher.bankActivity
                                                    .economic_code || 'N/A'
                                            }}
                                        </div>
                                    </div>
                                </div>
                                <div class="col-12">
                                    <div class="field">
                                        <label class="text-500 font-semibold">Payment Tag/Reference</label>
                                        <div class="text-900">
                                            <Tag :value="voucher.bankActivity.tag ||
                                                'No tag'
                                                " severity="info" />
                                        </div>
                                    </div>
                                </div>
                            </template>

                            <div class="col-12">
                                <div class="field">
                                    <label class="text-500 font-semibold">Total Amount</label>
                                    <div class="text-900 text-primary text-2xl font-bold">
                                        {{
                                            formatCurrency(
                                                voucher.amount ||
                                                voucher.total_amount,
                                            )
                                        }}
                                    </div>
                                </div>
                            </div>

                            <div class="col-12">
                                <div class="field">
                                    <label class="text-500 font-semibold">Amount in Words</label>
                                    <div class="text-900 border-round border-1 border-200 bg-gray-50 p-2 italic">
                                        {{ amountInWords }}
                                    </div>
                                </div>
                            </div>

                            <div class="col-12" v-if="
                                retirementStatusData &&
                                voucher.voucher_type === 'prepayment'
                            ">
                                <div class="field">
                                    <label class="text-500 font-semibold">Retirement Progress</label>
                                    <div class="space-y-2">
                                        <div class="relative h-4 w-full rounded-full bg-gray-200">
                                            <div class="bg-primary h-4 rounded-full transition-all duration-300"
                                                :style="`width: ${retirementProgress}%;`" :class="{
                                                    'bg-green-500':
                                                        retirementProgress ===
                                                        100,
                                                    'bg-primary':
                                                        retirementProgress >
                                                        0 &&
                                                        retirementProgress <
                                                        100,
                                                    'bg-red-500':
                                                        retirementProgress >
                                                        100,
                                                }"></div>
                                            <div class="absolute inset-0 flex items-center justify-center">
                                                <span class="text-xs font-semibold text-white">
                                                    {{
                                                        retirementProgress.toFixed(
                                                            1,
                                                        )
                                                    }}%
                                                </span>
                                            </div>
                                        </div>

                                        <div class="mt-2 grid">
                                            <div class="col-4 text-center">
                                                <div class="text-500 text-xs">
                                                    Original Amount
                                                </div>
                                                <div class="text-900 font-bold">
                                                    {{
                                                        formatCurrency(
                                                            voucher.amount ||
                                                            voucher.total_amount,
                                                        )
                                                    }}
                                                </div>
                                            </div>
                                            <div class="col-4 text-center">
                                                <div class="text-500 text-xs">
                                                    Retired Amount
                                                </div>
                                                <div class="text-900 font-bold" :class="{
                                                    'text-green-600':
                                                        retirementStatusData.retired_amount >
                                                        0,
                                                    'text-gray-600':
                                                        retirementStatusData.retired_amount ===
                                                        0,
                                                }">
                                                    {{
                                                        formatCurrency(
                                                            retirementStatusData.retired_amount,
                                                        )
                                                    }}
                                                </div>
                                            </div>
                                            <div class="col-4 text-center">
                                                <div class="text-500 text-xs">
                                                    Available Balance
                                                </div>
                                                <div class="text-900 font-bold" :class="{
                                                    'text-green-600':
                                                        retirementStatusData.available_balance >
                                                        0,
                                                    'text-orange-600':
                                                        retirementStatusData.available_balance ===
                                                        0,
                                                    'text-red-600':
                                                        retirementStatusData.available_balance <
                                                        0,
                                                }">
                                                    {{
                                                        formatCurrency(
                                                            retirementStatusData.available_balance,
                                                        )
                                                    }}
                                                </div>
                                            </div>
                                        </div>

                                        <div class="mt-2 text-center">
                                            <Tag :value="getRetirementStatusText()
                                                " :severity="getRetirementStatusSeverity(
                                                    getRetirementStatusText(),
                                                )
                                                    " :icon="retirementStatusData.already_retired
                                                        ? 'pi pi-check-circle'
                                                        : 'pi pi-clock'
                                                        " class="font-semibold" />
                                            <div v-if="voucher.retired_at" class="text-500 mt-1 text-xs">
                                                Last retired on
                                                {{
                                                    formatDate(
                                                        voucher.retired_at,
                                                    )
                                                }}
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="col-6">
                                <div class="field">
                                    <label class="text-500 font-semibold">Voucher Status</label>
                                    <div>
                                        <Tag :value="voucher.status" :severity="getStatusSeverity(
                                            voucher.status,
                                        )
                                            " />
                                    </div>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="field">
                                    <label class="text-500 font-semibold">Line Items</label>
                                    <div class="text-900 font-medium">
                                        {{ totalItems }} items
                                    </div>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="field">
                                    <label class="text-500 font-semibold">Created By</label>
                                    <div class="text-900">
                                        {{ voucher.creator?.name || 'N/A' }}
                                    </div>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="field">
                                    <label class="text-500 font-semibold">Created Date</label>
                                    <div class="text-900">
                                        {{ formatDateTime(voucher.created_at) }}
                                    </div>
                                </div>
                            </div>

                            <div class="col-12" v-if="
                                voucher.approved_by || voucher.approved_at
                            ">
                                <div class="field">
                                    <label class="text-500 font-semibold">Approval Details</label>
                                    <div class="text-900">
                                        <div v-if="voucher.approved_by" class="mb-1">
                                            Approved by:
                                            {{ voucher.approved_by }}
                                        </div>
                                        <div v-if="voucher.approved_at" class="text-500 text-sm">
                                            on
                                            {{
                                                formatDateTime(
                                                    voucher.approved_at,
                                                )
                                            }}
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </template>
                </Card>
            </div>

            <!-- Voucher Items -->
            <div class="col-12">
                <Card>
                    <template #title>
                        <div class="justify-content-between align-items-center flex">
                            <div class="align-items-center flex gap-2">
                                <i class="pi pi-list text-primary"></i>
                                <span>Voucher Items ({{ totalItems }})</span>
                            </div>
                            <span class="text-lg font-bold">
                                Total:
                                {{ formatCurrency(voucher.total_amount) }}
                            </span>
                        </div>
                    </template>
                    <template #content>
                        <DataTable :value="voucher.items || []" dataKey="id" stripedRows responsiveLayout="scroll"
                            class="p-datatable-sm" :emptyMessage="'No items found in this voucher.'">
                            <Column field="description" header="Description" headerStyle="width: 40%">
                                <template #body="slotProps">
                                    <span class="font-medium">
                                        {{ slotProps.data.description }}
                                    </span>
                                </template>
                            </Column>

                            <Column field="economy_code" header="Economy Code" headerStyle="width: 15%">
                                <template #body="slotProps">
                                    <div v-if="slotProps.data.economy_code" class="flex-column flex">
                                        <span class="font-medium">
                                            {{
                                                slotProps.data.economy_code.code
                                            }}
                                        </span>
                                        <small class="text-500">
                                            {{
                                                slotProps.data.economy_code.name
                                            }}
                                        </small>
                                    </div>
                                    <span v-else class="text-500">N/A</span>
                                </template>
                            </Column>

                            <Column field="economy_code_item" header="Code Item" headerStyle="width: 15%">
                                <template #body="slotProps">
                                    <div v-if="slotProps.data.economy_code_item" class="flex-column flex">
                                        <span>{{
                                            slotProps.data.economy_code_item
                                                .code
                                        }}</span>
                                        <small class="text-500">{{
                                            slotProps.data.economy_code_item
                                                .name
                                        }}</small>
                                    </div>
                                    <span v-else class="text-500">N/A</span>
                                </template>
                            </Column>

                            <Column field="quantity" header="Qty" headerStyle="width: 8%" bodyClass="text-center">
                                <template #body="slotProps">
                                    <span class="font-mono">{{
                                        slotProps.data.quantity
                                    }}</span>
                                </template>
                            </Column>

                            <Column field="unit_price" header="Unit Price" headerStyle="width: 12%"
                                bodyClass="text-right">
                                <template #body="slotProps">
                                    <span class="font-medium">{{
                                        formatCurrency(
                                            slotProps.data.unit_price,
                                        )
                                    }}</span>
                                </template>
                            </Column>

                            <Column field="sub_total" header="Sub Total" headerStyle="width: 10%"
                                bodyClass="text-right font-bold">
                                <template #body="slotProps">
                                    <span class="text-primary">{{
                                        formatCurrency(slotProps.data.sub_total)
                                    }}</span>
                                </template>
                            </Column>
                        </DataTable>

                        <div
                            class="justify-content-between align-items-center border-round bg-primary-50 mt-4 flex border-1 border-200 p-3">
                            <span class="text-lg font-bold">Grand Total</span>
                            <span class="text-primary text-xl font-bold">{{
                                formatCurrency(voucher.total_amount)
                            }}</span>
                        </div>
                    </template>
                </Card>
            </div>

            <!-- Documents Section -->
            <!-- Documents Section - Enhanced with View Option -->
            <div class="col-12 md:col-6" v-if="voucher.documents && voucher.documents.length > 0">
                <Card>
                    <template #title>
                        <div class="align-items-center flex gap-2">
                            <i class="pi pi-file-pdf text-primary"></i>
                            <span>Supporting Documents ({{ totalDocuments }})</span>
                        </div>
                    </template>
                    <template #content>
                        <div class="space-y-2">
                            <div v-for="document in voucher.documents" :key="document.id"
                                class="align-items-center justify-content-between border-round flex border-1 border-200 p-3 hover:surface-100 transition-all cursor-pointer"
                                @click="viewDocument(document)">
                                <div class="align-items-center flex gap-3">
                                    <i :class="getDocumentIcon(document.file_name)" class="text-xl"></i>
                                    <div>
                                        <div class="font-medium">
                                            {{ document.file_name }}
                                        </div>
                                        <div class="text-500 text-sm flex gap-2">
                                            <span>{{ document.document_type_label || 'Document' }}</span>
                                            <span>•</span>
                                            <span>{{ (document.file_size / 1024).toFixed(2) }} KB</span>
                                        </div>
                                    </div>
                                </div>
                                <div class="flex gap-2">
                                    <Button 
                                        icon="pi pi-eye" 
                                        text 
                                        rounded 
                                        severity="info" 
                                        size="small"
                                        v-tooltip.top="'View Document'"
                                        @click.stop="viewDocument(document)"
                                    />
                                    <Button 
                                        icon="pi pi-download" 
                                        text 
                                        rounded 
                                        severity="secondary" 
                                        size="small"
                                        v-tooltip.top="'Download Document'"
                                        @click.stop="window.open(document.url || `/storage/${document.file_path}`, '_blank')"
                                    />
                                </div>
                            </div>
                        </div>
                    </template>
                </Card>
            </div>

            <!-- Approval Workflow - Using API Loaded Data -->
            <div class="col-12" :class="voucher.documents && voucher.documents.length > 0
                ? 'md:col-6'
                : 'md:col-12'
                ">
                <Card class="approval-history-card">
                    <template #title>
                        <div class="align-items-center flex gap-2">
                            <i class="pi pi-sitemap text-primary"></i>
                            <span>Approval Workflow</span>
                            <Badge :value="approvals.length || 0" severity="info" />
                        </div>
                    </template>
                    <template #content>
                        <div v-if="loadingApprovals" class="text-center p-4">
                            <ProgressSpinner style="width: 40px; height: 40px" strokeWidth="4" />
                            <p class="text-500 mt-2">Loading workflow...</p>
                        </div>
                        <div v-else-if="approvals && approvals.length > 0" class="workflow-timeline">
                            <Timeline
                                :value="[...approvals].sort((a, b) => {
                                    const dateA = new Date(a.action_at || a.created_at);
                                    const dateB = new Date(b.action_at || b.created_at);
                                    return dateA - dateB;
                                })"
                                layout="vertical"
                                align="left"
                                class="custom-timeline"
                            >
                                <template #marker="slotProps">
                                    <span 
                                        class="custom-marker p-shadow-2" 
                                        :class="{
                                            'bg-green-500': slotProps.item.action === 'Approved' || slotProps.item.action === 'Paid' || slotProps.item.action === 'Closed',
                                            'bg-red-500': slotProps.item.action === 'Declined' || slotProps.item.action === 'Rejected',
                                            'bg-blue-500': slotProps.item.action === 'Forwarded' || slotProps.item.action === 'Submitted',
                                            'bg-orange-500': slotProps.item.action === 'Sent Back' || slotProps.item.action === 'Returned',
                                            'bg-purple-500': slotProps.item.action === 'Created',
                                            'bg-gray-500': slotProps.item.action === 'Saved' || slotProps.item.action === 'Updated'
                                        }"
                                    >
                                        <i :class="{
                                            'pi pi-check': slotProps.item.action === 'Approved' || slotProps.item.action === 'Paid' || slotProps.item.action === 'Closed',
                                            'pi pi-times': slotProps.item.action === 'Declined' || slotProps.item.action === 'Rejected',
                                            'pi pi-send': slotProps.item.action === 'Forwarded' || slotProps.item.action === 'Submitted',
                                            'pi pi-undo': slotProps.item.action === 'Sent Back' || slotProps.item.action === 'Returned',
                                            'pi pi-plus-circle': slotProps.item.action === 'Created',
                                            'pi pi-save': slotProps.item.action === 'Saved',
                                            'pi pi-pencil': slotProps.item.action === 'Updated'
                                        }" class="text-white text-sm"></i>
                                    </span>
                                </template>
                                <template #content="slotProps">
                                    <div class="workflow-card-item p-3 border-round border-1 surface-border">
                                        <div class="flex flex-column gap-2">
                                            <div class="flex align-items-center justify-content-between flex-wrap">
                                                <span class="font-semibold text-primary">
                                                    {{ slotProps.item.approval_role || 'System' }}
                                                    <span v-if="slotProps.item.approval_step" class="text-500 text-xs font-normal ml-1">
                                                        (Step {{ slotProps.item.approval_step }})
                                                    </span>
                                                </span>
                                                <Tag 
                                                    :value="slotProps.item.action" 
                                                    :severity="getApprovalActionSeverity(slotProps.item.action)"
                                                    size="small"
                                                />
                                            </div>
                                            <div class="text-600 text-sm">
                                                <i class="pi pi-clock mr-1"></i>
                                                {{ formatDateTime(slotProps.item.action_at || slotProps.item.created_at) }}
                                            </div>
                                            <div v-if="slotProps.item.comment" class="text-500 text-sm mt-1 p-2 bg-gray-50 border-round">
                                                <i class="pi pi-comment mr-1"></i>
                                                {{ slotProps.item.comment }}
                                            </div>
                                            <div v-if="slotProps.item.user" class="text-500 text-xs">
                                                <i class="pi pi-user mr-1"></i>
                                                By: {{ slotProps.item.user.name }}
                                            </div>
                                            <div v-if="slotProps.item.status" class="text-500 text-xs">
                                                <i class="pi pi-info-circle mr-1"></i>
                                                Status: {{ slotProps.item.status }}
                                            </div>
                                        </div>
                                    </div>
                                </template>
                            </Timeline>
                        </div>
                        <div v-else class="text-center p-4">
                            <i class="pi pi-clock text-400 text-3xl mb-2"></i>
                            <p class="text-600">No workflow history available</p>
                            <p class="text-500 text-sm">Approval history will appear here once the voucher is processed.</p>
                        </div>
                    </template>
                </Card>
            </div>
        </div>

        <!-- Retirement Modal -->
        <Dialog v-model:visible="showRetirementModal" :style="{ width: '98vw', maxWidth: '1600px', maxHeight: '95vh' }"
            header="Retire Prepayment Voucher" :modal="true" :closable="true" @hide="showRetirementModal = false"
            contentClass="flex flex-column">
            <!-- ... (keep your existing retirement modal content) ... -->
        </Dialog>

        <!-- Delete Confirmation Modal -->
        <Dialog v-model:visible="showConfirmationModal" :style="{ width: '450px' }" header="Delete Voucher"
            :modal="true">
            <div class="align-items-center flex">
                <i class="pi pi-exclamation-triangle mr-3 text-red-500" style="font-size: 2rem"></i>
                <div>
                    <span>Are you sure you want to
                        <strong class="text-red-600">permanently delete</strong>
                        Voucher <strong>{{ voucher.voucher_number }}</strong>? This action cannot be undone.</span>
                </div>
            </div>
            <template #footer>
                <Button label="Cancel" icon="pi pi-times" @click="showConfirmationModal = false" text />
                <Button label="Yes, Delete" icon="pi pi-trash" severity="danger" @click="confirmDelete" autofocus />
            </template>
        </Dialog>

        <!-- Draft Confirmation Modal -->
        <Dialog v-model:visible="showConfirmationModalDraft" :style="{ width: '450px' }" header="Save as Draft"
            :modal="true">
            <div class="align-items-center flex">
                <i class="pi pi-exclamation-triangle mr-3 text-yellow-500" style="font-size: 2rem"></i>
                <div>
                    <span>Are you sure you want to
                        <strong class="text-yellow-600">make this</strong>
                        Voucher <strong>{{ voucher.voucher_number }}</strong> a draft?</span>
                </div>
            </div>
            <template #footer>
                <Button label="Cancel" icon="pi pi-times" @click="showConfirmationModalDraft = false" text />
                <Button label="Yes, Make Draft" icon="pi pi-save" severity="info" @click="confirmDraftVoucher"
                    autofocus />
            </template>
        </Dialog>

        <!-- Retirement History Modal -->
        <Dialog v-model:visible="showRetirementHistoryModal"
            :style="{ width: '90vw', maxWidth: '1400px', maxHeight: '90vh' }" header="Retirement History" :modal="true"
            :closable="true" @hide="showRetirementHistoryModal = false" class="retirement-history-modal">
            <!-- ... (keep your existing retirement history modal content) ... -->
        </Dialog>

        <!-- Document Viewer Modal -->
<!-- Document Viewer Modal - Updated with Better URL Handling -->
<Dialog
    v-model:visible="showDocumentViewer"
    :header="`Document - ${currentDocument?.file_name || 'Document'}`"
    :style="{ width: '90vw', height: '90vh' }"
    :modal="true"
    maximizable
    @update:visible="closeDocumentViewer"
    class="document-viewer-dialog"
>
    <div class="flex flex-column h-full">
        <!-- Document Info Header -->
        <div class="flex justify-content-between align-items-center mb-3 pb-2 border-bottom-1 surface-border">
            <div class="flex align-items-center gap-2">
                <i :class="getDocumentIcon(currentDocument?.file_name)" class="text-xl"></i>
                <span class="font-semibold">{{ currentDocument?.file_name }}</span>
                <Tag 
                    v-if="currentDocument" 
                    :value="getDocumentTypeLabel(currentDocument)" 
                    severity="info" 
                    size="small"
                />
                <span v-if="currentDocument" class="text-500 text-sm">
                    ({{ (currentDocument.file_size / 1024).toFixed(2) }} KB)
                </span>
            </div>
            <div class="flex gap-2">
                <Button 
                    icon="pi pi-download" 
                    label="Download" 
                    severity="secondary" 
                    size="small"
                    @click="downloadDocument" 
                />
                <Button 
                    icon="pi pi-external-link" 
                    label="Open in New Tab" 
                    severity="info" 
                    size="small"
                    @click="openInNewTab" 
                />
            </div>
        </div>
        
        <!-- Document Preview -->
        <div class="flex-1 border-round surface-border border-1 overflow-hidden document-preview">
            <!-- Show loading state -->
            <div v-if="!documentUrl" class="flex align-items-center justify-content-center w-full h-full bg-gray-50" style="min-height: 500px;">
                <ProgressSpinner style="width: 50px; height: 50px" strokeWidth="4" />
                <p class="text-500 mt-2">Loading document...</p>
            </div>
            
            <!-- PDF Preview -->
            <iframe 
                v-else-if="currentDocument?.file_name?.endsWith('.pdf')"
                :src="documentUrl" 
                frameborder="0" 
                width="100%" 
                height="100%" 
                class="w-full h-full"
                style="min-height: 500px;"
                @error="handleIframeError"
            ></iframe>
            
            <!-- Image Preview -->
            <div v-else-if="currentDocument?.file_name?.match(/\.(jpg|jpeg|png|gif|svg|webp)$/i)" 
                 class="flex align-items-center justify-content-center w-full h-full bg-gray-50" 
                 style="min-height: 500px;">
                <img 
                    :src="documentUrl" 
                    :alt="currentDocument?.file_name" 
                    class="max-w-full max-h-full object-contain"
                    @error="handleImageError"
                    @load="handleImageLoad"
                />
            </div>
            
            <!-- Word/Excel/PowerPoint - Show preview with Office Online -->
            <div v-else-if="currentDocument?.file_name?.match(/\.(doc|docx|xls|xlsx|ppt|pptx)$/i)" 
                 class="flex flex-column align-items-center justify-content-center w-full h-full bg-gray-50" 
                 style="min-height: 500px;">
                <i class="pi pi-file text-6xl text-gray-400 mb-4"></i>
                <h4 class="text-900">Office Document</h4>
                <p class="text-500 text-sm mb-3">{{ currentDocument?.file_name }}</p>
                <div class="flex gap-2">
                    <Button 
                        label="Download" 
                        icon="pi pi-download" 
                        severity="primary" 
                        @click="downloadDocument" 
                    />
                    <Button 
                        label="Open in New Tab" 
                        icon="pi pi-external-link" 
                        severity="secondary" 
                        @click="openInNewTab" 
                    />
                </div>
                <p class="text-500 text-xs mt-3">Office documents can be downloaded and opened in Microsoft Office or Google Docs.</p>
            </div>
            
            <!-- Other File Types - Show Download Option -->
            <div v-else class="flex flex-column align-items-center justify-content-center w-full h-full bg-gray-50" 
                 style="min-height: 500px;">
                <i class="pi pi-file text-6xl text-gray-400 mb-4"></i>
                <h4 class="text-900">Preview Not Available</h4>
                <p class="text-500 text-sm">This file type cannot be previewed directly.</p>
                <p class="text-500 text-sm mb-3">{{ currentDocument?.file_name }}</p>
                <div class="flex gap-2">
                    <Button 
                        label="Download File" 
                        icon="pi pi-download" 
                        severity="primary" 
                        @click="downloadDocument" 
                    />
                    <Button 
                        label="Open in New Tab" 
                        icon="pi pi-external-link" 
                        severity="secondary" 
                        @click="openInNewTab" 
                    />
                </div>
            </div>
        </div>
    </div>
</Dialog>
    </AppLayout>
</template>


<style scoped>
.field {
    margin-bottom: 1.25rem;
}

.field:last-child {
    margin-bottom: 0;
}

.field label {
    display: block;
    margin-bottom: 0.25rem;
    font-size: 0.875rem;
}

:deep(.p-card) {
    height: 100%;
}

:deep(.p-card-content) {
    padding: 0;
}

:deep(.p-card-content .grid) {
    margin: 0 -1rem;
}

:deep(.p-card-content .col-12),
:deep(.p-card-content .col-6) {
    padding: 1rem;
}

:deep(.p-datatable) {
    font-size: 0.875rem;
}

:deep(.p-datatable .p-datatable-thead > tr > th) {
    background-color: var(--surface-100);
    color: var(--text-color);
    font-weight: 600;
    font-size: 0.75rem;
    padding: 0.5rem;
}

:deep(.p-datatable .p-datatable-tbody > tr > td) {
    padding: 0.5rem;
}

.bg-gray-50 {
    background-color: var(--surface-50);
}

.bg-primary-50 {
    background-color: var(--primary-50);
    border-color: var(--primary-200);
}

.space-y-2>*+* {
    margin-top: 0.5rem;
}

.space-y-3>*+* {
    margin-top: 0.75rem;
}

.space-y-4>*+* {
    margin-top: 1rem;
}

/* Enhanced Retirement Modal Styles */
.w-6rem {
    width: 6rem;
}

.w-8rem {
    width: 8rem;
}

.bg-green-50 {
    background-color: #f0fdf4;
    border: 1px solid #bbf7d0;
}

.bg-orange-50 {
    background-color: #fff7ed;
    border: 1px solid #fed7aa;
}

.bg-red-50 {
    background-color: #fef2f2;
    border: 1px solid #fecaca;
}

/* Fix for scrollable datatable */
:deep(.p-datatable-scrollable-wrapper) {
    flex: 1;
    min-height: 0;
}

:deep(.p-datatable-scrollable-body) {
    max-height: none !important;
}

/* Ensure modal content doesn't overflow */
:deep(.p-dialog-content) {
    flex: 1;
    min-height: 0;
    overflow: hidden;
}

/* Progress bar styles */
.relative {
    position: relative;
}

.absolute {
    position: absolute;
}

.inset-0 {
    top: 0;
    right: 0;
    bottom: 0;
    left: 0;
}

.transition-all {
    transition-property: all;
    transition-timing-function: cubic-bezier(0.4, 0, 0.2, 1);
    transition-duration: 300ms;
}

/* Status colors */
.text-green-600 {
    color: #059669;
}

.text-orange-600 {
    color: #ea580c;
}

.text-red-600 {
    color: #dc2626;
}

.bg-green-500 {
    background-color: #10b981;
}

.bg-red-500 {
    background-color: #ef4444;
}

/* Timeline Styles */
.timeline {
    position: relative;
    padding-left: 2rem;
}

.timeline-item {
    position: relative;
    padding-bottom: 2rem;
    border-left: 2px solid var(--surface-200);
    padding-left: 1.5rem;
}

.timeline-item:last-child {
    padding-bottom: 0;
    border-left: 2px solid transparent;
}

.timeline-marker {
    position: absolute;
    left: -0.75rem;
    top: 0;
    width: 1.5rem;
    height: 1.5rem;
    background: white;
    border: 2px solid var(--surface-200);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
}

.timeline-content {
    padding-left: 0.5rem;
}

.timeline-date {
    margin-bottom: 0.25rem;
}

.timeline-title {
    margin-bottom: 0.5rem;
}

.timeline-description {
    color: var(--text-color-secondary);
}

/* Modal specific styles */
.retirement-history-modal :deep(.p-dialog-content) {
    display: flex;
    flex-direction: column;
}

:deep(.p-datatable-sm .p-datatable-thead > tr > th) {
    padding: 0.5rem;
    font-size: 0.75rem;
}

:deep(.p-datatable-sm .p-datatable-tbody > tr > td) {
    padding: 0.5rem;
    font-size: 0.875rem;
}

/* Responsive adjustments */
@media (max-width: 768px) {
    .timeline {
        padding-left: 1.5rem;
    }

    .timeline-item {
        padding-left: 1rem;
    }

    .timeline-marker {
        left: -0.625rem;
    }
}

.workflow-timeline {
    max-height: 500px;
    overflow-y: auto;
}

.workflow-timeline::-webkit-scrollbar {
    width: 4px;
}

.workflow-timeline::-webkit-scrollbar-track {
    background: #f1f1f1;
    border-radius: 4px;
}

.workflow-timeline::-webkit-scrollbar-thumb {
    background: #c1c1c1;
    border-radius: 4px;
}

.workflow-timeline::-webkit-scrollbar-thumb:hover {
    background: #a8a8a8;
}

.custom-marker {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 2rem;
    height: 2rem;
    border-radius: 50%;
    flex-shrink: 0;
}

.custom-timeline :deep(.p-timeline-event-opposite) {
    flex: 0;
    padding: 0;
}

.custom-timeline :deep(.p-timeline-event-content) {
    margin-left: 1rem;
}

.custom-timeline :deep(.p-timeline-event-marker) {
    background: transparent !important;
    border: none !important;
}

.workflow-card-item :deep(.p-card) {
    box-shadow: none;
    border: 1px solid #e2e8f0;
    border-radius: 0.5rem;
    margin: 0.5rem 0;
}

.workflow-card-item :deep(.p-card-content) {
    padding: 0.75rem;
}

/* Status colors for timeline markers */
.bg-green-500 {
    background-color: #10b981;
}

.bg-red-500 {
    background-color: #ef4444;
}

.bg-blue-500 {
    background-color: #3b82f6;
}

.bg-orange-500 {
    background-color: #f59e0b;
}

.bg-purple-500 {
    background-color: #8b5cf6;
}

.bg-gray-500 {
    background-color: #6b7280;
}

/* Badge severities override */
:deep(.p-badge-success) {
    background-color: #10b981;
}

:deep(.p-badge-danger) {
    background-color: #ef4444;
}

:deep(.p-badge-info) {
    background-color: #3b82f6;
}

:deep(.p-badge-warning) {
    background-color: #f59e0b;
}

:deep(.p-badge-secondary) {
    background-color: #6b7280;
}

/* Document Viewer Styles */
.document-viewer-dialog :deep(.p-dialog-header) {
    background: #f8fafc;
    border-bottom: 1px solid #e2e8f0;
    padding: 1rem 1.5rem;
}

.document-viewer-dialog :deep(.p-dialog-content) {
    padding: 1rem 1.5rem 1.5rem 1.5rem;
    flex: 1;
    display: flex;
    flex-direction: column;
}

.document-viewer-dialog :deep(.p-dialog-content .flex-1) {
    flex: 1;
    min-height: 0;
}

.document-preview {
    background: #ffffff;
    border-radius: 0.5rem;
}

.document-preview iframe {
    border-radius: 0.5rem;
}

/* Hover effect for document items */
.hover\:surface-100:hover {
    background-color: var(--surface-100);
}

.transition-all {
    transition: all 0.2s ease;
}

.cursor-pointer {
    cursor: pointer;
}

/* Document preview background */
.bg-gray-50 {
    background-color: #f9fafb;
}

.object-contain {
    object-fit: contain;
}

.max-w-full {
    max-width: 100%;
}

.max-h-full {
    max-height: 100%;
}
</style>
