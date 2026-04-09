<script setup>
import { computed, ref } from 'vue';
import { Head, router } from '@inertiajs/vue3';

// --- PrimeVue Imports ---
import Card from 'primevue/card';
import DataTable from 'primevue/datatable';
import Column from 'primevue/column';
import Button from 'primevue/button';
import Tag from 'primevue/tag';
import Paginator from 'primevue/paginator';
import Dialog from 'primevue/dialog';
import Toast from 'primevue/toast';
import { useToast } from 'primevue/usetoast';
import Textarea from 'primevue/textarea';
import AppLayout from '@/layouts/AppLayout.vue'; 

const toast = useToast();

// --- 1. DEFINE PROPS ---
const props = defineProps({
    vouchers: Object, // Inertia paginated object
});

// --- 2. DUMMY DATA (Fallback Source) ---
const dummyVouchers = {
    data: [
        { 
            id: 10, 
            number: 'VCH-010', 
            date: new Date(2025, 11, 1), 
            customer: { name: 'Final Review Ltd. (Dummy)' }, 
            invoiceTotal: 120000.00, 
            paymentStatus: 'Pending Final Accounts', 
            documentPath: '/docs/vch_010.pdf' 
        },
        { 
            id: 11, 
            number: 'VCH-011', 
            date: new Date(2025, 11, 10), 
            customer: { name: 'Closing Books Co. (Dummy)' }, 
            invoiceTotal: 35000.00, 
            paymentStatus: 'Pending Final Accounts', 
            documentPath: '/docs/vch_011.jpg' 
        },
    ],
    total: 2, 
    per_page: 15, 
    current_page: 1, 
    from: 1, 
    to: 2,
};

// --- 3. UNIFIED DATA SOURCE (The FIX for 'undefined prop' error) ---
const finalAccountVoucherData = computed(() => {
    return props.vouchers || dummyVouchers;
});

// --- 4. STATE FOR MODAL AND ACTIONS ---
const showRejectionModal = ref(false);
const showApprovalModal = ref(false); 
const showDocumentViewer = ref(false);
const currentVoucher = ref(null);
const rejectionReason = ref('');
const documentUrl = ref(''); 

// --- 5. COMPUTED PROPERTIES for Paginator ---
const paginatorTotalRecords = computed(() => finalAccountVoucherData.value.total);
const paginatorCurrentPage = computed(() => finalAccountVoucherData.value.current_page);
const paginatorRows = computed(() => finalAccountVoucherData.value.per_page);

const breadcrumbs = [ { title: 'Final Accounts Queue', href: '#' } ];

// --- 6. ACTION HANDLERS ---

const openDocument = (voucher) => {
    documentUrl.value = voucher.documentPath || '#';
    currentVoucher.value = voucher;
    showDocumentViewer.value = true;
};

const openRejectModal = (voucher) => {
    currentVoucher.value = voucher;
    rejectionReason.value = ''; 
    showRejectionModal.value = true;
};

const openApproveModal = (voucher) => {
    currentVoucher.value = voucher;
    showApprovalModal.value = true; 
};

const handleApprove = () => {
    // Send Inertia request to a hypothetical FinalAccountsController's 'approve' method
    router.post(`/final-accounts/vouchers/${currentVoucher.value.id}/approve`, {}, {
        preserveScroll: true,
        onSuccess: () => {
            showApprovalModal.value = false;
            toast.add({ severity: 'success', summary: 'Approved', detail: `Voucher ${currentVoucher.value.number} is approved and ready for closure.`, life: 4000 });
        },
        onError: (errors) => {
            showApprovalModal.value = false;
            toast.add({ severity: 'error', summary: 'Error', detail: 'Failed to approve voucher.', life: 5000 });
        }
    });
};

const handleReject = () => {
    if (!rejectionReason.value) {
        toast.add({ severity: 'warn', summary: 'Required', detail: 'Please provide a reason for rejection.', life: 3000 });
        return;
    }
    
    // Send Inertia request to a hypothetical FinalAccountsController's 'reject' method
    router.post(`/final-accounts/vouchers/${currentVoucher.value.id}/reject`, {
        reason: rejectionReason.value
    }, {
        preserveScroll: true,
        onSuccess: () => {
            toast.add({ severity: 'info', summary: 'Rejected', detail: `Voucher ${currentVoucher.value.number} has been rejected.`, life: 4000 });
            showRejectionModal.value = false;
        },
        onError: (errors) => {
            toast.add({ severity: 'error', summary: 'Error', detail: 'Failed to reject voucher.', life: 5000 });
        }
    });
};

// Helper function
const formatCurrency = (value) => {
    return new Intl.NumberFormat('en-NG', { style: 'currency', currency: 'NGN' }).format(value);
};
const onPageChange = (event) => { 
    console.log(`Navigating to page ${event.page + 1}`); 
};

