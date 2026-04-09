<script setup>
import AppLayout from '@/layouts/AppLayout.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import AuthLayout from '@/layouts/AuthLayout.vue';
import { Form, Head } from '@inertiajs/vue3';
import { ref } from 'vue'
import { useForm, router } from '@inertiajs/vue3';
import Message from 'primevue/message';

// const props = defineProps{
//     token: string;
//     email: string;
// };

const props = defineProps(['email']);



const form = useForm({
    current_password: '',
    password: '',
    password_confirmation: '',
})

const processing = ref(false)
const successMessage = ref('')

const submit = () => {
    processing.value = true
    router.post(route('password2.update'), form, {
        onFinish: () => {
            processing.value = false
        },
        onSuccess: () => {
            successMessage.value = 'Password changed successfully.'
            form.reset()
        },
        onError: (errors) => {
            // errors will be handled automatically by Inertia
        }
    })
}



</script>

<template>
    <AppLayout title="Dashboard">

        <Head title="Change password" />
        <div class="grid">


            <div class="p-fluid" style="max-width: 400px; margin: auto; padding-top: 50px;">
                <h2>Change Password</h2>

              

                <div v-if="successMessage" class="p-mb-3">
                    <p-toast severity="success" :closable="true" :sticky="false" :summary="'Success'"
                        :detail="successMessage" />
                </div>
                <!-- <div v-if="props.errors" class="p-mb-3">
                    <p-toast v-for="err in props.errors" severity="danger" :closable="true" :sticky="false"
                        :summary="'danger'" :detail="err" v-bind:key="err" />
                </div> -->

                <div v-if="$page.props.errors && Object.keys($page.props.errors).length">
                    <ul>
                        <!-- Loop through each error message -->
                        <li v-for="(messages, field) in $page.props.errors" :key="field">
                            <!-- messages can be an array of error messages for each field -->
                            <!-- <div v-for="(message, index) in messages" :key="index" class="error-message">
                                {{ message }}
                            </div> -->
                            <!-- <p-toast severity="danger" :closable="true" :sticky="false" :summary="'danger'"
                                :detail="messages" /> -->

                                <Message severity="error">{{ messages }}</Message>
                                <br />
                        </li>
                    </ul>
                </div>

                <form @submit.prevent="submit" class="flex flex-col gap-6">

                    <div class="p-field w-full">

                        <label for="current_password" class="text-900 mb-2 block font-medium">Current Password</label>
                        <Password id="current_password" v-model="form.current_password" required class="w-full" />
                    </div>

                    <div class="p-field">
                        <label for="password" class="text-900 mb-2 block font-medium">New Password</label>
                        <Password id="password" v-model="form.password" required class="w-full" />
                    </div>

                    <div class="p-field">
                        <label for="password_confirmation" class="text-900 mb-2 block font-medium">Confirm New
                            Password</label>
                        <Password id="password_confirmation" v-model="form.password_confirmation" required class="w-full" />
                    </div>

                    <div class="p-mt-3">
                        <!-- <Button label="Change Password" type="submit" :loading="processing" /> -->

                        <Button type="submit" class="mt-4 w-full" :tabindex="4" :disabled="processing"
                            data-test="login-button">
                            <Spinner v-if="processing" />
                            Change Password
                        </Button>
                    </div>
                </form>
            </div>
        </div>

    </AppLayout>
</template>
