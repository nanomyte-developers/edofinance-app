<!-- PrintableSchedule.vue -->
<template>
    <div class="printable-schedule">
        <!-- Print Header -->
        <div class="print-header">
            <button class="print-button" @click="printDocument">
                <i class="pi pi-print"></i> Print Schedule
            </button>
        </div>

        <!-- Document Container -->
        <div class="document-container" ref="documentContent">
            <!-- Header Section with side-by-side layout -->
            <div class="header-section">
                <div class="header-top">
                    <div class="schedule-number">
                        {{ scheduleData.schedule_number }}
                    </div>
                    <div class="document-title">ACCS GEN. 3 (Revised).</div>
                </div>

                <div class="header-middle">
                    <div class="code-ministry-line">
                        <!-- Use relationship-based computed properties -->
                        <span class="code-label">ADMINISTRATIVE CODE:
                            {{
                                scheduleCodeFromRelationship || scheduleCode
                            }}</span>
                        <span class="ministry-name">{{
                            ministryNameFromRelationship || ministryName
                            }}</span>
                    </div>
                </div>
            </div>

            <!-- Authorization Text -->
            <div class="authorization-text">
                THE TREASURY CASH OFFICER AT THE TREASURY CASH OFFICE, BENIN
                CITY IS AUTHORIZED TO MAKE THE FOLLOWING PAYMENTS CHARGEABLE TO
                THE ABOVE HEAD OF EXPENDITURE {{ budgetHeadCode }}
            </div>

            <!-- Payment Table -->
            <table class="payment-table">
                <thead>
                    <tr>
                        <th>DATE</th>
                        <th>Serial No.</th>
                        <th>Economic Code</th>
                        <th>Name of Payee</th>
                        <th>Treasury cash Office</th>
                        <th>Amount</th>
                        <th>For use by the treasury cash office</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="(item, index) in scheduleData.items" :key="item.id"
                        style="border-bottom: 0px !important;">
                        <td style="border-bottom: 0px !important;">{{ formatDate(item.item_date) }}</td>
                        <td style="border-bottom: 0px !important;">{{ item.serial_number }}</td>
                        <td style="border-bottom: 0px !important;">{{ getEconomyHead(item.economy_code_id) }}</td>
                        <td style="border-bottom: 0px !important;">
                            {{ item.payee_name }}
                            <div class="pv-note">(1 PV Only)</div>
                        </td>
                        <td style="border-bottom: 0px !important;"></td>
                        <td class="amount-column" style="border-bottom: 0px !important;">
                            {{ formatCurrency(item.amount) }}
                        </td>
                        <td style="border-bottom: 0px !important;"></td>
                    </tr>

                    <!-- Empty space rows using CSS -->
                    <tr class="empty-space-row" v-for="n in 15" :key="`empty-${n}`">
                        <td></td>
                        <td></td>
                        <td></td>
                        <td></td>
                        <td></td>
                        <td></td>
                        <td></td>
                    </tr>

                    <!-- Total Row -->
                    <tr class="total-row ">
                        <td style="border-top: 0px !important;" ></td>
                        <td style="border-top: 0px !important;" > </td>
                        <td   style="border-top: 0px !important;"></td>
                        <td   style="border-top: 0px !important;"></td>
                        <td   style="border-top: 0px !important;"></td>
                        <td class="total-amount " style="border-top: 0px !important;">
                            {{ formatCurrency(scheduleTotal) }}
                        </td>
                        <td   style="border-top: 0px !important;"></td>
                    </tr>
                </tbody>
            </table>

            <!-- Signature Section -->
            <div class="signature-section">
                <div class="signature-box">
                    <div>Signing Officer</div>
                </div>
                <div class="signature-box">
                    <div>Countersigning Officer</div>
                </div>
            </div>

            <!-- Footer Note -->
            <div class="footer-note">
                This is the schedule, it is a coverage/summary of the Voucher or
                Vaches.
            </div>
        </div>
    </div>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue';

const props = defineProps({
    schedule: {
        type: Object,
        required: true,
    },
    administrativeCodes: {
        type: Array,
        default: () => [],
    },
    administrativeSectorCodes: {
        type: Array,
        default: () => [],
    },
    economyCodes: {
        type: Array,
        default: () => [],
    },
    economyCodeItems: {
        type: Array,
        default: () => [],
    },
});

const documentContent = ref(null);
const scheduleData = ref({ items: [] });

