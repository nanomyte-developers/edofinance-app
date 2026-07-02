<template>
    <div class="print-ledger-container" v-if="visible">
        <div class="print-ledger">
            <!-- Print Header -->
            <div class="print-header">
                <div class="print-title">OFFICE OF THE ACCOUNTANT GENERAL</div>
                <div class="print-subtitle">EXPENDITURE AND CONTROL DEPARTMENT</div>
                <div class="print-subtitle">TREASURY HOUSE</div>
                <div class="print-subtitle">SECRETARIAT COMPLEX</div>
                <h2 class="print-title">CAPITAL EXPENDITURE</h2>
                <div class="print-subtitle font-bold uppercase">
                    {{ mdaName || 'ALL MINISTRIES' }}
                </div>
                <div class="print-info">
                    <span>Period: {{ monthName }} {{ year }}</span>
                    <span>Generated: {{ currentDate }}</span>
                </div>
            </div>

            <!-- Ledger Table -->
            <table class="ledger-table">
                <thead>
                    <tr>
                        <th class="date-col">DATE</th>
                        <th class="head-code-col">HEAD/CODE</th>
                        <th class="pay-point-col">PAY POINT</th>
                        <th class="sch-col">SCH NO</th>
                        <th class="pv-col">PV NO</th>
                        <th class="payee-col">PAYEE</th>
                        <th class="purpose-col">PURPOSE OF PAYMENT</th>
                        <th class="amount-col">AMOUNT</th>
                        <th class="balance-col">BALANCE</th>
                        <th class="payment-date-col">PAYMENT DATE</th>
                    </tr>
                </thead>
                <tbody>
                    <!-- Opening Balance Row -->
                    <tr class="opening-balance-row">
                        <td class="text-center">{{ formatShortDate(startDate) }}</td>
                        <td class="text-center">-</td>
                        <td class="text-center">-</td>
                        <td class="text-center">-</td>
                        <td class="text-center">-</td>
                        <td class="text-left">BALANCE B/F</td>
                        <td class="text-left">Opening Balance</td>
                        <td class="amount-cell"></td>
                        <td class="amount-cell">{{ formatCurrencyAmount(openingBalance) }}</td>
                        <td class="text-center">-</td>
                    </tr>

                    <!-- Data Rows -->
                    <tr v-for="(entry, index) in entries" :key="index" class="data-row">
                        <td class="text-center">{{ formatShortDate(entry.transaction_date) }}</td>
                        <td class="text-center">{{ entry.head_code || entry.mda?.code || 'N/A' }}</td>
                        <td class="text-center">
                            <span class="pay-point-badge">{{ entry.pay_point || 'MAS' }}</span>
                        </td>
                        <td class="text-center">{{ entry.schedule_number || '-' }}</td>
                        <td class="text-center">{{ entry.voucher_number || '-' }}</td>
                        <td class="text-left">{{ entry.payee_name || 'N/A' }}</td>
                        <td class="text-left purpose-cell">{{ entry.description || 'N/A' }}</td>
                        <td class="amount-cell">{{ formatCurrencyAmount(entry.amount) }}</td>
                        <td class="amount-cell">{{ formatCurrencyAmount(entry.running_balance) }}</td>
                        <td class="text-center">{{ formatShortDate(entry.payment_date) }}</td>
                    </tr>

                    <!-- Empty Rows -->
                    <tr v-for="n in emptyRows" :key="'empty-' + n" class="empty-row">
                        <td></td>
                        <td></td>
                        <td></td>
                        <td></td>
                        <td></td>
                        <td></td>
                        <td></td>
                        <td></td>
                        <td></td>
                        <td></td>
                    </tr>

                    <!-- Total Row -->
                    <tr class="total-row">
                        <td colspan="7" class="text-right font-bold">TOTAL</td>
                        <td class="amount-cell font-bold">{{ formatCurrencyAmount(totalAmount) }}</td>
                        <td class="amount-cell font-bold">{{ formatCurrencyAmount(closingBalance) }}</td>
                        <td class="text-center">-</td>
                    </tr>
                </tbody>
            </table>

            <!-- Print Footer -->
            <div class="print-footer">
                <span>Page 1</span>
                <div class="text-right uppercase italic">
                    PREPARED BY EXPENDITURE AND CONTROL DEPARTMENT<br />
                    OFFICE OF THE ACCOUNTANT-GENERAL, BENIN CITY
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { computed, onUnmounted, ref, watch } from 'vue';

