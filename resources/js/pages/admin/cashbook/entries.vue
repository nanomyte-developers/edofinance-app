<script setup>
import AppLayout from '@/layouts/AppLayout.vue';
import { Head } from '@inertiajs/vue3';
import axios from 'axios';
import Button from 'primevue/button';
import Card from 'primevue/card';
import Tag from 'primevue/tag';
import Toast from 'primevue/toast';
import Toolbar from 'primevue/toolbar';
import { useToast } from 'primevue/usetoast';
import { computed, ref, onMounted } from 'vue';
import { saveAs } from 'file-saver';

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
            id: null,
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
});

const toast = useToast();
const isPrintMode = ref(false);
const debouncedPrint = ref(false);
const loading = ref(false);
const previousMonthData = ref(null);
const nextMonthData = ref(null);
const balanceChain = ref(null);

// Fetch previous month balance data
const fetchPreviousMonthBalance = async () => {
    try {
        if (!props.cashbook.id) return;
        
        const response = await axios.get(`/cashbook/${props.cashbook.id}/previous-balance`);
        
        if (response.data.success) {
            previousMonthData.value = response.data;
        }
    } catch (error) {
        console.error('Error fetching previous month balance:', error);
    }
};

// Fetch next month info
const fetchNextMonthInfo = async () => {
    try {
        if (!props.cashbook.id) return;
        
        const response = await axios.get(`/cashbook/${props.cashbook.id}/next-month-info`);
        
        if (response.data.success) {
            nextMonthData.value = response.data;
        }
    } catch (error) {
        console.error('Error fetching next month info:', error);
    }
};

// Fetch complete balance chain
const fetchBalanceChain = async () => {
    try {
        if (!props.cashbook.id) return;
        
        const response = await axios.get(`/cashbook/${props.cashbook.id}/balance-chain`);
        
        if (response.data.success) {
            balanceChain.value = response.data;
        }
    } catch (error) {
        console.error('Error fetching balance chain:', error);
    }
};

// Fetch all balance data on component mount
onMounted(() => {
    fetchPreviousMonthBalance();
    fetchNextMonthInfo();
    fetchBalanceChain();
});

// Computed properties with actual data
const previousMonthBalance = computed(() => {
    if (previousMonthData.value) {
        return previousMonthData.value.previous_month_balance || 0;
    }
    return props.cashbook.opening_balance || 0;
});

const nextMonthOpeningBalance = computed(() => {
    return balanceCD.value;
});

const carryForwardInfo = computed(() => {
    if (!nextMonthData.value) return null;
    
    return {
        nextMonth: nextMonthData.value.next_month_name,
        nextYear: nextMonthData.value.next_year,
        amount: balanceCD.value,
        willCarryForward: nextMonthData.value.will_carry_forward,
        hasNextCashbook: nextMonthData.value.has_next_cashbook,
    };
});

const previousMonthInfo = computed(() => {
    if (!previousMonthData.value) return null;
    
    return {
        isJanuary: previousMonthData.value.is_january,
        source: previousMonthData.value.source,
        monthName: previousMonthData.value.previous_month_name,
        monthNumber: previousMonthData.value.previous_month_number,
        year: previousMonthData.value.previous_year,
        status: previousMonthData.value.previous_cashbook_status,
    };
});

// Balance chain summary
const balanceChainSummary = computed(() => {
    if (!balanceChain.value) return null;
    
    const chain = balanceChain.value.chain;
    const summary = {
        hasPrevious: chain?.previous_months?.length > 0,
        previousCount: chain?.previous_months?.length || 0,
        hasNext: chain?.next_months?.length > 0,
        nextCount: chain?.next_months?.length || 0,
        carryForwardChain: balanceChain.value.carry_forward_chain || [],
    };
    
    return summary;
});

// Helper to format currency with better readability
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

// Format amount for display (merged Naira and Kobo)
const formatMergedAmount = (amount) => {
    if (!amount && amount !== 0) return '';
    const formatted = formatCurrency(amount);
    return `${formatted.naira}.${formatted.kobo}`;
};

// Safely get values with fallbacks
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

// Calculate cumulative bank balance for receipts
const receiptBankBalances = computed(() => {
    const balances = [];
    let runningBalance = openingBalance.value;

    // Opening Balance row
    balances.push(formatMergedAmount(openingBalance.value));

    // Each receipt row
    props.receipts.forEach((receipt, index) => {
        const amount = parseFloat(receipt.amount) || 0;
        runningBalance += amount;
        balances.push(formatMergedAmount(runningBalance));
    });

    // Empty rows
    const emptyRows = rowCount.value - props.receipts.length - 2;
    for (let i = 0; i < emptyRows; i++) {
        balances.push('');
    }

    // BAL b/d row (same as balanceCD)
    balances.push(formatMergedAmount(balanceCD.value));

    return balances;
});

// Calculate cumulative bank balance for payments
const paymentBankBalances = computed(() => {
    const balances = [];
    let runningBalance = totalDebitSide.value; // Start with total debit side

    // Each payment row
    props.payments.forEach((payment, index) => {
        const amount = parseFloat(payment.amount) || 0;
        runningBalance -= amount;
        balances.push(formatMergedAmount(runningBalance));
    });

    // Empty rows
    const emptyRows = rowCount.value - props.payments.length - 2;
    for (let i = 0; i < emptyRows; i++) {
        balances.push('');
    }

    // BAL c/d row (should be balanceCD)
    balances.push(formatMergedAmount(balanceCD.value));

    return balances;
});

// Format date safely
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
        const year = d.getFullYear().toString().slice(-2); // Get last 2 digits of year

        return `${day}-${month}-${year}`;
    } catch (error) {
        return '';
    }
};

// Ensure the table has enough rows to look like the printed form
const rowCount = computed(() => {
    const minRows = 15;
    const receiptsRows = props.receipts?.length || 0;
    const paymentsRows = props.payments?.length || 0;
    return Math.max(receiptsRows + 2, paymentsRows + 2, minRows);
});

// const printCashbook = () => {
//     debouncedPrint.value = true;
//     isPrintMode.value = true;

//     setTimeout(() => {
//         // Create a new window for printing
//         const printWindow = window.open('', '_blank');
        
//         // Get the current date for the print header
//         const currentDate = new Date().toLocaleDateString('en-GB', {
//             day: '2-digit',
//             month: 'short',
//             year: 'numeric',
//         });
        
//         // Build the print HTML content
//         const printContent = `
//             <!DOCTYPE html>
//             <html>
//             <head>
//                 <title>Treasury Cash Book - ${props.cashbook.month_name} ${props.cashbook.year}</title>
//                 <style>
//                     @page {
//                         size: A4 landscape;
//                         margin: 10mm;
//                     }
                    
//                     body {
//                         font-family: Arial, sans-serif;
//                         font-size: 10px;
//                         margin: 0;
//                         padding: 0;
//                         -webkit-print-color-adjust: exact !important;
//                         color-adjust: exact !important;
//                     }
                    
//                     .print-container {
//                         width: 100%;
//                         min-width: 1000px;
//                         page-break-inside: avoid;
//                         padding: 20px;
//                     }
                    
//                     .print-header {
//                         text-align: center;
//                         margin-bottom: 15px;
//                         border-bottom: 2px solid #000;
//                         padding-bottom: 10px;
//                         page-break-after: avoid;
//                     }
                    
//                     .print-title {
//                         font-size: 16px;
//                         font-weight: bold;
//                         margin: 5px 0;
//                         text-transform: uppercase;
//                     }
                    
//                     .print-subtitle {
//                         font-size: 12px;
//                         margin: 3px 0;
//                     }
                    
//                     .print-info {
//                         font-size: 10px;
//                         margin: 5px 0;
//                         display: flex;
//                         justify-content: space-between;
//                     }
                    
//                     .combined-table {
//                         width: 100%;
//                         border-collapse: collapse;
//                         font-size: 9px;
//                         table-layout: fixed;
//                         border: 1px solid #000;
//                         page-break-inside: avoid;
//                         margin-bottom: 20px;
//                     }
                    
//                     .combined-table th,
//                     .combined-table td {
//                         border: 1px solid #000;
//                         padding: 3px 5px;
//                         vertical-align: middle;
//                         text-align: center;
//                         height: 25px;
//                         overflow: hidden;
//                     }
                    
//                     .combined-table th {
//                         background-color: #f0f0f0 !important;
//                         font-weight: bold;
//                         -webkit-print-color-adjust: exact;
//                         print-color-adjust: exact;
//                     }
                    
//                     .debit-header {
//                         background-color: #e3f2fd !important;
//                     }
                    
//                     .credit-header {
//                         background-color: #ffebee !important;
//                     }
                    
//                     .debit-row {
//                         background-color: rgba(59, 130, 246, 0.05) !important;
//                     }
                    
//                     .credit-row {
//                         background-color: rgba(239, 68, 68, 0.05) !important;
//                     }
                    
