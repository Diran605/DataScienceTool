<script setup>
const props = defineProps({
    profile: {
        type: Object,
        required: true
    }
});

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
    <div class="rounded-md bg-gray-50 p-4">
        <h4 class="text-md font-medium text-gray-700 mb-3 border-b pb-2">Dataset Summary</h4>
        
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4 text-sm mb-6">
            <div class="bg-white p-3 rounded shadow-sm">
                <span class="block text-gray-500 text-xs uppercase tracking-wider">Rows</span>
                <span class="text-lg font-semibold">{{ profile.row_count }}</span>
            </div>
            <div class="bg-white p-3 rounded shadow-sm">
                <span class="block text-gray-500 text-xs uppercase tracking-wider">Columns</span>
                <span class="text-lg font-semibold">{{ profile.column_count }}</span>
            </div>
            <div class="bg-white p-3 rounded shadow-sm">
                <span class="block text-gray-500 text-xs uppercase tracking-wider">Missing Cells</span>
                <span class="text-lg font-semibold" :class="profile.missing_cells > 0 ? 'text-red-600' : ''">
                    {{ profile.missing_cells }}
                </span>
            </div>
            <div class="bg-white p-3 rounded shadow-sm">
                <span class="block text-gray-500 text-xs uppercase tracking-wider">Duplicate Rows</span>
                <span class="text-lg font-semibold" :class="profile.duplicate_rows > 0 ? 'text-red-600' : ''">
                    {{ profile.duplicate_rows }}
                </span>
            </div>
        </div>

        <h4 class="text-md font-medium text-gray-700 mb-3 border-b pb-2">Columns Details</h4>
        
        <!-- Cleaning Recommendations -->
        <div v-if="profile.columns" class="bg-yellow-50 border border-yellow-200 p-4 rounded mb-6">
            <h4 class="text-sm font-bold text-yellow-800 mb-2">💡 Cleaning Recommendations</h4>
            <ul class="list-disc pl-5 text-sm text-yellow-700 space-y-1">
                <li v-if="profile.duplicate_rows > 0">
                    Dataset has <strong>{{ profile.duplicate_rows }} duplicate rows</strong>. Consider using the <em>Deduplicate</em> action.
                </li>
                <template v-for="(col, colName) in profile.columns" :key="'rec-'+colName">
                    <li v-if="col.null_count > 0">
                        <strong>{{ colName }}</strong> has {{ col.null_count }} missing values. Consider using <em>Fill Nulls</em> or <em>Drop Nulls</em>.
                    </li>
                    <li v-if="col.outlier_count > 0">
                        <strong>{{ colName }}</strong> has {{ col.outlier_count }} outliers. Consider using a <em>Custom Expression</em> to filter or cap them.
                    </li>
                </template>
                <li v-if="profile.duplicate_rows === 0 && !Object.values(profile.columns || {}).some(c => c.null_count > 0 || c.outlier_count > 0)" class="text-green-700 list-none -ml-5 font-medium">
                    ✨ Data looks clean! No immediate cleaning actions recommended.
                </li>
            </ul>
        </div>

        <div v-if="profile.columns" class="space-y-4">
            <div v-for="(col, colName) in profile.columns" :key="colName" class="bg-white p-4 rounded shadow-sm border border-gray-100">
                <div class="flex justify-between items-start mb-2">
                    <div>
                        <span class="font-bold text-gray-900 text-lg">{{ colName }}</span>
                        <span class="ml-2 text-xs font-mono bg-gray-100 px-2 py-1 rounded text-gray-600">{{ col.type }}</span>
                    </div>
                    <div class="text-right text-sm">
                        <div><span class="text-gray-500">Nulls:</span> <span :class="col.null_count > 0 ? 'text-red-600 font-bold' : ''">{{ col.null_count }}</span> ({{ col.null_percentage != null ? col.null_percentage.toFixed(1) : ((col.null_count / profile.row_count) * 100).toFixed(1) }}%)</div>
                        <div title="The number of distinct, non-repeating values in this column."><span class="text-gray-500 border-b border-dotted border-gray-400 cursor-help">Unique:</span> {{ col.unique_count }}</div>
                    </div>
                </div>

                <!-- Numeric Stats -->
                <div v-if="['numeric', 'integer', 'float', 'int64', 'float64'].includes(col.type)" class="mt-3">
                    <div class="grid grid-cols-4 md:grid-cols-8 gap-2 text-xs mb-3">
                        <div title="The smallest value in the column"><span class="text-gray-500 block border-b border-dotted border-gray-400 cursor-help w-max">Min</span>{{ col.min != null ? (typeof col.min === 'number' ? col.min.toFixed(2) : col.min) : '-' }}</div>
                        <div title="The 25th percentile. 25% of the data falls below this value."><span class="text-gray-500 block border-b border-dotted border-gray-400 cursor-help w-max">Q1</span>{{ col.q1 != null ? (typeof col.q1 === 'number' ? col.q1.toFixed(2) : col.q1) : '-' }}</div>
                        <div title="The middle value of the sorted data. Much better than Mean when there are extreme outliers."><span class="text-gray-500 block border-b border-dotted border-gray-400 cursor-help w-max">Median</span>{{ col.median != null ? (typeof col.median === 'number' ? col.median.toFixed(2) : col.median) : '-' }}</div>
                        <div title="The mathematical average."><span class="text-gray-500 block border-b border-dotted border-gray-400 cursor-help w-max">Mean</span>{{ col.mean != null ? (typeof col.mean === 'number' ? col.mean.toFixed(2) : col.mean) : '-' }}</div>
                        <div title="The 75th percentile. 75% of the data falls below this value."><span class="text-gray-500 block border-b border-dotted border-gray-400 cursor-help w-max">Q3</span>{{ col.q3 != null ? (typeof col.q3 === 'number' ? col.q3.toFixed(2) : col.q3) : '-' }}</div>
                        <div title="The largest value in the column"><span class="text-gray-500 block border-b border-dotted border-gray-400 cursor-help w-max">Max</span>{{ col.max != null ? (typeof col.max === 'number' ? col.max.toFixed(2) : col.max) : '-' }}</div>
                        <div title="Standard Deviation. A high number means the data is very spread out. A low number means the data is tightly clustered around the mean."><span class="text-gray-500 block border-b border-dotted border-gray-400 cursor-help w-max">Std Dev</span>{{ col.std != null ? (typeof col.std === 'number' ? col.std.toFixed(2) : col.std) : '-' }}</div>
                        <div><span class="text-gray-500 block">Outliers</span>
                            <span v-if="col.outlier_count > 0" class="inline-flex items-center rounded-full bg-red-100 px-2 py-0.5 text-xs font-medium text-red-800">{{ col.outlier_count }}</span>
                            <span v-else>0</span>
                        </div>
                    </div>
                    <div class="grid grid-cols-2 gap-2 text-xs mb-3">
                        <div title="Measures asymmetry. 0 is perfectly symmetrical. A positive number means a long 'tail' of high values. A negative number means a long 'tail' of low values."><span class="text-gray-500 border-b border-dotted border-gray-400 cursor-help">Skewness:</span> {{ col.skewness != null ? (typeof col.skewness === 'number' ? col.skewness.toFixed(2) : col.skewness) : '-' }}</div>
                        <div title="Measures how many extreme outliers exist. Above 3 means heavy outliers. Below 3 means very few outliers."><span class="text-gray-500 border-b border-dotted border-gray-400 cursor-help">Kurtosis:</span> {{ col.kurtosis != null ? (typeof col.kurtosis === 'number' ? col.kurtosis.toFixed(2) : col.kurtosis) : '-' }}</div>
                    </div>

                    <!-- Histogram -->
                    <div v-if="col.histogram_bins && col.histogram_bins.length > 0" class="mt-4">
                        <div class="text-xs text-gray-500 mb-1">Distribution</div>
                        <div class="flex items-end h-16 gap-0.5 w-full">
                            <div v-for="(bin, idx) in col.histogram_bins" :key="idx" 
                                class="bg-indigo-300 hover:bg-indigo-500 transition-colors flex-1"
                                :style="{ height: `${Math.max(1, (bin.count / Math.max(...col.histogram_bins.map(b => b.count))) * 100)}%` }"
                                :title="`Range: ${bin.min != null ? bin.min.toFixed(2) : ''} - ${bin.max != null ? bin.max.toFixed(2) : ''} \nCount: ${bin.count}`"
                            ></div>
                        </div>
                        <div class="flex justify-between text-[10px] text-gray-400 mt-1">
                            <span>{{ col.histogram_bins[0].min != null ? col.histogram_bins[0].min.toFixed(1) : '' }}</span>
                            <span>{{ col.histogram_bins[col.histogram_bins.length - 1].max != null ? col.histogram_bins[col.histogram_bins.length - 1].max.toFixed(1) : '' }}</span>
                        </div>
                    </div>
                </div>

                <!-- Categorical Top Values -->
                <div v-else-if="col.top_values && col.top_values.length > 0" class="mt-3">
                    <div class="text-xs text-gray-500 mb-2">Top Values</div>
                    <div class="space-y-1">
                        <div v-for="(item, idx) in col.top_values" :key="idx" class="flex items-center text-xs">
                            <div class="w-1/3 truncate pr-2" :title="item.value">{{ item.value === '' ? '(empty)' : item.value }}</div>
                            <div class="w-2/3 flex items-center">
                                <div class="bg-blue-200 h-4 rounded-sm" :style="{ width: `${Math.max(1, item.percentage || 0)}%` }"></div>
                                <span class="ml-2 text-gray-500">{{ item.count }} ({{ item.percentage != null ? item.percentage.toFixed(1) : 0 }}%)</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Correlation Matrix (Strong pairs) -->
        <div v-if="profile.correlation_matrix" class="mt-6">
            <h4 class="text-md font-medium text-gray-700 mb-3 border-b pb-2">Strong Correlations (|r| > 0.7)</h4>
            <div class="bg-white rounded shadow-sm border border-gray-100 p-4">
                <ul v-if="getCorrelatedPairs(profile.correlation_matrix).length > 0" class="divide-y divide-gray-100">
                    <li v-for="(pair, idx) in getCorrelatedPairs(profile.correlation_matrix)" :key="idx" class="py-2 flex justify-between items-center">
                        <span class="text-sm font-medium text-gray-700">{{ pair.col1 }} <span class="text-gray-400 mx-2">&harr;</span> {{ pair.col2 }}</span>
                        <span class="text-sm font-bold" :class="pair.val > 0 ? 'text-green-600' : 'text-red-600'">{{ pair.val.toFixed(3) }}</span>
                    </li>
                </ul>
                <div v-else class="text-sm text-gray-500">No strong correlations found.</div>
            </div>
        </div>

        <!-- Sample Rows -->
        <div v-if="profile.sample_rows && profile.sample_rows.length > 0" class="mt-6">
            <h4 class="text-md font-medium text-gray-700 mb-3 border-b pb-2">Sample Rows</h4>
            <div class="overflow-x-auto bg-white rounded shadow-sm border border-gray-100">
                <table class="min-w-full text-xs divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th v-for="(val, colName) in profile.sample_rows[0]" :key="colName" class="px-4 py-2 text-left font-medium text-gray-500 whitespace-nowrap">
                                {{ colName }}
                            </th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        <tr v-for="(row, idx) in profile.sample_rows" :key="idx" class="hover:bg-gray-50">
                            <td v-for="(val, colName) in row" :key="colName" class="px-4 py-2 text-gray-900 whitespace-nowrap">
                                {{ val !== null ? val : '(null)' }}
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</template>