const props = defineProps({
    entries: {
        type: Array,
        default: () => [],
    },
    summary: {
        type: Object,
        default: () => ({}),
    },
    month_name: {
        type: String,
        default: '',
    },
    year: {
        type: Number,
        default: new Date().getFullYear(),
    },
    month: {
        type: Number,
        default: new Date().getMonth() + 1,
    },
    mdas: {
        type: Array,
        default: () => [],
    },
    filters: {
        type: Object,
        default: () => ({}),
    },
    visible: {
        type: Boolean,
        default: false,
    },
});

const emit = defineEmits(['close']);

const currentDate = ref(
    new Date().toLocaleDateString('en-GB', {
        day: '2-digit',
        month: 'short',
        year: 'numeric',
    }),
);

// Format currency like the cashbook (Naira.Kobo)
const formatCurrencyAmount = (value) => {
    if (value === null || value === undefined || value === '') return '';
    const num = parseFloat(value) || 0;
    const parts = num.toFixed(2).split('.');
    return new Intl.NumberFormat('en-NG').format(parts[0]) + '.' + parts[1];
};

// Format short date like the cashbook (DD-MMM-YY)
const formatShortDate = (date) => {
    if (!date) return '';
    try {
        const d = new Date(date);
        const day = d.getDate().toString().padStart(2, '0');
        const monthNames = ['JAN', 'FEB', 'MAR', 'APR', 'MAY', 'JUN', 'JUL', 'AUG', 'SEP', 'OCT', 'NOV', 'DEC'];
        const month = monthNames[d.getMonth()];
        const year = d.getFullYear().toString().slice(-2);
        return `${day}-${month}-${year}`;
    } catch (error) {
        return '';
    }
};

// Format full date
const formatDate = (date) => {
    if (!date) return '';
    try {
        const d = new Date(date);
        return d.toLocaleDateString('en-GB', {
            day: '2-digit',
            month: 'short',
            year: 'numeric',
        });
    } catch (error) {
        return '';
    }
};

// Computed values
const openingBalance = computed(() => {
    return props.summary?.opening_balance || 0;
});

const totalAmount = computed(() => {
    return props.entries.reduce((sum, entry) => sum + (entry.amount || 0), 0);
});

const closingBalance = computed(() => {
    return props.summary?.closing_balance || 0;
});

const mdaName = computed(() => {
    if (props.filters?.mda_id) {
        const mda = props.mdas.find(m => m.id === props.filters.mda_id);
        return mda ? mda.name : '';
    }
    return 'ALL MINISTRIES';
});

const monthName = computed(() => {
    return props.month_name || '';
});

const year = computed(() => {
    return props.year || '';
});

const startDate = computed(() => {
    return new Date(props.year, props.month - 1, 1);
});

// Calculate empty rows needed (minimum 15 rows total for ledger look)
const emptyRows = computed(() => {
    const minRows = 15;
    const dataRows = props.entries.length + 1; // +1 for opening balance
    return Math.max(0, minRows - dataRows);
});

// Handle print
const handlePrint = () => {
    const printWindow = window.open('', '_blank');

    printWindow.document.write(`
        <!DOCTYPE html>
        <html>
        <head>
            <title>Capital Expenditure Ledger - ${monthName.value} ${year.value}</title>
            <style>
                @page {
                    size: A4 landscape;
                    margin: 10mm;
                }
                
                body {
                    font-family: Arial, sans-serif;
                    font-size: 10px;
                    margin: 0;
                    padding: 20px;
                    -webkit-print-color-adjust: exact !important;
                    color-adjust: exact !important;
                }
                
                .print-ledger-container {
                    width: 100%;
                    min-width: 1000px;
                }
                
                ${document.querySelector('#print-styles')?.innerHTML || ''}
            </style>
        </head>
        <body>
            <div class="print-ledger-container">
                ${document.querySelector('.print-ledger')?.outerHTML || ''}
            </div>
            
            <script>
                window.onload = function() {
                    window.print();
                    setTimeout(() => {
                        window.close();
                    }, 100);
                };
            <\\/script>
        </body>
        </html>
    `);

    printWindow.document.close();
    emit('close');
};