//                     .amount-cell {
//                         text-align: right;
//                         font-family: 'Courier New', monospace;
//                         font-weight: bold;
//                         padding-right: 8px;
//                     }
                    
//                     .wrap-text {
//                         word-wrap: break-word;
//                         overflow-wrap: break-word;
//                         white-space: normal;
//                         line-height: 1.2;
//                     }
                    
//                     .text-right {
//                         text-align: right;
//                     }
                    
//                     .text-center {
//                         text-align: center;
//                     }
                    
//                     .font-bold {
//                         font-weight: bold;
//                     }
                    
//                     .bg-blue-50 {
//                         background-color: rgba(59, 130, 246, 0.1) !important;
//                         -webkit-print-color-adjust: exact;
//                         print-color-adjust: exact;
//                     }
                    
//                     .bg-red-50 {
//                         background-color: rgba(239, 68, 68, 0.1) !important;
//                         -webkit-print-color-adjust: exact;
//                         print-color-adjust: exact;
//                     }
                    
//                     .empty-row {
//                         height: 25px;
//                     }
                    
//                     .print-footer {
//                         margin-top: 15px;
//                         padding-top: 10px;
//                         border-top: 1px solid #000;
//                         font-size: 9px;
//                         display: flex;
//                         justify-content: space-between;
//                         page-break-before: avoid;
//                     }
                    
//                     /* Column widths */
//                     .date-col { width: 70px; }
//                     .cb-col { width: 60px; }
//                     .payer-col { width: 150px; }
//                     .title-col { width: 120px; }
//                     .num-col { width: 80px; }
//                     .receipt-col { width: 100px; }
//                     .amount-col { width: 120px; }
//                     .bank-col { width: 120px; }
//                     .dept-col { width: 70px; }
//                     .payee-col { width: 150px; }
//                     .cheque-col { width: 100px; }
                    
//                     /* Ensure no page breaks inside table rows */
//                     tr {
//                         page-break-inside: avoid;
//                         page-break-after: auto;
//                     }
                    
//                     /* Force landscape */
//                     @media print {
//                         body {
//                             transform: rotate(0deg);
//                             width: 100%;
//                             height: 100%;
//                         }
                        
//                         .combined-table {
//                             page-break-inside: avoid;
//                         }
                        
//                         .no-break {
//                             page-break-inside: avoid;
//                         }
                        
//                         .print-container {
//                             padding: 0 !important;
//                         }
//                     }
//                 </style>
//             </head>
//             <body>
//                 <div class="print-container">
//                     <!-- Print Header -->
//                     <div class="print-header">
//                         <div class="print-title">
//                             MANAGEMENT ACCOUNTS REPORTING - BENIN CITY
//                         </div>
//                         <div class="print-subtitle">
//                             A/CS GEN. 18 (REVISED)
//                         </div>
//                         <h2 class="print-title">
//                             TREASURY CASH BOOK FOR THE MONTH OF
//                             ${props.cashbook.month_name || 'Unknown'},
//                             ${props.cashbook.year || ''}
//                         </h2>
//                         <div class="print-subtitle font-bold">
//                             EDSG RECEIPT AND PAYMENT A/C:
//                             ${props.cashbook.account_number ||
//                               props.cashbook.bank_account?.account_number ||
//                               'N/A'}
//                         </div>
//                         <div class="print-subtitle uppercase">
//                             ${props.cashbook.bank_name ||
//                               props.cashbook.bank_account?.bank_name ||
//                               'N/A'}
//                         </div>
//                         <div class="print-subtitle">KING'S SQUARE, BENIN CITY</div>
//                         <div class="print-info">
//                             <span>Period: ${formatDate(props.cashbook.start_date)} to ${formatDate(props.cashbook.end_date)}</span>
//                             <span>Status: ${props.cashbook.status || 'N/A'}</span>
//                             <span>Generated: ${currentDate}</span>
//                         </div>
//                     </div>

//                     <!-- Combined Cashbook Table -->
//                     <table class="combined-table no-break">
//                         <thead>
//                             <tr>
//                                 <!-- Debit Side Headers -->
//                                 <th colspan="8" class="debit-header">
//                                     DEBIT SIDE (RECEIPTS)
//                                 </th>
//                                 <!-- Credit Side Headers -->
//                                 <th colspan="9" class="credit-header">
//                                     CREDIT SIDE (PAYMENTS)
//                                 </th>
//                             </tr>
//                             <tr>
//                                 <!-- Debit Side Column Headers -->
//                                 <th rowspan="2" class="date-col">Date</th>
//                                 <th rowspan="2" class="cb-col">CB S/N</th>
//                                 <th rowspan="2" class="payer-col">From Whom Received</th>
//                                 <th colspan="2" class="class-col">Classification</th>
//                                 <th rowspan="2" class="receipt-col">Treasury Receipt No.</th>
//                                 <th rowspan="2" class="amount-col">Receipts (₦)</th>
//                                 <th rowspan="2" class="bank-col">Bank (₦)</th>
                                
//                                 <!-- Credit Side Column Headers -->
//                                 <th rowspan="2" class="date-col">Date</th>
//                                 <th rowspan="2" class="cb-col">CB S/N</th>
//                                 <th rowspan="2" class="dept-col">Dept No.</th>
//                                 <th rowspan="2" class="payee-col">To Whom Paid</th>
//                                 <th colspan="2" class="class-col">Classification</th>
//                                 <th rowspan="2" class="cheque-col">Cheque No.</th>
//                                 <th rowspan="2" class="amount-col">Payments (₦)</th>
//                                 <th rowspan="2" class="bank-col">Bank (₦)</th>
//                             </tr>
//                             <tr>
//                                 <!-- Debit Side Sub-headers -->
//                                 <th class="title-col">Title</th>
//                                 <th class="num-col">Number</th>
                                
//                                 <!-- Credit Side Sub-headers -->
//                                 <th class="title-col">Title</th>
//                                 <th class="num-col">Number</th>
//                             </tr>
//                         </thead>
//                         <tbody>
//                             ${generatePrintRows()}
//                         </tbody>
//                         <tfoot>
//                             <tr class="font-bold">
//                                 <!-- Debit Side Totals -->
//                                 <td colspan="6" class="text-right">TOTAL</td>
//                                 <td class="amount-cell">${formatMergedAmount(totalDebitSide.value)}</td>
//                                 <td class="amount-cell">${formatMergedAmount(totalDebitSide.value)}</td>
                                
//                                 <!-- Credit Side Totals -->
//                                 <td colspan="7" class="text-right">TOTAL</td>
//                                 <td class="amount-cell">${formatMergedAmount(totalDebitSide.value)}</td>
//                                 <td class="amount-cell">${formatMergedAmount(totalDebitSide.value)}</td>
//                             </tr>
//                         </tfoot>
//                     </table>
                    
//                     <!-- Print Footer -->
//                     <div class="print-footer">
//                         <span>Page 1</span>
//                         <div class="text-right uppercase italic">
//                             PREPARED BY MANAGEMENT ACCOUNTS AND REPORTING SECTION<br />
//                             OFFICE OF THE ACCOUNTANT-GENERAL, BENIN CITY
//                         </div>
//                     </div>
//                 </div>
                
//                 <script>
//                     window.onload = function() {
//                         window.print();
//                         window.onafterprint = function() {
//                             window.close();
//                         };
//                     };
//                 <\/script>
//             </body>
//             </html>
//         `;
        
//         printWindow.document.write(printContent);
//         printWindow.document.close();

//         // Reset print mode
//         setTimeout(() => {
//             isPrintMode.value = false;
//             debouncedPrint.value = false;
//         }, 1000);
        
//     }, 100);
// };

// Helper function to generate print rows


// const generatePrintRows = () => {
//     const rows = [];
//     const maxRows = Math.max(props.receipts.length + 2, props.payments.length + 2, 15);
    
//     for (let i = 0; i < maxRows; i++) {
//         let debitDate = '';
//         let debitCbSn = '';
//         let debitPayer = '';
//         let debitTitle = '';
//         let debitNumber = '';
//         let debitReceiptNo = '';
//         let debitAmount = '';
//         let debitBank = '';
        
//         let creditDate = '';
//         let creditCbSn = '';
//         let creditDept = '';
//         let creditPayee = '';
//         let creditTitle = '';
//         let creditNumber = '';
//         let creditCheque = '';
//         let creditAmount = '';
//         let creditBank = '';
        
