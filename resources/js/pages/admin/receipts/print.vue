<template>

    <Head :title="'Receipt - ' + receipt.receipt_number" />

    <!-- Print Button (non-printable) -->
    <div class="no-print print-button bg-gray-100 p-4 text-center">
        <button @click="handlePrint"
            class="rounded-lg bg-blue-600 px-6 py-2 text-white transition-colors hover:bg-blue-700">
            Print Receipt
        </button>
        <p class="mt-2 text-sm text-gray-600">
            This page will auto-print. Use this button if it doesn't print
            automatically.
        </p>
    </div>

    <!-- Main Receipt Container -->
    <div class="receipt-container relative mx-auto max-w-4xl overflow-hidden bg-white p-10 font-serif text-gray-900">
        <!-- A5 Watermark -->
        <div class="pointer-events-none absolute top-1/2 right-1/4 -translate-y-1/2 transform opacity-10 select-none">
            <span class="text-[200px] font-bold text-red-900">{{
                receipt.bank_tag
            }}</span>
        </div>

        <!-- Circle Watermark -->
        <div
            class="pointer-events-none absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 transform opacity-10 select-none">
            <div class="flex h-56 w-56 items-center justify-center rounded-full border-[12px] border-red-900">
                <span class="text-[70px] font-bold text-green-900">T-H</span>
            </div>
        </div>

        <div class="relative z-10 " style="margin-top: -30px;">
            <!-- Header Section with Logo -->
            <div class="mb-1 text-center">
                <!-- Logo and Bank Name Row -->
                <div class="mb-1 flex items-center justify-center gap-4">
                    <!-- Left Logo (optional) -->
                    <div class="h-16 w-16 flex-shrink-0">
                        <!-- Placeholder for left logo - replace with actual logo -->
                        <!-- <div
                            class="flex h-full w-full items-center justify-center rounded-full border-2 border-blue-800 bg-blue-50"
                        >
                            <span class="text-lg font-bold text-blue-800"
                                >ESG</span
                            >
                        </div> -->
                    </div>

                    <!-- Bank Name -->
                    <div class="flex-1">
                        <p class="text-sm font-bold italic underline">
                            {{ receipt.bank_name || 'Access Bank PLC' }}
                        </p>
                    </div>

                    <!-- Right Logo (optional) -->
                    <div class="h-16 w-16 flex-shrink-0">
                        <!-- <div
                            class="flex h-full w-full items-center justify-center rounded-full border-2 border-green-800 bg-green-50"
                        >
                            <span class="text-lg font-bold text-green-800"
                                >NG</span
                            >
                        </div> -->
                    </div>
                </div>

                <!-- Main Logo in Center -->
                <div class="mb-4 flex justify-center">
                    <div class="relative">
                        <!-- Main Logo Container -->
                        <!-- <div
                            class="flex h-24 w-24 items-center justify-center rounded-full border-4 border-red-800 bg-white shadow-lg"
                        > -->
                        <div class="flex h-24 w-24 items-center justify-center rounded-full bg-white shadow-lg">
                            <!-- Replace this with your actual logo image -->
                            <!-- <img src="/images/edo-state-logo.png" alt="Edo State Logo" class="h-20 w-20 object-contain" /> -->

                            <!-- Placeholder logo with coat of arms style -->
                            <div class="flex flex-col items-center">
                                <div class="mb-1 text-2xl font-bold text-red-800">

                                </div>
                                <div class="text-center">
                                    <!-- <div
                                        class="text-xs font-bold tracking-wider text-gray-800 uppercase"
                                    >
                                        EDO
                                    </div>
                                    <div
                                        class="text-xs font-bold tracking-wider text-gray-800 uppercase"
                                    >
                                        STATE
                                    </div> -->
                                    <img :src="logo" alt="EDFS Logo" class="mr-2 h-54 w-44 object-contain" />
                                </div>

                            </div>
                        </div>

                        <!-- Optional: Seal or crest around logo -->
                        <div class="absolute -inset-2 rounded-full border-2 border-dotted border-amber-600 opacity-50">
                        </div>
                    </div>
                </div>

                <!-- State Title -->
                <h1 class="text-xl font-bold  uppercase">
                    EDO STATE GOVERNMENT OF NIGERIA
                </h1>

                <!-- Subtitle -->
                <p class="mt-1 text-sm font-semibold text-gray-600 italic">
                    <span class="mr-2 text-sm">RV. NO.</span>
                    <span class="text-3xl font-bold text-red-800 italic">{{
                        receipt.receipt_number
                    }}</span>
                </p>

                <!-- <div class="ml-10">
                    <div class="flex items-baseline">
                        <span class="mr-2 text-sm">RV. NO.</span>
                        <span class="text-3xl font-bold text-red-800 italic">{{
                            receipt.receipt_number
                            }}</span>
                    </div>
                </div> -->
            </div>

            <!-- Receipt Details -->
            <div class="mt-2 flex items-start justify-between ">
                <div class="flex-1 space-y-4 ">
                    <div class="flex items-end">
                        <span class="text-sm whitespace-nowrap col-2">Head of Receipt:</span>
                        <span class="ml-2 flex-1 border-b border-dotted border-black px-2 pb-1 text-lg font-bold col-2">
                            {{ formatEcoCode(receipt.eco_code) }}
                        </span>
                        <span class="text-sm whitespace-nowrap col-8">{{
                            formatMdaName(receipt.mda_name)
                        }}</span>
                    </div>
                    <div class="flex items-end">
                        <span class="text-sm whitespace-nowrap">Sub-Head:</span>
                        <span class="ml-2 flex-1 border-b border-dotted border-black px-2 pb-1 text-lg font-bold">
                            {{ formatEcoCodeItem(receipt.eco_code_item) }}
                        </span>
                    </div>
                </div>
                <!-- <div class="ml-10">
                    <div class="flex items-baseline">
                        <span class="mr-2 text-sm">RV. NO.</span>
                        <span class="text-3xl font-bold text-red-800 italic">{{
                            receipt.receipt_number
                            }}</span>
                    </div>
                </div> -->
            </div>

            <!-- To Accountant-General -->
            <p class="mt-4 mb-2 font-bold">
                To the Accountant-General, Edo State.
            </p>

            <!-- Amount in Words Section -->
            <div class="mt-4 space-y-1">
                <div class="flex items-baseline">
                    <span class="mr-2 italic">Please receive the sum of</span>
                    <div class="flex-1 border-b border-dotted border-black px-2 text-lg font-semibold italic">
                        {{ amountWords.line1 }} {{ amountWords.line2 }} {{ amountWords.line3
                        }} Naira <span v-if="amountWords.kobo === 'zero'">Only</span> <span
                            v-if="amountWords.kobo !== 'zero'"> {{ amountWords.kobo }} Kobo Only </span>
                    </div>
                </div>
                <!-- <div
                    class="flex min-h-[2.8rem] items-end border-b border-dotted border-black px-2 text-lg font-semibold italic"
               v-if="amountWords.line2" >
                    {{ amountWords.line2 }}
                </div>
                <div class="flex items-end">
                    <div
                        class="min-h-[2.8rem] flex-1 border-b border-dotted border-black px-2 text-lg font-semibold italic"
                   v-if="amountWords.line3" >
                        {{ amountWords.line3 }}
                    </div> -->
                <!-- <span class="ml-2 italic">Naira.</span>
                <div class="min-h-[2.8rem] w-32 border-b border-dotted border-black px-2 text-center text-lg font-semibold italic"
                    v-if="amountWords.kobo">
                    {{ amountWords.kobo }}
                </div>
                <span class="ml-2 italic">Kobo</span> -->
                <!-- </div> -->
            </div>

            <!-- Purpose Section -->
            <div class="mt-4">
                <div class="flex items-baseline">
                    <span class="mr-2 font-bold italic">Being:</span>
                    <div class="flex-1 border-b border-dotted border-black px-2 text-xl font-semibold italic">
                        {{
                            receipt.activity || 'Payment for services rendered'
                        }}
                    </div>
                </div>
                <div
                    class="flex min-h-[2.8rem] items-end border-b border-dotted border-black px-2 text-xl font-semibold italic">
                    {{ receipt.mda_name }}
                </div>
            </div>

            <!-- Footer Section with Amount, Date, and Signatures -->
            <div class="mt-4 flex items-end justify-between">
                <div class="space-y-6">
                    <!-- Amount in Figures -->
                    <div class="flex items-center text-2xl font-bold">
                        <span class="mr-2">₦</span>
                        <span class="min-w-[150px] border-b-2 border-dotted border-black px-6 pb-1">
                            {{ formatCurrency(receipt.amount) }}
                        </span>
                    </div>

                    <!-- Date -->
                    <div class="flex items-baseline text-2xl font-semibold">
                        <span class="border-b border-dotted border-black px-6 pb-1">{{ getDateDayMonth() }}</span>
                        <span class="mx-2">/</span>
                        <span class="border-b border-dotted border-black px-6 pb-1">20{{ getDateYear() }}</span>
                    </div>
                </div>

                <!-- Signatures -->
                <div class="w-72 space-y-14 mt-4">
                    <div class="relative text-center">
                        <div
                            class="font-signature absolute -top-10 left-1/2 -translate-x-1/2 text-3xl text-blue-900 italic opacity-60">
                            &nbsp;
                        </div>
                        <div class="w-full border-b border-dotted border-black"></div>
                        <p class="mt-2 text-[11px] font-extrabold tracking-tighter uppercase">
                            Signature of Payer
                        </p>
                    </div>
                    <div class="text-center">
                        <div class="w-full border-b border-dotted border-black"></div>
                        <p class="mt-2 text-[11px] font-extrabold tracking-tighter uppercase">
                            Witness to Mark
                        </p>
                    </div>
                </div>
            </div>

            <!-- Note Section -->
            <div class="mt-2 border-t border-gray-400 pt-3 text-[11px] leading-tight font-bold italic">
                <p>
                    NOTE: The person making this is to be given a Receipt from a
                    book of numbered receipts and is requested to sign the
                    counterfoil in the book.
                </p>
            </div>

            <!-- Classification Badge -->
            <div class="mt-2 text-right">
                <span class="inline-block rounded bg-blue-100 px-4 py-2 text-sm font-bold text-blue-800 uppercase">
                    {{ receipt.classification || 'Revenue' }}
                </span>
            </div>

            <!-- Footer with small logos -->
            <div class="mt-4 flex items-center justify-between border-t border-gray-300 pt-4">
                <div class="flex items-center gap-2">
                    <div class="h-8 w-8 rounded-full border border-gray-400 bg-gray-100"></div>
                    <span class="text-xs text-gray-500">Official Seal</span>
                </div>
                <div class="text-center text-xs text-gray-500">
                    <p>Issued by Edo State Government</p>
                    <p class="mt-1">www.edostate.gov.ng</p>
                </div>
                <div class="flex items-center gap-2">
                    <span class="text-xs text-gray-500">Security Stamp</span>
                    <div class="h-8 w-8 rounded-full border border-gray-400 bg-gray-100"></div>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { Head } from '@inertiajs/vue3';
