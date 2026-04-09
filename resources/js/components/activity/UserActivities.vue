<!-- resources/js/components/activity/UserActivities.vue -->
<template>
    <div class="grid">
        <div class="col-12">
            <div class="card">
                <!-- Back Button and Title -->
                <div class="align-items-center mb-4 flex">
                    <Button
                        icon="pi pi-arrow-left"
                        class="p-button-text mr-3"
                        @click="goBack"
                    />
                    <div>
                        <h2 class="m-0">User Activities: {{ user.name }}</h2>
                        <p class="text-500 m-0">{{ user.email }}</p>
                    </div>
                </div>

                <!-- User Summary -->
                <div class="surface-100 border-round mb-4 p-4">
                    <div class="grid">
                        <div class="col-12 md:col-3">
                            <div class="text-center">
                                <div class="text-primary text-3xl font-bold">
                                    {{ userSummary.totalActivities }}
                                </div>
                                <div class="text-500">Total Activities</div>
                            </div>
                        </div>
                        <div class="col-12 md:col-3">
                            <div class="text-center">
                                <div class="text-3xl font-bold text-green-500">
                                    {{ userSummary.todayActivities }}
                                </div>
                                <div class="text-500">Today</div>
                            </div>
                        </div>
                        <div class="col-12 md:col-3">
                            <div class="text-center">
                                <div class="text-3xl font-bold text-blue-500">
                                    {{ userSummary.daysActive }}
                                </div>
                                <div class="text-500">Days Active</div>
                            </div>
                        </div>
                        <div class="col-12 md:col-3">
                            <div class="text-center">
                                <div class="text-3xl font-bold text-orange-500">
                                    {{
                                        formatTimeAgo(userSummary.lastActivity)
                                    }}
                                </div>
                                <div class="text-500">Last Active</div>
                            </div>
                        </div>
                    </div>

                    <!-- Activity Type Breakdown -->
                    <div class="mt-4">
                        <h4 class="mb-3">Activity Type Breakdown</h4>
                        <div class="flex flex-wrap gap-2">
                            <Tag
                                v-for="(
                                    count, type
                                ) in userSummary.activityTypes"
                                :key="type"
                                :value="`${type}: ${count}`"
                                :severity="getTagSeverity(type)"
                                class="text-sm"
                            />
                        </div>
                    </div>
                </div>

                <!-- Filters -->
                <div class="p-fluid mb-4 grid">
                    <div class="col-12 md:col-4">
                        <div class="field">
                            <label for="logName">Activity Type</label>
                            <Dropdown
                                id="logName"
                                v-model="filters.log_name"
                                :options="activityTypeOptions"
                                optionLabel="label"
                                optionValue="value"
                                placeholder="All Types"
                                showClear
                            />
                        </div>
                    </div>
                    <div class="col-12 md:col-4">
                        <div class="field">
                            <label for="dateRange">Date Range</label>
                            <Calendar
                                id="dateRange"
                                v-model="dateRange"
                                selectionMode="range"
                                dateFormat="yy-mm-dd"
                                placeholder="Select date range"
                                showIcon
                            />
                        </div>
                    </div>
                    <div class="col-12 md:col-4">
                        <div class="field">
                            <label for="search">Search</label>
                            <InputText
                                id="search"
                                v-model="filters.search"
                                placeholder="Search activities..."
                                @keyup.enter="fetchUserActivities"
                            />
                        </div>
                    </div>
                </div>

                <!-- Action Buttons -->
                <div class="justify-content-between mb-4 flex">
                    <Button
                        label="Clear Filters"
                        icon="pi pi-filter-slash"
                        class="p-button-outlined"
                        @click="clearFilters"
                    />
                    <div class="flex gap-2">
                        <Button
                            label="Export"
                            icon="pi pi-download"
                            class="p-button-help"
                            @click="exportActivities"
                        />
                        <Button
                            label="Refresh"
                            icon="pi pi-refresh"
                            class="p-button-success"
                            @click="fetchUserActivities"
                            :loading="loading"
                        />
                    </div>
                </div>

                <!-- Activities DataTable -->
                <DataTable
                    :value="activities"
                    :loading="loading"
                    :paginator="true"
                    :rows="pagination.per_page"
                    :totalRecords="pagination.total"
                    :rowsPerPageOptions="[10, 25, 50, 100]"
                    v-model:first="pagination.from"
                    @page="onPageChange"
                    @sort="onSort"
                    responsiveLayout="scroll"
                    class="p-datatable-sm"
                >
                    <Column
                        field="created_at"
                        header="Date & Time"
                        :sortable="true"
                    >
                        <template #body="{ data }">
                            <div>
                                <div class="font-medium">
                                    {{ formatDate(data.created_at) }}
                                </div>
                                <div class="text-500 text-sm">
                                    {{ formatTime(data.created_at) }}
                                </div>
                            </div>
                        </template>
                    </Column>

                    <Column field="log_name" header="Type" :sortable="true">
                        <template #body="{ data }">
                            <Tag
                                :value="data.log_name"
                                :severity="getLogSeverity(data.log_name)"
                            />
                        </template>
                    </Column>

                    <Column
                        field="description"
                        header="Description"
                        :sortable="true"
                    >
                        <template #body="{ data }">
                            <div class="font-medium">
                                {{ data.description }}
                            </div>
                            <div
                                v-if="data.properties?.error"
                                class="mt-1 text-sm text-red-500"
                            >
                                <i class="pi pi-exclamation-triangle mr-1"></i>
                                {{ data.properties.error }}
                            </div>
                        </template>
                    </Column>

                    <Column field="event" header="Action">
                        <template #body="{ data }">
                            <Tag
                                v-if="data.event"
                                :value="data.event"
                                :severity="getEventSeverity(data.event)"
                            />
                            <span v-else class="text-500">-</span>
                        </template>
                    </Column>

                    <Column header="Details">
                        <template #body="{ data }">
                            <div class="flex-column flex gap-1">
                                <Chip
                                    v-if="data.properties?.voucher_number"
                                    :label="data.properties.voucher_number"
                                    icon="pi pi-file"
                                    size="small"
                                    class="text-xs"
                                />
                                <Chip
                                    v-if="data.properties?.ip_address"
                                    :label="data.properties.ip_address"
                                    icon="pi pi-globe"
                                    size="small"
                                    class="text-xs"
                                />
                                <span
                                    v-if="data.properties?.method"
                                    class="bg-surface-200 border-round px-2 py-1 text-xs"
                                >
                                    {{ data.properties.method }}
                                </span>
                            </div>
                        </template>
                    </Column>

                    <Column header="View">
                        <template #body="{ data }">
                            <Button
                                icon="pi pi-eye"
                                class="p-button-rounded p-button-text p-button-sm"
                                @click="viewActivityDetails(data)"
                                v-tooltip="'View Details'"
                            />
                        </template>
                    </Column>

                    <template #empty>
                        <div class="py-8 text-center">
                            <i class="pi pi-inbox text-400 mb-3 text-4xl"></i>
                            <p class="text-500">
                                No activities found for this user.
                            </p>
                        </div>
                    </template>
                </DataTable>
            </div>
        </div>

        <!-- Activity Details Dialog -->
        <Dialog
            v-model:visible="detailsDialog"
            header="Activity Details"
            :style="{ width: '600px' }"
            :modal="true"
        >
            <div v-if="selectedActivity" class="grid">
                <div class="col-12">
                    <!-- User Info -->
                    <div class="surface-100 border-round mb-3 p-3">
                        <div class="align-items-center flex gap-3">
                            <Avatar
                                :label="
                                    selectedActivity.causer?.name?.charAt(0) ||
                                    'U'
                                "
                                size="large"
                                shape="circle"
                                :style="{
                                    backgroundColor: getAvatarColor(
                                        selectedActivity.causer_id,
                                    ),
                                    color: 'white',
                                }"
                            />
                            <div>
                                <div class="font-bold">
                                    {{
                                        selectedActivity.causer?.name ||
                                        'Unknown User'
                                    }}
                                </div>
                                <div class="text-500">
                                    {{
                                        selectedActivity.causer?.email || 'N/A'
                                    }}
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Activity Info -->
                    <div class="mb-3 grid">
                        <div class="col-6">
                            <div class="text-500 text-sm">Date & Time</div>
                            <div class="font-medium">
                                {{
                                    formatDateTime(selectedActivity.created_at)
                                }}
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="text-500 text-sm">Activity Type</div>
                            <Tag
                                :value="selectedActivity.log_name"
                                :severity="
                                    getLogSeverity(selectedActivity.log_name)
                                "
                            />
                        </div>
                    </div>

                    <!-- Description -->
                    <div class="mb-3">
                        <div class="text-500 text-sm">Description</div>
                        <div class="border-round surface-50 p-3 font-medium">
                            {{ selectedActivity.description }}
                        </div>
                    </div>

                    <!-- Properties -->
                    <div class="mb-3" v-if="selectedActivity.properties">
                        <div class="text-500 mb-2 text-sm">Properties</div>
                        <pre
                            class="surface-100 border-round overflow-auto p-3 text-sm"
                            style="max-height: 200px"
                            >{{
                                JSON.stringify(
                                    selectedActivity.properties,
                                    null,
                                    2,
                                )
                            }}
                        </pre>
                    </div>

                    <!-- Additional Info -->
                    <div class="grid" v-if="selectedActivity.properties">
                        <div class="col-6">
                            <div class="text-500 text-sm">IP Address</div>
                            <div class="font-medium">
                                {{
                                    selectedActivity.properties.ip_address ||
                                    'N/A'
                                }}
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="text-500 text-sm">User Agent</div>
                            <div class="truncate text-sm">
                                {{
                                    selectedActivity.properties.user_agent ||
                                    'N/A'
                                }}
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
    </div>
