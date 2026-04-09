<template>
    <div
        id="remittance-print"
        class="mx-auto max-w-[1050px] bg-gray-50 p-4 sm:p-10 print:bg-white print:p-0"
    >
        <div class="mb-8 flex justify-end gap-3 print:hidden">
            <Button
                label="Print"
                icon="pi pi-print"
                @click="handlePrint"
                severity="primary"
            />
            <Button
                label="Back"
                icon="pi pi-arrow-left"
                @click="$router.back()"
                severity="secondary"
                outlined
            />
        </div>

        <div
            class="remittance-receipt relative border border-gray-300 bg-white p-[12mm] text-black shadow-lg print:border-none print:shadow-none"
        >
            <div class="relative mb-2 flex items-start justify-between">
                <div
                    class="absolute top-0 left-0 font-serif text-[85px] leading-[0.8] text-red-700 select-none"
                >
                    <img
                        :src="logo"
                        alt="EDFS Logo"
                        class="mr-2 h-34 w-24 object-contain"
                    />
                </div>

                <div class="flex-grow text-center">
                    <h1
                        class="text-[19px] font-bold tracking-[0.2em] uppercase"
                    >
                        EDO STATE OF NIGERIA
                    </h1>
                    <div class="mt-4 inline-block text-left">
                        <h2 class="text-[17px] leading-tight font-bold">
                            PAYMENT VOUCHER
                        </h2>
                        <p class="text-[12px] font-bold tracking-tight">
                            GENERAL LEDGER ACCOUNT REMITTANCE
                        </p>
                    </div>
                </div>

                <div class="pt-2 text-right">
                    <div
                        class="mb-1 pr-2 font-serif text-[32px] leading-none text-red-500/80 italic"
                    >
                        IGR
                    </div>
                    <div
                        class="text-[13px] font-bold tracking-tighter uppercase"
                    >
                        TREASURY 18
                    </div>
                </div>
            </div>

            <div class="mb-6 flex justify-end">
                <table
                    class="w-[340px] border-collapse border-[1.5px] border-black text-[11px]"
                >
                    <thead>
                        <tr class="text-center font-bold uppercase">
                            <td
                                class="w-1/3 border-[1.5px] border-black bg-gray-50/50 px-2 py-1"
                            >
                                STATION
                            </td>
                            <td
                                class="w-1/3 border-[1.5px] border-black bg-gray-50/50 px-2 py-1"
                            >
                                MONTH
                            </td>
                            <td
                                class="w-1/3 border-[1.5px] border-black bg-gray-50/50 px-2 py-1"
                            >
                                P.V. NO.
                            </td>
                        </tr>
                    </thead>
                    <tbody>
                        <tr class="h-9 text-center">
                            <td
                                class="border-[1.5px] border-black px-1 font-serif text-[15px] text-blue-900 italic"
                            >
                                {{ remittance.treasury || 'Benin City' }}
                            </td>
                            <td
                                class="border-[1.5px] border-black px-1 font-serif text-[15px] text-blue-900 italic"
                            >
                                {{ formatMonthYear(remittance.transfer_date) }}
                            </td>
                            <td
                                class="border-[1.5px] border-black px-1 font-serif text-[15px] font-bold text-red-600 italic"
                            >
                                {{ remittance.receipt_number }}
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <!-- <div class="flex border-[1.5px] border-black">
                <div class="flex-[3] border-r-[1.5px] border-black">
                    <div
                        class="min-h-[160px] border-b-[1.5px] border-black p-3 text-[13px] leading-relaxed"
                    >
                        <p class="mb-4">
                            I CERTIFY that I have this day remitted to Office of
                            the Accountant-General, Sapele Road, Benin City, the
                            sum of
                            <span
                                class="font-bold underline decoration-dotted"
                                >{{
                                    convertNumberToWords(remittance.amount)
                                }}</span
                            >.
                        </p>

                        <p class="mt-4">
                            {{ remittance.narration || 'Payment for IGR' }}
                        </p>
                    </div>

                    <div
                        class="border-b-[1.5px] border-black py-1 text-center text-[13px] font-bold tracking-widest"
                    >
                        PARTICULARS
                    </div>
                    <table class="w-full text-[12px]">
                        <thead>
                            <tr
                                class="border-b-[1.5px] border-black font-bold uppercase"
                            >
                                <td
                                    class="w-3 border-r-[1.5px] border-black py-1 text-left"
                                >
                                    NO.
                                </td>
                                <td
                                    class="border-r-[1.5px] border-black px-2 py-1"
                                >
                                    DRAWER
                                </td>
                                <td class="px-2 py-1 text-center">BANK</td>
                            </tr>
                        </thead>
                        <tbody>
                            <tr class="h-40 align-top">
                                <td
                                    class="border-r-[1.5px] border-black pt-2 text-left"
                                >
                                    1
                                </td>
                                <td
                                    class="border-r-[1.5px] border-black px-2 pt-2 italic"
                                >
                                    Accountant-General, Edo State, Benin City.
                                </td>
                                <td
                                    class="px-2 pt-2 text-center font-bold uppercase"
                                >
                                    {{
                                        remittance.source_bank.bank_name ||
                                        'N/A'
                                    }}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div class="flex min-w-[200px] flex-1 flex-col">
                    <div
                        class="flex h-8 border-b-[1.5px] border-black text-center text-[12px] font-bold"
                    >
                        <div
                            class="flex flex-1 items-center justify-center border-r-[1.5px] border-black"
                        >
                            N
                        </div>
                        <div
                            class="justify-right flex w-10 items-center border-r-[1.5px] border-black pl-10"
                        >
                            K
                        </div>
                        <div
                            class="flex flex-1 items-center justify-center border-r-[1.5px] border-black"
                        >
                            N
                        </div>
                        <div class="justify-right flex w-10 items-center pl-10">
                            K
                        </div>
                    </div>
                    <div class="flex flex-grow text-[14px] font-bold">
                        <div
                            class="flex-1 border-r-[1.5px] border-black pt-10 pr-2 text-right"
                        >
                            {{ formatNumberOnly(remittance.amount) }}
                        </div>
                        <div
                            class="w-10 border-r-[1.5px] border-black pt-10 text-center"
                        ></div>
                        <div
                            class="flex-1 border-r-[1.5px] border-black pt-10 pr-2 text-right"
                        >
                            {{ formatNumberOnly(remittance.amount) }}
                        </div>
                        <div class="w-10 pt-10 text-center"></div>
                    </div>
                </div>
            </div> -->

            <div class="flex border-[1.5px] border-black">
                <div class="flex-[3] border-r-[1.5px] border-black">
                    <div
                        class="min-h-[160px] border-b-[1.5px] border-black p-3 text-[13px] leading-relaxed"
                    >
                        <p class="mb-4">
                            I CERTIFY that I have this day remitted to Office of
                            the Accountant-General, Sapele Road, Benin City, the
                            sum of
                            <span class="font-bold underline decoration-dotted">
                                {{
                                    convertNumberToWords(remittance.amount)
                                }} </span
                            >.
                        </p>
                        <p class="mt-4 font-medium italic">
                            {{
                                remittance.narration ||
                                'Being remittance of IGR for the period.'
                            }}
                        </p>
                    </div>

                    <div
                        class="border-b-[1.5px] border-black py-1 text-center text-[13px] font-bold tracking-widest"
                    >
                        PARTICULARS
                    </div>
                    <table class="w-full text-[12px]">
                        <thead>
                            <tr
                                class="border-b-[1.5px] border-black font-bold uppercase"
                            >
                                <td
                                    class="w-2 border-r-[0.5px] border-black py-1 text-left"
                                >
                                    NO.
                                </td>
                                <td
                                    class="border-r-[1.5px] border-black px-2 py-1 pr-10 text-left"
                                >
                                    DRAWER
                                </td>
                                <td class="px-2 py-1 text-left">BANK</td>
                            </tr>
                        </thead>
                        <tbody>
                            <tr class="h-40 align-top">
                                <td
                                    class="border-r-[1.5px] border-black pt-2 text-left"
                                >
                                    1
                                </td>
                                <td
                                    class="border-r-[1.5px] border-black px-2 pt-2 italic"
                                >
                                    Accountant-General, Edo State, Benin City.
                                </td>
                                <td
                                    class="px-2 pt-2 text-center font-bold uppercase"
                                >
                                    {{
                                        remittance.source_bank?.bank_name ||
                                        'N/A'
                                    }}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div class="flex min-w-[200px] flex-1 flex-col">
                    <div
                        class="flex h-8 border-b-[1.5px] border-black text-center text-[12px] font-bold"
                    >
                        <div
                            class="flex flex-1 items-center justify-center border-r-[1.5px] border-black"
                        >
                            N
                        </div>
                        <div
                            class="flex w-10 items-center justify-center border-r-[1.5px] border-black"
                        >
                            K
                        </div>
                        <div
                            class="flex flex-1 items-center justify-center border-r-[1.5px] border-black"
                        >
                            N
                        </div>
                        <div class="flex w-10 items-center justify-center">
                            K
                        </div>
                    </div>

                    <div class="flex flex-grow text-[14px] font-bold">
                        <div
                            class="flex-1 border-r-[1.5px] border-black pt-10 pr-2 text-right font-mono"
                        >
                            {{
                                formatNumberOnly(remittance.amount).split(
                                    '.',
                                )[0]
                            }}
                        </div>
                        <div
                            class="w-10 border-r-[1.5px] border-black pt-10 text-center font-mono"
                        >
                            {{
                                formatNumberOnly(remittance.amount).split(
                                    '.',
                                )[1] || '00'
                            }}
                        </div>
                        <div
                            class="flex-1 border-r-[1.5px] border-black pt-10 pr-2 text-right font-mono text-gray-700"
                        >
                            {{
                                formatNumberOnly(remittance.amount).split(
                                    '.',
                                )[0]
                            }}
                        </div>
                        <div
                            class="w-10 pt-10 text-center font-mono text-gray-700"
                        >
                            {{
                                formatNumberOnly(remittance.amount).split(
                                    '.',
                                )[1] || '00'
                            }}
                        </div>
                    </div>
                </div>
            </div>

            <div class="mt-12 flex items-start justify-between text-[13px]">
                <div class="pt-2 italic">
                    Dated:
                    <span class="ml-4">{{
                        formatMonthYear(remittance.transfer_date)
                    }}</span>
                </div>
                <div class="w-1/3 text-center">
                    <p class="mb-0 text-[16px] font-bold">
                        {{ remittance.created_by_name || 'Okhomina Godwin' }}
                    </p>
                    <div class="my-1 h-[1px] w-full bg-black"></div>
                    <p class="text-[11px] font-bold">for: Accountant-General</p>
                </div>
            </div>

            <div class="mt-10 space-y-4 text-[13px]">
                <div class="flex flex-wrap items-baseline gap-y-4">
                    <span class="mr-2"
                        >Received the above mentioned remittance which has been
                        checked by me and amounted to</span
                    >
                    <span
                        class="min-w-[200px] flex-grow border-b border-dotted border-black px-2 font-serif text-[15px] text-blue-800 italic"
                    >
                        {{
                            convertNumberToWords(remittance.amount).split(
                                ' Naira',
                            )[0]
                        }}
                    </span>
                </div>

                <div class="flex items-baseline">
                    <span
                        class="h-5 flex-grow border-b border-dotted border-black px-2"
                    ></span>
                    <span class="mx-2">Naira</span>
                    <span
                        class="h-5 w-24 border-b border-dotted border-black px-2 text-center font-serif text-[15px] text-blue-800 italic"
                    >
                        Zero
                    </span>
                    <span class="ml-2">kobo.</span>
                </div>

                <div class="grid grid-cols-2 gap-20 pt-4">
                    <div class="space-y-4">
                        <div class="flex items-baseline gap-2">
                            <span>See</span>
                            <span
                                class="flex-grow border-b border-dotted border-black px-2 font-serif text-blue-800 italic"
                            >
                                Treasury Receipt
                            </span>
                            <span>Receipt/Voucher No.</span>
                            <span
                                class="w-24 border-b border-dotted border-black px-2 font-serif text-blue-800 italic"
                            >
                            </span>
                            <span>dated</span>
                            <span
                                class="w-24 border-b border-dotted border-black px-2 font-serif text-blue-800 italic"
                            >
                                {{ formatMonthYear(remittance.transfer_date) }}
                            </span>
                        </div>
                        <div class="flex items-baseline gap-2">
                            <span>Station Office</span>
                            <span
                                class="flex-grow border-b border-dotted border-black px-2 font-serif text-blue-800 italic"
                            >
                                {{ remittance.treasury || 'Benin City' }}
                            </span>
                        </div>
                    </div>

                    <div class="flex flex-col justify-end">
                        <div
                            class="border-t border-black pt-1 text-center text-[11px] font-bold uppercase"
                        >
                            Receiver's Signature
                        </div>
                    </div>
                </div>
            </div>

            <div
                class="mt-10 border-t border-gray-200 pt-4 text-[10px] leading-tight uppercase italic"
            >
                <p>
                    <span class="font-bold">NOTE:</span> Four copies of this
                    voucher, two a payment and other two a Receipt Voucher (in
                    duplicate) are required to be sent in respect of each
                    remittance completed by the receiving officer who returns
                    the Payment Voucher (in duplicate) with a Treasury Receipt
                    to the back of the original copy to the remitting officer.
                    Both officers are then in possession of a voucher in
                    duplicate signed by both the Remitter and Receiver to
                    support their accounts.
                </p>
            </div>
        </div>
    </div>
