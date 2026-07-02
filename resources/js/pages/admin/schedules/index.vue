<script setup>
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import Button from 'primevue/button';
import Card from 'primevue/card';
import Column from 'primevue/column';
import DataTable from 'primevue/datatable';
import Dialog from 'primevue/dialog';
import Tag from 'primevue/tag';
import Toast from 'primevue/toast';
import Badge from 'primevue/badge';
import Message from 'primevue/message';
import InputText from 'primevue/inputtext';
import InputIcon from 'primevue/inputicon';
import IconField from 'primevue/iconfield';
import Dropdown from 'primevue/dropdown';
import Calendar from 'primevue/calendar';
import OverlayPanel from 'primevue/overlaypanel';
import { useToast } from 'primevue/usetoast';
import { computed, ref, onMounted, watch } from 'vue';
import { FilterMatchMode } from '@primevue/core/api';
import axios from 'axios';

const schedules = ref([]);

const vTooltip = {
    mounted(el, binding) {
        el.setAttribute('title', binding.value);
        el.style.cursor = binding.value ? 'pointer' : 'default';
    },
    updated(el, binding) {
        el.setAttribute('title', binding.value);
    },
};

const filters = ref({
    global: { value: null, matchMode: FilterMatchMode.CONTAINS },
});

const toast = useToast();

const props = defineProps({
    schedules: {
        type: Object,
        required: true,
        default: () => ({
            data: [],
            total: 0,
            current_page: 1,
            per_page: 15,
            links: [],
        }),
    },
    stats: {
        type: Object,
        default: () => ({
            total_schedules: 0,
            draft_count: 0,
            submitted_count: 0,
            processed_count: 0,
            approved_count: 0,
            voucher_raised_count: 0,
            rejected_count: 0,
            total_amount: 0,
            total_amount_posted: 0,
        }),
    },
    mdas: {
        type: Array,
        default: () => [],
    },
    statusOptions: {
        type: Array,
        default: () => [],
    },
    permissions: {
        type: Object,
        default: () => ({
            can_create_schedule: false,
            has_signature: false,
            can_be_signatory: false,
        }),
    },
    userMdas: {
        type: Array,
        default: () => [],
    },
    isAdmin: {
        type: Boolean,
        default: false,
    },
    hasMdas: {
        type: Boolean,
        default: false,
    },
    filters: {
        type: Object,
        default: () => ({
            search: '',
            status: '',
            mda_id: '',
            date_from: '',
            date_to: '',
        }),
    },
});

const showConfirmationModal = ref(false);
const showVoucherTypeModal = ref(false);
const showSignatureWarningModal = ref(false);
const showLineItemsModal = ref(false);
const showCreateVoucherModal = ref(false);
const currentSchedule = ref(null);
const currentAction = ref(null);
const selectedVoucherType = ref(null);
const selectedLineItem = ref(null);
const scheduleItems = ref([]);

const helpOverlay = ref(null);
const helpButton = ref(null);

const searchQuery = ref(props.filters?.search || '');
const selectedStatus = ref(props.filters?.status || '');
const selectedMda = ref(props.filters?.mda_id || '');
const dateRange = ref(null);
const exporting = ref(false);

const voucherTypes = [
    {
        label: 'Standard Voucher',
        value: 'standard',
        description: 'Regular payment voucher for completed services/goods',
    },
    {
        label: 'Prepayment Voucher',
        value: 'prepayment',
        description: 'Advance payment before delivery of services/goods',
    },
    {
        label: 'Capital Voucher',
        value: 'capital',
        description: 'Payment voucher for capital expenditures',
    },
    {
        label: 'Recurrent Voucher',
        value: 'recurrent',
        description: 'Payment voucher for recurrent expenditures',
    },
    {
        label: 'Salary Voucher',
        value: 'salary',
        description: 'Payment voucher for employee salaries',
    },
    {
        label: 'Pension Voucher',
        value: 'pension',
        description: 'Payment voucher for pension payments',
    },
    {
        label: 'Gratuity Voucher',
        value: 'gratuity',
        description: 'Payment voucher for gratuity payments',
    },
];

const canCreateVoucher = (schedule) => {
    if (!schedule || !schedule.status) return false;
    const status = schedule.status.toLowerCase().trim();
    const voucherEligibleStatuses = [
        'submitted',
        'processed',
        'approved',
        'awaiting voucher',
        'voucher raised',
    ];
    return voucherEligibleStatuses.includes(status);
};

const canEditSchedule = (schedule) => {
    if (!schedule || !schedule.status) return false;
    const status = schedule.status.toLowerCase().trim();
    const editableStatuses = ['draft', 'saved', 'returned', 'needs attention'];
    return editableStatuses.includes(status);
};

const canDeleteSchedule = (schedule) => {
    if (!schedule || !schedule.status) return false;
    const status = schedule.status.toLowerCase().trim();
    return ['draft', 'saved'].includes(status);
};

const canCreateSchedule = computed(() => {
    return props.permissions?.can_create_schedule || false;
});

const hasSignature = computed(() => {
    return props.permissions?.has_signature || false;
});

const canBeSignatory = computed(() => {
    return props.permissions?.can_be_signatory || false;
});

const hasMdas = computed(() => {
    return props.hasMdas || false;
});

const isAdmin = computed(() => {
    return props.isAdmin || false;
});

const userMdas = computed(() => {
    return props.userMdas || [];
});

const getCreationRestrictionReason = computed(() => {
    if (!canCreateSchedule.value) {
        return '❌ You do not have permission to create schedules. Please contact your administrator.';
    }
    if (!hasSignature.value) {
        return '❌ You must upload your signature before creating a schedule. Please update your profile.';
    }
    if (!canBeSignatory.value) {
        return '❌ You must be designated as a signatory before creating a schedule. Please contact your administrator.';
    }
    return null;
});

const canProceedToCreate = computed(() => {
    return canCreateSchedule.value && hasSignature.value && canBeSignatory.value;
});

const getRequirementsList = computed(() => {
    const requirements = [];
    requirements.push({
        label: 'Schedule Creation Permission',
        met: canCreateSchedule.value,
        icon: canCreateSchedule.value ? '✅' : '❌',
        detail: canCreateSchedule.value ? 'Granted' : 'Missing - Contact Administrator'
    });
    requirements.push({
        label: 'Signature Uploaded',
        met: hasSignature.value,
        icon: hasSignature.value ? '✅' : '❌',
        detail: hasSignature.value ? 'Uploaded' : 'Missing - Upload in Profile'
    });
    requirements.push({
        label: 'Signatory Designation',
        met: canBeSignatory.value,
        icon: canBeSignatory.value ? '✅' : '❌',
        detail: canBeSignatory.value ? 'Active' : 'Inactive - Contact Administrator'
    });
    return requirements;
});

const getStatusSeverity = (status) => {
    if (!status) return 'info';
    const normalizedStatus = status.toLowerCase().trim();

    switch (normalizedStatus) {
        case 'voucher raised':
        case 'processed':
        case 'approved':
            return 'success';
        case 'rejected':
        case 'declined':
            return 'danger';
        case 'returned':
        case 'needs attention':
            return 'warning';
        case 'submitted':
        case 'awaiting voucher':
            return 'secondary';
        case 'draft':
        case 'saved':
            return 'info';
        case 'pending voucher':
            return 'warning';
        default:
            return 'info';
    }
};

