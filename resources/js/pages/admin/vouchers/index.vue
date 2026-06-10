<script setup>
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import Button from 'primevue/button';
import Card from 'primevue/card';
import Column from 'primevue/column';
import DataTable from 'primevue/datatable';
import Dialog from 'primevue/dialog';
import Menu from 'primevue/menu';
import Paginator from 'primevue/paginator';
import Tag from 'primevue/tag';
import Toast from 'primevue/toast';
import { useToast } from 'primevue/usetoast';
import { computed, onMounted, ref, watch } from 'vue';
import { FilterMatchMode } from '@primevue/core/api';
import InputText from 'primevue/inputtext';
import InputIcon from 'primevue/inputicon';
import IconField from 'primevue/iconfield';
import axios from 'axios';


const toast = useToast();

const searchQuery = ref(""); // Search input

const filters = ref({
    global: { value: null, matchMode: FilterMatchMode.CONTAINS },

    voucher_number: { value: null, matchMode: FilterMatchMode.CONTAINS },

    voucher_type: { value: null, matchMode: FilterMatchMode.CONTAINS },

    voucher_date: { value: null, matchMode: FilterMatchMode.CONTAINS },

    // mda.name: { value: null, matchMode: FilterMatchMode.CONTAINS },

    narration: { value: null, matchMode: FilterMatchMode.CONTAINS },
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


const onPage = (event) => {
    lazyParams.value.page = event.page + 1; // Laravel pagination starts at 1
    lazyParams.value.first = event.first;
    lazyParams.value.rows = event.rows;
    loadVouchers();
};


// ðŸ’¡ State for Modal
const showConfirmationModal = ref(false);
const currentVoucher = ref(null);
const currentAction = ref(null);

// ðŸ’¡ PROPS: Receive real data from Laravel controller
const props = defineProps({
    // vouchers: {
    //     type: Object,
    //     required: true,
    //     default: () => ({

    //         total: 0,
    //         current_page: 1,
    //         per_page: 15,
    //         links: [],
    //     }),
    // },
    // paginator: {
    //     type: Object,
    //     required: true,
    //     default: () => ({
    //         total: 0,
    //         per_page: 15,
    //         current_page: 1,
    //         last_page: 1,
    //         first_page_url: "",
    //         last_page_url: "",
    //         next_page_url: "",
    //         prev_page_url: "",
    //         path: "",
    //         from: "",
    //         to: "",
    //     }),
    // }
});

// Use the real vouchers data from props but maintain your structure
// const vouchers = computed(() => props.vouchers);

const vouchers = ref([]);

// Debug function to see actual status values
const debugVoucherStatuses = () => {
    console.log('=== VOUCHER STATUS DEBUG ===');
    const statusCount = {};

    vouchers.value.data.forEach((voucher) => {
        const originalStatus = voucher.status;
        const normalizedStatus = originalStatus?.toLowerCase().trim();
        statusCount[normalizedStatus] =
            (statusCount[normalizedStatus] || 0) + 1;

        console.log(`Voucher ${voucher.voucher_number}:`, {
            id: voucher.id,
            originalStatus: originalStatus,
            normalized: normalizedStatus,
            canEdit: canEditVoucher(voucher),
            canDelete: canDeleteVoucher(voucher),
        });
    });

    console.log('Status Summary:', statusCount);
};

// Fixed voucher permission functions for first-letter caps database values
const canEditVoucher = (voucher) => {
    if (!voucher || !voucher.status) return false;

    const status = voucher.status.toLowerCase().trim();

    // Allow editing for these statuses - matches your actual DB values (first letter caps)
    const editableStatuses = [
        'draft',
        'saved',
        'sent back',
        'returned',
        'declined',
        'rejected',
        'audit_rejected',
    ];

    const canEdit = editableStatuses.includes(status);

    // console.log(`Edit check for ${voucher.voucher_number}:`, {
    //     originalStatus: voucher.status,
    //     normalized: status,
    //     canEdit: canEdit,
    // });

    return canEdit;
};

const canDeleteVoucher = (voucher) => {

    if (usePage().props.auth.userRoles.includes('Admin') || usePage().props.auth.userRoles.includes('admin')) {
        return true;
    }
    return false;
    if (!voucher || !voucher.status) return false;

    const status = voucher.status.toLowerCase().trim();

    // Allow deletion only for drafts and saved vouchers
    const deletableStatuses = ['draft', 'saved'];

    const canDelete = deletableStatuses.includes(status);

    // console.log(`Delete check for ${voucher.voucher_number}:`, {
    //     originalStatus: voucher.status,
    //     normalized: status,
    //     canDelete: canDelete,
    // });

    return canDelete;
};

// Status severity mapping for first-letter caps database values
const getStatusSeverity = (status) => {
    if (!status) return 'info';

    const normalizedStatus = status.toLowerCase().trim();

    switch (normalizedStatus) {
        // âœ… Approved/Completed States
        case 'approved':
        case 'paid':
        case 'closed':
            return 'success';

        // âœ… Rejected/Declined States
        case 'declined':
        case 'rejected':
        case 'decline and close':
            return 'danger';

        // âœ… Returned/Needs Attention States
        case 'sent back':
        case 'returned':
        case 'cancelled':
            return 'warning';

        // âœ… In Progress/Pending States
        case 'submitted':
        case 'pending':
        case 'forwarded':
            return 'secondary';

        // âœ… Draft/Saved States
        case 'draft':
        case 'saved':
            return 'info';

        // âš ï¸ Default fallback
        default:
            return 'info';
    }
};

// --- MODAL HANDLING FUNCTIONS ---
const openConfirmationModal = (voucher, action) => {
    // --- ADD VALIDATION ---
    if (action === 'edit' && !canEditVoucher(voucher)) {
        toast.add({
            severity: 'error',
            summary: 'Cannot Edit',
            detail: `Voucher ${voucher.voucher_number} is "${voucher.status}" and cannot be edited. Only Draft, Saved, Sent Back, Returned, or Declined vouchers can be edited.`,
            life: 5000,
        });
        return;
    }

    if (action === 'delete' && !canDeleteVoucher(voucher)) {
        toast.add({
            severity: 'error',
            summary: 'Cannot Delete',
            detail: `Voucher ${voucher.voucher_number} is "${voucher.status}" and cannot be deleted. Only Draft or Saved vouchers can be deleted.`,
            life: 5000,
        });
        return;
    }

    currentVoucher.value = voucher;
    currentAction.value = action;
    showConfirmationModal.value = true;
};

const confirmAction = () => {
    showConfirmationModal.value = false;

    if (!currentVoucher.value) return;

    const id = currentVoucher.value.id;

    if (currentAction.value === 'delete') {
        router.delete(route('vouchers.destroy', id), {
            preserveScroll: true,
            onSuccess: () => {
                toast.add({
                    severity: 'success',
                    summary: 'Deleted',
                    detail: `Voucher ${currentVoucher.value.voucher_number} successfully deleted.`,
                    life: 3000,
                });
            },
            onError: (errors) => {
                const detail =
                    errors.message || 'Failed to delete the voucher.';
                toast.add({
                    severity: 'error',
                    summary: 'Error',
                    detail: detail,
                    life: 5000,
                });
            },
        });
    } else if (currentAction.value === 'edit') {
        router.visit(`/vouchers/${id}/edit`);
    }
};

// --- PRINT FUNCTIONALITY ---
const printVoucher = (voucher) => {
    // Open print view in new window or redirect to print route
    const printUrl = `/vouchers/${voucher.id}/print`;
    window.open(printUrl, '_blank');
};

// --- NEW VOUCHER MENU ---
const menu = ref(null);
const newVoucherItems = ref([
    {
        label: 'Standard Voucher',
        icon: 'pi pi-file',
        description:
            'For general expenses like utilities or standard purchases.',
        command: () => {
            window.location.href = '/vouchers/create?type=standard';
        },
    },
    {
        label: 'Prepayment Voucher',
        icon: 'pi pi-list',
        description: 'For expenses paid in advance, e.g., rent or insurance.',
        command: () => {
            window.location.href = '/vouchers/create?type=prepayment';
        },
    },
]);

const toggleMenu = (event) => {
    if (menu.value) {
        menu.value.toggle(event);
    }
};

// --- HELPER FUNCTIONS ---
const formatCurrency = (value) => {
    return new Intl.NumberFormat('en-NG', {
        style: 'currency',
        currency: 'NGN',
    }).format(value);
};

// --- PAGINATION ---
const paginatorTotalRecords = computed(() => vouchers.value.total);
const onPageChange = (event) => {
    console.log(`Navigating to page ${event.page + 1}`);
    // Use real pagination if needed
    const url = vouchers.value.links[event.page + 1]?.url;
    if (url) {
        router.get(url, {}, { preserveState: true, replace: true });
    }
};

const breadcrumbs = [{ title: 'Vouchers', href: '#' }];




const loadVouchers = async () => {
    loading.value = true;
    try {
        const response = await axios.get('vsearch', { params: { per_page: lazyParams.value.rows, page: lazyParams.value.page, search: searchQuery.value }, });
        console.log(response.data);
        vouchers.value = response.data.vouchers.data;
        totalRecords.value = response.data.paginator.total;
    } catch (error) {
        toast.add({ severity: "error", summary: "Error", detail: "Failed to load data", life: 3000 });
        console.error(error);

    }
    loading.value = false;
};

// Call debug on mount

onMounted(() => {
    // debugVoucherStatuses();
    console.log('=== END DEBUG ===');
    console.log(props);
    console.log('=== END DEBUG ===');
    lazyParams.value.page = 1;
    loadVouchers();
});


watch(searchQuery, () => {
    clearTimeout(debounceTimer);
    debounceTimer = setTimeout(() => {
        lazyParams.value.page = 1; // Reset to first page when searching
        loadVouchers();
    }, 2000); // 500ms debounce delay

});
const dt = ref();

const exportCSV = () => {
    dt.value.exportCSV();
};


const exportItemCode = (event) => {
    const rowData = event.data; // Full row object
    const items = rowData.items;

    // Manual extraction for the exporter
    if (items && items.length > 0 && items[0].economy_code_item) {
        return items[0].economy_code_item.code;
    }

    return 'N/A';
};
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">

        <Head title="Voucher List" />

        <Toast />

        <Card>
            <template #title>
                <div class="justify-content-between align-items-center flex">
                    <span>Voucher List ({{ totalRecords }})</span>

                    <div class="relative">
                        <!-- <Button
                            label="Create Voucher"
                            icon="pi pi-plus"
                            severity="success"
                            @click="toggleMenu"
                            aria-controls="voucher_menu"
                            aria-haspopup="true"
                        /> -->

                        <Menu ref="menu" id="voucher_menu" :model="newVoucherItems" :popup="true">
                            <template #item="{ item, props }">
                                <a v-ripple class="align-items-center p-menuitem-link flex" v-bind="props.action">
                                    <span :class="item.icon" />
                                    <div class="flex-column ml-3 flex">
                                        <span class="font-bold">{{
                                            item.label
                                            }}</span>
                                        <small class="text-500">{{
                                            item.description
                                            }}</small>
                                    </div>
                                </a>
                            </template>
                        </Menu>
                    </div>
                </div>
            </template>

            <template #content>
                <DataTable ref="dt" v-model:filters="filters" :value="vouchers" dataKey="id" stripedRows
                    responsiveLayout="scroll" class="p-datatable-sm" :emptyMessage="'No vouchers found.'"
                    :paginator="true" :rowsPerPageOptions="[5, 10, 20, 50, 100, 500]" :loading="loading"
                    :rows="lazyParams.rows" :totalRecords="totalRecords" @page="onPage" removableSort
                    :globalFilterFields="['voucher_number', 'voucher_type', 'voucher_date', 'mda.name', 'narration', 'status']"
                    lazy size="small"
                    paginatorTemplate="RowsPerPageDropdown FirstPageLink PrevPageLink CurrentPageReport NextPageLink LastPageLink"
                    currentPageReportTemplate="{first} to {last} of {totalRecords}" exportFilename="vouchers">

                    <template #header>
                        <div class="flex justify-end">
                            <IconField>
                                <InputIcon>
                                    <i class="pi pi-search" />
                                </InputIcon>
                                <InputText v-model="searchQuery" placeholder="Keyword Search" />
                            </IconField> &nbsp; &nbsp; &nbsp; &nbsp;



                            <Button icon="  pi pi-external-link" label="Export" @click="exportCSV($event)" />

                        </div>
                    </template>

                    <Column field="voucher_number" header="Voucher #" headerStyle="width: 10%" :sortable="true">
                        <template #body="slotProps">
                            <Link :href="'/vouchers/' + slotProps.data.id"
                                class="text-primary-600 font-medium hover:underline">
                                {{ slotProps.data.voucher_number }}
                            </Link>
                        </template>
                    </Column>

                    <Column field="voucher_type" header="Voucher Type" headerStyle="width: 15%">
                        <template #body="slotProps">
                            <span v-if="slotProps.data.voucher_type">{{
                                slotProps.data.voucher_type
                                }}</span>
                            <span v-else class="text-500">N/A</span>
                        </template>
                    </Column>

                    <Column field="voucher_date" header="Date" headerStyle="width: 10%" :sortable="true">
                        <template #body="slotProps">
                            {{
                                new Date(
                                    slotProps.data.voucher_date,
                                ).toLocaleDateString()
                            }}
                        </template>
                    </Column>

                    <Column field="mda.name" header="MDA" headerStyle="width: 25%">
                        <template #body="slotProps">
                            <span v-if="slotProps.data.mda">{{
                                slotProps.data.mda.name
                                }}</span>
                            <span v-else class="text-500">N/A</span>
                        </template>
                    </Column>
                    <Column :visible="false" hidden="true" field="mda.code" header="Admin Code"
                        headerStyle="width: 25%">
                        <template #body="slotProps">
                            <span v-if="slotProps.data.mda">{{
                                slotProps.data.mda.code
                                }}</span>
                            <span v-else class="text-500">N/A</span>
                        </template>
                    </Column>
                    <Column :visible="false" hidden="true" field="bank_activity.bank_name" header="Bank Name"
                        headerStyle="width: 25%">
                        <template #body="slotProps">
                            <span v-if="slotProps.data.bank_activity">{{
                                slotProps.data.bank_activity.bank_name
                                }}</span>
                            <span v-else class="text-500">N/A</span>
                        </template>
                    </Column>
                    <Column :visible="false" hidden="true" field="bank_activity.title" header="Payment Title"
                        headerStyle="width: 25%">
                        <template #body="slotProps">
                            <span v-if="slotProps.data.bank_activity">{{
                                slotProps.data.bank_activity.title
                                }}</span>
                            <span v-else class="text-500">N/A</span>
                        </template>
                    </Column>
                    <Column :visible="false" hidden="true" field="bank_activity.account_number" header="Account Number"
                        headerStyle="width: 25%">
                        <template #body="slotProps">
                            <span v-if="slotProps.data.bank_activity">{{
                                slotProps.data.bank_activity.account_number
                                }}</span>
                            <span v-else class="text-500">N/A</span>
                        </template>
                    </Column>
                    <Column :visible="false" hidden="true" field="bank_activity.economic_code"
                        header="Bank Economic Code" headerStyle="width: 25%">
                        <template #body="slotProps">
                            <span v-if="slotProps.data.bank_activity">{{
                                slotProps.data.bank_activity.economic_code
                                }}</span>
                            <span v-else class="text-500">N/A</span>
                        </template>
                    </Column>

                    <Column :visible="false" hidden="true" field="items.economy_code_item.code"
                        header="Item Economic Code" headerStyle="width: 25%" :exportFunction="exportItemCode">
                        <template #body="slotProps">
                            <!-- Keep your UI template as is -->
                            <span v-if="slotProps.data.items?.economy_code_item">
                                {{ slotProps.data.items.economy_code_item.code }}
                            </span>
                            <span v-else class="text-500">N/A</span>
                        </template>
                    </Column>


                    <Column :visible="false" hidden="true" field="payee_name"
                        header="Payee/Beneficiary" headerStyle="width: 25%" >
                        <template #body="slotProps">
                            <!-- Keep your UI template as is -->
                            <span v-if="slotProps.data.payee_name">
                                {{ slotProps.data.payee_name }}
                            </span>
                            <span v-else class="text-500">N/A</span>
                        </template>
                    </Column>

                    <Column :visible="false" hidden="true" field="bank_activity.tag" header="TAG"
                        headerStyle="width: 25%">
                        <template #body="slotProps">
                            <span v-if="slotProps.data.bank_activity.tag">{{
                                slotProps.data?.bank_activity.tag || 'N/A'
                                }}</span>
                            <span v-else class="text-500">N/A</span>
                        </template>
                    </Column>

                    <Column field="narration" header="Narration" headerStyle="width: 25%">
                        <template #body="slotProps">
                            <span class="text-600 block max-w-xs truncate">
                                {{ slotProps.data.narration || 'No narration' }}
                            </span>
                        </template>
                    </Column>

                    <Column field="total_amount" header="Total Amount" headerStyle="width: 15%"
                        bodyClass="font-bold text-left" :sortable="true">
                        <template #body="slotProps">
                            {{
                                formatCurrency(slotProps.data.total_amount || 0)
                            }}
                        </template>
                    </Column>

                    <Column field="status" header="Status" headerStyle="width: 10%">
                        <template #body="slotProps">
                            <Tag :value="slotProps.data.status" :severity="getStatusSeverity(slotProps.data.status)
                                " />
                        </template>
                    </Column>

                    <Column header="Actions" headerStyle="width: 15%" bodyClass="text-center">
                        <template #body="slotProps">
                            <div class="justify-content-center flex gap-2">
                                <Button icon="pi pi-print" text rounded severity="info" v-tooltip.top="'Print Voucher'"
                                    @click="printVoucher(slotProps.data)" />

                                <!-- EDIT BUTTON: Disabled for approved vouchers -->
                                <Button icon="pi pi-pencil" text rounded severity="secondary"
                                    :disabled="!canEditVoucher(slotProps.data)" v-tooltip.top="canEditVoucher(slotProps.data)
                                        ? 'Edit Voucher'
                                        : `Cannot edit - Status: ${slotProps.data.status}`
                                        " @click="
                                            openConfirmationModal(
                                                slotProps.data,
                                                'edit',
                                            )
                                            " />

                                <!-- DELETE BUTTON: Disabled for approved/submitted vouchers -->
                                <Button v-if="usePage().props.auth.userRoles.includes('Admin') || usePage().props.auth.userRoles.includes('admin')" icon="pi pi-trash" text rounded severity="danger" :disabled="!canDeleteVoucher(slotProps.data)
                                    " v-tooltip.top="canDeleteVoucher(slotProps.data)
                                        ? 'Delete Voucher'
                                        : `Cannot delete - Status: ${slotProps.data.status}`
                                        " @click="
                                            openConfirmationModal(
                                                slotProps.data,
                                                'delete',
                                            )
                                            " />
                            </div>
                        </template>
                    </Column>
                </DataTable>

                <!-- <div class="justify-content-end mt-4 flex">
                    <Paginator :rows="vouchers.per_page" :totalRecords="paginatorTotalRecords"
                        :first="(vouchers.current_page - 1) * vouchers.per_page" @page="onPageChange" :template="{
                            '640px':
                                'PrevPageLink CurrentPageReport NextPageLink',
                            '960px':
                                'FirstPageLink PrevPageLink CurrentPageReport NextPageLink LastPageLink',
                            '1300px':
                                'FirstPageLink PrevPageLink PageLinks NextPageLink LastPageLink',
                            default:
                                'FirstPageLink PrevPageLink PageLinks NextPageLink LastPageLink RowsPerPageDropdown',
                        }" />
                </div> -->
            </template>
        </Card>

        <!-- Debug Information Card (Set v-if to true to enable debugging) -->
        <Card v-if="false" class="mt-4">
            <template #title>
                <span>Status Debug Information</span>
            </template>
            <template #content>
                <div class="text-500 mb-3 text-sm">
                    This section shows the edit/delete permissions for each
                    voucher. Set v-if to "true" to enable this debug view.
                </div>
                <div v-for="voucher in vouchers.data" :key="voucher.id" class="surface-50 border-round mb-2 p-2">
                    <strong>{{ voucher.voucher_number }}</strong>: Status: "<strong>{{ voucher.status }}</strong>" â†’ Can
                    Edit:
                    <strong :class="canEditVoucher(voucher)
                        ? 'text-green-600'
                        : 'text-red-600'
                        ">{{ canEditVoucher(voucher) }}</strong>
                    | Can Delete:
                    <strong :class="canDeleteVoucher(voucher)
                        ? 'text-green-600'
                        : 'text-red-600'
                        ">{{ canDeleteVoucher(voucher) }}</strong>
                </div>
            </template>
        </Card>

        <Dialog v-model:visible="showConfirmationModal" :style="{ width: '400px' }" header="Confirm Action"
            :modal="true">
            <div class="align-items-center flex">
                <i :class="currentAction === 'delete'
                    ? 'pi pi-exclamation-triangle mr-3 text-red-500'
                    : 'pi pi-question-circle mr-3 text-orange-500'
                    " style="font-size: 2rem"></i>

                <span v-if="currentVoucher && currentAction === 'delete'">
                    Are you sure you want to
                    <strong>permanently delete</strong> Voucher
                    <strong>{{ currentVoucher.voucher_number }}</strong>? This action cannot be undone.
                </span>

                <span v-else-if="currentVoucher && currentAction === 'edit'">
                    Do you want to proceed to the edit page for Voucher
                    <strong>{{ currentVoucher.voucher_number }}</strong>?
                </span>
            </div>

            <template #footer>
                <Button label="No" icon="pi pi-times" @click="showConfirmationModal = false" text />

                <Button :label="currentAction === 'delete'
                    ? 'Yes, Delete'
                    : 'Yes, Proceed'
                    " :icon="currentAction === 'delete'
                        ? 'pi pi-trash'
                        : 'pi pi-check'
                        " :severity="currentAction === 'delete' ? 'danger' : 'secondary'
                            " @click="confirmAction" autofocus />
            </template>
        </Dialog>
    </AppLayout>
</template>