//         // Debit Side Logic
//         if (i === 0) {
//             // Opening Balance
//             debitDate = formatShortDate(props.cashbook.start_date);
//             debitCbSn = '1';
//             debitPayer = 'BAL B/F';
//             debitTitle = 'Opening Balance';
//             debitAmount = formatMergedAmount(openingBalance.value);
//             debitBank = receiptBankBalances.value[0];
//         } else if (i <= props.receipts.length) {
//             // Receipts
//             const receipt = props.receipts[i - 1];
//             if (receipt) {
//                 debitDate = formatShortDate(receipt.transaction_date);
//                 debitCbSn = (i + 1).toString();
//                 debitPayer = receipt.payer_name || 'N/A';
//                 debitTitle = receipt.classification_title || '';
//                 debitNumber = receipt.sub_category || '';
//                 debitReceiptNo = receipt.receipt_no || '';
//                 debitAmount = formatMergedAmount(receipt.amount);
//                 debitBank = receiptBankBalances.value[i];
//             }
//         } else if (i === props.receipts.length + 1) {
//             // BAL b/d
//             debitDate = formatShortDate(props.cashbook.end_date);
//             debitCbSn = (props.receipts.length + 2).toString();
//             debitPayer = 'BAL b/d';
//             debitTitle = 'Balance Brought Down';
//             debitAmount = formatMergedAmount(balanceCD.value);
//             debitBank = receiptBankBalances.value[receiptBankBalances.value.length - 1];
//         }
        
//         // Credit Side Logic
//         if (i < props.payments.length) {
//             // Payments
//             const payment = props.payments[i];
//             if (payment) {
//                 creditDate = formatShortDate(payment.transaction_date);
//                 creditCbSn = (props.receipts.length + i + 3).toString();
//                 creditDept = payment.department_number || '';
//                 creditPayee = payment.payee_name || 'N/A';
//                 creditTitle = payment.classification_title || '';
//                 creditNumber = payment.sub_category || '';
//                 creditCheque = payment.cheque_no || '';
//                 creditAmount = formatMergedAmount(payment.amount);
//                 creditBank = paymentBankBalances.value[i];
//             }
//         } else if (i === props.payments.length) {
//             // BAL c/d
//             creditDate = formatShortDate(props.cashbook.end_date);
//             creditCbSn = (props.receipts.length + props.payments.length + 3).toString();
//             creditPayee = 'BAL c/d';
//             creditTitle = 'Balance Carried Down';
//             creditAmount = formatMergedAmount(balanceCD.value);
//             creditBank = paymentBankBalances.value[paymentBankBalances.value.length - 1];
//         }
        
//         const rowClass = i === 0 || i === props.receipts.length + 1 ? 'bg-blue-50' : 
//                         i === props.payments.length ? 'bg-red-50' : '';
        
//         rows.push(`
//             <tr class="${rowClass}">
//                 <!-- Debit Side Cells -->
//                 <td class="date-col text-center">${debitDate}</td>
//                 <td class="cb-col text-center">${debitCbSn}</td>
//                 <td class="payer-col wrap-text">${debitPayer}</td>
//                 <td class="title-col wrap-text">${debitTitle}</td>
//                 <td class="num-col wrap-text">${debitNumber}</td>
//                 <td class="receipt-col wrap-text">${debitReceiptNo}</td>
//                 <td class="amount-col amount-cell">${debitAmount}</td>
//                 <td class="bank-col amount-cell">${debitBank}</td>
                
//                 <!-- Credit Side Cells -->
//                 <td class="date-col text-center">${creditDate}</td>
//                 <td class="cb-col text-center">${creditCbSn}</td>
//                 <td class="dept-col wrap-text">${creditDept}</td>
//                 <td class="payee-col wrap-text">${creditPayee}</td>
//                 <td class="title-col wrap-text">${creditTitle}</td>
//                 <td class="num-col wrap-text">${creditNumber}</td>
//                 <td class="cheque-col wrap-text">${creditCheque}</td>
//                 <td class="amount-col amount-cell">${creditAmount}</td>
//                 <td class="bank-col amount-cell">${creditBank}</td>
//             </tr>
//         `);
//     }
    
//     return rows.join('');
// };

const exportToCSV = () => {
    try {
        // Prepare CSV headers
        const headers = [
            'S/N',
            'Date',
            'CB S/N',
            'From Whom Received/To Whom Paid',
            'Classification Title',
            'Classification Number',
            'Receipt No/Cheque No/Dept No',
            'Receipts (₦)',
            'Bank (₦)',
            'Payments (₦)',
            'Bank (₦)',
            'Type',
            'Transaction Date',
            'Amount'
        ];
        
        // Prepare CSV rows
        const rows = [];
        let serialNumber = 1;
        
        // Add opening balance
        rows.push([
            serialNumber,
            formatShortDate(props.cashbook.start_date),
            '1',
            'BAL B/F',
            'Opening Balance',
            '',
            '',
            formatMergedAmount(openingBalance.value),
            receiptBankBalances.value[0] || '',
            '',
            '',
            'Opening Balance',
            props.cashbook.start_date,
            openingBalance.value
        ]);
        serialNumber++;
        
        // Add receipts
        props.receipts.forEach((receipt, index) => {
            rows.push([
                serialNumber,
                formatShortDate(receipt.transaction_date),
                (index + 2).toString(),
                receipt.payer_name || 'N/A',
                receipt.classification_title || '',
                receipt.sub_category || '',
                receipt.receipt_no || '',
                formatMergedAmount(receipt.amount),
                receiptBankBalances.value[index + 1] || '',
                '',
                '',
                'Receipt',
                receipt.transaction_date,
                receipt.amount
            ]);
            serialNumber++;
        });
        
        // Add BAL b/d
        rows.push([
            serialNumber,
            formatShortDate(props.cashbook.end_date),
            (props.receipts.length + 2).toString(),
            'BAL b/d',
            'Balance Brought Down',
            '',
            '',
            formatMergedAmount(balanceCD.value),
            receiptBankBalances.value[receiptBankBalances.value.length - 1] || '',
            '',
            '',
            'Balance',
            props.cashbook.end_date,
            balanceCD.value
        ]);

        serialNumber++;
        
        // Add payments
        props.payments.forEach((payment, index) => {
            rows.push([
                serialNumber,
                formatShortDate(payment.transaction_date),
                (props.receipts.length + index + 3).toString(),
                payment.payee_name || 'N/A',
                payment.classification_title || '',
                payment.sub_category || '',
                payment.cheque_no || payment.department_number || '',
                '',
                '',
                formatMergedAmount(payment.amount),
                paymentBankBalances.value[index] || '',
                'Payment',
                payment.transaction_date,
                payment.amount
            ]);
            serialNumber++;
        });
        
        // Add BAL c/d
        rows.push([
            serialNumber,
            formatShortDate(props.cashbook.end_date),
            (props.receipts.length + props.payments.length + 3).toString(),
            'BAL c/d',
            'Balance Carried Down',
            '',
            '',
            '',
            '',
            formatMergedAmount(balanceCD.value),
            paymentBankBalances.value[paymentBankBalances.value.length - 1] || '',
            'Balance',
            props.cashbook.end_date,
            balanceCD.value
        ]);

        serialNumber++;
        
        // Add totals row
        rows.push([
            'TOTAL',
            '',
            '',
            '',
            '',
            '',
            '',
            formatMergedAmount(totalDebitSide.value),
            formatMergedAmount(totalDebitSide.value),
            formatMergedAmount(totalDebitSide.value),
            formatMergedAmount(totalDebitSide.value),
            'Total',
            '',
            totalDebitSide.value
        ]);
        
        // Convert to CSV format
        const csvContent = [
            headers.join(','),
            ...rows.map(row => row.map(cell => {
                // Escape commas and quotes in cells
                if (typeof cell === 'string' && (cell.includes(',') || cell.includes('"') || cell.includes('\n'))) {
                    return `"${cell.replace(/"/g, '""')}"`;
                }
                return cell;
            }).join(','))
        ].join('\n');
        
        // Create blob and download
        const blob = new Blob(['\ufeff' + csvContent], { type: 'text/csv;charset=utf-8;' });
        const filename = `Treasury_Cash_Book_${props.cashbook.month_name}_${props.cashbook.year}_${new Date().toISOString().split('T')[0]}.csv`;
        
        saveAs(blob, filename);
        
        toast.add({
            severity: 'success',
            summary: 'Export Successful',
            detail: `Cashbook exported as ${filename}`,
            life: 3000,
        });
        
    } catch (error) {
        console.error('CSV Export Error:', error);
        toast.add({
            severity: 'error',
            summary: 'Export Failed',
            detail: 'Failed to export cashbook. Please try again.',
            life: 3000,
        });
    }
};

const printCashbook = () => {
    // Open print view in new window
    const printUrl = `/cashbooks/${props.cashbook.id}/print`;
    window.open(printUrl, '_blank');
};

const goBack = () => {
    window.history.back();
};

