<script setup>
import { computed, ref, watch, onMounted } from 'vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import { useForm as useVeeForm, useField, configure } from 'vee-validate';

// PrimeVue
import Card from 'primevue/card';

import AppLayout from '@/layouts/AppLayout.vue';
import Toast from 'primevue/toast';
import { useToast } from 'primevue/usetoast';
import Tooltip from 'primevue/tooltip';
import Calendar from 'primevue/calendar';
import ProgressSpinner from 'primevue/progressspinner';


const isLoading = ref(false);
const vTooltip = Tooltip;
const toast = useToast();
const myForm = ref(null);
const yearMonth = ref(null);
const endDate = ref(null);
configure({ validateOnBlur: true, validateOnChange: true });


const props = defineProps({
    csrfToken: {
        type: String,
        default: null,
    },
});

const breadcrumbs = [{ title: 'Finance' }, { title: 'General Trial Balance Control' }];

function generateTrialBalance() {

    // console.log(myForm.value);
    // endDate.value = yearMonth.value;
    isLoading.value = true;
    myForm.value.submit();
}
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">

        <Head title="Select Trial Balance End Month" />
        <Toast />



        <Card class="shadow-sm border-0" style="">
            <template #title>Trial Balance End Month</template>
            <template #content>
                <form ref="myForm" action="/reports/trialbalance" method="POST">
                    <input type="hidden" name="_token" :value="props.csrfToken" />
                    <input type="hidden" ref="endDate" name="yearMonth1" v-model="yearMonth" />


                    <div class="flex flex-column align-items-center justify-content-center gap-3">
                        <Calendar v-model="yearMonth" view="month" dateFormat="mm/yy" showButtonBar name="yearMonth"
                            :monthNavigator="true" :yearNavigator="false" yearRange="2000:2030"
                            inputId="month-picker" />
                        <p>Selected End Month: {{ monthDisplay }}</p>
                    </div>
                    <div class="flex flex-column align-items-center justify-content-center gap-3">
                        <Button tyype="submit" label="Generate Trial Balance" severity="primary"
                            @click="generateTrialBalance" :loading="isLoading" />
                        <ProgressSpinner v-if="isLoading" />
                    </div>
                </form>
            </template>
        </Card>


    </AppLayout>
</template>
