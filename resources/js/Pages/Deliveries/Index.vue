<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link } from '@inertiajs/vue3';

defineProps({
    deliveries: Object,
});
</script>

<template>
    <Head title="Deliveries" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Deliveries</h2>
        </template>

        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <div class="bg-white shadow sm:rounded-lg overflow-hidden">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Run ID</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Contract Team</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Format</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Delivered By</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Date</th>
                                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <tr v-for="delivery in deliveries?.data" :key="delivery.id">
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">{{ delivery.run_id }}</td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{{ delivery.contract?.dev_team_name }}</td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{{ delivery.format }}</td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{{ delivery.user?.name }}</td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{{ new Date(delivery.created_at).toLocaleDateString() }}</td>
                                <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                    <Link :href="route('deliveries.show', delivery.id)" class="text-indigo-600 hover:text-indigo-900 mr-4">View</Link>
                                    <a :href="route('deliveries.download', delivery.id)" class="text-green-600 hover:text-green-900">Download</a>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
