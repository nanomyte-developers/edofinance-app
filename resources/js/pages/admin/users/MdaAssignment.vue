<template>
    <div class="mda-assignment-component surface-border border-round border-1 p-3">
        <label class="mb-2 block font-semibold">Available MDA(s)</label>
        <div style="max-height: 300px; overflow-y: auto">
            <div
                v-for="mda in allMdas"
                :key="mda.id"
                class="mb-2"
            >
                <div class="align-items-center flex">
                    <Checkbox
                        :id="`mda_${mda.id}`"
                        v-model="selectedMdas"
                        :value="mda.id"
                        :binary="false"
                    />
                    <label
                        :for="`mda_${mda.id}`"
                        class="ml-2 flex-1 cursor-pointer"
                    >
                        <div class="font-medium">
                            {{ mda.name }}
                        </div>
                    </label>
                </div>
            </div>
        </div>
        <small class="text-500 mt-1 block">
            {{ selectedMdas.length }} MDA(s) selected
        </small>
    </div>
</template>

<script setup>
import Checkbox from 'primevue/checkbox';
import { computed } from 'vue';

const props = defineProps({
    allMdas: {
        type: Array,
        default: () => [],
    },
    modelValue: { // Array of MDA IDs (v-model)
        type: Array,
        required: true,
    },
});

const emit = defineEmits(['update:modelValue']);

const selectedMdas = computed({
    get: () => props.modelValue,
    set: (value) => {
        emit('update:modelValue', value);
    },
});
</script>