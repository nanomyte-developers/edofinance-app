<!-- resources/js/components/dashboard/ActivityWidget.vue -->
<template>
    <div class="card">
        <div class="justify-content-between align-items-center mb-4 flex">
            <h5 class="m-0">Recent Activities</h5>
            <Button
                icon="pi pi-ellipsis-v"
                class="p-button-text p-button-rounded"
                @click="toggleMenu"
                aria-haspopup="true"
                aria-controls="overlay_menu"
            />
            <Menu
                ref="menu"
                id="overlay_menu"
                :model="menuItems"
                :popup="true"
            />
        </div>

        <div class="activity-feed">
            <div v-if="loading" class="py-4 text-center">
                <ProgressSpinner
                    style="width: 50px; height: 50px"
                    strokeWidth="8"
                />
            </div>

            <div v-else-if="activities.length === 0" class="py-8 text-center">
                <i class="pi pi-inbox text-400 mb-2 text-4xl"></i>
                <p class="text-500">No recent activities</p>
            </div>

            <div v-else class="space-y-3">
                <div
                    v-for="activity in activities"
                    :key="activity.id"
                    class="activity-item border-round surface-50 p-3"
                >
                    <div class="align-items-start flex">
                        <Avatar
                            :label="activity.causer?.name?.charAt(0) || 'S'"
                            :style="getAvatarStyle(activity.event)"
                            size="large"
                            shape="circle"
                            class="mr-3"
                        />
                        <div class="flex-1">
                            <div
                                class="justify-content-between align-items-start flex"
                            >
                                <div>
                                    <span class="font-medium">{{
                                        activity.causer?.name || 'System'
                                    }}</span>
                                    <span class="text-500 ml-2">
                                        <span
                                            v-if="
                                                activity.description?.includes(
                                                    'Failed',
                                                )
                                            "
                                            class="text-red-500"
                                        >
                                            {{ getActionDescription(activity) }}
                                        </span>
                                        <span v-else>
                                            {{ getActionDescription(activity) }}
                                        </span>
                                    </span>
                                </div>
                                <span class="text-500 text-sm">{{
                                    formatTime(activity.created_at)
                                }}</span>
                            </div>
                            <p class="mt-1 mb-0 text-sm">
                                {{ activity.description }}
                            </p>

                            <div
                                v-if="activity.properties?.error"
                                class="border-round mt-2 bg-red-50 p-2 text-sm text-red-700"
                            >
                                <i class="pi pi-exclamation-triangle mr-2"></i>
                                Error: {{ activity.properties.error }}
                            </div>

                            <div class="align-items-center mt-2 flex gap-2">
                                <Tag
                                    :value="activity.log_name"
                                    :severity="
                                        getLogSeverity(activity.log_name)
                                    "
                                    size="small"
                                />
                                <Tag
                                    v-if="activity.event"
                                    :value="activity.event"
                                    :severity="getEventSeverity(activity.event)"
                                    size="small"
                                />
                                <Chip
                                    v-if="activity.properties?.ip_address"
                                    :label="activity.properties.ip_address"
                                    icon="pi pi-globe"
                                    size="small"
                                    class="text-xs"
                                />
                                <Chip
                                    v-if="activity.properties?.voucher_number"
                                    :label="activity.properties.voucher_number"
                                    icon="pi pi-file"
                                    size="small"
                                    severity="info"
                                    class="text-xs"
                                />
                                <span
                                    v-if="activity.properties?.method"
                                    class="bg-surface-200 border-round px-2 py-1 text-xs"
                                >
                                    {{ activity.properties.method }}
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="border-top-1 surface-border mt-4 pt-3">
            <Button
                label="View All Activities"
                icon="pi pi-list"
                class="p-button-text justify-content-center w-full"
                @click="goToActivityLogs"
            />
        </div>
    </div>
</template>

<script setup>
import { router } from '@inertiajs/vue3';
import { useToast } from 'primevue/usetoast';
import { onMounted, ref } from 'vue';

