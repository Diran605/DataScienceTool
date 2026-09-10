<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link } from '@inertiajs/vue3';

defineProps({
    delivery: Object,
});
</script>

<template>
    <Head title="Delivery Details" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Delivery #{{ delivery?.id }}</h2>
        </template>

        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
                <div class="p-4 sm:p-8 bg-white shadow sm:rounded-lg">
                    <h3 class="text-lg font-medium text-gray-900 mb-4">Delivery Information</h3>
                    <div class="grid grid-cols-2 gap-4 text-sm text-gray-700">
                        <div><strong>Run ID:</strong> {{ delivery?.run_id }}</div>
                        <div><strong>Format:</strong> {{ delivery?.format }}</div>
                        <div><strong>Contract Team:</strong> {{ delivery?.contract?.dev_team_name }}</div>
                        <div><strong>Delivered By:</strong> {{ delivery?.user?.name }}</div>
                        <div><strong>Date:</strong> {{ delivery?.created_at ? new Date(delivery.created_at).toLocaleString() : '' }}</div>
                        <div><strong>File Path:</strong> {{ delivery?.file_path }}</div>
                    </div>

                    <div class="mt-8">
                        <a v-if="delivery" :href="route('deliveries.download', delivery.id)" class="inline-flex items-center px-4 py-2 bg-gray-800 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700 focus:bg-gray-700 active:bg-gray-900 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 transition ease-in-out duration-150">
                            Download File
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
