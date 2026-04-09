<script setup>
import AppLayout from '@/layouts/AppLayout.vue';
import { hasPermission, hasRole } from '@/lib/utils/permissions';
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
const currentAction = ref(null);
const showRetirementHistoryModal = ref(false);
const retirementHistory = ref([]);
const loadingRetirementHistory = ref(false);

// API Loading States
const loadingEconomicCodes = ref(false);
const loadingCodeItems = ref(false);
const economicCodes = ref([]);
const codeItems = ref({});

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

const emit = defineEmits(['approve', 'retire']);
const page = usePage();

console.log(usePage().props.auth.userRoles);

// User info from Laravel backend
const currentUser = computed(() => page.props.auth?.user || {});
const currentUserId = computed(() => currentUser.value?.id);

console.log('Current User:', currentUser.value);

// Show conditions for buttons
const showApproveButton = computed(() => {
    return (
        props.voucher.voucher_type === 'prepayment' &&
        props.voucher.status === 'Submitted'
    );
});

const showRetireButton = computed(() => {
    return (
        props.voucher.voucher_type === 'prepayment' &&
        props.voucher.status === 'Approved'
    );
});

// Permission checks - UPDATED
const canApprove = computed(() => {
    if (!showApproveButton.value) return false;

    // Also check from API if available
    if (retirementStatusData.value?.can_be_approved === false) return false;

    return (
        hasRole(['Final Account', 'admin']) || hasPermission('approve_vouchers')
    );
});

const canRetire = computed(() => {
    if (!showRetireButton.value) return false;

    // Check if retirement status data is available
    if (!retirementStatusData.value) return false;

    // Check retirement status from API
    if (
        retirementStatusData.value.already_retired ||
        !retirementStatusData.value.can_retire
    ) {
        return false;
    }

    return (
        hasRole(['Final Account', 'admin']) || hasPermission('retire_vouchers')
    );
});

// Tooltip messages - UPDATED
const approveTooltip = computed(() => {
    if (!canApprove.value) {
        const reasons = [];

        if (props.voucher.voucher_type !== 'prepayment') {
            reasons.push('Not a prepayment voucher');
        }
        if (props.voucher.status !== 'Submitted') {
            reasons.push(
                `Current status: ${props.voucher.status} (needs to be Submitted)`,
            );
        }
        if (!hasRole(['finance_manager', 'approver', 'admin'])) {
            reasons.push(
                `Current role: ${currentUser.value.roles?.join(', ') || 'None'}`,
            );
        }
        if (!hasPermission('approve_vouchers')) {
            reasons.push('No approval permission');
        }

        return reasons.join(' • ') || 'Cannot approve voucher';
    }
    return 'Approve this prepayment voucher for retirement';
});

const retireTooltip = computed(() => {
    if (!canRetire.value) {
        if (!retirementStatusData.value) {
            return 'Loading retirement status...';
        }
        if (retirementStatusData.value.already_retired) {
            return 'Voucher already retired';
        }
        if (retirementStatusData.value.voucher_status !== 'Approved') {
            return `Voucher status: ${retirementStatusData.value.voucher_status} (needs to be Approved)`;
        }
        if (retirementStatusData.value.can_retire === false) {
            return 'Voucher not ready for retirement';
        }
        if (!hasRole(['finance_manager', 'admin'])) {
            return `Requires finance manager or admin role`;
        }
        if (!hasPermission('retire_vouchers')) {
            return 'No retirement permission';
        }
        return 'Cannot retire voucher';
    }
    return 'Retire prepayment voucher';
});

// Approval function - UPDATED with better feedback
const approveVoucher = async () => {
    if (!canApprove.value) return;

    try {
        // Show loading
        const loadingToast = toast.add({
            severity: 'info',
            summary: 'Approving...',
            detail: 'Please wait while we approve the voucher',
            life: 0, // Show until removed
        });

        // Using Inertia for the request
        const response = await axios.put(
            route('vouchers.approve', { voucher: props.voucher.id }),
            {
                approved_by: currentUserId.value,
                approved_at: new Date().toISOString(),
                status: 'Approved', // Explicitly set status
            },
        );

        // Remove loading toast
        toast.remove(loadingToast);

        // Show success message
        toast.add({
            severity: 'success',
            summary: 'Voucher Approved',
            detail: 'Prepayment voucher has been approved for retirement.',
            life: 3000,
        });

        // Refresh page to show updated status
        router.reload({ only: ['voucher'] });
    } catch (error) {
        console.error('Failed to approve voucher:', error);

        // Remove any loading toast
        toast.removeAllGroups();

        // Show error message
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
            life: 3000,
        });
    }
};