// Refresh balance data
const refreshBalanceData = async () => {
    loading.value = true;
    try {
        await Promise.all([
            fetchPreviousMonthBalance(),
            fetchNextMonthInfo(),
            fetchBalanceChain(),
        ]);
        
        toast.add({
            severity: 'success',
            summary: 'Refreshed',
            detail: 'Balance data refreshed successfully',
            life: 3000,
        });
    } catch (error) {
        toast.add({
            severity: 'error',
            summary: 'Refresh Failed',
            detail: 'Failed to refresh balance data',
            life: 3000,
        });
    } finally {
        loading.value = false;
    }
};
</script>

<template>
    <AppLayout>
        <Head
            :title="`Treasury Cash Book - ${cashbook.month_name} ${cashbook.year}`"
        />
        <Toast />

        <div
            class="card surface-card shadow-2 border-round p-4"
            :class="{ 'print-mode': debouncedPrint }"
        >
            <!-- Header with Actions -->
            <Toolbar class="mb-4 print-hide">
                <template #start>
                    <div class="align-items-center flex gap-3">
                        <Button
                            icon="pi pi-arrow-left"
                            class="p-button-text p-button-rounded"
                            @click="goBack"
                            v-tooltip.top="'Go Back'"
                        />
                        <div>
                            <h3 class="m-0 font-bold uppercase">
                                Treasury Cash Book
                            </h3>
                            <div class="align-items-center mt-1 flex gap-2">
                                <Tag
                                    :value="cashbook.status || 'N/A'"
                                    :severity="cashbook.status === 'processed' ? 'success' : 'warning'"
                                    class="text-xs"
                                />
                                <span class="text-500 text-sm">
                                    {{ cashbook.month_name || 'Unknown' }},
                                    {{ cashbook.year || '' }}
                                </span>
                            </div>
                        </div>
                    </div>
                </template>

                <template #end>
                    <div class="flex gap-2">
                        <Button
                            label="Refresh"
                            icon="pi pi-refresh"
                            class="p-button-outlined p-button-secondary"
                            @click="refreshBalanceData"
                            :loading="loading"
                            v-tooltip.top="'Refresh balance data'"
                        />
                        <Button
                            label="Print"
                            icon="pi pi-print"
                            class="p-button-outlined"
                            @click="printCashbook"
                            v-tooltip.top="'Print cashbook'"
                        />
                        <Button
                            label="Export CSV"
                            icon="pi pi-file-excel"
                            class="p-button-outlined p-button-success"
                            @click="exportToCSV"
                            v-tooltip.top="'Export to CSV'"
                        />
                        <div class="align-items-center flex gap-2">
                            <Tag
                                value="Receipts"
                                severity="success"
                                class="text-xs"
                            />
                            <span class="text-sm">{{ receipts?.length || 0 }}</span>
                            <span class="text-500">|</span>
                            <Tag
                                value="Payments"
                                severity="warning"
                                class="text-xs"
                            />
                            <span class="text-sm">{{ payments?.length || 0 }}</span>
                        </div>
                    </div>
                </template>
            </Toolbar>

            <!-- Balance Carry Forward Information -->
            <div v-if="previousMonthData || nextMonthData" class="mb-4 grid print-hide">
                <!-- Previous Month Balance -->
                <div class="col-12 md:col-4">
                    <Card class="surface-50">
                        <template #title>
                            <div class="align-items-center flex gap-2">
                                <i class="pi pi-arrow-left text-blue-600"></i>
                                <span>Previous Month Balance</span>
                            </div>
                        </template>
                        <template #content>
                            <div v-if="previousMonthInfo">
                                <div class="mb-2">
                                    <div class="text-500 text-sm">
                                        Source
                                    </div>
                                    <Tag 
                                        :value="previousMonthInfo.source === 'financial_year_opening' ? 'Financial Year' : 'Previous Month'"
                                        :severity="previousMonthInfo.source === 'financial_year_opening' ? 'info' : 'success'"
                                        class="text-xs"
                                    />
                                </div>
                                
                                <div class="mb-2">
                                    <div class="text-500 text-sm">
                                        Opening Balance Source
                                    </div>
                                    <div class="text-900 text-lg font-bold text-blue-600">
                                        {{ formatCurrency(previousMonthBalance, false) }}
                                    </div>
                                </div>
                                
                                <div v-if="!previousMonthInfo.isJanuary" class="text-500 text-sm">
                                    Carried from 
                                    <span class="font-medium">
                                        {{ previousMonthInfo.monthName }} {{ previousMonthInfo.year }}
                                    </span>
                                    <Tag 
                                        :value="previousMonthInfo.status || 'N/A'"
                                        :severity="previousMonthInfo.status === 'processed' ? 'success' : 'warning'"
                                        class="ml-2 text-xs"
                                    />
                                </div>
                                
                                <div v-else class="text-500 text-sm">
                                    January - Financial Year Opening Balance
                                </div>
                            </div>
                            <div v-else class="py-3 text-center">
                                <i class="pi pi-spin pi-spinner text-400"></i>
                                <p class="text-500 mt-2">Loading previous month data...</p>
                            </div>
                        </template>
                    </Card>
                </div>
                
                <!-- Current Month Summary -->
                <div class="col-12 md:col-4">
                    <Card class="surface-50">
                        <template #title>
                            <div class="align-items-center flex gap-2">
                                <i class="pi pi-calendar text-primary"></i>
                                <span>Current Month</span>
                            </div>
                        </template>
                        <template #content>
                            <div class="grid">
                                <div class="col-12 md:col-6">
                                    <div class="mb-2">
                                        <div class="text-500 text-sm">
                                            Opening Balance
                                        </div>
                                        <div class="text-900 text-lg font-bold text-blue-600">
                                            {{ formatCurrency(openingBalance, false) }}
                                        </div>
                                    </div>
                                </div>
                                <div class="col-12 md:col-6">
                                    <div class="mb-2">
                                        <div class="text-500 text-sm">
                                            Closing Balance
                                        </div>
                                        <div class="text-900 text-lg font-bold"
                                            :class="{
                                                'text-green-600': balanceCD > 0,
                                                'text-red-600': balanceCD < 0,
                                                'text-blue-600': balanceCD === 0,
                                            }"
                                        >
                                            {{ formatCurrency(balanceCD, false) }}
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="border-top-1 mt-3 pt-3">
                                <div class="justify-content-between flex">
                                    <span class="text-500">Movement:</span>
                                    <span class="font-medium"
                                        :class="{
                                            'text-green-600': (balanceCD - openingBalance) > 0,
                                            'text-red-600': (balanceCD - openingBalance) < 0,
                                        }"
                                    >
                                        {{ formatCurrency(balanceCD - openingBalance, false) }}
                                    </span>
                                </div>
                            </div>
                        </template>
                    </Card>
                </div>
                
                <!-- Next Month Carry Forward -->
                <div class="col-12 md:col-4">
                    <Card class="surface-50">
                        <template #title>
                            <div class="align-items-center flex gap-2">
                                <i class="pi pi-arrow-right text-green-600"></i>
                                <span>Next Month Carry Forward</span>
                            </div>
                        </template>
                        <template #content>
                            <div v-if="carryForwardInfo">
                                <div class="mb-2">
                                    <div class="text-500 text-sm">
                                        Will Carry Forward to
                                    </div>
                                    <div class="text-900 font-bold">
                                        {{ carryForwardInfo.nextMonth }} {{ carryForwardInfo.nextYear }}
                                    </div>
                                </div>
                                
                                <div class="mb-2">
                                    <div class="text-500 text-sm">
                                        Amount to Carry Forward
                                    </div>
                                    <div class="text-900 text-lg font-bold text-green-600">
                                        {{ formatCurrency(carryForwardInfo.amount, false) }}
                                    </div>
                                </div>
                                
                                <div v-if="carryForwardInfo.hasNextCashbook">
                                    <Tag 
                                        :value="carryForwardInfo.willCarryForward ? 'Will Carry Forward' : 'Not Processed'"
                                        :severity="carryForwardInfo.willCarryForward ? 'success' : 'warning'"
                                        class="text-xs"
                                    />
                                    <div class="text-500 mt-1 text-sm">
                                        This month's closing balance will become next month's opening balance
                                    </div>
                                </div>
                                <div v-else class="text-warning">
                                    <i class="pi pi-exclamation-triangle mr-1"></i>
                                    <span class="text-sm">No cashbook found for next month</span>
                                </div>
                            </div>
                            <div v-else class="py-3 text-center">
                                <i class="pi pi-spin pi-spinner text-400"></i>
                                <p class="text-500 mt-2">Loading next month data...</p>
                            </div>
                        </template>
                    </Card>
                </div>
            </div>

            <!-- Balance Chain View -->
            <div v-if="balanceChainSummary" class="mb-4 print-hide">
                <Card>
                    <template #title>
                        <div class="align-items-center flex gap-2">
                            <i class="pi pi-chart-line text-primary"></i>
                            <span>Balance Chain</span>
                        </div>
                    </template>
                    <template #content>
                        <div class="overflow-x-auto">
                            <table class="w-full text-sm">
                                <thead>
                                    <tr class="border-bottom-1 surface-border">
                                        <th class="py-2 text-left">Month</th>
                                        <th class="py-2 text-right">Opening Balance</th>
                                        <th class="py-2 text-right">Closing Balance</th>
                                        <th class="py-2 text-center">Status</th>
                                        <th class="py-2 text-center">Carry Forward</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <!-- Previous Months -->
                                    <tr 
                                        v-for="month in balanceChain?.chain?.previous_months || []" 
                                        :key="`prev-${month.month_number}`"
                                        class="border-bottom-1 surface-border"
                                    >
                                        <td class="py-2">
                                            {{ month.month }} {{ month.year }}
                                            <Tag value="Previous" severity="secondary" class="ml-2 text-xs" />
                                        </td>
                                        <td class="py-2 text-right font-medium">
                                            {{ formatCurrency(month.opening_balance, false) }}
                                        </td>
                                        <td class="py-2 text-right font-medium">
                                            {{ formatCurrency(month.closing_balance, false) }}
                                        </td>
                                        <td class="py-2 text-center">
                                            <Tag 
                                                :value="month.status || 'N/A'"
                                                :severity="month.status === 'processed' ? 'success' : 'warning'"
                                                class="text-xs"
                                            />
                                        </td>
                                        <td class="py-2 text-center text-500">
                                            <i class="pi pi-check text-green-500" v-if="month.is_processed"></i>
                                            <i class="pi pi-times text-red-500" v-else></i>
                                        </td>
                                    </tr>
                                    
                                    <!-- Current Month -->
                                    <tr class="border-bottom-1 surface-border bg-blue-50">
                                        <td class="py-2 font-bold">
                                            {{ balanceChain?.chain?.current_month?.month }} 
                                            {{ balanceChain?.chain?.current_month?.year }}
                                            <Tag value="Current" severity="info" class="ml-2 text-xs" />
                                        </td>
                                        <td class="py-2 text-right font-bold text-blue-600">
                                            {{ formatCurrency(balanceChain?.chain?.current_month?.opening_balance, false) }}
                                        </td>
                                        <td class="py-2 text-right font-bold"
                                            :class="{
                                                'text-green-600': balanceChain?.chain?.current_month?.closing_balance > 0,
                                                'text-red-600': balanceChain?.chain?.current_month?.closing_balance < 0,
                                            }"
                                        >
                                            {{ formatCurrency(balanceChain?.chain?.current_month?.closing_balance, false) }}
                                        </td>
                                        <td class="py-2 text-center">
                                            <Tag 
                                                :value="balanceChain?.chain?.current_month?.status || 'N/A'"
                                                :severity="balanceChain?.chain?.current_month?.status === 'processed' ? 'success' : 'warning'"
                                                class="text-xs"
                                            />
                                        </td>
                                        <td class="py-2 text-center">
                                            <i class="pi pi-arrow-right text-green-600 font-bold" v-if="carryForwardInfo?.willCarryForward"></i>
                                            <span v-else class="text-500">-</span>
                                        </td>
                                    </tr>
                                    
                                    <!-- Next Months -->
                                    <tr 
                                        v-for="month in balanceChain?.chain?.next_months || []" 
                                        :key="`next-${month.month_number}`"
                                        class="border-bottom-1 surface-border"
                                    >
                                        <td class="py-2">
                                            {{ month.month }} {{ month.year }}
                                            <Tag value="Next" severity="success" class="ml-2 text-xs" />
                                        </td>
                                        <td class="py-2 text-right font-medium">
                                            {{ formatCurrency(month.opening_balance, false) }}
                                        </td>
                                        <td class="py-2 text-right font-medium">
                                            {{ formatCurrency(month.closing_balance, false) }}
                                        </td>
                                        <td class="py-2 text-center">
                                            <Tag 
                                                :value="month.status || 'N/A'"
                                                :severity="month.status === 'processed' ? 'success' : 'warning'"
                                                class="text-xs"
                                            />
                                        </td>
                                        <td class="py-2 text-center">
                                            <i class="pi pi-arrow-left text-blue-600" v-if="month.will_receive_carry_forward"></i>
                                            <span v-else class="text-500">-</span>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                        
                        <!-- Carry Forward Chain -->
                        <div v-if="balanceChainSummary.carryForwardChain.length > 0" class="mt-4 border-top-1 pt-4">
                            <h5 class="font-bold mb-2">Carry Forward Chain</h5>
                            <div class="flex flex-wrap gap-2">
                                <div 
                                    v-for="(chain, index) in balanceChainSummary.carryForwardChain" 
                                    :key="index"
                                    class="surface-100 border-round border-1 border-200 p-3"
                                >
                                    <div class="flex align-items-center gap-2">
                                        <div class="font-medium">
                                            {{ chain.from_month }}
                                            <i class="pi pi-arrow-right mx-2 text-500"></i>
                                            {{ chain.to_month }}
                                        </div>
                                        <Tag 
                                            :value="formatCurrency(chain.amount, false)"
                                            :severity="chain.is_carried ? 'success' : 'info'"
                                            class="text-xs"
                                        />
                                    </div>
                                    <div class="text-500 mt-1 text-sm">
                                        {{ chain.is_carried ? 'Will be carried forward' : 'Projected carry forward' }}
                                    </div>
                                </div>
                            </div>
                        </div>
                    </template>
                </Card>
            </div>

            <!-- Print Header (Only visible when printing) -->
            <div class="print-only mb-4 text-center">
                <div
                    class="justify-content-between mb-1 flex text-xs font-bold"
                >
                    <div class="uppercase">
                        MANAGEMENT ACCOUNTS REPORTING<br />BENIN CITY
                    </div>
                    <div>A/CS GEN. 18 (REVISED)</div>
                </div>

                <h2 class="m-0 text-xl font-bold uppercase">
                    TREASURY CASH BOOK FOR THE MONTH OF
                    {{ cashbook.month_name || 'Unknown' }},
                    {{ cashbook.year || '' }}
                </h2>
                <div class="font-bold">
                    EDSG RECEIPT AND PAYMENT A/C:
                    {{
                        cashbook.account_number ||
                        cashbook.bank_account?.account_number ||
                        'N/A'
                    }}
                </div>
                <div class="uppercase">
                    {{
                        cashbook.bank_name ||
                        cashbook.bank_account?.bank_name ||
                        'N/A'
                    }}
                </div>
                <div class="text-sm">KING'S SQUARE, BENIN CITY</div>
                <div class="mt-1 text-xs">
                    Period: {{ formatDate(cashbook.start_date) }} to
                    {{ formatDate(cashbook.end_date) }} | Status:
                    {{ cashbook.status || 'N/A' }} | Generated:
                    {{ new Date().toLocaleDateString('en-GB') }}
                </div>
            </div>

            <!-- Main Cashbook Container -->
            <div
                class="cashbook-container surface-border border-round border-1"
            >
                <div class="cashbook-header grid-nogutter grid">
                    <div class="border-right-1 surface-border col-6">
                        <div
                            class="border-bottom-1 surface-border bg-blue-50 p-2 text-center font-bold text-blue-700"
                        >
                            <i class="pi pi-arrow-down-right mr-2"></i>
                            DEBIT SIDE (RECEIPTS)
                        </div>
                    </div>
                    <div class="col-6">
                        <div
                            class="border-bottom-1 surface-border bg-red-50 p-2 text-center font-bold text-red-700"
                        >
                            <i class="pi pi-arrow-up-right mr-2"></i>
                            CREDIT SIDE (PAYMENTS)
                        </div>
                    </div>
                </div>

                <div class="cashbook-content">
                    <div class="grid-nogutter grid h-full">
                        <!-- Debit Side (Receipts) -->
                        <div class="border-right-1 surface-border col-6">
                            <div class="debit-side-container h-full">
                                <div class="debit-side-scrollable">
                                    <table class="treasury-table w-full">
                                        <thead class="sticky-top">
                                            <tr class="text-center">
                                                <th
                                                    rowspan="2"
                                                    class="sticky-column date-col"
                                                >
                                                    Date
                                                </th>
                                                <th
                                                    rowspan="2"
                                                    class="sticky-column cb-col"
                                                >
                                                    CB S/N
                                                </th>
                                                <th
                                                    rowspan="2"
                                                    class="sticky-column payer-col"
                                                >
                                                    From Whom Received
                                                </th>
                                                <th
                                                    colspan="2"
                                                    class="class-col"
                                                >
                                                    Classification
                                                </th>
                                                <th
                                                    rowspan="2"
                                                    class="small-header receipt-col"
                                                >
                                                    Treasury Receipt No.
                                                </th>
                                                <th class="amount-col">
                                                    Receipts
                                                </th>
                                                <th class="bank-col">Bank</th>
                                            </tr>
                                            <tr class="text-center">
                                                <th
                                                    class="small-header title-col"
                                                >
                                                    Title
                                                </th>
                                                <th
                                                    class="small-header num-col"
                                                >
                                                    Number
                                                </th>
                                                <th>₦</th>
                                                <th>₦</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <!-- Opening Balance Row (Bal B/F) -->
                                            <tr class="bg-blue-50 font-bold">
                                                <td
                                                    class="sticky-column date-col text-center"
                                                >
                                                    {{
                                                        formatShortDate(
                                                            cashbook.start_date,
                                                        )
                                                    }}
                                                </td>
                                                <td
                                                    class="sticky-column cb-col text-center"
                                                >
                                                    1
                                                </td>
                                                <td
                                                    class="sticky-column payer-col wrap-text"
                                                >
                                                    BAL B/F
                                                </td>
                                                <td class="title-col">Opening Balance</td>
                                                <td class="num-col"></td>
                                                <td class="receipt-col"></td>
                                                <td
                                                    class="amount-cell text-right"
                                                >
                                                    {{
                                                        formatMergedAmount(
                                                            openingBalance,
                                                        )
                                                    }}
                                                </td>
                                                <td
                                                    class="amount-cell text-right"
                                                >
                                                    {{ receiptBankBalances[0] }}
                                                </td>
                                            </tr>

                                            <!-- Receipts Rows -->
                                            <tr
                                                v-for="(r, i) in receipts"
                                                :key="'r' + i"
                                                class="hover:bg-surface-100"
                                            >
                                                <td
                                                    class="sticky-column date-col text-center"
                                                >
                                                    {{
                                                        formatShortDate(
                                                            r.transaction_date,
                                                        )
                                                    }}
                                                </td>
                                                <td
                                                    class="sticky-column cb-col text-center"
                                                >
                                                    {{ i + 2 }}
                                                </td>
                                                <td
                                                    class="sticky-column payer-col wrap-text"
                                                >
                                                    {{ r.payer_name || 'N/A' }}
                                                </td>
                                                <td class="title-col wrap-text">
                                                    {{
                                                        r.classification_title ||
                                                        ''
                                                    }}
                                                </td>
                                                <td class="num-col wrap-text">
                                                    {{ r.sub_category || '' }}
                                                </td>
                                                <td
                                                    class="receipt-col wrap-text"
                                                >
                                                    {{ r.receipt_no || '' }}
                                                </td>
                                                <td
                                                    class="amount-cell text-right"
                                                >
                                                    {{
                                                        formatMergedAmount(
                                                            r.amount,
                                                        )
                                                    }}
                                                </td>
                                                <td
                                                    class="amount-cell text-right"
                                                >
                                                    {{
                                                        formatMergedAmount(
                                                            r.amount,
                                                        )
                                                    }}
                                                </td>
                                            </tr>

                                            <!-- Empty Rows -->
                                            <tr
                                                v-for="n in rowCount -
                                                receipts.length -
                                                2"
                                                :key="'rf' + n"
                                            >
                                                <td
                                                    v-for="c in 9"
                                                    :key="c"
                                                    class="empty-row"
                                                >
                                                    &nbsp;
                                                </td>
                                            </tr>

                                            <!-- Balance Brought Down (BAL b/d) -->
                                            <tr class="bg-blue-50 font-bold">
                                                <td
                                                    colspan="2"
                                                    class="sticky-column"
                                                >
                                                    BAL b/d
                                                </td>
                                                <td
                                                    colspan="4"
                                                    class="sticky-column"
                                                ></td>
                                                <td
                                                    class="amount-cell text-right"
                                                >
                                                    {{
                                                        formatMergedAmount(
                                                            balanceCD,
                                                        )
                                                    }}
                                                </td>
                                                <td
                                                    class="amount-cell text-right"
                                                >
                                                    {{
                                                        formatMergedAmount(
                                                            balanceCD,
                                                        )
                                                    }}
                                                </td>
                                            </tr>
                                        </tbody>
                                        <tfoot class="sticky-bottom">
                                            <!-- Total Row -->
                                            <tr class="bg-blue-100 font-bold">
                                                <td
                                                    colspan="6"
                                                    class="text-right"
                                                >
                                                    TOTAL
                                                </td>
                                                <!-- Receipts Total -->
                                                <td
                                                    class="amount-cell text-right"
                                                >
                                                    {{
                                                        formatMergedAmount(
                                                            totalDebitSide,
                                                        )
                                                    }}
                                                </td>
                                                <!-- Bank Total (same as Receipts Total) -->
                                                <td
                                                    class="amount-cell text-right"
                                                >
                                                    {{
                                                        formatMergedAmount(
                                                            totalDebitSide,
                                                        )
                                                    }}
                                                </td>
                                            </tr>
                                            <tr class="bg-blue-100 font-bold">
                                                <td
                                                    colspan="6"
                                                    class="text-right"
                                                >
                                                    BAL b/d
                                                </td>
                                                <!-- Receipts Total -->
                                                <td
                                                    class="amount-cell text-right"
                                                >
                                                    {{
                                                        formatMergedAmount(
                                                            balanceCD,
                                                        )
                                                    }}
                                                </td>
                                                <!-- Bank Total (same as Receipts Total) -->
                                                <td
                                                    class="amount-cell text-right"
                                                >
                                                    {{
                                                        formatMergedAmount(
                                                            balanceCD,
                                                        )
                                                    }}
                                                </td>
                                            </tr>
                                        </tfoot>
                                    </table>
                                </div>
                            </div>
                        </div>

                        <!-- Credit Side (Payments) -->
                        <div class="col-6">
                            <div class="credit-side-container h-full">
                                <div class="credit-side-scrollable">
                                    <table class="treasury-table w-full">
                                        <thead class="sticky-top">
                                            <tr class="text-center">
                                                <th
                                                    rowspan="2"
                                                    class="sticky-column date-col"
                                                >
                                                    Date
                                                </th>
                                                <th
                                                    rowspan="2"
                                                    class="sticky-column cb-col"
                                                >
                                                    CB S/N
                                                </th>
                                                <th
                                                    rowspan="2"
                                                    class="sticky-column dept-col"
                                                >
                                                    Dept No.
                                                </th>
                                                <th
                                                    rowspan="2"
                                                    class="sticky-column payee-col"
                                                >
                                                    To Whom Paid
                                                </th>
                                                <th
                                                    colspan="2"
                                                    class="class-col"
                                                >
                                                    Classification
                                                </th>
                                                <th
                                                    rowspan="2"
                                                    class="cheque-col"
                                                >
                                                    Cheque No.
                                                </th>
                                                <th class="amount-col">
                                                    Payments
                                                </th>
                                                <th class="bank-col">Bank</th>
                                            </tr>
                                            <tr class="text-center">
                                                <th
                                                    class="small-header title-col"
                                                >
                                                    Title
                                                </th>
                                                <th
                                                    class="small-header num-col"
                                                >
                                                    Number
                                                </th>
                                                <th>₦</th>
                                                <th>₦</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <!-- Payments Rows -->
                                            <tr
                                                v-for="(p, i) in payments"
                                                :key="'p' + i"
                                                class="hover:bg-surface-100"
                                            >
                                                <td
                                                    class="sticky-column date-col text-center"
                                                >
                                                    {{
                                                        formatShortDate(
                                                            p.transaction_date,
                                                        )
                                                    }}
                                                </td>
                                                <td
                                                    class="sticky-column cb-col text-center"
                                                >
                                                    <!-- {{ p.cb_sn || '' }} -->
                                                    {{ receipts.length + i + 2 }}
                                                </td>
                                                <td
                                                    class="sticky-column dept-col wrap-text"
                                                >
                                                    {{
                                                        p.department_number ||
                                                        ''
                                                    }}
                                                </td>
                                                <td
                                                    class="sticky-column payee-col wrap-text"
                                                >
                                                    {{ p.payee_name || 'N/A' }}
                                                </td>
                                                <td class="title-col wrap-text">
                                                    {{
                                                        p.classification_title ||
                                                        ''
                                                    }}
                                                </td>
                                                <td class="num-col wrap-text">
                                                    {{ p.sub_category || '' }}
                                                </td>
                                                <td
                                                    class="cheque-col wrap-text"
                                                >
                                                    {{ p.cheque_no || '' }}
                                                </td>
                                                <td
                                                    class="amount-cell text-right"
                                                >
                                                    {{
                                                        formatMergedAmount(
                                                            p.amount,
                                                        )
                                                    }}
                                                </td>
                                                <td
                                                    class="amount-cell text-right"
                                                >
                                                    {{
                                                        formatMergedAmount(
                                                            p.amount,
                                                        )
                                                    }}
                                                </td>
                                            </tr>

                                            <!-- Empty Rows -->
                                            <tr
                                                v-for="n in rowCount -
                                                payments.length -
                                                2"
                                                :key="'pf' + n"
                                            >
                                                <td
                                                    v-for="c in 10"
                                                    :key="c"
                                                    class="empty-row"
                                                >
                                                    &nbsp;
                                                </td>
                                            </tr>

                                            <!-- Balance Carried Down (BAL c/d) -->
                                            <tr
                                                class="bg-red-50 font-bold text-red-700"
                                            >
                                                <td
                                                    class="sticky-column date-col text-center"
                                                >
                                                    {{
                                                        formatShortDate(
                                                            cashbook.end_date,
                                                        )
                                                    }}
                                                </td>
                                                <td
                                                    class="sticky-column cb-col text-center"
                                                >
                                                    {{ receipts.length + payments.length + 2 }}
                                                </td>
                                                <td
                                                    class="sticky-column dept-col"
                                                ></td>
                                                <td
                                                    class="sticky-column payee-col"
                                                >
                                                    BAL c/d
                                                </td>
                                                <td class="title-col">Balance Carried Down</td>
                                                <td class="num-col"></td>
                                                <td class="cheque-col"></td>
                                                <td
                                                    class="amount-cell text-right"
                                                >
                                                    {{
                                                        formatMergedAmount(
                                                            balanceCD,
                                                        )
                                                    }}
                                                </td>
                                                <td
                                                    class="amount-cell text-right"
                                                >
                                                    {{
                                                        formatMergedAmount(
                                                            balanceCD,
                                                        )
                                                    }}
                                                </td>
                                            </tr>
                                        </tbody>
                                        <tfoot class="sticky-bottom">
                                            <!-- Total Row -->
                                            <tr class="bg-red-100 font-bold">
                                                <td
                                                    colspan="7"
                                                    class="text-right"
                                                >
                                                    TOTAL
                                                </td>
                                                <!-- Payments Total -->
                                                <td
                                                    class="amount-cell text-right"
                                                >
                                                    {{
                                                        formatMergedAmount(
                                                            totalDebitSide,
                                                        )
                                                    }}
                                                </td>
                                                <!-- Bank Total (same as Payments Total) -->
                                                <td
                                                    class="amount-cell text-right"
                                                >
                                                    {{
                                                        formatMergedAmount(
                                                            totalDebitSide,
                                                        )
                                                    }}
                                                </td>
                                            </tr>
                                        </tfoot>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Balance Summary -->
            <div class="print-hide mt-4">
                <Card class="surface-50">
                    <template #title>
                        <div class="align-items-center flex gap-2">
                            <i class="pi pi-chart-bar text-primary"></i>
                            <span>Balance Summary</span>
                        </div>
                    </template>
                    <template #content>
                        <div class="grid">
                            <div class="col-12 text-center md:col-3">
                                <div class="text-500 mb-1">
                                    Opening Balance (Bal B/F)
                                </div>
                                <div
                                    class="text-900 text-lg font-bold text-blue-600"
                                >
                                    {{ formatCurrency(openingBalance, false) }}
                                </div>
                            </div>
                            <div class="col-12 text-center md:col-3">
                                <div class="text-500 mb-1">Total Receipts</div>
                                <div
                                    class="text-900 text-lg font-bold text-green-600"
                                >
                                    {{ formatCurrency(totalReceipts, false) }}
                                </div>
                                <small class="text-500"
                                    >{{ receipts?.length || 0 }} entries</small
                                >
                            </div>
                            <div class="col-12 text-center md:col-3">
                                <div class="text-500 mb-1">Total Payments</div>
                                <div
                                    class="text-900 text-lg font-bold text-red-600"
                                >
                                    {{ formatCurrency(totalPayments, false) }}
                                </div>
                                <small class="text-500"
                                    >{{ payments?.length || 0 }} vouchers</small
                                >
                            </div>
                            <div class="col-12 text-center md:col-3">
                                <div class="text-500 mb-1">
                                    Closing Balance (Bal c/d)
                                </div>
                                <div
                                    class="text-900 text-lg font-bold"
                                    :class="{
                                        'text-green-600': balanceCD > 0,
                                        'text-red-600': balanceCD < 0,
                                        'text-blue-600': balanceCD === 0,
                                    }"
                                >
                                    {{ formatCurrency(balanceCD, false) }}
                                </div>
                                <small class="text-500"
                                    >Next period's Bal B/F</small
                                >
                            </div>
                        </div>
                        <div class="mt-3 border-t-1 pt-3">
                            <div class="grid">
                                <div class="col-12 text-center md:col-4">
                                    <div class="text-500 mb-1">
                                        Total Debit Side
                                    </div>
                                    <div
                                        class="text-900 text-lg font-bold text-blue-700"
                                    >
                                        {{
                                            formatCurrency(
                                                totalDebitSide,
                                                false,
                                            )
                                        }}
                                    </div>
                                    <small class="text-500"
                                        >Bal B/F + Total Receipts</small
                                >
                                </div>
                                <div class="col-12 text-center md:col-4">
                                    <div class="text-500 mb-1">
                                        Total Credit Side
                                    </div>
                                    <div
                                        class="text-900 text-lg font-bold text-red-700"
                                    >
                                        {{
                                            formatCurrency(
                                                totalDebitSide,
                                                false,
                                            )
                                        }}
                                    </div>
                                    <small class="text-500"
                                        >Total Payments + Bal c/d</small
                                >
                                </div>
                                <div class="col-12 text-center md:col-4">
                                    <div class="text-500 mb-1">
                                        Net Movement
                                    </div>
                                    <div
                                        class="text-900 text-lg font-bold"
                                        :class="{
                                            'text-green-600':
                                                totalReceipts - totalPayments >
                                                0,
                                            'text-red-600':
                                                totalReceipts - totalPayments <
                                                0,
                                        }"
                                    >
                                        {{
                                            formatCurrency(
                                                totalReceipts - totalPayments,
                                                false,
                                            )
                                        }}
                                    </div>
                                    <small class="text-500"
                                        >Receipts - Payments</small
                                >
                                </div>
                            </div>
                        </div>
                    </template>
                    <template #footer>
                        <div class="text-500 text-sm">
                            <i class="pi pi-info-circle mr-2"></i>
                            Bal B/F = Balance Brought Forward | Bal c/d =
                            Balance Carried Down | Bal b/d = Balance Brought
                            Down
                        </div>
                    </template>
                </Card>
            </div>

            <!-- Footer Information -->
            <div class="justify-content-between print-hide mt-4 flex text-xs">
                <div class="align-items-center flex gap-2">
                    <i class="pi pi-info-circle text-500"></i>
                    <span
                        >Generated on:
                        {{
                            new Date().toLocaleDateString('en-GB', {
                                day: '2-digit',
                                month: 'long',
                                year: 'numeric',
                                hour: '2-digit',
                                minute: '2-digit',
                            })
                        }}</span
                    >
                </div>
                <div class="text-right uppercase italic">
                    PREPARED BY MANAGEMENT ACCOUNTS AND REPORTING SECTION<br />
                    OFFICE OF THE ACCOUNTANT-GENERAL, BENIN CITY
                </div>
            </div>

            <!-- Print Footer (Only visible when printing) -->
            <div class="print-only justify-content-between mt-4 flex text-xs">
                <span>Page 1</span>
                <div class="text-right uppercase italic">
                    PREPARED BY MANAGEMENT ACCOUNTS AND REPORTING SECTION<br />
                    OFFICE OF THE ACCOUNTANT-GENERAL, BENIN CITY
                </div>
            </div>
        </div>
    </AppLayout>