const statsData = computed(() => [
    {
        title: 'Total Schedules',
        value: props.stats?.total_schedules || 0,
        icon: 'pi pi-file',
        color: 'text-blue-500',
        bgColor: 'bg-blue-50',
    },
    {
        title: 'Total Amount',
        value: formatCurrency(props.stats?.total_amount || 0),
        icon: 'pi pi-money-bill',
        color: 'text-green-500',
        bgColor: 'bg-green-50',
    },
    {
        title: 'Submitted',
        value: props.stats?.submitted_count || 0,
        icon: 'pi pi-clock',
        color: 'text-orange-500',
        bgColor: 'bg-orange-50',
    },
    {
        title: 'Processed',
        value: props.stats?.processed_count || 0,
        icon: 'pi pi-check-circle',
        color: 'text-cyan-500',
        bgColor: 'bg-cyan-50',
    },
    {
        title: 'Approved',
        value: props.stats?.approved_count || 0,
        icon: 'pi pi-check-circle',
        color: 'text-green-500',
        bgColor: 'bg-green-50',
    },
    {
        title: 'Voucher Raised',
        value: props.stats?.voucher_raised_count || 0,
        icon: 'pi pi-file-pdf',
        color: 'text-purple-500',
        bgColor: 'bg-purple-50',
    },
    {
        title: 'Draft',
        value: props.stats?.draft_count || 0,
        icon: 'pi pi-pencil',
        color: 'text-gray-500',
        bgColor: 'bg-gray-50',
    },
    {
        title: 'Rejected',
        value: props.stats?.rejected_count || 0,
        icon: 'pi pi-times-circle',
        color: 'text-red-500',
        bgColor: 'bg-red-50',
    },
]);

const openConfirmationModal = (schedule, action) => {
    if (action === 'edit' && !canEditSchedule(schedule)) {
        toast.add({
            severity: 'error',
            summary: 'Cannot Edit',
            detail: `Schedule ${schedule.schedule_number} is "${schedule.status}" and cannot be edited.`,
            life: 5000,
        });
        return;
    }

    if (action === 'delete' && !canDeleteSchedule(schedule)) {
        toast.add({
            severity: 'error',
            summary: 'Cannot Delete',
            detail: `Schedule ${schedule.schedule_number} cannot be deleted in its current status.`,
            life: 5000,
        });
        return;
    }

    currentSchedule.value = schedule;
    currentAction.value = action;
    showConfirmationModal.value = true;
};

const openLineItemsModal = async (schedule) => {
    currentSchedule.value = schedule;
    showLineItemsModal.value = true;
    
    try {
        const response = await axios.get(`/schedules/${schedule.id}/items`);
        scheduleItems.value = response.data.items;
        
        // ✅ Update the schedule status if needed based on items
        if (response.data.total_items > 0 && response.data.vouchers_created === 0) {
            // No vouchers created yet - status should show pending
            if (currentSchedule.value.status === 'Processed') {
                currentSchedule.value.status = 'Pending Voucher';
            }
        } else if (response.data.total_items > 0 && response.data.vouchers_created === response.data.total_items) {
            // All items have vouchers
            if (currentSchedule.value.status === 'Processed' || currentSchedule.value.status === 'Pending Voucher') {
                currentSchedule.value.status = 'Voucher Raised';
            }
        }
    } catch (error) {
        toast.add({
            severity: 'error',
            summary: 'Error',
            detail: 'Failed to load schedule items.',
            life: 3000,
        });
        console.error(error);
    }
};

const openCreateVoucherModal = (item) => {
    selectedLineItem.value = item;
    selectedVoucherType.value = null;
    showCreateVoucherModal.value = true;
    showLineItemsModal.value = false;
};

const createVoucherForLineItem = () => {
    if (!selectedLineItem.value || !selectedVoucherType.value) {
        toast.add({
            severity: 'error',
            summary: 'Selection Required',
            detail: 'Please select a voucher type.',
            life: 3000,
        });
        return;
    }

    // ✅ Check if voucher already exists for this line item
    if (selectedLineItem.value.has_voucher) {
        toast.add({
            severity: 'warn',
            summary: 'Voucher Already Exists',
            detail: `A voucher has already been created for this line item. You can view or edit it.`,
            life: 5000,
        });
        showCreateVoucherModal.value = false;
        showLineItemsModal.value = true;
        // Refresh the items list
        if (currentSchedule.value) {
            openLineItemsModal(currentSchedule.value);
        }
        return;
    }

    const scheduleId = currentSchedule.value.id;
    const itemId = selectedLineItem.value.id;

    router.visit(
        `/vouchers/create?schedule_id=${scheduleId}&item_id=${itemId}&type=${selectedVoucherType.value}`,
        {
            onSuccess: () => {
                showCreateVoucherModal.value = false;
                showLineItemsModal.value = true;
                toast.add({
                    severity: 'success',
                    summary: 'Voucher Creation',
                    detail: `Redirecting to create ${selectedVoucherType.value} voucher for line item...`,
                    life: 3000,
                });
                // Refresh the items list
                if (currentSchedule.value) {
                    openLineItemsModal(currentSchedule.value);
                }
            },
            onError: (errors) => {
                // Check if error is because voucher already exists
                if (errors && errors.message && errors.message.includes('already exists')) {
                    toast.add({
                        severity: 'warn',
                        summary: 'Voucher Already Exists',
                        detail: 'A voucher has already been created for this line item.',
                        life: 5000,
                    });
                    showCreateVoucherModal.value = false;
                    showLineItemsModal.value = true;
                    if (currentSchedule.value) {
                        openLineItemsModal(currentSchedule.value);
                    }
                } else {
                    toast.add({
                        severity: 'error',
                        summary: 'Error',
                        detail: 'Failed to redirect to voucher creation.',
                    });
                }
            },
        },
    );
};

const createSchedule = () => {
    if (!canProceedToCreate.value) {
        if (!hasSignature.value) {
            showSignatureWarningModal.value = true;
        } else {
            toast.add({
                severity: 'error',
                summary: 'Cannot Create Schedule',
                detail: getCreationRestrictionReason.value || 'You do not have the required permissions.',
                life: 5000,
            });
        }
        return;
    }
    router.visit('/schedules/create');
};

const viewVoucher = (schedule) => {
    if (schedule.voucher_id) {
        router.visit(`/vouchers/${schedule.voucher_id}`);
    }
};

const printSchedule = (schedule) => {
    if (schedule.id) {
        window.open(`/schedules/${schedule.id}/print`, '_blank');
    }
};

const exportToExcel = async () => {
    exporting.value = true;
    try {
        const params = new URLSearchParams({
            search: searchQuery.value,
            status: selectedStatus.value,
            mda_id: selectedMda.value,
            date_from: dateRange.value?.[0] || '',
            date_to: dateRange.value?.[1] || '',
        });
        window.open(`/schedules/export/excel?${params.toString()}`, '_blank');
        toast.add({
            severity: 'success',
            summary: 'Export Started',
            detail: 'Excel file is being downloaded.',
            life: 3000,
        });
    } catch (error) {
        toast.add({
            severity: 'error',
            summary: 'Export Failed',
            detail: 'Failed to export schedules to Excel.',
            life: 5000,
        });
    } finally {
        exporting.value = false;
    }
};

