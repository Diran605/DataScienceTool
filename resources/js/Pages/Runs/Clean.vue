<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, useForm, router } from '@inertiajs/vue3';
import { ref, computed } from 'vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import ProfileViewer from '@/Components/ProfileViewer.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';

const props = defineProps({
    run: Object,
    profileResult: Object,
    lastCleanResult: Object,
    lastCleanConfig: Object,
});

const availableActions = [
    { type: 'drop_nulls', label: 'Drop Nulls', desc: 'Remove any rows containing empty cells.' },
    { type: 'drop_columns', label: 'Drop Columns', desc: 'Remove an entire column completely if it has too much missing data.' },
    { type: 'fill_nulls', label: 'Fill Nulls', desc: 'Replace empty cells with Mean, Median, Mode, or a Custom Value.' },
    { type: 'handle_outliers', label: 'Handle Outliers', desc: 'Cap (Winsorize) or Filter (Drop) extreme values using percentiles or thresholds.' },
    { type: 'deduplicate', label: 'Remove Duplicates', desc: 'Remove identical, repeated rows from the dataset.' },
    { type: 'normalize_dates', label: 'Format Dates', desc: 'Force dates into a standard YYYY-MM-DD format.' },
    { type: 'normalize_text', label: 'Normalize Text', desc: 'Convert text to lowercase and strip extra whitespace.' },
    { type: 'rename_columns', label: 'Rename Columns', desc: 'Rename columns for clarity using a mapping.' },
    { type: 'regex_extract', label: 'Regex / Text Extract', desc: 'Extract specific patterns (like emails or IDs) into new columns.' },
    { type: 'custom_expression', label: 'Custom Expression', desc: 'Advanced math or logic using Python/Pandas (e.g., df["Total"] = df["Qty"] * 2).' },
];

const form = useForm({
    steps: props.lastCleanConfig?.steps || [],
    options: {},
});

const selectedActionType = ref('');
const newActionConfig = ref({
    columns: '',
    strategy: '',
    value: '',
    custom_expression: '',
});

const availableColumns = computed(() => {
    if (!props.profileResult || !props.profileResult.columns) return [];
    return Object.keys(props.profileResult.columns);
});

const addStep = () => {
    if (!selectedActionType.value) return;
    
    const step = {
        action: selectedActionType.value,
        columns: newActionConfig.value.columns ? newActionConfig.value.columns.split(',').map(s => s.trim()).filter(s => s) : [],
        strategy: newActionConfig.value.strategy || null,
        value: newActionConfig.value.value || null,
        custom_expression: newActionConfig.value.custom_expression || null,
    };
    
    form.steps.push(step);
    
    selectedActionType.value = '';
    newActionConfig.value = { columns: '', strategy: '', value: '', custom_expression: '' };
};

const removeStep = (index) => {
    form.steps.splice(index, 1);
};

const submit = (preview = false) => {
    form.options = { preview_only: preview };
    form.put(route('runs.clean.update', props.run.id), {
        preserveScroll: true,
    });
};
</script>