</template>

<style scoped>
/* Balance Chain Styles */
.balance-chain-container {
    max-height: 300px;
    overflow-y: auto;
}

.balance-chain-container table th {
    position: sticky;
    top: 0;
    background-color: var(--surface-50);
    z-index: 10;
}

.balance-chain-container tr:hover {
    background-color: var(--surface-100);
}

/* Carry Forward Chain Styles */
.carry-forward-item {
    transition: all 0.2s ease;
    border-left: 3px solid var(--blue-500);
}

.carry-forward-item:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
}

.carry-forward-item.will-carry {
    border-left-color: var(--green-500);
}

.carry-forward-item.projected {
    border-left-color: var(--yellow-500);
}

/* Print Styles */
@media print {
    .print-hide {
        display: none !important;
    }

    .print-only {
        display: block !important;
    }

    .print-mode .card {
        box-shadow: none !important;
        border: none !important;
        padding: 0 !important;
        margin: 0 !important;
    }

    @page {
        size: A4 landscape;
        margin: 10mm;
    }

    .cashbook-container {
        height: auto !important;
        max-height: none !important;
        page-break-inside: avoid;
        border: 1px solid #000 !important;
    }

    .debit-side-container,
    .credit-side-container {
        overflow: visible !important;
        height: auto !important;
    }

    .debit-side-scrollable,
    .credit-side-scrollable {
        overflow: visible !important;
        height: auto !important;
        position: static !important;
    }

    .debit-side-scrollable > table,
    .credit-side-scrollable > table {
        position: static !important;
        min-width: auto !important;
        width: 100% !important;
    }

    .treasury-table {
        min-width: auto !important;
        width: 100% !important;
        font-size: 9px !important;
        border: 1px solid #000 !important;
    }

    .treasury-table th,
    .treasury-table td {
        border: 1px solid #000 !important;
        padding: 2px 3px !important;
        height: auto !important;
    }

    .wrap-text {
        font-size: 8px !important;
        line-height: 1.2 !important;
    }

    .amount-cell {
        font-family: 'Arial', sans-serif !important;
        font-weight: bold !important;
        font-size: 9px !important;
    }

    .sticky-column,
    .treasury-table th.sticky-column,
    .treasury-table td.sticky-column {
        position: static !important;
        box-shadow: none !important;
        background: transparent !important;
    }

    .treasury-table th,
    .treasury-table tfoot tr {
        position: static !important;
        box-shadow: none !important;
    }

    .hover\:bg-surface-100:hover {
        background-color: transparent !important;
    }

    /* Remove scrollbars in print */
    .debit-side-scrollable::-webkit-scrollbar,
    .credit-side-scrollable::-webkit-scrollbar {
        display: none !important;
    }

    .debit-side-scrollable,
    .credit-side-scrollable {
        scrollbar-width: none !important;
    }
}

