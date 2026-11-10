<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { onMounted, onUnmounted, ref } from 'vue';
import ProfileViewer from '@/Components/ProfileViewer.vue';

const props = defineProps({
    run: Object,
    canProcess: Boolean,
});

let pollInterval = null;

const startPolling = () => {
    if (!pollInterval) {
        pollInterval = setInterval(() => {
            router.reload({ only: ['run'], preserveScroll: true, onSuccess: () => checkPollingStatus() });
        }, 2000); // Check every 2 seconds
    }
};

const stopPolling = () => {
    if (pollInterval) {
        clearInterval(pollInterval);
        pollInterval = null;
    }
};

const checkPollingStatus = () => {
    const activeStatuses = ['pending', 'profiling', 'cleaning', 'validating', 'exporting'];
    if (activeStatuses.includes(props.run.status)) {
        startPolling();
    } else {
        stopPolling();
    }
};

onMounted(() => {
    checkPollingStatus();
});

onUnmounted(() => {
    stopPolling();
});

const refreshRun = () => {
    router.reload({ only: ['run'], preserveScroll: true });
};

const statusColors = {
    pending: 'bg-gray-100 text-gray-700',
    profiling: 'bg-blue-100 text-blue-700',
    profiled: 'bg-green-100 text-green-700',
    cleaning: 'bg-yellow-100 text-yellow-700',
    cleaned: 'bg-green-100 text-green-700',
    validating: 'bg-purple-100 text-purple-700',
    validated: 'bg-emerald-100 text-emerald-700',
    validation_failed: 'bg-orange-100 text-orange-700',
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

const getStepLabel = (step, index, steps) => {
    let label = stepTypeLabels[step.step_type] || step.step_type;
    if (step.step_type === 'profile') {
        const hasOlderCleanStep = steps.slice(index + 1).some(s => s.step_type === 'clean');
        if (hasOlderCleanStep) {
            label = 'Post-Cleaning Profile';
        } else {
            label = 'Initial Profile';
        }
    }
    return label;
};

const deleteRun = () => {
    if (confirm('Are you sure you want to delete this run?')) {
        router.delete(route('runs.destroy', props.run.id));
    }
};

const reprofileRun = () => {
    if (confirm('Are you sure you want to re-profile this run? This will discard subsequent steps.')) {
        router.post(route('runs.reprofile', props.run.id));
    }
};

const getCorrelatedPairs = (matrix) => {
    const pairs = [];
    if (!matrix) return pairs;
    const cols = Object.keys(matrix);
    for (let i = 0; i < cols.length; i++) {
        for (let j = i + 1; j < cols.length; j++) {
            const val = matrix[cols[i]][cols[j]];
            if (val !== undefined && Math.abs(val) > 0.7) {
                pairs.push({ col1: cols[i], col2: cols[j], val: val });
            }
        }
    }
    return pairs.sort((a, b) => Math.abs(b.val) - Math.abs(a.val));
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
                        {{ run.source_file_path.split(/[\\/]/).pop() }}
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
                        v-if="run.status === 'profiling' || run.status === 'cleaning' || run.status === 'validating' || run.status === 'exporting'"
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
                <div v-if="canProcess && ['profiled', 'cleaned', 'validated', 'validation_failed', 'delivered', 'failed'].includes(run.status)" class="overflow-hidden bg-white shadow-sm sm:rounded-lg">
                    <div class="p-6">
                        <h3 class="text-lg font-medium text-gray-900 mb-4">Actions</h3>
                        <div class="flex flex-wrap gap-3">
                            <button
                                @click="reprofileRun"
                                class="inline-flex items-center rounded-md bg-blue-50 px-4 py-2 text-sm font-semibold text-blue-700 ring-1 ring-blue-200 hover:bg-blue-100"
                            >
                                Re-Profile
                            </button>
                            <p v-if="run.status === 'failed'" class="self-center text-sm text-gray-500">
                                The last step failed. Re-profile the run to continue.
                            </p>
                            <template v-else>
                            <Link
                                :href="route('runs.clean', run.id)"
                                class="inline-flex items-center rounded-md bg-yellow-50 px-4 py-2 text-sm font-semibold text-yellow-700 ring-1 ring-yellow-200 hover:bg-yellow-100"
                            >
                                Clean Data
                            </Link>
                            <Link
                                :href="route('runs.validate', run.id)"
                                class="inline-flex items-center rounded-md bg-purple-50 px-4 py-2 text-sm font-semibold text-purple-700 ring-1 ring-purple-200 hover:bg-purple-100"
                            >
                                Validate
                            </Link>
                            <Link
                                :href="route('runs.export', run.id)"
                                class="inline-flex items-center rounded-md bg-indigo-50 px-4 py-2 text-sm font-semibold text-indigo-700 ring-1 ring-indigo-200 hover:bg-indigo-100"
                            >
                                Export
                            </Link>
                            </template>
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
                                v-for="(step, idx) in run.steps"
                                :key="step.id"
                                class="rounded-lg border border-gray-200 p-4"
                            >
                                <div class="flex items-center justify-between mb-2">
                                    <div class="flex items-center gap-2">
                                        <span class="font-medium text-gray-900">
                                            {{ getStepLabel(step, idx, run.steps) }}
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

                                <details :open="run.steps.indexOf(step) === 0" class="group">
                                    <summary class="cursor-pointer text-sm text-blue-600 hover:text-blue-800 mb-2 font-medium select-none">
                                        <span class="group-open:hidden">▶ Show Details</span>
                                        <span class="hidden group-open:inline">▼ Hide Details</span>
                                    </summary>

                                <!-- Profile Report -->
                                <div v-if="step.step_type === 'profile' && step.result && step.status === 'completed'" class="mt-3">
                                    <ProfileViewer :profile="step.result" />
                                </div>

                                <!-- Error display -->
                                <div v-if="step.status === 'failed' && step.result?.error" class="mt-3">
                                    <div class="rounded-md bg-red-50 p-3 text-sm text-red-700">
                                        {{ step.result.error }}
                                    </div>
                                </div>

                                <!-- Clean Result -->
                                <div v-else-if="step.result && step.status === 'completed' && step.step_type === 'clean'" class="mt-3">
                                    <div class="grid grid-cols-2 md:grid-cols-4 gap-4 text-sm mb-6">
                                        <div class="bg-gray-50 p-3 rounded shadow-sm border border-gray-100">
                                            <span class="block text-gray-500 text-xs uppercase tracking-wider">Rows Before</span>
                                            <span class="text-lg font-semibold">{{ step.result.diff_summary?.initial_rows || '-' }}</span>
                                        </div>
                                        <div class="bg-gray-50 p-3 rounded shadow-sm border border-gray-100">
                                            <span class="block text-gray-500 text-xs uppercase tracking-wider">Rows After</span>
                                            <span class="text-lg font-semibold">{{ step.result.diff_summary?.final_rows || '-' }}</span>
                                        </div>
                                        <div class="bg-gray-50 p-3 rounded shadow-sm border border-gray-100">
                                            <span class="block text-gray-500 text-xs uppercase tracking-wider">Rows Modified</span>
                                            <span class="text-lg font-semibold">{{ step.result.rows_modified || '-' }}</span>
                                        </div>
                                        <div class="bg-gray-50 p-3 rounded shadow-sm border border-gray-100">
                                            <span class="block text-gray-500 text-xs uppercase tracking-wider">Steps Applied</span>
                                            <span class="text-lg font-semibold">{{ step.result.diff_summary?.steps_applied || '-' }}</span>
                                        </div>
                                    </div>
                                </div>

                                <!-- Raw result (fallback) -->
                                <div v-else-if="step.result && step.status === 'completed' && !['profile', 'clean'].includes(step.step_type)" class="mt-3">
                                    <details class="text-sm">
                                        <summary class="cursor-pointer text-gray-500 hover:text-gray-700">View result data</summary>
                                        <pre class="mt-2 overflow-x-auto rounded bg-gray-50 p-3 text-xs">{{ JSON.stringify(step.result, null, 2) }}</pre>
                                    </details>
                                </div>
                                </details>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
