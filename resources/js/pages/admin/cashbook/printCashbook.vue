<template>
    <div class="print-cashbook-container" v-if="visible">
        <div class="print-cashbook">
            <!-- Print Header -->
            <div class="print-header">
                <div class="print-title">
                    MANAGEMENT ACCOUNTS SECTION - BENIN CITY
                </div>
                <div class="print-subtitle">A/CS GEN. 18 (REVISED)</div>
                <h2 class="print-title">
                    TREASURY CASH BOOK FOR THE MONTH OF
                    {{ cashbook.month_name || 'Unknown' }},
                    {{ cashbook.year || '' }}
                </h2>
                <div class="print-subtitle font-bold">
                    {{ cashbook.title ||
                        cashbook.bank_account?.title ||
                        'N/A' }}:
                    {{
                        cashbook.account_number ||
                        cashbook.bank_account?.account_number ||
                        'N/A'
                    }}
                </div>
                <div class="print-subtitle uppercase">
                    {{
                        cashbook.bank_name ||
                        cashbook.bank_account?.bank_name ||
                        'N/A'
                    }}
                </div>
                <div class="print-subtitle">KING'S SQUARE, BENIN CITY</div>
                <div class="print-info">
                    <span
                        >Period: {{ formatDate(cashbook.start_date) }} to
                        {{ formatDate(cashbook.end_date) }}</span
                    >
                    <span>Status: {{ cashbook.status || 'N/A' }}</span>
                    <span>Generated: {{ currentDate }}</span>
                </div>
            </div>

            <!-- Combined Cashbook Table -->
            <table class="combined-table">
                <thead>
                    <tr>
                        <!-- Debit Side Headers -->
                        <th colspan="8" class="debit-header">
                            DEBIT SIDE (RECEIPTS)
                        </th>
                        <!-- Credit Side Headers -->
                        <th colspan="9" class="credit-header">
                            CREDIT SIDE (PAYMENTS)
                        </th>
                    </tr>
                    <tr>
                        <!-- Debit Side Column Headers -->
                        <th rowspan="2" class="date-col">Date</th>
                        <th rowspan="2" class="cb-col">CB S/N</th>
                        <th rowspan="2" class="payer-col">
                            From Whom Received
                        </th>
                        <th colspan="2" class="class-col">Classification</th>
                        <th rowspan="2" class="receipt-col">
                            Treasury Receipt No.
                        </th>
                        <th rowspan="2" class="amount-col">Receipts (â‚¦)</th>
                        <th rowspan="2" class="bank-col">Bank (â‚¦)</th>

                        <!-- Credit Side Column Headers -->
                        <th rowspan="2" class="date-col">Date</th>
                        <th rowspan="2" class="cb-col">CB S/N</th>
                        <th rowspan="2" class="dept-col">Dept No.</th>
                        <th rowspan="2" class="payee-col">To Whom Paid</th>
                        <th colspan="2" class="class-col">Classification</th>
                        <th rowspan="2" class="cheque-col">Cheque No.</th>
                        <th rowspan="2" class="amount-col">Payments (â‚¦)</th>
                        <th rowspan="2" class="bank-col">Bank (â‚¦)</th>
                    </tr>
                    <tr>
                        <!-- Debit Side Sub-headers -->
                        <th class="title-col">Title</th>
                        <th class="num-col">Number</th>

                        <!-- Credit Side Sub-headers -->
                        <th class="title-col">Title</th>
                        <th class="num-col">Number</th>
                    </tr>
                </thead>
                <tbody>
                    <!-- Generate rows dynamically -->
                    <tr
                        v-for="(row, index) in printRows"
                        :key="index"
                        :class="row.rowClass"
                    >
                        <!-- Debit Side Cells -->
                        <td class="date-col text-center">
                            {{ row.debitDate }}
                        </td>
                        <td class="cb-col text-center">{{ row.debitCbSn }}</td>
                        <td class="payer-col wrap-text">
                            {{ row.debitPayer }}
                        </td>
                        <td class="title-col wrap-text">
                            {{ row.debitTitle }}
                        </td>
                        <td class="num-col wrap-text">{{ row.debitNumber }}</td>
                        <td class="receipt-col wrap-text">
                            {{ row.debitReceiptNo }}
                        </td>
                        <td class="amount-col amount-cell">
                            {{ row.debitAmount }}
                        </td>
                        <td class="bank-col amount-cell">
                            {{ row.debitBank }}
                        </td>

                        <!-- Credit Side Cells -->
                        <td class="date-col text-center">
                            {{ row.creditDate }}
                        </td>
                        <td class="cb-col text-center">{{ row.creditCbSn }}</td>
                        <td class="dept-col wrap-text">{{ row.creditDept }}</td>
                        <td class="payee-col wrap-text">
                            {{ row.creditPayee }}
                        </td>
                        <td class="title-col wrap-text">
                            {{ row.creditTitle }}
                        </td>
                        <td class="num-col wrap-text">
                            {{ row.creditNumber }}
                        </td>
                        <td class="cheque-col wrap-text">
                            {{ row.creditCheque }}
                        </td>
                        <td class="amount-col amount-cell">
                            {{ row.creditAmount }}
                        </td>
                        <td class="bank-col amount-cell">
                            {{ row.creditBank }}
                        </td>
                    </tr>
                </tbody>
                <tfoot>
                    <tr class="font-bold">
                        <!-- Debit Side Totals -->
                        <td colspan="6" class="text-right">TOTAL</td>
                        <td class="amount-cell">
                            {{ formatMergedAmount(totalDebitSide) }}
                        </td>
                        <td class="amount-cell">
                            {{ formatMergedAmount(totalDebitSide) }}
                        </td>

                        <!-- Credit Side Totals -->
                        <td colspan="7" class="text-right">TOTAL</td>
                        <td class="amount-cell">
                            {{ formatMergedAmount(totalDebitSide) }}
                        </td>
                        <td class="amount-cell">
                            {{ formatMergedAmount(totalDebitSide) }}
                        </td>
                    </tr>
                </tfoot>
            </table>

            <!-- Print Footer -->
            <div class="print-footer">
                <span>Page 1</span>
                <div class="text-right uppercase italic">
                    PREPARED BY MANAGEMENT ACCOUNTS SECTION<br />
                    OFFICE OF THE ACCOUNTANT-GENERAL, BENIN CITY
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { computed, onUnmounted, ref, watch } from 'vue';

