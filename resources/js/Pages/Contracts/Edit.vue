<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, useForm } from '@inertiajs/vue3';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import InputError from '@/Components/InputError.vue';

const props = defineProps({
    contract: Object,
    formats: Array,
});

const form = useForm({
    dev_team_name: props.contract?.dev_team_name || '',
    format: props.contract?.format || '',
    schema_definition: props.contract?.schema_definition || '',
});

const submit = () => {
    form.put(route('contracts.update', props.contract?.id));
};
</script>

<template>
    <Head title="Edit Contract" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Edit Contract</h2>
        </template>

        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
                <div class="p-4 sm:p-8 bg-white shadow sm:rounded-lg">
                    <form @submit.prevent="submit" class="space-y-6 max-w-xl">
                        <div>
                            <InputLabel for="dev_team_name" value="Dev Team Name" />
                            <TextInput id="dev_team_name" type="text" class="mt-1 block w-full" v-model="form.dev_team_name" required autofocus />
                            <InputError class="mt-2" :message="form.errors.dev_team_name" />
                        </div>

                        <div>
                            <InputLabel for="format" value="Format" />
                            <select id="format" v-model="form.format" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm" required>
                                <option v-for="f in formats" :key="f.value" :value="f.value">{{ f.label }}</option>
                            </select>
                            <InputError class="mt-2" :message="form.errors.format" />
                        </div>

                        <div>
                            <InputLabel for="schema_definition" value="Schema Definition (JSON)" />
                            <textarea id="schema_definition" v-model="form.schema_definition" rows="6" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm font-mono text-sm" required></textarea>
                            <InputError class="mt-2" :message="form.errors.schema_definition" />
                        </div>

                        <div class="flex items-center gap-4">
                            <PrimaryButton :disabled="form.processing">Update</PrimaryButton>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
