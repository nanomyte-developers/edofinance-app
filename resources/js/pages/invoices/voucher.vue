<template>
    <Card class="invoice-card">
        <template #title>
            <div class="flex justify-content-between align-items-center">
                <span>Invoice</span>
                <div class="flex gap-2">
                    <Button label="Print" icon="pi pi-print" @click="printInvoice" />
                    <Button label="Download" icon="pi pi-download" severity="secondary" />
                </div>
            </div>
        </template>
        
        <template #content>
            <!-- Invoice Header -->
            <div class="invoice-header grid">
                <div class="col-6">
                    <div class="company-info">
                        <h2 class="company-name">Your Company Name</h2>
                        <p class="company-address">
                            123 Business Street<br>
                            City, State 12345<br>
                            Phone: (123) 456-7890<br>
                            Email: info@company.com
                        </p>
                    </div>
                </div>
                <div class="col-6 text-right">
                    <div class="invoice-info">
                        <h3 class="invoice-number">Invoice #{{ invoice.number }}</h3>
                        <p class="invoice-date">
                            <strong>Date:</strong> {{ formatDate(invoice.date) }}<br>
                            <strong>Due Date:</strong> {{ formatDate(invoice.dueDate) }}
                        </p>
                    </div>
                </div>
            </div>

            <!-- Bill To / Ship To -->
            <div class="grid mt-5">
                <div class="col-6">
                    <div class="field-group">
                        <h4>Bill To:</h4>
                        <div class="field-value">
                            <strong>{{ invoice.customer.name }}</strong><br>
                            {{ invoice.customer.address }}<br>
                            {{ invoice.customer.city }}, {{ invoice.customer.state }} {{ invoice.customer.zip }}<br>
                            {{ invoice.customer.email }}
                        </div>
                    </div>
                </div>
                <div class="col-6">
                    <div class="field-group">
                        <h4>Ship To:</h4>
                        <div class="field-value">
                            <strong>{{ invoice.shipping.name }}</strong><br>
                            {{ invoice.shipping.address }}<br>
                            {{ invoice.shipping.city }}, {{ invoice.shipping.state }} {{ invoice.shipping.zip }}
                        </div>
                    </div>
                </div>
            </div>

            <!-- Line Items Table -->
            <div class="mt-5">
                <DataTable :value="invoice.items" class="p-datatable-sm" responsiveLayout="scroll">
                    <Column field="description" header="Description" headerStyle="width: 40%">
                        <template #body="slotProps">
                            <div class="item-description">
                                <strong>{{ slotProps.data.description }}</strong>
                                <div v-if="slotProps.data.details" class="item-details text-500 text-sm">
                                    {{ slotProps.data.details }}
                                </div>
                            </div>
                        </template>
                    </Column>
                    <Column field="quantity" header="Qty" headerStyle="width: 15%">
                        <template #body="slotProps">
                            {{ slotProps.data.quantity }}
                        </template>
                    </Column>
                    <Column field="price" header="Unit Price" headerStyle="width: 15%">
                        <template #body="slotProps">
                            {{ formatCurrency(slotProps.data.price) }}
                        </template>
                    </Column>
                    <Column field="amount" header="Amount" headerStyle="width: 15%">
                        <template #body="slotProps">
                            <strong>{{ formatCurrency(slotProps.data.amount) }}</strong>
                        </template>
                    </Column>
                    <Column headerStyle="width: 15%">
                        <template #body>
                            <Button icon="pi pi-trash" severity="danger" text rounded />
                        </template>
                    </Column>
                </DataTable>
            </div>

            <!-- Totals -->
            <div class="grid mt-5">
                <div class="col-6">
                    <div class="notes-section">
                        <h4>Notes</h4>
                        <Textarea v-model="invoice.notes" rows="3" class="w-full" placeholder="Add any additional notes here..." />
                    </div>
                </div>
                <div class="col-6">
                    <div class="totals-section">
                        <div class="total-row flex justify-content-between mb-2">
                            <span class="text-500">Subtotal:</span>
                            <span class="font-semibold">{{ formatCurrency(invoice.subtotal) }}</span>
                        </div>
                        <div class="total-row flex justify-content-between mb-2">
                            <span class="text-500">Tax ({{ invoice.taxRate }}%):</span>
                            <span class="font-semibold">{{ formatCurrency(invoice.taxAmount) }}</span>
                        </div>
                        <div class="total-row flex justify-content-between mb-2">
                            <span class="text-500">Discount:</span>
                            <span class="font-semibold text-red-500">-{{ formatCurrency(invoice.discount) }}</span>
                        </div>
                        <div class="total-row flex justify-content-between mb-2">
                            <span class="text-500">Shipping:</span>
                            <span class="font-semibold">{{ formatCurrency(invoice.shippingCost) }}</span>
                        </div>
                        <Divider />
                        <div class="total-row flex justify-content-between text-xl font-bold">
                            <span>Total:</span>
                            <span class="text-primary">{{ formatCurrency(invoice.total) }}</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Payment Information -->
            <div class="mt-5">
                <h4>Payment Information</h4>
                <div class="grid">
                    <div class="col-4">
                        <div class="field">
                            <label class="block text-500 text-sm mb-1">Payment Method</label>
                            <Dropdown v-model="invoice.paymentMethod" :options="paymentMethods" optionLabel="name" 
                                     placeholder="Select Payment Method" class="w-full" />
                        </div>
                    </div>
                    <div class="col-4">
                        <div class="field">
                            <label class="block text-500 text-sm mb-1">Payment Status</label>
                            <Dropdown v-model="invoice.paymentStatus" :options="paymentStatuses" 
                                     placeholder="Select Status" class="w-full" />
                        </div>
                    </div>
                    <div class="col-4">
                        <div class="field">
                            <label class="block text-500 text-sm mb-1">Amount Paid</label>
                            <InputNumber v-model="invoice.amountPaid" mode="currency" currency="USD" locale="en-US" 
                                        class="w-full" />
                        </div>
                    </div>
                </div>
            </div>

            <!-- Actions -->
            <div class="flex justify-content-end gap-2 mt-5">
                <Button label="Save Draft" severity="secondary" />
                <Button label="Send Invoice" icon="pi pi-send" />
                <Button label="Mark as Paid" severity="success" />
            </div>
        </template>
    </Card>