const props = defineProps({
    cashbook: {
        type: Object,
        default: () => ({
            month_name: '',
            year: '',
            opening_balance: 0,
            start_date: '',
            end_date: '',
            account_number: '',
            bank_name: '',
            bank_account: {},
            status: '',
        }),
    },
    receipts: {
        type: Array,
        default: () => [],
    },
    payments: {
        type: Array,
        default: () => [],
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

// Helper to format currency
const formatCurrency = (val) => {
    const amount = parseFloat(val) || 0;
    const parts = amount.toFixed(2).split('.');
    return {
        naira: new Intl.NumberFormat('en-NG').format(parts[0]),
        kobo: parts[1],
        full: new Intl.NumberFormat('en-NG', {
            style: 'currency',
            currency: 'NGN',
            minimumFractionDigits: 2,
            maximumFractionDigits: 2,
        }).format(amount),
    };
};

// Format amount for display
const formatMergedAmount = (amount) => {
    if (!amount && amount !== 0) return '';
    const formatted = formatCurrency(amount);
    return `${formatted.naira}.${formatted.kobo}`;
};

// Format date
const formatDate = (date) => {
    if (!date) return '';
    try {
        const d = new Date(date);
        return d
            .toLocaleDateString('en-GB', {
                day: '2-digit',
                month: 'short',
                year: 'numeric',
            })
            .replace(/\//g, '-');
    } catch (error) {
        return '';
    }
};

// Format date with 2-digit year (31-JAN-25)
const formatShortDate = (date) => {
    if (!date) return '';
    try {
        const d = new Date(date);
        const day = d.getDate().toString().padStart(2, '0');
        const monthNames = [
            'JAN',
            'FEB',
            'MAR',
            'APR',
            'MAY',
            'JUN',
            'JUL',
            'AUG',
            'SEP',
            'OCT',
            'NOV',
            'DEC',
        ];
        const month = monthNames[d.getMonth()];
        const year = d.getFullYear().toString().slice(-2);
        return `${day}-${month}-${year}`;
    } catch (error) {
        return '';
    }
};

// Calculations
const openingBalance = computed(() => {
    const balance = parseFloat(props.cashbook?.opening_balance) || 0;
    return isNaN(balance) ? 0 : balance;
});

const totalReceipts = computed(() => {
    if (!Array.isArray(props.receipts)) return 0;
    return props.receipts.reduce((sum, r) => {
        const amount = parseFloat(r?.amount) || 0;
        return sum + amount;
    }, 0);
});

const totalPayments = computed(() => {
    if (!Array.isArray(props.payments)) return 0;
    return props.payments.reduce((sum, p) => {
        const amount = parseFloat(p?.amount) || 0;
        return sum + amount;
    }, 0);
});

const totalDebitSide = computed(() => {
    return openingBalance.value + totalReceipts.value;
});

const balanceCD = computed(() => {
    return totalDebitSide.value - totalPayments.value;
});

// Calculate bank balances
const receiptBankBalances = computed(() => {
    const balances = [];
    let runningBalance = openingBalance.value;

    // Opening Balance row
    balances.push(formatMergedAmount(openingBalance.value));

    // Each receipt row
    props.receipts.forEach((receipt) => {
        const amount = parseFloat(receipt.amount) || 0;
        runningBalance += amount;
        balances.push(formatMergedAmount(runningBalance));
    });

    // Empty rows
    const emptyRows = rowCount.value - props.receipts.length - 2;
    for (let i = 0; i < emptyRows; i++) {
        balances.push('');
    }

    // BAL b/d row
    balances.push(formatMergedAmount(balanceCD.value));

    return balances;
});

const paymentBankBalances = computed(() => {
    const balances = [];
    let runningBalance = totalDebitSide.value;

    // Each payment row
    props.payments.forEach((payment) => {
        const amount = parseFloat(payment.amount) || 0;
        runningBalance -= amount;
        balances.push(formatMergedAmount(runningBalance));
    });

    // Empty rows
    const emptyRows = rowCount.value - props.payments.length - 2;
    for (let i = 0; i < emptyRows; i++) {
        balances.push('');
    }

    // BAL c/d row
    balances.push(formatMergedAmount(balanceCD.value));

    return balances;
});

const rowCount = computed(() => {
    const minRows = 15;
    const receiptsRows = props.receipts?.length || 0;
    const paymentsRows = props.payments?.length || 0;
    return Math.max(receiptsRows + 2, paymentsRows + 2, minRows);
});

// Generate print rows
const printRows = computed(() => {
    const rows = [];
    const maxRows = rowCount.value;

    for (let i = 0; i < maxRows; i++) {
        let debitDate = '';
        let debitCbSn = '';
        let debitPayer = '';
        let debitTitle = '';
        let debitNumber = '';
        let debitReceiptNo = '';
        let debitAmount = '';
        let debitBank = '';

        let creditDate = '';
        let creditCbSn = '';
        let creditDept = '';
        let creditPayee = '';
        let creditTitle = '';
        let creditNumber = '';
        let creditCheque = '';
        let creditAmount = '';
        let creditBank = '';

        let rowClass = '';

        // Debit Side Logic
        if (i === 0) {
            // Opening Balance
            debitDate = formatShortDate(props.cashbook.start_date);
            debitCbSn = '1';
            debitPayer = 'BAL B/F';
            debitTitle = 'Opening Balance';
            debitAmount = formatMergedAmount(openingBalance.value);
            debitBank = receiptBankBalances.value[0];
            rowClass = 'bg-blue-50';
        } else if (i <= props.receipts.length) {
            // Receipts
            const receipt = props.receipts[i - 1];
            if (receipt) {
                debitDate = formatShortDate(receipt.transaction_date);
                debitCbSn = (i + 1).toString();
                debitPayer = receipt.payer_name || 'N/A';
                debitTitle = receipt.classification_title || '';
                debitNumber = receipt.sub_category || '';
                debitReceiptNo = receipt.receipt_no || '';
                debitAmount = formatMergedAmount(receipt.amount);
                debitBank = receiptBankBalances.value[i];
            }
        } else if (i === props.receipts.length + 1) {
            // BAL b/d
            debitDate = formatShortDate(props.cashbook.end_date);
            debitCbSn = (props.receipts.length + 2).toString();
            debitPayer = 'BAL b/d';
            debitTitle = 'Balance Brought Down';
            debitAmount = formatMergedAmount(balanceCD.value);
            debitBank =
                receiptBankBalances.value[receiptBankBalances.value.length - 1];
            rowClass = 'bg-blue-50 font-bold';
        }

        // Credit Side Logic
        if (i < props.payments.length) {
            // Payments
            const payment = props.payments[i];
            if (payment) {
                creditDate = formatShortDate(payment.transaction_date);
                creditCbSn = (props.receipts.length + i + 3).toString();
                creditDept = payment.department_number || '';
                creditPayee = payment.payee_name || 'N/A';
                creditTitle = payment.classification_title || '';
                creditNumber = payment.sub_category || '';
                creditCheque = payment.cheque_no || '';
                creditAmount = formatMergedAmount(payment.amount);
                creditBank = paymentBankBalances.value[i];
            }
        } else if (i === props.payments.length) {
            // BAL c/d
            creditDate = formatShortDate(props.cashbook.end_date);
            creditCbSn = (
                props.receipts.length +
                props.payments.length +
                3
            ).toString();
            creditPayee = 'BAL c/d';
            creditTitle = 'Balance Carried Down';
            creditAmount = formatMergedAmount(balanceCD.value);
            creditBank =
                paymentBankBalances.value[paymentBankBalances.value.length - 1];
            rowClass = 'bg-red-50 font-bold';
        }

        rows.push({
            debitDate,
            debitCbSn,
            debitPayer,
            debitTitle,
            debitNumber,
            debitReceiptNo,
            debitAmount,
            debitBank,
            creditDate,
            creditCbSn,
            creditDept,
            creditPayee,
            creditTitle,
            creditNumber,
            creditCheque,
            creditAmount,
            creditBank,
            rowClass,
        });
    }

    return rows;
});

// Handle print
const handlePrint = () => {
    const printWindow = window.open('', '_blank');

    printWindow.document.write(`
        <!DOCTYPE html>
        <html>
        <head>
            <title>Treasury Cash Book - ${props.cashbook.month_name} ${props.cashbook.year}</title>
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
                
                .print-container {
                    width: 100%;
                    min-width: 1000px;
                }
                
                ${document.querySelector('#print-styles')?.innerHTML || ''}
            </style>
        </head>
        <body>
            <div class="print-container">
                ${document.querySelector('.print-cashbook')?.outerHTML || ''}
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
                @page {
                    size: A4 landscape;
                    margin: 10mm;
                }
                
                .print-cashbook-container {
                    display: none;
                }
                
                @media print {
                    .print-cashbook-container {
                        display: block !important;
                    }
                    
                    body * {
                        visibility: hidden;
                    }
                    
                    .print-cashbook,
                    .print-cashbook * {
                        visibility: visible;
                    }
                    
                    .print-cashbook {
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
.print-cashbook-container {
    display: none;
}

@media print {
    .print-cashbook-container {
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

    .print-cashbook {
        width: 100%;
        height: 100%;
        overflow: visible;
    }
}

.print-cashbook {
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

.combined-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 9px;
    table-layout: fixed;
    border: 1px solid #000;
    margin-bottom: 20px;
}

.combined-table th,
.combined-table td {
    border: 1px solid #000;
    padding: 3px 5px;
    vertical-align: middle;
    text-align: center;
    height: 25px;
    overflow: hidden;
}

.combined-table th {
    background-color: #f0f0f0 !important;
    font-weight: bold;
    -webkit-print-color-adjust: exact;
    print-color-adjust: exact;
}

.debit-header {
    background-color: #e3f2fd !important;
}

.credit-header {
    background-color: #ffebee !important;
}

.amount-cell {
    text-align: right;
    font-family: 'Courier New', monospace;
    font-weight: bold;
    padding-right: 8px;
}

.wrap-text {
    word-wrap: break-word;
    overflow-wrap: break-word;
    white-space: normal;
    line-height: 1.2;
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

.bg-blue-50 {
    background-color: rgba(59, 130, 246, 0.1) !important;
    -webkit-print-color-adjust: exact;
    print-color-adjust: exact;
}

.bg-red-50 {
    background-color: rgba(239, 68, 68, 0.1) !important;
    -webkit-print-color-adjust: exact;
    print-color-adjust: exact;
}

/* Column widths */
.date-col {
    width: 70px;
}
.cb-col {
    width: 60px;
}
.payer-col {
    width: 150px;
}
.title-col {
    width: 120px;
}
.num-col {
    width: 80px;
}
.receipt-col {
    width: 100px;
}
.amount-col {
    width: 120px;
}
.bank-col {
    width: 120px;
}
.dept-col {
    width: 70px;
}
.payee-col {
    width: 150px;
}
.cheque-col {
    width: 100px;
}

.print-footer {
    margin-top: 15px;
    padding-top: 10px;
    border-top: 1px solid #000;
    font-size: 9px;
    display: flex;
    justify-content: space-between;
}

.uppercase {
    text-transform: uppercase;
}

.italic {
    font-style: italic;
}
</style>
