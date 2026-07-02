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
                    :all-user-categories="allUserCategories"
                    :all-users="allUsers"
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

const props = defineProps({
    user: Object,
    allRoles: { type: Array, default: () => [] },
    allPermissions: { type: Array, default: () => [] },
    allMdas: { type: Array, default: () => [] },
    allUserCategories: { type: Array, default: () => [] },
    allUsers: { type: Array, default: () => [] },
});

const editMode = !!props.user;

const breadcrumbs = [
    { title: 'Users', href: '/users' },
    { title: editMode ? 'Edit User' : 'Create User', href: '#' },
];

const onSaved = (formData) => {
    console.log('📤 Parent - Saving data:', formData);
    console.log('📤 Parent - Edit Mode:', editMode);
    console.log('📤 Parent - User ID:', props.user?.id);
    
    if (editMode) {
        // Editing existing user
        if (formData instanceof FormData) {
            // ✅ For FormData, use POST with _method=PUT
            formData.append('_method', 'PUT');
            router.post(`/users/${props.user.id}`, formData, {
                forceFormData: true,
                preserveScroll: true,
                onSuccess: () => {
                    console.log('✅ User updated successfully');
                    router.visit(route('users.index'));
                },
                onError: (errors) => {
                    console.error('❌ Update failed:', errors);
                    // Show validation errors
                    if (errors && typeof errors === 'object') {
                        const errorMessages = Object.values(errors).flat();
                        alert('Update failed: ' + errorMessages.join(', '));
                    }
                }
            });
        } else {
            // ✅ For regular object
            router.put(`/users/${props.user.id}`, formData, {
                preserveScroll: true,
                onSuccess: () => {
                    console.log('✅ User updated successfully');
                    router.visit(route('users.index'));
                },
                onError: (errors) => {
                    console.error('❌ Update failed:', errors);
                    if (errors && typeof errors === 'object') {
                        const errorMessages = Object.values(errors).flat();
                        alert('Update failed: ' + errorMessages.join(', '));
                    }
                }
            });
        }
    } else {
        // Creating new user
        if (formData instanceof FormData) {
            router.post('/users', formData, {
                forceFormData: true,
                preserveScroll: true,
                onSuccess: () => {
                    console.log('✅ User created successfully');
                    router.visit(route('users.index'));
                },
                onError: (errors) => {
                    console.error('❌ Creation failed:', errors);
                    if (errors && typeof errors === 'object') {
                        const errorMessages = Object.values(errors).flat();
                        alert('Creation failed: ' + errorMessages.join(', '));
                    }
                }
            });
        } else {
            router.post('/users', formData, {
                preserveScroll: true,
                onSuccess: () => {
                    console.log('✅ User created successfully');
                    router.visit(route('users.index'));
                },
                onError: (errors) => {
                    console.error('❌ Creation failed:', errors);
                    if (errors && typeof errors === 'object') {
                        const errorMessages = Object.values(errors).flat();
                        alert('Creation failed: ' + errorMessages.join(', '));
                    }
                }
            });
        }
    }
};

const onCancel = () => {
    router.get(route('users.index'));
};

onMounted(() => {
    console.log('--- CREATE COMPONENT MOUNTED ---');
    console.log('Edit Mode:', editMode);
    console.log('User Data:', props.user);
    console.log('All Users count:', props.allUsers?.length || 0);
    console.log('All User Categories:', props.allUserCategories);
    console.log('--- END DEBUG ---');
});
</script>