import { computed, onMounted } from 'vue';
import logo from '../../../../../public/images/logo.jpg';

const props = defineProps({
    receipt: {
        type: Object,
        required: true,
    },
});

// Format currency
const formatCurrency = (val) => {
    return new Intl.NumberFormat('en-NG', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    }).format(val || 0);
};

// Convert amount to words
const convertNumberToWords = (amount) => {
    if (isNaN(amount) || amount === 0) return ['Zero', 'Naira', ''];

    const units = [
        '',
        'One',
        'Two',
        'Three',
        'Four',
        'Five',
        'Six',
        'Seven',
        'Eight',
        'Nine',
    ];
    const teens = [
        'Ten',
        'Eleven',
        'Twelve',
        'Thirteen',
        'Fourteen',
        'Fifteen',
        'Sixteen',
        'Seventeen',
        'Eighteen',
        'Nineteen',
    ];
    const tens = [
        '',
        '',
        'Twenty',
        'Thirty',
        'Forty',
        'Fifty',
        'Sixty',
        'Seventy',
        'Eighty',
        'Ninety',
    ];

    const convertHundreds = (num) => {
        let result = '';
        // Hundreds
        if (num >= 100) {
            result += units[Math.floor(num / 100)] + ' Hundred ';
            num %= 100;
        }
        // Tens and units
        if (num >= 20) {
            result += tens[Math.floor(num / 10)] + ' ';
            num %= 10;
        } else if (num >= 10) {
            result += teens[num - 10] + ' ';
            num = 0;
        }
        // Units
        if (num > 0) {
            result += units[num] + ' ';
        }
        return result.trim();
    };

    let words = '';
    let nairaAmount = Math.floor(amount);
    let koboAmount = Math.round((amount - nairaAmount) * 100);

    // Billions
    if (nairaAmount >= 1000000000) {
        words +=
            convertHundreds(Math.floor(nairaAmount / 1000000000)) + ' Billion ';
        nairaAmount %= 1000000000;
    }

    // Millions
    if (nairaAmount >= 1000000) {
        words +=
            convertHundreds(Math.floor(nairaAmount / 1000000)) + ' Million ';
        nairaAmount %= 1000000;
    }

    // Thousands
    if (nairaAmount >= 1000) {
        words += convertHundreds(Math.floor(nairaAmount / 1000)) + ' Thousand ';
        nairaAmount %= 1000;
    }

    // Hundreds
    if (nairaAmount > 0) {
        words += convertHundreds(nairaAmount) + ' ';
    }

    words = words.trim();

    // Split words into lines for display
    const wordsArray = words.split(' ');
    const result = [];
    let currentLine = '';

    for (const word of wordsArray) {
        if ((currentLine + ' ' + word).length > 30) {
            result.push(currentLine.trim());
            currentLine = word;
        } else {
            currentLine += (currentLine ? ' ' : '') + word;
        }
    }

    if (currentLine) {
        result.push(currentLine.trim());
    }

    // Pad with empty strings if needed
    while (result.length < 3) {
        result.push('');
    }

    return {
        line1: result[0] || '',
        line2: result[1] || '',
        line3: result[2] || '',
        kobo:
            koboAmount > 0 ? convertHundreds(koboAmount).toLowerCase() : 'zero',
    };
};

