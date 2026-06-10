<template>
    <Create
        :user="user"
        :all-roles="allRoles"
        :all-permissions="allPermissions"
        :all-mdas="allMdas"
    />
</template>

<script setup>
import Create from './Create.vue';

defineProps({
    user: Object,
    // ðŸ”¥ CRITICAL FIX: Define all the assignment list props
    allRoles: { type: Array, default: () => [] },
    allPermissions: { type: Array, default: () => [] },
    allMdas: { type: Array, default: () => [] },
});
</script>
