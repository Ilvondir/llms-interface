<script setup>
import AssistantMessageMenu from '@/Components/Chat/AssistantMessageMenu.vue';
import MarkdownContent from '@/Components/Chat/MarkdownContent.vue';
import ReasoningBlock from '@/Components/Chat/ReasoningBlock.vue';
import ResponseStats from '@/Components/Chat/ResponseStats.vue';

defineProps({
    messages: {
        type: Array,
        default: () => [],
    },
    thinkingMessageId: {
        type: String,
        default: null,
    },
});
</script>

<template>
    <div class="chat-scroll flex-1 overflow-y-auto px-4 py-6">
        <div
            v-if="messages.length === 0"
            class="h-full flex items-center justify-center text-sm text-gray-500 dark:text-gray-400"
        >
            Send a message to start the conversation.
        </div>

        <div v-else class="mx-auto max-w-3xl space-y-4">
            <div
                v-for="message in messages"
                :key="message.id"
                class="rounded-lg px-4 py-3"
                :class="message.role === 'user'
                    ? 'bg-gray-900 text-white dark:bg-gray-100 dark:text-gray-900 ml-8'
                    : 'bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 mr-8'"
            >
                <div class="mb-1 flex items-start justify-between gap-2">
                    <div class="min-w-0">
                        <div class="text-xs font-semibold uppercase tracking-wide opacity-70">
                            {{ message.role }}
                        </div>
                        <div
                            v-if="message.role === 'assistant' && message.model"
                            class="mt-0.5 mb-3 text-[11px] leading-tight text-gray-500 dark:text-gray-400 font-normal normal-case tracking-normal"
                        >
                            {{ message.model }}
                        </div>
                    </div>
                    <AssistantMessageMenu
                        v-if="message.role === 'assistant'"
                        :message="message"
                    />
                </div>
                <ReasoningBlock
                    v-if="message.role === 'assistant'"
                    :reasoning="message.reasoning"
                    :thinking="thinkingMessageId === message.id"
                />
                <MarkdownContent
                    v-if="message.role === 'assistant' && message.content"
                    :content="message.content"
                />
                <div
                    v-else-if="message.content"
                    class="text-sm whitespace-pre-wrap"
                >
                    {{ message.content }}
                </div>
                <p
                    v-if="message.error"
                    class="mt-2 text-xs text-red-600 dark:text-red-400"
                >
                    {{ message.error }}
                </p>
                <ResponseStats
                    v-if="message.role === 'assistant'"
                    :stats="message.stats"
                />
            </div>
        </div>
    </div>
</template>