// Initialize data when component mounts
onMounted(() => {
    console.log('Schedule Data:', props.schedule);
    console.log('Administrative Codes:', props.administrativeCodes);
    console.log('Economic Codes:', props.economyCodes);
    console.log('Economic Code Items:', props.economyCodeItems);

    scheduleData.value = props.schedule;
});

// Computed properties
const ministryName = computed(() => {
    if (scheduleData.value.mda_id && props.administrativeCodes.length > 0) {
        const mda = props.administrativeCodes.find(
            (code) => code.id === scheduleData.value.mda_id,
        );
        console.log('Found MDA:', mda);
        return mda ? mda.name : 'Ministry of Finance';
    }
    return 'Ministry of Finance';
});

// Add the missing computed properties for relationships
const ministryNameFromRelationship = computed(() => {
    // If schedule has mda relationship loaded
    if (scheduleData.value.mda && scheduleData.value.mda.name) {
        return scheduleData.value.mda.name;
    }
    return ministryName.value; // Fallback to the other method
});

const scheduleCode = computed(() => {
    // You can customize this based on your actual code structure
    // If you have a specific code field in your schedule, use it here
    return scheduleData.value.code || '0233001001000';
});

const scheduleCodeFromRelationship = computed(() => {
    // If schedule has budget_code relationship loaded with code
    if (scheduleData.value.budget_code && scheduleData.value.budget_code.code) {
        return scheduleData.value.budget_code.code;
    }
    return scheduleCode.value; // Fallback to the other method
});

const budgetHeadCode = computed(() => {
    if (
        scheduleData.value.budget_code_id &&
        props.administrativeSectorCodes.length > 0
    ) {
        const budgetHead = props.administrativeSectorCodes.find(
            (head) => head.id === scheduleData.value.budget_code_id,
        );
        console.log('Found Budget Head:', budgetHead);
        return budgetHead
            ? `GPB ${budgetHead.code}`
            : 'GPB 86(76) 177/50.000LS';
    }
    return 'GPB 86(76) 177/50.000LS';
});

const scheduleTotal = computed(() => {
    if (scheduleData.value.items && scheduleData.value.items.length > 0) {
        const total = scheduleData.value.items.reduce(
            (sum, item) => sum + (parseFloat(item.amount) || 0),
            0,
        );
        console.log('Calculated Total:', total);
        return total;
    }
    return 0;
});

// Methods
const formatDate = (dateString) => {
    if (!dateString) return '';

    try {
        // If it's already formatted, return as is
        if (
            typeof dateString === 'string' &&
            dateString.match(/^\d{2}\/\d{2}\/\d{4}$/)
        ) {
            return dateString;
        }

        const date = new Date(dateString);
        if (isNaN(date.getTime())) {
            return '';
        }
        return date.toLocaleDateString('en-GB', {
            day: '2-digit',
            month: '2-digit',
            year: 'numeric',
        });
    } catch (error) {
        console.error('Error formatting date:', error, dateString);
        return dateString || '';
    }
};

const formatCurrency = (value) => {
    const numValue = parseFloat(value);
    if (isNaN(numValue)) return '0.00';
    return new Intl.NumberFormat('en-NG', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    }).format(numValue);
};

const getEconomyHead = (economyCodeId) => {
    if (!economyCodeId || !props.economyCodes.length) {
        console.log('No Economic Code ID or Economic Codes available');
        return '';
    }

    console.log('Looking for Economic Code:', economyCodeId);
    console.log('Available Economic Codes:', props.economyCodes);

    const economyCode = props.economyCodes.find((code) => {
        // Try different possible ID fields
        return (
            code.id == economyCodeId ||
            code.value == economyCodeId ||
            code.economy_code_id == economyCodeId
        );
    });

    if (economyCode) {
        console.log('Found Economic Code:', economyCode);
        // Return the code part only (e.g., "22020301")
        if (economyCode.code) return economyCode.code;
        if (economyCode.label && economyCode.label.includes(' - ')) {
            return economyCode.label.split(' - ')[0];
        }
        if (economyCode.name) return economyCode.name;
        return economyCode.label || '';
    }

    console.log('Economic Code not found for ID:', economyCodeId);
    return '';
};

const printDocument = () => {
    window.print();
};
</script>

<style scoped>
.printable-schedule {
    font-family: 'Times New Roman', serif;
    color: #000;
    font-size: 12px;
    line-height: 1.2;
}

.print-header {
    margin-bottom: 20px;
    text-align: center;
}

.print-button {
    background-color: #4caf50;
    color: white;
    border: none;
    padding: 10px 20px;
    border-radius: 4px;
    cursor: pointer;
    font-size: 16px;
    display: inline-flex;
    align-items: center;
    gap: 8px;
}

