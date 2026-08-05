<script setup>
import AppShellLayout from '@/Layouts/AppShellLayout.vue';

defineProps({
    title: {
        type: String,
        default: 'Chat',
    },
});
</script>

<template>
    <AppShellLayout :title="title" full-height>
        <div class="flex-1 min-h-0 flex">
            <aside class="chat-scroll w-72 shrink-0 border-r border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900 overflow-y-auto">
                <slot name="sidebar" />
            </aside>

            <main class="flex-1 min-w-0 flex flex-col min-h-0">
                <slot />
            </main>
        </div>
    </AppShellLayout>
</template>