<template>
    <Head title="Clean Data" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Clean Data: {{ run?.source_file_path?.split(/[\\/]/).pop() || run?.id }}</h2>
        </template>

        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
                
                <div class="p-4 sm:p-8 bg-white shadow sm:rounded-lg">
                    <h3 class="text-lg font-medium text-gray-900 mb-4">Available Actions</h3>
                    <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-4">
                        <div v-for="action in availableActions" :key="action.type" 
                             @click="selectedActionType = action.type; newActionConfig = { columns: '', strategy: '', value: '', custom_expression: '' }"
                             class="p-4 border rounded cursor-pointer transition-colors flex flex-col justify-center h-full"
                             :class="selectedActionType === action.type ? 'bg-indigo-50 border-indigo-500 ring-1 ring-indigo-500' : 'hover:bg-gray-50'">
                            <span :class="selectedActionType === action.type ? 'text-indigo-700 font-bold' : 'text-gray-800 font-semibold'" class="block mb-1">{{ action.label }}</span>
                            <span class="text-xs text-gray-500 leading-relaxed">{{ action.desc }}</span>
                        </div>
                    </div>
                    
                    <div v-if="selectedActionType" class="mt-4 p-5 border rounded bg-gray-50">
                        <h4 class="font-medium text-gray-700 mb-4 border-b pb-2">Configure: {{ availableActions.find(a => a.type === selectedActionType)?.label }}</h4>
                        
                        <div class="grid gap-4 md:grid-cols-2">
                            <div class="col-span-2 md:col-span-1">
                                <InputLabel for="columns" value="Target Columns (comma separated)" />
                                <TextInput id="columns" v-model="newActionConfig.columns" type="text" class="mt-1 block w-full" placeholder="e.g. email, phone" />
                                <p class="mt-1 text-xs text-gray-500">Leave empty to apply to all applicable columns.</p>
                            </div>

                            <div v-if="['fill_nulls'].includes(selectedActionType)" class="col-span-2 md:col-span-1">
                                <InputLabel for="strategy" value="Strategy" />
                                <select id="strategy" v-model="newActionConfig.strategy" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                    <option value="">Select Strategy...</option>
                                    <option value="mean">Mean</option>
                                    <option value="median">Median</option>
                                    <option value="mode">Mode</option>
                                    <option value="constant">Constant</option>
                                </select>
                            </div>

                            <div v-if="['handle_outliers'].includes(selectedActionType)" class="col-span-2 md:col-span-1">
                                <InputLabel for="strategy" value="Strategy" />
                                <select id="strategy" v-model="newActionConfig.strategy" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                    <option value="">Select Strategy...</option>
                                    <option value="cap">Cap / Winsorize (Keep Row, Adjust Value)</option>
                                    <option value="filter">Filter (Delete Entire Row)</option>
                                </select>
                            </div>

                            <div v-if="['handle_outliers'].includes(selectedActionType)" class="col-span-2 md:col-span-1">
                                <InputLabel for="value" value="Threshold" />
                                <TextInput id="value" v-model="newActionConfig.value" type="text" class="mt-1 block w-full" placeholder="e.g., 0.99 or 10000" />
                                <p class="mt-1 text-xs text-gray-500">Enter a percentile (e.g. 0.99 for 99th percentile) or an absolute number (e.g. 10000).</p>
                            </div>

                            <div v-if="selectedActionType === 'fill_nulls' && newActionConfig.strategy === 'constant'" class="col-span-2 md:col-span-1">
                                <InputLabel for="value" value="Constant Value" />
                                <TextInput id="value" v-model="newActionConfig.value" type="text" class="mt-1 block w-full" />
                            </div>

                            <div v-if="['regex_extract'].includes(selectedActionType)" class="col-span-2 md:col-span-1">
                                <InputLabel for="value" value="Regex Pattern" />
                                <TextInput id="value" v-model="newActionConfig.value" type="text" class="mt-1 block w-full" placeholder="(?P<group_name>.*)" />
                            </div>

                            <div v-if="['rename_columns'].includes(selectedActionType)" class="col-span-2 md:col-span-1">
                                <InputLabel for="value" value="Mapping JSON (Value)" />
                                <TextInput id="value" v-model="newActionConfig.value" type="text" class="mt-1 block w-full" placeholder='{"old_name": "new_name"}' />
                            </div>

                            <div v-if="selectedActionType === 'custom_expression'" class="col-span-2">
                                <InputLabel for="custom_expression" value="Custom Pandas Expression" />
                                <textarea id="custom_expression" v-model="newActionConfig.custom_expression" rows="3" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500 font-mono text-sm" placeholder="df['col'] = df['col'] * 2"></textarea>
                                <p class="mt-1 text-xs text-gray-500">Use `df` as the dataframe variable.</p>
                            </div>
                        </div>

                        <div class="mt-4 flex justify-end">
                            <PrimaryButton @click="addStep" type="button">Add Step to Pipeline</PrimaryButton>
                        </div>
                    </div>
                </div>

                <div class="p-4 sm:p-8 bg-white shadow sm:rounded-lg">
                    <div v-if="$page.props.flash.error" class="mb-4 bg-red-50 text-red-700 p-4 rounded-md border border-red-200">
                        <h4 class="font-bold mb-1">Backend Error:</h4>
                        <p class="font-mono text-sm">{{ $page.props.flash.error }}</p>
                    </div>
                    <div v-if="$page.props.flash.success" class="mb-4 bg-green-50 text-green-700 p-4 rounded-md border border-green-200">
                        <p class="font-medium text-sm">{{ $page.props.flash.success }}</p>
                    </div>
                    <div v-if="Object.keys(form.errors).length > 0" class="mb-4 bg-red-50 text-red-700 p-4 rounded-md border border-red-200">
                        <h4 class="font-bold mb-1">Please fix the following errors:</h4>
                        <ul class="list-disc pl-5 text-sm">
                            <li v-for="(error, key) in form.errors" :key="key">{{ key }}: {{ error }}</li>
                        </ul>
                    </div>
                    <h3 class="text-lg font-medium text-gray-900 mb-4">Cleaning Pipeline</h3>
                    
                    <div v-if="form.steps.length > 0" class="space-y-3">
                        <div v-for="(step, index) in form.steps" :key="index" class="p-4 border border-gray-200 rounded-lg flex flex-col sm:flex-row justify-between items-start sm:items-center bg-gray-50 gap-4">
                            <div class="flex-1 w-full">
                                <div class="flex items-center gap-2 mb-1">
                                    <span class="inline-flex items-center justify-center h-6 w-6 rounded-full bg-indigo-100 text-indigo-700 font-bold text-xs">{{ index + 1 }}</span>
                                    <span class="font-bold text-gray-900">{{ step.action }}</span>
                                </div>
                                <div class="text-sm text-gray-600 ml-8 grid grid-cols-1 sm:grid-cols-2 gap-x-4 gap-y-1">
                                    <div v-if="step.columns && step.columns.length"><span class="font-medium text-gray-500">Columns:</span> {{ step.columns.join(', ') }}</div>
                                    <div v-else><span class="font-medium text-gray-500">Columns:</span> <span class="italic">All applicable</span></div>
                                    <div v-if="step.strategy"><span class="font-medium text-gray-500">Strategy:</span> {{ step.strategy }}</div>
                                    <div v-if="step.value"><span class="font-medium text-gray-500">Value:</span> {{ step.value }}</div>
                                    <div v-if="step.custom_expression" class="col-span-full"><span class="font-medium text-gray-500 block">Expression:</span> <code class="bg-gray-100 px-1 py-0.5 rounded text-xs">{{ step.custom_expression }}</code></div>
                                </div>
                            </div>
                            <button @click="removeStep(index)" type="button" class="text-red-500 hover:text-red-700 bg-red-50 px-3 py-1 rounded text-sm font-medium border border-red-100 hover:bg-red-100 shrink-0">
                                Remove
                            </button>
                        </div>
                    </div>
                    <div v-else class="text-gray-500 text-sm p-4 border border-dashed border-gray-300 rounded-lg text-center bg-gray-50">
                        No steps added to the pipeline yet. Add actions from above.
                    </div>

                    <div class="mt-6 flex flex-col sm:flex-row items-center justify-end gap-3 pt-4 border-t">
                        <SecondaryButton :class="{ 'opacity-25': form.processing }" :disabled="form.processing" @click="submit(true)">
                            Preview on Sample
                        </SecondaryButton>
                        <PrimaryButton :class="{ 'opacity-25': form.processing }" :disabled="form.processing" @click="submit(false)">
                            Save & Run Cleaning
                        </PrimaryButton>
                    </div>
                </div>

                <div v-if="lastCleanResult && Object.keys(lastCleanResult).length > 0" class="p-4 sm:p-8 bg-white shadow sm:rounded-lg overflow-hidden">
                    <h3 class="text-lg font-medium text-gray-900 mb-4">Last Output</h3>
                    
                    <div v-if="lastCleanResult.error" class="bg-red-50 text-red-700 p-4 rounded-md border border-red-200">
                        <h4 class="font-bold mb-1">Error processing steps:</h4>
                        <p class="font-mono text-sm">{{ lastCleanResult.error }}</p>
                    </div>
                    
                    <div v-else-if="lastCleanResult.diff_summary?.is_preview" class="space-y-4">
                        <div class="bg-blue-50 text-blue-700 p-3 rounded-md text-sm mb-4 border border-blue-100">
                            <strong>Preview Mode:</strong> Changes were applied to a sample of data and not saved.
                        </div>
                        
                        <div v-if="lastCleanResult.diff_summary.preview_profile">
                            <ProfileViewer :profile="lastCleanResult.diff_summary.preview_profile" />
                        </div>
                        <div v-else class="text-sm text-gray-500 italic">No profile data available for preview.</div>
                    </div>
                    
                    <div v-else class="space-y-4">
                        <div class="bg-green-50 text-green-700 p-3 rounded-md text-sm border border-green-100">
                            <strong>Success:</strong> Cleaning applied to the entire dataset successfully.
                        </div>
                        <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mt-4">
                             <div class="bg-gray-50 p-4 rounded border border-gray-100">
                                 <span class="block text-gray-500 text-xs uppercase">Rows Before</span>
                                 <span class="text-lg font-semibold">{{ lastCleanResult.diff_summary?.initial_rows || '-' }}</span>
                             </div>
                             <div class="bg-gray-50 p-4 rounded border border-gray-100">
                                 <span class="block text-gray-500 text-xs uppercase">Rows After</span>
                                 <span class="text-lg font-semibold">{{ lastCleanResult.diff_summary?.final_rows || '-' }}</span>
                             </div>
                             <div class="bg-gray-50 p-4 rounded border border-gray-100">
                                 <span class="block text-gray-500 text-xs uppercase">Rows Modified</span>
                                 <span class="text-lg font-semibold">{{ lastCleanResult.rows_modified || '-' }}</span>
                             </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
