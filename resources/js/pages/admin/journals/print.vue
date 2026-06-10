<template>
    <!-- Print.vue -->
    <div class="print-container">
        <Head :title="`Print Journal - ${journal.journal_number}`" />

        <!-- Action buttons (only visible when not printing) -->
        <div class="no-print mb-4 flex justify-end gap-2 p-4">
            <Button
                label="Generate PDF"
                icon="pi pi-file-pdf"
                @click="generatePDF"
                class="rounded-lg bg-green-600 px-6 py-2 font-semibold text-white shadow-lg transition duration-150 hover:bg-green-700"
            />
            <Button
                label="Print"
                icon="pi pi-print"
                @click="printDocument"
                class="rounded-lg bg-blue-600 px-6 py-2 font-semibold text-white shadow-lg transition duration-150 hover:bg-blue-700"
            />
            <Button
                label="Close"
                icon="pi pi-times"
                @click="closeWindow"
                class="rounded-lg bg-gray-600 px-6 py-2 font-semibold text-white shadow-lg transition duration-150 hover:bg-gray-700"
            />
        </div>

        <!-- Journal content to be printed -->
        <div id="journal-content" class="journal-content mx-auto max-w-5xl">
            <!-- Journal Header -->
            <div class="mb-6 border-b-2 border-black pb-4">
                <div class="flex justify-between text-sm font-medium">
                    <span class="font-bold uppercase">Original Copy</span>
                    <span class="border border-black px-2 py-0.5 font-bold uppercase">Journal Voucher</span>
                </div>
                <h1 class="mt-4 text-center text-2xl font-extrabold uppercase">
                    Edo State Government of Nigeria
                </h1>
                <p class="mt-2 text-center text-base font-medium">
                    {{ journal.mda?.name || 'Ministry/Agency' }}
                </p>
                <h2 class="mt-4 text-center text-xl font-bold uppercase">
                    General Journal Entry
                </h2>
            </div>

            <!-- Journal Details -->
            <div class="mb-6 grid grid-cols-1 gap-4 md:grid-cols-3">
                <div class="space-y-2">
                    <div class="flex items-center">
                        <span class="w-40 font-bold">Journal Number:</span>
                        <span class="border-b border-gray-400 px-2 font-mono">{{ journal.journal_number || 'N/A' }}</span>
                    </div>
                    <div class="flex items-center">
                        <span class="w-40 font-bold">Journal Date:</span>
                        <span class="border-b border-gray-400 px-2">{{ formatDate(journal.journal_date) }}</span>
                    </div>
                </div>
                <div class="space-y-2">
                    <div class="flex items-center">
                        <span class="w-40 font-bold">Posting Date:</span>
                        <span class="border-b border-gray-400 px-2">{{ formatDate(journal.posting_date) }}</span>
                    </div>
                    <div class="flex items-center">
                        <span class="w-40 font-bold">Reference:</span>
                        <span class="border-b border-gray-400 px-2">{{ journal.reference || 'N/A' }}</span>
                    </div>
                </div>
                <div class="space-y-2">
                    <div class="flex items-center">
                        <span class="w-40 font-bold">Status:</span>
                        <span class="border-b border-gray-400 px-2 capitalize">{{ journal.status || 'N/A' }}</span>
                    </div>
                    <div class="flex items-center">
                        <span class="w-40 font-bold">Total Amount:</span>
                        <span class="border-b border-gray-400 px-2 font-bold">{{ formatCurrency(journal.total_amount) }}</span>
                    </div>
                </div>
            </div>

            <!-- Description -->
            <div class="mb-6">
                <div class="flex items-start">
                    <span class="w-32 font-bold">Description:</span>
                    <div class="flex-1 border-b border-gray-400 px-2">{{ journal.description || 'No description provided' }}</div>
                </div>
            </div>

            <!-- Journal Entries Table -->
            <div class="mb-8">
                <h3 class="mb-4 text-lg font-bold">Journal Entries</h3>
                <table class="w-full border-collapse border border-black">
                    <thead>
                        <tr class="bg-gray-100 text-left text-sm font-bold">
                            <th class="border border-black p-3">Account Code</th>
                            <th class="border border-black p-3">Account Name</th>
                            <th class="border border-black p-3">Economic Code</th>
                            <th class="border border-black p-3 text-right">Debit (â‚¦)</th>
                            <th class="border border-black p-3 text-right">Credit (â‚¦)</th>
                            <th class="border border-black p-3">Description</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="entry in journal.entries" :key="entry.id" class="text-sm">
                            <td class="border border-black p-3">{{ entry.account?.code || 'N/A' }}</td>
                            <td class="border border-black p-3">{{ entry.account?.name || 'N/A' }}</td>
                            <td class="border border-black p-3">{{ entry.economic_code?.code || 'N/A' }}</td>
                            <td class="border border-black p-3 text-right">{{ formatCurrency(entry.debit_amount) }}</td>
                            <td class="border border-black p-3 text-right">{{ formatCurrency(entry.credit_amount) }}</td>
                            <td class="border border-black p-3">{{ entry.description || '' }}</td>
                        </tr>
                        
                        <!-- Totals Row -->
                        <tr class="bg-gray-100 font-bold">
                            <td class="border border-black p-3 text-right" colspan="3">Totals:</td>
                            <td class="border border-black p-3 text-right">{{ formatCurrency(journal.total_debit) }}</td>
                            <td class="border border-black p-3 text-right">{{ formatCurrency(journal.total_credit) }}</td>
                            <td class="border border-black p-3">
                                <span v-if="journal.total_debit === journal.total_credit" class="text-green-600">âœ“ Balanced</span>
                                <span v-else class="text-red-600">âœ— Not Balanced</span>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Amount in Words -->
            <div class="mb-8 border border-black bg-gray-50 p-4">
                <div class="flex items-start">
                    <span class="mt-1 mr-4 font-bold">Amount in Words:</span>
                    <div class="flex-1 italic">
                        {{ convertToWords(journal.total_amount) }}
                    </div>
                </div>
            </div>

            <!-- Authorizations -->
            <div class="space-y-6">
                <!-- Prepared By -->
                <div class="flex justify-between">
                    <div>
                        <p class="mb-1 font-bold">Prepared By:</p>
                        <div class="h-16 border-b border-black"></div>
                        <p class="mt-1 text-sm">Name & Signature</p>
                        <p class="text-sm">Date: {{ formatDate(new Date()) }}</p>
                    </div>
                    <div>
                        <p class="mb-1 font-bold">Checked By:</p>
                        <div class="h-16 border-b border-black"></div>
                        <p class="mt-1 text-sm">Name & Signature</p>
                        <p class="text-sm">Date: {{ formatDate(new Date()) }}</p>
                    </div>
                </div>

                <!-- Approved By -->
                <div>
                    <p class="mb-1 font-bold">Approved By:</p>
                    <div class="h-20 border-b border-black"></div>
                    <p class="mt-1 text-sm">Name & Signature</p>
                    <p class="text-sm">Date: {{ formatDate(new Date()) }}</p>
                    <p class="mt-1 text-sm font-bold">{{ journal.mda?.name || 'Ministry/Agency' }}</p>
                </div>
            </div>

            <!-- Footer -->
            <div class="mt-8 border-t border-black pt-4 text-center text-sm italic">
                <p>This journal voucher is subject to audit verification</p>
                <p class="mt-1">Keep this copy for your records</p>
            </div>
        </div>
    </div>
