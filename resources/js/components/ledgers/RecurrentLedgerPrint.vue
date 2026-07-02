<template>
    <div class="print-ledger-container" v-if="visible">
        <div class="print-ledger">
            <!-- Print Header -->
            <div class="print-header">
                <div class="print-title">EXPENDITURE AND CONTROL DEPARTMENT</div>
                <div class="print-subtitle">TREASURY HOUSE</div>
                <div class="print-subtitle">SECRETARIAT COMPLEX</div>
                <h2 class="print-title">RECURRENT EXPENDITURES</h2>
                <div class="print-subtitle font-bold uppercase">
                    {{ mdaName || 'ALL MINISTRIES' }}
                </div>
                <div class="print-info">
                    <span>Period: {{ monthName }} {{ year }}</span>
                    <span>Generated: {{ currentDate }}</span>
                </div>
            </div>

            <!-- Ledger Table -->
            <table class="print-ledger-table" id="print-table">
                <thead>
                    <tr>
                        <th class="date-col">DATE</th>
                        <th class="payee-col">PAYEE</th>
                        <th class="pay-point-col">PAY POINT</th>
                        <th class="sch-col">SCH NO</th>
                        <th class="pv-col">PV NO</th>
                        <th class="purpose-col">PURPOSE OF PAYMENT</th>
                        <th class="amount-col">AMOUNT</th>
                        <th class="ec21-col">21</th>
                        <th class="ec220101-col">220201</th>
                        <th class="ec220102-col">220202</th>
                        <th class="ec220103-col">220203</th>
                        <th class="ec220104-col">220204</th>
                        <th class="ec220105-col">220205</th>
                        <th class="ec220106-col">220206</th>
                        <th class="ec220107-col">220207</th>
                        <th class="ec220108-col">220208</th>
                        <th class="ec220109-col">220209</th>
                        <th class="ec220110-col">220210</th>
                        <th class="oh-col">2202 OH</th>
                    </tr>
                    <!-- Sub-headers for Economy Codes -->
                    <tr>
                        <th colspan="7" class="sub-header"></th>
                        <th class="sub-header">Personnel and Other Allowances</th>
                        <th class="sub-header">Transport and Travelling</th>
                        <th class="sub-header">Utilities General</th>
                        <th class="sub-header">Materials and Supply</th>
                        <th class="sub-header">Maintenance Service General</th>
                        <th class="sub-header">Training General</th>
                        <th class="sub-header">Other Service General</th>
                        <th class="sub-header">Consulting and Professional Service</th>
                        <th class="sub-header">Fuel and Lubricant</th>
                        <th class="sub-header">Financial Expenses Charges</th>
                        <th class="sub-header">Miscellaneous General</th>
                        <th class="sub-header">Overhead</th>
                    </tr>
                </thead>
                <tbody>
                    <!-- Opening Balance Row -->
                    <tr class="opening-balance-row">
                        <td class="text-center">{{ formatShortDate(startDate) }}</td>
                        <td class="text-left">BALANCE B/F</td>
                        <td class="text-center">-</td>
                        <td class="text-center">-</td>
                        <td class="text-center">-</td>
                        <td class="text-left">Opening Balance</td>
                        <td class="amount-cell"></td>
                        <td class="amount-cell"></td>
                        <td class="amount-cell"></td>
                        <td class="amount-cell"></td>
                        <td class="amount-cell"></td>
                        <td class="amount-cell"></td>
                        <td class="amount-cell"></td>
                        <td class="amount-cell"></td>
                        <td class="amount-cell"></td>
                        <td class="amount-cell"></td>
                        <td class="amount-cell"></td>
                        <td class="amount-cell"></td>
                        <td class="amount-cell">{{ formatCurrencyAmount(openingBalance) }}</td>
                    </tr>

                    <!-- Data Rows -->
                    <tr v-for="(entry, index) in entries" :key="index" class="data-row">
                        <td class="text-center">{{ formatShortDate(entry.transaction_date) }}</td>
                        <td class="text-left">{{ entry.payee_name || 'N/A' }}</td>
                        <td class="text-center">
                            <span class="pay-point-badge">{{ entry.pay_point || 'MAS' }}</span>
                        </td>
                        <td class="text-center">{{ entry.schedule_number || '-' }}</td>
                        <td class="text-center">{{ entry.voucher_number || '-' }}</td>
                        <td class="text-left purpose-cell">{{ entry.description || 'N/A' }}</td>
                        <td class="amount-cell">{{ formatCurrencyAmount(entry.amount) }}</td>
                        <td class="amount-cell">{{ formatCurrencyAmount(entry.c21) }}</td>
                        <td class="amount-cell">{{ formatCurrencyAmount(entry.c220101) }}</td>
                        <td class="amount-cell">{{ formatCurrencyAmount(entry.c220102) }}</td>
                        <td class="amount-cell">{{ formatCurrencyAmount(entry.c220103) }}</td>
                        <td class="amount-cell">{{ formatCurrencyAmount(entry.c220104) }}</td>
                        <td class="amount-cell">{{ formatCurrencyAmount(entry.c220105) }}</td>
                        <td class="amount-cell">{{ formatCurrencyAmount(entry.c220106) }}</td>
                        <td class="amount-cell">{{ formatCurrencyAmount(entry.c220107) }}</td>
                        <td class="amount-cell">{{ formatCurrencyAmount(entry.c220108) }}</td>
                        <td class="amount-cell">{{ formatCurrencyAmount(entry.c220109) }}</td>
                        <td class="amount-cell">{{ formatCurrencyAmount(entry.c220110) }}</td>
                        <td class="amount-cell">{{ formatCurrencyAmount(entry.c2202ch) }}</td>
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
                        <td colspan="6" class="text-right font-bold">TOTAL</td>
                        <td class="amount-cell font-bold">{{ formatCurrencyAmount(totalAmount) }}</td>
                        <td class="amount-cell font-bold">{{ formatCurrencyAmount(totalC21) }}</td>
                        <td class="amount-cell font-bold">{{ formatCurrencyAmount(totalC220101) }}</td>
                        <td class="amount-cell font-bold">{{ formatCurrencyAmount(totalC220102) }}</td>
                        <td class="amount-cell font-bold">{{ formatCurrencyAmount(totalC220103) }}</td>
                        <td class="amount-cell font-bold">{{ formatCurrencyAmount(totalC220104) }}</td>
                        <td class="amount-cell font-bold">{{ formatCurrencyAmount(totalC220105) }}</td>
                        <td class="amount-cell font-bold">{{ formatCurrencyAmount(totalC220106) }}</td>
                        <td class="amount-cell font-bold">{{ formatCurrencyAmount(totalC220107) }}</td>
                        <td class="amount-cell font-bold">{{ formatCurrencyAmount(totalC220108) }}</td>
                        <td class="amount-cell font-bold">{{ formatCurrencyAmount(totalC220109) }}</td>
                        <td class="amount-cell font-bold">{{ formatCurrencyAmount(totalC220110) }}</td>
                        <td class="amount-cell font-bold">{{ formatCurrencyAmount(totalC2202ch) }}</td>
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