const exportToPdf = async () => {
    exporting.value = true;
    try {
        const params = new URLSearchParams({
            search: searchQuery.value,
            status: selectedStatus.value,
            mda_id: selectedMda.value,
            date_from: dateRange.value?.[0] || '',
            date_to: dateRange.value?.[1] || '',
        });
        window.open(`/schedules/export/pdf?${params.toString()}`, '_blank');
        toast.add({
            severity: 'success',
            summary: 'Export Started',
            detail: 'PDF file is being downloaded.',
            life: 3000,
        });
    } catch (error) {
        toast.add({
            severity: 'error',
            summary: 'Export Failed',
            detail: 'Failed to export schedules to PDF.',
            life: 5000,
        });
    } finally {
        exporting.value = false;
    }
};

const formatCurrency = (value) => {
    const numValue = Number(value) || 0;
    return new Intl.NumberFormat('en-NG', {
        style: 'currency',
        currency: 'NGN',
        minimumFractionDigits: 2,
    }).format(numValue);
};

const formatDate = (dateString) => {
    if (!dateString) return 'N/A';
    return new Date(dateString).toLocaleDateString('en-GB');
};

const lazyParams = ref({
    first: 0,
    rows: 15,
    page: 1,
});

const totalRecords = ref(0);
const loading = ref(false);
let debounceTimer = null;

const loadSchedules = async () => {
    loading.value = true;
    try {
        const params = {
            per_page: lazyParams.value.rows,
            page: lazyParams.value.page,
            search: searchQuery.value,
            status: selectedStatus.value,
            mda_id: selectedMda.value,
            date_from: dateRange.value?.[0] || '',
            date_to: dateRange.value?.[1] || '',
        };

        const response = await axios.get('/schedules/search', { params });
        schedules.value = response.data.schedules;
        totalRecords.value = response.data.paginator.total;
        
        if (response.data.stats) {
            props.stats = response.data.stats;
        }
    } catch (error) {
        toast.add({ severity: "error", summary: "Error", detail: "Failed to load data", life: 3000 });
        console.error(error);
    }
    loading.value = false;
};

watch([searchQuery, selectedStatus, selectedMda, dateRange], () => {
    clearTimeout(debounceTimer);
    debounceTimer = setTimeout(() => {
        lazyParams.value.page = 1;
        loadSchedules();
    }, 500);
}, { deep: true });

const onPage = (event) => {
    lazyParams.value.page = event.page + 1;
    lazyParams.value.first = event.first;
    lazyParams.value.rows = event.rows;
    loadSchedules();
};

const clearFilters = () => {
    searchQuery.value = '';
    selectedStatus.value = '';
    selectedMda.value = '';
    dateRange.value = null;
    lazyParams.value.page = 1;
    loadSchedules();
};

const refreshData = () => {
    loadSchedules();
    toast.add({
        severity: 'success',
        summary: 'Refreshed',
        detail: 'Data refreshed successfully',
        life: 2000,
    });
};

const goToProfile = () => {
    showSignatureWarningModal.value = false;
    router.visit('/profile');
};

const breadcrumbs = ref([
    { title: 'Schedules', href: '/schedules' },
    { title: 'List', href: '#' },
]);

const dt = ref(null);

