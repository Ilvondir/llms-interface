<script setup>
import { ref, watch } from 'vue';
import ConversationList from '@/Components/Chat/ConversationList.vue';
import McpServersPanel from '@/Components/Chat/McpServersPanel.vue';
import { Link } from '@inertiajs/vue3';

const SIDEBAR_SECTIONS_STORAGE_KEY = 'llms.sidebar.sections.v1';

const defaultSidebarSections = () => ({
    parametersOpen: false,
    mcpOpen: false,
    chatsOpen: true,
});

const readSidebarSections = () => {
    const defaults = defaultSidebarSections();

    if (typeof window === 'undefined' || ! window.localStorage) {
        return defaults;
    }

    try {
        const raw = window.localStorage.getItem(SIDEBAR_SECTIONS_STORAGE_KEY);

        if (! raw) {
            return defaults;
        }

        const parsed = JSON.parse(raw);

        return {
            parametersOpen: typeof parsed?.parametersOpen === 'boolean'
                ? parsed.parametersOpen
                : defaults.parametersOpen,
            mcpOpen: typeof parsed?.mcpOpen === 'boolean'
                ? parsed.mcpOpen
                : defaults.mcpOpen,
            chatsOpen: typeof parsed?.chatsOpen === 'boolean'
                ? parsed.chatsOpen
                : defaults.chatsOpen,
        };
    } catch {
        return defaults;
    }
};

const writeSidebarSections = (sections) => {
    if (typeof window === 'undefined' || ! window.localStorage) {
        return;
    }

    window.localStorage.setItem(SIDEBAR_SECTIONS_STORAGE_KEY, JSON.stringify(sections));
};

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
    mcpServers: {
        type: Array,
        default: () => [],
    },
    enabledMcpServerIds: {
        type: Array,
        default: () => [],
    },
    mcpTokens: {
        type: Object,
        default: () => ({}),
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
    isGuest: {
        type: Boolean,
        default: false,
    },
});

defineEmits([
    'update:apiBaseUrl',
    'update:model',
    'update:temperature',
    'update:maxTokens',
    'update:topP',
    'update:systemPrompt',
    'update:mcpServers',
    'update:enabledMcpServerIds',
    'update:mcpToken',
    'commit-api-base-url',
    'select-conversation',
    'create-conversation',
    'rename-conversation',
    'delete-conversation',
]);

const storedSections = readSidebarSections();
const parametersOpen = ref(storedSections.parametersOpen);
const mcpOpen = ref(storedSections.mcpOpen);
const chatsOpen = ref(storedSections.chatsOpen);