// Computed values
const openingBalance = computed(() => {
    return props.summary?.opening_balance || 0;
});

const totalAmount = computed(() => {
    return props.entries.reduce((sum, entry) => sum + (entry.amount || 0), 0);
});

const totalC21 = computed(() => {
    return props.entries.reduce((sum, entry) => sum + (entry.c21 || 0), 0);
});

const totalC220101 = computed(() => {
    return props.entries.reduce((sum, entry) => sum + (entry.c220101 || 0), 0);
});

const totalC220102 = computed(() => {
    return props.entries.reduce((sum, entry) => sum + (entry.c220102 || 0), 0);
});

const totalC220103 = computed(() => {
    return props.entries.reduce((sum, entry) => sum + (entry.c220103 || 0), 0);
});

const totalC220104 = computed(() => {
    return props.entries.reduce((sum, entry) => sum + (entry.c220104 || 0), 0);
});

const totalC220105 = computed(() => {
    return props.entries.reduce((sum, entry) => sum + (entry.c220105 || 0), 0);
});

const totalC220106 = computed(() => {
    return props.entries.reduce((sum, entry) => sum + (entry.c220106 || 0), 0);
});

const totalC220107 = computed(() => {
    return props.entries.reduce((sum, entry) => sum + (entry.c220107 || 0), 0);
});

const totalC220108 = computed(() => {
    return props.entries.reduce((sum, entry) => sum + (entry.c220108 || 0), 0);
});

const totalC220109 = computed(() => {
    return props.entries.reduce((sum, entry) => sum + (entry.c220109 || 0), 0);
});

const totalC220110 = computed(() => {
    return props.entries.reduce((sum, entry) => sum + (entry.c220110 || 0), 0);
});

