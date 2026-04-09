<script setup>
import AppLayout from '@/layouts/AppLayout.vue';
import { router, usePage } from '@inertiajs/vue3';
import { computed, onMounted, ref, watch } from 'vue';

// PrimeVue Components - ADD THESE IMPORTS
import Avatar from 'primevue/avatar'; // Make sure this is imported
import Button from 'primevue/button';
import DatePicker from 'primevue/datepicker'; // Make sure this is imported
import Card from 'primevue/card';
import Column from 'primevue/column';
import DataTable from 'primevue/datatable';
import Dialog from 'primevue/dialog'; // Make sure this is imported
import Dropdown from 'primevue/dropdown'; // Make sure this is imported
import InputText from 'primevue/inputtext';
import ProgressSpinner from 'primevue/progressspinner';
import Tag from 'primevue/tag'; // Make sure this is imported
import { FilterMatchMode } from '@primevue/core/api';
import { useToast } from 'primevue/usetoast';
import axios from 'axios';
import moment from 'moment';


import Toast from 'primevue/toast';

const toast = useToast();




// PrimeVue Components
// import Avatar from 'primevue/avatar';
// import Button from 'primevue/button';
// import Card from 'primevue/card';
// import Column from 'primevue/column';
// import DataTable from 'primevue/datatable';
// import ProgressSpinner from 'primevue/progressspinner';
// import Tag from 'primevue/tag';

import { useDebounceFn } from '@vueuse/core';

const page = usePage();
const user = computed(() => page.props.auth?.user);
const isAuthenticated = computed(() => !!user.value);

// Activity log statistics
const activityStats = ref({
    totalActivities: 0,
    activeUsers: 0,
    todayActivities: 0,
    loading: true,
    error: null,
});

// Recent activities for the table
const recentActivities = ref([]);

// Fetch all activity data from your /activity-stats endpoint
const fetchActivityData = async () => {
    try {
        activityStats.value.loading = true;

        // Use your existing /activity-stats endpoint
        const response = await fetch('/activity-stats');

        if (!response.ok) {
            throw new Error(
                `Failed to fetch activity statistics: ${response.status}`,
            );
        }

        const data = await response.json();

        console.log('Activity stats data:', data); // For debugging

        // Check if your API returns data in the expected format
        if (data.success && data.data) {
            // Check the structure of your API response
            if (data.data.stats) {
                // If your API returns stats object
                activityStats.value.totalActivities =
                    data.data.stats.totalActivities || 0;
                activityStats.value.activeUsers =
                    data.data.stats.activeUsers || 0;
                activityStats.value.todayActivities =
                    data.data.stats.todayActivities || 0;
            } else if (data.data.totalActivities !== undefined) {
                // If your API returns flat structure
                activityStats.value.totalActivities =
                    data.data.totalActivities || 0;
                activityStats.value.activeUsers = data.data.activeUsers || 0;
                activityStats.value.todayActivities =
                    data.data.todayActivities || 0;
            } else {
                // Try to extract from logs array if that's what your API returns
                const logs = data.data.logs || [];
                activityStats.value.totalActivities =
                    data.data.pagination?.total || logs.length;
                activityStats.value.activeUsers = this.countUniqueUsers(logs);
                activityStats.value.todayActivities =
                    this.countTodaysActivities(logs);
            }

            // Get recent activities for the table
            if (data.data.logs && Array.isArray(data.data.logs)) {
                recentActivities.value = data.data.logs.slice(0, 10); // Get first 10
            } else {
                // If no logs in response, fetch them separately
                // await fetchRecentActivities();
            }
        } else {
            throw new Error('Invalid API response format');
        }
    } catch (error) {
        console.error('Error fetching activity data:', error);
        activityStats.value.error = error.message;

        // Fallback: Try to fetch from activity-logs endpoint
        // await fetchRecentActivities();
    } finally {
        activityStats.value.loading = false;
    }
};

// Helper function to count unique users from logs
const countUniqueUsers = (logs) => {
    if (!logs || !Array.isArray(logs)) return 0;
    const userIds = new Set();
    logs.forEach((log) => {
        if (log.causer_id) userIds.add(log.causer_id);
    });
    return userIds.size;
};

// Helper function to count today's activities
const countTodaysActivities = (logs) => {
    if (!logs || !Array.isArray(logs)) return 0;
    const today = new Date().toDateString();
    return logs.filter((log) => {
        const logDate = new Date(log.created_at).toDateString();
        return logDate === today;
    }).length;
};

// Fetch recent activities from activity-logs endpoint
const fetchRecentActivities = async () => {
    try {
        const response = await fetch('/activity-logs?per_page=10');

        if (!response.ok) {
            throw new Error('Failed to fetch recent activities');
        }

        const data = await response.json();

        if (data.success && data.data && data.data.logs) {
            recentActivities.value = data.data.logs;

            // Update statistics from this data if not already set
            if (
                activityStats.value.totalActivities === 0 &&
                data.data.pagination
            ) {
                activityStats.value.totalActivities =
                    data.data.pagination.total || 0;
            }

            // Count unique users from recent activities
            if (activityStats.value.activeUsers === 0) {
                activityStats.value.activeUsers = countUniqueUsers(
                    data.data.logs,
                );
            }

            // Count today's activities
            if (activityStats.value.todayActivities === 0) {
                activityStats.value.todayActivities = countTodaysActivities(
                    data.data.logs,
                );
            }
        }
    } catch (error) {
        console.error('Error fetching recent activities:', error);
    }
};