onMounted(() => {
    lazyParams.value.page = 1;
    loadSchedules();
});
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head title="Schedule List" />
        <Toast />

        <div v-if="!isAdmin && hasMdas && userMdas.length > 0" class="mb-4">
            <Message severity="info" :closable="false">
                <div class="flex align-items-center gap-2 flex-wrap">
                    <i class="pi pi-building"></i>
                    <span>
                        <strong>Showing schedules for your assigned MDAs:</strong>
                        <span v-for="(mda, index) in userMdas" :key="mda.id || index" class="ml-2">
                            <Badge :value="mda.name || mda" severity="info" />
                        </span>
                    </span>
                </div>
            </Message>
        </div>

        <div v-if="!isAdmin && !hasMdas" class="mb-4">
            <Message severity="warn" :closable="false">
                <div class="flex align-items-center gap-2">
                    <i class="pi pi-exclamation-triangle"></i>
                    <span>
                        <strong>No MDAs assigned to you.</strong>
                        Please contact your administrator to get MDA access.
                    </span>
                </div>
            </Message>
        </div>

        <!-- Stats Cards -->
        <div class="mb-4 grid">
            <div v-for="stat in statsData" :key="stat.title" class="col-12 md:col-3 lg:col-3 xl:col-3">
                <Card class="h-full stat-card">
                    <template #content>
                        <div class="flex align-items-center">
                            <div :class="['border-circle flex align-items-center justify-content-center mr-3', stat.bgColor]" style="width: 3.5rem; height: 3.5rem;">
                                <i :class="[stat.icon, stat.color, 'text-xl']"></i>
                            </div>
                            <div class="flex-1">
                                <div class="text-900 text-xl font-bold">
                                    {{ stat.value }}
                                </div>
                                <div class="text-600 text-sm">
                                    {{ stat.title }}
                                </div>
                            </div>
                        </div>
                    </template>
                </Card>
            </div>
        </div>

        <Card>
            <template #title>
                <div class="justify-content-between align-items-center flex flex-wrap gap-2">
                    <div class="flex align-items-center gap-2">
                        <span>Payment Schedules</span>
                        <Badge :value="schedules.total || totalRecords" severity="info" />
                        <Badge 
                            v-if="!isAdmin && hasMdas"
                            value="Filtered by MDAs"
                            severity="info"
                            class="ml-2"
                        />
                        <Badge 
                            v-if="isAdmin"
                            value="Admin - All MDAs"
                            severity="success"
                            class="ml-2"
                        />
                    </div>

                    <div class="flex flex-wrap gap-2 align-items-center">
                        <Button 
                            label="Refresh" 
                            icon="pi pi-refresh" 
                            severity="secondary" 
                            outlined 
                            size="small"
                            @click="refreshData" 
                            :loading="loading" 
                        />
                        <Button 
                            label="Export Excel" 
                            icon="pi pi-file-excel" 
                            severity="success" 
                            outlined 
                            size="small"
                            @click="exportToExcel" 
                            :loading="exporting" 
                        />
                        <Button 
                            label="Export PDF" 
                            icon="pi pi-file-pdf" 
                            severity="danger" 
                            outlined 
                            size="small"
                            @click="exportToPdf" 
                            :loading="exporting" 
                        />
                        <Button 
                            label="Clear Filters" 
                            icon="pi pi-filter-slash" 
                            severity="secondary" 
                            outlined 
                            size="small"
                            @click="clearFilters" 
                        />
                        
                        <div class="flex align-items-center gap-1">
                            <Button 
                                label="Create Schedule" 
                                icon="pi pi-plus" 
                                severity="primary"
                                size="small"
                                @click="createSchedule"
                                :disabled="!canProceedToCreate"
                            />
                            
                            <Button
                                ref="helpButton"
                                icon="pi pi-question-circle"
                                severity="secondary"
                                text
                                rounded
                                size="small"
                                class="help-button"
                                @click="helpOverlay.toggle($event)"
                                v-tooltip="'Click to see requirements'"
                            />
                            
                            <OverlayPanel ref="helpOverlay" :showCloseIcon="true">
                                <div class="p-2" style="min-width: 280px; max-width: 350px;">
                                    <div class="flex align-items-center gap-2 mb-3">
                                        <i class="pi pi-info-circle text-primary"></i>
                                        <h4 class="m-0 text-primary">Requirements to Create Schedule</h4>
                                    </div>
                                    
                                    <div class="flex flex-column gap-2">
                                        <div 
                                            v-for="req in getRequirementsList" 
                                            :key="req.label"
                                            class="flex align-items-center justify-content-between p-2 border-round"
                                            :class="req.met ? 'bg-green-50' : 'bg-red-50'"
                                        >
                                            <div class="flex align-items-center gap-2">
                                                <span class="text-xl">{{ req.icon }}</span>
                                                <span class="font-medium">{{ req.label }}</span>
                                            </div>
                                            <Badge 
                                                :severity="req.met ? 'success' : 'danger'" 
                                                :value="req.met ? '✓' : '✗'"
                                                class="text-sm"
                                            />
                                        </div>
                                    </div>
                                    
                                    <div class="mt-3 pt-2 border-top-1 surface-border">
                                        <div class="flex align-items-center gap-2">
                                            <i class="pi pi-check-circle text-green-500"></i>
                                            <span class="text-sm text-green-500 font-medium">All requirements must be met</span>
                                        </div>
                                        <div class="flex align-items-center gap-2 mt-1">
                                            <i class="pi pi-exclamation-triangle text-yellow-500"></i>
                                            <span class="text-sm text-yellow-500">Missing any requirement prevents creation</span>
                                        </div>
                                    </div>
                                    
                                    <div class="mt-3 pt-2 border-top-1 surface-border">
                                        <Button 
                                            v-if="!hasSignature"
                                            label="Upload Signature" 
                                            icon="pi pi-upload" 
                                            severity="warning" 
                                            size="small"
                                            class="w-full"
                                            @click="goToProfile"
                                        />
                                        <Button 
                                            v-if="!canBeSignatory"
                                            label="Contact Administrator" 
                                            icon="pi pi-user" 
                                            severity="info" 
                                            size="small"
                                            class="w-full"
                                            @click="helpOverlay.hide()"
                                        />
                                    </div>
                                </div>
                            </OverlayPanel>
                        </div>
                    </div>
                </div>
            </template>

            <template #content>
                <div v-if="!canProceedToCreate" class="mb-4 p-3 surface-ground border-round border-1 border-yellow-300">
                    <div class="flex align-items-center gap-2">
                        <i class="pi pi-exclamation-triangle text-yellow-500 text-xl"></i>
                        <div class="flex flex-wrap align-items-center gap-2">
                            <span class="font-semibold">Cannot Create Schedules:</span>
                            <span class="text-500">{{ getCreationRestrictionReason }}</span>
                            <Button 
                                v-if="!hasSignature"
                                label="Upload Signature"
                                icon="pi pi-upload"
                                severity="warning"
                                size="small"
                                @click="goToProfile"
                            />
                            <Button
                                icon="pi pi-question-circle"
                                severity="secondary"
                                text
                                rounded
                                size="small"
                                @click="helpOverlay.toggle($event)"
                                v-tooltip="'View requirements'"
                            />
                        </div>
                    </div>
                </div>

                <div class="mb-4">
                    <div class="grid">
                        <div class="col-12 md:col-3">
                            <IconField>
                                <InputIcon>
                                    <i class="pi pi-search" />
                                </InputIcon>
                                <InputText 
                                    v-model="searchQuery" 
                                    placeholder="Search schedules..." 
                                    class="w-full"
                                    size="small"
                                />
                            </IconField>
                        </div>

                        <div class="col-12 md:col-2">
                            <Dropdown
                                v-model="selectedStatus"
                                :options="statusOptions"
                                optionLabel="label"
                                optionValue="value"
                                placeholder="All Status"
                                class="w-full"
                                size="small"
                                :showClear="true"
                            />
                        </div>

                        <div class="col-12 md:col-2">
                            <Dropdown
                                v-model="selectedMda"
                                :options="mdas"
                                optionLabel="name"
                                optionValue="id"
                                placeholder="All MDAs"
                                class="w-full"
                                size="small"
                                :showClear="true"
                            />
                        </div>

                        <div class="col-12 md:col-3">
                            <Calendar
                                v-model="dateRange"
                                selectionMode="range"
                                :manualInput="false"
                                placeholder="Date Range"
                                class="w-full"
                                size="small"
                                :showIcon="true"
                            />
                        </div>

                        <div class="col-12 md:col-2 flex align-items-center">
                            <Button 
                                icon="pi pi-external-link" 
                                label="Export CSV" 
                                severity="secondary"
                                size="small"
                                @click="dt?.exportCSV()" 
                            />
                        </div>
                    </div>
                    <div class="flex justify-content-between mt-2">
                        <span class="text-sm text-500">
                            <i class="pi pi-info-circle mr-1"></i>
                            {{ totalRecords || schedules.total || 0 }} record(s) found
                        </span>
                        <span v-if="props.stats?.total_amount" class="text-sm text-500">
                            <i class="pi pi-money-bill mr-1"></i>
                            Total Amount: {{ formatCurrency(props.stats.total_amount) }}
                        </span>
                    </div>
                </div>

                <DataTable 
                    ref="dt"
                    :filters="filters" 
                    :value="schedules.data" 
                    dataKey="id" 
                    stripedRows
                    responsiveLayout="scroll" 
                    class="p-datatable-sm" 
                    :emptyMessage="'No schedules found.'"
                    :paginator="true" 
                    :rowsPerPageOptions="[5, 10, 15, 25, 50, 100]" 
                    :loading="loading"
                    :rows="lazyParams.rows" 
                    :totalRecords="totalRecords || schedules.total" 
                    @page="onPage" 
                    removableSort
                    :globalFilterFields="['schedule_number', 'schedule_date']" 
                    lazy 
                    size="small"
                    paginatorTemplate="RowsPerPageDropdown FirstPageLink PrevPageLink CurrentPageReport NextPageLink LastPageLink"
                    currentPageReportTemplate="{first} to {last} of {totalRecords}"
                    exportFilename="schedules"
                    showGridlines
                >
                    <Column field="schedule_number" header="Schedule #" headerStyle="width: 10%" :sortable="true">
                        <template #body="slotProps">
                            <Link :href="'/schedules/' + slotProps.data.id"
                                class="text-primary-600 font-medium hover:underline">
                                {{ slotProps.data.schedule_number }}
                            </Link>
                        </template>
                    </Column>

                    <Column field="schedule_date" header="Date" headerStyle="width: 8%" :sortable="true">
                        <template #body="slotProps">
                            {{ formatDate(slotProps.data.schedule_date) }}
                        </template>
                    </Column>

                    <Column field="mda.name" header="MDA" headerStyle="width: 12%">
                        <template #body="slotProps">
                            <span v-if="slotProps.data.mda">
                                {{ slotProps.data.mda.name }}
                            </span>
                            <span v-else class="text-500">N/A</span>
                        </template>
                    </Column>

                    <Column field="budget_code" header="Admin Code" headerStyle="width: 10%">
                        <template #body="slotProps">
                            <span class="font-mono text-sm">
                                {{ slotProps.data.budget_code || 'N/A' }}
                            </span>
                        </template>
                    </Column>

                    <Column header="Payee / Description" headerStyle="width: 15%">
                        <template #body="slotProps">
                            <div class="flex-column flex">
                                <span class="font-medium">
                                    {{ slotProps.data.payee_name || 'Multiple Payees' }}
                                </span>
                                <small class="text-500" v-if="slotProps.data.items_count">
                                    {{ slotProps.data.items_count }} line items
                                </small>
                            </div>
                        </template>
                    </Column>

                    <Column field="total_amount" header="Total Amount" headerStyle="width: 12%"
                        bodyClass="font-bold text-left" :sortable="true">
                        <template #body="slotProps">
                            {{ formatCurrency(slotProps.data.total_amount || 0) }}
                            <br />
                            <small class="text-500 text-blue-500">
                                Raised: {{ formatCurrency(slotProps.data.amount_posted) }}
                            </small><br />
                            <small class="text-500 text-green-700"
                                v-if="(slotProps.data.total_amount - slotProps.data.amount_posted) > 0">
                                Oust: {{ formatCurrency(slotProps.data.total_amount - slotProps.data.amount_posted) }}
                            </small>
                            <small class="text-500"
                                v-if="(slotProps.data.total_amount - slotProps.data.amount_posted) == 0">
                                Oust: {{ formatCurrency(slotProps.data.total_amount - slotProps.data.amount_posted) }}
                            </small>
                            <small class="text-500 text-red-600"
                                v-if="(slotProps.data.total_amount - slotProps.data.amount_posted) < 0">
                                Oust: {{ formatCurrency(slotProps.data.total_amount - slotProps.data.amount_posted) }}
                            </small>
                        </template>
                    </Column>

                    <Column field="status" header="Status" headerStyle="width: 8%">
                        <template #body="slotProps">
                            <Tag :value="slotProps.data.status" :severity="getStatusSeverity(slotProps.data.status)" />
                        </template>
                    </Column>

                    <!-- ✅ Vouchers Column with Progress Tracking -->
                    <Column header="Vouchers" headerStyle="width: 14%" bodyClass="text-center">
                        <template #body="slotProps">
                            <div class="flex flex-column align-items-center gap-1">
                                <div 
                                    class="voucher-status-badge cursor-pointer p-2 border-round flex align-items-center gap-2 hover:shadow-2 transition-all"
                                    :class="slotProps.data.all_items_processed ? 'bg-green-50 border-1 border-green-300' : 'bg-orange-50 border-1 border-orange-300'"
                                    @click="openLineItemsModal(slotProps.data)"
                                    v-tooltip="'Click to view line items and create vouchers'"
                                >
                                    <div class="flex align-items-center gap-2">
                                        <!-- For one-to-one: show simple status -->
                                        <div v-if="slotProps.data.items_count === 1" class="flex align-items-center gap-1">
                                            <i :class="slotProps.data.has_direct_voucher ? 'pi pi-check-circle text-green-500' : 'pi pi-clock text-orange-500'" class="text-sm"></i>
                                            <span :class="slotProps.data.has_direct_voucher ? 'text-green-600' : 'text-orange-600'" class="font-bold">
                                                {{ slotProps.data.has_direct_voucher ? 'Voucher' : 'Pending' }}
                                            </span>
                                        </div>
                                        
                                        <!-- For multiple items: show progress -->
                                        <div v-else class="flex align-items-center gap-2">
                                            <div class="flex align-items-center gap-1">
                                                <i class="pi pi-check-circle text-green-500 text-sm"></i>
                                                <span class="font-bold text-green-600">{{ slotProps.data.processed_items || 0 }}</span>
                                            </div>
                                            <span class="text-500">/</span>
                                            <div class="flex align-items-center gap-1">
                                                <i class="pi pi-clock text-orange-500 text-sm"></i>
                                                <span class="font-bold text-orange-600">{{ slotProps.data.unprocessed_items || 0 }}</span>
                                            </div>
                                            <span class="text-500">|</span>
                                            <div class="flex align-items-center gap-1">
                                                <i class="pi pi-file text-blue-500 text-sm"></i>
                                                <span class="font-bold text-blue-600">{{ slotProps.data.items_count || 0 }}</span>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div v-if="slotProps.data.items_count > 1" class="progress-indicator" style="width: 50px; height: 4px; background: #e5e7eb; border-radius: 2px; overflow: hidden;">
                                        <div 
                                            class="progress-bar"
                                            :style="{
                                                width: (slotProps.data.items_count > 0 ? (slotProps.data.processed_items / slotProps.data.items_count * 100) : 0) + '%',
                                                background: slotProps.data.all_items_processed ? '#22c55e' : '#f97316',
                                                height: '100%',
                                                transition: 'width 0.3s ease'
                                            }"
                                        ></div>
                                    </div>
                                </div>
                                <small class="text-xs text-500">
                                    <span v-if="slotProps.data.items_count === 1">
                                        {{ slotProps.data.has_direct_voucher ? 'Voucher created' : 'No voucher yet' }}
                                    </span>
                                    <span v-else>
                                        {{ slotProps.data.processed_items || 0 }} of {{ slotProps.data.items_count || 0 }} processed
                                        <span v-if="!slotProps.data.all_items_processed" class="text-orange-500 font-bold ml-1">
                                            ({{ slotProps.data.unprocessed_items || 0 }} pending)
                                        </span>
                                        <span v-else class="text-green-500 font-bold ml-1">✓ Complete</span>
                                    </span>
                                </small>
                            </div>
                        </template>
                    </Column>

                    <Column header="Actions" headerStyle="width: 12%" bodyClass="text-center">
                        <template #body="slotProps">
                            <div class="justify-content-center flex gap-2">
                                <Button 
                                    icon="pi pi-print" 
                                    text 
                                    rounded 
                                    severity="info" 
                                    v-tooltip="'Print Schedule'"
                                    @click="printSchedule(slotProps.data)" 
                                    class="p-button-sm" 
                                />

                                <Button 
                                    icon="pi pi-pencil" 
                                    text 
                                    rounded 
                                    severity="secondary"
                                    :disabled="!canEditSchedule(slotProps.data)" 
                                    v-tooltip="canEditSchedule(slotProps.data) ? 'Edit Schedule' : 'Cannot edit'"
                                    @click="openConfirmationModal(slotProps.data, 'edit')" 
                                    class="p-button-sm" 
                                />

                                <Button 
                                    icon="pi pi-trash" 
                                    text 
                                    rounded 
                                    severity="danger" 
                                    :disabled="!canDeleteSchedule(slotProps.data)"
                                    v-tooltip="canDeleteSchedule(slotProps.data) ? 'Delete Schedule' : 'Cannot delete'"
                                    @click="openConfirmationModal(slotProps.data, 'delete')" 
                                    class="p-button-sm" 
                                />
                            </div>
                        </template>
                    </Column>
                </DataTable>
            </template>
        </Card>

        <!-- ✅ Line Items Modal with Voucher Workflow Status -->
        <Dialog 
            v-model:visible="showLineItemsModal" 
            :style="{ width: '90vw', maxWidth: '1200px' }" 
            :header="`Line Items - ${currentSchedule?.schedule_number}`"
            :modal="true"
            :closable="true"
            class="line-items-dialog"
        >
            <div class="flex flex-column gap-4">
                <!-- Schedule Summary -->
                <div class="grid p-3 surface-ground border-round">
                    <div class="col-12 md:col-3">
                        <div class="text-sm text-500">Schedule Number</div>
                        <div class="font-bold">{{ currentSchedule?.schedule_number }}</div>
                    </div>
                    <div class="col-12 md:col-3">
                        <div class="text-sm text-500">Total Amount</div>
                        <div class="font-bold">{{ formatCurrency(currentSchedule?.total_amount) }}</div>
                    </div>
                    <div class="col-12 md:col-3">
                        <div class="text-sm text-500">Status</div>
                        <Tag :value="currentSchedule?.status" :severity="getStatusSeverity(currentSchedule?.status)" />
                    </div>
                    <div class="col-12 md:col-3">
                        <div class="text-sm text-500">Progress</div>
                        <div class="flex align-items-center gap-2">
                            <span class="font-bold">
                                {{ scheduleItems.filter(item => item.has_voucher).length }} / {{ scheduleItems.length }}
                            </span>
                            <Badge 
                                :severity="scheduleItems.length > 0 && scheduleItems.filter(item => item.has_voucher).length === scheduleItems.length ? 'success' : 'warning'"
                                :value="scheduleItems.length > 0 && scheduleItems.filter(item => item.has_voucher).length === scheduleItems.length ? 'Complete' : 'In Progress'"
                            />
                        </div>
                    </div>
                </div>

                <!-- ✅ Progress Summary Cards with Voucher Status Breakdown -->
                <div class="grid">
                    <div class="col-12 md:col-3">
                        <Card class="p-2">
                            <template #content>
                                <div class="flex align-items-center gap-2">
                                    <i class="pi pi-check-circle text-green-500 text-xl"></i>
                                    <div>
                                        <div class="text-sm text-500">Processed</div>
                                        <div class="text-xl font-bold text-green-600">
                                            {{ scheduleItems.filter(item => item.has_voucher).length }}
                                        </div>
                                    </div>
                                </div>
                            </template>
                        </Card>
                    </div>
                    <div class="col-12 md:col-3">
                        <Card class="p-2">
                            <template #content>
                                <div class="flex align-items-center gap-2">
                                    <i class="pi pi-clock text-orange-500 text-xl"></i>
                                    <div>
                                        <div class="text-sm text-500">Pending</div>
                                        <div class="text-xl font-bold text-orange-600">
                                            {{ scheduleItems.filter(item => !item.has_voucher).length }}
                                        </div>
                                    </div>
                                </div>
                            </template>
                        </Card>
                    </div>
                    <div class="col-12 md:col-3">
                        <Card class="p-2">
                            <template #content>
                                <div class="flex align-items-center gap-2">
                                    <i class="pi pi-send text-blue-500 text-xl"></i>
                                    <div>
                                        <div class="text-sm text-500">In Review</div>
                                        <div class="text-xl font-bold text-blue-600">
                                            {{ scheduleItems.filter(item => item.has_voucher && (item.voucher_status === 'submitted' || item.voucher_status === 'pending')).length }}
                                        </div>
                                    </div>
                                </div>
                            </template>
                        </Card>
                    </div>
                    <div class="col-12 md:col-3">
                        <Card class="p-2">
                            <template #content>
                                <div class="flex align-items-center gap-2">
                                    <i class="pi pi-check-circle text-emerald-500 text-xl"></i>
                                    <div>
                                        <div class="text-sm text-500">Approved</div>
                                        <div class="text-xl font-bold text-emerald-600">
                                            {{ scheduleItems.filter(item => item.has_voucher && (item.voucher_status === 'approved' || item.voucher_status === 'audit_approved' || item.voucher_status === 'fa_approved' || item.voucher_status === 'ec_approved' || item.voucher_status === 'ag_approved' || item.voucher_status === 'mas_approved' || item.voucher_status === 'closed')).length }}
                                        </div>
                                    </div>
                                </div>
                            </template>
                        </Card>
                    </div>
                </div>

                <!-- ✅ Line Items Table with Voucher Status -->
                <DataTable 
                    :value="scheduleItems" 
                    dataKey="id" 
                    stripedRows
                    responsiveLayout="scroll" 
                    class="p-datatable-sm" 
                    :emptyMessage="'No line items found.'"
                    showGridlines
                >
                    <Column field="serial_number" header="S/N" headerStyle="width: 5%">
                        <template #body="slotProps">
                            {{ slotProps.index + 1 }}
                        </template>
                    </Column>

                    <Column field="serial_number" header="Serial No." headerStyle="width: 8%">
                        <template #body="slotProps">
                            {{ slotProps.data.serial_number || 'N/A' }}
                        </template>
                    </Column>

                    <Column field="payee_name" header="Payee" headerStyle="width: 15%">
                        <template #body="slotProps">
                            {{ slotProps.data.payee_name }}
                        </template>
                    </Column>

                    <Column field="economy_code" header="Economy Code" headerStyle="width: 10%">
                        <template #body="slotProps">
                            <div>
                                <div class="font-medium">{{ slotProps.data.economy_code }}</div>
                                <div class="text-xs text-500">{{ slotProps.data.economy_code_name }}</div>
                            </div>
                        </template>
                    </Column>

                    <Column field="economy_code_item" header="Code Item" headerStyle="width: 10%">
                        <template #body="slotProps">
                            <div>
                                <div class="font-medium">{{ slotProps.data.economy_code_item }}</div>
                                <div class="text-xs text-500">{{ slotProps.data.economy_code_item_name }}</div>
                            </div>
                        </template>
                    </Column>

                    <Column field="amount" header="Amount" headerStyle="width: 10%" bodyClass="text-right font-bold">
                        <template #body="slotProps">
                            {{ formatCurrency(slotProps.data.amount) }}
                        </template>
                    </Column>

                    <!-- ✅ Status Column with Workflow Stage -->
                    <Column header="Voucher Status" headerStyle="width: 18%" bodyClass="text-center">
                        <template #body="slotProps">
                            <div v-if="slotProps.data.has_voucher">
                                <div class="flex flex-column align-items-center gap-1">
                                    <Tag 
                                        :severity="slotProps.data.voucher_status_severity || 'info'" 
                                        :value="slotProps.data.voucher_status_display || slotProps.data.voucher_status || 'Unknown'"
                                        icon="pi pi-check"
                                    />
                                    <small class="text-xs text-500">
                                        <i class="pi pi-arrow-right mr-1"></i>
                                        {{ slotProps.data.voucher_workflow_stage || 'Draft' }}
                                    </small>
                                    <small class="text-xs text-500">
                                        <i class="pi pi-user mr-1"></i>
                                        {{ slotProps.data.voucher_created_by || 'Unknown' }}
                                    </small>
                                    <small class="text-xs text-orange-500" v-if="slotProps.data.is_voucher_submitted">
                                        <i class="pi pi-clock mr-1"></i>
                                        Awaiting Approval
                                    </small>
                                    <small class="text-xs text-green-500" v-if="slotProps.data.is_voucher_approved">
                                        <i class="pi pi-check-circle mr-1"></i>
                                        Approved
                                    </small>
                                    <small class="text-xs text-red-500" v-if="slotProps.data.is_voucher_rejected">
                                        <i class="pi pi-times-circle mr-1"></i>
                                        Rejected
                                    </small>
                                </div>
                            </div>
                            <div v-else>
                                <Tag severity="warning" value="No Voucher" icon="pi pi-clock" />
                                <small class="text-xs text-500 block mt-1">Click + to create</small>
                            </div>
                        </template>
                    </Column>

                    <!-- ✅ Action Column -->
                    <Column header="Action" headerStyle="width: 12%" bodyClass="text-center">
                        <template #body="slotProps">
                            <div v-if="slotProps.data.has_voucher">
                                <div class="flex flex-wrap justify-content-center gap-1">
                                    <!-- View Voucher Button -->
                                    <Button 
                                        icon="pi pi-eye" 
                                        text 
                                        rounded 
                                        severity="info" 
                                        v-tooltip="'View Voucher'"
                                        @click="router.visit(`/vouchers/${slotProps.data.voucher_id}`)" 
                                        class="p-button-sm" 
                                    />
                                    <!-- Edit Voucher Button (only if not submitted/approved/rejected) -->
                                    <Button 
                                        v-if="slotProps.data.can_edit_voucher"
                                        icon="pi pi-pencil" 
                                        text 
                                        rounded 
                                        severity="secondary" 
                                        v-tooltip="'Edit Voucher'"
                                        @click="router.visit(`/vouchers/${slotProps.data.voucher_id}/edit`)" 
                                        class="p-button-sm" 
                                    />
                                    <!-- Show status badge instead of buttons if submitted -->
                                    <Badge 
                                        v-if="slotProps.data.is_voucher_submitted"
                                        value="In Review"
                                        severity="warning"
                                        size="small"
                                        class="block mt-1"
                                    />
                                    <Badge 
                                        v-if="slotProps.data.is_voucher_approved"
                                        value="Approved"
                                        severity="success"
                                        size="small"
                                        class="block mt-1"
                                    />
                                    <Badge 
                                        v-if="slotProps.data.is_voucher_rejected"
                                        value="Rejected"
                                        severity="danger"
                                        size="small"
                                        class="block mt-1"
                                    />
                                </div>
                            </div>
                            <div v-else>
                                <Button 
                                    icon="pi pi-plus" 
                                    text 
                                    rounded 
                                    severity="primary" 
                                    v-tooltip="'Create Voucher for this line item'"
                                    @click="openCreateVoucherModal(slotProps.data)" 
                                    class="p-button-sm" 
                                />
                            </div>
                        </template>
                    </Column>
                </DataTable>

                <!-- ✅ Summary Footer with Workflow Status Breakdown -->
                <div class="flex justify-content-between p-3 surface-ground border-round flex-wrap gap-2">
                    <div>
                        <span class="font-medium">Total Items: </span>
                        <span class="font-bold">{{ scheduleItems.length }}</span>
                    </div>
                    <div>
                        <span class="font-medium">Total Amount: </span>
                        <span class="font-bold">{{ formatCurrency(currentSchedule?.total_amount) }}</span>
                    </div>
                    <div>
                        <span class="font-medium text-green-500">✓ Processed: </span>
                        <span class="font-bold text-green-500">
                            {{ scheduleItems.filter(item => item.has_voucher).length }}
                        </span>
                    </div>
                    <div>
                        <span class="font-medium text-orange-500">⏳ Pending: </span>
                        <span class="font-bold text-orange-500">
                            {{ scheduleItems.filter(item => !item.has_voucher).length }}
                        </span>
                    </div>
                    <div>
                        <span class="font-medium text-blue-500">📤 In Review: </span>
                        <span class="font-bold text-blue-500">
                            {{ scheduleItems.filter(item => item.has_voucher && (item.voucher_status === 'submitted' || item.voucher_status === 'pending')).length }}
                        </span>
                    </div>
                    <div>
                        <span class="font-medium text-emerald-500">✅ Approved: </span>
                        <span class="font-bold text-emerald-500">
                            {{ scheduleItems.filter(item => item.has_voucher && (item.voucher_status === 'approved' || item.voucher_status === 'audit_approved' || item.voucher_status === 'fa_approved' || item.voucher_status === 'ec_approved' || item.voucher_status === 'ag_approved' || item.voucher_status === 'mas_approved' || item.voucher_status === 'closed')).length }}
                        </span>
                    </div>
                    <div>
                        <span class="font-medium">Progress: </span>
                        <span class="font-bold">
                            {{ scheduleItems.length > 0 ? Math.round(scheduleItems.filter(item => item.has_voucher).length / scheduleItems.length * 100) : 0 }}%
                        </span>
                    </div>
                </div>
            </div>

            <template #footer>
                <Button label="Close" icon="pi pi-times" @click="showLineItemsModal = false" text />
            </template>
        </Dialog>

        <!-- ✅ Create Voucher for Line Item Modal -->
        <Dialog 
            v-model:visible="showCreateVoucherModal" 
            :style="{ width: '550px' }" 
            header="Create Voucher for Line Item"
            :modal="true"
        >
            <div class="flex flex-column gap-4">
                <div class="p-3 surface-ground border-round">
                    <div class="grid">
                        <div class="col-12">
                            <div class="text-sm text-500">Payee</div>
                            <div class="font-bold">{{ selectedLineItem?.payee_name }}</div>
                        </div>
                        <div class="col-6">
                            <div class="text-sm text-500">Economy Code</div>
                            <div class="font-medium">{{ selectedLineItem?.economy_code }}</div>
                        </div>
                        <div class="col-6">
                            <div class="text-sm text-500">Amount</div>
                            <div class="font-bold">{{ formatCurrency(selectedLineItem?.amount) }}</div>
                        </div>
                        <div class="col-12">
                            <div class="text-sm text-500">Serial Number</div>
                            <div class="font-medium">{{ selectedLineItem?.serial_number || 'N/A' }}</div>
                        </div>
                    </div>
                </div>

                <div class="flex flex-column gap-2">
                    <label class="font-medium">Select Voucher Type</label>
                    <div class="voucher-type-options">
                        <div v-for="type in voucherTypes" :key="type.value"
                            class="voucher-type-option border-round mb-2 cursor-pointer border-1 p-3" :class="{
                                'border-primary bg-blue-50': selectedVoucherType === type.value,
                                'border-200': selectedVoucherType !== type.value,
                            }" @click="selectedVoucherType = type.value">
                            <div class="align-items-center flex gap-3">
                                <i class="pi" :class="{
                                    'pi-check-circle text-primary': selectedVoucherType === type.value,
                                    'pi-circle text-400': selectedVoucherType !== type.value,
                                }"></i>
                                <div class="flex-column flex">
                                    <span class="font-semibold">{{ type.label }}</span>
                                    <span class="text-600 text-sm">{{ type.description }}</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <template #footer>
                <Button label="Cancel" icon="pi pi-times" @click="showCreateVoucherModal = false; showLineItemsModal = true" text />
                <Button 
                    label="Create Voucher" 
                    icon="pi pi-check" 
                    severity="primary" 
                    :disabled="!selectedVoucherType"
                    @click="createVoucherForLineItem" 
                    autofocus 
                />
            </template>
        </Dialog>

        <!-- Confirmation Modal -->
        <Dialog v-model:visible="showConfirmationModal" :style="{ width: '400px' }" header="Confirm Action"
            :modal="true">
            <div class="align-items-center flex">
                <i :class="currentAction === 'delete'
                    ? 'pi pi-exclamation-triangle mr-3 text-red-500'
                    : 'pi pi-question-circle mr-3 text-blue-500'
                    " style="font-size: 2rem"></i>

                <span v-if="currentSchedule && currentAction === 'delete'">
                    Are you sure you want to <strong>delete</strong> Schedule
                    <strong>{{ currentSchedule.schedule_number }}</strong>?
                </span>
                <span v-else-if="currentSchedule && currentAction === 'edit'">
                    Proceed to edit Schedule
                    <strong>{{ currentSchedule.schedule_number }}</strong>?
                </span>
            </div>

            <template #footer>
                <Button label="No" icon="pi pi-times" @click="showConfirmationModal = false" text />
                <Button :label="currentAction === 'delete' ? 'Yes, Delete' : 'Yes, Proceed'"
                    :icon="currentAction === 'delete' ? 'pi pi-trash' : 'pi pi-check'"
                    :severity="currentAction === 'delete' ? 'danger' : 'primary'"
                    @click="confirmAction" autofocus />
            </template>
        </Dialog>

        <!-- Signature Warning Modal -->
        <Dialog v-model:visible="showSignatureWarningModal" :style="{ width: '450px' }" 
            header="Missing Requirements" :modal="true" :closable="true">
            <div class="flex-column flex gap-3 p-2">
                <div class="flex align-items-center gap-3">
                    <i class="pi pi-exclamation-triangle text-yellow-500 text-3xl"></i>
                    <div>
                        <h4 class="m-0">You cannot create schedules yet</h4>
                        <p class="text-500 text-sm m-0">Please complete the following requirements:</p>
                    </div>
                </div>

                <div class="flex flex-column gap-2">
                    <div v-for="req in getRequirementsList" :key="req.label"
                        class="flex align-items-center justify-content-between p-2 border-round"
                        :class="req.met ? 'bg-green-50' : 'bg-red-50'"
                    >
                        <div class="flex align-items-center gap-2">
                            <span class="text-xl">{{ req.icon }}</span>
                            <span class="font-medium">{{ req.label }}</span>
                        </div>
                        <Badge 
                            :severity="req.met ? 'success' : 'danger'" 
                            :value="req.met ? '✓' : '✗'"
                            class="text-sm"
                        />
                    </div>
                </div>
            </div>

            <template #footer>
                <Button label="Close" icon="pi pi-times" @click="showSignatureWarningModal = false" text />
                <Button 
                    v-if="!hasSignature"
                    label="Upload Signature" 
                    icon="pi pi-upload" 
                    severity="warning"
                    @click="goToProfile" 
                    autofocus 
                />
                <Button 
                    v-if="!canBeSignatory"
                    label="Contact Administrator" 
                    icon="pi pi-user" 
                    severity="info"
                    @click="showSignatureWarningModal = false" 
                />
            </template>
        </Dialog>
    </AppLayout>
