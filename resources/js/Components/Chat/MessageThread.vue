<script setup>
import { nextTick, ref, watch } from 'vue';
import AssistantMessageMenu from '@/Components/Chat/AssistantMessageMenu.vue';
import MarkdownContent from '@/Components/Chat/MarkdownContent.vue';
import ReasoningBlock from '@/Components/Chat/ReasoningBlock.vue';
import ResponseStats from '@/Components/Chat/ResponseStats.vue';
import { contentPlainText } from '@/utils/contentParts';

const props = defineProps({
    messages: {
        type: Array,
        default: () => [],
    },
    thinkingMessageId: {
        type: String,
        default: null,
    },
    conversationId: {
        type: [Number, String],
        default: null,
    },
});

const threadEl = ref(null);
const bottomEl = ref(null);
let scrollRaf = null;
let forceScrollToEnd = false;

const IMAGE_DATA_URL_PATTERN = /^data:image\/(jpeg|png|gif|webp);base64,/i;

const imageUrls = (content) => {
    if (! Array.isArray(content)) {
        return [];
    }

    return content
        .filter((part) => (
            part?.type === 'image_url'
            && typeof part.image_url?.url === 'string'
            && IMAGE_DATA_URL_PATTERN.test(part.image_url.url)
        ))
        .map((part) => part.image_url.url);
};

const textContent = (content) => {
    if (typeof content === 'string') {
        return content;
    }

    return contentPlainText(content);
};

const isNearBottom = () => {
    const el = threadEl.value;

    if (! el) {
        return true;
    }

    return el.scrollHeight - el.scrollTop - el.clientHeight < 140;
};

const scrollToEnd = async () => {
    await nextTick();

    if (bottomEl.value) {
        bottomEl.value.scrollIntoView({ block: 'end', behavior: 'auto' });

        return;
    }

    if (threadEl.value) {
        threadEl.value.scrollTop = threadEl.value.scrollHeight;
    }
};

const scheduleScrollToEnd = ({ force = false } = {}) => {
    if (! force && ! forceScrollToEnd && ! isNearBottom()) {
        return;
    }

    if (scrollRaf != null) {
        return;
    }

    scrollRaf = requestAnimationFrame(() => {
        scrollRaf = null;
        scrollToEnd();
    });
};

const onImageLoad = () => {
    scheduleScrollToEnd({ force: forceScrollToEnd || isNearBottom() });
};

watch(
    () => props.conversationId,
    () => {
        forceScrollToEnd = true;
        scheduleScrollToEnd({ force: true });
        requestAnimationFrame(() => scheduleScrollToEnd({ force: true }));
        setTimeout(() => scheduleScrollToEnd({ force: true }), 100);
        setTimeout(() => {
            scheduleScrollToEnd({ force: true });
            forceScrollToEnd = false;
        }, 400);
    },
    { immediate: true },
);

watch(
    () => [
        props.messages.length,
        props.messages.at(-1)?.id,
        props.thinkingMessageId,
    ],
    () => {
        scheduleScrollToEnd();
    },
);

// Stream token updates: throttle via rAF, only stick if user is near bottom.
watch(
    () => [props.messages.at(-1)?.content, props.messages.at(-1)?.reasoning],
    () => {
        scheduleScrollToEnd();
    },
);
</script>

<template>
    <div
        ref="threadEl"
        class="chat-scroll flex-1 overflow-y-auto px-4 py-6"
    >
        <div
            v-if="messages.length === 0"
            class="h-full flex items-center justify-center text-sm text-gray-500 dark:text-gray-400"
        >
            Send a message to start the conversation.
        </div>

        <div
            v-else
            class="mx-auto max-w-3xl space-y-4"
        >
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
                    v-else-if="message.role === 'user'"
                    class="space-y-2"
                >
                    <div
                        v-for="(url, index) in imageUrls(message.content)"
                        :key="`${message.id}-img-${index}`"
                    >
                        <img
                            :src="url"
                            alt="User attachment"
                            class="max-h-64 max-w-full rounded-md object-contain"
                            @load="onImageLoad"
                        >
                    </div>
                    <div
                        v-if="textContent(message.content)"
                        class="text-sm whitespace-pre-wrap"
                    >
                        {{ textContent(message.content) }}
                    </div>
                </div>
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
            <div
                ref="bottomEl"
                aria-hidden="true"
            />
        </div>
    </div>
</template>
