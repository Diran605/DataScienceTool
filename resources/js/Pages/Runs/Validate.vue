<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import InputError from '@/Components/InputError.vue';

const props = defineProps({
    run: Object,
    contracts: Array,
});

const form = useForm({
    contract_id: '',
});

const overrideForm = useForm({
    reason: '',
});

const submit = () => {
    form.post(route('runs.validate.store', props.run.id));
};

const submitOverride = (stepId) => {
    overrideForm.post(route('runs.validate.override', { run: props.run.id, step: stepId }));
};

const validationSteps = props.run?.steps?.filter(s => s.type === 'validate') || [];
const latestValidation = validationSteps[validationSteps.length - 1];

</script>

<template>
    <Head title="Validate Data" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Validate Run: {{ run?.name }}</h2>
        </template>

        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
                
                <div class="p-4 sm:p-8 bg-white shadow sm:rounded-lg">
                    <h3 class="text-lg font-medium text-gray-900 mb-4">Select Contract</h3>
                    <form @submit.prevent="submit" class="space-y-4">
                        <div>
                            <InputLabel for="contract_id" value="Contract" />
                            <select v-model="form.contract_id" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm">
                                <option disabled value="">Please select one</option>
                                <option v-for="c in contracts" :key="c.id" :value="c.id">
                                    {{ c.dev_team_name }} ({{ c.format }})
                                </option>
                            </select>
                            <InputError :message="form.errors.contract_id" class="mt-2" />
                        </div>
                        
                        <div class="flex items-center gap-4">
                            <PrimaryButton :disabled="form.processing">Run Validation</PrimaryButton>
                        </div>
                    </form>
                </div>

                <div v-if="latestValidation" class="p-4 sm:p-8 bg-white shadow sm:rounded-lg">
                    <h3 class="text-lg font-medium text-gray-900 mb-4 flex items-center">
                        Validation Result
                        <span v-if="latestValidation.result?.passed" class="ml-3 px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800">Pass</span>
                        <span v-else class="ml-3 px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-red-100 text-red-800">Fail</span>
                    </h3>

                    <div v-if="!latestValidation.result?.passed" class="mt-4">
                        <h4 class="font-medium text-gray-700">Violations</h4>
                        <div class="mt-2 overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Field</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Rule</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Message</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    <tr v-for="(v, i) in latestValidation.result?.violations" :key="i">
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">{{ v.field }}</td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{{ v.rule }}</td>
                                        <td class="px-6 py-4 text-sm text-gray-500">{{ v.message }}</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        <div class="mt-8 border-t pt-6">
                            <h4 class="font-medium text-gray-700 mb-2">Override Failure</h4>
                            <form @submit.prevent="submitOverride(latestValidation.id)" class="flex gap-4 items-end">
                                <div class="flex-grow">
                                    <InputLabel for="reason" value="Reason for override" />
                                    <TextInput id="reason" type="text" v-model="overrideForm.reason" class="mt-1 block w-full" required />
                                </div>
                                <PrimaryButton :disabled="overrideForm.processing">Override</PrimaryButton>
                            </form>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </AuthenticatedLayout>
</template>
