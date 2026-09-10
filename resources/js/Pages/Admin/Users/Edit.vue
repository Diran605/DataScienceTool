<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import { Head, useForm, router } from '@inertiajs/vue3';
import { ref } from 'vue';

const props = defineProps({
    user: Object,
    roles: Array,
    userRoles: Array,
    userContracts: Array,
});

const roleForm = useForm({
    roles: [...props.userRoles],
});

const toggleRole = (roleName) => {
    const index = roleForm.roles.indexOf(roleName);
    if (index > -1) {
        roleForm.roles.splice(index, 1);
    } else {
        roleForm.roles.push(roleName);
    }
};

const submitRoles = () => {
    roleForm.put(route('admin.users.update', props.user.id));
};

const revokeContract = (contractId) => {
    if (confirm('Revoke access to this contract?')) {
        router.delete(route('admin.users.contracts.destroy', [props.user.id, contractId]));
    }
};
</script>

<template>
    <Head :title="`Edit User: ${user.name}`" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800">
                Edit User: {{ user.name }}
            </h2>
        </template>

        <div class="py-12">
            <div class="mx-auto max-w-4xl sm:px-6 lg:px-8 space-y-6">
                <!-- Role Management -->
                <div class="overflow-hidden bg-white shadow-sm sm:rounded-lg">
                    <div class="p-6">
                        <h3 class="text-lg font-medium text-gray-900 mb-4">Roles</h3>
                        <form @submit.prevent="submitRoles">
                            <div class="flex flex-wrap gap-3 mb-4">
                                <label
                                    v-for="role in roles"
                                    :key="role.id"
                                    class="inline-flex items-center gap-2 rounded-md border px-3 py-2 text-sm cursor-pointer"
                                    :class="roleForm.roles.includes(role.name)
                                        ? 'border-indigo-500 bg-indigo-50 text-indigo-700'
                                        : 'border-gray-300 text-gray-700 hover:bg-gray-50'"
                                >
                                    <input
                                        type="checkbox"
                                        :checked="roleForm.roles.includes(role.name)"
                                        @change="toggleRole(role.name)"
                                        class="rounded border-gray-300 text-indigo-600"
                                    />
                                    {{ role.name }}
                                </label>
                            </div>
                            <PrimaryButton :disabled="roleForm.processing">
                                Save Roles
                            </PrimaryButton>
                        </form>
                    </div>
                </div>

                <!-- Contract Access -->
                <div class="overflow-hidden bg-white shadow-sm sm:rounded-lg">
                    <div class="p-6">
                        <h3 class="text-lg font-medium text-gray-900 mb-4">Contract Access</h3>

                        <div v-if="userContracts.length === 0" class="text-sm text-gray-500">
                            No contract access assigned.
                        </div>

                        <div v-else class="space-y-3">
                            <div
                                v-for="contract in userContracts"
                                :key="contract.id"
                                class="flex items-center justify-between rounded-lg border border-gray-200 p-3"
                            >
                                <div>
                                    <span class="font-medium text-gray-900">{{ contract.dev_team_name }}</span>
                                    <div class="flex gap-3 mt-1 text-xs">
                                        <span :class="contract.can_run ? 'text-green-600' : 'text-gray-400'">
                                            {{ contract.can_run ? '✓ Can Run' : '✗ Cannot Run' }}
                                        </span>
                                        <span :class="contract.can_view_deliveries ? 'text-green-600' : 'text-gray-400'">
                                            {{ contract.can_view_deliveries ? '✓ View Deliveries' : '✗ No Delivery View' }}
                                        </span>
                                    </div>
                                </div>
                                <button
                                    @click="revokeContract(contract.id)"
                                    class="text-sm text-red-600 hover:text-red-800"
                                >
                                    Revoke
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
