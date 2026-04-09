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
import Message from 'primevue/message';
import AppLayout from '@/layouts/AppLayout.vue'; 

const toast = useToast();

// --- 1. DEFINE PROPS ---
const props = defineProps({
    vouchers: Object, 
});

// --- 2. DUMMY DATA (Fallback Source) ---
const dummyVouchers = {
    data: [
        { 
            id: 30, 
            number: 'VCH-030', 
            date: new Date(2026, 1, 1), 
            customer: { name: 'AG Review Dept. (Dummy)' }, 
            invoiceTotal: 1500000.00, 
            paymentStatus: 'Pending Accountant General', 
            documentPath: '/docs/vch_030.pdf' 
        },
        { 
            id: 31, 
            number: 'VCH-031', 
            date: new Date(2026, 1, 15), 
            customer: { name: 'Final Signoff Co. (Dummy)' }, 
            invoiceTotal: 50000.00, 
            paymentStatus: 'Pending Accountant General', 
            documentPath: '/docs/vch_031.jpg' 
        },
    ],
    total: 2, per_page: 15, current_page: 1, from: 1, to: 2,
};

// --- 3. UNIFIED DATA SOURCE ---
const agVoucherData = computed(() => {
    return props.vouchers || dummyVouchers;
});

// --- 4. STATE FOR MODAL AND ACTIONS ---
const showDeclineModal = ref(false); // 💡 Renamed from showRejectionModal for clarity in AG flow
const showApprovalModal = ref(false); 
const showDocumentViewer = ref(false);
const currentVoucher = ref(null);
const rejectionReason = ref('');
const documentUrl = ref(''); 

// --- 5. COMPUTED PROPERTIES for Paginator ---
const paginatorTotalRecords = computed(() => agVoucherData.value.total);
const paginatorCurrentPage = computed(() => agVoucherData.value.current_page);
const paginatorRows = computed(() => agVoucherData.value.per_page);

const breadcrumbs = [ { title: 'Accountant General Queue', href: '#' } ];

// --- 6. ACTION HANDLERS ---

const openDocument = (voucher) => {
    documentUrl.value = voucher.documentPath || '#';
    currentVoucher.value = voucher;
    showDocumentViewer.value = true;
};

// Opens the combined Decline modal
const openDeclineModal = (voucher) => {
    currentVoucher.value = voucher;
    rejectionReason.value = ''; 
    showDeclineModal.value = true;
};

const openApproveModal = (voucher) => {
    currentVoucher.value = voucher;
    showApprovalModal.value = true; 
};

const handleApprove = () => {
    // Send Inertia request to a hypothetical AGController's 'approve' method
    router.post(`/accountant-general/vouchers/${currentVoucher.value.id}/approve`, {}, {
        preserveScroll: true,
        onSuccess: () => {
            showApprovalModal.value = false;
            toast.add({ severity: 'success', summary: 'Approved', detail: `Voucher ${currentVoucher.value.number} is approved and posted.`, life: 4000 });
        },
        onError: (errors) => {
            showApprovalModal.value = false;
            toast.add({ severity: 'error', summary: 'Error', detail: 'Failed to approve voucher.', life: 5000 });
        }
    });
};

// 💡 NEW: Sends the voucher back to the Director of Finance (DFA)
const handleDeclineToDFA = () => {
    if (!rejectionReason.value) {
        toast.add({ severity: 'warn', summary: 'Required', detail: 'Please provide a reason for declining.', life: 3000 });
        return;
    }
    
    // Set status to 'Rejected by AG - Sent to DFA'
    router.post(`/accountant-general/vouchers/${currentVoucher.value.id}/decline-dfa`, {
        reason: rejectionReason.value
    }, {
        preserveScroll: true,
        onSuccess: () => {
            toast.add({ severity: 'info', summary: 'Declined', detail: `Voucher ${currentVoucher.value.number} declined and returned to DFA.`, life: 4000 });
            showDeclineModal.value = false;
        },
        onError: (errors) => {
            toast.add({ severity: 'error', summary: 'Error', detail: 'Failed to decline voucher.', life: 5000 });
        }
    });
};