</template>

<script setup>
import { router, usePage } from '@inertiajs/vue3';
import { useToast } from 'primevue/usetoast';
import { onMounted, ref } from 'vue';

// PrimeVue Components
import Avatar from 'primevue/avatar';
import Button from 'primevue/button';
import Calendar from 'primevue/calendar';
import Chip from 'primevue/chip';
import Column from 'primevue/column';
import DataTable from 'primevue/datatable';
import Dialog from 'primevue/dialog';
import Dropdown from 'primevue/dropdown';
import InputText from 'primevue/inputtext';
import Tag from 'primevue/tag';

const toast = useToast();
const page = usePage();

// Get user from query parameters or props
const user = ref({
    id: page.props.user_id || null,
    name: page.props.user_name || 'Unknown User',
    email: page.props.user_email || 'N/A',
});

// Refs
const loading = ref(false);
const detailsDialog = ref(false);
const selectedActivity = ref(null);
const dateRange = ref(null);

// Data
const activities = ref([]);
const userSummary = ref({
    totalActivities: 0,
    todayActivities: 0,
    daysActive: 0,
    lastActivity: null,
    activityTypes: {},
});

const activityTypeOptions = ref([
    { label: 'Default', value: 'default' },
    { label: 'Voucher', value: 'voucher' },
    { label: 'User', value: 'user' },
    { label: 'Receipt', value: 'receipt' },
    { label: 'Schedule', value: 'schedule' },
]);