// Computed properties for amount words
const amountWords = computed(() => {
    return convertNumberToWords(props.receipt.amount);
});

// Format economy code with padding
const formatEcoCode = (code) => {
    if (!code) return ' ';
    return code.padEnd(2, '_') + '';
};

// Format economy code item with padding
const formatEcoCodeItem = (code) => {
    if (!code) return '___________';
    return code.padEnd(15, '_') + '—';
};

// Format MDA name with padding
const formatMdaName = (name) => {
    if (!name) return '__';
    // Keep the name as is, but ensure it fits in the space
    return name;
};

// Format MDA code with padding
const formatMdaCode = (code) => {
    if (!code) return '___________';
    return code.padEnd(15, '_') + '—';
};

// Get date day and month with leading zeros for both
const getDateDayMonth = () => {
    if (!props.receipt.receipt_date) return '  /  ';
    const date = new Date(props.receipt.receipt_date);

    // Get day with leading zero
    const day = date.getDate().toString().padStart(2, '0');

    // Get month with leading zero
    const month = (date.getMonth() + 1).toString().padStart(2, '0');

    return `${day} / ${month}`;
};

// Get date year
const getDateYear = () => {
    if (!props.receipt.receipt_date) return '__';
    const date = new Date(props.receipt.receipt_date);
    return date.getFullYear().toString().slice(-2);
};