// 💡 NEW: Declines and permanently closes the voucher
const handleDeclineAndClose = () => {
    if (!rejectionReason.value) {
        toast.add({ severity: 'warn', summary: 'Required', detail: 'Please provide a reason for declining and closing.', life: 3000 });
        return;
    }
    
    // Set status to 'Permanently Closed by AG'
    router.post(`/accountant-general/vouchers/${currentVoucher.value.id}/decline-close`, {
        reason: rejectionReason.value
    }, {
        preserveScroll: true,
        onSuccess: () => {
            toast.add({ severity: 'danger', summary: 'Closed', detail: `Voucher ${currentVoucher.value.number} permanently closed.`, life: 4000 });
            showDeclineModal.value = false;
        },
        onError: (errors) => {
            toast.add({ severity: 'error', summary: 'Error', detail: 'Failed to close voucher.', life: 5000 });
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
        <Head title="Accountant General Queue" />

        <Toast />

        <Card>
            <template #title>
                <div class="flex justify-content-between align-items-center">
                    <span>Accountant General Queue ({{ agVoucherData.total }})</span>
                    <Button label="Refresh List" icon="pi pi-refresh" severity="secondary" @click="router.reload()" />
                </div>
            </template>
            
            <template #content>
                <div class="mb-4">
                    <Message severity="info" :closable="false" class="p-0">
                        <div class="flex align-items-center">
                            <i class="pi pi-briefcase mr-2 text-xl"></i>
                            <span class="font-medium">Accountant General Queue:</span>
                            <span class="ml-2">These **{{ agVoucherData.total }}** vouchers are pending final authority signature for posting.</span>
                        </div>
                    </Message>
                </div>

                <DataTable 
                    :value="agVoucherData.data" 
                    dataKey="id" 
                    stripedRows 
                    responsiveLayout="scroll"
                    class="p-datatable-sm"
                    :emptyMessage="'No vouchers pending AG review.'"
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
                            <Tag value="Pending AG" severity="danger" />
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
                                    v-tooltip.top="'Decline Voucher'"
                                    @click="openDeclineModal(slotProps.data)" 
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
            header="Confirm Approval" 
            :modal="true"
        >
            <div class="flex align-items-center">
                <i class="pi pi-exclamation-circle text-2xl mr-3 text-primary"></i>
                <span>
                    Are you absolutely sure you want to **APPROVE** Voucher **{{ currentVoucher?.number }}**? 
                    This is the final signoff for posting.
                </span>
            </div>
            
            <template #footer>
                <Button label="Cancel" icon="pi pi-times" @click="showApprovalModal = false" text />
                <Button label="Approve" icon="pi pi-check-circle" severity="success" @click="handleApprove" />
            </template>
        </Dialog>

        <Dialog 
            v-model:visible="showDeclineModal" 
            :style="{ width: '550px' }" 
            header="Decline Voucher - Select Destination" 
            :modal="true"
        >
            <div class="p-fluid">
                <p>
                    Voucher **{{ currentVoucher?.number }}** requires a mandatory reason for declining.
                </p>
                <div class="field mt-3">
                    <label for="reject_reason">Reason</label>
                    <Textarea id="reject_reason" v-model="rejectionReason" rows="5" />
                </div>
            </div>
            
            <template #footer>
                <Button label="Cancel" icon="pi pi-times" @click="showDeclineModal = false" text />
                
                <Button 
                    label="Decline & Close" 
                    icon="pi pi-ban" 
                    severity="danger" 
                    v-tooltip.top="'Stops the workflow permanently.'"
                    @click="handleDeclineAndClose" 
                />

                <Button 
                    label="Decline to DFA" 
                    icon="pi pi-arrow-left" 
                    severity="warning" 
                    v-tooltip.top="'Sends back to Director of Finance.'"
                    @click="handleDeclineToDFA" 
                />
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