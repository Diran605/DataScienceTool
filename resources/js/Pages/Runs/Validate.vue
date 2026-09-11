<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import InputError from '@/Components/InputError.vue';
import { computed } from 'vue';

const props = defineProps({
    run: Object,
    contracts: Array,
});

const form = useForm({
    schema_contract_id: '',
});

const submit = () => {
    form.post(route('runs.validate.store', props.run.id));
};

const validationSteps = props.run?.steps?.filter(s => s.step_type === 'validate') || [];
const latestValidation = validationSteps[validationSteps.length - 1];
</script>

<template>
    <Head title="Validate Data" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Validate Data: {{ run?.source_file_path?.split(/[\\/]/).pop() || run?.id }}</h2>
        </template>

        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
                
                <div class="p-4 sm:p-8 bg-white shadow sm:rounded-lg">
                    <h3 class="text-lg font-medium text-gray-900 mb-4">Select Contract</h3>
                    <form @submit.prevent="submit" class="space-y-4">
                        <div>
                            <InputLabel for="schema_contract_id" value="Contract" />
                            <select v-model="form.schema_contract_id" class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm">
                                <option disabled value="">Please select one</option>
                                <option v-for="c in contracts" :key="c.id" :value="c.id">
                                    {{ c.dev_team_name }} - {{ c.target_table_name }} ({{ c.format }})
                                </option>
                            </select>
                            <InputError :message="form.errors.schema_contract_id" class="mt-2" />
                        </div>
                        
                        <div class="flex items-center gap-4 pt-2">
                            <PrimaryButton :class="{ 'opacity-25': form.processing }" :disabled="form.processing">Run Validation</PrimaryButton>
                        </div>
                    </form>
                </div>

                <div v-if="latestValidation && latestValidation.result" class="p-4 sm:p-8 bg-white shadow sm:rounded-lg">
                    <h3 class="text-lg font-medium text-gray-900 mb-4 flex items-center">
                        Validation Result
                        <span v-if="latestValidation.result.is_valid" class="ml-3 px-2.5 py-0.5 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800">Pass</span>
                        <span v-else class="ml-3 px-2.5 py-0.5 inline-flex text-xs leading-5 font-semibold rounded-full bg-red-100 text-red-800">Fail</span>
                    </h3>
                    
                    <div v-if="latestValidation.result.is_valid" class="bg-green-50 text-green-700 p-4 rounded-md border border-green-200">
                        The dataset completely matches the selected schema contract and is ready for export.
                        <div class="mt-4">
                            <Link :href="route('runs.export', run.id)" class="inline-flex items-center px-4 py-2 bg-green-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-green-700 focus:bg-green-700 active:bg-green-900 focus:outline-none focus:ring-2 focus:ring-green-500 focus:ring-offset-2 transition ease-in-out duration-150">
                                Proceed to Export
                            </Link>
                        </div>
                    </div>

                    <div v-if="!latestValidation.result.is_valid && latestValidation.result.errors" class="mt-4">
                        <h4 class="font-medium text-gray-700 mb-3 border-b pb-2">Violations</h4>
                        <div class="overflow-x-auto border border-gray-200 rounded-lg shadow-sm">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Column</th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Rule</th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Message</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    <tr v-for="(v, i) in latestValidation.result.errors" :key="i" class="hover:bg-gray-50">
                                        <td class="px-4 py-3 whitespace-nowrap text-sm font-medium text-gray-900">{{ v.column || '-' }}</td>
                                        <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-500"><span class="bg-gray-100 px-2 py-1 rounded text-xs font-mono">{{ v.rule }}</span></td>
                                        <td class="px-4 py-3 text-sm text-red-600">{{ v.message }}</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                        
                        <div class="mt-6">
                            <Link :href="route('runs.clean', run.id)" class="inline-flex items-center rounded-md bg-yellow-50 px-4 py-2 text-sm font-semibold text-yellow-700 ring-1 ring-yellow-200 hover:bg-yellow-100">
                                Back to Cleaning
                            </Link>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </AuthenticatedLayout>
</template>
