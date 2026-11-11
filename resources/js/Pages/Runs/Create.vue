<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import InputError from '@/Components/InputError.vue';
import { Head, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';

const form = useForm({
    file: null,
});

const fileInput = ref(null);
const dragOver = ref(false);

const handleFileSelect = (event) => {
    form.file = event.target.files[0];
};

const handleDrop = (event) => {
    dragOver.value = false;
    const files = event.dataTransfer.files;
    if (files.length > 0) {
        form.file = files[0];
    }
};

const submit = () => {
    form.post(route('runs.store'), {
        forceFormData: true,
    });
};
</script>

<template>
    <Head title="Upload File" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800">
                New Run — Upload File
            </h2>
        </template>

        <div class="py-12">
            <div class="mx-auto max-w-2xl sm:px-6 lg:px-8">
                <div class="overflow-hidden bg-white shadow-sm sm:rounded-lg">
                    <div class="p-6">
                        <form @submit.prevent="submit">
                            <div class="mb-4">
                                <InputLabel for="file" value="Data File" />

                                <div
                                    class="mt-2 flex justify-center rounded-lg border border-dashed px-6 py-10"
                                    :class="dragOver ? 'border-indigo-500 bg-indigo-50' : 'border-gray-900/25'"
                                    @dragover.prevent="dragOver = true"
                                    @dragleave="dragOver = false"
                                    @drop.prevent="handleDrop"
                                >
                                    <div class="text-center">
                                        <svg class="mx-auto h-12 w-12 text-gray-300" viewBox="0 0 24 24" fill="currentColor">
                                            <path fill-rule="evenodd" d="M1.5 6a2.25 2.25 0 012.25-2.25h16.5A2.25 2.25 0 0122.5 6v12a2.25 2.25 0 01-2.25 2.25H3.75A2.25 2.25 0 011.5 18V6zM3 16.06V18c0 .414.336.75.75.75h16.5A.75.75 0 0021 18v-1.94l-2.69-2.689a1.5 1.5 0 00-2.12 0l-.88.879.97.97a.75.75 0 11-1.06 1.06l-5.16-5.159a1.5 1.5 0 00-2.12 0L3 16.061zm10.125-7.81a1.125 1.125 0 112.25 0 1.125 1.125 0 01-2.25 0z" clip-rule="evenodd" />
                                        </svg>
                                        <div class="mt-4 flex text-sm leading-6 text-gray-600">
                                            <label
                                                for="file-upload"
                                                class="relative cursor-pointer rounded-md bg-white font-semibold text-indigo-600 focus-within:outline-none focus-within:ring-2 focus-within:ring-indigo-600 focus-within:ring-offset-2 hover:text-indigo-500"
                                            >
                                                <span>Upload a file</span>
                                                <input
                                                    id="file-upload"
                                                    ref="fileInput"
                                                    type="file"
                                                    class="sr-only"
                                                    accept=".csv,.xlsx,.xls,.parquet,.json,.pdf,.txt,.doc,.docx"
                                                    @change="handleFileSelect"
                                                />
                                            </label>
                                            <p class="pl-1">or drag and drop</p>
                                        </div>
                                        <p class="text-xs leading-5 text-gray-600">
                                            CSV, Excel, Parquet, JSON, PDF, TXT, DOC up to 100MB
                                        </p>
                                    </div>
                                </div>

                                <div v-if="form.file" class="mt-3 flex items-center gap-2 rounded-md bg-gray-50 px-3 py-2 text-sm text-gray-700">
                                    <svg class="h-5 w-5 text-gray-400" viewBox="0 0 20 20" fill="currentColor">
                                        <path fill-rule="evenodd" d="M15.621 4.379a3 3 0 00-4.242 0l-7 7a3 3 0 004.241 4.243h.001l.497-.5a.75.75 0 011.064 1.057l-.498.501-.002.002a4.5 4.5 0 01-6.364-6.364l7-7a4.5 4.5 0 016.368 6.36l-3.455 3.553A2.625 2.625 0 119.52 9.52l3.45-3.451a.75.75 0 111.061 1.06l-3.45 3.451a1.125 1.125 0 001.587 1.595l3.454-3.553a3 3 0 000-4.242z" clip-rule="evenodd" />
                                    </svg>
                                    {{ form.file.name }}
                                    <span class="text-gray-400">({{ (form.file.size / 1024 / 1024).toFixed(2) }} MB)</span>
                                </div>

                                <InputError class="mt-2" :message="form.errors.file" />
                            </div>

                            <div class="flex items-center justify-end">
                                <PrimaryButton
                                    :class="{ 'opacity-25': form.processing }"
                                    :disabled="form.processing || !form.file"
                                >
                                    Upload & Profile
                                </PrimaryButton>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