@media screen {
    .print-only {
        display: none !important;
    }
}

/* Main Cashbook Container */
.cashbook-container {
    height: 600px;
    overflow: hidden;
    display: flex;
    flex-direction: column;
    background: white;
}

.cashbook-header {
    flex-shrink: 0;
    border-bottom: 2px solid var(--surface-border);
    background: white;
    z-index: 10;
}

.cashbook-content {
    flex: 1;
    overflow: hidden;
    min-height: 0;
}

/* Debit and Credit Sides Containers */
.debit-side-container,
.credit-side-container {
    height: 100%;
    position: relative;
    display: flex;
    flex-direction: column;
}

.debit-side-scrollable,
.credit-side-scrollable {
    flex: 1;
    overflow: auto;
    position: relative;
    width: 100%;
    height: 100%;
}

/* Fixed table container */
.debit-side-scrollable > table,
.credit-side-scrollable > table {
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    min-width: 1200px;
    table-layout: fixed;
}

/* Scrollable Tables */
.treasury-table {
    border-collapse: collapse;
    width: 100%;
    font-size: 12px;
    background: white;
}

.treasury-table th,
.treasury-table td {
    border: 1px solid #e5e7eb;
    padding: 6px 8px;
    vertical-align: middle;
    overflow: hidden;
    white-space: nowrap;
}