</template>

<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { ref, onMounted } from 'vue';
import html2pdf from 'html2pdf.js';
import Button from 'primevue/button';

const props = defineProps({
    journal: {
        type: Object,
        required: true,
    },
});

// Format currency
const formatCurrency = (amount: number) => {
    if (!amount && amount !== 0) return 'â‚¦0.00';
    return new Intl.NumberFormat('en-NG', {
        style: 'currency',
        currency: 'NGN',
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    }).format(amount);
};

// Format date
const formatDate = (dateString: string | Date) => {
    if (!dateString) return '';
    const date = new Date(dateString);
    return date.toLocaleDateString('en-GB', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
    });
};

// Number to words function (same as in PrintableVoucher.vue)
const convertToWords = (amount: string | number) => {
    if (!amount && amount !== 0) return '';
    
    const amountNum = typeof amount === 'string' 
        ? parseFloat(amount.replace(/,/g, '')) 
        : amount;

    if (amountNum === 0) return 'Zero Naira Only';

    const units = ['', 'One', 'Two', 'Three', 'Four', 'Five', 'Six', 'Seven', 'Eight', 'Nine'];
    const teens = ['Ten', 'Eleven', 'Twelve', 'Thirteen', 'Fourteen', 'Fifteen', 'Sixteen', 'Seventeen', 'Eighteen', 'Nineteen'];
    const tens = ['', '', 'Twenty', 'Thirty', 'Forty', 'Fifty', 'Sixty', 'Seventy', 'Eighty', 'Ninety'];

    function convertThreeDigits(num: number) {
        let result = '';
        const hundreds = Math.floor(num / 100);
        const remainder = num % 100;

        if (hundreds > 0) {
            result += units[hundreds] + ' Hundred';
        }

        if (remainder > 0) {
            if (result !== '') result += ' and ';
            if (remainder < 10) {
                result += units[remainder];
            } else if (remainder < 20) {
                result += teens[remainder - 10];
            } else {
                const tensDigit = Math.floor(remainder / 10);
                const unitsDigit = remainder % 10;
                result += tens[tensDigit];
                if (unitsDigit > 0) {
                    result += '-' + units[unitsDigit];
                }
            }
        }
        return result;
    }

    let words = '';
    let nairaAmount = Math.floor(amountNum);

    if (nairaAmount >= 1000000) {
        const millions = Math.floor(nairaAmount / 1000000);
        words += convertThreeDigits(millions) + ' Million';
        nairaAmount %= 1000000;
    }

    if (nairaAmount >= 1000) {
        if (words !== '') words += ' ';
        const thousands = Math.floor(nairaAmount / 1000);
        words += convertThreeDigits(thousands) + ' Thousand';
        nairaAmount %= 1000;
    }

    if (nairaAmount > 0) {
        if (words !== '') words += ' ';
        words += convertThreeDigits(nairaAmount);
    }

    const kobo = Math.round((amountNum - Math.floor(amountNum)) * 100);
    let koboWords = '';

    if (kobo > 0) {
        koboWords = ' and ';
        if (kobo < 10) {
            koboWords += units[kobo];
        } else if (kobo < 20) {
            koboWords += teens[kobo - 10];
        } else {
            const tensDigit = Math.floor(kobo / 10);
            const unitsDigit = kobo % 10;
            koboWords += tens[tensDigit];
            if (unitsDigit > 0) {
                koboWords += '-' + units[unitsDigit];
            }
        }
        koboWords += ' Kobo';
    }

    return words + ' Naira' + koboWords + ' Only';
};