</template>

<script setup lang="ts">
import Button from 'primevue/button';
import logo from '../../../public/images/logo.jpg';

const props = defineProps<{
    remittance: any;
}>();

const formatMonthYear = (dateString: string) => {
    if (!dateString) return 'February, 2025';
    const date = new Date(dateString);
    return date.toLocaleDateString('en-US', { month: 'long', year: 'numeric' });
};

const formatNumberOnly = (val: number) => {
    return new Intl.NumberFormat('en-US', { minimumFractionDigits: 2 }).format(
        val || 0,
    );
};

const handlePrint = () => {
    window.print();
};

const convertNumberToWords = (amount) => {
    if (isNaN(amount) || amount === 0) return 'Zero Naira';

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
        if (num >= 100) {
            result += units[Math.floor(num / 100)] + ' Hundred ';
            num %= 100;
        }
        if (num >= 20) {
            result += tens[Math.floor(num / 10)] + ' ';
            num %= 10;
        } else if (num >= 10) {
            result += teens[num - 10] + ' ';
            num = 0;
        }
        if (num > 0) {
            result += units[num] + ' ';
        }
        return result.trim();
    };

    let words = '';
    let nairaAmount = Math.floor(amount);
    let koboAmount = Math.round((amount - nairaAmount) * 100);

    if (nairaAmount >= 1000000000) {
        words +=
            convertHundreds(Math.floor(nairaAmount / 1000000000)) + ' Billion ';
        nairaAmount %= 1000000000;
    }
    if (nairaAmount >= 1000000) {
        words +=
            convertHundreds(Math.floor(nairaAmount / 1000000)) + ' Million ';
        nairaAmount %= 1000000;
    }
    if (nairaAmount >= 1000) {
        words += convertHundreds(Math.floor(nairaAmount / 1000)) + ' Thousand ';
        nairaAmount %= 1000;
    }
    if (nairaAmount > 0) {
        words += convertHundreds(nairaAmount) + ' ';
    }

    words = words.trim();
    words += words ? ' Naira' : 'Zero Naira';

    if (koboAmount > 0) {
        words += ' and ';
        if (koboAmount >= 20) {
            words += tens[Math.floor(koboAmount / 10)] + ' ';
            koboAmount %= 10;
        } else if (koboAmount >= 10) {
            words += teens[koboAmount - 10] + ' ';
            koboAmount = 0;
        }
        if (koboAmount > 0) {
            words += units[koboAmount] + ' ';
        }
        words += 'Kobo';
    }

    return words.trim() + ' Only';
};
</script>

<style scoped>
.remittance-receipt {
    font-family: 'Times New Roman', Times, serif;
    line-height: 1.2;
}

@media print {
    @page {
        size: A4;
        margin: 10mm;
    }
    .remittance-receipt {
        padding: 0;
        width: 100%;
    }
}
</style>
