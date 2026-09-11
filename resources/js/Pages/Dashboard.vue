<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link } from '@inertiajs/vue3';

defineProps({
    stats: {
        type: Object,
        default: () => ({
            total_runs: 0,
            profiled: 0,
            delivered: 0,
            failed: 0,
        })
    },
    recent_runs: {
        type: Array,
        default: () => []
    }
});
</script>

<template>
    <Head title="Dashboard" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <h2 class="text-xl font-semibold leading-tight text-gray-800">
                    Dashboard
                </h2>
                <div class="flex space-x-4">
                    <Link
                        :href="route('runs.create')"
                        class="inline-flex items-center rounded-md border border-transparent bg-indigo-600 px-4 py-2 text-sm font-medium text-white shadow-sm hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2"
                    >
                        New Run
                    </Link>
                    <Link
                        :href="route('runs.index')"
                        class="inline-flex items-center rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 shadow-sm hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2"
                    >
                        View All Runs
                    </Link>
                </div>
            </div>
        </template>

        <div class="py-12">
            <div class="mx-auto max-w-7xl sm:px-6 lg:px-8">
                
                <!-- Stat Cards -->
                <div class="grid grid-cols-1 gap-5 sm:grid-cols-2 lg:grid-cols-4 mb-8">
                    <div class="overflow-hidden rounded-lg bg-white px-4 py-5 shadow sm:p-6">
                        <dt class="truncate text-sm font-medium text-gray-500">Total Runs</dt>
                        <dd class="mt-1 text-3xl font-semibold tracking-tight text-gray-900">{{ stats.total_runs }}</dd>
                    </div>
                    <div class="overflow-hidden rounded-lg bg-white px-4 py-5 shadow sm:p-6">
                        <dt class="truncate text-sm font-medium text-gray-500">Profiled</dt>
                        <dd class="mt-1 text-3xl font-semibold tracking-tight text-gray-900">{{ stats.profiled }}</dd>
                    </div>
                    <div class="overflow-hidden rounded-lg bg-white px-4 py-5 shadow sm:p-6">
                        <dt class="truncate text-sm font-medium text-gray-500">Delivered</dt>
                        <dd class="mt-1 text-3xl font-semibold tracking-tight text-gray-900">{{ stats.delivered }}</dd>
                    </div>
                    <div class="overflow-hidden rounded-lg bg-white px-4 py-5 shadow sm:p-6">
                        <dt class="truncate text-sm font-medium text-gray-500">Failed</dt>
                        <dd class="mt-1 text-3xl font-semibold tracking-tight text-gray-900">{{ stats.failed }}</dd>
                    </div>
                </div>

                <!-- Recent Runs Table -->
                <div class="bg-white shadow sm:rounded-lg">
                    <div class="border-b border-gray-200 px-4 py-5 sm:px-6">
                        <h3 class="text-base font-semibold leading-6 text-gray-900">Recent Runs</h3>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-300">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th scope="col" class="py-3.5 pl-4 pr-3 text-left text-sm font-semibold text-gray-900 sm:pl-6">ID</th>
                                    <th scope="col" class="px-3 py-3.5 text-left text-sm font-semibold text-gray-900">File</th>
                                    <th scope="col" class="px-3 py-3.5 text-left text-sm font-semibold text-gray-900">Status</th>
                                    <th scope="col" class="px-3 py-3.5 text-left text-sm font-semibold text-gray-900">Date</th>
                                    <th scope="col" class="relative py-3.5 pl-3 pr-4 sm:pr-6">
                                        <span class="sr-only">View</span>
                                    </th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200 bg-white">
                                <tr v-for="run in recent_runs" :key="run.id">
                                    <td class="whitespace-nowrap py-4 pl-4 pr-3 text-sm font-medium text-gray-900 sm:pl-6">
                                        {{ run.id }}
                                    </td>
                                    <td class="whitespace-nowrap px-3 py-4 text-sm text-gray-500">
                                        {{ run.source_file_path ? run.source_file_path.split(/[\\/]/).pop() : 'N/A' }}
                                    </td>
                                    <td class="whitespace-nowrap px-3 py-4 text-sm text-gray-500">
                                        <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium capitalize"
                                            :class="{
                                                'bg-green-100 text-green-800': run.status === 'delivered',
                                                'bg-red-100 text-red-800': run.status === 'failed',
                                                'bg-yellow-100 text-yellow-800': ['pending', 'processing'].includes(run.status),
                                                'bg-blue-100 text-blue-800': run.status === 'profiled'
                                            }">
                                            {{ run.status }}
                                        </span>
                                    </td>
                                    <td class="whitespace-nowrap px-3 py-4 text-sm text-gray-500">
                                        {{ new Date(run.created_at).toLocaleDateString() }}
                                    </td>
                                    <td class="relative whitespace-nowrap py-4 pl-3 pr-4 text-right text-sm font-medium sm:pr-6">
                                        <Link :href="route('runs.show', run.id)" class="text-indigo-600 hover:text-indigo-900">
                                            View<span class="sr-only">, {{ run.id }}</span>
                                        </Link>
                                    </td>
                                </tr>
                                <tr v-if="!recent_runs.length">
                                    <td colspan="5" class="py-4 text-center text-sm text-gray-500">No recent runs found.</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

            </div>
        </div>
    </AuthenticatedLayout>
</template>
