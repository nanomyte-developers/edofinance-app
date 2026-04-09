<script setup lang="ts">
import html2pdf from 'html2pdf.js';
import Button from 'primevue/button';
import { computed, onMounted, ref } from 'vue';


// Props to receive voucher data
const props = defineProps({
    voucher: {
        type: Object,
        default: () => ({}),
    },
    administrativeSectorCode: {
        type: Object,
        default: () => ({}),
    },
    schedule: {
        type: Object,
        default: () => ({}),
    },
    economyCode: {
        type: Object,
        default: () => ({}),
    },
});

// Reactive data for the voucher form
const voucherForm = ref({
    station: 'Benin',
    month: new Date().toLocaleString('default', {
        month: 'long',
        year: 'numeric',
    }),
    pvNumber: '',
    departmentNo: '',
    economyCode: '',
    code: '',
    financialAuthority: 'G.W...2025',
    place: 'Benin City',
    date: new Date().toLocaleDateString('en-GB'),
    items: [],
    mdaName: '',
    mdaInitials: '',
    budgetCodeNumber: '',
    permanentSecretaryTitle: '',
});

// Function to round quantity to nearest whole number
const roundQuantity = (quantity: string | number) => {
    if (!quantity && quantity !== 0) return '';

    const quantityNum =
        typeof quantity === 'string'
            ? parseFloat(quantity.replace(/,/g, ''))
            : quantity;

    return Math.round(quantityNum).toString();
};

// Function to format unit price without decimal places for whole numbers
const formatUnitPrice = (price: string | number) => {
    if (!price && price !== 0) return '';

    const priceNum =
        typeof price === 'string' ? parseFloat(price.replace(/,/g, '')) : price;

    if (Number.isInteger(priceNum)) {
        return new Intl.NumberFormat('en-NG', {
            minimumFractionDigits: 0,
            maximumFractionDigits: 0,
        }).format(priceNum);
    } else {
        return new Intl.NumberFormat('en-NG', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2,
        }).format(priceNum);
    }
};

// Function to split amount into Naira and Kobo
const splitAmount = (amount: number) => {
    if (!amount && amount !== 0) return { naira: '', kobo: '00' };

    const amountStr = typeof amount === 'string' ? amount : amount.toString();
    const cleanAmount = amountStr.replace(/[^\d.]/g, '');
    const parts = cleanAmount.split('.');
    const naira = parts[0] || '0';

    let kobo = '00';
    if (parts.length > 1) {
        kobo = parts[1].padEnd(2, '0').substring(0, 2);
    }

    const formattedNaira = parseInt(naira).toLocaleString('en-NG');
    return { naira: formattedNaira, kobo };
};

