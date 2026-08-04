<script setup>
import ConversationList from '@/Components/Chat/ConversationList.vue';

defineProps({
    apiBaseUrl: {
        type: String,
        default: '',
    },
    model: {
        type: String,
        default: '',
    },
    temperature: {
        type: [Number, String],
        default: 0.7,
    },
    maxTokens: {
        type: [Number, String],
        default: 2048,
    },
    topP: {
        type: [Number, String],
        default: 1,
    },
    systemPrompt: {
        type: String,
        default: '',
    },
    conversations: {
        type: Array,
        default: () => [],
    },
    activeConversationId: {
        type: String,
        default: null,
    },
});

defineEmits([
    'update:apiBaseUrl',
    'update:model',
    'update:temperature',
    'update:maxTokens',
    'update:topP',
    'update:systemPrompt',
    'select-conversation',
    'create-conversation',
    'rename-conversation',
]);
</script>

<template>
    <div class="flex flex-col gap-3 p-3 text-xs">
        <div class="space-y-2">
            <label class="block space-y-0.5">
                <span class="font-medium text-gray-500 dark:text-gray-400">API URL</span>
                <input
                    type="url"
                    class="w-full rounded border-gray-300 dark:border-gray-700 dark:bg-gray-950 shadow-sm text-xs py-1.5"
                    :value="apiBaseUrl"
                    placeholder="http://localhost:1234/v1"
                    @input="$emit('update:apiBaseUrl', $event.target.value)"
                >
            </label>

            <label class="block space-y-0.5">
                <span class="font-medium text-gray-500 dark:text-gray-400">Model</span>
                <input
                    type="text"
                    class="w-full rounded border-gray-300 dark:border-gray-700 dark:bg-gray-950 shadow-sm text-xs py-1.5"
                    :value="model"
                    placeholder="Model id"
                    @input="$emit('update:model', $event.target.value)"
                >
            </label>
        </div>

        <div class="border-t border-gray-200 dark:border-gray-800 pt-2.5 space-y-2">
            <h2 class="font-semibold uppercase tracking-wide text-[10px] text-gray-500 dark:text-gray-400">
                Parameters
            </h2>

            <div class="grid grid-cols-3 gap-1.5">
                <label class="block space-y-0.5 min-w-0">
                    <span class="text-gray-500 dark:text-gray-400 truncate block">Temp</span>
                    <input
                        type="number"
                        step="0.1"
                        min="0"
                        max="2"
                        class="w-full rounded border-gray-300 dark:border-gray-700 dark:bg-gray-950 shadow-sm text-xs py-1"
                        :value="temperature"
                        @input="$emit('update:temperature', $event.target.value)"
                    >
                </label>
                <label class="block space-y-0.5 min-w-0">
                    <span class="text-gray-500 dark:text-gray-400 truncate block">Max tok</span>
                    <input
                        type="number"
                        min="1"
                        class="w-full rounded border-gray-300 dark:border-gray-700 dark:bg-gray-950 shadow-sm text-xs py-1"
                        :value="maxTokens"
                        @input="$emit('update:maxTokens', $event.target.value)"
                    >
                </label>
                <label class="block space-y-0.5 min-w-0">
                    <span class="text-gray-500 dark:text-gray-400 truncate block">Top P</span>
                    <input
                        type="number"
                        step="0.05"
                        min="0"
                        max="1"
                        class="w-full rounded border-gray-300 dark:border-gray-700 dark:bg-gray-950 shadow-sm text-xs py-1"
                        :value="topP"
                        @input="$emit('update:topP', $event.target.value)"
                    >
                </label>
            </div>

            <label class="block space-y-0.5">
                <span class="text-gray-500 dark:text-gray-400">System prompt</span>
                <textarea
                    rows="2"
                    class="w-full rounded border-gray-300 dark:border-gray-700 dark:bg-gray-950 shadow-sm text-xs py-1.5 resize-y"
                    :value="systemPrompt"
                    placeholder="You are a helpful assistant."
                    @input="$emit('update:systemPrompt', $event.target.value)"
                />
            </label>
        </div>

        <div class="border-t border-gray-200 dark:border-gray-800 pt-2.5">
            <h2 class="font-semibold uppercase tracking-wide text-[10px] text-gray-500 dark:text-gray-400 mb-1.5">
                Chats
            </h2>
            <ConversationList
                :conversations="conversations"
                :active-id="activeConversationId"
                @select="$emit('select-conversation', $event)"
                @create="$emit('create-conversation')"
                @rename="$emit('rename-conversation', $event)"
            />
        </div>
    </div>
</template>
