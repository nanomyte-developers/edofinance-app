<script setup>
import html2pdf from 'html2pdf.js';
import Button from 'primevue/button';
import * as XLSX from 'xlsx';

import { computed, onMounted, ref } from 'vue';

const props = defineProps({
    cashbook: {
        type: Object,
        default: () => ({}),
    },
    receipts: {
        type: Array,
        default: () => [],
    },
    payments: {
        type: Array,
        default: () => [],
    },
});

// Reactive data
const cashbookData = ref({
    month_name: '',
    year: '',
    opening_balance: 0,
    start_date: '',
    end_date: '',
    account_number: '',
    bank_name: '',
    status: '',
});

// Current date for print header
const currentDate = ref(
    new Date().toLocaleDateString('en-GB', {
        day: '2-digit',
        month: 'short',
        year: 'numeric',
    }),
);

// Helper function to get property with fallback
const getProp = (obj, prop, fallback = '') => {
    return obj?.[prop] || fallback;
};

// Computed properties
const openingBalance = computed(() => {
    return parseFloat(props.cashbook?.opening_balance) || 0;
});

const totalReceipts = computed(() => {
    if (!Array.isArray(props.receipts)) return 0;
    return props.receipts.reduce((sum, r) => {
        return sum + (parseFloat(r?.amount) || 0);
    }, 0);
});

const totalPayments = computed(() => {
    if (!Array.isArray(props.payments)) return 0;
    return props.payments.reduce((sum, p) => {
        return sum + (parseFloat(p?.amount) || 0);
    }, 0);
});

const totalDebitSide = computed(() => {
    return openingBalance.value + totalReceipts.value;
});

const balanceCD = computed(() => {
    return totalDebitSide.value - totalPayments.value;
});

// NEW: Credit Side Total = Total Payments + Balance Carried Down
const totalCreditSide = computed(() => {
    return totalPayments.value + balanceCD.value;
});

// Calculate row count for empty rows
const rowCount = computed(() => {
    const minRows = 15;
    const receiptsRows = props.receipts?.length || 0;
    const paymentsRows = props.payments?.length || 0;
    return Math.max(receiptsRows + 2, paymentsRows + 2, minRows);
});

// Format currency
const formatCurrency = (val, showKobo = true) => {
    const amount = parseFloat(val) || 0;
    if (showKobo) {
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
    } else {
        return new Intl.NumberFormat('en-NG', {
            style: 'currency',
            currency: 'NGN',
            minimumFractionDigits: 2,
            maximumFractionDigits: 2,
        }).format(amount);
    }
};

// Format merged amount (Naira.Kobo)
const formatMergedAmount = (amount) => {
    if (!amount && amount !== 0) return '';
    const formatted = formatCurrency(amount);
    return `${formatted.naira}.${formatted.kobo}`;
};

// Format short date
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

// Format full date
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

// Calculate bank balances for receipts
const receiptBankBalances = computed(() => {
    const balances = [];
    let runningBalance = openingBalance.value;

    // Opening Balance
    balances.push(formatMergedAmount(openingBalance.value));

    // Receipts
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

    // BAL b/d
    balances.push(formatMergedAmount(balanceCD.value));

    return balances;
});

// Calculate bank balances for payments
const paymentBankBalances = computed(() => {
    const balances = [];
    let runningBalance = totalDebitSide.value;

    // Payments
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

    // BAL c/d
    balances.push(formatMergedAmount(balanceCD.value));

    return balances;
});

// PDF Generation
const generatePDF = () => {
    const element = document.getElementById('cashbook-content');

    const opt = {
        margin: [0.1, 0.1, 0.1, 0.1],
        filename: `Treasury_Cash_Book_${props.cashbook.month_name}_${props.cashbook.year}.pdf`,
        image: {
            type: 'jpeg',
            quality: 1,
        },
        html2canvas: {
            scale: 3,
            useCORS: true,
            logging: false,
            letterRendering: true,
            width: element.scrollWidth,
            height: element.scrollHeight,
            backgroundColor: '#FFFFFF',
        },
        jsPDF: {
            unit: 'mm',
            format: 'a4',
            orientation: 'landscape',
            compress: false,
        },
    };

    html2pdf()
        .set(opt)
        .from(element)
        .save()
        .catch((err) => {
            console.error('PDF generation failed:', err);
            printDocument();
        });
};

