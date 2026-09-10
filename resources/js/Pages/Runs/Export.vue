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
    format: '',
    contract_id: '',
});

const submit = () => {
    form.post(route('runs.export.store', props.run.id));
};

</script>

<template>
    <Head title="Export Data" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Export Run: {{ run?.name }}</h2>
        </template>

        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
                
                <div class="p-4 sm:p-8 bg-white shadow sm:rounded-lg">
                    <h3 class="text-lg font-medium text-gray-900 mb-4">Run Summary</h3>
                    <div class="bg-gray-50 p-4 rounded text-sm text-gray-700">
                        <p><strong>Name:</strong> {{ run?.name }}</p>
                        <p><strong>Status:</strong> {{ run?.status }}</p>
                    </div>
                </div>

                <div class="p-4 sm:p-8 bg-white shadow sm:rounded-lg">
                    <h3 class="text-lg font-medium text-gray-900 mb-4">Export & Delivery Settings</h3>
                    <form @submit.prevent="submit" class="space-y-4">
                        <div>
                            <InputLabel for="contract_id" value="Contract" />
                            <select v-model="form.contract_id" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm">
                                <option disabled value="">Select Contract</option>
                                <option v-for="c in contracts" :key="c.id" :value="c.id">
                                    {{ c.dev_team_name }}
                                </option>
                            </select>
                            <InputError :message="form.errors.contract_id" class="mt-2" />
                        </div>

                        <div>
                            <InputLabel for="format" value="Format" />
                            <select v-model="form.format" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm">
                                <option value="csv">CSV</option>
                                <option value="json">JSON</option>
                                <option value="parquet">Parquet</option>
                            </select>
                            <InputError :message="form.errors.format" class="mt-2" />
                        </div>
                        
                        <div class="flex items-center gap-4">
                            <PrimaryButton :disabled="form.processing">Export and Deliver</PrimaryButton>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