// Watch for visibility changes
watch(
    () => props.visible,
    (newVal) => {
        if (newVal) {
            // Add print-specific styles to document
            if (!document.querySelector('#print-styles')) {
                const style = document.createElement('style');
                style.id = 'print-styles';
                style.textContent = `
                    .print-ledger-container {
                        display: none;
                    }
                    
                    @media print {
                        .print-ledger-container {
                            display: block !important;
                        }
                        
                        body * {
                            visibility: hidden;
                        }
                        
                        .print-ledger,
                        .print-ledger * {
                            visibility: visible;
                        }
                        
                        .print-ledger {
                            position: absolute;
                            left: 0;
                            top: 0;
                            width: 100%;
                        }
                    }
                `;
                document.head.appendChild(style);
            }

            // Trigger print after a short delay
            setTimeout(() => {
                handlePrint();
            }, 100);
        }
    },
);

// Clean up on unmount
onUnmounted(() => {
    const style = document.querySelector('#print-styles');
    if (style) {
        style.remove();
    }
});
</script>

<style scoped>
.print-ledger-container {
    display: none;
}

@media print {
    .print-ledger-container {
        display: block !important;
        position: fixed;
        top: 0;
        left: 0;
        width: 100vw;
        height: 100vh;
        background: white;
        z-index: 9999;
        padding: 20px;
    }

    .print-ledger {
        width: 100%;
        height: 100%;
        overflow: visible;
    }
}

.print-ledger {
    width: 100%;
    font-family: Arial, sans-serif;
}

.print-header {
    text-align: center;
    margin-bottom: 15px;
    border-bottom: 2px solid #000;
    padding-bottom: 10px;
}

.print-title {
    font-size: 16px;
    font-weight: bold;
    margin: 5px 0;
    text-transform: uppercase;
}

.print-subtitle {
    font-size: 12px;
    margin: 3px 0;
}

.print-info {
    font-size: 10px;
    margin: 5px 0;
    display: flex;
    justify-content: space-between;
}

.ledger-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 9px;
    table-layout: fixed;
    border: 1px solid #000;
    margin-bottom: 20px;
}

.ledger-table th,
.ledger-table td {
    border: 1px solid #000;
    padding: 3px 5px;
    vertical-align: middle;
    text-align: center;
    height: 25px;
    overflow: hidden;
}

.ledger-table th {
    background-color: #f0f0f0 !important;
    font-weight: bold;
    -webkit-print-color-adjust: exact;
    print-color-adjust: exact;
    text-transform: uppercase;
    font-size: 8px;
}

.amount-cell {
    text-align: right;
    font-family: 'Courier New', monospace;
    font-weight: bold;
    padding-right: 8px;
}

.purpose-cell {
    text-align: left;
    font-size: 8px;
    word-wrap: break-word;
    overflow-wrap: break-word;
    white-space: normal;
    line-height: 1.2;
}

.text-left {
    text-align: left;
}

.text-right {
    text-align: right;
}

.text-center {
    text-align: center;
}

.font-bold {
    font-weight: bold;
}

.uppercase {
    text-transform: uppercase;
}

.italic {
    font-style: italic;
}

.opening-balance-row {
    background-color: #e3f2fd !important;
    -webkit-print-color-adjust: exact;
    print-color-adjust: exact;
}

.total-row {
    background-color: #fff3e0 !important;
    -webkit-print-color-adjust: exact;
    print-color-adjust: exact;
}

.empty-row {
    height: 25px;
}

.data-row:hover {
    background-color: #f5f5f5 !important;
}

.pay-point-badge {
    display: inline-block;
    padding: 1px 8px;
    border-radius: 4px;
    font-weight: bold;
    font-size: 8px;
    background-color: #4caf50;
    color: white;
}

/* Column widths */
.date-col {
    width: 8%;
}
.head-code-col {
    width: 10%;
}
.pay-point-col {
    width: 8%;
}
.sch-col {
    width: 6%;
}
.pv-col {
    width: 8%;
}
.payee-col {
    width: 12%;
}
.purpose-col {
    width: 16%;
}
.amount-col {
    width: 10%;
}
.balance-col {
    width: 10%;
}
.payment-date-col {
    width: 8%;
}

.print-footer {
    margin-top: 15px;
    padding-top: 10px;
    border-top: 1px solid #000;
    font-size: 9px;
    display: flex;
    justify-content: space-between;
}

/* Ensure no page breaks inside table rows */
tr {
    page-break-inside: avoid;
    page-break-after: auto;
}
</style>