// Print function
const printDocument = () => {
    window.print();
};

// Close window
const closeWindow = () => {
    window.close();
};

// Debug function to log data
const debugData = () => {
    console.log('Cashbook Data:', props.cashbook);
    console.log('First Receipt:', props.receipts[0]);
    console.log('First Payment:', props.payments[0]);
    console.log('All Receipts:', props.receipts);
    console.log('All Payments:', props.payments);
};

// Initialize data
onMounted(() => {
    if (props.cashbook) {
        cashbookData.value = { ...props.cashbook };
    }

    // Debug data
    debugData();
});

// Generate table rows
const generateTableRows = () => {
    const maxRows = rowCount.value;
    const rows = [];
    let j = 0;
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

        // Debit Side Logic
        if (i === 0) {
            // Opening Balance
            debitDate = formatShortDate(props.cashbook.start_date);
            debitCbSn = '';
            debitPayer = 'BAL B/F';
            debitTitle = 'Opening Balance';
            debitAmount = formatMergedAmount(openingBalance.value);
            debitBank = receiptBankBalances.value[0];
        } else if (i <= props.receipts.length) {
            // Receipts
            const receipt = props.receipts[i - 1];
            if (receipt) {
                debitDate = formatShortDate(receipt.transaction_date);
                debitCbSn = (i).toString();

                // Use correct property names from CashbookEntry
                debitPayer = receipt.payer_name || 'N/A';
                debitTitle =
                    receipt.category || receipt.classification_title || '';
                debitNumber = receipt.sub_category || '';
                debitReceiptNo =
                    receipt.reference_number || receipt.receipt_no || '';
                debitAmount = formatMergedAmount(receipt.amount);
                debitBank = formatMergedAmount(receipt.amount);
                // debitBank = receiptBankBalances.value[i];
            }
        } else if (i === props.receipts.length + 1) {
            // BAL b/d
            // Commented out as requested
        }

        // Credit Side Logic
        if (i < props.payments.length) {
            // Payments
            const payment = props.payments[i];
            if (payment) {
                creditDate = formatShortDate(payment.transaction_date);
                creditCbSn = (j + 1).toString();
                j++;
                // creditDept = payment.department_number || '';
                creditDept = payment.reference_number || payment.reference_number || '';

                // Use correct property names from CashbookEntry
                creditPayee = payment.payee_name || 'N/A';
                creditTitle =
                    payment.category || payment.classification_title || '';
                creditNumber = payment.sub_category || '';
                creditCheque = '';
                creditAmount = formatMergedAmount(payment.amount);
                creditBank = formatMergedAmount(payment.amount);
                // creditBank = paymentBankBalances.value[i];
            }
        } else if (i === props.payments.length) {
            // BAL c/d
            // Commented out as requested
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
            rowClass:
                i === 0 || i === props.receipts.length + 1 ? 'bg-blue-50' : '',
        });
    }

    return rows;
};


const exportHtmlTableToExcel = () => {
    // Access the HTML table element
    const table = document.getElementById('my_ledger');

    // Convert the HTML table element to a worksheet
    const worksheet = XLSX.utils.table_to_sheet(table);

    // Create a new workbook and append the worksheet
    const workbook = XLSX.utils.book_new();
    XLSX.utils.book_append_sheet(workbook, worksheet, 'Cashbook.xlsx' );

    // Trigger download
    XLSX.writeFile(workbook, "Cashbook.xlsx");
};
</script>