// Pagination
const pagination = ref({
    current_page: 1,
    from: 0,
    last_page: 1,
    per_page: 25,
    to: 0,
    total: 0,
});

// Filters
const filters = ref({
    log_name: null,
    search: '',
    date_from: null,
    date_to: null,
    sort_by: 'created_at',
    sort_dir: 'desc',
});

// Fetch user activities
const fetchUserActivities = async () => {
    try {
        loading.value = true;

        // Update date filters if dateRange is set
        if (dateRange.value && dateRange.value.length === 2) {
            filters.value.date_from = formatDate(dateRange.value[0]);
            filters.value.date_to = formatDate(dateRange.value[1]);
        } else {
            filters.value.date_from = null;
            filters.value.date_to = null;
        }

        const params = {
            user_id: user.value.id,
            ...filters.value,
            page: pagination.value.current_page,
            per_page: pagination.value.per_page,
        };

        // Remove null values
        Object.keys(params).forEach((key) => {
            if (params[key] === null || params[key] === undefined) {
                delete params[key];
            }
        });

        const response = await fetch(
            `/user-activities?${new URLSearchParams(params)}`,
        );

        if (!response.ok) {
            throw new Error('Failed to fetch user activities');
        }

        const data = await response.json();
        activities.value = data.data.activities || [];
        userSummary.value = data.data.summary || {};
        pagination.value = data.data.pagination || pagination.value;
    } catch (error) {
        console.error('Error fetching user activities:', error);
        toast.add({
            severity: 'error',
            summary: 'Error',
            detail: 'Failed to load user activities',
            life: 3000,
        });
    } finally {
        loading.value = false;
    }
};

