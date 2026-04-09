<!-- <template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head :title="editMode ? 'Edit User' : 'Create User'" />

        <Card>
            <template #title>
                {{ editMode ? 'Edit User' : 'Create New User' }}
            </template>
            <template #content>
                <UserForm
                    :user="user"
                    :editMode="editMode"
                    @saved="onSaved"
                    @cancel="onCancel"
                    :all-roles="allRoles"
                    :all-permissions="allPermissions"
                    :all-mdas="allMdas"
                />
            </template>
        </Card>
    </AppLayout>
</template> -->

<!-- <script setup>
    import AppLayout from '@/layouts/AppLayout.vue';
    import { Head, router } from '@inertiajs/vue3';
    import Card from 'primevue/card';
    import { onMounted } from 'vue';
    import UserForm from './UserForm.vue';

    const props = defineProps({
        user: Object,
        // Add new props received from UsersController@create
        allRoles: { type: Array, default: () => [] },
        allPermissions: { type: Array, default: () => [] },
        allMdas: { type: Array, default: () => [] },
    });

    const editMode = !!props.user;

    const breadcrumbs = [
        { title: 'Users', href: '/users' },
        { title: editMode ? 'Edit User' : 'Create User', href: '#' },
    ];

    const onSaved = (formData) => {
        if (editMode) {
            // ✅ Alternative FIX: Hardcoded URL path for PUT
            router.put(`/users/${props.user.id}`, formData, {
                onSuccess: () => router.visit(route('users.index')),
            });
        } else {
            // ✅ Alternative FIX: Hardcoded URL path for POST
            router.post('/users', formData, {
                onSuccess: () => router.visit(route('users.index')),
            });
        }
    };

    const onCancel = () => {
        router.get(route('users.index'));
    };

    onMounted(() => {
        console.log('--- USER PROP RECEIVED IN CREATE.VUE ---');
        console.log(props.user);
        console.log('--- END DEBUG ---');
    });
</script> -->

<!-- <script setup>
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, router } from '@inertiajs/vue3';
import Card from 'primevue/card';
import { onMounted, ref } from 'vue';
import UserForm from './UserForm.vue';

const props = defineProps({
    user: Object,
    allRoles: { type: Array, default: () => [] },
    allPermissions: { type: Array, default: () => [] },
    allMdas: { type: Array, default: () => [] },
});

const editMode = ref(!!props.user);

const breadcrumbs = [
    { title: 'Users', href: '/users' },
    { title: editMode.value ? 'Edit User' : 'Create User', href: '#' },
];

const onSaved = (formData) => {
    if (editMode.value) {
        router.put(`/users/${props.user.id}`, formData, {
            onSuccess: () => router.visit(route('users.index')),
        });
    } else {
        router.post('/users', formData, {
            onSuccess: () => router.visit(route('users.index')),
        });
    }
};

const onCancel = () => {
    router.get(route('users.index'));
};

onMounted(() => {
    console.log('--- USER PROP RECEIVED IN CREATE.VUE ---');
    console.log('Edit Mode:', editMode.value);
    console.log('User Data:', props.user);
    console.log('Roles:', props.user?.roles);
    console.log('Permissions:', props.user?.permissions);
    console.log('MDAs:', props.user?.mdas);
    console.log('--- END DEBUG ---');
});
</script> -->

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head :title="editMode ? 'Edit User' : 'Create User'" />

        <Card>
            <template #title>
                {{ editMode ? 'Edit User' : 'Create New User' }}
            </template>
            <template #content>
                <UserForm
                    :user="user"
                    :editMode="editMode"
                    @saved="onSaved"
                    @cancel="onCancel"
                    :all-roles="allRoles"
                    :all-permissions="allPermissions"
                    :all-mdas="allMdas"
                />
            </template>
        </Card>
    </AppLayout>
</template>

<script setup>
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, router } from '@inertiajs/vue3';
import Card from 'primevue/card';
import { onMounted } from 'vue';
import UserForm from './UserForm.vue';

const props = defineProps({
    user: Object,
    // Add new props received from UsersController@create
    allRoles: { type: Array, default: () => [] },
    allPermissions: { type: Array, default: () => [] },
    allMdas: { type: Array, default: () => [] },
});

const editMode = !!props.user;

const breadcrumbs = [
    { title: 'Users', href: '/users' },
    { title: editMode ? 'Edit User' : 'Create User', href: '#' },
];

const onSaved = (formData) => {
    if (editMode) {
        // ✅ Alternative FIX: Hardcoded URL path for PUT
        router.put(`/users/${props.user.id}`, formData, {
            onSuccess: () => router.visit(route('users.index')),
        });
    } else {
        // ✅ Alternative FIX: Hardcoded URL path for POST
        router.post('/users', formData, {
            onSuccess: () => router.visit(route('users.index')),
        });
    }
};

const onCancel = () => {
    router.get(route('users.index'));
};

onMounted(() => {
    console.log('--- USER PROP RECEIVED IN CREATE.VUE ---');
    console.log(props.user);
    console.log('--- END DEBUG ---');
});
</script>
