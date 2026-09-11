<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import InputLabel from '@/Components/InputLabel.vue';
import InputError from '@/Components/InputError.vue';

const props = defineProps({
    run: Object,
    contracts: Array,
});

const form = useForm({
    format: 'csv',
    schema_contract_id: '',
});

const submit = () => {
    form.post(route('runs.export.store', props.run.id));
};

</script>

<template>
    <Head title="Export Data" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Export Data: {{ run?.source_file_path?.split(/[\\/]/).pop() || run?.id }}</h2>
        </template>

        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
                
                <div class="p-4 sm:p-8 bg-white shadow sm:rounded-lg">
                    <h3 class="text-lg font-medium text-gray-900 mb-4">Run Summary</h3>
                    <div class="bg-gray-50 p-4 rounded text-sm text-gray-700 grid grid-cols-2 gap-4">
                        <div><strong>File:</strong> {{ run?.source_file_path?.split(/[\\/]/).pop() }}</div>
                        <div>
                            <strong>Status:</strong> 
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium capitalize"
                                :class="{
                                    'bg-green-100 text-green-800': run.status === 'delivered',
                                    'bg-indigo-100 text-indigo-800': run.status === 'exporting' || run.status === 'validating',
                                    'bg-blue-100 text-blue-800': run.status === 'profiled',
                                    'bg-gray-100 text-gray-800': !['delivered', 'exporting', 'validating', 'profiled'].includes(run.status)
                                }">
                                {{ run?.status }}
                            </span>
                        </div>
                    </div>
                </div>

                <div class="p-4 sm:p-8 bg-white shadow sm:rounded-lg">
                    <h3 class="text-lg font-medium text-gray-900 mb-4">Export & Delivery Settings</h3>
                    <form @submit.prevent="submit" class="space-y-6 max-w-xl">
                        <div>
                            <InputLabel for="schema_contract_id" value="Target Schema Contract (Optional)" />
                            <select id="schema_contract_id" v-model="form.schema_contract_id" class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm">
                                <option value="">No Contract (Export as is)</option>
                                <option v-for="c in contracts" :key="c.id" :value="c.id">
                                    {{ c.dev_team_name }} - {{ c.target_table_name }} ({{ c.format }})
                                </option>
                            </select>
                            <p class="mt-1 text-xs text-gray-500">Optionally transform the output to match a specific team's schema contract.</p>
                            <InputError :message="form.errors.schema_contract_id" class="mt-2" />
                        </div>

                        <div>
                            <InputLabel for="format" value="Export Format" />
                            <select id="format" v-model="form.format" class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm">
                                <option value="csv">CSV (Comma-separated values)</option>
                                <option value="json">JSON</option>
                                <option value="parquet">Parquet</option>
                            </select>
                            <InputError :message="form.errors.format" class="mt-2" />
                        </div>
                        
                        <div class="flex items-center gap-4 pt-4">
                            <PrimaryButton :class="{ 'opacity-25': form.processing }" :disabled="form.processing">Export and Deliver</PrimaryButton>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
