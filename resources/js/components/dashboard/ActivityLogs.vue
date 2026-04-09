<!-- resources/js/components/activity/ActivityLogs.vue -->
<template>
    <div class="grid">
        <div class="col-12">
            <div class="card">
                <Toolbar class="mb-4">
                    <template #start>
                        <h2>Activity Logs</h2>
                    </template>
                    <template #end>
                        <Button
                            icon="pi pi-download"
                            label="Export"
                            class="p-button-help mr-2"
                            @click="exportDialog = true"
                        />
                        <Button
                            icon="pi pi-filter-slash"
                            label="Clear"
                            class="p-button-outlined mr-2"
                            @click="clearFilters"
                        />
                        <Button
                            icon="pi pi-refresh"
                            label="Refresh"
                            class="p-button-success"
                            @click="fetchLogs"
                        />
                    </template>
                </Toolbar>

                <!-- Filters -->
                <div class="p-fluid mb-4 grid">
                    <div class="col-12 md:col-3">
                        <div class="field">
                            <label for="search">Search</label>
                            <InputText
                                id="search"
                                v-model="filters.search"
                                placeholder="Search logs..."
                                @keyup.enter="fetchLogs"
                            />
                        </div>
                    </div>
                    <div class="col-12 md:col-2">
                        <div class="field">
                            <label for="logName">Log Type</label>
                            <Dropdown
                                id="logName"
                                v-model="filters.log_name"
                                :options="filterOptions.log_names"
                                optionLabel="label"
                                optionValue="value"
                                placeholder="All Types"
                                showClear
                            />
                        </div>
                    </div>
                    <div class="col-12 md:col-2">
                        <div class="field">
                            <label for="event">Event</label>
                            <Dropdown
                                id="event"
                                v-model="filters.event"
                                :options="filterOptions.events"
                                optionLabel="label"
                                optionValue="value"
                                placeholder="All Events"
                                showClear
                            />
                        </div>
                    </div>
                    <div class="col-12 md:col-2">
                        <div class="field">
                            <label for="user">User</label>
                            <Dropdown
                                id="user"
                                v-model="filters.causer_id"
                                :options="filterOptions.users"
                                optionLabel="name"
                                optionValue="id"
                                placeholder="All Users"
                                showClear
                            />
                        </div>
                    </div>
                    <div class="col-12 md:col-3">
                        <div class="field">
                            <label for="dateRange">Date Range</label>
                            <Calendar
                                id="dateRange"
                                v-model="dateRange"
                                selectionMode="range"
                                :manualInput="false"
                                dateFormat="yy-mm-dd"
                                placeholder="Select date range"
                                showIcon
                            />
                        </div>
                    </div>
                </div>

                <!-- Activity Logs Table -->
                <DataTable
                    :value="logs"
                    :loading="loading"
                    :paginator="true"
                    :rows="pagination.per_page"
                    :totalRecords="pagination.total"
                    :rowsPerPageOptions="[10, 25, 50]"
                    v-model:first="pagination.from"
                    @page="onPageChange"
                    @sort="onSort"
                    responsiveLayout="scroll"
                >
                    <Column field="created_at" header="Date" :sortable="true">
                        <template #body="{ data }">
                            {{ formatDateTime(data.created_at) }}
                        </template>
                    </Column>

                    <Column field="causer.name" header="User" :sortable="true">
                        <template #body="{ data }">
                            <div v-if="data.causer">
                                <span class="font-medium">{{
                                    data.causer.name
                                }}</span>
                                <br />
                                <small class="text-500">{{
                                    data.causer.email
                                }}</small>
                            </div>
                            <span v-else>System</span>
                        </template>
                    </Column>

                    <Column field="log_name" header="Module" :sortable="true">
                        <template #body="{ data }">
                            <Tag
                                :value="data.log_name"
                                :severity="getLogSeverity(data.log_name)"
                            />
                        </template>
                    </Column>

                    <Column field="event" header="Action" :sortable="true">
                        <template #body="{ data }">
                            <Tag
                                :value="data.event"
                                :severity="getEventSeverity(data.event)"
                            />
                        </template>
                    </Column>

                    <Column field="description" header="Description">
                        <template #body="{ data }">
                            <div class="line-clamp-2">
                                {{ data.description }}
                            </div>
                        </template>
                    </Column>

                    <Column field="properties" header="Details">
                        <template #body="{ data }">
                            <Button
                                icon="pi pi-eye"
                                class="p-button-rounded p-button-text"
                                @click="viewDetails(data)"
                                v-tooltip.top="'View Details'"
                            />
                        </template>
                    </Column>

                    <Column header="IP Address">
                        <template #body="{ data }">
                            <Chip
                                v-if="data.properties?.ip_address"
                                :label="data.properties.ip_address"
                                icon="pi pi-globe"
                                class="text-xs"
                            />
                        </template>
                    </Column>

                    <template #empty>
                        <div class="py-8 text-center">
                            <i class="pi pi-inbox text-400 mb-2 text-4xl"></i>
                            <p class="text-500">No activity logs found.</p>
                        </div>
                    </template>
                </DataTable>
            </div>
        </div>

        <!-- Details Dialog -->
        <Dialog
            v-model:visible="detailsDialog"
            :style="{ width: '600px' }"
            header="Activity Details"
            :modal="true"
        >
            <div v-if="selectedLog" class="grid">
                <div class="col-12">
                    <div class="surface-100 border-round mb-3 p-3">
                        <div class="grid">
                            <div class="col-6">
                                <div class="text-500 text-sm">User</div>
                                <div class="font-medium">
                                    {{ selectedLog.causer?.name || 'System' }}
                                </div>
                                <div class="text-500 text-sm">
                                    {{ selectedLog.causer?.email }}
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="text-500 text-sm">Date & Time</div>
                                <div class="font-medium">
                                    {{ formatDateTime(selectedLog.created_at) }}
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="mb-3">
                        <div class="text-500 text-sm">Action</div>
                        <div class="align-items-center mt-1 flex gap-2">
                            <Tag
                                :value="selectedLog.event"
                                :severity="getEventSeverity(selectedLog.event)"
                            />
                            <span class="font-medium">on</span>
                            <Tag :value="selectedLog.log_name" />
                        </div>
                    </div>

                    <div class="mb-3">
                        <div class="text-500 text-sm">Description</div>
                        <div class="font-medium">
                            {{ selectedLog.description }}
                        </div>
                    </div>

                    <div class="mb-3" v-if="selectedLog.subject">
                        <div class="text-500 text-sm">Entity</div>
                        <div class="font-medium">
                            {{ selectedLog.subject_type }} #{{
                                selectedLog.subject_id
                            }}
                        </div>
                    </div>

                    <div class="mb-3" v-if="selectedLog.properties">
                        <div class="text-500 mb-2 text-sm">Properties</div>
                        <pre
                            class="surface-100 border-round overflow-auto p-3 text-sm"
                            style="max-height: 200px"
                            >{{
                                JSON.stringify(selectedLog.properties, null, 2)
                            }}
            </pre
                        >
                    </div>

                    <div class="grid" v-if="selectedLog.properties">
                        <div class="col-6">
                            <div class="text-500 text-sm">IP Address</div>
                            <div class="font-medium">
                                {{ selectedLog.properties.ip_address || 'N/A' }}
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="text-500 text-sm">User Agent</div>
                            <div class="truncate text-sm font-medium">
                                {{ selectedLog.properties.user_agent || 'N/A' }}
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <template #footer>
                <Button
                    label="Close"
                    icon="pi pi-times"
                    @click="detailsDialog = false"
                    class="p-button-text"
                />
            </template>
        </Dialog>

        <!-- Export Dialog -->
        <Dialog
            v-model:visible="exportDialog"
            :style="{ width: '400px' }"
            header="Export Logs"
            :modal="true"
        >
            <div class="grid">
                <div class="col-12">
                    <div class="field">
                        <label for="exportFormat" class="mb-2 block"
                            >Format</label
                        >
                        <Dropdown
                            id="exportFormat"
                            v-model="exportFormat"
                            :options="exportFormats"
                            optionLabel="name"
                            optionValue="value"
                            class="w-full"
                        />
                    </div>
                </div>

                <div class="col-12">
                    <div class="field">
                        <label for="exportDateRange" class="mb-2 block"
                            >Date Range</label
                        >
                        <Calendar
                            id="exportDateRange"
                            v-model="exportDateRange"
                            selectionMode="range"
                            :manualInput="false"
                            dateFormat="yy-mm-dd"
                            placeholder="Select date range"
                            class="w-full"
                            showIcon
                        />
                    </div>
                </div>
            </div>

            <template #footer>
                <Button
                    label="Cancel"
                    icon="pi pi-times"
                    @click="exportDialog = false"
                    class="p-button-text"
                />
                <Button
                    label="Export"
                    icon="pi pi-download"
                    @click="exportLogs"
                    class="p-button-success"
                />
            </template>
        </Dialog>
    </div>