</template>

<script setup lang="ts">
import { ref, computed } from 'vue';
import Card from 'primevue/card';
import Button from 'primevue/button';
import DataTable from 'primevue/datatable';
import Column from 'primevue/column';
import Textarea from 'primevue/textarea';
import Divider from 'primevue/divider';
import Dropdown from 'primevue/dropdown';
import InputNumber from 'primevue/inputnumber';

// Invoice data
const invoice = ref({
    number: 'INV-001',
    date: new Date(),
    dueDate: new Date(Date.now() + 30 * 24 * 60 * 60 * 1000), // 30 days from now
    customer: {
        name: 'John Doe',
        address: '456 Customer Ave',
        city: 'New York',
        state: 'NY',
        zip: '10001',
        email: 'john.doe@example.com'
    },
    shipping: {
        name: 'John Doe',
        address: '456 Customer Ave',
        city: 'New York',
        state: 'NY',
        zip: '10001'
    },
    items: [
        { id: 1, description: 'Website Design', details: 'Custom responsive website design', quantity: 1, price: 1200, amount: 1200 },
        { id: 2, description: 'Web Development', details: 'Frontend and backend development', quantity: 40, price: 75, amount: 3000 },
        { id: 3, description: 'SEO Optimization', details: 'Search engine optimization package', quantity: 1, price: 800, amount: 800 },
        { id: 4, description: 'Hosting & Maintenance', details: '12 months hosting and support', quantity: 12, price: 50, amount: 600 }
    ],
    notes: 'Thank you for your business! We appreciate your trust in our services.',
    subtotal: 5600,
    taxRate: 10,
    taxAmount: 560,
    discount: 200,
    shippingCost: 50,
    total: 6010,
    paymentMethod: null,
    paymentStatus: 'Pending',
    amountPaid: 0
});

// Computed properties
const invoiceTotal = computed(() => {
    const subtotal = invoice.value.items.reduce((sum, item) => sum + item.amount, 0);
    const tax = subtotal * (invoice.value.taxRate / 100);
    return subtotal + tax - invoice.value.discount + invoice.value.shippingCost;
});

// Methods
const formatCurrency = (value: number) => {
    return new Intl.NumberFormat('en-US', {
        style: 'currency',
        currency: 'USD'
    }).format(value);
};

const formatDate = (date: Date) => {
    return new Intl.DateTimeFormat('en-US', {
        year: 'numeric',
        month: 'long',
        day: 'numeric'
    }).format(date);
};

const printInvoice = () => {
    window.print();
};

// Dropdown options
const paymentMethods = ref([
    { name: 'Credit Card', value: 'credit_card' },
    { name: 'Bank Transfer', value: 'bank_transfer' },
    { name: 'PayPal', value: 'paypal' },
    { name: 'Check', value: 'check' },
    { name: 'Cash', value: 'cash' }
]);

const paymentStatuses = ref([
    'Pending',
    'Paid',
    'Overdue',
    'Cancelled',
    'Refunded'
]);
</script>

<style scoped>
.invoice-card {
    min-height: 100vh;
}

.company-name {
    color: var(--p-primary-color);
    margin: 0;
    font-size: 1.5rem;
}

.company-address {
    color: var(--p-text-color-secondary);
    margin: 0.5rem 0 0 0;
    line-height: 1.5;
}

.invoice-number {
    color: var(--p-primary-color);
    margin: 0;
    font-size: 1.25rem;
}

.invoice-date {
    color: var(--p-text-color-secondary);
    margin: 0.5rem 0 0 0;
    line-height: 1.5;
}

.field-group h4 {
    margin: 0 0 0.5rem 0;
    color: var(--p-text-color);
    font-size: 1rem;
}

.field-value {
    color: var(--p-text-color);
    line-height: 1.5;
    padding: 0.75rem;
    background: var(--p-surface-50);
    border-radius: 6px;
    border: 1px solid var(--p-surface-200);
}

.item-description {
    line-height: 1.4;
}

.item-details {
    margin-top: 0.25rem;
    font-size: 0.875rem;
}

.notes-section h4 {
    margin: 0 0 0.5rem 0;
    color: var(--p-text-color);
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

:deep(.p-datatable) {
    border: 1px solid var(--p-surface-200);
    border-radius: 6px;
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

/* Print styles */
@media print {
    .invoice-card {
        box-shadow: none !important;
        border: none !important;
    }
    
    .p-button {
        display: none !important;
    }
    
    .p-dropdown, .p-inputnumber, .p-textarea {
        border: 1px solid #ccc !important;
        background: white !important;
    }
}
</style>