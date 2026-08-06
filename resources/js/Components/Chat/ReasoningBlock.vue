<script setup>
import { computed, ref, watch } from 'vue';
import MarkdownContent from '@/Components/Chat/MarkdownContent.vue';
import { formatMcpArguments } from '@/utils/streamEvents';

const props = defineProps({
    reasoning: {
        type: String,
        default: '',
    },
    thinking: {
        type: Boolean,
        default: false,
    },
    /**
     * Chronological thinking timeline: { type: 'text', text } | { type: 'mcp', ...frame }
     */
    thinkingTrace: {
        type: Array,
        default: () => [],
    },
    mcpCalls: {
        type: Array,
        default: () => [],
    },
});

const isOpen = ref(false);
const expandedMcpIds = ref({});

const hasTrace = computed(() => Array.isArray(props.thinkingTrace) && props.thinkingTrace.length > 0);
const hasReasoning = computed(() => typeof props.reasoning === 'string' && props.reasoning.trim() !== '');
const hasMcpCalls = computed(() => Array.isArray(props.mcpCalls) && props.mcpCalls.length > 0);
const visible = computed(() => (
    props.thinking || hasTrace.value || hasReasoning.value || hasMcpCalls.value
));

const onToggle = (event) => {
    isOpen.value = event.target.open;
};

const toggleMcpMore = (id) => {
    expandedMcpIds.value = {
        ...expandedMcpIds.value,
        [id]: ! expandedMcpIds.value[id],
    };
};

const mcpStatusLabel = (status) => {
    if (status === 'calling') {
        return 'Running';
    }

    if (status === 'done') {
        return 'Done';
    }

    if (status === 'error') {
        return 'Error';
    }

    return status || 'MCP';
};

watch(
    () => props.thinking,
    (thinking) => {
        if (thinking) {
            isOpen.value = true;
        }
    },
);
</script>