</template>

<script setup>
import axios from '@/axios';
import { useToast } from 'primevue/usetoast';
import { onMounted, ref, watch } from 'vue';

const toast = useToast();

// Refs
const logs = ref([]);
const loading = ref(false);
const detailsDialog = ref(false);
const exportDialog = ref(false);
const selectedLog = ref(null);
const dateRange = ref(null);
const exportDateRange = ref(null);
const exportFormat = ref('csv');

// Filters and pagination
const filters = ref({
    search: '',
    log_name: null,
    event: null,
    causer_id: null,
    date_from: null,
    date_to: null,
    sort_by: 'created_at',
    sort_dir: 'desc',
});

const pagination = ref({
    current_page: 1,
    from: 0,
    last_page: 1,
    per_page: 25,
    to: 0,
    total: 0,
});

const filterOptions = ref({
    log_names: [],
    events: [],
    users: [],
});

const exportFormats = [
    { name: 'CSV', value: 'csv' },
    { name: 'Excel', value: 'excel' },
    { name: 'PDF', value: 'pdf' },
];

// Computed
const logSeverityMap = {
    schedule: 'info',
    voucher: 'warning',
    receipt: 'success',
    user: 'danger',
    default: 'help',
};

const eventSeverityMap = {
    created: 'success',
    updated: 'warning',
    deleted: 'danger',
    approved: 'info',
    rejected: 'danger',
    default: 'secondary',
};

