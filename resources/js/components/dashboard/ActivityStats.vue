<!-- resources/js/components/dashboard/ActivityStats.vue -->
<template>
    <div class="card">
        <div class="justify-content-between align-items-center mb-4 flex">
            <h5 class="m-0">Activity Statistics</h5>
            <div class="flex gap-2">
                <Button
                    icon="pi pi-refresh"
                    class="p-button-rounded p-button-text"
                    @click="fetchStats"
                    :loading="loading"
                />
                <Button
                    icon="pi pi-filter"
                    class="p-button-rounded p-button-text"
                    @click="filterDialog = true"
                />
            </div>
        </div>

        <!-- Date Range Filter (Optional) -->
        <div v-if="showDateFilter" class="mb-4">
            <div class="grid">
                <div class="col-12 md:col-6">
                    <div class="field">
                        <label for="dateFrom" class="mb-2 block"
                            >From Date</label
                        >
                        <Calendar
                            id="dateFrom"
                            v-model="dateRange.start"
                            dateFormat="yy-mm-dd"
                            placeholder="Start Date"
                            showIcon
                        />
                    </div>
                </div>
                <div class="col-12 md:col-6">
                    <div class="field">
                        <label for="dateTo" class="mb-2 block">To Date</label>
                        <Calendar
                            id="dateTo"
                            v-model="dateRange.end"
                            dateFormat="yy-mm-dd"
                            placeholder="End Date"
                            showIcon
                        />
                    </div>
                </div>
            </div>
        </div>

        <!-- Statistics Overview -->
        <div class="mb-4 grid">
            <div class="col-12 md:col-4">
                <div class="border-round surface-50 p-3">
                    <div
                        class="align-items-center justify-content-between mb-2 flex"
                    >
                        <div class="text-500">Total Activities</div>
                        <i class="pi pi-chart-bar text-primary"></i>
                    </div>
                    <div class="text-2xl font-bold">
                        {{ formatNumber(stats.totalActivities) }}
                    </div>
                    <div class="text-500 text-sm">All time activities</div>
                </div>
            </div>

            <div class="col-12 md:col-4">
                <div class="border-round surface-50 p-3">
                    <div
                        class="align-items-center justify-content-between mb-2 flex"
                    >
                        <div class="text-500">Active Users</div>
                        <i class="pi pi-users text-blue-500"></i>
                    </div>
                    <div class="text-2xl font-bold">
                        {{ stats.activeUsers }}
                    </div>
                    <div class="text-500 text-sm">Users with activities</div>
                </div>
            </div>

            <div class="col-12 md:col-4">
                <div class="border-round surface-50 p-3">
                    <div
                        class="align-items-center justify-content-between mb-2 flex"
                    >
                        <div class="text-500">Today's Activities</div>
                        <i class="pi pi-clock text-green-500"></i>
                    </div>
                    <div class="text-2xl font-bold">
                        {{ stats.todayActivities }}
                    </div>
                    <div class="text-500 text-sm">Activities today</div>
                </div>
            </div>
        </div>

        <!-- User Activity Cards -->
        <div class="grid">
            <div
                v-for="user in userStats"
                :key="user.id"
                class="col-12 md:col-6 lg:col-4"
            >
                <Card
                    class="hover:shadow-2 cursor-pointer transition-all duration-200"
                    @click="viewUserActivities(user)"
                >
                    <template #title>
                        <div class="align-items-center flex gap-3">
                            <Avatar
                                :label="user.name.charAt(0)"
                                :style="{
                                    backgroundColor: getAvatarColor(user.id),
                                    color: 'white',
                                }"
                                size="large"
                                shape="circle"
                            />
                            <div>
                                <div class="font-bold">{{ user.name }}</div>
                                <div class="text-500 text-sm">
                                    {{ user.email }}
                                </div>
                            </div>
                        </div>
                    </template>
                    <template #content>
                        <div class="grid">
                            <div class="col-6">
                                <div class="text-center">
                                    <div
                                        class="text-primary text-2xl font-bold"
                                    >
                                        {{ user.activityCount }}
                                    </div>
                                    <div class="text-500 text-xs">
                                        Activities
                                    </div>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="text-center">
                                    <div
                                        class="text-2xl font-bold text-green-500"
                                    >
                                        {{ user.lastActivityDays }}d
                                    </div>
                                    <div class="text-500 text-xs">
                                        Last active
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Activity Types Breakdown -->
                        <div class="mt-3">
                            <div class="text-500 mb-2 text-sm">
                                Activity Types:
                            </div>
                            <div class="flex flex-wrap gap-1">
                                <Tag
                                    v-for="(count, type) in user.activityTypes"
                                    :key="type"
                                    :value="`${type}: ${count}`"
                                    severity="info"
                                    size="small"
                                    class="text-xs"
                                />
                            </div>
                        </div>

                        <!-- Recent Activity -->
                        <div class="mt-3" v-if="user.recentActivity">
                            <div class="text-500 mb-1 text-sm">Recent:</div>
                            <div class="line-clamp-2 text-sm">
                                {{ user.recentActivity.description }}
                            </div>
                            <div class="text-500 mt-1 text-xs">
                                {{
                                    formatTimeAgo(
                                        user.recentActivity.created_at,
                                    )
                                }}
                            </div>
                        </div>
                    </template>
                    <template #footer>
                        <Button
                            label="View Details"
                            icon="pi pi-eye"
                            class="p-button-text justify-content-center w-full"
                            @click.stop="viewUserActivities(user)"
                        />
                    </template>
                </Card>
            </div>
        </div>

        <!-- Filter Dialog -->
        <Dialog
            v-model:visible="filterDialog"
            header="Filter Statistics"
            :style="{ width: '450px' }"
        >
            <div class="grid">
                <div class="col-12">
                    <div class="field">
                        <label for="timeRange" class="mb-2 block"
                            >Time Range</label
                        >
                        <Dropdown
                            id="timeRange"
                            v-model="filters.timeRange"
                            :options="timeRangeOptions"
                            optionLabel="label"
                            optionValue="value"
                            class="w-full"
                        />
                    </div>
                </div>
                <div class="col-12">
                    <div class="field">
                        <label for="minActivities" class="mb-2 block"
                            >Minimum Activities</label
                        >
                        <InputNumber
                            id="minActivities"
                            v-model="filters.minActivities"
                            :min="0"
                            :max="100"
                            class="w-full"
                        />
                    </div>
                </div>
            </div>
            <template #footer>
                <Button
                    label="Cancel"
                    icon="pi pi-times"
                    @click="filterDialog = false"
                    class="p-button-text"
                />
                <Button
                    label="Apply"
                    icon="pi pi-check"
                    @click="applyFilters"
                />
            </template>
        </Dialog>
    </div>