<template>
    <details
        v-if="visible"
        class="mb-3 rounded-md border border-gray-200/80 dark:border-gray-700/80 bg-gray-50/80 dark:bg-gray-950/40"
        :open="isOpen"
        @toggle="onToggle"
    >
        <summary
            class="flex cursor-pointer list-none items-center gap-2 px-3 py-2 text-xs font-medium text-gray-600 dark:text-gray-400 select-none [&::-webkit-details-marker]:hidden"
        >
            <svg
                class="h-3.5 w-3.5 shrink-0 transition-transform"
                :class="isOpen ? 'rotate-90' : ''"
                viewBox="0 0 20 20"
                fill="currentColor"
                aria-hidden="true"
            >
                <path
                    fill-rule="evenodd"
                    d="M7.21 14.77a.75.75 0 01.02-1.06L11.168 10 7.23 6.29a.75.75 0 111.04-1.08l4.5 4.25a.75.75 0 010 1.08l-4.5 4.25a.75.75 0 01-1.06-.02z"
                    clip-rule="evenodd"
                />
            </svg>
            <span class="inline-flex items-center">
                <span
                    v-if="thinking"
                    class="thinking-dots"
                    aria-hidden="true"
                >
                    <span /><span /><span />
                </span>
                Thinking
            </span>
        </summary>

        <div
            v-if="hasTrace"
            class="space-y-2 border-t border-gray-200/80 dark:border-gray-700/80 px-3 py-2.5"
        >
            <template
                v-for="(part, index) in thinkingTrace"
                :key="part.type === 'mcp' ? part.id : `text-${index}`"
            >
                <div
                    v-if="part.type === 'text' && part.text"
                    class="text-sm"
                >
                    <MarkdownContent
                        :content="part.text"
                        compact
                    />
                </div>
                <div
                    v-else-if="part.type === 'mcp'"
                    class="rounded border border-gray-300/80 dark:border-gray-600 bg-white/70 dark:bg-gray-900/60 px-2.5 py-2"
                >
                    <div class="flex items-center gap-2">
                        <span
                            v-if="part.status === 'calling'"
                            class="thinking-dots shrink-0 text-gray-500 dark:text-gray-400"
                            aria-hidden="true"
                        >
                            <span /><span /><span />
                        </span>
                        <div class="min-w-0 flex-1">
                            <div class="text-[10px] font-semibold uppercase tracking-wide text-gray-600 dark:text-gray-300">
                                MCP · {{ part.serverName || part.serverId || 'server' }}
                                <span class="ml-1 font-normal normal-case tracking-normal opacity-70">
                                    · {{ mcpStatusLabel(part.status) }}
                                </span>
                            </div>
                            <div class="mt-0.5 truncate text-xs text-gray-700 dark:text-gray-200">
                                <code class="text-[11px]">{{ part.tool }}</code>
                            </div>
                        </div>
                        <button
                            type="button"
                            class="shrink-0 rounded px-1.5 py-0.5 text-[11px] font-medium text-gray-600 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-gray-800"
                            @click.stop="toggleMcpMore(part.id)"
                        >
                            {{ expandedMcpIds[part.id] ? 'Less' : 'More' }}
                        </button>
                    </div>
                    <div
                        v-if="expandedMcpIds[part.id]"
                        class="mt-2 space-y-2 border-t border-gray-200/70 dark:border-gray-700/70 pt-2"
                    >
                        <div>
                            <div class="mb-1 text-[10px] font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                                Request
                            </div>
                            <pre class="max-h-48 overflow-auto rounded bg-gray-100 dark:bg-black/40 px-2 py-1.5 text-[11px] leading-snug text-gray-800 dark:text-gray-200 whitespace-pre-wrap break-words">{{ formatMcpArguments(part.arguments) }}</pre>
                        </div>
                        <div v-if="part.result != null && part.result !== ''">
                            <div class="mb-1 text-[10px] font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                                Result
                            </div>
                            <pre class="max-h-64 overflow-auto rounded bg-gray-100 dark:bg-black/40 px-2 py-1.5 text-[11px] leading-snug text-gray-800 dark:text-gray-200 whitespace-pre-wrap break-words">{{ formatMcpArguments(part.result) }}</pre>
                        </div>
                        <p
                            v-else-if="part.status === 'calling'"
                            class="text-[11px] text-gray-500 dark:text-gray-400"
                        >
                            Waiting for tool result…
                        </p>
                        <p
                            v-else-if="part.status === 'error' && part.detail"
                            class="text-[11px] text-red-600 dark:text-red-400"
                        >
                            {{ part.detail }}
                        </p>
                    </div>
                </div>
            </template>
        </div>

        <template v-else>
            <div
                v-if="hasReasoning"
                class="border-t border-gray-200/80 dark:border-gray-700/80 px-3 py-2.5"
            >
                <MarkdownContent
                    :content="reasoning"
                    compact
                />
            </div>
            <p
                v-else-if="thinking"
                class="border-t border-gray-200/80 dark:border-gray-700/80 px-3 py-2.5 text-xs text-gray-500 dark:text-gray-500"
            >
                Waiting for reasoning…
            </p>

            <div
                v-if="hasMcpCalls"
                class="space-y-2 border-t border-gray-200/80 dark:border-gray-700/80 px-3 py-2.5"
            >
                <div
                    v-for="call in mcpCalls"
                    :key="call.id"
                    class="rounded border border-gray-300/80 dark:border-gray-600 bg-white/70 dark:bg-gray-900/60 px-2.5 py-2"
                >
                    <div class="flex items-center gap-2">
                        <span
                            v-if="call.status === 'calling'"
                            class="thinking-dots shrink-0 text-gray-500 dark:text-gray-400"
                            aria-hidden="true"
                        >
                            <span /><span /><span />
                        </span>
                        <div class="min-w-0 flex-1">
                            <div class="text-[10px] font-semibold uppercase tracking-wide text-gray-600 dark:text-gray-300">
                                MCP · {{ call.serverName || call.serverId || 'server' }}
                                <span class="ml-1 font-normal normal-case tracking-normal opacity-70">
                                    · {{ mcpStatusLabel(call.status) }}
                                </span>
                            </div>
                            <div class="mt-0.5 truncate text-xs text-gray-700 dark:text-gray-200">
                                <code class="text-[11px]">{{ call.tool }}</code>
                            </div>
                        </div>
                        <button
                            type="button"
                            class="shrink-0 rounded px-1.5 py-0.5 text-[11px] font-medium text-gray-600 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-gray-800"
                            @click.stop="toggleMcpMore(call.id)"
                        >
                            {{ expandedMcpIds[call.id] ? 'Less' : 'More' }}
                        </button>
                    </div>
                    <div
                        v-if="expandedMcpIds[call.id]"
                        class="mt-2 space-y-2 border-t border-gray-200/70 dark:border-gray-700/70 pt-2"
                    >
                        <div>
                            <div class="mb-1 text-[10px] font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                                Request
                            </div>
                            <pre class="max-h-48 overflow-auto rounded bg-gray-100 dark:bg-black/40 px-2 py-1.5 text-[11px] leading-snug text-gray-800 dark:text-gray-200 whitespace-pre-wrap break-words">{{ formatMcpArguments(call.arguments) }}</pre>
                        </div>
                        <div v-if="call.result != null && call.result !== ''">
                            <div class="mb-1 text-[10px] font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                                Result
                            </div>
                            <pre class="max-h-64 overflow-auto rounded bg-gray-100 dark:bg-black/40 px-2 py-1.5 text-[11px] leading-snug text-gray-800 dark:text-gray-200 whitespace-pre-wrap break-words">{{ formatMcpArguments(call.result) }}</pre>
                        </div>
                    </div>
                </div>
            </div>
        </template>
    </details>
</template>