<template>
    <div class="cashbook-print-container">
        <div class="no-print mb-4 flex justify-end">
            <Button label="Generate PDF" icon="pi pi-file-pdf" @click="generatePDF"
                class="rounded-lg bg-green-600 px-6 py-2 font-semibold text-white shadow-lg transition duration-150 hover:bg-green-700" />
            <Button label="Print" icon="pi pi-print" @click="printDocument"
                class="ml-2 rounded-lg bg-blue-600 px-6 py-2 font-semibold text-white shadow-lg transition duration-150 hover:bg-blue-700" />
            <Button label="Close" icon="pi pi-times" @click="closeWindow"
                class="ml-2 rounded-lg bg-gray-600 px-6 py-2 font-semibold text-white shadow-lg transition duration-150 hover:bg-gray-700" />
            <Button label="Debug Data" icon="pi pi-bug" @click="debugData"
                class="ml-2 rounded-lg bg-yellow-600 px-6 py-2 font-semibold text-white shadow-lg transition duration-150 hover:bg-yellow-700" />
        </div>

        <div id="cashbook-content" class="cashbook-content">
            <div class="print-container">
                <!-- Print Header -->
                <div class="print-header">
                    <div class="print-title">
                        MANAGEMENT ACCOUNTS SECTION - BENIN CITY
                    </div>
                    <div class="print-subtitle">A/CS GEN. 18 (REVISED)</div>
                    <h2 class="print-title">
                        TREASURY CASH BOOK FOR THE MONTH OF
                        {{ props.cashbook.month_name || 'Unknown' }},
                        {{ props.cashbook.year || '' }}
                    </h2>
                    <div class="print-subtitle font-bold">
                        {{ cashbook.title ||
                        cashbook.bank_account?.title ||
                        'N/A' }}:
                        {{
                            props.cashbook.account_number ||
                            props.cashbook.bank_account?.account_number ||
                            'N/A'
                        }}
                    </div>
                    <div class="print-subtitle uppercase">
                        {{
                            props.cashbook.bank_name ||
                            props.cashbook.bank_account?.bank_name ||
                            'N/A'
                        }}
                    </div>
                    <div class="print-subtitle">KING'S SQUARE, BENIN CITY</div>
                    <div class="print-info">
                        <span>Period:
                            {{ formatDate(props.cashbook.start_date) }} to
                            {{ formatDate(props.cashbook.end_date) }}</span>
                        <span>Status: {{ props.cashbook.status || 'N/A' }}</span>
                        <span>Generated: {{ currentDate }}</span>
                    </div>
                </div>

                <!-- Combined Cashbook Table -->
                <table class="combined-table no-break" id="my_ledger">
                    <thead>
                        <tr>
                            <th colspan="8" class="debit-header">
                                DEBIT SIDE (RECEIPTS)
                            </th>
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
                            <th colspan="2" class="class-col">
                                Classification
                            </th>
                            <th rowspan="2" class="receipt-col">
                                Treasury Receipt No.
                            </th>
                            <th rowspan="2" class="amount-col">Receipts (₦)</th>
                            <th rowspan="2" class="bank-col">Bank (₦)</th>

                            <!-- Credit Side Column Headers -->
                            <th rowspan="2" class="date-col">Date</th>
                            <th rowspan="2" class="cb-col">CB S/N</th>
                            <th rowspan="2" class="dept-col">Dept No.</th>
                            <th rowspan="2" class="payee-col">To Whom Paid</th>
                            <th colspan="2" class="class-col">
                                Classification
                            </th>
                            <th rowspan="2" class="cheque-col">Cheque No.</th>
                            <th rowspan="2" class="amount-col">Payments (₦)</th>
                            <th rowspan="2" class="bank-col">Bank (₦)</th>
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
                        <tr v-for="(row, index) in generateTableRows()" :key="index" :class="row.rowClass">
                            <!-- Debit Side Cells -->
                            <td class="date-col text-center">
                                {{ row.debitDate }}
                            </td>
                            <td class="cb-col text-center">
                                {{ row.debitCbSn }}
                            </td>
                            <td class="payer-col wrap-text">
                                {{ row.debitPayer }}
                            </td>
                            <td class="title-col wrap-text">
                                {{ row.debitTitle }}
                            </td>
                            <td class="num-col wrap-text">
                                {{ row.debitNumber }}
                            </td>
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
                            <td class="cb-col text-center">
                                {{ row.creditCbSn }}
                            </td>
                            <td class="dept-col wrap-text">
                                {{ row.creditDept }}
                            </td>
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
                        <!-- Empty row for spacing -->
                        <tr class="font-bold">
                            <td colspan="8" class="text-right">&nbsp;</td>
                            <td colspan="9">&nbsp;</td>
                        </tr>

                        <!-- Totals Row -->
                        <tr class="bg-blue-100 font-bold">
                            <!-- Debit Side -->
                            <td colspan="6" class="text-right">TOTAL</td>
                            <td class="amount-cell">
                                {{ formatMergedAmount(totalDebitSide) }}
                            </td>
                            <td class="amount-cell">
                                {{ formatMergedAmount(totalDebitSide) }}
                            </td>

                            <!-- Credit Side -->
                            <td class="text-right"></td>
                            <td class="text-right"></td>
                            <td class="text-right"></td>
                            <td class="text-right"></td>
                            <td colspan="3" class="text-right"></td>
                            <td class="amount-cell">

                            </td>
                            <td class="amount-cell">

                            </td>
                        </tr>

                        <!-- Balance Brought Down Row -->
                        <tr class="font-bold">
                            <!-- Debit Side -->
                            <td class="text-right"></td>
                            <td class="text-right"></td>
                            <td class="text-right">BAL b/d</td>
                            <td colspan="3" class="text-right"></td>
                            <td class="amount-cell">
                                {{ formatMergedAmount(balanceCD) }}
                            </td>
                            <td class="amount-cell">
                                {{ formatMergedAmount(balanceCD) }}
                            </td>

                            <!-- Credit Side Totals -->
                            <td colspan="7" class="text-right"></td>
                            <td class="amount-cell">
                                {{ formatMergedAmount(balanceCD) }}
                            </td>
                            <td class="amount-cell">
                                {{ formatMergedAmount(balanceCD) }}
                            </td>
                        </tr>
                        <tr class="font-bold">
                            <!-- Debit Side -->
                            <td class="text-right"></td>
                            <td class="text-right"></td>
                            <td class="text-right"></td>
                            <td colspan="3" class="text-right"></td>
                            <td class="amount-cell">

                            </td>
                            <td class="amount-cell">

                            </td>

                            <!-- Credit Side Totals -->
                            <td colspan="7" class="text-right"></td>
                            <td class="amount-cell">
                                {{ formatMergedAmount(totalCreditSide) }}
                            </td>
                            <td class="amount-cell">
                                {{ formatMergedAmount(totalCreditSide) }}
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
    </div>
    <div class="w-full"> <Button @click="exportHtmlTableToExcel">Export to Excel</button></div>