// View activity details
const viewActivityDetails = (activity) => {
    selectedActivity.value = activity;
    detailsDialog.value = true;
};

// Export activities
const exportActivities = async () => {
    try {
        const params = {
            user_id: user.value.id,
            date_from: filters.value.date_from,
            date_to: filters.value.date_to,
        };

        const response = await fetch(
            `/api/user-activities/export?${new URLSearchParams(params)}`,
            {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
            },
        );

        if (!response.ok) {
            throw new Error('Failed to export activities');
        }

        const blob = await response.blob();
        const url = window.URL.createObjectURL(blob);
        const link = document.createElement('a');
        link.href = url;
        link.download = `activities-${user.value.name}-${new Date().toISOString().split('T')[0]}.csv`;
        document.body.appendChild(link);
        link.click();
        link.remove();

        toast.add({
            severity: 'success',
            summary: 'Success',
            detail: 'Activities exported successfully',
            life: 3000,
        });
    } catch (error) {
        console.error('Error exporting activities:', error);
        toast.add({
            severity: 'error',
            summary: 'Error',
            detail: 'Failed to export activities',
            life: 3000,
        });
    }
};

// Clear filters
const clearFilters = () => {
    filters.value = {
        log_name: null,
        search: '',
        date_from: null,
        date_to: null,
        sort_by: 'created_at',
        sort_dir: 'desc',
    };
    dateRange.value = null;
    pagination.value.current_page = 1;
    fetchUserActivities();
};

// Navigation
const goBack = () => {
    router.visit('/dashboard');
};

// Pagination handlers
const onPageChange = (event) => {
    pagination.value.current_page = event.page + 1;
    pagination.value.per_page = event.rows;
    fetchUserActivities();
};

const onSort = (event) => {
    filters.value.sort_by = event.sortField;
    filters.value.sort_dir = event.sortOrder === 1 ? 'asc' : 'desc';
    fetchUserActivities();
};

// Helper functions
const getAvatarColor = (userId) => {
    const colors = [
        '#4f46e5',
        '#059669',
        '#dc2626',
        '#ea580c',
        '#7c3aed',
        '#db2777',
        '#0891b2',
        '#65a30d',
    ];
    return colors[userId % colors.length] || '#4f46e5';
};

const getLogSeverity = (logName) => {
    const severityMap = {
        voucher: 'warning',
        receipt: 'success',
        user: 'danger',
        schedule: 'info',
        default: 'secondary',
    };
    return severityMap[logName] || severityMap.default;
};

const getEventSeverity = (event) => {
    const severityMap = {
        created: 'success',
        updated: 'warning',
        deleted: 'danger',
        approved: 'info',
        rejected: 'danger',
        default: 'secondary',
    };
    return severityMap[event] || severityMap.default;
};

const getTagSeverity = (type) => {
    const severityMap = {
        voucher: 'warning',
        default: 'info',
        user: 'danger',
        receipt: 'success',
        schedule: 'help',
    };
    return severityMap[type] || 'secondary';
};

const formatDate = (dateString) => {
    return new Date(dateString).toLocaleDateString('en-GB', {
        day: '2-digit',
        month: 'short',
        year: 'numeric',
    });
};

const formatTime = (dateString) => {
    return new Date(dateString).toLocaleTimeString('en-GB', {
        hour: '2-digit',
        minute: '2-digit',
    });
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

const formatTimeAgo = (dateString) => {
    if (!dateString) return 'N/A';

    const now = new Date();
    const date = new Date(dateString);
    const diffMs = now - date;
    const diffDays = Math.floor(diffMs / 86400000);

    if (diffDays === 0) return 'Today';
    if (diffDays === 1) return 'Yesterday';
    if (diffDays < 7) return `${diffDays} days ago`;
    if (diffDays < 30) return `${Math.floor(diffDays / 7)} weeks ago`;
    return `${Math.floor(diffDays / 30)} months ago`;
};

// Lifecycle
onMounted(() => {
    if (!user.value.id) {
        toast.add({
            severity: 'error',
            summary: 'Error',
            detail: 'No user selected',
            life: 3000,
        });
        goBack();
        return;
    }

    fetchUserActivities();
});
</script>

<style scoped>
.truncate {
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
</style>