.treasury-table th {
    background-color: #f9fafb;
    font-weight: 600;
    position: sticky;
    top: 0;
    z-index: 20;
    box-shadow: 0 2px 2px -1px rgba(0, 0, 0, 0.1);
}

.treasury-table tfoot tr {
    position: sticky;
    bottom: 0;
    z-index: 20;
    background-color: white;
    box-shadow: 0 -2px 2px -1px rgba(0, 0, 0, 0.1);
}

/* Word wrapping for text columns */
.wrap-text {
    white-space: normal !important;
    word-wrap: break-word;
    overflow-wrap: break-word;
    line-height: 1.4;
    min-height: 40px;
    vertical-align: top;
    padding-top: 8px;
    padding-bottom: 8px;
}

/* Amount cells - better readability */
.amount-cell {
    font-family: 'Courier New', monospace;
    font-weight: 600;
    letter-spacing: 0.5px;
    background-color: rgba(249, 250, 251, 0.8);
    padding-right: 12px;
    text-align: right;
    font-size: 12px;
}

/* Sticky columns for better navigation */
.sticky-column {
    position: sticky;
    left: 0;
    z-index: 15;
    background: white;
    box-shadow: 2px 0 5px rgba(0, 0, 0, 0.1);
}

.treasury-table thead .sticky-column {
    z-index: 30;
    background: #f9fafb;
}

