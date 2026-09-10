<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';

const props = defineProps({
    run: Object,
});

const statusColors = {
    pending: 'bg-gray-100 text-gray-700',
    profiling: 'bg-blue-100 text-blue-700',
    profiled: 'bg-green-100 text-green-700',
    cleaning: 'bg-yellow-100 text-yellow-700',
    validating: 'bg-purple-100 text-purple-700',
    exporting: 'bg-indigo-100 text-indigo-700',
    delivered: 'bg-emerald-100 text-emerald-700',
    failed: 'bg-red-100 text-red-700',
};

const stepTypeLabels = {
    profile: 'Profiling',
    clean: 'Cleaning',
    validate: 'Validation',
    export: 'Export',
};

const stepStatusColors = {
    pending: 'text-gray-500',
    running: 'text-blue-600',
    completed: 'text-green-600',
    failed: 'text-red-600',
};

const deleteRun = () => {
    if (confirm('Are you sure you want to delete this run?')) {
        router.delete(route('runs.destroy', props.run.id));
    }
};

const refreshRun = () => {
    router.reload({ only: ['run'] });
};
</script>

<template>
    <Head :title="`Run #${run.id}`" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <div>
                    <h2 class="text-xl font-semibold leading-tight text-gray-800">
                        Run #{{ run.id }}
                    </h2>
                    <p class="mt-1 text-sm text-gray-500">
                        {{ run.source_file_path.split('/').pop() }}
                    </p>
                </div>
                <div class="flex items-center gap-3">
                    <span
                        class="inline-flex rounded-full px-3 py-1 text-sm font-semibold"
                        :class="statusColors[run.status] || 'bg-gray-100 text-gray-700'"
                    >
                        {{ run.status }}
                    </span>
                    <button
                        v-if="run.status === 'profiling' || run.status === 'cleaning'"
                        @click="refreshRun"
                        class="inline-flex items-center rounded-md bg-white px-3 py-2 text-sm font-semibold text-gray-700 shadow-sm ring-1 ring-gray-300 hover:bg-gray-50"
                    >
                        Refresh
                    </button>
                    <button
                        @click="deleteRun"
                        class="inline-flex items-center rounded-md bg-red-50 px-3 py-2 text-sm font-semibold text-red-700 ring-1 ring-red-200 hover:bg-red-100"
                    >
                        Delete
                    </button>
                </div>
            </div>
        </template>

        <div class="py-12">
            <div class="mx-auto max-w-7xl sm:px-6 lg:px-8 space-y-6">
                <!-- Run Info -->
                <div class="overflow-hidden bg-white shadow-sm sm:rounded-lg">
                    <div class="p-6">
                        <h3 class="text-lg font-medium text-gray-900 mb-4">Run Details</h3>
                        <dl class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                            <div>
                                <dt class="text-sm font-medium text-gray-500">File Type</dt>
                                <dd class="mt-1 text-sm text-gray-900 uppercase">{{ run.source_file_type }}</dd>
                            </div>
                            <div>
                                <dt class="text-sm font-medium text-gray-500">Created By</dt>
                                <dd class="mt-1 text-sm text-gray-900">{{ run.creator?.name }}</dd>
                            </div>
                            <div>
                                <dt class="text-sm font-medium text-gray-500">Created At</dt>
                                <dd class="mt-1 text-sm text-gray-900">{{ new Date(run.created_at).toLocaleString() }}</dd>
                            </div>
                        </dl>
                    </div>
                </div>

                <!-- Workflow Actions -->
                <div v-if="['profiled', 'delivered'].includes(run.status)" class="overflow-hidden bg-white shadow-sm sm:rounded-lg">
                    <div class="p-6">
                        <h3 class="text-lg font-medium text-gray-900 mb-4">Actions</h3>
                        <div class="flex flex-wrap gap-3">
                            <Link
                                v-if="run.status === 'profiled'"
                                :href="route('runs.clean', run.id)"
                                class="inline-flex items-center rounded-md bg-yellow-50 px-4 py-2 text-sm font-semibold text-yellow-700 ring-1 ring-yellow-200 hover:bg-yellow-100"
                            >
                                Clean Data
                            </Link>
                            <Link
                                v-if="run.status === 'profiled'"
                                :href="route('runs.validate', run.id)"
                                class="inline-flex items-center rounded-md bg-purple-50 px-4 py-2 text-sm font-semibold text-purple-700 ring-1 ring-purple-200 hover:bg-purple-100"
                            >
                                Validate
                            </Link>
                            <Link
                                v-if="run.status === 'profiled'"
                                :href="route('runs.export', run.id)"
                                class="inline-flex items-center rounded-md bg-indigo-50 px-4 py-2 text-sm font-semibold text-indigo-700 ring-1 ring-indigo-200 hover:bg-indigo-100"
                            >
                                Export
                            </Link>
                        </div>
                    </div>
                </div>
                <div class="overflow-hidden bg-white shadow-sm sm:rounded-lg">
                    <div class="p-6">
                        <h3 class="text-lg font-medium text-gray-900 mb-4">Processing Steps</h3>

                        <div v-if="!run.steps || run.steps.length === 0" class="text-sm text-gray-500">
                            No steps recorded yet. Processing will begin shortly.
                        </div>

                        <div v-else class="space-y-4">
                            <div
                                v-for="step in run.steps"
                                :key="step.id"
                                class="rounded-lg border border-gray-200 p-4"
                            >
                                <div class="flex items-center justify-between mb-2">
                                    <div class="flex items-center gap-2">
                                        <span class="font-medium text-gray-900">
                                            {{ stepTypeLabels[step.step_type] || step.step_type }}
                                        </span>
                                        <span
                                            class="text-sm font-medium"
                                            :class="stepStatusColors[step.status] || 'text-gray-500'"
                                        >
                                            {{ step.status }}
                                        </span>
                                    </div>
                                    <span class="text-xs text-gray-400">
                                        {{ new Date(step.updated_at).toLocaleString() }}
                                    </span>
                                </div>

                                <!-- Profile Report -->
                                <div v-if="step.step_type === 'profile' && step.result && step.status === 'completed'" class="mt-3">
                                    <div class="rounded-md bg-gray-50 p-4">
                                        <h4 class="text-sm font-medium text-gray-700 mb-3">Quality Report</h4>

                                        <div v-if="step.result.columns" class="overflow-x-auto">
                                            <table class="min-w-full text-xs">
                                                <thead>
                                                    <tr class="border-b">
                                                        <th class="py-2 pr-4 text-left font-medium text-gray-600">Column</th>
                                                        <th class="py-2 pr-4 text-left font-medium text-gray-600">Type</th>
                                                        <th class="py-2 pr-4 text-right font-medium text-gray-600">Nulls</th>
                                                        <th class="py-2 pr-4 text-right font-medium text-gray-600">Unique</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <tr v-for="col in step.result.columns" :key="col.name" class="border-b border-gray-100">
                                                        <td class="py-2 pr-4 font-medium">{{ col.name }}</td>
                                                        <td class="py-2 pr-4 text-gray-500">{{ col.dtype }}</td>
                                                        <td class="py-2 pr-4 text-right" :class="col.null_count > 0 ? 'text-red-600 font-medium' : 'text-gray-500'">
                                                            {{ col.null_count }}
                                                        </td>
                                                        <td class="py-2 pr-4 text-right text-gray-500">{{ col.unique_count }}</td>
                                                    </tr>
                                                </tbody>
                                            </table>
                                        </div>

                                        <div v-if="step.result.summary" class="mt-3 grid grid-cols-3 gap-4 text-sm">
                                            <div>
                                                <span class="text-gray-500">Rows:</span>
                                                <span class="ml-1 font-medium">{{ step.result.summary.row_count }}</span>
                                            </div>
                                            <div>
                                                <span class="text-gray-500">Columns:</span>
                                                <span class="ml-1 font-medium">{{ step.result.summary.column_count }}</span>
                                            </div>
                                            <div>
                                                <span class="text-gray-500">Duplicates:</span>
                                                <span class="ml-1 font-medium" :class="step.result.summary.duplicate_rows > 0 ? 'text-red-600' : ''">
                                                    {{ step.result.summary.duplicate_rows }}
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Error display -->
                                <div v-if="step.status === 'failed' && step.result?.error" class="mt-3">
                                    <div class="rounded-md bg-red-50 p-3 text-sm text-red-700">
                                        {{ step.result.error }}
                                    </div>
                                </div>

                                <!-- Raw result (fallback) -->
                                <div v-else-if="step.result && step.status === 'completed' && step.step_type !== 'profile'" class="mt-3">
                                    <details class="text-sm">
                                        <summary class="cursor-pointer text-gray-500 hover:text-gray-700">View result data</summary>
                                        <pre class="mt-2 overflow-x-auto rounded bg-gray-50 p-3 text-xs">{{ JSON.stringify(step.result, null, 2) }}</pre>
                                    </details>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
