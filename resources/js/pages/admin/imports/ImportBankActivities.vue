<script setup>
import { ref } from 'vue';

const file = ref(null);
const message = ref('');
const loading = ref(false);
const fileInput = ref(null);

const props = defineProps({
    
    csrf_token: String,

});


function handleFileChange() {
    file.value = fileInput.value?.files[0];
}

async function submitForm() {
    if (!file.value) {
        message.value = 'Please select a file.';
        return;
    }

    loading.value = true;
    message.value = '';

    const formData = new FormData();
    formData.append('file', file.value);
    formData.append('_token', props.csrf_token);


    try {
        const response = await fetch('/importBankActivities', {
            method: 'POST',
            headers: {
                // 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
            },
            body: formData,
        });

        if (response.ok) {
            message.value = 'Upload successful!';
        } else {
            const errorData = await response.text();
            message.value = `Error: ${errorData}`;
        }
    } catch (error) {
        message.value = `Error: ${error.message}`;
    } finally {
        loading.value = false;
    }
}
</script>



<template>
    <div>
        <h2>Upload Excel File</h2>
        <form @submit.prevent="submitForm" enctype="multipart/form-data">
            <input type="file" ref="fileInput" @change="handleFileChange" required />
            <button type="submit" :disabled="loading">Upload</button>
        </form>
        <p v-if="message">{{ message }}</p>
    </div>
</template>