.print-button:hover {
    background-color: #45a049;
}

.document-container {
    width: 210mm;
    min-height: 297mm;
    margin: 0 auto;
    padding: 15mm;
    background-color: white;
    box-sizing: border-box;
    position: relative;
}

/* Header Section */
.header-section {
    margin-bottom: 20px;
}

.header-top {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 10px;
}

.schedule-number {
    font-weight: bold;
    font-size: 14px;
    text-transform: uppercase;
}

.document-title {
    font-weight: bold;
    font-size: 12px;
    text-align: right;
}

.header-middle {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 5px;
}

.code-ministry-line {
    display: flex;
    justify-content: space-between;
    width: 100%;
    font-size: 11px;
}

.code-label {
    font-weight: bold;
}

.ministry-name {
    font-weight: bold;
}

/* Authorization Text */
.authorization-text {
    font-size: 10px;
    margin-bottom: 15px;
    text-align: left;
    line-height: 1.3;
    text-transform: uppercase;
}

/* Payment Table */
.payment-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 10px;
    margin-bottom: 30px;
    table-layout: fixed;
    border: 1px solid #000;
}

.payment-table th,
.payment-table td {
    border: 1px solid #000;
    padding: 8px 4px;
    text-align: left;
    vertical-align: top;
    height: 40px;
}

.payment-table th {
    background-color: #f8f8f8;
    font-weight: bold;
    text-align: center;
    text-transform: uppercase;
}

/* Empty space rows - using CSS for consistent spacing */
.empty-space-row td {
    height: 40px;
    /* Same height as regular rows */
    border: 1px solid #000;
    border-bottom: 0px !important;
    border-top: 0px !important;
}

/* Exact column widths to match A4 paper */
.payment-table th:nth-child(1),
.payment-table td:nth-child(1) {
    width: 12%;
    text-align: center;
}

/* DATE */
.payment-table th:nth-child(2),
.payment-table td:nth-child(2) {
    width: 10%;
    text-align: center;
}

/* Serial No. */
.payment-table th:nth-child(3),
.payment-table td:nth-child(3) {
    width: 12%;
    text-align: center;
}

/* Economy Head */
.payment-table th:nth-child(4),
.payment-table td:nth-child(4) {
    width: 26%;
}

/* Name of Payee */
.payment-table th:nth-child(5),
.payment-table td:nth-child(5) {
    width: 14%;
}

/* Treasury cash Office */
.payment-table th:nth-child(6),
.payment-table td:nth-child(6) {
    width: 13%;
}

/* Amount */
.payment-table th:nth-child(7),
.payment-table td:nth-child(7) {
    width: 13%;
}

/* For use by the treasure cash office */

.amount-column {
    text-align: right;
    font-weight: bold;
    padding-right: 8px;
}

.pv-note {
    font-size: 9px;
    font-style: italic;
    margin-top: 4px;
}

.total-row {
    font-weight: bold;
}

.total-amount {
    text-align: right;
    font-weight: bold;
    padding-right: 8px;
}

/* Signature Section */
.signature-section {
    display: flex;
    justify-content: space-between;
    margin-top: 60px;
    margin-bottom: 10px;
    font-size: 11px;
    font-weight: bold;
}

.signature-box {
    width: 45%;
    text-align: center;
}

/* Footer Note */
.footer-note {
    font-style: italic;
    font-size: 10px;
    text-align: center;
    margin-top: 30px;
    border-top: 1px solid #000;
    padding-top: 10px;
}

/* Print Styles */
@media print {
    .print-header, .layout-topbar, .layout-sidebar, .layout-menu, .layout-content, .layout-footer {
        display: none !important;
    }

    .document-container {
        border: none;
        padding: 15mm;
        width: 100%;
        min-height: 100vh;
        margin: 0;
        box-shadow: none;
    }

    body {
        margin: 0;
        padding: 0;
        background: white;
        width: 210mm;
        height: 297mm;
    }

    .printable-schedule {
        margin: 0;
        padding: 0;
        width: 210mm;
        height: 297mm;
    }

    /* Ensure proper page breaks */
    .document-container {
        page-break-after: avoid;
        page-break-inside: avoid;
    }

    .payment-table {
        page-break-inside: avoid;
    }
}

/* A4 paper size specifications */
@page {
    size: A4;
    margin: 15mm;
}

@media screen and (max-width: 210mm) {
    .document-container {
        width: 100%;
        padding: 20px;
    }
}
</style>