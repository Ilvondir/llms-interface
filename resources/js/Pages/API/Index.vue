<script setup>
import ApiTokenManager from '@/Pages/API/Partials/ApiTokenManager.vue';
import AppShellLayout from '@/Layouts/AppShellLayout.vue';

defineProps({
    tokens: Array,
    availablePermissions: Array,
    defaultPermissions: Array,
});
</script>

<template>
    <AppShellLayout title="API Tokens">
        <div class="flex-1 overflow-y-auto">
            <div class="max-w-4xl mx-auto py-8 px-4 sm:px-6">
                <h1 class="text-lg font-semibold text-gray-900 dark:text-gray-100 mb-8">
                    API Tokens
                </h1>

                <ApiTokenManager
                    :tokens="tokens"
                    :available-permissions="availablePermissions"
                    :default-permissions="defaultPermissions"
                />
            </div>
        </div>
    </AppShellLayout>
</template>
