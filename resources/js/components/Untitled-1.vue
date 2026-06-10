<script setup>
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
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
import { computed, ref } from 'vue';

const toast = useToast();

// ðŸ’¡ State for Modal
const showConfirmationModal = ref(false);
const currentVoucher = ref(null);
const currentAction = ref(null);

// ðŸ’¡ PROPS: Receive real data from Laravel controller
const props = defineProps({
    vouchers: {
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
});

// Use the real vouchers data from props but maintain your structure
const vouchers = computed(() => props.vouchers);

// --- MODAL HANDLING FUNCTIONS ---
const openConfirmationModal = (voucher, action) => {
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
        // router.visit(route('vouchers.edit', id));
        router.visit(`/vouchers/${id}/edit`);
    }
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
const getStatusSeverity = (status) => {
    switch (status) {
        case 'Paid':
            return 'success';
        case 'Overdue':
            return 'danger';
        case 'Cancelled':
            return 'warning';
        default:
            return 'info';
    }
};

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
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head title="Voucher List" />

        <Toast />

        <Card>
            <template #title>
                <div class="justify-content-between align-items-center flex">
                    <span>Voucher List ({{ vouchers.total }})</span>

                    <div class="relative">
                        <Button
                            label="Create Voucher"
                            icon="pi pi-plus"
                            severity="success"
                            @click="toggleMenu"
                            aria-controls="voucher_menu"
                            aria-haspopup="true"
                        />

                        <Menu
                            ref="menu"
                            id="voucher_menu"
                            :model="newVoucherItems"
                            :popup="true"
                        >
                            <template #item="{ item, props }">
                                <a
                                    v-ripple
                                    class="align-items-center p-menuitem-link flex"
                                    v-bind="props.action"
                                >
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
                <DataTable
                    :value="vouchers.data"
                    dataKey="id"
                    stripedRows
                    responsiveLayout="scroll"
                    class="p-datatable-sm"
                    :loading="false"
                    :emptyMessage="'No vouchers found.'"
                >
                    <Column
                        field="voucher_number"
                        header="Voucher #"
                        headerStyle="width: 10%"
                        :sortable="true"
                    >
                        <template #body="slotProps">
                            <Link
                                :href="'/vouchers/' + slotProps.data.id"
                                class="text-primary-600 font-medium hover:underline"
                            >
                                {{ slotProps.data.voucher_number }}
                            </Link>
                        </template>
                    </Column>

                    <Column
                        field="voucher_type"
                        header="Voucher Type"
                        headerStyle="width: 15%"
                    >
                        <template #body="slotProps">
                            <span v-if="slotProps.data.voucher_type">{{
                                slotProps.data.voucher_type
                            }}</span>
                            <span v-else class="text-500">N/A</span>
                        </template>
                    </Column>

                    <Column
                        field="voucher_date"
                        header="Date"
                        headerStyle="width: 10%"
                        :sortable="true"
                    >
                        <template #body="slotProps">
                            {{
                                new Date(
                                    slotProps.data.voucher_date,
                                ).toLocaleDateString()
                            }}
                        </template>
                    </Column>

                    <Column
                        field="mda.name"
                        header="MDA"
                        headerStyle="width: 25%"
                    >
                        <template #body="slotProps">
                            <span v-if="slotProps.data.mda">{{
                                slotProps.data.mda.name
                            }}</span>
                            <span v-else class="text-500">N/A</span>
                        </template>
                    </Column>

                    <Column
                        field="narration"
                        header="Narration"
                        headerStyle="width: 25%"
                    >
                        <template #body="slotProps">
                            <span class="text-600 block max-w-xs truncate">
                                {{ slotProps.data.narration || 'No narration' }}
                            </span>
                        </template>
                    </Column>

                    <Column
                        field="total_amount"
                        header="Total Amount"
                        headerStyle="width: 15%"
                        bodyClass="font-bold text-left"
                        :sortable="true"
                    >
                        <template #body="slotProps">
                            {{
                                formatCurrency(slotProps.data.total_amount || 0)
                            }}
                        </template>
                    </Column>

                    <Column
                        field="status"
                        header="Status"
                        headerStyle="width: 10%"
                    >
                        <template #body="slotProps">
                            <Tag
                                :value="slotProps.data.status"
                                :severity="
                                    getStatusSeverity(slotProps.data.status)
                                "
                            />
                        </template>
                    </Column>

                    <Column
                        header="Actions"
                        headerStyle="width: 10%"
                        bodyClass="text-center"
                    >
                        <template #body="slotProps">
                            <div class="justify-content-center flex gap-2">
                                <Button
                                    icon="pi pi-pencil"
                                    text
                                    rounded
                                    severity="secondary"
                                    v-tooltip.top="'Edit Voucher'"
                                    @click="
                                        openConfirmationModal(
                                            slotProps.data,
                                            'edit',
                                        )
                                    "
                                />

                                <Button
                                    icon="pi pi-trash"
                                    text
                                    rounded
                                    severity="danger"
                                    v-tooltip.top="'Delete Voucher'"
                                    @click="
                                        openConfirmationModal(
                                            slotProps.data,
                                            'delete',
                                        )
                                    "
                                />
                            </div>
                        </template>
                    </Column>
                </DataTable>

                <div class="justify-content-end mt-4 flex">
                    <Paginator
                        :rows="vouchers.per_page"
                        :totalRecords="paginatorTotalRecords"
                        :first="(vouchers.current_page - 1) * vouchers.per_page"
                        @page="onPageChange"
                        :template="{
                            '640px':
                                'PrevPageLink CurrentPageReport NextPageLink',
                            '960px':
                                'FirstPageLink PrevPageLink CurrentPageReport NextPageLink LastPageLink',
                            '1300px':
                                'FirstPageLink PrevPageLink PageLinks NextPageLink LastPageLink',
                            default:
                                'FirstPageLink PrevPageLink PageLinks NextPageLink LastPageLink RowsPerPageDropdown',
                        }"
                    />
                </div>
            </template>
        </Card>

        <Dialog
            v-model:visible="showConfirmationModal"
            :style="{ width: '400px' }"
            header="Confirm Action"
            :modal="true"
        >
            <div class="align-items-center flex">
                <i
                    :class="
                        currentAction === 'delete'
                            ? 'pi pi-exclamation-triangle mr-3 text-red-500'
                            : 'pi pi-question-circle mr-3 text-orange-500'
                    "
                    style="font-size: 2rem"
                ></i>

                <span v-if="currentVoucher && currentAction === 'delete'">
                    Are you sure you want to **permanently delete** Voucher **{{
                        currentVoucher.voucher_number
                    }}**? This action cannot be undone.
                </span>

                <span v-else-if="currentVoucher && currentAction === 'edit'">
                    Do you want to proceed to the edit page for Voucher **{{
                        currentVoucher.voucher_number
                    }}**?
                </span>
            </div>

            <template #footer>
                <Button
                    label="No"
                    icon="pi pi-times"
                    @click="showConfirmationModal = false"
                    text
                />

                <Button
                    :label="
                        currentAction === 'delete'
                            ? 'Yes, Delete'
                            : 'Yes, Proceed'
                    "
                    :icon="
                        currentAction === 'delete'
                            ? 'pi pi-trash'
                            : 'pi pi-check'
                    "
                    :severity="
                        currentAction === 'delete' ? 'danger' : 'secondary'
                    "
                    @click="confirmAction"
                    autofocus
                />
            </template>
        </Dialog>
    </AppLayout>
</template>