watch(
    [parametersOpen, mcpOpen, chatsOpen],
    ([parameters, mcp, chats]) => {
        writeSidebarSections({
            parametersOpen: parameters,
            mcpOpen: mcp,
            chatsOpen: chats,
        });
    },
);
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

        <details
            class="border-t border-gray-200 dark:border-gray-800 pt-2.5"
            :open="parametersOpen"
            @toggle="parametersOpen = $event.target.open"
        >
            <summary class="cursor-pointer list-none font-semibold uppercase tracking-wide text-[10px] text-gray-500 dark:text-gray-400 select-none [&::-webkit-details-marker]:hidden flex items-center gap-1.5">
                <svg
                    class="h-3 w-3 shrink-0 transition-transform"
                    :class="parametersOpen ? 'rotate-90' : ''"
                    viewBox="0 0 20 20"
                    fill="currentColor"
                    aria-hidden="true"
                >
                    <path fill-rule="evenodd" d="M7.21 14.77a.75.75 0 01.02-1.06L11.168 10 7.23 6.29a.75.75 0 111.04-1.08l4.5 4.25a.75.75 0 010 1.08l-4.5 4.25a.75.75 0 01-1.06-.02z" clip-rule="evenodd" />
                </svg>
                Parameters
            </summary>

            <div class="mt-2 space-y-2">
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
        </details>

        <details
            class="border-t border-gray-200 dark:border-gray-800 pt-2.5"
            :open="mcpOpen"
            @toggle="mcpOpen = $event.target.open"
        >
            <summary class="cursor-pointer list-none font-semibold uppercase tracking-wide text-[10px] text-gray-500 dark:text-gray-400 select-none [&::-webkit-details-marker]:hidden flex items-center gap-1.5">
                <svg
                    class="h-3 w-3 shrink-0 transition-transform"
                    :class="mcpOpen ? 'rotate-90' : ''"
                    viewBox="0 0 20 20"
                    fill="currentColor"
                    aria-hidden="true"
                >
                    <path fill-rule="evenodd" d="M7.21 14.77a.75.75 0 01.02-1.06L11.168 10 7.23 6.29a.75.75 0 111.04-1.08l4.5 4.25a.75.75 0 010 1.08l-4.5 4.25a.75.75 0 01-1.06-.02z" clip-rule="evenodd" />
                </svg>
                MCP servers
                <span
                    v-if="(mcpServers?.length ?? 0) > 0"
                    class="normal-case tracking-normal font-normal text-gray-400"
                >
                    ({{ mcpServers.length }})
                </span>
            </summary>

            <div class="mt-2">
                <McpServersPanel
                    :mcp-servers="mcpServers"
                    :enabled-mcp-server-ids="enabledMcpServerIds"
                    :mcp-tokens="mcpTokens"
                    :is-guest="isGuest"
                    embedded
                    @update:mcp-servers="$emit('update:mcpServers', $event)"
                    @update:enabled-mcp-server-ids="$emit('update:enabledMcpServerIds', $event)"
                    @update:mcp-token="$emit('update:mcpToken', $event)"
                />
            </div>
        </details>

        <details
            class="border-t border-gray-200 dark:border-gray-800 pt-2.5"
            :open="chatsOpen"
            @toggle="chatsOpen = $event.target.open"
        >
            <summary class="cursor-pointer list-none font-semibold uppercase tracking-wide text-[10px] text-gray-500 dark:text-gray-400 select-none [&::-webkit-details-marker]:hidden flex items-center gap-1.5 mb-1.5">
                <svg
                    class="h-3 w-3 shrink-0 transition-transform"
                    :class="chatsOpen ? 'rotate-90' : ''"
                    viewBox="0 0 20 20"
                    fill="currentColor"
                    aria-hidden="true"
                >
                    <path fill-rule="evenodd" d="M7.21 14.77a.75.75 0 01.02-1.06L11.168 10 7.23 6.29a.75.75 0 111.04-1.08l4.5 4.25a.75.75 0 010 1.08l-4.5 4.25a.75.75 0 01-1.06-.02z" clip-rule="evenodd" />
                </svg>
                Chats
            </summary>

            <div
                v-if="isGuest"
                class="mb-2.5 rounded-md border border-amber-200/80 dark:border-amber-900/60 bg-amber-50/80 dark:bg-amber-950/40 px-2.5 py-2 text-[11px] leading-snug text-amber-950 dark:text-amber-100/90"
            >
                <p>
                    Guest chats stay in this browser only and expire after about a day.
                    They are not synced across devices.
                </p>
                <p class="mt-1.5">
                    <Link
                        :href="route('login')"
                        class="font-medium underline underline-offset-2 hover:text-amber-800 dark:hover:text-amber-50"
                    >
                        Log in
                    </Link>
                    or
                    <Link
                        :href="route('register')"
                        class="font-medium underline underline-offset-2 hover:text-amber-800 dark:hover:text-amber-50"
                    >
                        create an account
                    </Link>
                    to keep conversations on your account.
                </p>
            </div>

            <ConversationList
                :conversations="conversations"
                :active-id="activeConversationId"
                :can-create="canCreateConversation"
                @select="$emit('select-conversation', $event)"
                @create="$emit('create-conversation')"
                @rename="$emit('rename-conversation', $event)"
                @delete="$emit('delete-conversation', $event)"
            />
        </details>
    </div>
</template>