</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head title="Final Accounts Queue" />

        <Toast />

        <Card>
            <template #title>
                <div class="flex justify-content-between align-items-center">
                    <span>Final Accounts Queue ({{ finalAccountVoucherData.total }})</span>
                    <Button label="Refresh List" icon="pi pi-refresh" severity="secondary" @click="router.reload()" />
                </div>
            </template>
            
            <template #content>
                <div class="mb-4">
                    <Message severity="info" :closable="false" class="p-0">
                        <div class="flex align-items-center">
                            <i class="pi pi-exclamation-triangle mr-2 text-xl"></i>
                            <span class="font-medium">Final Accounts Review Queue:</span>
                            <span class="ml-2">These **{{ finalAccountVoucherData.total }}** vouchers are pending final accounts review and require approval for ledger closure.</span>
                        </div>
                    </Message>
                </div>
                <DataTable 
                    :value="finalAccountVoucherData.data" 
                    dataKey="id" 
                    stripedRows 
                    responsiveLayout="scroll"
                    class="p-datatable-sm"
                    :emptyMessage="'No vouchers pending final accounts review.'"
                >
                    <Column field="number" header="Voucher #" headerStyle="width: 10%">
                        <template #body="slotProps">
                            <span class="font-medium">{{ slotProps.data.number }}</span>
                        </template>
                    </Column>
                    
                    <Column field="customer.name" header="Submitted By/Vendor" headerStyle="width: 25%">
                        <template #body="slotProps">
                            <span v-if="slotProps.data.customer">{{ slotProps.data.customer.name }}</span>
                            <span v-else class="text-500">N/A</span>
                        </template>
                    </Column>
                    
                    <Column field="date" header="Submission Date" headerStyle="width: 15%">
                        <template #body="slotProps">
                            {{ new Date(slotProps.data.date).toLocaleDateString() }}
                        </template>
                    </Column>
                    
                    <Column field="invoiceTotal" header="Amount" headerStyle="width: 15%" bodyClass="font-bold text-right">
                        <template #body="slotProps">
                            {{ formatCurrency(slotProps.data.invoiceTotal || 0) }}
                        </template>
                    </Column>
                    
                    <Column field="paymentStatus" header="Status" headerStyle="width: 10%">
                        <template #body>
                            <Tag value="Pending Final Accounts" severity="warning" />
                        </template>
                    </Column>

                    <Column header="Actions" headerStyle="width: 25%" bodyClass="text-center">
                        <template #body="slotProps">
                            <div class="flex gap-2 justify-content-center">
                                <Button 
                                    icon="pi pi-eye" 
                                    label="View Doc"
                                    severity="info"
                                    outlined
                                    v-tooltip.top="'View Attached Document'"
                                    @click="openDocument(slotProps.data)"
                                />
                                <Button 
                                    icon="pi pi-check" 
                                    severity="success"
                                    v-tooltip.top="'Approve Voucher'"
                                    @click="openApproveModal(slotProps.data)"
                                />
                                <Button 
                                    icon="pi pi-times" 
                                    severity="danger" 
                                    v-tooltip.top="'Reject Voucher'"
                                    @click="openRejectModal(slotProps.data)" 
                                />
                            </div>
                        </template>
                    </Column>
                </DataTable>

                <div class="mt-4 flex justify-content-end">
                    <Paginator 
                        :rows="paginatorRows" 
                        :totalRecords="paginatorTotalRecords" 
                        :first="(paginatorCurrentPage - 1) * paginatorRows"
                        @page="onPageChange"
                        :template="{ 
                            default: 'FirstPageLink PrevPageLink PageLinks NextPageLink LastPageLink' 
                        }"
                    />
                </div>
            </template>
        </Card>

        <Dialog 
            v-model:visible="showApprovalModal" 
            :style="{ width: '400px' }" 
            header="Confirm Final Approval" 
            :modal="true"
        >
            <div class="flex align-items-center">
                <i class="pi pi-exclamation-circle text-2xl mr-3 text-primary"></i>
                <span>
                    Are you absolutely sure you want to **APPROVE** Voucher **{{ currentVoucher?.number }}**? 
                    This action is final and will close the voucher for ledger posting.
                </span>
            </div>
            
            <template #footer>
                <Button label="Cancel" icon="pi pi-times" @click="showApprovalModal = false" text />
                <Button label="Approve" icon="pi pi-check-circle" severity="success" @click="handleApprove" />
            </template>
        </Dialog>

        <Dialog 
            v-model:visible="showRejectionModal" 
            :style="{ width: '450px' }" 
            header="Reject Voucher" 
            :modal="true"
        >
            <div class="p-fluid">
                <p>
                    Voucher **{{ currentVoucher?.number }}** will be returned to the originator.
                    Please state the **mandatory reason** for rejection.
                </p>
                <div class="field mt-3">
                    <label for="reject_reason">Reason</label>
                    <Textarea id="reject_reason" v-model="rejectionReason" rows="5" />
                </div>
            </div>
            
            <template #footer>
                <Button label="Cancel" icon="pi pi-times" @click="showRejectionModal = false" text />
                <Button label="Confirm Rejection" icon="pi pi-ban" severity="danger" @click="handleReject" />
            </template>
        </Dialog>
        
        <Dialog 
            v-model:visible="showDocumentViewer" 
            :header="'Viewing Document: ' + currentVoucher?.number" 
            :style="{ width: '80vw' }" 
            :modal="true"
            maximizable
        >
            <div style="height: 70vh;">
                <iframe 
                    :src="documentUrl" 
                    frameborder="0" 
                    width="100%" 
                    height="100%"
                ></iframe>
            </div>
        </Dialog>

    </AppLayout>
</template>