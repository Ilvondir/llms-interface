<script setup>
import { computed, onMounted, ref } from 'vue';
import { useToast } from 'vue-toastification';
import ChatLayout from '@/Layouts/ChatLayout.vue';
import ChatSidebar from '@/Components/Chat/ChatSidebar.vue';
import MessageThread from '@/Components/Chat/MessageThread.vue';
import ChatComposer from '@/Components/Chat/ChatComposer.vue';
import { useChatModels } from '@/composables/useChatModels';
import { useChatStream } from '@/composables/useChatStream';
import { useGuestChatStore } from '@/composables/useGuestChatStore';
import { splitThinkTaggedContent } from '@/utils/assistantOutput';
import { buildUpstreamRequest } from '@/utils/buildUpstreamRequest';
import { estimatePromptTokens, estimateTokenCount } from '@/utils/estimateTokens';

const toast = useToast();
const store = useGuestChatStore();
const { modelOptions, modelsLoading, modelsError, fetchModels } = useChatModels();
const { isStreaming, streamChat, cancel } = useChatStream();
const streamingMessageId = ref(null);
const thinkingMessageId = ref(null);

const apiBaseUrl = computed({
    get: () => store.state.settings.apiBaseUrl,
    set: (value) => store.setApiBaseUrl(value),
});

const model = computed({
    get: () => store.activeConversation.value?.model ?? '',
    set: (value) => store.setModel(value),
});

const temperature = computed({
    get: () => store.activeConversation.value?.params?.temperature ?? store.state.settings.defaultParams.temperature,
    set: (value) => store.setTemperature(value),
});

const maxTokens = computed({
    get: () => {
        const conversation = store.activeConversation.value;

        // null means Unlimited — do not fall back with ?? (null is intentional)
        if (conversation) {
            return conversation.params.max_tokens;
        }

        return store.state.settings.defaultParams.max_tokens;
    },
    set: (value) => store.setMaxTokens(value),
});

const topP = computed({
    get: () => store.activeConversation.value?.params?.top_p ?? store.state.settings.defaultParams.top_p,
    set: (value) => store.setTopP(value),
});

const systemPrompt = computed({
    get: () => store.activeConversation.value?.systemPrompt ?? '',
    set: (value) => store.setSystemPrompt(value),
});

const conversations = computed(() => store.state.conversations);
const activeConversationId = computed(() => store.state.activeConversationId);
const messages = computed(() => store.messages.value);
const canCreateConversation = computed(() => (
    ! store.isEmptyConversation(store.activeConversation.value)
));

const historyForModel = () => (
    (store.activeConversation.value?.messages ?? [])
        .filter((message) => message.role === 'user' || message.role === 'assistant')
        .map((message) => {
            const content = message.role === 'assistant'
                ? splitThinkTaggedContent(message.content).content
                : message.content;

            return {
                role: message.role,
                content,
            };
        })
        .filter((message) => typeof message.content === 'string' && message.content.trim() !== '')
);

const commitApiBaseUrl = async (rawUrl) => {
    const trimmed = String(rawUrl ?? '').trim();

    store.setApiBaseUrl(trimmed);

    const ids = await fetchModels(trimmed);

    if (ids.length > 0 && ! model.value) {
        store.setModel(ids[0]);
    }
};

onMounted(() => {
    if (store.state.settings.apiBaseUrl) {
        commitApiBaseUrl(store.state.settings.apiBaseUrl);
    }
});

const sendMessage = async (content) => {
    if (! apiBaseUrl.value?.trim()) {
        toast.error('Set an API URL first.');

        return;
    }

    if (! model.value?.trim()) {
        toast.error('Select or type a model id first.');

        return;
    }

    if (isStreaming.value) {
        return;
    }

    store.appendMessage({
        role: 'user',
        content,
    });

    const outboundMessages = historyForModel();
    const requestModel = model.value.trim();
    const requestPayload = buildUpstreamRequest({
        model: requestModel,
        systemPrompt: systemPrompt.value,
        messages: outboundMessages,
        temperature: Number(temperature.value),
        topP: Number(topP.value),
        maxTokens: maxTokens.value,
    });
    const sentAt = Date.now();

    const assistant = store.appendMessage({
        role: 'assistant',
        content: '',
        model: requestModel,
        sentAt,
        requestPayload,
    });

    const enrichStats = (stats) => {
        const questionTokens = estimateTokenCount(content);
        const promptTokensEstimated = estimatePromptTokens({
            systemPrompt: systemPrompt.value,
            messages: outboundMessages,
        });
        const promptTokens = stats?.inputTokens ?? null;
        const historyTokens = promptTokens != null
            ? Math.max(0, promptTokens - questionTokens)
            : Math.max(0, promptTokensEstimated - questionTokens);

        return {
            ...(stats ?? {}),
            questionTokens,
            promptTokensEstimated,
            historyTokens,
            usageSource: stats?.usageSource ?? (stats?.inputTokens != null ? 'upstream' : 'client'),
        };
    };

    streamingMessageId.value = assistant.id;
    thinkingMessageId.value = null;

    try {
        await streamChat({
            apiBaseUrl: apiBaseUrl.value.trim(),
            model: requestModel,
            systemPrompt: systemPrompt.value,
            messages: outboundMessages,
            temperature: Number(temperature.value),
            maxTokens: maxTokens.value,
            topP: Number(topP.value),
            onToken: (_delta, full) => {
                store.updateMessage(assistant.id, { content: full }, { persist: false });
            },
            onReasoning: (_delta, full) => {
                store.updateMessage(assistant.id, { reasoning: full }, { persist: false });
            },
            onThinking: (active) => {
                thinkingMessageId.value = active ? assistant.id : null;
            },
            onFinish: ({ content: finalContent, reasoning, stats }) => {
                thinkingMessageId.value = null;
                store.updateMessage(assistant.id, {
                    content: finalContent,
                    reasoning: reasoning || null,
                    stats: enrichStats(stats),
                    receivedAt: Date.now(),
                    error: null,
                });
            },
            onError: ({ message, content: partialContent, reasoning }) => {
                thinkingMessageId.value = null;
                store.updateMessage(assistant.id, {
                    content: partialContent,
                    reasoning: reasoning || null,
                    stats: enrichStats(null),
                    receivedAt: Date.now(),
                    error: message,
                });
                toast.error(message);
            },
        });
    } finally {
        streamingMessageId.value = null;
        thinkingMessageId.value = null;
    }
};
</script>

<template>
    <ChatLayout title="Chat">
        <template #sidebar>
            <ChatSidebar
                v-model:api-base-url="apiBaseUrl"
                v-model:model="model"
                v-model:temperature="temperature"
                v-model:max-tokens="maxTokens"
                v-model:top-p="topP"
                v-model:system-prompt="systemPrompt"
                :model-options="modelOptions"
                :models-loading="modelsLoading"
                :models-error="modelsError"
                :conversations="conversations"
                :active-conversation-id="activeConversationId"
                :can-create-conversation="canCreateConversation"
                @commit-api-base-url="commitApiBaseUrl"
                @select-conversation="store.selectConversation"
                @create-conversation="store.createConversation"
                @rename-conversation="store.renameConversation"
                @delete-conversation="store.deleteConversation"
            />
        </template>

        <MessageThread
            :messages="messages"
            :thinking-message-id="thinkingMessageId"
        />
        <ChatComposer
            :disabled="isStreaming"
            :streaming="isStreaming"
            @send="sendMessage"
            @stop="cancel"
        />
    </ChatLayout>
</template>