// Methods
const fetchLogs = async () => {
    try {
        loading.value = true;

        // Update date filters from dateRange
        if (dateRange.value && dateRange.value.length === 2) {
            filters.value.date_from = formatDate(dateRange.value[0]);
            filters.value.date_to = formatDate(dateRange.value[1]);
        } else {
            filters.value.date_from = null;
            filters.value.date_to = null;
        }

        const params = {
            ...filters.value,
            page: pagination.value.current_page,
            per_page: pagination.value.per_page,
        };

        const response = await axios.get('/api/activity-logs', { params });

        logs.value = response.data.data.logs;
        pagination.value = response.data.data.pagination;

        // Update filter options if not loaded yet
        if (filterOptions.value.log_names.length === 0) {
            filterOptions.value = response.data.data.filters;

            // Transform log names and events for dropdown
            filterOptions.value.log_names =
                response.data.data.filters.log_names.map((name) => ({
                    label: name.charAt(0).toUpperCase() + name.slice(1),
                    value: name,
                }));

            filterOptions.value.events = response.data.data.filters.events.map(
                (event) => ({
                    label: event.charAt(0).toUpperCase() + event.slice(1),
                    value: event,
                }),
            );
        }
    } catch (error) {
        toast.add({
            severity: 'error',
            summary: 'Error',
            detail: 'Failed to fetch activity logs',
            life: 3000,
        });
    } finally {
        loading.value = false;
    }
};

const fetchFilterOptions = async () => {
    try {
        const response = await axios.get('/api/activity-logs');
        filterOptions.value = response.data.data.filters;
    } catch (error) {
        console.error('Failed to fetch filter options:', error);
    }
};

const clearFilters = () => {
    filters.value = {
        search: '',
        log_name: null,
        event: null,
        causer_id: null,
        date_from: null,
        date_to: null,
        sort_by: 'created_at',
        sort_dir: 'desc',
    };
    dateRange.value = null;
    pagination.value.current_page = 1;
    fetchLogs();
};

const viewDetails = (log) => {
    selectedLog.value = log;
    detailsDialog.value = true;
};

const exportLogs = async () => {
    try {
        const params = {
            format: exportFormat.value,
        };

        if (exportDateRange.value && exportDateRange.value.length === 2) {
            params.date_from = formatDate(exportDateRange.value[0]);
            params.date_to = formatDate(exportDateRange.value[1]);
        }

        const response = await axios.post('/api/activity-logs/export', params, {
            responseType: 'blob',
        });

        // Create download link
        const url = window.URL.createObjectURL(new Blob([response.data]));
        const link = document.createElement('a');
        link.href = url;

        const timestamp = new Date().toISOString().split('T')[0];
        link.setAttribute(
            'download',
            `activity-logs-${timestamp}.${exportFormat.value}`,
        );
        document.body.appendChild(link);
        link.click();
        link.remove();

        exportDialog.value = false;

        toast.add({
            severity: 'success',
            summary: 'Success',
            detail: 'Logs exported successfully',
            life: 3000,
        });
    } catch (error) {
        toast.add({
            severity: 'error',
            summary: 'Error',
            detail: 'Failed to export logs',
            life: 3000,
        });
    }
};

const onPageChange = (event) => {
    pagination.value.current_page = event.page + 1;
    pagination.value.per_page = event.rows;
    fetchLogs();
};

const onSort = (event) => {
    filters.value.sort_by = event.sortField;
    filters.value.sort_dir = event.sortOrder === 1 ? 'asc' : 'desc';
    fetchLogs();
};

const getLogSeverity = (logName) => {
    return logSeverityMap[logName] || logSeverityMap.default;
};

const getEventSeverity = (event) => {
    return eventSeverityMap[event] || eventSeverityMap.default;
};

const formatDateTime = (dateString) => {
    return new Date(dateString).toLocaleString('en-GB', {
        day: '2-digit',
        month: 'short',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
};

const formatDate = (date) => {
    return date.toISOString().split('T')[0];
};

// Lifecycle
onMounted(() => {
    fetchLogs();
    fetchFilterOptions();
});

// Watchers
watch(
    filters,
    () => {
        pagination.value.current_page = 1;
    },
    { deep: true },
);

watch(dateRange, () => {
    if (!dateRange.value) {
        filters.value.date_from = null;
        filters.value.date_to = null;
    }
});
</script>

<style scoped>
.line-clamp-2 {
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
}

.truncate {
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
</style>