const props = defineProps({
    initialActivities: {
        type: Array,
        default: () => [],
    },
});

const toast = useToast();
const menu = ref();
const activities = ref(props.initialActivities);
const loading = ref(false);

// Fetch activities from the API (client-side refresh)
const fetchActivities = async () => {
    try {
        loading.value = true;
        const response = await fetch('/api/activity-logs?per_page=5');

        if (!response.ok) {
            throw new Error('Network response was not ok');
        }

        const data = await response.json();
        activities.value = data.data.logs;

        toast.add({
            severity: 'success',
            summary: 'Success',
            detail: 'Activities refreshed',
            life: 3000,
        });
    } catch (error) {
        console.error('Error fetching activities:', error);
        toast.add({
            severity: 'error',
            summary: 'Error',
            detail: 'Failed to fetch activities',
            life: 3000,
        });
    } finally {
        loading.value = false;
    }
};

// Define menuItems AFTER fetchActivities is defined
const menuItems = ref([
    {
        label: 'Refresh',
        icon: 'pi pi-refresh',
        command: fetchActivities, // Now this function exists
    },
    {
        label: 'View All',
        icon: 'pi pi-external-link',
        command: () => router.visit('/activity-logs'),
    },
]);

const toggleMenu = (event) => {
    menu.value.toggle(event);
};

const getAvatarStyle = (event) => {
    const colors = {
        created: 'background-color: var(--green-100); color: var(--green-800)',
        updated:
            'background-color: var(--yellow-100); color: var(--yellow-800)',
        deleted: 'background-color: var(--red-100); color: var(--red-800)',
        approved: 'background-color: var(--blue-100); color: var(--blue-800)',
        null: 'background-color: var(--surface-200); color: var(--surface-800)',
        undefined:
            'background-color: var(--surface-200); color: var(--surface-800)',
        default:
            'background-color: var(--surface-200); color: var(--surface-800)',
    };
    return colors[event] || colors.default;
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
        null: 'secondary',
        undefined: 'secondary',
        default: 'secondary',
    };
    return severityMap[event] || severityMap.default;
};

const getActionDescription = (activity) => {
    const description = activity.description || '';

    if (description.includes('Failed')) return 'attempted to';
    if (description.includes('Searched')) return 'searched';
    if (description.includes('Accessed')) return 'accessed';
    if (description.includes('Updated')) return 'updated';
    if (description.includes('Created')) return 'created';
    if (description.includes('Deleted')) return 'deleted';

    return '';
};

const formatTime = (dateString) => {
    const now = new Date();
    const activityDate = new Date(dateString);
    const diffMs = now - activityDate;
    const diffMins = Math.floor(diffMs / 60000);
    const diffHours = Math.floor(diffMs / 3600000);
    const diffDays = Math.floor(diffMs / 86400000);

    if (diffMins < 1) return 'Just now';
    if (diffMins < 60) return `${diffMins}m ago`;
    if (diffHours < 24) return `${diffHours}h ago`;
    if (diffDays < 7) return `${diffDays}d ago`;
    return activityDate.toLocaleDateString();
};

const goToActivityLogs = () => {
    router.visit('/activity-logs');
};

// If no initial activities are provided, fetch them on mount
onMounted(() => {
    if (props.initialActivities.length === 0) {
        fetchActivities();
    }
});
</script>

<style scoped>
.activity-feed {
    max-height: 400px;
    overflow-y: auto;
}

.activity-item {
    transition: background-color 0.2s;
}

.activity-item:hover {
    background-color: var(--surface-100) !important;
}

.activity-feed::-webkit-scrollbar {
    width: 6px;
}

.activity-feed::-webkit-scrollbar-track {
    background: var(--surface-100);
    border-radius: 3px;
}

.activity-feed::-webkit-scrollbar-thumb {
    background: var(--surface-300);
    border-radius: 3px;
}

.activity-feed::-webkit-scrollbar-thumb:hover {
    background: var(--surface-400);
}
</style>