</template>

<script setup>
import { router } from '@inertiajs/vue3';
import { useToast } from 'primevue/usetoast';
import { onMounted, ref } from 'vue';

// PrimeVue Components
import Avatar from 'primevue/avatar';
import Button from 'primevue/button';
import Calendar from 'primevue/calendar';
import Card from 'primevue/card';
import Dialog from 'primevue/dialog';
import Dropdown from 'primevue/dropdown';
import InputNumber from 'primevue/inputnumber';
import Tag from 'primevue/tag';

const toast = useToast();

// Props
const props = defineProps({
    initialStats: {
        type: Object,
        default: () => ({}),
    },
});

// Refs
const loading = ref(false);
const filterDialog = ref(false);
const showDateFilter = ref(false);

// Stats data
const stats = ref({
    totalActivities: 0,
    activeUsers: 0,
    todayActivities: 0,
    userStats: [],
});

// User statistics
const userStats = ref([]);

// Filters
const dateRange = ref({
    start: null,
    end: null,
});

const filters = ref({
    timeRange: '7days', // last 7 days
    minActivities: 0,
});

const timeRangeOptions = [
    { label: 'Last 24 Hours', value: '24hours' },
    { label: 'Last 7 Days', value: '7days' },
    { label: 'Last 30 Days', value: '30days' },
    { label: 'All Time', value: 'all' },
    { label: 'Custom Range', value: 'custom' },
];

