<!-- resources/js/Pages/Activities/Index.vue -->
<template>
    <div class="p-6">
        <h1 class="mb-6 text-2xl font-bold">Activity Logs</h1>

        <!-- Filters -->
        <div class="card mb-6">
            <div class="p-fluid grid">
                <div class="col-12 md:col-4">
                    <div class="field">
                        <label for="search">Search</label>
                        <InputText
                            id="search"
                            v-model="filters.search"
                            placeholder="Search activities..."
                            class="w-full"
                        />
                    </div>
                </div>
                <div class="col-12 md:col-3">
                    <div class="field">
                        <label for="dateFrom">From Date</label>
                        <Calendar
                            id="dateFrom"
                            v-model="filters.date_from"
                            dateFormat="yy-mm-dd"
                            showIcon
                            class="w-full"
                        />
                    </div>
                </div>
                <div class="col-12 md:col-3">
                    <div class="field">
                        <label for="dateTo">To Date</label>
                        <Calendar
                            id="dateTo"
                            v-model="filters.date_from"
                            dateFormat="yy-mm-dd"
                            showIcon
                            class="w-full"
                        />
                    </div>
                </div>
                <div class="col-12 md:col-2">
                    <div class="field mt-4">
                        <Button
                            label="Filter"
                            icon="pi pi-filter"
                            @click="applyFilters"
                            class="w-full"
                        />
                    </div>
                </div>
            </div>
        </div>

        <!-- Activity Logs Table -->
        <div class="card">
            <DataTable
                :value="logs.data"
                :loading="loading"
                :paginator="true"
                :rows="logs.per_page"
                :totalRecords="logs.total"
                @page="onPageChange"
                class="p-datatable-sm"
            >
                <Column field="id" header="ID" sortable />
                <Column field="description" header="Description" sortable />
                <Column field="log_name" header="Type" sortable />
                <Column field="event" header="Event" sortable />
                <Column header="User">
                    <template #body="slotProps">
                        <div v-if="slotProps.data.causer">
                            {{ slotProps.data.causer.name }}
                            <br />
                            <small class="text-500">{{
                                slotProps.data.causer.email
                            }}</small>
                        </div>
                        <span v-else class="text-500">System</span>
                    </template>
                </Column>
                <Column field="created_at" header="Date" sortable>
                    <template #body="slotProps">
                        {{ formatDateTime(slotProps.data.created_at) }}
                    </template>
                </Column>
                <Column header="Properties">
                    <template #body="slotProps">
                        <Button
                            label="View Details"
                            icon="pi pi-eye"
                            class="p-button-text p-button-sm"
                            @click="viewDetails(slotProps.data)"
                        />
                    </template>
                </Column>
            </DataTable>
        </div>

        <!-- Details Dialog -->
        <Dialog
            v-model:visible="detailsDialog"
            header="Activity Details"
            :style="{ width: '600px' }"
        >
            <div v-if="selectedLog" class="grid">
                <div class="col-12">
                    <h4 class="mb-2 font-bold">
                        {{ selectedLog.description }}
                    </h4>
                    <p class="text-500">
                        {{ selectedLog.log_name }} •
                        {{ selectedLog.event || 'No event' }}
                    </p>
                </div>
                <div class="col-12">
                    <div class="border-round surface-100 p-3">
                        <pre class="max-h-200px overflow-auto text-sm">{{
                            JSON.stringify(selectedLog.properties, null, 2)
                        }}</pre>
                    </div>
                </div>
                <div class="col-12">
                    <p class="text-500 mt-3 text-sm">
                        Created: {{ formatDateTime(selectedLog.created_at) }}
                    </p>
                </div>
            </div>
        </Dialog>
    </div>
</template>

<script setup>
import { router } from '@inertiajs/vue3';
import { useDebounceFn } from '@vueuse/core';
import { ref, watch } from 'vue';

// PrimeVue Components
import Button from 'primevue/button';
import Calendar from 'primevue/calendar';
import Column from 'primevue/column';
import DataTable from 'primevue/datatable';
import Dialog from 'primevue/dialog';
import InputText from 'primevue/inputtext';

const props = defineProps({
    logs: Object,
    filters: Object,
    search: String,
    date_from: String,
    date_to: String,
    user_id: Number,
    log_name: String,
    event: String,
});

const loading = ref(false);
const detailsDialog = ref(false);
const selectedLog = ref(null);

const filters = ref({
    search: props.search || '',
    date_from: props.date_from || null,
    date_to: props.date_to || null,
    user_id: props.user_id || null,
    log_name: props.log_name || null,
    event: props.event || null,
});

const formatDateTime = (dateString) => {
    return new Date(dateString).toLocaleString();
};

const viewDetails = (log) => {
    selectedLog.value = log;
    detailsDialog.value = true;
};

const applyFilters = () => {
    router.get('/activities', filters.value, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    });
};

const onPageChange = (event) => {
    router.get(
        '/activities',
        {
            ...filters.value,
            page: event.page + 1,
            per_page: event.rows,
        },
        {
            preserveState: true,
            preserveScroll: true,
        },
    );
};

// Debounced search
const debouncedSearch = useDebounceFn(() => {
    applyFilters();
}, 500);

watch(() => filters.value.search, debouncedSearch);
</script>

<style scoped>
.max-h-200px {
    max-height: 200px;
}
</style>