</template>

<style scoped>
.stat-card :deep(.p-card) {
    border-radius: 1rem;
    transition: transform 0.2s, box-shadow 0.2s;
}

.stat-card :deep(.p-card):hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
}

.voucher-type-option {
    transition: all 0.2s ease;
}

.voucher-type-option:hover {
    background-color: #f8f9fa;
    border-color: #6c757d !important;
}

.voucher-type-option.selected {
    border-color: #3b82f6 !important;
    background-color: #eff6ff;
}

:deep(.voucher-button) {
    position: relative;
    z-index: 10;
    pointer-events: auto !important;
}

:deep(.p-button) {
    pointer-events: auto !important;
}

:deep(.p-datatable) {
    position: relative;
    z-index: 1;
}

.cursor-pointer {
    cursor: pointer;
}

.cursor-pointer:hover {
    opacity: 0.8;
}

.help-button {
    width: 32px !important;
    height: 32px !important;
    padding: 0 !important;
    border-radius: 50% !important;
    border: 1px solid var(--surface-border) !important;
    background: var(--surface-ground) !important;
    transition: all 0.2s ease;
}

.help-button:hover {
    background: var(--primary-50) !important;
    border-color: var(--primary-color) !important;
    transform: scale(1.05);
}

.help-button :deep(.pi) {
    font-size: 16px !important;
    color: var(--text-color-secondary);
}

