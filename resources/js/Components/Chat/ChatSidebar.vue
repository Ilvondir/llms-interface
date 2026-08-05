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
    modelOptions: {
        type: Array,
        default: () => [],
    },
    modelsLoading: {
        type: Boolean,
        default: false,
    },
    modelsError: {
        type: String,
        default: null,
    },
    temperature: {
        type: [Number, String],
        default: 0.7,
    },
    maxTokens: {
        default: null,
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
        type: [String, Number],
        default: null,
    },
    canCreateConversation: {
        type: Boolean,
        default: true,
    },
});

defineEmits([
    'update:apiBaseUrl',
    'update:model',
    'update:temperature',
    'update:maxTokens',
    'update:topP',
    'update:systemPrompt',
    'commit-api-base-url',
    'select-conversation',
    'create-conversation',
    'rename-conversation',
    'delete-conversation',
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
                    placeholder="http://localhost:1234"
                    @input="$emit('update:apiBaseUrl', $event.target.value)"
                    @keydown.enter.prevent="$emit('commit-api-base-url', $event.target.value)"
                    @blur="$emit('commit-api-base-url', $event.target.value)"
                >
            </label>

            <label class="block space-y-0.5">
                <span class="font-medium text-gray-500 dark:text-gray-400">Model</span>
                <select
                    v-if="modelOptions.length > 0"
                    class="w-full rounded border-gray-300 dark:border-gray-700 dark:bg-gray-950 shadow-sm text-xs py-1.5"
                    :value="model"
                    @change="$emit('update:model', $event.target.value)"
                >
                    <option value="" disabled>
                        Select model
                    </option>
                    <option
                        v-if="model && ! modelOptions.includes(model)"
                        :value="model"
                    >
                        {{ model }} (current)
                    </option>
                    <option
                        v-for="option in modelOptions"
                        :key="option"
                        :value="option"
                    >
                        {{ option }}
                    </option>
                </select>
                <input
                    v-else
                    type="text"
                    class="w-full rounded border-gray-300 dark:border-gray-700 dark:bg-gray-950 shadow-sm text-xs py-1.5"
                    :value="model"
                    placeholder="Model id"
                    :disabled="modelsLoading"
                    @input="$emit('update:model', $event.target.value)"
                >
                <span
                    v-if="modelsLoading"
                    class="text-[10px] text-gray-500 dark:text-gray-400"
                >
                    Loading models…
                </span>
                <span
                    v-else-if="modelsError"
                    class="text-[10px] text-red-600 dark:text-red-400"
                >
                    {{ modelsError }} — type model id manually
                </span>
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
                <label class="block space-y-0.5 min-w-0 col-span-1">
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
                <div class="block space-y-0.5 min-w-0">
                    <span class="text-gray-500 dark:text-gray-400 truncate block">Max tok</span>
                    <button
                        v-if="maxTokens === null || maxTokens === ''"
                        type="button"
                        class="w-full rounded border border-gray-300 dark:border-gray-700 dark:bg-gray-950 text-xs py-1 text-left px-2 text-gray-700 dark:text-gray-200"
                        title="Click to set a limit"
                        @click="$emit('update:maxTokens', 2048)"
                    >
                        Unlimited
                    </button>
                    <input
                        v-else
                        type="number"
                        min="1"
                        class="w-full rounded border-gray-300 dark:border-gray-700 dark:bg-gray-950 shadow-sm text-xs py-1"
                        :value="maxTokens"
                        @input="$emit('update:maxTokens', $event.target.value === '' ? null : $event.target.value)"
                    >
                </div>
            </div>
            <button
                v-if="maxTokens !== null && maxTokens !== ''"
                type="button"
                class="text-[10px] text-gray-500 dark:text-gray-400 hover:text-gray-800 dark:hover:text-gray-200 underline-offset-2 hover:underline"
                @click="$emit('update:maxTokens', null)"
            >
                Use unlimited max tokens
            </button>

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
                :can-create="canCreateConversation"
                @select="$emit('select-conversation', $event)"
                @create="$emit('create-conversation')"
                @rename="$emit('rename-conversation', $event)"
                @delete="$emit('delete-conversation', $event)"
            />
        </div>
    </div>
</template>