// Fetch statistics from API
// const fetchStats = async () => {
//     try {
//         loading.value = true;

//         const params = {
//             ...filters.value,
//             date_from: dateRange.value.start
//                 ? formatDate(dateRange.value.start)
//                 : null,
//             date_to: dateRange.value.end
//                 ? formatDate(dateRange.value.end)
//                 : null,
//         };

//         // Remove null values
//         Object.keys(params).forEach((key) => {
//             if (params[key] === null || params[key] === undefined) {
//                 delete params[key];
//             }
//         });

//         const response = await fetch(
//             `/activity-stats?${new URLSearchParams(params)}`,
//         );

//         if (!response.ok) {
//             throw new Error('Failed to fetch statistics');
//         }

//         const data = await response.json();
//         stats.value = data.data.stats || {};
//         userStats.value = data.data.userStats || [];
//     } catch (error) {
//         console.error('Error fetching stats:', error);
//         toast.add({
//             severity: 'error',
//             summary: 'Error',
//             detail: 'Failed to load activity statistics',
//             life: 3000,
//         });
//     } finally {
//         loading.value = false;
//     }
// };
// In ActivityStats.vue, modify the fetchStats function
const fetchStats = async () => {
    try {
        loading.value = true;

        const params = {
            ...filters.value,
            date_from: dateRange.value.start
                ? formatDate(dateRange.value.start)
                : null,
            date_to: dateRange.value.end
                ? formatDate(dateRange.value.end)
                : null,
        };

        // Remove null values
        Object.keys(params).forEach((key) => {
            if (params[key] === null || params[key] === undefined) {
                delete params[key];
            }
        });

        const response = await axios.get('/activity-stats', { params });

        stats.value = response.data.stats || {};
        userStats.value = response.data.userStats || [];
    } catch (error) {
        console.error('Error fetching stats:', error);
        toast.add({
            severity: 'error',
            summary: 'Error',
            detail: 'Failed to load activity statistics',
            life: 3000,
        });
    } finally {
        loading.value = false;
    }
};

// View user's activities in detail
const viewUserActivities = (user) => {
    // Store the selected user in session or pass as query param
    const queryParams = {
        user_id: user.id,
        user_name: user.name,
        date_from: dateRange.value.start
            ? formatDate(dateRange.value.start)
            : null,
        date_to: dateRange.value.end ? formatDate(dateRange.value.end) : null,
    };

    // Navigate to user activities page
    router.visit('/user-activities', {
        data: queryParams,
        preserveState: true,
    });
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
    return colors[userId % colors.length];
};

const formatNumber = (num) => {
    if (!num) return '0';
    return num.toLocaleString();
};

const formatTimeAgo = (dateString) => {
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

const formatDate = (date) => {
    return date.toISOString().split('T')[0];
};

const applyFilters = () => {
    filterDialog.value = false;
    if (filters.value.timeRange === 'custom') {
        showDateFilter.value = true;
    } else {
        showDateFilter.value = false;
        dateRange.value = { start: null, end: null };
    }
    fetchStats();
};

// Lifecycle
onMounted(() => {
    if (props.initialStats && Object.keys(props.initialStats).length > 0) {
        stats.value = props.initialStats;
    } else {
        fetchStats();
    }
});
</script>

<style scoped>
.cursor-pointer {
    cursor: pointer;
}

.hover\:shadow-2:hover {
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
}

.transition-all {
    transition: all 0.3s ease;
}

.line-clamp-2 {
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
}
</style>
