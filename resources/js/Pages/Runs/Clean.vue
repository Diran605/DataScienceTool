<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, useForm, router } from '@inertiajs/vue3';
import { ref } from 'vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import InputError from '@/Components/InputError.vue';

const props = defineProps({
    run: Object,
    profileResult: Object,
    lastCleanResult: Object,
    lastCleanConfig: Object,
});

const availableActions = [
    { type: 'drop_nulls', label: 'Drop Nulls', requiresConfig: false },
    { type: 'fill_nulls', label: 'Fill Nulls', requiresConfig: true },
    { type: 'deduplicate', label: 'Deduplicate', requiresConfig: false },
    { type: 'normalize_dates', label: 'Normalize Dates', requiresConfig: false },
    { type: 'normalize_text', label: 'Normalize Text', requiresConfig: false },
    { type: 'rename_columns', label: 'Rename Columns', requiresConfig: true },
    { type: 'regex_extract', label: 'Regex Extract', requiresConfig: true },
];

const form = useForm({
    steps: props.lastCleanConfig?.steps || [],
});

const selectedActionType = ref('');
const newActionConfig = ref({});

const addStep = () => {
    if (!selectedActionType.value) return;
    const action = availableActions.find(a => a.type === selectedActionType.value);
    
    form.steps.push({
        type: action.type,
        config: { ...newActionConfig.value }
    });
    
    selectedActionType.value = '';
    newActionConfig.value = {};
};

const removeStep = (index) => {
    form.steps.splice(index, 1);
};

const submit = () => {
    form.put(route('runs.clean.update', props.run.id));
};
</script>

<template>
    <Head title="Clean Data" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Clean Data: {{ run?.name }}</h2>
        </template>

        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
                <!-- Action Picker -->
                <div class="p-4 sm:p-8 bg-white shadow sm:rounded-lg">
                    <h3 class="text-lg font-medium text-gray-900 mb-4">Available Actions</h3>
                    <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-4">
                        <div v-for="action in availableActions" :key="action.type" 
                             @click="selectedActionType = action.type"
                             class="p-4 border rounded cursor-pointer hover:bg-gray-50"
                             :class="{'ring-2 ring-indigo-500': selectedActionType === action.type}">
                            {{ action.label }}
                        </div>
                    </div>
                    
                    <div v-if="selectedActionType" class="mt-4 p-4 border rounded bg-gray-50">
                        <h4 class="font-medium text-gray-700 mb-2">Configure Action</h4>
                        <div v-if="selectedActionType === 'fill_nulls'">
                            <InputLabel for="strategy" value="Strategy" />
                            <select v-model="newActionConfig.strategy" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm">
                                <option value="mean">Mean</option>
                                <option value="median">Median</option>
                                <option value="zero">Zero</option>
                                <option value="custom">Custom</option>
                            </select>
                        </div>
                        <div v-if="selectedActionType === 'rename_columns'">
                            <InputLabel for="mapping" value="Mapping JSON" />
                            <TextInput v-model="newActionConfig.mapping" type="text" class="mt-1 block w-full" placeholder='{"old": "new"}' />
                        </div>
                        <div v-if="selectedActionType === 'regex_extract'">
                            <InputLabel for="pattern" value="Pattern" />
                            <TextInput v-model="newActionConfig.pattern" type="text" class="mt-1 block w-full" />
                        </div>
                        <PrimaryButton @click="addStep" class="mt-4">Add Step</PrimaryButton>
                    </div>
                </div>

                <!-- Selected Steps -->
                <div class="p-4 sm:p-8 bg-white shadow sm:rounded-lg">
                    <h3 class="text-lg font-medium text-gray-900 mb-4">Cleaning Steps</h3>
                    <ul v-if="form.steps.length > 0" class="space-y-2">
                        <li v-for="(step, index) in form.steps" :key="index" class="p-3 border rounded flex justify-between items-center bg-gray-50">
                            <div>
                                <span class="font-semibold">{{ step.type }}</span>
                                <span v-if="Object.keys(step.config || {}).length > 0" class="text-sm text-gray-500 ml-2">
                                    {{ JSON.stringify(step.config) }}
                                </span>
                            </div>
                            <button @click="removeStep(index)" class="text-red-500 hover:text-red-700">Remove</button>
                        </li>
                    </ul>
                    <p v-else class="text-gray-500 text-sm">No steps added yet.</p>

                    <div class="mt-6 border-t pt-4">
                        <h4 class="text-sm font-medium text-gray-700 mb-2">Configuration Preview</h4>
                        <pre class="bg-gray-800 text-gray-100 p-4 rounded text-xs overflow-x-auto">{{ JSON.stringify({ steps: form.steps }, null, 2) }}</pre>
                    </div>

                    <div class="mt-6 flex items-center justify-end">
                        <PrimaryButton :class="{ 'opacity-25': form.processing }" :disabled="form.processing" @click="submit">
                            Save & Run Cleaning
                        </PrimaryButton>
                    </div>
                </div>

                <!-- Last Results -->
                <div v-if="lastCleanResult" class="p-4 sm:p-8 bg-white shadow sm:rounded-lg">
                    <h3 class="text-lg font-medium text-gray-900 mb-4">Last Cleaning Result</h3>
                    <pre class="bg-gray-50 p-4 rounded text-sm overflow-x-auto border">{{ lastCleanResult }}</pre>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