// Handle print button click
const handlePrint = () => {
    window.print();
};

// Auto-print when component mounts
onMounted(() => {
    // Short delay to ensure content is rendered
    setTimeout(() => {
        window.print();

        // Optional: close window after print
        window.onafterprint = function () {
            window.close();
        };
    }, 500);
});
</script>

<style scoped>
@import url('https://fonts.googleapis.com/css2?family=Dancing+Script&display=swap');

.receipt-container {
    background-color: #fffefb;
    /* Off-white paper tint */
}

/* Custom font for handwriting feel */
.font-signature {
    font-family: 'Dancing Script', cursive;
}

/* Print styles */
@media print {
    @page {
        size: A5;
        margin: 0;
    }

    body {
        margin: 0 !important;
        padding: 0 !important;
    }

    .receipt-container {
        box-shadow: none !important;
        border: none !important;
        padding: 20px !important;
        margin: 0 !important;
        width: 100% !important;
        max-width: none !important;
        page-break-inside: avoid;
    }

    /* Remove all non-printable elements */
    .no-print,
    .print-button {
        display: none !important;
    }
}

/* Screen styles */
@media screen {
    .receipt-container {
        border: 1px solid #e5e7eb;
        box-shadow:
            0 4px 6px -1px rgba(0, 0, 0, 0.1),
            0 2px 4px -1px rgba(0, 0, 0, 0.06);
        margin: 20px auto;
    }
}
</style>