// PDF Generation function
const generatePDF = () => {
    const element = document.getElementById('journal-content');
    
    const opt = {
        margin: [0.1, 0.1, 0.1, 0.1],
        filename: `journal-${props.journal.journal_number || 'entry'}.pdf`,
        image: {
            type: 'jpeg',
            quality: 1,
        },
        html2canvas: {
            scale: 3,
            useCORS: true,
            logging: false,
            letterRendering: true,
            width: element?.scrollWidth || 800,
            height: element?.scrollHeight || 1120,
            backgroundColor: '#FFFFFF',
        },
        jsPDF: {
            unit: 'mm',
            format: 'a4',
            orientation: 'portrait',
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

// Close window function
const closeWindow = () => {
    window.history.back();
};
</script>

<style scoped>
.print-container {
    font-family: 'Inter', sans-serif;
    background-color: #f0f4f8;
    padding: 1rem;
}

.journal-content {
    background: white;
    border: 1px solid #d1d5db;
    border-radius: 0.5rem;
    padding: 2rem;
    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
}

table {
    width: 100%;
    border-collapse: collapse;
}

th, td {
    border: 1px solid #000;
    padding: 0.5rem;
    text-align: left;
}

th {
    background-color: #f3f4f6;
    font-weight: bold;
}

/* Print styles */
@media print {
    @page {
        margin: 0.2in !important;
        size: auto !important;
    }

    .no-print {
        display: none !important;
    }

    .print-container {
        margin: 0 !important;
        padding: 0 !important;
        background: white !important;
    }

    .journal-content {
        box-shadow: none !important;
        border: none !important;
        padding: 0 !important;
    }

    /* Ensure all borders are visible */
    table, th, td {
        border: 1px solid #000 !important;
    }
}
</style>