// Enhanced Number to words function for Nigerian Naira
const convertToWords = (amount: string | number) => {
    if (!amount && amount !== 0) return '';

    const amountNum =
        typeof amount === 'string'
            ? parseFloat(amount.replace(/,/g, ''))
            : amount;

    if (amountNum === 0) return 'Zero Naira Only';

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

// Helper function to get permanent secretary title
const getPermanentSecretaryTitle = () => {
    const adminSector = props.administrativeSectorCode;
    if (!adminSector || !adminSector.name) return 'The Permanent Secretary';

    const mdaName = adminSector.name;
    const upperName = mdaName.toUpperCase();

    if (upperName.includes('MINISTRY')) {
        return `The Permanent Secretary, ${mdaName}`;
    } else if (
        upperName.includes('AGENCY') ||
        upperName.includes('COMMISSION')
    ) {
        return `The Director General, ${mdaName}`;
    } else if (
        upperName.includes('DEPARTMENT') ||
        upperName.includes('OFFICE')
    ) {
        return `The Director, ${mdaName}`;
    } else if (upperName.includes('BOARD')) {
        return `The Chairman, ${mdaName}`;
    } else {
        return `The Head, ${mdaName}`;
    }
};

// Extract department number from schedule number
const extractDepartmentNumber = () => {
    if (!props.schedule || !props.schedule.schedule_number) return '';

    const scheduleNumber = props.schedule.schedule_number;
    const parts = scheduleNumber.split('/');
    if (parts.length >= 3) {
        return parts[parts.length - 2] || '';
    }

    const matches = scheduleNumber.match(/\d+/);
    return matches ? matches[0] : '';
};

// Get Economic Code
const getEconomyCodeValue = () => {
    // Try from economyCode prop
    if (props.economyCode && props.economyCode.code) {
        return props.economyCode.code;
    }

    // Try from voucher items
    if (
        props.voucher &&
        props.voucher.items &&
        props.voucher.items.length > 0
    ) {
        const firstItem = props.voucher.items[0];
        if (firstItem.economy_code && firstItem.economy_code.code) {
            return firstItem.economy_code.code;
        }
    }

    return '';
};

// PDF Generation function
const generatePDF = () => {
    const element = document.getElementById('voucher-content');

    // console.log(element);

    const opt = {
        margin: [0.1, 0.1, 0.1, 0.1],
        filename: `voucher-${voucherForm.value.pvNumber || 'receipt'}.pdf`,
        image: {
            type: 'jpeg',
            quality: 1,
        },
        html2canvas: {
            scale: 3,
            useCORS: true,
            logging: false,
            letterRendering: true,
            width: element.scrollWidth - 500,
            height: element?.scrollHeight ,
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
    window.close();
};

// Computed total amount
const totalAmount = computed(() => {
    return props.voucher?.total_amount || 0;
});

// Computed for total amount split into naira and kobo
const totalAmountSplit = computed(() => {
    return splitAmount(totalAmount.value);
});

// Computed amount in words
const amountInWords = computed(() => {
    return convertToWords(totalAmount.value);
});

// Initialize with props data
onMounted(() => {
    // Set administrative sector code data
    if (props.administrativeSectorCode) {
        voucherForm.value.code = props.administrativeSectorCode.code || '';
        voucherForm.value.budgetCodeNumber =
            props.administrativeSectorCode.code || '';
        voucherForm.value.mdaName = props.administrativeSectorCode.name || '';
        voucherForm.value.mdaInitials =
            props.administrativeSectorCode.initials || '';
    }

    // Set permanent secretary title
    voucherForm.value.permanentSecretaryTitle = getPermanentSecretaryTitle();

    // Set Economic Code
    voucherForm.value.economyCode = getEconomyCodeValue();

    // Set department number from schedule
    voucherForm.value.departmentNo = extractDepartmentNumber();

    // Set voucher items
    if (
        props.voucher &&
        props.voucher.items &&
        props.voucher.items.length > 0
    ) {
        voucherForm.value.items = props.voucher.items.map((item) => {
            const amountSplit = splitAmount(item.sub_total || item.amount || 0);
            const roundedQuantity = roundQuantity(item.quantity);
            const formattedUnitPrice = formatUnitPrice(item.unit_price);

            return {
                date: formatDate(props.voucher.voucher_date),
                description: item.description || '',
                quantity: roundedQuantity,
                rate: formattedUnitPrice,
                amount: amountSplit,
            };
        });

        // Fill empty rows if needed
        const emptyRowsNeeded = Math.max(0, 4 - voucherForm.value.items.length);
        for (let i = 0; i < emptyRowsNeeded; i++) {
            voucherForm.value.items.push({
                date: '',
                description: '',
                quantity: '',
                rate: '',
                amount: { naira: '', kobo: '' },
            });
        }
    } else {
        voucherForm.value.items = Array(4)
            .fill()
            .map(() => ({
                date: '',
                description: '',
                quantity: '',
                rate: '',
                amount: { naira: '', kobo: '' },
            }));
    }

    // Set voucher number and date
    if (props.voucher && props.voucher.voucher_number) {
        voucherForm.value.pvNumber = props.voucher.voucher_number;
    }

    if (props.voucher && props.voucher.voucher_date) {
        voucherForm.value.date = formatDate(props.voucher.voucher_date);
    }
});

// Format date function
const formatDate = (dateString) => {
    if (!dateString) return '';
    return new Date(dateString).toLocaleDateString('en-GB');
};
</script>

<template>
    <div class="voucher-print-container">
        <div class="no-print mb-4 flex justify-end">
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
                class="ml-2 rounded-lg bg-blue-600 px-6 py-2 font-semibold text-white shadow-lg transition duration-150 hover:bg-blue-700"
            />
            <Button
                label="Close"
                icon="pi pi-times"
                @click="closeWindow"
                class="ml-2 rounded-lg bg-gray-600 px-6 py-2 font-semibold text-white shadow-lg transition duration-150 hover:bg-gray-700"
            />
        </div>

        <!-- Voucher Container with ID for PDF generation -->
        <div id="voucher-content" class="voucher-content">
            <div
                class="voucher-container mx-auto max-w-5xl rounded-xl border border-gray-300 bg-white p-6 shadow-2xl md:p-8"
            >
                <!-- Header Section -->
                <header class="mb-2 border-b border-black pb-2">
                    <div class="flex justify-between text-sm font-medium">
                        <span class="font-bold">ORIGINAL</span>
                        <span class="border border-black px-2 py-0.5 font-bold"
                            >TREASURY 1</span
                        >
                    </div>
                    <h1
                        class="mt-2 text-center text-xl font-extrabold md:text-2xl"
                    >
                        EDO STATE GOVERNMENT OF NIGERIA
                    </h1>
                    <p class="mt-1 text-center text-sm">
                        Checked and passed for payment at Benin City only
                    </p>
                    <h2 class="mt-1 text-center text-lg font-bold md:text-xl">
                        PAYMENT VOUCHERS
                        <span class="text-sm font-normal">(Other Charges)</span>
                    </h2>
                </header>

                <!-- Authorization Block -->
                <section class="mb-4 flex flex-col gap-4 text-sm md:flex-row">
                    <!-- Left Side: Code and Dept -->
                    <div class="flex-1">
                        <p class="mb-2 font-medium">
                            Administrative Code:
                            <span
                                class="input-line ml-2 inline-block w-48 border-b border-gray-400 font-mono"
                                >{{ voucherForm.code || 'N/A' }}</span
                            >
                            - {{ voucherForm.mdaName || 'Ministry/Agency' }}
                        </p>
                        <p class="mb-2 font-medium">
                            Economic Code:
                            <span
                                class="input-line ml-2 inline-block w-32 border-b border-gray-400 font-mono"
                                >{{ voucherForm.economyCode || 'N/A' }}</span
                            >
                        </p>
                        <p class="font-medium">
                            Dr. To: {{ voucherForm.permanentSecretaryTitle }}
                        </p>
                    </div>

                    <!-- Right Side: Station, Month, PV No -->
                    <div class="flex-1 rounded-lg border border-black p-3">
                        <div class="mb-3 flex items-center justify-between">
                            <span class="font-medium">Department No:</span>
                            <!-- <input
                                type="text"
                                :value="voucherForm.departmentNo"
                                class="input-line w-16 border-b border-gray-400 text-center"
                                placeholder="___"
                                readonly
                            /> -->
                            <span class="input-line w-16 border-b border-gray-400 text-center">{{
                                voucherForm.departmentNo
                            }}
                            </span>
                        </div>
                        <div
                            class="mb-2 grid grid-cols-4 gap-2 border-t border-black pt-2"
                        >
                            <span class="col-span-1 text-xs font-bold"
                                >STATION</span
                            >
                            <span class="col-span-1 text-xs font-bold"
                                >MONTH</span
                            >
                            <span class="col-span-2 text-xs font-bold"
                                >P.V No.</span
                            >
                        </div>
                        <div class="grid grid-cols-4 gap-2">
                            <!-- <input
                                type="text"
                                :value="voucherForm.station"
                                class="input-line col-span-1 border-b border-gray-400 text-center"
                                readonly
                            /> -->
                            <span class="input-line col-span-1 border-b border-gray-400 text-left text-xs">{{
                                voucherForm.station
                            }}
                            </span>
                            <!-- <input
                                type="text"
                                :value="voucherForm.month"
                                class="input-line col-span-2 border-b border-gray-400 text-center"
                                readonly
                            /> -->
                            <span class="input-line col-span-1 border-b border-gray-400 text-left">{{
                                voucherForm.month
                            }} </span>
                            <!-- <input
                                type="text"
                                :value="voucherForm.pvNumber"
                                class="input-line col-span-1 border-b border-gray-400 text-center font-mono"
                                placeholder="____"
                                readonly
                            /> -->
                            <span class="input-line col-span-2 border-b border-gray-400 text-left font-mono">{{
                                voucherForm.pvNumber
                            }}</span>
                        </div>
                    </div>
                </section>

                <!-- Line Item Table -->
                <section class="mb-2">
                    <table
                        class="voucher-table w-full border-collapse border border-black"
                    >
                        <thead>
                            <tr class="bg-gray-100 text-left text-xs font-bold">
                                <th class="w-1/12 border border-black p-2">
                                    Date
                                </th>
                                <th class="w-5/12 border border-black p-2">
                                    Detailed Description of Service or Article
                                </th>
                                <th class="w-1/12 border border-black p-2">
                                    Qty
                                </th>
                                <th class="w-1/12 border border-black p-2">
                                    Rate
                                </th>
                                <th
                                    class="w-3/12 border border-black p-2 text-center"
                                    colspan="2"
                                >
                                    AMOUNT
                                </th>
                            </tr>
                            <tr
                                class="bg-gray-100 text-center text-xs font-bold"
                            >
                                <th
                                    class="border border-black p-1"
                                    colspan="4"
                                ></th>
                                <th class="w-1/12 border border-black p-1">
                                    N
                                </th>
                                <th class="w-2/12 border border-black p-1">
                                    K
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            <!-- Dynamic Rows from Backend Data -->
                            <tr
                                v-for="(item, index) in voucherForm.items"
                                :key="index"
                                class="h-8"
                            >
                                <td class="border border-black p-2 text-xs">
                                    <!-- <input
                                        type="text"
                                        :value="item.date"
                                        class="input-line w-full text-center"
                                        placeholder="DD/MM/YYYY"
                                        readonly
                                    /> -->

                                    <span class="w-full text-center ">{{ item?.date || '' }}</span>
                                </td>
                                <td class="border border-black p-2 text-xs">
                                    <!-- <input
                                        type="text"
                                        :value="item.description"
                                        class="input-line w-full"
                                        readonly
                                    /> -->

                                    <span class="w-full ">{{ item?.description || '' }}</span>
                                </td>
                                <td class="border border-black p-2 text-xs">
                                    <!-- <input
                                        type="text"
                                        :value="item.quantity"
                                        class="input-line w-full text-center"
                                        readonly
                                    /> -->

                                    <span class="w-full text-center ">{{ item?.quantity || '' }}</span>
                                </td>
                                <td class="border border-black p-2 text-xs">
                                    <!-- <input
                                        type="text"
                                        :value="item.rate"
                                        class="input-line w-full text-right"
                                        readonly
                                    /> -->

                                    <span class="w-full text-right ">{{ item?.rate || '' }}</span>
                                </td>
                                <td
                                    class="border border-black p-2 text-right text-xs"
                                >
                                    <!-- <input
                                        type="text"
                                        :value="item.amount?.naira || ''"
                                        class="input-line w-full text-right"
                                        readonly
                                    /> -->

                                    <span class="w-full text-right ">{{ item?.amount?.naira || '' }}</span>
                                </td>
                                <td
                                    class="border border-black p-2 text-right text-xs"
                                >
                                    <!-- <input
                                        type="text"
                                        :value="item.amount?.kobo || ''"
                                        class="input-line w-full text-right"
                                        readonly
                                    /> -->

                                    <span class="w-full text-right text-xs">{{ item?.amount?.kobo || '00' }}</span>
                                </td>
                            </tr>

                            <!-- Total Row -->
                            <tr class="h-8 bg-gray-100 text-xs font-bold">
                                <td class="border border-black p-2" colspan="3">
                                    Financial Authority:
                                    <span
                                        class="input-line inline-block w-20 border-b border-gray-400"
                                        >{{
                                            voucherForm.financialAuthority
                                        }}</span
                                    >
                                </td>
                                <td class="border border-black p-2 text-center">
                                    TOTAL
                                </td>
                                <td class="border border-black p-2 text-right">
                                    <!-- <input
                                        type="text"
                                        :value="totalAmountSplit?.naira || ''"
                                        class="input-line w-full text-right font-bold"
                                        readonly
                                    /> -->
                                    <span class="w-full text-right font-bold">{{ totalAmountSplit?.naira || '00' }}</span>
                                </td>
                                <td class="border border-black p-2 text-right">
                                    <!-- <input
                                        type="text"
                                        :value="totalAmountSplit?.kobo || '00'"
                                        class="input-line w-full text-right font-bold"
                                        readonly
                                    /> -->
                                    <span class="w-full text-right font-bold">{{ totalAmountSplit?.kobo || '00' }}</span>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </section>

                <!-- Amount in Words Section -->
                <section class="mb-2 border border-black bg-gray-50 p-2">
                    <div class="flex items-start">
                        <span class="mt-1 mr-4 text-xs font-bold"
                            >Amount in Words:</span
                        >
                        <div class="flex-1">
                            <!-- <input
                                type="text"
                                :value="amountInWords"
                                class="input-line w-full text-sm font-medium italic"
                                readonly
                            /> -->
                             {{ amountInWords }}
                        </div>
                    </div>
                </section>

                <!-- Certification & Signatures -->
                <section class="space-y-2 text-xs">
                    <!-- Certification Text -->
                    <div class="leading-relaxed">
                        <p class="mb-2 font-medium">
                            *CERTIFY THAT the above account is correct, and was
                            incurred under the authority quoted, and that the
                            service <strong>has</strong> been duly performed,
                            and that the rate/price charge is/are according to
                            regulation/contract
                        </p>
                        <p class="mb-2 font-medium">
                            Fair and reasonable and that the amount of:
                            <strong>{{ convertToWords(totalAmount) }}</strong>
                        </p>
                        <p class="font-medium">
                            Can be paid under the sub-head quoted
                        </p>
                    </div>

                    <!-- Place, Date and First Signature -->
                    <div class="mt-2 flex items-start justify-between gap-8">
                        <div>
                            <p class="mb-2">Place: {{ voucherForm.place }}</p>
                            <p>
                                Date:
                                <span
                                    class="input-line ml-2 inline-block w-32 border-b border-gray-400"
                                    >{{ voucherForm.date }}</span
                                >
                            </p>
                        </div>
                        <div class="text-right">
                            <p class="mb-1">
                                Signature of Officer controlling Expenditure
                            </p>
                            <div
                                class="mb-1 inline-block w-4/5 border-b border-black"
                            >
                                .....................................................................
                            </div>
                            <p class="text-sm">
                                Rank......................................................................
                            </p>
                        </div>
                    </div>

                    <!-- Received Section -->
                    <div class="mt-4 border-t border-black pt-6">
                        <p class="mb-4">
                            RECEIVED this
                            <span
                                class="input-line mx-2 inline-block w-40 border-b border-gray-400"
                                >............................</span
                            >
                            date of
                            <span
                                class="input-line mx-2 inline-block w-24 border-b border-gray-400"
                                >.............</span
                            >
                            20
                            <span
                                class="input-line mx-2 inline-block w-24 border-b border-gray-400"
                                >.............</span
                            >
                            in payment of the above
                        </p>

                        <div class="flex items-start justify-between gap-8">
                            <div>
                                <p class="mb-2">
                                    Account the sum of
                                    <span
                                        class="input-line ml-2 inline-block w-48 border-b border-gray-400"
                                        >........................................................</span
                                    >
                                </p>
                                <p>
                                    Witness to mark
                                    <span
                                        class="input-line ml-2 inline-block w-48 border-b border-gray-400"
                                        >........................................................</span
                                    >
                                </p>
                            </div>
                            <div class="text-right">
                                <p class="mb-1">Signature of Receiver</p>
                                <div
                                    class="inline-block w-4/5 border-b border-black"
                                >
                                    .....................................................................
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Footer Note -->
                    <p class="pt-4 text-center italic">
                        *The Certificate must be made to apply to the
                        circumstance of the payment.
                    </p>
                </section>
            </div>
        </div>
    </div>
</template>

<style scoped>
/* Custom Font */
@import '../../../public/css2.css'; 

.voucher-print-container {
    font-family: 'Inter', sans-serif;
    background-color: #f0f4f8;
    padding: 2rem;
}

.voucher-content {
    background: white;
}

.voucher-container {
    background-color: white;
    border: 1px solid #d1d5db;
    border-radius: 0.75rem;
    box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
    padding: 1.5rem;
    max-width: 80rem;
    margin: 0 auto;
}

.header {
    text-align: center;
    margin-bottom: 1.5rem;
    border-bottom: 2px solid #000;
    padding-bottom: 0.5rem;
}

.header h1 {
    margin: 0;
    font-size: 1.5rem;
    font-weight: 800;
    line-height: 1.2;
}

.header h2 {
    margin: 0.25rem 0 0 0;
    font-size: 1.25rem;
    font-weight: 700;
}

.header p {
    margin: 0.25rem 0;
    font-size: 0.875rem;
}

.voucher-table {
    width: 100%;
    border-collapse: collapse;
    border: 1px solid #000;
    margin-bottom: 1.5rem;
    font-size: 0.75rem;
}

.voucher-table th,
.voucher-table td {
    border: 1px solid #000;
    padding: 0.25rem 0.5rem;
    text-align: left;
    vertical-align: top;
}

.voucher-table th {
    background-color: #f3f4f6;
    font-weight: 700;
    text-align: center;
}

.voucher-table .text-center {
    text-align: center;
}

.voucher-table .text-right {
    text-align: right;
}

.input-line {
    background: transparent;
    border: none;
    border-bottom: 1px dashed #9ca3af;
    outline: none;
    width: 100%;
    padding: 0.125rem 0;
    font-family: inherit;
    font-size: inherit;
}

.input-line:focus {
    border-bottom-color: #3b82f6;
}

.voucher-table input {
    width: 100%;
    background: transparent;
    border: none;
    outline: none;
    font-size: inherit;
    font-family: inherit;
}

.voucher-table input:focus {
    background-color: #f0f9ff;
}

.bg-gray-100 {
    background-color: #f3f4f6;
}

.bg-gray-50 {
    background-color: #f9fafb;
}

.border {
    border-width: 1px;
}

.border-black {
    border-color: #000;
}

.border-gray-300 {
    border-color: #d1d5db;
}

.font-bold {
    font-weight: 700;
}

.font-medium {
    font-weight: 500;
}

.font-semibold {
    font-weight: 600;
}

.font-extrabold {
    font-weight: 800;
}

.text-xs {
    font-size: 0.75rem;
    line-height: 1rem;
}

.text-sm {
    font-size: 0.875rem;
    line-height: 1.25rem;
}

.text-lg {
    font-size: 1.125rem;
    line-height: 1.75rem;
}

.text-xl {
    font-size: 1.25rem;
    line-height: 1.75rem;
}

.text-2xl {
    font-size: 1.5rem;
    line-height: 2rem;
}

.text-center {
    text-align: center;
}

.text-right {
    text-align: right;
}

.italic {
    font-style: italic;
}

.leading-relaxed {
    line-height: 1.625;
}

.flex {
    display: flex;
}

.justify-between {
    justify-content: space-between;
}

.justify-end {
    justify-content: flex-end;
}

.items-start {
    align-items: flex-start;
}

.items-center {
    align-items: center;
}

.flex-1 {
    flex: 1 1 0%;
}

.flex-col {
    flex-direction: column;
}

.grid {
    display: grid;
}

.grid-cols-4 {
    grid-template-columns: repeat(4, minmax(0, 1fr));
}

.gap-2 {
    gap: 0.5rem;
}

.gap-6 {
    gap: 1.5rem;
}

.gap-8 {
    gap: 2rem;
}

.col-span-1 {
    grid-column: span 1 / span 1;
}

.col-span-2 {
    grid-column: span 2 / span 2;
}

.col-span-3 {
    grid-column: span 3 / span 3;
}

.col-span-4 {
    grid-column: span 4 / span 4;
}

.m-0 {
    margin: 0;
}

.mb-1 {
    margin-bottom: 0.25rem;
}

.mb-2 {
    margin-bottom: 0.5rem;
}

.mb-3 {
    margin-bottom: 0.75rem;
}

.mb-4 {
    margin-bottom: 1rem;
}

.mb-6 {
    margin-bottom: 1.5rem;
}

.mt-1 {
    margin-top: 0.25rem;
}

.mt-2 {
    margin-top: 0.5rem;
}

.mt-4 {
    margin-top: 1rem;
}

.mt-6 {
    margin-top: 1.5rem;
}

.ml-2 {
    margin-left: 0.5rem;
}

.mr-4 {
    margin-right: 1rem;
}

.mx-auto {
    margin-left: auto;
    margin-right: auto;
}

.mx-2 {
    margin-left: 0.5rem;
    margin-right: 0.5rem;
}

.p-2 {
    padding: 0.5rem;
}

.p-3 {
    padding: 0.75rem;
}

.p-4 {
    padding: 1rem;
}

.p-6 {
    padding: 1.5rem;
}

.p-8 {
    padding: 2rem;
}

.pb-2 {
    padding-bottom: 0.5rem;
}

.pt-2 {
    padding-top: 0.5rem;
}

.pt-4 {
    padding-top: 1rem;
}

.pt-6 {
    padding-top: 1.5rem;
}

.w-1\/12 {
    width: 8.333333%;
}

.w-2\/12 {
    width: 16.666667%;
}

.w-3\/12 {
    width: 25%;
}

.w-5\/12 {
    width: 41.666667%;
}

.w-6\/12 {
    width: 50%;
}

.w-16 {
    width: 4rem;
}

.w-20 {
    width: 5rem;
}

.w-24 {
    width: 6rem;
}

.w-32 {
    width: 8rem;
}

.w-40 {
    width: 10rem;
}

.w-48 {
    width: 12rem;
}

.w-full {
    width: 100%;
}

.w-4\/5 {
    width: 80%;
}

.h-8 {
    height: 2rem;
}

.rounded-lg {
    border-radius: 0.5rem;
}

.rounded-xl {
    border-radius: 0.75rem;
}

.border-b {
    border-bottom-width: 1px;
}

.border-t {
    border-top-width: 1px;
}

.shadow-2xl {
    box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
}

.shadow-lg {
    box-shadow:
        0 10px 15px -3px rgba(0, 0, 0, 0.1),
        0 4px 6px -2px rgba(0, 0, 0, 0.05);
}

.text-white {
    color: #fff;
}

.bg-blue-600 {
    background-color: #2563eb;
}

.bg-green-600 {
    background-color: #059669;
}

.bg-gray-600 {
    background-color: #4b5563;
}

.hover\:bg-blue-700:hover {
    background-color: #1d4ed8;
}

.hover\:bg-green-700:hover {
    background-color: #047857;
}

.hover\:bg-gray-700:hover {
    background-color: #374151;
}

.transition {
    transition-property:
        background-color, border-color, color, fill, stroke, opacity,
        box-shadow, transform;
    transition-timing-function: cubic-bezier(0.4, 0, 0.2, 1);
    transition-duration: 150ms;
}

.font-mono {
    font-family:
        ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas,
        'Liberation Mono', 'Courier New', monospace;
}

.no-print {
    display: block;
}

/* Responsive design for screen */
@media (min-width: 768px) {
    .md\:flex-row {
        flex-direction: row;
    }

    .md\:p-8 {
        padding: 2rem;
    }

    .md\:text-2xl {
        font-size: 1.5rem;
        line-height: 2rem;
    }

    .md\:text-xl {
        font-size: 1.25rem;
        line-height: 1.75rem;
    }
}

/* Print styles that remove headers/footers */
@media print {
    @page {
        margin: 0.2in !important;
        size: auto !important;
        margin-header: 0 !important;
        margin-footer: 0 !important;
    }

    body,
    .voucher-print-container {
        margin: 0 !important;
        padding: 0 !important;
        background: white !important;
        -webkit-print-color-adjust: exact !important;
        print-color-adjust: exact !important;
    }

    .no-print {
        display: none !important;
    }

    .voucher-container {
        box-shadow: none !important;
        border: none !important;
        max-width: 100% !important;
        padding: 0 !important;
        margin: 0 !important;
        border-radius: 0 !important;
    }

    /* Ensure all borders are visible */
    .voucher-table,
    .voucher-table th,
    .voucher-table td {
        border: 1px solid #000 !important;
    }
}

/* Ensure good print quality for PDF */
@media print {
    * {
        -webkit-print-color-adjust: exact;
        print-color-adjust: exact;
    }
}
</style>