const totalC2202ch = computed(() => {
    return props.entries.reduce((sum, entry) => sum + (entry.c2202ch || 0), 0);
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

// Handle print
const handlePrint = () => {
    const printWindow = window.open('', '_blank', 'width=1200,height=800,scrollbars=yes');
    if (!printWindow) {
        alert('Please allow popups for this site to print.');
        emit('close');
        return;
    }

    const contentHTML = document.querySelector('.print-ledger')?.outerHTML || '';

    printWindow.document.write(`
        <!DOCTYPE html>
        <html>
        <head>
            <title>Recurrent Expenditure Ledger - ${monthName.value} ${year.value}</title>
            <meta charset="UTF-8">
            <style>
                /* Reset */
                * { margin: 0; padding: 0; box-sizing: border-box; }
                body {
                    font-family: Arial, sans-serif;
                    font-size: 9px;
                    padding: 15px;
                    background: white;
                    -webkit-print-color-adjust: exact !important;
                    print-color-adjust: exact !important;
                }
                
                .print-ledger-container {
                    width: 100%;
                    min-width: 1200px;
                }
                
                /* Print Header */
                .print-header {
                    text-align: center;
                    margin-bottom: 12px;
                    border-bottom: 2px solid #000;
                    padding-bottom: 8px;
                }
                .print-title {
                    font-size: 14px;
                    font-weight: bold;
                    margin: 4px 0;
                    text-transform: uppercase;
                }
                .print-subtitle {
                    font-size: 11px;
                    margin: 2px 0;
                }
                .print-info {
                    font-size: 9px;
                    margin: 4px 0;
                    display: flex;
                    justify-content: space-between;
                }
                
                /* Ledger Table */
                .print-ledger-table {
                    width: 100%;
                    border-collapse: collapse;
                    font-size: 8px;
                    table-layout: fixed;
                    border: 1px solid #000;
                    margin-bottom: 15px;
                }
                .print-ledger-table th,
                .print-ledger-table td {
                    border: 1px solid #000;
                    padding: 3px 4px;
                    vertical-align: middle;
                    text-align: center;
                    height: 24px;
                    overflow: hidden;
                }
                .print-ledger-table th {
                    background-color: #f0f0f0 !important;
                    font-weight: bold;
                    text-transform: uppercase;
                    font-size: 7px;
                    -webkit-print-color-adjust: exact !important;
                    print-color-adjust: exact !important;
                }
                .print-ledger-table .sub-header {
                    font-size: 6px;
                    text-transform: none;
                    font-weight: 600;
                    background-color: #e8e8e8 !important;
                }
                .print-ledger-table .amount-cell {
                    text-align: right;
                    font-family: 'Courier New', monospace;
                    font-weight: bold;
                    padding-right: 6px;
                    font-size: 7px;
                }
                .print-ledger-table .purpose-cell {
                    text-align: left;
                    font-size: 7px;
                    word-wrap: break-word;
                    overflow-wrap: break-word;
                    white-space: normal;
                    line-height: 1.2;
                }
                .print-ledger-table .text-left { text-align: left; }
                .print-ledger-table .text-right { text-align: right; }
                .print-ledger-table .text-center { text-align: center; }
                .print-ledger-table .font-bold { font-weight: bold; }
                
                .print-ledger-table .opening-balance-row {
                    background-color: #e3f2fd !important;
                    -webkit-print-color-adjust: exact !important;
                    print-color-adjust: exact !important;
                }
                .print-ledger-table .total-row {
                    background-color: #fff3e0 !important;
                    -webkit-print-color-adjust: exact !important;
                    print-color-adjust: exact !important;
                }
                .print-ledger-table .empty-row { height: 24px; }
                
                .print-ledger-table .pay-point-badge {
                    display: inline-block;
                    padding: 1px 6px;
                    border-radius: 3px;
                    font-weight: bold;
                    font-size: 6px;
                    background-color: #4caf50;
                    color: white;
                }
                
                /* Column widths */
                .print-ledger-table .date-col { width: 6%; }
                .print-ledger-table .payee-col { width: 8%; }
                .print-ledger-table .pay-point-col { width: 6%; }
                .print-ledger-table .sch-col { width: 5%; }
                .print-ledger-table .pv-col { width: 6%; }
                .print-ledger-table .purpose-col { width: 12%; }
                .print-ledger-table .amount-col { width: 6%; }
                .print-ledger-table .ec21-col { width: 5%; }
                .print-ledger-table .ec220101-col { width: 5%; }
                .print-ledger-table .ec220102-col { width: 5%; }
                .print-ledger-table .ec220103-col { width: 5%; }
                .print-ledger-table .ec220104-col { width: 5%; }
                .print-ledger-table .ec220105-col { width: 5%; }
                .print-ledger-table .ec220106-col { width: 5%; }
                .print-ledger-table .ec220107-col { width: 5%; }
                .print-ledger-table .ec220108-col { width: 5%; }
                .print-ledger-table .ec220109-col { width: 5%; }
                .print-ledger-table .ec220110-col { width: 5%; }
                .print-ledger-table .oh-col { width: 5%; }
                
                /* Print Footer */
                .print-footer {
                    margin-top: 12px;
                    padding-top: 8px;
                    border-top: 1px solid #000;
                    font-size: 8px;
                    display: flex;
                    justify-content: space-between;
                }
                .uppercase { text-transform: uppercase; }
                .italic { font-style: italic; }
                
                @page {
                    size: A4 landscape;
                    margin: 8mm;
                }
                
                @media print {
                    body { padding: 0; }
                    .print-ledger-table th { background-color: #f0f0f0 !important; }
                    .print-ledger-table .opening-balance-row { background-color: #e3f2fd !important; }
                    .print-ledger-table .total-row { background-color: #fff3e0 !important; }
                    .print-ledger-table .sub-header { background-color: #e8e8e8 !important; }
                }
            </style>
        </head>
        <body>
            ${contentHTML}
            <script>
                window.onload = function() {
                    setTimeout(function() {
                        window.print();
                        setTimeout(function() {
                            window.close();
                        }, 500);
                    }, 300);
                };
            <\/script>
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
            setTimeout(() => {
                handlePrint();
            }, 300);
        }
    },
);

// Clean up on unmount
onUnmounted(() => {
    // Cleanup if needed
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
        padding: 15px;
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

/* Print Styles */
.print-header {
    text-align: center;
    margin-bottom: 12px;
    border-bottom: 2px solid #000;
    padding-bottom: 8px;
}

.print-title {
    font-size: 14px;
    font-weight: bold;
    margin: 4px 0;
    text-transform: uppercase;
}

.print-subtitle {
    font-size: 11px;
    margin: 2px 0;
}

.print-info {
    font-size: 9px;
    margin: 4px 0;
    display: flex;
    justify-content: space-between;
}

.print-ledger-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 8px;
    table-layout: fixed;
    border: 1px solid #000;
    margin-bottom: 15px;
}

.print-ledger-table th,
.print-ledger-table td {
    border: 1px solid #000;
    padding: 3px 4px;
    vertical-align: middle;
    text-align: center;
    height: 24px;
    overflow: hidden;
}

.print-ledger-table th {
    background-color: #f0f0f0 !important;
    font-weight: bold;
    text-transform: uppercase;
    font-size: 7px;
}

.print-ledger-table .sub-header {
    font-size: 6px;
    text-transform: none;
    font-weight: 600;
    background-color: #e8e8e8 !important;
}

.print-ledger-table .amount-cell {
    text-align: right;
    font-family: 'Courier New', monospace;
    font-weight: bold;
    padding-right: 6px;
}

.print-ledger-table .purpose-cell {
    text-align: left;
    font-size: 7px;
    word-wrap: break-word;
    overflow-wrap: break-word;
    white-space: normal;
    line-height: 1.2;
}

.print-ledger-table .text-left { text-align: left; }
.print-ledger-table .text-right { text-align: right; }
.print-ledger-table .text-center { text-align: center; }
.print-ledger-table .font-bold { font-weight: bold; }

.print-ledger-table .opening-balance-row {
    background-color: #e3f2fd !important;
}

.print-ledger-table .total-row {
    background-color: #fff3e0 !important;
}

.print-ledger-table .empty-row {
    height: 24px;
}

.print-ledger-table .pay-point-badge {
    display: inline-block;
    padding: 1px 6px;
    border-radius: 3px;
    font-weight: bold;
    font-size: 6px;
    background-color: #4caf50;
    color: white;
}

/* Column widths */
.print-ledger-table .date-col { width: 6%; }
.print-ledger-table .payee-col { width: 8%; }
.print-ledger-table .pay-point-col { width: 6%; }
.print-ledger-table .sch-col { width: 5%; }
.print-ledger-table .pv-col { width: 6%; }
.print-ledger-table .purpose-col { width: 12%; }
.print-ledger-table .amount-col { width: 6%; }
.print-ledger-table .ec21-col { width: 5%; }
.print-ledger-table .ec220101-col { width: 5%; }
.print-ledger-table .ec220102-col { width: 5%; }
.print-ledger-table .ec220103-col { width: 5%; }
.print-ledger-table .ec220104-col { width: 5%; }
.print-ledger-table .ec220105-col { width: 5%; }
.print-ledger-table .ec220106-col { width: 5%; }
.print-ledger-table .ec220107-col { width: 5%; }
.print-ledger-table .ec220108-col { width: 5%; }
.print-ledger-table .ec220109-col { width: 5%; }
.print-ledger-table .ec220110-col { width: 5%; }
.print-ledger-table .oh-col { width: 5%; }

.print-footer {
    margin-top: 12px;
    padding-top: 8px;
    border-top: 1px solid #000;
    font-size: 8px;
    display: flex;
    justify-content: space-between;
}

.uppercase { text-transform: uppercase; }
.italic { font-style: italic; }
</style>