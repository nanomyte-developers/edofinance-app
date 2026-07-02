<template>
    <Create
        :user="user"
        :all-roles="allRoles"
        :all-permissions="allPermissions"
        :all-mdas="allMdas"
        :all-user-categories="allUserCategories"
        :all-users="allUsers"
    />
</template>

<script setup>
import Create from './Create.vue';

const props = defineProps({
    user: Object,
    allRoles: { type: Array, default: () => [] },
    allPermissions: { type: Array, default: () => [] },
    allMdas: { type: Array, default: () => [] },
    allUserCategories: { type: Array, default: () => [] },
    allUsers: { type: Array, default: () => [] },
});
</script>