// Format time ago
const formatTimeAgo = (dateString) => {
    if (!dateString) return 'N/A';

    const now = new Date();
    const date = new Date(dateString);
    const diffMs = now - date;
    const diffMins = Math.floor(diffMs / 60000);
    const diffHours = Math.floor(diffMs / 3600000);
    const diffDays = Math.floor(diffMs / 86400000);

    if (diffMins < 1) return 'Just now';
    if (diffMins < 60) return `${diffMins}m ago`;
    if (diffHours < 24) return `${diffHours}h ago`;
    if (diffDays < 7) return `${diffDays}d ago`;
    return date.toLocaleDateString();
};

// Format number with commas
const formatNumber = (num) => {
    return num?.toLocaleString() || '0';
};

// Format full date and time
const formatDateTime = (dateString) => {
    if (!dateString) return 'N/A';
    return new Date(dateString).toLocaleString('en-GB', {
        day: '2-digit',
        month: 'short',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
};

// // Get status severity
// const getStatusSeverity = (activity) => {
//     if (
//         activity.description?.includes('Failed') ||
//         activity.properties?.error
//     ) {
//         return 'danger';
//     }
//     return 'success';
// };

// // Get status text
// const getStatusText = (activity) => {
//     if (
//         activity.description?.includes('Failed') ||
//         activity.properties?.error
//     ) {
//         return 'Error';
//     }
//     return 'Success';
// };

// // Get activity type severity
// const getActivityTypeSeverity = (logName) => {
//     const severityMap = {
//         voucher: 'warning',
//         user: 'danger',
//         receipt: 'success',
//         default: 'info',
//     };
//     return severityMap[logName] || 'secondary';
// };

// // Get activity type label
// const getActivityTypeLabel = (logName) => {
//     const labelMap = {
//         voucher: 'Voucher',
//         user: 'User',
//         receipt: 'Receipt',
//         default: 'General',
//     };
//     return labelMap[logName] || logName;
// };

// Add these reactive properties
const activityFilters = ref({
    search: '',
    dateRange: null,
    logName: null,
});

const globalFilter = ref('');
const activityDetailsDialog = ref(false);
const selectedActivity = ref(null);

// Add log name options
const logNameOptions = ref([
    { label: 'Default', value: 'default' },
    { label: 'Voucher', value: 'voucher' },
    { label: 'Bank Activity', value: 'bank_activity' },
    { label: 'User', value: 'user' },
    { label: 'System', value: 'system' },
]);

// Add hasActiveFilters computed property
const hasActiveFilters = computed(() => {
    return (
        activityFilters.value.search ||
        activityFilters.value.dateRange ||
        activityFilters.value.logName
    );
});

// Add filteredActivities computed property
const filteredActivities = computed(() => {
    let activities = recentActivities.value || [];

    // Apply search filter
    if (activityFilters.value.search) {
        const searchTerm = activityFilters.value.search.toLowerCase();
        activities = activities.filter((activity) => {
            return (
                activity.description?.toLowerCase().includes(searchTerm) ||
                activity.causer?.name?.toLowerCase().includes(searchTerm) ||
                activity.causer?.email?.toLowerCase().includes(searchTerm) ||
                activity.log_name?.toLowerCase().includes(searchTerm) ||
                JSON.stringify(activity.properties)
                    ?.toLowerCase()
                    .includes(searchTerm)
            );
        });
    }

    // Apply date range filter
    if (
        activityFilters.value.dateRange &&
        activityFilters.value.dateRange.length === 2
    ) {
        const [startDate, endDate] = activityFilters.value.dateRange;
        activities = activities.filter((activity) => {
            const activityDate = new Date(activity.created_at);
            return activityDate >= startDate && activityDate <= endDate;
        });
    }

    // Apply log name filter
    if (activityFilters.value.logName) {
        activities = activities.filter(
            (activity) => activity.log_name === activityFilters.value.logName,
        );
    }

    // Apply global filter
    if (globalFilter.value) {
        const searchTerm = globalFilter.value.toLowerCase();
        activities = activities.filter((activity) => {
            return (
                activity.description?.toLowerCase().includes(searchTerm) ||
                activity.causer?.name?.toLowerCase().includes(searchTerm) ||
                activity.causer?.email?.toLowerCase().includes(searchTerm) ||
                activity.log_name?.toLowerCase().includes(searchTerm) ||
                JSON.stringify(activity.properties)
                    ?.toLowerCase()
                    .includes(searchTerm)
            );
        });
    }

    return activities;
});

// Add debounced apply filters function
const debouncedApplyFilters = useDebounceFn(() => {
    // This will be automatically called when search input changes
}, 300);

// Add filter functions
const applyFilters = () => {
    // Force a refresh of the filtered data
    // The computed property will automatically update
};

const clearFilters = () => {
    activityFilters.value = {
        search: '',
        dateRange: null,
        logName: null,
    };
    globalFilter.value = '';
};

const onGlobalFilter = () => {
    // Global filter is applied through the computed property
};

// Add show activity details function
const showActivityDetails = (activity) => {
    // selectedActivity.value = activity;
    // activityDetailsDialog.value = true;
    if (activity.properties?.voucher_number) {
        router.visit('vouchers/' + activity.properties.voucher_id)
    }
    if (activity.properties?.receipt_number) {
        router.visit('receipts/' + activity.properties.receipt_id)
    }
    if( activity.properties?.remittance_number) {
        router.visit('remittances/' + activity.properties.remittance_id)
    }
};

// Add export CSV function
const exportCSV = () => {
    import('primevue/config').then((primevue) => {
        const dt = document.querySelector('.p-datatable');
        if (dt && dt.__vue__ && dt.__vue__.exportCSV) {
            dt.__vue__.exportCSV();
        } else {
            // Fallback manual CSV export
            const headers = ['Time', 'User', 'Activity', 'Type', 'Status'];
            const csvContent = [
                headers.join(','),
                ...filteredActivities.value.map((activity) =>
                    [
                        formatDateTime(activity.created_at),
                        activity.causer?.name || 'System',
                        `"${activity.description}"`,
                        activity.log_name,
                        getStatusText(activity),
                    ].join(','),
                ),
            ].join('\n');

            const blob = new Blob([csvContent], { type: 'text/csv' });
            const url = window.URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = `activity-logs-${new Date().toISOString().split('T')[0]}.csv`;
            a.click();
        }
    });
};

// Add new helper functions
const getActivityTypeLabel = (logName) => {
    const labels = {
        default: 'Default',
        voucher: 'Voucher',
        bank_activity: 'Bank Activity',
        user: 'User',
        system: 'System',
    };
    return labels[logName] || logName;
};

const getActivityTypeSeverity = (logName) => {
    const severities = {
        default: 'info',
        voucher: 'success',
        bank_activity: 'warning',
        user: 'primary',
        system: 'secondary',
    };
    return severities[logName] || 'info';
};

const getStatusText = (activity) => {
    if (activity.properties?.error) return 'Error';
    if (activity.event === 'updated') return 'Updated';
    if (activity.event === 'created') return 'Created';
    if (activity.event === 'deleted') return 'Deleted';
    return 'Success';
};

const getStatusSeverity = (activity) => {
    if (activity.properties?.error) return 'danger';
    if (activity.event === 'updated') return 'warning';
    if (activity.event === 'created') return 'success';
    if (activity.event === 'deleted') return 'danger';
    return 'success';
};

// Add max-height CSS
const maxHeightStyle = {
    maxHeight: '200px',
    overflow: 'auto',
};

// Fetch data on component mount
// onMounted(() => {
//     fetchActivityData();
//     // console.log(usePage().props.auth.userRoles);
// });



const vouchers = ref([]);

const searchQuery = ref(""); // Search input

const filters = ref({
    global: { value: null, matchMode: FilterMatchMode.CONTAINS },

    name: { value: null, matchMode: FilterMatchMode.CONTAINS },

    // voucher_type: { value: null, matchMode: FilterMatchMode.CONTAINS },

    // voucher_date: { value: null, matchMode: FilterMatchMode.CONTAINS },

    // // mda.name: { value: null, matchMode: FilterMatchMode.CONTAINS },

    // narration: { value: null, matchMode: FilterMatchMode.CONTAINS },
    // status: { value: null, matchMode: FilterMatchMode.CONTAINS }
});


const lazyParams = ref({
    first: 0,
    rows: 50,
    page: 1,
    date_from: null,
    date_to: null,
});

const totalRecords = ref(0);
const loading = ref(false);
let debounceTimer = null; // Timer for debounce
let debounceTimer2 = null; // Timer for debounce

const dateFrom = ref(null);
const dateTo = ref(null);


const loadVouchers = async () => {
    loading.value = true;
    try {

        const response = await axios.get('dashboardStats', { params: { per_page: lazyParams.value.rows, page: lazyParams.value.page, search: searchQuery.value, date_from: lazyParams.value.date_from, date_to: lazyParams.value.date_to }, });
        console.log(response.data.data);
        vouchers.value = response.data.data.logs;
        totalRecords.value = response.data.data.paginator.total;
        recentActivities.value = vouchers.value;
    } catch (error) {
        toast.add({ severity: "error", summary: "Error", detail: "Failed to load data", life: 3000 });
        console.error(error);

    }
    loading.value = false;
};


onMounted(() => {
    // debugVoucherStatuses();
    console.log('=== END DEBUG ===');
    // console.log(props);
    console.log('=== END DEBUG ===');
    lazyParams.value.page = 1;
    loadVouchers();
    fetchActivityData();
});


watch(searchQuery, () => {
    clearTimeout(debounceTimer);
    debounceTimer = setTimeout(() => {
        lazyParams.value.page = 1; // Reset to first page when searching
        loadVouchers();
    }, 2000); // 500ms debounce delay

});

watch(() => activityFilters.value.dateRange, () => {
    console.log(activityFilters.value);
    clearTimeout(debounceTimer2);
    debounceTimer2 = setTimeout(() => {
        let reload = false;
        if (activityFilters.value.dateRange[0]) {
            lazyParams.value.date_from = moment(activityFilters.value.dateRange[0]).format('YYYY-MM-DD'); // Reset to first page when searching
            reload = true;
        }
        if (activityFilters.value.dateRange[1]) {
            lazyParams.value.date_to = moment(activityFilters.value.dateRange[1]).format('YYYY-MM-DD'); // Reset to first page when searching
            reload = true
        }
        if (reload) {
            loadVouchers();
        }
    }, 2000); // 500ms debounce delay

});

const onPage = (event) => {
    lazyParams.value.page = event.page + 1; // Laravel pagination starts at 1
    lazyParams.value.first = event.first;
    lazyParams.value.rows = event.rows;
    loadVouchers();
};


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




</script>

<template>
    <Toast />

    <AppLayout title="Dashboard - Activity Logs">
        <div class="grid" v-if="usePage().props.auth.userRoles.includes('admin')">
            <!-- Main Activity Statistics Cards -->
            <div class="col-12">
                <div class="grid">
                    <!-- Total Activities Card -->
                    <div class="col-12 md:col-4">
                        <Card class="mb-0 h-full">
                            <template #content>
                                <div v-if="activityStats.loading" class="py-4 text-center">
                                    <ProgressSpinner style="width: 30px; height: 30px" />
                                </div>
                                <div v-else-if="activityStats.error" class="py-4 text-center">
                                    <i class="pi pi-exclamation-triangle mb-2 text-2xl text-red-500"></i>
                                    <p class="text-sm text-red-500">
                                        {{ activityStats.error }}
                                    </p>
                                </div>
                                <div v-else>
                                    <div class="justify-content-between mb-3 flex">
                                        <div>
                                            <span class="text-500 mb-3 block font-medium">Total Activities</span>
                                            <div class="text-900 text-3xl font-bold">
                                                {{
                                                    formatNumber(
                                                        activityStats.totalActivities,
                                                    )
                                                }}
                                            </div>
                                        </div>
                                        <div class="align-items-center justify-content-center border-round flex bg-blue-100"
                                            style="width: 3rem; height: 3rem">
                                            <i class="pi pi-chart-bar text-2xl text-blue-500"></i>
                                        </div>
                                    </div>
                                    <div class="text-500 text-sm">
                                        All system activities recorded
                                    </div>
                                </div>
                            </template>
                        </Card>
                    </div>

                    <!-- Active Users Card -->
                    <div class="col-12 md:col-4">
                        <Card class="mb-0 h-full">
                            <template #content>
                                <div v-if="activityStats.loading" class="py-4 text-center">
                                    <ProgressSpinner style="width: 30px; height: 30px" />
                                </div>
                                <div v-else-if="activityStats.error" class="py-4 text-center">
                                    <i class="pi pi-exclamation-triangle mb-2 text-2xl text-red-500"></i>
                                    <p class="text-sm text-red-500">
                                        {{ activityStats.error }}
                                    </p>
                                </div>
                                <div v-else>
                                    <div class="justify-content-between mb-3 flex">
                                        <div>
                                            <span class="text-500 mb-3 block font-medium">Active Users</span>
                                            <div class="text-900 text-3xl font-bold">
                                                {{
                                                    formatNumber(
                                                        activityStats.activeUsers,
                                                    )
                                                }}
                                            </div>
                                        </div>
                                        <div class="align-items-center justify-content-center border-round flex bg-green-100"
                                            style="width: 3rem; height: 3rem">
                                            <i class="pi pi-users text-2xl text-green-500"></i>
                                        </div>
                                    </div>
                                    <div class="text-500 text-sm">
                                        Users with recorded activities
                                    </div>
                                </div>
                            </template>
                        </Card>
                    </div>

                    <!-- Today's Activities Card -->
                    <div class="col-12 md:col-4">
                        <Card class="mb-0 h-full">
                            <template #content>
                                <div v-if="activityStats.loading" class="py-4 text-center">
                                    <ProgressSpinner style="width: 30px; height: 30px" />
                                </div>
                                <div v-else-if="activityStats.error" class="py-4 text-center">
                                    <i class="pi pi-exclamation-triangle mb-2 text-2xl text-red-500"></i>
                                    <p class="text-sm text-red-500">
                                        {{ activityStats.error }}
                                    </p>
                                </div>
                                <div v-else>
                                    <div class="justify-content-between mb-3 flex">
                                        <div>
                                            <span class="text-500 mb-3 block font-medium">Today's Activities</span>
                                            <div class="text-900 text-3xl font-bold">
                                                {{
                                                    formatNumber(
                                                        activityStats.todayActivities,
                                                    )
                                                }}
                                            </div>
                                        </div>
                                        <div class="align-items-center justify-content-center border-round flex bg-orange-100"
                                            style="width: 3rem; height: 3rem">
                                            <i class="pi pi-clock text-2xl text-orange-500"></i>
                                        </div>
                                    </div>
                                    <div class="text-500 text-sm">
                                        Activities recorded today
                                    </div>
                                </div>
                            </template>
                        </Card>
                    </div>
                </div>
            </div>

            <!-- Recent Activities Table -->
            <div class="col-12">
                <Card>
                    <template #title>
                        <div class="align-items-center justify-content-between flex">
                            <div>
                                <h2 class="m-0 text-xl font-semibold">
                                    Recent Activity Logs
                                </h2>
                                <p class="text-500 m-0 mt-1 text-sm">
                                    Latest system activities
                                </p>
                            </div>
                            <div class="flex gap-2">
                                <Button icon="pi pi-refresh" class="p-button-rounded p-button-text"
                                    @click="fetchActivityData" :loading="activityStats.loading"
                                    v-tooltip="'Refresh Data'" />
                                <Button label="View All" icon="pi pi-external-link" class="p-button-text"
                                    @click="$inertia.visit('/activity-logs')" />
                            </div>
                        </div>
                    </template>

                    <template #content>
                        <!-- Filters Section - Improved Layout -->
                        <div class="mb-6 border-b pb-4">
                            <div class="grid gap-3">
                                <!-- First Row: Main Filters -->
                                <div class="col-12">
                                    <div class="grid gap-3">
                                        <!-- Search Input -->
                                        <div class="col-12 md:col-4">
                                            <div class="field mb-0">
                                                <!-- <label for="search" class="text-500 mb-2 block text-sm font-medium">
                                                    Search
                                                </label>
                                                <span class="p-input-icon-left w-full">
                                                    <i class="pi pi-search text-400" />
                                                    <InputText id="search" v-model="activityFilters.search
                                                        " placeholder="Search activities..." class="w-full" @input="
                                                            debouncedApplyFilters
                                                        " />
                                                </span> -->
                                            </div>
                                        </div>

                                        <!-- Date Range -->
                                        <div class="col-12 md:col-4">
                                            <div class="field mb-0">
                                                <label for="dateRange" class="text-500 mb-2 block text-sm font-medium">
                                                    Date Range
                                                </label>
                                                <DatePicker id="dateRange" v-model="activityFilters.dateRange
                                                    " selectionMode="range" :manualInput="false" utc="true"
                                                    placeholder="Select date range" dateFormat="yy-mm-dd" showIcon
                                                    showButtonBar class="w-full" @date-select="applyFilters" />
                                            </div>
                                        </div>

                                        <!-- Activity Type Filter -->
                                        <!-- <div class="col-12 md:col-3">
                                            <div class="field mb-0">
                                                <label for="logName" class="text-500 mb-2 block text-sm font-medium">
                                                    Activity Type
                                                </label>
                                                <Dropdown id="logName" v-model="activityFilters.logName
                                                    " :options="logNameOptions" optionLabel="label" optionValue="value"
                                                    placeholder="All Types" showClear class="w-full"
                                                    @change="applyFilters" />
                                            </div>
                                        </div> -->

                                        <!-- Clear Filters Button -->
                                        <div class="col-12 md:col-1">
                                            <div class="field mb-0">
                                                <label class="text-500 mb-2 block text-sm font-medium opacity-0">
                                                    Actions
                                                </label>
                                                <Button icon="pi pi-filter-slash"
                                                    class="p-button-outlined p-button-secondary w-full"
                                                    @click="clearFilters" v-tooltip="'Clear all filters'
                                                        " />
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Second Row: Global Search and Active Filters Indicator -->
                                <div class="col-12">
                                    <div class="flex items-center justify-between">
                                        <div class="flex items-center gap-2">
                                            <div v-if="hasActiveFilters" class="flex items-center gap-2">
                                                <span class="text-500 text-sm">
                                                    Active filters:
                                                </span>
                                                <Tag v-if="
                                                    activityFilters.search
                                                " :value="`Search: ${activityFilters.search}`" severity="info"
                                                    size="small" icon="pi pi-search" @click="
                                                        activityFilters.search =
                                                        ''
                                                        " class="cursor-pointer" />
                                                <Tag v-if="
                                                    activityFilters.dateRange &&
                                                    activityFilters
                                                        .dateRange
                                                        .length === 2
                                                " :value="`Date: ${formatDate(activityFilters.dateRange[0])} to ${formatDate(activityFilters.dateRange[1])}`"
                                                    severity="warning" size="small" icon="pi pi-calendar" @click="
                                                        activityFilters.dateRange =
                                                        null
                                                        " class="cursor-pointer" />
                                                <Tag v-if="
                                                    activityFilters.logName
                                                " :value="`Type: ${getActivityTypeLabel(activityFilters.logName)}`"
                                                    severity="success" size="small" icon="pi pi-tag" @click="
                                                        activityFilters.logName =
                                                        null
                                                        " class="cursor-pointer" />
                                            </div>
                                        </div>

                                        <div class="flex items-center gap-2">
                                            <span class="text-500 text-sm">
                                                Showing:
                                                {{ filteredActivities.length }}
                                                of
                                                {{ totalRecords }}
                                                records
                                            </span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Loading State -->
                        <div v-if="activityStats.loading" class="py-8 text-center">
                            <ProgressSpinner style="width: 50px; height: 50px" />
                            <p class="text-500 mt-2">
                                Loading activity logs...
                            </p>
                        </div>

                        <!-- Error State -->
                        <div v-else-if="activityStats.error" class="py-8 text-center">
                            <i class="pi pi-exclamation-triangle mb-3 text-4xl text-red-500"></i>
                            <p class="mb-2 text-red-500">
                                {{ activityStats.error }}
                            </p>
                            <Button label="Retry" icon="pi pi-refresh" @click="fetchActivityData"
                                class="p-button-outlined" />
                        </div>

                        <!-- DataTable 
                        <DataTable
                            v-else
                            :value="filteredActivities"
                            class="p-datatable-sm"
                            :rows="100"
                            :paginator="true"
                            lazy
                            :rowsPerPageOptions="[10, 25, 50]"
                            paginatorTemplate="FirstPageLink PrevPageLink PageLinks NextPageLink LastPageLink CurrentPageReport RowsPerPageDropdown"
                            currentPageReportTemplate="Showing {first} to {last} of {totalRecords} entries"
                            responsiveLayout="scroll"
                            stripedRows
                            :globalFilterFields="[
                                'description',
                                'causer.name',
                                'causer.email',
                                'log_name',
                                'properties',
                            ]"
                        > -->

                        <DataTable v-model:filters="filters" :value="vouchers" dataKey="id" stripedRows
                            responsiveLayout="scroll" class="p-datatable-sm" :emptyMessage="'No vouchers found.'"
                            :paginator="true" :rowsPerPageOptions="[5, 10, 20, 50, 100]" :loading="loading"
                            :rows="lazyParams.rows" :totalRecords="totalRecords" @page="onPage" removableSort
                            :globalFilterFields="['name', 'status']" lazy size="small">

                            <!-- Header with global filter -->
                            <template #header>
                                <div class="mb-4 flex items-center justify-between">
                                    <div class="text-500 flex items-center gap-2 text-sm">
                                        <i class="pi pi-table"></i>
                                        <span>Activity Logs Table</span>
                                    </div>
                                    <div class="flex items-center gap-3">
                                        <!-- <span class="p-input-icon-left">
                                            <i class="pi pi-search text-400" />
                                            <InputText v-model="globalFilter" placeholder="Search in table..."
                                                size="small" class="w-64" @input="onGlobalFilter" />
                                        </span> -->


                                        <IconField class="w-xl">
                                            <InputIcon>
                                                <i class="pi pi-search" />
                                                <InputText v-model="searchQuery" placeholder="Keyword Search" />
                                            </InputIcon>
                                        </IconField>

                                        <Button icon="pi pi-download" class="p-button-text p-button-sm"
                                            @click="exportCSV" :disabled="filteredActivities.length === 0
                                                " v-tooltip="'Export to CSV'" />
                                    </div>
                                </div>
                            </template>

                            <!-- Columns -->
                            <Column field="created_at" header="Time" sortable>
                                <template #body="slotProps">
                                    <div class="flex flex-col">
                                        <div class="text-sm font-medium">
                                            {{
                                                formatTimeAgo(
                                                    slotProps.data.created_at,
                                                )
                                            }}
                                        </div>
                                        <div class="text-500 text-xs">
                                            {{
                                                formatDateTime(
                                                    slotProps.data.created_at,
                                                )
                                            }}
                                        </div>
                                    </div>
                                </template>
                            </Column>

                            <Column field="causer.name" header="User" sortable>
                                <template #body="slotProps">
                                    <div class="flex items-center gap-2">
                                        <Avatar :label="slotProps.data.causer?.name?.charAt(
                                            0,
                                        ) || 'S'
                                            " size="small" shape="circle" class="flex-shrink-0" :style="{
                                                backgroundColor: slotProps.data
                                                    .causer
                                                    ? '#4f46e5'
                                                    : '#6b7280',
                                                color: 'white',
                                            }" />
                                        <div class="min-w-0">
                                            <div class="truncate text-sm font-medium">
                                                {{
                                                    slotProps.data.causer
                                                        ?.name || 'System'
                                                }}
                                            </div>
                                            <div class="text-500 truncate text-xs">
                                                {{
                                                    slotProps.data.causer
                                                        ?.email ||
                                                    'System Activity'
                                                }}
                                            </div>
                                        </div>
                                    </div>
                                </template>
                            </Column>

                            <Column field="description" header="Activity" sortable>
                                <template #body="slotProps">
                                    <div>
                                        <div class="mb-1 text-sm font-medium">
                                            {{ slotProps.data.description }}
                                        </div>
                                        <div class="flex flex-wrap gap-1">
                                            <div v-if="
                                                slotProps.data.properties
                                                    ?.voucher_number
                                            ">
                                                <Tag :value="`Voucher: ${slotProps.data.properties.voucher_number}`"
                                                    severity="info" size="small" class="text-xs"
                                                    @click="router.visit('vouchers/' + slotProps.data.properties.voucher_id)" />
                                            </div>
                                            <div v-if="
                                                slotProps.data.properties
                                                    ?.receipt_number
                                            ">
                                                <Tag :value="`Receipt: ${slotProps.data.properties.receipt_number}`"
                                                    severity="warn" size="small" class="text-xs"
                                                    @click="router.visit('receipts/' + slotProps.data.properties.receipt_id)" />
                                            </div>
                                            <div v-if="
                                                slotProps.data.properties
                                                    ?.remittance_number
                                            ">
                                                <Tag :value="`Remittance: ${slotProps.data.properties.remittance_number}`"
                                                    severity="success" size="small" class="text-xs"
                                                    @click="router.visit('remittances/' + slotProps.data.properties.remittance_id)" />
                                            </div>
                                            <div v-if="
                                                slotProps.data.properties
                                                    ?.error
                                            ">
                                                <Tag :value="`Error: ${slotProps.data.properties.error}`"
                                                    severity="danger" size="small" class="text-xs" />
                                            </div>
                                        </div>
                                    </div>
                                </template>
                            </Column>

                            <Column field="log_name" header="Type" sortable>
                                <template #body="slotProps">
                                    <Tag :value="getActivityTypeLabel(
                                        slotProps.data.log_name,
                                    )
                                        " :severity="getActivityTypeSeverity(
                                            slotProps.data.log_name,
                                        )
                                            " size="small" />
                                </template>
                            </Column>

                            <Column header="Status">
                                <template #body="slotProps">
                                    <Tag :value="getStatusText(slotProps.data)" :severity="getStatusSeverity(slotProps.data)
                                        " size="small" />
                                </template>
                            </Column>

                            <!-- Details Column -->
                            <Column header="Actions" :exportable="false" style="width: 80px">
                                <template #body="slotProps">
                                    <div class="flex gap-1">
                                        <Button icon="pi pi-eye" class="p-button-text p-button-sm" @click="
                                            showActivityDetails(
                                                slotProps.data,
                                            )
                                            " v-tooltip="'View Details'" />
                                    </div>
                                </template>
                            </Column>

                            <template #empty>
                                <div class="py-8 text-center">
                                    <i class="pi pi-inbox text-400 mb-2 text-4xl"></i>
                                    <p class="text-500 mb-2">
                                        No activity logs found
                                    </p>
                                    <div v-if="hasActiveFilters" class="mt-2">
                                        <Button label="Clear Filters" icon="pi pi-times" @click="clearFilters"
                                            class="p-button-text p-button-sm" />
                                    </div>
                                </div>
                            </template>
                        </DataTable>
                    </template>

                    <template #footer>
                        <div class="flex items-center justify-between border-t pt-4">
                            <div class="text-500 flex items-center gap-2 text-sm">
                                <i class="pi pi-info-circle"></i>
                                <span>
                                    Showing {{ filteredActivities.length }} of
                                    {{
                                        formatNumber(
                                            activityStats.totalActivities,
                                        )
                                    }}
                                    total activities
                                </span>
                            </div>
                            <div class="text-500 flex items-center gap-2 text-sm">
                                <i class="pi pi-clock"></i>
                                <span>
                                    Last updated:
                                    {{ new Date().toLocaleTimeString() }}
                                </span>
                            </div>
                        </div>
                    </template>
                </Card>

                <!-- Activity Details Dialog -->
                <Dialog v-model:visible="activityDetailsDialog" header="Activity Details" :style="{ width: '600px' }"
                    :modal="true">
                    <!-- ... keep your existing dialog content ... -->
                </Dialog>
            </div>

            <!-- Quick Info Card -->
            <div class="col-12 md:col-6">
                <Card>
                    <template #title>Activity Summary</template>
                    <template #content>
                        <div class="grid">
                            <div class="col-6">
                                <div class="surface-50 border-round p-3 text-center">
                                    <div class="text-primary text-2xl font-bold">
                                        {{
                                            activityStats.loading
                                                ? '...'
                                                : formatNumber(
                                                    activityStats.totalActivities,
                                                )
                                        }}
                                    </div>
                                    <div class="text-500 text-sm">
                                        Total Logs
                                    </div>
                                </div>
                            </div>
                            <div class="col-6" v-if="searchQuery.length > 0 || hasActiveFilters">
                                <div class="surface-50 border-round p-3 text-center">
                                    <div class="text-primary text-2xl font-bold">
                                        {{
                                            activityStats.loading
                                                ? '...'
                                                : formatNumber(
                                                    totalRecords,
                                                )
                                        }}
                                    </div>
                                    <div class="text-500 text-sm">
                                        Total From Search Result
                                    </div>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="surface-50 border-round p-3 text-center">
                                    <div class="text-2xl font-bold text-green-500">
                                        {{
                                            activityStats.loading
                                                ? '...'
                                                : formatNumber(
                                                    activityStats.activeUsers,
                                                )
                                        }}
                                    </div>
                                    <div class="text-500 text-sm">
                                        Active Users
                                    </div>
                                </div>
                            </div>
                            <div class="col-6 mt-3">
                                <div class="surface-50 border-round p-3 text-center">
                                    <div class="text-2xl font-bold text-orange-500">
                                        {{
                                            activityStats.loading
                                                ? '...'
                                                : formatNumber(
                                                    activityStats.todayActivities,
                                                )
                                        }}
                                    </div>
                                    <div class="text-500 text-sm">Today</div>
                                </div>
                            </div>
                            <div class="col-6 mt-3">
                                <div class="surface-50 border-round p-3 text-center">
                                    <div class="text-2xl font-bold text-blue-500">
                                        {{
                                            activityStats.loading
                                                ? '...'
                                                : formatNumber(
                                                    recentActivities.length,
                                                )
                                        }}
                                    </div>
                                    <div class="text-500 text-sm">Showing</div>
                                </div>
                            </div>
                        </div>
                    </template>
                </Card>
            </div>

            <!-- System Status Card -->
            <div class="col-12 md:col-6">
                <Card>
                    <template #title>System Status</template>
                    <template #content>
                        <div class="space-y-3">
                            <div class="align-items-center justify-content-between flex">
                                <div class="align-items-center flex gap-2">
                                    <i :class="activityStats.loading
                                        ? 'pi pi-spin pi-spinner text-blue-500'
                                        : 'pi pi-database text-green-500'
                                        "></i>
                                    <span>Activity Logs</span>
                                </div>
                                <Tag :value="activityStats.loading
                                    ? 'Loading...'
                                    : 'Active'
                                    " :severity="activityStats.loading
                                        ? 'info'
                                        : 'success'
                                        " />
                            </div>

                            <div class="align-items-center justify-content-between flex">
                                <div class="align-items-center flex gap-2">
                                    <i class="pi pi-users text-green-500"></i>
                                    <span>User Activity Tracking</span>
                                </div>
                                <Tag value="Enabled" severity="success" />
                            </div>

                            <div class="align-items-center justify-content-between flex">
                                <div class="align-items-center flex gap-2">
                                    <i class="pi pi-shield text-green-500"></i>
                                    <span>Audit Logging</span>
                                </div>
                                <Tag value="Active" severity="success" />
                            </div>

                            <div class="align-items-center justify-content-between flex">
                                <div class="align-items-center flex gap-2">
                                    <i class="pi pi-cloud text-green-500"></i>
                                    <span>Data Storage</span>
                                </div>
                                <Tag value="Online" severity="success" />
                            </div>
                        </div>
                    </template>
                </Card>
            </div>
        </div>
    </AppLayout>