</template>

<style scoped>
.cashbook-print-container {
    font-family: 'Inter', sans-serif;
    background-color: #f0f4f8;
    padding: 2rem;
}

.cashbook-content {
    background: white;
}

.print-container {
    width: 100%;
    min-width: 1000px;
    page-break-inside: avoid;
    padding: 20px;
}

.print-header {
    text-align: center;
    margin-bottom: 15px;
    border-bottom: 2px solid #000;
    padding-bottom: 10px;
    page-break-after: avoid;
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
    page-break-inside: avoid;
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

.debit-row {
    background-color: rgba(59, 130, 246, 0.05) !important;
}

.credit-row {
    background-color: rgba(239, 68, 68, 0.05) !important;
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

.bg-blue-100 {
    background-color: rgba(59, 130, 246, 0.15) !important;
}

.bg-gray-100 {
    background-color: rgba(243, 244, 246, 0.8) !important;
}

.empty-row {
    height: 25px;
}

.print-footer {
    margin-top: 15px;
    padding-top: 10px;
    border-top: 1px solid #000;
    font-size: 9px;
    display: flex;
    justify-content: space-between;
    page-break-before: avoid;
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

/* Ensure no page breaks inside table rows */
tr {
    page-break-inside: avoid;
    page-break-after: auto;
}

/* Print styles */
@media print {
    .no-print {
        display: none !important;
    }

    .cashbook-print-container {
        margin: 0 !important;
        padding: 0 !important;
        background: white !important;
        -webkit-print-color-adjust: exact !important;
        color-adjust: exact !important;
    }

    .print-container {
        padding: 0 !important;
    }

    @page {
        size: A4 landscape;
        margin: 10mm;
    }

    .combined-table {
        page-break-inside: avoid;
    }

    .no-break {
        page-break-inside: avoid;
    }
}

/* Screen styles */
@media screen {
    .cashbook-print-container {
        font-family: 'Inter', sans-serif;
        background-color: #f0f4f8;
        padding: 2rem;
    }
}

/* Force landscape */
@media print {
    body {
        transform: rotate(0deg);
        width: 100%;
        height: 100%;
    }

    .combined-table {
        page-break-inside: avoid;
    }

    .no-break {
        page-break-inside: avoid;
    }

    .print-container {
        padding: 0 !important;
    }
}
</style>