.treasury-table tbody .sticky-column {
    background: white;
}

/* Column classes for specific widths */
.date-col {
    width: 80px;
    min-width: 80px;
    max-width: 80px;
}

.cb-col {
    width: 70px;
    min-width: 70px;
    max-width: 70px;
}

.payer-col,
.payee-col {
    width: 200px;
    min-width: 200px;
    max-width: 200px;
}

.dept-col {
    width: 80px;
    min-width: 80px;
    max-width: 80px;
}

.title-col {
    width: 150px;
    min-width: 150px;
    max-width: 150px;
}

.num-col {
    width: 100px;
    min-width: 100px;
    max-width: 100px;
}

.receipt-col,
.cheque-col {
    width: 120px;
    min-width: 120px;
    max-width: 120px;
}

/* Amount and Bank columns (merged Naira and Kobo) */
.treasury-table th:nth-child(7),
.treasury-table th:nth-child(8) {
    width: 140px;
    min-width: 140px;
    max-width: 140px;
}

.treasury-table td:nth-child(7),
.treasury-table td:nth-child(8) {
    width: 140px;
    min-width: 140px;
    max-width: 140px;
}

/* Ensure proper scrolling */
.debit-side-scrollable::-webkit-scrollbar,
.credit-side-scrollable::-webkit-scrollbar {
    height: 12px;
    width: 12px;
}

.debit-side-scrollable::-webkit-scrollbar-track,
.credit-side-scrollable::-webkit-scrollbar-track {
    background: #f1f1f1;
    border-radius: 6px;
    margin: 2px;
}

.debit-side-scrollable::-webkit-scrollbar-thumb,
.credit-side-scrollable::-webkit-scrollbar-thumb {
    background-color: #c1c1c1;
    border-radius: 6px;
    border: 2px solid #f1f1f1;
}

.debit-side-scrollable::-webkit-scrollbar-thumb:hover,
.credit-side-scrollable::-webkit-scrollbar-thumb:hover {
    background-color: #a8a8a8;
}

.debit-side-scrollable::-webkit-scrollbar-corner,
.credit-side-scrollable::-webkit-scrollbar-corner {
    background: #f1f1f1;
}

/* Horizontal scroll indicator */
.scroll-indicator {
    position: absolute;
    bottom: 0;
    left: 0;
    right: 0;
    height: 4px;
    background: linear-gradient(to right, transparent, #c1c1c1, transparent);
    opacity: 0.5;
    pointer-events: none;
}

/* Hover effects */
.hover\:bg-surface-100:hover {
    background-color: var(--surface-100) !important;
}

/* Table styling */
.small-header {
    font-size: 10px;
    line-height: 1.2;
    font-weight: 500;
}

.empty-row {
    height: 40px;
    min-height: 40px;
}

.text-right {
    text-align: right;
}

.text-center {
    text-align: center;
}

/* Color coding */
.bg-blue-50 {
    background-color: rgba(59, 130, 246, 0.1) !important;
}

.bg-red-50 {
    background-color: rgba(239, 68, 68, 0.1) !important;
}

.bg-blue-100 {
    background-color: rgba(59, 130, 246, 0.15) !important;
}

.bg-red-100 {
    background-color: rgba(239, 68, 68, 0.15) !important;
}

/* Responsive Design */
@media screen and (max-width: 1200px) {
    .cashbook-container {
        height: 700px;
    }

    .date-col {
        width: 70px;
        min-width: 70px;
        max-width: 70px;
    }

    .payer-col,
    .payee-col {
        width: 180px;
        min-width: 180px;
        max-width: 180px;
    }
}

@media screen and (max-width: 768px) {
    .cashbook-container {
        height: 800px;
    }

    .cashbook-content .grid > .col-6 {
        width: 100% !important;
        border-right: none !important;
        border-bottom: 1px solid var(--surface-border);
    }

    .debit-side-container,
    .credit-side-container {
        height: 400px;
    }
}

/* Improve scroll performance */
.debit-side-scrollable,
.credit-side-scrollable {
    will-change: transform;
    -webkit-overflow-scrolling: touch;
}

/* Custom tooltip styles */
:deep(.p-tooltip) {
    max-width: 300px;
}

/* Card hover effects */
.card:hover {
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
    transition: box-shadow 0.3s ease;
}

/* Loading spinner */
.pi-spin {
    animation: spin 1s linear infinite;
}

@keyframes spin {
    from {
        transform: rotate(0deg);
    }
    to {
        transform: rotate(360deg);
    }
}

/* Status colors */
.text-success {
    color: var(--green-500) !important;
}

.text-warning {
    color: var(--yellow-500) !important;
}

.text-danger {
    color: var(--red-500) !important;
}

.text-info {
    color: var(--blue-500) !important;
}

/* Utility classes */
.uppercase {
    text-transform: uppercase;
}

.italic {
    font-style: italic;
}

.font-medium {
    font-weight: 500;
}

.font-bold {
    font-weight: 700;
}
</style>