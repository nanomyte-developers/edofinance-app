<template>
  <div class="p-10 bg-white min-h-screen font-serif text-[11px]">
    <div class="max-w-6xl mx-auto">

      <div class="flex justify-end mb-6">
        <h1 class="text-lg font-bold border-b-2 border-black pb-1">
          NOTES TO GPFS FOR THE YEAR ENDED 31ST DECEMBER 2025
        </h1>
      </div>

      <table class="w-full border-collapse">
        <thead>
          <tr class="bg-black text-white uppercase">
            <th class="border border-black p-2 w-2 text-center">Note</th>
            <th class="border border-black p-2 text-left">Details</th>
            <th class="border border-black p-2 w-16 text-center">Ref. Notes</th>
            <th class="border border-black p-2 w-32 text-center">Actual<br>₦</th>
            <th class="border border-black p-2 w-32 text-center">Budget 2025<br>₦</th>
            <th class="border border-black p-2 w-32 text-center">Variance<br>₦</th>
            <th class="border border-black p-2 w-32 text-center text-[9px]">2024<br>Actual ₦</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="(row, index) in notesData" :key="index" :class="getRowClass(row)">
            <td class="border border-black p-1.5 text-center font-bold">
              {{ row.note }}
            </td>

            <td class="border border-black p-1.5">
              <div v-if="row.is_header" class="font-bold uppercase tracking-tight">
                {{ row.title }}
              </div>
              <div v-else :class="{'pl-6': !row.is_subtotal, 'font-bold uppercase': row.is_subtotal}">
                {{ row.details }}
              </div>
            </td>

            <td class="border border-black p-1.5 text-center italic font-semibold">
              {{ row.ref_notes }}
            </td>

            <td class="border border-black p-1.5 text-right font-medium">
              {{ formatCurrency(row.actual_2025) }}
            </td>
            <td class="border border-black p-1.5 text-right font-medium">
              {{ formatCurrency(row.budget_2025) }}
            </td>
            <td class="border border-black p-1.5 text-right font-medium">
              {{ formatCurrency(row.variance_2025) }}
            </td>
            <td class="border border-black p-1.5 text-right font-medium">
              {{ formatCurrency(row.actual_2024) }}
            </td>
          </tr>
        </tbody>
      </table>

      <div class="mt-12 flex justify-between items-end text-gray-700">
        <div class="flex flex-col">
          <span class="text-[10px] tracking-[0.2em]">www.edostate.gov.ng</span>
          <div class="h-0.5 w-64 bg-black mt-1"></div>
        </div>
        <div class="font-bold text-lg">10</div>
      </div>
    </div>
  </div>
</template>

<script setup>
const props = defineProps({
  notesData: Array
});

const formatCurrency = (value) => {
  if (value === null || value === undefined) return '';
  if (value === 0) return '-';

  const formatted = new Intl.NumberFormat('en-NG', {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  }).format(Math.abs(value));

  return value < 0 ? `(${formatted})` : formatted;
};

const getRowClass = (row) => {
  if (row.is_header) return 'bg-[#f8f9fa]';
  if (row.is_subtotal) return 'bg-[#fdfdfd]';
  return '';
};
</script>

<style scoped>
/* Ensure borders are sharp and match the printed document look */
table {
  border: 1.5px solid black;
}
th {
  font-size: 10px;
  line-height: 1.2;
}
</style>
