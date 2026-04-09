<template>
    <div class="user-details" v-if="user">
        <div class="grid">
            <div class="col-12">
                <div class="field">
                    <label class="mb-1 block font-bold">Name</label>
                    <div class="text-lg">{{ user.name }}</div>
                </div>
            </div>
            <div class="col-12">
                <div class="field">
                    <label class="mb-1 block font-bold">Email</label>
                    <div class="text-lg">{{ user.email }}</div>
                </div>
            </div>
            <div class="col-12">
                <div class="field">
                    <label class="mb-1 block font-bold">Status</label>
                    <Tag
                        :value="user.is_verified ? 'Verified' : 'Unverified'"
                        :severity="user.is_verified ? 'success' : 'warning'"
                    />
                </div>
            </div>
            <div class="col-12">
                <div class="field">
                    <label class="mb-1 block font-bold">Joined</label>
                    <div>{{ user.created_at_formatted }}</div>
                </div>
            </div>
            <div class="col-12">
                <div class="field">
                    <label class="mb-1 block font-bold">Last Login</label>
                    <div>{{ user.last_login_formatted || 'Never' }}</div>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import Tag from 'primevue/tag';

defineProps({
    user: Object,
});
</script>