</template>

<style scoped>
.card {
    height: 100%;
}

:deep(.p-card-content) {
    padding: 1rem;
}

:deep(.p-card-footer) {
    padding: 1rem 1rem 0 1rem;
}

:deep(.p-datatable) {
    font-size: 0.875rem;
}

:deep(.p-button.p-button-text) {
    color: var(--primary-color);
}

/* Improved form styling */
:deep(.field label) {
    display: block;
    margin-bottom: 0.25rem;
}

/* Calendar and Dropdown improvements */
:deep(.p-calendar) {
    display: block;
    width: 100%;
}

:deep(.p-dropdown) {
    display: block;
    width: 100%;
}

:deep(.p-inputtext) {
    width: 100%;
}

/* Table improvements */
:deep(.p-datatable-thead > tr > th) {
    background-color: #f9fafb;
    font-weight: 600;
    color: #374151;
    padding: 0.75rem 1rem;
}

:deep(.p-datatable-tbody > tr > td) {
    padding: 0.75rem 1rem;
    vertical-align: top;
}

/* Responsive adjustments */
@media screen and (max-width: 768px) {
    :deep(.p-datatable) {
        font-size: 0.75rem;
    }

    :deep(.p-column-title) {
        font-size: 0.7rem;
    }

    .grid {
        gap: 0.5rem;
    }
}

/* Tag styling for filter chips */
:deep(.p-tag) {
    cursor: pointer;
    transition: all 0.2s ease;
}

:deep(.p-tag:hover) {
    opacity: 0.8;
}

/* Avatar improvements */
:deep(.p-avatar) {
    flex-shrink: 0;
}

/* Truncate text for long content */
.truncate {
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.min-w-0 {
    min-width: 0;
}
</style>