.help-button:hover :deep(.pi) {
    color: var(--primary-color);
}

:deep(.p-overlaypanel) {
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.15);
    border-radius: 8px;
    border: 1px solid var(--surface-border);
}

:deep(.p-overlaypanel .p-overlaypanel-content) {
    padding: 0;
}

.line-items-dialog :deep(.p-dialog-content) {
    padding: 1.5rem;
}

.line-items-dialog :deep(.p-dialog-header) {
    background: var(--surface-ground);
    border-bottom: 1px solid var(--surface-border);
}

.voucher-status-badge {
    transition: all 0.2s ease;
    min-width: 120px;
}

.voucher-status-badge:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1) !important;
}

.voucher-status-badge.bg-green-50 {
    background-color: #f0fdf4;
}

.voucher-status-badge.bg-orange-50 {
    background-color: #fff7ed;
}

.voucher-status-badge .border-1 {
    border-width: 1px;
    border-style: solid;
}

.voucher-status-badge .border-green-300 {
    border-color: #86efac;
}

.voucher-status-badge .border-orange-300 {
    border-color: #fdba74;
}

.progress-indicator {
    background: #e5e7eb;
    border-radius: 4px;
    overflow: hidden;
}

.progress-bar {
    border-radius: 4px;
    transition: width 0.6s ease-in-out;
}

.text-green-500 { color: #22c55e; }
.text-green-600 { color: #16a34a; }
.text-orange-500 { color: #f97316; }
.text-orange-600 { color: #ea580c; }
.text-blue-500 { color: #3b82f6; }
.text-blue-600 { color: #2563eb; }
.text-emerald-500 { color: #10b981; }
.text-emerald-600 { color: #059669; }

.hover\:shadow-2:hover {
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
}

.bg-green-50 {
    background-color: #f0fdf4;
}

.bg-red-50 {
    background-color: #fef2f2;
}

.border-top-1 {
    border-top-width: 1px;
}

.border-top-1.surface-border {
    border-top-color: var(--surface-border);
}

.hover\:opacity-80:hover {
    opacity: 0.8;
}

.text-xs {
    font-size: 0.75rem;
}

.transition-all {
    transition: all 0.2s ease;
}

@media (max-width: 768px) {
    .voucher-status-badge {
        min-width: 80px;
        font-size: 0.75rem;
    }
    
    .voucher-status-badge .progress-indicator {
        width: 30px !important;
    }
}
</style>