// Retirement function for opening the modal
const openRetirementModal = async () => {
    if (!canRetire.value) return;

    // Check retirement status from API
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
        // Set default values if API fails
        retirementStatusData.value = {
            can_retire: false,
            already_retired: false,
            retired_amount: 0,
            available_balance: props.voucher.total_amount || 0,
        };
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

        // Check if already cached
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

        // Cache the results
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
        // Fetch economic codes first
        await fetchEconomicCodes();

        // Initialize with one empty item
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

        // Reset totals
        retirementForm.value.total_amount = 0;
        retirementForm.value.comment = '';

        // Calculate initial totals
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

        // Fallback to empty form
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
    // Get available balance from retirement status data
    const availableBalance = retirementStatusData.value?.available_balance || 0;

    console.log('=== BALANCE CHECK ===');
    console.log('Available balance:', availableBalance);
    console.log('Trying to retire:', retirementForm.value.total_amount);

    // Check if there's any balance to retire
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

    // Check if trying to retire more than available
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

    // Prepare retirement data with line items
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

    // Send retirement request
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

            // Close modal and refresh page
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

    // Reset code item when economic code changes
    item.code_item_id = null;

    // Fetch code items for the selected economic code
    if (economicCodeId) {
        const items = await fetchCodeItems(economicCodeId);
        console.log('Code items fetched:', items);

        // Auto-populate description based on selection
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
    retirementForm.value.total_amount = total;
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

        // Assuming the API returns data in response.data.data
        retirementHistory.value = response.data.data || [];

        // Show modal
        showRetirementHistoryModal.value = true;

        // Optional: Show toast notification
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

    // You can implement Excel export here
    toast.add({
        severity: 'success',
        summary: 'Export Started',
        detail: `Exporting ${retirementHistory.value.length} retirement records`,
        life: 3000,
    });

    // TODO: Implement actual Excel export
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

const formatDateTime = (dateString) => {
    if (!dateString) return 'N/A';
    return new Date(dateString).toLocaleString('en-GB');
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
                <div
                    class="justify-content-between align-items-center mb-4 flex"
                >
                    <div class="align-items-center flex gap-3">
                        <Button
                            icon="pi pi-arrow-left"
                            text
                            rounded
                            severity="secondary"
                            @click="goBack"
                            v-tooltip="'Go Back'"
                        />
                        <div>
                            <h1 class="mb-1 text-2xl font-bold">
                                {{ voucher.voucher_number }}
                            </h1>
                            <div class="align-items-center flex gap-3">
                                <Tag
                                    :value="voucher.status"
                                    :severity="
                                        getStatusSeverity(voucher.status)
                                    "
                                />
                                <Tag
                                    v-if="retirementStatusData"
                                    :value="
                                        retirementStatusData.already_retired
                                            ? 'FULLY RETIRED'
                                            : 'PENDING RETIREMENT'
                                    "
                                    :severity="
                                        retirementStatusData.already_retired
                                            ? 'success'
                                            : 'warning'
                                    "
                                    :icon="
                                        retirementStatusData.already_retired
                                            ? 'pi pi-check-circle'
                                            : 'pi pi-clock'
                                    "
                                    v-tooltip.top="
                                        retirementStatusData.already_retired
                                            ? 'Voucher is fully retired'
                                            : `Available balance: ${formatCurrency(retirementStatusData.available_balance)}`
                                    "
                                />
                                <span class="text-500">
                                    Date: {{ formatDate(voucher.voucher_date) }}
                                </span>
                                <span
                                    v-if="
                                        retirementStatusData &&
                                        retirementStatusData.retired_amount > 0
                                    "
                                    class="text-500"
                                >
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
                        <!-- Approve Button - Shows when voucher is Submitted -->
                        <Button
                            v-if="showApproveButton"
                            label="Approve Prepayment Voucher"
                            icon="pi pi-check"
                            severity="success"
                            :disabled="!canApprove"
                            v-tooltip="approveTooltip"
                            @click="approveVoucher"
                        />

                        <!-- Retire Button - Shows when voucher is Approved -->
                        <Button
                            v-if="showRetireButton"
                            label="Approve Prepayment Voucher For Retirement"
                            icon="pi pi-check-circle"
                            severity="secondary"
                            :disabled="!canRetire"
                            v-tooltip="retireTooltip"
                            @click="openRetirementModal"
                        />

                        <Button
                            label="Print"
                            icon="pi pi-print"
                            severity="info"
                            @click="printVoucher"
                        />
                        <Button
                            label="Edit Voucher"
                            icon="pi pi-pencil"
                            severity="secondary"
                            :disabled="!canEditVoucher(voucher)"
                            v-tooltip="
                                canEditVoucher(voucher)
                                    ? 'Edit Voucher'
                                    : 'Cannot edit this voucher'
                            "
                            @click="editVoucher"
                        />
                        <Button
                            label="Delete"
                            icon="pi pi-trash"
                            severity="danger"
                            :disabled="!canDeleteVoucher(voucher)"
                            v-tooltip="
                                canDeleteVoucher(voucher)
                                    ? 'Delete Voucher'
                                    : 'Cannot delete this voucher'
                            "
                            @click="deleteVoucher"
                        />
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
                                    <label class="text-500 font-semibold"
                                        >Voucher Number</label
                                    >
                                    <div class="text-900 text-lg font-medium">
                                        {{ voucher.voucher_number }}
                                    </div>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="field">
                                    <label class="text-500 font-semibold"
                                        >Voucher Date</label
                                    >
                                    <div class="text-900">
                                        {{ formatDate(voucher.voucher_date) }}
                                    </div>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="field">
                                    <label class="text-500 font-semibold"
                                        >Voucher Type</label
                                    >
                                    <div class="text-900 font-medium">
                                        <Tag
                                            :value="
                                                voucher.voucher_type?.toUpperCase() ||
                                                'N/A'
                                            "
                                            :severity="
                                                voucher.voucher_type ===
                                                'prepayment'
                                                    ? 'warning'
                                                    : 'info'
                                            "
                                        />
                                    </div>
                                </div>
                            </div>

                            <!-- Retirement Status from API -->
                            <div class="col-6" v-if="retirementStatusData">
                                <div class="field">
                                    <label class="text-500 font-semibold"
                                        >Retirement Status</label
                                    >
                                    <div class="text-900 font-medium">
                                        <Tag
                                            :value="
                                                retirementStatusData.already_retired
                                                    ? 'Fully Retired'
                                                    : 'Not Retired'
                                            "
                                            :severity="
                                                retirementStatusData.already_retired
                                                    ? 'success'
                                                    : 'warning'
                                            "
                                            :icon="
                                                retirementStatusData.already_retired
                                                    ? 'pi pi-check-circle'
                                                    : 'pi pi-clock'
                                            "
                                        />
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

                            <!-- Retirement Amount Details -->
                            <div class="col-12" v-if="retirementStatusData">
                                <div class="field">
                                    <label class="text-500 font-semibold"
                                        >Retirement Details</label
                                    >
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
                                            <div
                                                class="text-900 font-medium"
                                                :class="
                                                    retirementStatusData.available_balance >
                                                    0
                                                        ? 'text-green-600'
                                                        : 'text-gray-600'
                                                "
                                            >
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
                                    <label class="text-500 font-semibold"
                                        >MDA</label
                                    >
                                    <div class="text-900 font-medium">
                                        <span v-if="voucher.mda">
                                            {{ voucher.mda.name }}
                                            <span class="text-500 ml-2"
                                                >({{ voucher.mda.code }})</span
                                            >
                                        </span>
                                        <span v-else class="text-500">N/A</span>
                                    </div>
                                </div>
                            </div>

                            <!-- Retirement History Button -->
                            <div
                                class="col-12"
                                v-if="
                                    retirementStatusData &&
                                    retirementStatusData.retired_amount > 0
                                "
                            >
                                <div class="field">
                                    <Button
                                        label="View Retirement History"
                                        icon="pi pi-history"
                                        severity="info"
                                        size="small"
                                        outlined
                                        @click="viewRetirementHistory"
                                    />
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
                            <!-- Payee Information -->
                            <div class="col-12" v-if="voucher.payee_name">
                                <div class="field">
                                    <label class="text-500 font-semibold"
                                        >Payee/Beneficiary</label
                                    >
                                    <div
                                        class="text-900 text-primary text-xl font-bold"
                                    >
                                        {{ voucher.payee_name }}
                                    </div>
                                </div>
                            </div>

                            <!-- Bank Activity Information -->
                            <template v-if="voucher.bankActivity">
                                <div class="col-12">
                                    <div class="field">
                                        <label class="text-500 font-semibold"
                                            >Payment Title</label
                                        >
                                        <div
                                            class="text-900 text-primary text-lg font-medium"
                                        >
                                            {{ voucher.bankActivity.title }}
                                        </div>
                                    </div>
                                </div>
                                <div class="col-6">
                                    <div class="field">
                                        <label class="text-500 font-semibold"
                                            >Bank Name</label
                                        >
                                        <div class="text-900 font-medium">
                                            {{ voucher.bankActivity.bank_name }}
                                        </div>
                                    </div>
                                </div>
                                <div class="col-6">
                                    <div class="field">
                                        <label class="text-500 font-semibold"
                                            >Account Number</label
                                        >
                                        <div class="text-900 font-medium">
                                            {{
                                                voucher.bankActivity
                                                    .account_number
                                            }}
                                        </div>
                                    </div>
                                </div>
                                <div class="col-12">
                                    <div class="field">
                                        <label class="text-500 font-semibold"
                                            >Payment Tag/Reference</label
                                        >
                                        <div class="text-900">
                                            <Tag
                                                :value="
                                                    voucher.bankActivity.tag ||
                                                    'No tag'
                                                "
                                                severity="info"
                                            />
                                        </div>
                                    </div>
                                </div>
                            </template>

                            <!-- Amount Information -->
                            <div class="col-12">
                                <div class="field">
                                    <label class="text-500 font-semibold"
                                        >Total Amount</label
                                    >
                                    <div
                                        class="text-900 text-primary text-2xl font-bold"
                                    >
                                        {{
                                            formatCurrency(
                                                voucher.amount ||
                                                    voucher.total_amount,
                                            )
                                        }}
                                    </div>
                                </div>
                            </div>

                            <!-- Amount in Words -->
                            <div class="col-12">
                                <div class="field">
                                    <label class="text-500 font-semibold"
                                        >Amount in Words</label
                                    >
                                    <div
                                        class="text-900 border-round border-1 border-200 bg-gray-50 p-2 italic"
                                    >
                                        {{ amountInWords }}
                                    </div>
                                </div>
                            </div>

                            <!-- Retirement Progress Section -->
                            <div
                                class="col-12"
                                v-if="
                                    retirementStatusData &&
                                    voucher.voucher_type === 'prepayment'
                                "
                            >
                                <div class="field">
                                    <label class="text-500 font-semibold"
                                        >Retirement Progress</label
                                    >
                                    <div class="space-y-2">
                                        <!-- Progress Bar -->
                                        <div
                                            class="relative h-4 w-full rounded-full bg-gray-200"
                                        >
                                            <div
                                                class="bg-primary h-4 rounded-full transition-all duration-300"
                                                :style="`width: ${retirementProgress}%;`"
                                                :class="{
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
                                                }"
                                            ></div>
                                            <div
                                                class="absolute inset-0 flex items-center justify-center"
                                            >
                                                <span
                                                    class="text-xs font-semibold text-white"
                                                >
                                                    {{
                                                        retirementProgress.toFixed(
                                                            1,
                                                        )
                                                    }}%
                                                </span>
                                            </div>
                                        </div>

                                        <!-- Progress Details -->
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
                                                <div
                                                    class="text-900 font-bold"
                                                    :class="{
                                                        'text-green-600':
                                                            retirementStatusData.retired_amount >
                                                            0,
                                                        'text-gray-600':
                                                            retirementStatusData.retired_amount ===
                                                            0,
                                                    }"
                                                >
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
                                                <div
                                                    class="text-900 font-bold"
                                                    :class="{
                                                        'text-green-600':
                                                            retirementStatusData.available_balance >
                                                            0,
                                                        'text-orange-600':
                                                            retirementStatusData.available_balance ===
                                                            0,
                                                        'text-red-600':
                                                            retirementStatusData.available_balance <
                                                            0,
                                                    }"
                                                >
                                                    {{
                                                        formatCurrency(
                                                            retirementStatusData.available_balance,
                                                        )
                                                    }}
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Retirement Status -->
                                        <div class="mt-2 text-center">
                                            <Tag
                                                :value="
                                                    getRetirementStatusText()
                                                "
                                                :severity="
                                                    getRetirementStatusSeverity(
                                                        getRetirementStatusText(),
                                                    )
                                                "
                                                :icon="
                                                    retirementStatusData.already_retired
                                                        ? 'pi pi-check-circle'
                                                        : 'pi pi-clock'
                                                "
                                                class="font-semibold"
                                            />
                                            <div
                                                v-if="voucher.retired_at"
                                                class="text-500 mt-1 text-xs"
                                            >
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

                            <!-- Status and Metadata -->
                            <div class="col-6">
                                <div class="field">
                                    <label class="text-500 font-semibold"
                                        >Voucher Status</label
                                    >
                                    <div>
                                        <Tag
                                            :value="voucher.status"
                                            :severity="
                                                getStatusSeverity(
                                                    voucher.status,
                                                )
                                            "
                                        />
                                    </div>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="field">
                                    <label class="text-500 font-semibold"
                                        >Line Items</label
                                    >
                                    <div class="text-900 font-medium">
                                        {{ totalItems }} items
                                    </div>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="field">
                                    <label class="text-500 font-semibold"
                                        >Created By</label
                                    >
                                    <div class="text-900">
                                        {{ voucher.creator?.name || 'N/A' }}
                                    </div>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="field">
                                    <label class="text-500 font-semibold"
                                        >Created Date</label
                                    >
                                    <div class="text-900">
                                        {{ formatDateTime(voucher.created_at) }}
                                    </div>
                                </div>
                            </div>

                            <!-- Approval Information -->
                            <div
                                class="col-12"
                                v-if="
                                    voucher.approved_by || voucher.approved_at
                                "
                            >
                                <div class="field">
                                    <label class="text-500 font-semibold"
                                        >Approval Details</label
                                    >
                                    <div class="text-900">
                                        <div
                                            v-if="voucher.approved_by"
                                            class="mb-1"
                                        >
                                            Approved by:
                                            {{ voucher.approved_by }}
                                        </div>
                                        <div
                                            v-if="voucher.approved_at"
                                            class="text-500 text-sm"
                                        >
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
                        <div
                            class="justify-content-between align-items-center flex"
                        >
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
                        <DataTable
                            :value="voucher.items || []"
                            dataKey="id"
                            stripedRows
                            responsiveLayout="scroll"
                            class="p-datatable-sm"
                            :emptyMessage="'No items found in this voucher.'"
                        >
                            <Column
                                field="description"
                                header="Description"
                                headerStyle="width: 40%"
                            >
                                <template #body="slotProps">
                                    <span class="font-medium">
                                        {{ slotProps.data.description }}
                                    </span>
                                </template>
                            </Column>

                            <Column
                                field="economy_code"
                                header="Economy Code"
                                headerStyle="width: 15%"
                            >
                                <template #body="slotProps">
                                    <div
                                        v-if="slotProps.data.economy_code"
                                        class="flex-column flex"
                                    >
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

                            <Column
                                field="economy_code_item"
                                header="Code Item"
                                headerStyle="width: 15%"
                            >
                                <template #body="slotProps">
                                    <div
                                        v-if="slotProps.data.economy_code_item"
                                        class="flex-column flex"
                                    >
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

                            <Column
                                field="quantity"
                                header="Qty"
                                headerStyle="width: 8%"
                                bodyClass="text-center"
                            >
                                <template #body="slotProps">
                                    <span class="font-mono">{{
                                        slotProps.data.quantity
                                    }}</span>
                                </template>
                            </Column>

                            <Column
                                field="unit_price"
                                header="Unit Price"
                                headerStyle="width: 12%"
                                bodyClass="text-right"
                            >
                                <template #body="slotProps">
                                    <span class="font-medium">{{
                                        formatCurrency(
                                            slotProps.data.unit_price,
                                        )
                                    }}</span>
                                </template>
                            </Column>

                            <Column
                                field="sub_total"
                                header="Sub Total"
                                headerStyle="width: 10%"
                                bodyClass="text-right font-bold"
                            >
                                <template #body="slotProps">
                                    <span class="text-primary">{{
                                        formatCurrency(slotProps.data.sub_total)
                                    }}</span>
                                </template>
                            </Column>
                        </DataTable>

                        <!-- Summary Row -->
                        <div
                            class="justify-content-between align-items-center border-round bg-primary-50 mt-4 flex border-1 border-200 p-3"
                        >
                            <span class="text-lg font-bold">Grand Total</span>
                            <span class="text-primary text-xl font-bold">{{
                                formatCurrency(voucher.total_amount)
                            }}</span>
                        </div>
                    </template>
                </Card>
            </div>

            <!-- Documents Section -->
            <div
                class="col-12 md:col-6"
                v-if="voucher.documents && voucher.documents.length > 0"
            >
                <Card>
                    <template #title>
                        <div class="align-items-center flex gap-2">
                            <i class="pi pi-file-pdf text-primary"></i>
                            <span
                                >Supporting Documents ({{
                                    totalDocuments
                                }})</span
                            >
                        </div>
                    </template>
                    <template #content>
                        <div class="space-y-2">
                            <div
                                v-for="document in voucher.documents"
                                :key="document.id"
                                class="align-items-center justify-content-between border-round flex border-1 border-200 p-3"
                            >
                                <div class="align-items-center flex gap-3">
                                    <i
                                        v-if="document.is_pdf"
                                        class="pi pi-file-pdf text-red-500"
                                    ></i>
                                    <i
                                        v-else-if="document.is_image"
                                        class="pi pi-image text-green-500"
                                    ></i>
                                    <i
                                        v-else
                                        class="pi pi-file text-blue-500"
                                    ></i>
                                    <div>
                                        <div class="font-medium">
                                            {{ document.file_name }}
                                        </div>
                                        <div class="text-500 text-sm">
                                            {{ document.document_type_label }} •
                                            {{
                                                (
                                                    document.file_size / 1024
                                                ).toFixed(2)
                                            }}
                                            KB
                                        </div>
                                    </div>
                                </div>
                                <Button
                                    icon="pi pi-download"
                                    text
                                    rounded
                                    severity="info"
                                    @click="window.open(document.url, '_blank')"
                                    v-tooltip="'Download document'"
                                />
                            </div>
                        </div>
                    </template>
                </Card>
            </div>

            <!-- Approval History -->
            <div
                class="col-12"
                :class="
                    voucher.documents && voucher.documents.length > 0
                        ? 'md:col-6'
                        : 'md:col-12'
                "
            >
                <Card>
                    <template #title>
                        <div class="align-items-center flex gap-2">
                            <i class="pi pi-history text-primary"></i>
                            <span>Approval History</span>
                        </div>
                    </template>
                    <template #content>
                        <div
                            v-if="
                                voucher.approvals &&
                                voucher.approvals.length > 0
                            "
                            class="space-y-3"
                        >
                            <div
                                v-for="approval in voucher.approvals"
                                :key="approval.id"
                                class="border-round border-1 border-200 p-3"
                            >
                                <div class="justify-content-between flex">
                                    <div>
                                        <div class="font-medium">
                                            {{
                                                approval.user?.name ||
                                                'Unknown User'
                                            }}
                                            <Tag
                                                :value="approval.approval_role"
                                                severity="info"
                                                class="ml-2"
                                            />
                                        </div>
                                        <div class="text-500 mt-1 text-sm">
                                            Step {{ approval.approval_step }} •
                                            {{
                                                formatDateTime(
                                                    approval.action_at,
                                                )
                                            }}
                                        </div>
                                    </div>
                                    <div>
                                        <Tag
                                            :value="approval.action"
                                            :severity="
                                                {
                                                    Approved: 'success',
                                                    Declined: 'danger',
                                                    'Sent Back': 'warning',
                                                    Forwarded: 'info',
                                                    Saved: 'secondary',
                                                }[approval.action] || 'info'
                                            "
                                        />
                                    </div>
                                </div>
                                <div
                                    v-if="approval.comment"
                                    class="text-500 mt-2 border-200 border-l-3 pl-2 text-sm"
                                >
                                    <i class="pi pi-comment mr-1"></i
                                    >{{ approval.comment }}
                                </div>
                            </div>
                        </div>
                        <div v-else class="text-500 p-4 text-center">
                            <i class="pi pi-info-circle mr-2"></i>No approval
                            history available
                        </div>
                    </template>
                </Card>
            </div>
        </div>

        <!-- Enhanced Retirement Modal -->
        <Dialog
            v-model:visible="showRetirementModal"
            :style="{ width: '98vw', maxWidth: '1600px', maxHeight: '95vh' }"
            header="Retire Prepayment Voucher"
            :modal="true"
            :closable="true"
            @hide="showRetirementModal = false"
            contentClass="flex flex-column"
        >
            <div class="flex-grow-1 overflow-auto p-3">
                <!-- Top Row: Voucher Details -->
                <div class="mb-4">
                    <Card>
                        <template #title>
                            <div class="align-items-center flex gap-2">
                                <i class="pi pi-info-circle text-primary"></i>
                                <span>Voucher Details</span>
                            </div>
                        </template>
                        <template #content>
                            <div class="grid">
                                <div class="col-12 md:col-2">
                                    <div class="field">
                                        <label
                                            class="text-500 text-sm font-semibold"
                                            >Voucher Number</label
                                        >
                                        <div class="text-900 font-bold">
                                            {{ voucher.voucher_number }}
                                        </div>
                                    </div>
                                </div>
                                <div class="col-12 md:col-2">
                                    <div class="field">
                                        <label
                                            class="text-500 text-sm font-semibold"
                                            >Voucher Type</label
                                        >
                                        <div>
                                            <Tag
                                                :value="
                                                    voucher.voucher_type?.toUpperCase()
                                                "
                                                severity="warning"
                                                class="text-sm"
                                            />
                                        </div>
                                    </div>
                                </div>
                                <div class="col-12 md:col-3">
                                    <div class="field">
                                        <label
                                            class="text-500 text-sm font-semibold"
                                            >Payee/Beneficiary</label
                                        >
                                        <div
                                            class="text-900 truncate font-medium"
                                        >
                                            {{ voucher.payee_name || 'N/A' }}
                                        </div>
                                    </div>
                                </div>
                                <div class="col-12 md:col-2">
                                    <div class="field">
                                        <label
                                            class="text-500 text-sm font-semibold"
                                            >MDA</label
                                        >
                                        <div class="text-900">
                                            <span v-if="voucher.mda">
                                                {{ voucher.mda.code }}
                                            </span>
                                            <span v-else>N/A</span>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-12 md:col-3">
                                    <div class="field">
                                        <label
                                            class="text-500 text-sm font-semibold"
                                            >Total Amount</label
                                        >
                                        <div
                                            class="text-primary text-lg font-bold"
                                        >
                                            {{
                                                formatCurrency(
                                                    voucher.total_amount,
                                                )
                                            }}
                                        </div>
                                    </div>
                                </div>
                                <div class="col-12">
                                    <div class="field">
                                        <label
                                            class="text-500 text-sm font-semibold"
                                            >Narration</label
                                        >
                                        <div class="text-900 text-sm italic">
                                            {{
                                                voucher.narration ||
                                                'No description provided'
                                            }}
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </template>
                    </Card>
                </div>

                <!-- Middle Row: Retirement Line Items -->
                <div class="mb-4">
                    <Card>
                        <template #title>
                            <div
                                class="align-items-center justify-content-between flex"
                            >
                                <div class="align-items-center flex gap-2">
                                    <i class="pi pi-list text-primary"></i>
                                    <span>Retirement Line Items</span>
                                    <Tag
                                        :value="
                                            retirementForm.line_items.length
                                        "
                                        severity="info"
                                        class="ml-2"
                                    />
                                </div>
                                <div class="align-items-center flex gap-2">
                                    <span class="text-500 text-sm"
                                        >Total:
                                        {{
                                            formatCurrency(
                                                retirementForm.total_amount,
                                            )
                                        }}</span
                                    >
                                    <Button
                                        label="Add Item"
                                        icon="pi pi-plus"
                                        severity="secondary"
                                        size="small"
                                        outlined
                                        @click="addNewItem"
                                    />
                                </div>
                            </div>
                        </template>
                        <template #content>
                            <div class="flex-column flex">
                                <!-- Summary Banner -->
                                <div class="border-round surface-100 mb-3 p-3">
                                    <div class="grid">
                                        <div class="col-12 md:col-4">
                                            <div class="text-500 text-xs">
                                                Voucher Total
                                            </div>
                                            <div
                                                class="text-900 text-lg font-bold"
                                            >
                                                {{
                                                    formatCurrency(
                                                        voucher.total_amount,
                                                    )
                                                }}
                                            </div>
                                        </div>
                                        <div
                                            class="col-12 text-center md:col-4"
                                        >
                                            <div class="text-500 text-xs">
                                                Retirement Progress
                                            </div>
                                            <div
                                                class="text-900 text-lg font-bold"
                                                :class="{
                                                    'text-green-600':
                                                        retirementProgress ===
                                                        100,
                                                    'text-orange-600':
                                                        retirementProgress <
                                                            100 &&
                                                        retirementProgress > 0,
                                                    'text-red-600':
                                                        retirementProgress >
                                                        100,
                                                }"
                                            >
                                                {{ retirementProgress }}%
                                            </div>
                                        </div>
                                        <div class="col-12 text-right md:col-4">
                                            <div class="text-500 text-xs">
                                                Balance
                                            </div>
                                            <div
                                                class="text-900 text-lg font-bold"
                                                :class="{
                                                    'text-green-600':
                                                        remainingBalance === 0,
                                                    'text-orange-600':
                                                        remainingBalance > 0,
                                                    'text-red-600':
                                                        remainingBalance < 0,
                                                }"
                                            >
                                                {{
                                                    formatCurrency(
                                                        Math.abs(
                                                            remainingBalance,
                                                        ),
                                                    )
                                                }}
                                                <span
                                                    class="ml-1 text-sm"
                                                    :class="{
                                                        'text-green-600':
                                                            remainingBalance ===
                                                            0,
                                                        'text-orange-600':
                                                            remainingBalance >
                                                            0,
                                                        'text-red-600':
                                                            remainingBalance <
                                                            0,
                                                    }"
                                                >
                                                    {{
                                                        remainingBalance === 0
                                                            ? '(Balanced)'
                                                            : remainingBalance >
                                                                0
                                                              ? '(Remaining)'
                                                              : '(Exceeded)'
                                                    }}
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Line Items Table -->
                                <div class="border-round border-1 border-200">
                                    <DataTable
                                        :value="retirementForm.line_items"
                                        dataKey="temp_id"
                                        responsiveLayout="scroll"
                                        class="p-datatable-sm"
                                        :emptyMessage="'No items added. Click Add Item to start.'"
                                        scrollable
                                        scrollHeight="400px"
                                        tableStyle="min-width: 100%"
                                        stripedRows
                                    >
                                        <!-- Actions -->
                                        <Column
                                            headerStyle="width: 5%; min-width: 50px;"
                                        >
                                            <template #body="slotProps">
                                                <Button
                                                    icon="pi pi-trash"
                                                    severity="danger"
                                                    text
                                                    rounded
                                                    size="small"
                                                    @click="
                                                        removeItem(
                                                            slotProps.index,
                                                        )
                                                    "
                                                    :disabled="
                                                        retirementForm
                                                            .line_items
                                                            .length === 1
                                                    "
                                                    v-tooltip="'Remove item'"
                                                />
                                            </template>
                                        </Column>

                                        <!-- Description -->
                                        <Column
                                            header="Description"
                                            headerStyle="width: 25%; min-width: 200px;"
                                        >
                                            <template #body="slotProps">
                                                <InputText
                                                    v-model="
                                                        slotProps.data
                                                            .description
                                                    "
                                                    placeholder="Enter description"
                                                    class="w-full text-sm"
                                                    @change="calculateTotals"
                                                />
                                            </template>
                                        </Column>

                                        <!-- Economic Code -->
                                        <Column
                                            header="Economic Code"
                                            headerStyle="width: 15%; min-width: 150px;"
                                        >
                                            <template #body="slotProps">
                                                <Dropdown
                                                    v-model="
                                                        slotProps.data
                                                            .economic_code_id
                                                    "
                                                    :options="economicCodes"
                                                    optionLabel="label"
                                                    optionValue="id"
                                                    placeholder="Select Code"
                                                    class="w-full text-sm"
                                                    @change="
                                                        onEconomicCodeChange(
                                                            slotProps.data,
                                                            $event.value,
                                                        )
                                                    "
                                                    :loading="
                                                        loadingEconomicCodes
                                                    "
                                                    :filter="true"
                                                    filterPlaceholder="Search code..."
                                                    :showClear="true"
                                                >
                                                    <template
                                                        #option="slotProps"
                                                    >
                                                        <div class="py-1">
                                                            <div
                                                                class="text-sm font-medium"
                                                            >
                                                                {{
                                                                    slotProps
                                                                        .option
                                                                        .code
                                                                }}
                                                            </div>
                                                            <div
                                                                class="text-500 truncate text-xs"
                                                            >
                                                                {{
                                                                    slotProps
                                                                        .option
                                                                        .name
                                                                }}
                                                            </div>
                                                        </div>
                                                    </template>
                                                </Dropdown>
                                            </template>
                                        </Column>

                                        <!-- Code Item -->
                                        <Column
                                            header="Code Item"
                                            headerStyle="width: 15%; min-width: 150px;"
                                        >
                                            <template #body="slotProps">
                                                <Dropdown
                                                    v-model="
                                                        slotProps.data
                                                            .code_item_id
                                                    "
                                                    :options="
                                                        getCodeItems(
                                                            slotProps.data
                                                                .economic_code_id,
                                                        )
                                                    "
                                                    optionLabel="label"
                                                    optionValue="id"
                                                    placeholder="Select Item"
                                                    class="w-full text-sm"
                                                    :disabled="
                                                        !slotProps.data
                                                            .economic_code_id
                                                    "
                                                    :loading="loadingCodeItems"
                                                    :filter="true"
                                                    filterPlaceholder="Search item..."
                                                    :showClear="true"
                                                >
                                                    <template
                                                        #option="slotProps"
                                                    >
                                                        <div class="py-1">
                                                            <div
                                                                class="text-sm font-medium"
                                                            >
                                                                {{
                                                                    slotProps
                                                                        .option
                                                                        .code
                                                                }}
                                                            </div>
                                                            <div
                                                                class="text-500 truncate text-xs"
                                                            >
                                                                {{
                                                                    slotProps
                                                                        .option
                                                                        .name
                                                                }}
                                                            </div>
                                                        </div>
                                                    </template>
                                                </Dropdown>
                                            </template>
                                        </Column>

                                        <!-- Quantity -->
                                        <Column
                                            header="Qty"
                                            headerStyle="width: 10%; min-width: 100px;"
                                        >
                                            <template #body="slotProps">
                                                <div
                                                    class="align-items-center flex gap-1"
                                                >
                                                    <Button
                                                        icon="pi pi-minus"
                                                        severity="secondary"
                                                        size="small"
                                                        text
                                                        rounded
                                                        @click="
                                                            decrementQty(
                                                                slotProps.data,
                                                            )
                                                        "
                                                        :disabled="
                                                            slotProps.data
                                                                .quantity <= 1
                                                        "
                                                        v-tooltip="
                                                            'Decrease quantity'
                                                        "
                                                    />
                                                    <InputNumber
                                                        v-model="
                                                            slotProps.data
                                                                .quantity
                                                        "
                                                        mode="decimal"
                                                        :min="0.01"
                                                        :max="999999"
                                                        :step="1"
                                                        class="w-8rem text-sm"
                                                        inputClass="text-center py-1"
                                                        @update:modelValue="
                                                            calculateTotals
                                                        "
                                                    />
                                                    <Button
                                                        icon="pi pi-plus"
                                                        severity="secondary"
                                                        size="small"
                                                        text
                                                        rounded
                                                        @click="
                                                            incrementQty(
                                                                slotProps.data,
                                                            )
                                                        "
                                                        v-tooltip="
                                                            'Increase quantity'
                                                        "
                                                    />
                                                </div>
                                            </template>
                                        </Column>

                                        <!-- Unit Price -->
                                        <Column
                                            header="Unit Price"
                                            headerStyle="width: 15%; min-width: 150px;"
                                            bodyClass="text-right"
                                        >
                                            <template #body="slotProps">
                                                <InputNumber
                                                    v-model="
                                                        slotProps.data
                                                            .unit_price
                                                    "
                                                    mode="currency"
                                                    currency="NGN"
                                                    locale="en-NG"
                                                    :min="0"
                                                    :max="1000000000"
                                                    class="w-full text-sm"
                                                    inputClass="text-right py-1"
                                                    @update:modelValue="
                                                        calculateTotals
                                                    "
                                                />
                                            </template>
                                        </Column>

                                        <!-- Sub Total -->
                                        <Column
                                            header="Sub Total"
                                            headerStyle="width: 15%; min-width: 150px;"
                                            bodyClass="text-right"
                                        >
                                            <template #body="slotProps">
                                                <div
                                                    class="font-bold"
                                                    :class="{
                                                        'text-green-600':
                                                            slotProps.data
                                                                .sub_total > 0,
                                                        'text-red-600':
                                                            slotProps.data
                                                                .sub_total <= 0,
                                                    }"
                                                >
                                                    {{
                                                        formatCurrency(
                                                            slotProps.data
                                                                .sub_total,
                                                        )
                                                    }}
                                                </div>
                                            </template>
                                        </Column>
                                    </DataTable>
                                </div>

                                <!-- Quick Actions Row -->
                                <div class="justify-content-between mt-3 flex">
                                    <div class="flex gap-2">
                                        <Button
                                            label="Auto-Balance to Zero"
                                            icon="pi pi-balance-scale"
                                            severity="help"
                                            outlined
                                            size="small"
                                            @click="autoBalanceToZero"
                                            :disabled="remainingBalance <= 0"
                                            v-tooltip="
                                                'Distribute remaining balance across items'
                                            "
                                        />
                                        <Button
                                            label="Clear All Items"
                                            icon="pi pi-trash"
                                            severity="danger"
                                            outlined
                                            size="small"
                                            @click="clearAllItems"
                                            v-tooltip="
                                                'Clear all retirement items'
                                            "
                                        />
                                    </div>
                                    <div class="text-right">
                                        <div class="text-500 text-xs">
                                            Total Retirement Amount
                                        </div>
                                        <div
                                            class="text-900 text-xl font-bold"
                                            :class="{
                                                'text-green-600':
                                                    retirementForm.total_amount ===
                                                    voucher.total_amount,
                                                'text-orange-600':
                                                    retirementForm.total_amount <
                                                        voucher.total_amount &&
                                                    retirementForm.total_amount >
                                                        0,
                                                'text-red-600':
                                                    retirementForm.total_amount >
                                                    voucher.total_amount,
                                            }"
                                        >
                                            {{
                                                formatCurrency(
                                                    retirementForm.total_amount,
                                                )
                                            }}
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </template>
                    </Card>
                </div>

                <!-- Bottom Row: Retirement Summary & Actions -->
                <div>
                    <Card>
                        <template #title>
                            <div class="align-items-center flex gap-2">
                                <i class="pi pi-calculator text-primary"></i>
                                <span>Retirement Summary & Actions</span>
                            </div>
                        </template>
                        <template #content>
                            <div class="grid">
                                <!-- Left: Balance Status & Validation -->
                                <div class="col-12 md:col-8">
                                    <div class="grid">
                                        <div class="col-12 md:col-6">
                                            <div
                                                class="border-round p-3"
                                                :class="{
                                                    'border-green-200 bg-green-50':
                                                        remainingBalance === 0,
                                                    'border-orange-200 bg-orange-50':
                                                        remainingBalance > 0,
                                                    'border-red-200 bg-red-50':
                                                        remainingBalance < 0,
                                                }"
                                            >
                                                <div class="text-center">
                                                    <i
                                                        class="pi mb-2 text-3xl"
                                                        :class="{
                                                            'pi-check-circle text-green-600':
                                                                remainingBalance ===
                                                                0,
                                                            'pi-exclamation-triangle text-orange-600':
                                                                remainingBalance >
                                                                0,
                                                            'pi-exclamation-circle text-red-600':
                                                                remainingBalance <
                                                                0,
                                                        }"
                                                    ></i>
                                                    <div
                                                        class="text-xl font-bold"
                                                    >
                                                        {{
                                                            formatCurrency(
                                                                Math.abs(
                                                                    remainingBalance,
                                                                ),
                                                            )
                                                        }}
                                                    </div>
                                                    <div
                                                        class="text-500 mt-1 text-sm"
                                                    >
                                                        <span
                                                            v-if="
                                                                remainingBalance ===
                                                                0
                                                            "
                                                            >✓ Perfectly
                                                            Balanced</span
                                                        >
                                                        <span
                                                            v-else-if="
                                                                remainingBalance >
                                                                0
                                                            "
                                                            >⚠ Partial
                                                            Retirement</span
                                                        >
                                                        <span v-else
                                                            >✗ Amount
                                                            Exceeded</span
                                                        >
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="col-12 md:col-6">
                                            <div class="space-y-2">
                                                <Message
                                                    v-if="remainingBalance < 0"
                                                    severity="error"
                                                    :closable="false"
                                                    class="py-2"
                                                >
                                                    <div class="font-bold">
                                                        Amount exceeded by
                                                        {{
                                                            formatCurrency(
                                                                Math.abs(
                                                                    remainingBalance,
                                                                ),
                                                            )
                                                        }}
                                                    </div>
                                                    <div class="text-sm">
                                                        Reduce the retirement
                                                        amount to proceed
                                                    </div>
                                                </Message>
                                                <Message
                                                    v-else-if="
                                                        remainingBalance > 0
                                                    "
                                                    severity="warn"
                                                    :closable="false"
                                                    class="py-2"
                                                >
                                                    <div class="font-bold">
                                                        {{
                                                            formatCurrency(
                                                                remainingBalance,
                                                            )
                                                        }}
                                                        remaining
                                                    </div>
                                                    <div class="text-sm">
                                                        This will be a partial
                                                        retirement
                                                    </div>
                                                </Message>
                                                <Message
                                                    v-else
                                                    severity="success"
                                                    :closable="false"
                                                    class="py-2"
                                                >
                                                    <div class="font-bold">
                                                        Ready to retire!
                                                    </div>
                                                    <div class="text-sm">
                                                        Complete retirement of
                                                        {{
                                                            formatCurrency(
                                                                voucher.total_amount,
                                                            )
                                                        }}
                                                    </div>
                                                </Message>

                                                <div
                                                    class="border-round border-1 border-200 p-2"
                                                >
                                                    <div
                                                        class="text-500 text-xs"
                                                    >
                                                        Retirement Type
                                                    </div>
                                                    <div
                                                        class="text-900 font-bold"
                                                    >
                                                        {{
                                                            remainingBalance ===
                                                            0
                                                                ? 'Complete Retirement'
                                                                : 'Partial Retirement'
                                                        }}
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Comment Section -->
                                        <div class="col-12 mt-3">
                                            <div>
                                                <label
                                                    for="retirementComment"
                                                    class="text-500 mb-2 block text-sm font-semibold"
                                                >
                                                    <i
                                                        class="pi pi-comment mr-1"
                                                    ></i
                                                    >Retirement Comments
                                                </label>
                                                <Textarea
                                                    id="retirementComment"
                                                    v-model="
                                                        retirementForm.comment
                                                    "
                                                    rows="3"
                                                    class="w-full"
                                                    placeholder="Add any comments about this retirement (optional)..."
                                                    autoResize
                                                />
                                                <small class="text-500 text-xs"
                                                    >Optional: Explain the
                                                    purpose or details of this
                                                    retirement</small
                                                >
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Right: Action Buttons -->
                                <div class="col-12 md:col-4">
                                    <!-- Totals Card -->
                                    <div
                                        class="border-round surface-50 mb-3 p-3"
                                    >
                                        <!-- Voucher Header -->
                                        <div class="mb-4 text-center">
                                            <div class="text-500 text-sm">
                                                Voucher
                                            </div>
                                            <div
                                                class="text-900 text-lg font-bold"
                                            >
                                                {{ voucher.voucher_number }}
                                            </div>
                                            <div class="text-500 text-xs">
                                                {{
                                                    formatDate(
                                                        voucher.voucher_date,
                                                    )
                                                }}
                                            </div>
                                        </div>

                                        <!-- Totals Section -->
                                        <div class="space-y-3">
                                            <!-- Original Amount -->
                                            <div
                                                class="border-round border-1 border-200 p-2"
                                            >
                                                <div class="text-500 text-xs">
                                                    Original Amount
                                                </div>
                                                <div class="text-900 font-bold">
                                                    {{
                                                        formatCurrency(
                                                            voucher.total_amount,
                                                        )
                                                    }}
                                                </div>
                                            </div>

                                            <!-- Retirement Amount -->
                                            <div
                                                class="border-round border-1 border-200 p-2"
                                            >
                                                <div class="text-500 text-xs">
                                                    Retirement Amount
                                                </div>
                                                <div class="text-900 font-bold">
                                                    {{
                                                        formatCurrency(
                                                            retirementForm.total_amount,
                                                        )
                                                    }}
                                                </div>
                                            </div>

                                            <!-- Line Items -->
                                            <div
                                                class="border-round border-1 border-200 p-2"
                                            >
                                                <div class="text-500 text-xs">
                                                    Line Items
                                                </div>
                                                <div class="text-900 font-bold">
                                                    {{
                                                        retirementForm
                                                            .line_items.length
                                                    }}
                                                    items
                                                </div>
                                            </div>

                                            <!-- Balance Status -->
                                            <div
                                                class="border-round border-1 border-200 p-2"
                                                :class="{
                                                    'border-green-200 bg-green-50':
                                                        remainingBalance === 0,
                                                    'border-orange-200 bg-orange-50':
                                                        remainingBalance > 0,
                                                    'border-red-200 bg-red-50':
                                                        remainingBalance < 0,
                                                }"
                                            >
                                                <div class="text-500 text-xs">
                                                    Balance
                                                </div>
                                                <div
                                                    class="text-900 font-bold"
                                                    :class="{
                                                        'text-green-600':
                                                            remainingBalance ===
                                                            0,
                                                        'text-orange-600':
                                                            remainingBalance >
                                                            0,
                                                        'text-red-600':
                                                            remainingBalance <
                                                            0,
                                                    }"
                                                >
                                                    {{
                                                        formatCurrency(
                                                            Math.abs(
                                                                remainingBalance,
                                                            ),
                                                        )
                                                    }}
                                                    <span
                                                        class="ml-1 text-sm"
                                                        :class="{
                                                            'text-green-600':
                                                                remainingBalance ===
                                                                0,
                                                            'text-orange-600':
                                                                remainingBalance >
                                                                0,
                                                            'text-red-600':
                                                                remainingBalance <
                                                                0,
                                                        }"
                                                    >
                                                        {{
                                                            remainingBalance ===
                                                            0
                                                                ? '(Balanced)'
                                                                : remainingBalance >
                                                                    0
                                                                  ? '(Remaining)'
                                                                  : '(Exceeded)'
                                                        }}
                                                    </span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Action Buttons Card - SEPARATE from totals -->
                                    <div class="border-round surface-50 p-3">
                                        <div class="space-y-3">
                                            <Button
                                                label="Submit Retirement"
                                                icon="pi pi-check-circle"
                                                severity="success"
                                                class="mb-2 w-full"
                                                size="large"
                                                :disabled="
                                                    remainingBalance < 0 ||
                                                    retirementForm.total_amount ===
                                                        0
                                                "
                                                @click="submitRetirement"
                                            />
                                            <Button
                                                label="Cancel"
                                                icon="pi pi-times"
                                                @click="
                                                    showRetirementModal = false
                                                "
                                                class="p-button-outlined p-button-secondary w-full"
                                            />
                                            <small
                                                class="text-500 mt-2 block text-center text-xs"
                                            >
                                                {{
                                                    remainingBalance === 0
                                                        ? 'Complete retirement'
                                                        : 'Partial retirement'
                                                }}
                                                will be recorded
                                            </small>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </template>
                    </Card>
                </div>
            </div>
        </Dialog>

        <!-- Delete Confirmation Modal -->
        <Dialog
            v-model:visible="showConfirmationModal"
            :style="{ width: '450px' }"
            header="Delete Voucher"
            :modal="true"
        >
            <div class="align-items-center flex">
                <i
                    class="pi pi-exclamation-triangle mr-3 text-red-500"
                    style="font-size: 2rem"
                ></i>
                <div>
                    <span
                        >Are you sure you want to
                        <strong class="text-red-600">permanently delete</strong>
                        Voucher <strong>{{ voucher.voucher_number }}</strong
                        >? This action cannot be undone.</span
                    >
                </div>
            </div>

            <template #footer>
                <Button
                    label="Cancel"
                    icon="pi pi-times"
                    @click="showConfirmationModal = false"
                    text
                />
                <Button
                    label="Yes, Delete"
                    icon="pi pi-trash"
                    severity="danger"
                    @click="confirmDelete"
                    autofocus
                />
            </template>
        </Dialog>

        <!-- Retirement History Modal -->
        <Dialog
            v-model:visible="showRetirementHistoryModal"
            :style="{ width: '90vw', maxWidth: '1400px', maxHeight: '90vh' }"
            header="Retirement History"
            :modal="true"
            :closable="true"
            @hide="showRetirementHistoryModal = false"
            class="retirement-history-modal"
        >
            <div class="p-3">
                <div v-if="loadingRetirementHistory" class="p-4 text-center">
                    <ProgressSpinner
                        style="width: 50px; height: 50px"
                        strokeWidth="4"
                        fill="var(--surface-ground)"
                        animationDuration=".5s"
                    />
                    <p class="text-500 mt-2">Loading retirement history...</p>
                </div>

                <div
                    v-else-if="retirementHistory.length === 0"
                    class="p-4 text-center"
                >
                    <i
                        class="pi pi-history text-500"
                        style="font-size: 3rem"
                    ></i>
                    <h3 class="text-900 mt-3">No Retirement History</h3>
                    <p class="text-500 mt-1">
                        No retirement records found for this voucher.
                    </p>
                </div>

                <div v-else class="space-y-4">
                    <!-- Summary Stats -->
                    <div class="grid">
                        <div class="col-12 md:col-4">
                            <Card>
                                <template #content>
                                    <div class="text-center">
                                        <div class="text-500 text-sm">
                                            Total Retirements
                                        </div>
                                        <div
                                            class="text-900 text-2xl font-bold"
                                        >
                                            {{ retirementHistory.length }}
                                        </div>
                                    </div>
                                </template>
                            </Card>
                        </div>
                        <div class="col-12 md:col-4">
                            <Card>
                                <template #content>
                                    <div class="text-center">
                                        <div class="text-500 text-sm">
                                            Total Amount Retired
                                        </div>
                                        <div
                                            class="text-900 text-2xl font-bold text-green-600"
                                        >
                                            {{
                                                formatCurrency(
                                                    totalRetiredAmount,
                                                )
                                            }}
                                        </div>
                                    </div>
                                </template>
                            </Card>
                        </div>
                        <div class="col-12 md:col-4">
                            <Card>
                                <template #content>
                                    <div class="text-center">
                                        <div class="text-500 text-sm">
                                            Average per Retirement
                                        </div>
                                        <div
                                            class="text-900 text-2xl font-bold text-blue-600"
                                        >
                                            {{
                                                formatCurrency(
                                                    averageRetirementAmount,
                                                )
                                            }}
                                        </div>
                                    </div>
                                </template>
                            </Card>
                        </div>
                    </div>

                    <!-- Retirement List -->
                    <Card>
                        <template #title>
                            <div class="align-items-center flex gap-2">
                                <i class="pi pi-list text-primary"></i>
                                <span
                                    >Retirement Records ({{
                                        retirementHistory.length
                                    }})</span
                                >
                            </div>
                        </template>
                        <template #content>
                            <div class="space-y-4">
                                <div
                                    v-for="retirement in retirementHistory"
                                    :key="retirement.id"
                                    class="border-round border-1 border-200 p-4"
                                >
                                    <!-- Retirement Header -->
                                    <div class="mb-3 grid">
                                        <div class="col-12 md:col-3">
                                            <div class="text-500 text-xs">
                                                Retirement Voucher
                                            </div>
                                            <div class="text-900 font-bold">
                                                {{
                                                    retirement.retirement_number ||
                                                    'N/A'
                                                }}
                                                <Tag
                                                    v-if="retirement.status"
                                                    :value="retirement.status"
                                                    :severity="
                                                        getRetirementStatusSeverity(
                                                            retirement.status,
                                                        )
                                                    "
                                                    class="ml-2 text-xs"
                                                />
                                            </div>
                                        </div>
                                        <div class="col-12 md:col-3">
                                            <div class="text-500 text-xs">
                                                Retirement Date
                                            </div>
                                            <div class="text-900 font-medium">
                                                {{
                                                    formatDateTime(
                                                        retirement.voucher_date ||
                                                            retirement.created_at,
                                                    )
                                                }}
                                            </div>
                                        </div>
                                        <div class="col-12 md:col-3">
                                            <div class="text-500 text-xs">
                                                Retirement Amount
                                            </div>
                                            <div
                                                class="text-900 text-lg font-bold text-green-600"
                                            >
                                                {{
                                                    formatCurrency(
                                                        retirement.retired_amount,
                                                    )
                                                }}
                                            </div>
                                        </div>
                                        <div class="col-12 md:col-3">
                                            <div class="text-500 text-xs">
                                                Created By
                                            </div>
                                            <div class="text-900">
                                                <div
                                                    class="align-items-center flex gap-2"
                                                >
                                                    <i
                                                        class="pi pi-user text-500"
                                                    ></i>
                                                    <span>{{
                                                        retirement.creator
                                                            ?.name || 'System'
                                                    }}</span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Retirement Items -->
                                    <div
                                        v-if="
                                            retirement.items &&
                                            retirement.items.length > 0
                                        "
                                    >
                                        <div class="text-500 mb-2 text-sm">
                                            Items Retired:
                                        </div>
                                        <div
                                            class="border-round overflow-hidden border-1 border-200"
                                        >
                                            <DataTable
                                                :value="retirement.items"
                                                size="small"
                                                class="p-datatable-sm"
                                                stripedRows
                                            >
                                                <Column
                                                    field="description"
                                                    header="Description"
                                                >
                                                    <template #body="slotProps">
                                                        {{
                                                            slotProps.data
                                                                .description
                                                        }}
                                                    </template>
                                                </Column>
                                                <Column
                                                    field="economic_code"
                                                    header="Economic Code"
                                                    style="width: 120px"
                                                >
                                                    <template #body="slotProps">
                                                        <div
                                                            v-if="
                                                                slotProps.data
                                                                    .economic_code
                                                            "
                                                        >
                                                            <div
                                                                class="font-medium"
                                                            >
                                                                {{
                                                                    slotProps
                                                                        .data
                                                                        .economic_code
                                                                        .code
                                                                }}
                                                            </div>
                                                            <small
                                                                class="text-500"
                                                            >
                                                                {{
                                                                    slotProps
                                                                        .data
                                                                        .economic_code
                                                                        .name
                                                                }}
                                                            </small>
                                                        </div>
                                                        <span
                                                            v-else
                                                            class="text-500"
                                                            >N/A</span
                                                        >
                                                    </template>
                                                </Column>
                                                <Column
                                                    field="code_item"
                                                    header="Code Item"
                                                    style="width: 120px"
                                                >
                                                    <template #body="slotProps">
                                                        <div
                                                            v-if="
                                                                slotProps.data
                                                                    .economic_code_item
                                                            "
                                                        >
                                                            <div
                                                                class="font-medium"
                                                            >
                                                                {{
                                                                    slotProps
                                                                        .data
                                                                        .economic_code_item
                                                                        .code
                                                                }}
                                                            </div>
                                                            <small
                                                                class="text-500"
                                                            >
                                                                {{
                                                                    slotProps
                                                                        .data
                                                                        .economic_code_item
                                                                        .name
                                                                }}
                                                            </small>
                                                        </div>
                                                        <span
                                                            v-else
                                                            class="text-500"
                                                            >N/A</span
                                                        >
                                                    </template>
                                                </Column>
                                                <Column
                                                    field="quantity"
                                                    header="Qty"
                                                    style="width: 80px"
                                                    bodyClass="text-center"
                                                >
                                                    <template #body="slotProps">
                                                        {{
                                                            slotProps.data
                                                                .quantity
                                                        }}
                                                    </template>
                                                </Column>
                                                <Column
                                                    field="unit_price"
                                                    header="Unit Price"
                                                    style="width: 120px"
                                                    bodyClass="text-right"
                                                >
                                                    <template #body="slotProps">
                                                        {{
                                                            formatCurrency(
                                                                slotProps.data
                                                                    .unit_price,
                                                            )
                                                        }}
                                                    </template>
                                                </Column>
                                                <Column
                                                    field="sub_total"
                                                    header="Sub Total"
                                                    style="width: 120px"
                                                    bodyClass="text-right font-bold"
                                                >
                                                    <template #body="slotProps">
                                                        <span
                                                            class="text-green-600"
                                                        >
                                                            {{
                                                                formatCurrency(
                                                                    slotProps
                                                                        .data
                                                                        .sub_total,
                                                                )
                                                            }}
                                                        </span>
                                                    </template>
                                                </Column>
                                            </DataTable>
                                        </div>
                                    </div>

                                    <!-- Retirement Comment -->
                                    <div v-if="retirement.comment" class="mt-3">
                                        <div class="text-500 text-sm">
                                            Comment
                                        </div>
                                        <div
                                            class="text-900 border-200 border-l-3 pl-2 italic"
                                        >
                                            {{ retirement.comment }}
                                        </div>
                                    </div>

                                    <!-- Action Buttons -->
                                    <div
                                        class="justify-content-end mt-3 flex gap-2"
                                    >
                                        <Button
                                            label="View Details"
                                            icon="pi pi-eye"
                                            severity="info"
                                            size="small"
                                            outlined
                                        />
                                        <Button
                                            label="Print"
                                            icon="pi pi-print"
                                            severity="secondary"
                                            size="small"
                                            outlined
                                        />
                                    </div>
                                </div>
                            </div>
                        </template>
                    </Card>

                    <!-- Timeline View (Alternative) -->
                    <Card>
                        <template #title>
                            <div class="align-items-center flex gap-2">
                                <i class="pi pi-timeline text-primary"></i>
                                <span>Retirement Timeline</span>
                            </div>
                        </template>
                        <template #content>
                            <div class="timeline">
                                <div
                                    v-for="(
                                        retirement, index
                                    ) in retirementHistory"
                                    :key="retirement.id"
                                    class="timeline-item"
                                >
                                    <div class="timeline-marker">
                                        <i
                                            class="pi pi-check-circle text-green-500"
                                        ></i>
                                    </div>
                                    <div class="timeline-content">
                                        <div
                                            class="timeline-date text-500 text-sm"
                                        >
                                            {{
                                                formatDateTime(
                                                    retirement.voucher_date ||
                                                        retirement.created_at,
                                                )
                                            }}
                                        </div>
                                        <div class="timeline-title font-bold">
                                            {{
                                                retirement.voucher_number ||
                                                `Retirement #${index + 1}`
                                            }}
                                        </div>
                                        <div class="timeline-description">
                                            <div
                                                class="align-items-center flex gap-2"
                                            >
                                                <span
                                                    class="font-bold text-green-600"
                                                    >{{
                                                        formatCurrency(
                                                            retirement.total_amount,
                                                        )
                                                    }}</span
                                                >
                                                <span class="text-500">•</span>
                                                <span
                                                    >{{
                                                        retirement.items
                                                            ?.length || 0
                                                    }}
                                                    items</span
                                                >
                                                <span class="text-500">•</span>
                                                <span
                                                    >By:
                                                    {{
                                                        retirement.creator
                                                            ?.name || 'System'
                                                    }}</span
                                                >
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </template>
                    </Card>
                </div>
            </div>

            <template #footer>
                <Button
                    label="Close"
                    icon="pi pi-times"
                    @click="showRetirementHistoryModal = false"
                    class="p-button-text"
                />
                <Button
                    label="Export to Excel"
                    icon="pi pi-file-excel"
                    severity="success"
                    @click="exportRetirementHistory"
                />
            </template>
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

.space-y-2 > * + * {
    margin-top: 0.5rem;
}

.space-y-3 > * + * {
    margin-top: 0.75rem;
}

.space-y-4 > * + * {
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
</style>
