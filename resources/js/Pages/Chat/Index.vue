<script setup>
import { computed, onMounted, ref } from 'vue';
import { usePage } from '@inertiajs/vue3';
import { useToast } from 'vue-toastification';
import ChatLayout from '@/Layouts/ChatLayout.vue';
import ChatSidebar from '@/Components/Chat/ChatSidebar.vue';
import MessageThread from '@/Components/Chat/MessageThread.vue';
import ChatComposer from '@/Components/Chat/ChatComposer.vue';
import { useAccountChatStore } from '@/composables/useAccountChatStore';
import { useChatModels } from '@/composables/useChatModels';
import { useChatStream } from '@/composables/useChatStream';
import { GUEST_DRAFT_ID, useGuestChatStore } from '@/composables/useGuestChatStore';
import { splitThinkTaggedContent } from '@/utils/assistantOutput';
import { buildUpstreamRequest } from '@/utils/buildUpstreamRequest';
import { contentPlainText, isContentEmpty } from '@/utils/contentParts';
import { estimatePromptTokens, estimateTokenCount } from '@/utils/estimateTokens';
import { buildUserMessageContent } from '@/utils/imageAttach';

const props = defineProps({
    chatSettings: {
        type: Object,
        default: null,
    },
    conversations: {
        type: Array,
        default: null,
    },
    activeConversation: {
        type: Object,
        default: null,
    },
});

const chatLayout = ref(null);
const toast = useToast();
const page = usePage();
const isAuthenticated = computed(() => !! page.props.auth?.user);

// Auth sessions must not touch guest localStorage on first paint. After Inertia logout
// this same page instance is reused — lazily create the guest store then.
let guestStore = isAuthenticated.value ? null : useGuestChatStore();
const accountStore = isAuthenticated.value
    ? useAccountChatStore({
        chatSettings: props.chatSettings,
        conversations: props.conversations,
        activeConversation: props.activeConversation,
    })
    : null;

const resolveStore = () => {
    if (isAuthenticated.value) {
        return accountStore;
    }

    if (! guestStore) {
        guestStore = useGuestChatStore();
    }

    return guestStore;
};

const store = computed(() => resolveStore());

const closeMobileSidebar = () => {
    chatLayout.value?.closeSidebar();
};

const selectConversation = (id) => {
    store.value.selectConversation(id);
    closeMobileSidebar();
};

const createConversation = () => {
    store.value.createConversation();
    closeMobileSidebar();
};

const { modelOptions, modelsLoading, modelsError, fetchModels } = useChatModels();
const { isStreaming, streamChat, cancel } = useChatStream();
const streamingMessageId = ref(null);
const thinkingMessageId = ref(null);

const apiBaseUrl = computed({
    get: () => store.value.state.settings.apiBaseUrl,
    set: (value) => store.value.setApiBaseUrl(value),
});

const model = computed({
    get: () => store.value.activeConversation.value?.model ?? '',
    set: (value) => store.value.setModel(value),
});

const temperature = computed({
    get: () => store.value.activeConversation.value?.params?.temperature
        ?? store.value.state.settings.defaultParams.temperature,
    set: (value) => store.value.setTemperature(value),
});

const maxTokens = computed({
    get: () => {
        const conversation = store.value.activeConversation.value;

        // null means Unlimited — do not fall back with ?? (null is intentional)
        if (conversation) {
            return conversation.params.max_tokens;
        }

        return store.value.state.settings.defaultParams.max_tokens;
    },
    set: (value) => store.value.setMaxTokens(value),
});

const topP = computed({
    get: () => store.value.activeConversation.value?.params?.top_p
        ?? store.value.state.settings.defaultParams.top_p,
    set: (value) => store.value.setTopP(value),
});

const systemPrompt = computed({
    get: () => store.value.activeConversation.value?.systemPrompt ?? '',
    set: (value) => store.value.setSystemPrompt(value),
});

const conversations = computed(() => store.value.state.conversations);
const activeConversationId = computed(() => {
    if (isAuthenticated.value) {
        return store.value.activeConversation.value?.id
            ?? store.value.state.settings.activeConversationId;
    }

    const id = store.value.state.activeConversationId;

    return id === GUEST_DRAFT_ID ? null : id;
});
const messages = computed(() => store.value.messages.value);
const canCreateConversation = computed(() => (
    ! store.value.isEmptyConversation(store.value.activeConversation.value)
));

const historyForModel = () => (
    (store.value.activeConversation.value?.messages ?? [])
        .filter((message) => message.role === 'user' || message.role === 'assistant')
        .map((message) => {
            let content = message.content;

            if (message.role === 'assistant' && typeof content === 'string') {
                content = splitThinkTaggedContent(content).content;
            }

            return {
                role: message.role,
                content,
            };
        })
        .filter((message) => ! isContentEmpty(message.content))
);

const commitApiBaseUrl = async (rawUrl, { persist = true } = {}) => {
    const trimmed = String(rawUrl ?? '').trim();

    store.value.setApiBaseUrl(trimmed);

    if (persist && typeof store.value.persistApiBaseUrl === 'function') {
        await store.value.persistApiBaseUrl();
    }

    const ids = await fetchModels(trimmed);

    if (ids.length > 0 && ! model.value) {
        await store.value.setModel(ids[0]);
    }
};

onMounted(() => {
    const url = store.value.state.settings.apiBaseUrl?.trim();

    // Fetch models only — never persist here (persist + redirect remounts Index → infinite loop).
    if (url) {
        commitApiBaseUrl(url, { persist: false });
    }
});

const sendMessage = async (payload) => {
    const text = typeof payload === 'string' ? payload : (payload?.text ?? '');
    const imageDataUrl = typeof payload === 'string' ? null : (payload?.imageDataUrl ?? null);

    if (! isAuthenticated.value && imageDataUrl) {
        toast.info('Sign in to send images.');

        return;
    }

    const content = buildUserMessageContent({ text, imageDataUrl });

    if (content == null) {
        return;
    }

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

    await store.value.appendMessage({
        role: 'user',
        content,
    });

    const outboundMessages = historyForModel();
    const requestModel = model.value.trim();
    const requestParams = {
        temperature: Number(temperature.value),
        top_p: Number(topP.value),
        max_tokens: maxTokens.value,
    };
    const requestPayload = buildUpstreamRequest({
        model: requestModel,
        systemPrompt: systemPrompt.value,
        messages: outboundMessages,
        temperature: requestParams.temperature,
        topP: requestParams.top_p,
        maxTokens: requestParams.max_tokens,
    });
    const sentAt = Date.now();

    const assistant = await store.value.appendMessage({
        role: 'assistant',
        content: '',
        model: requestModel,
        sentAt,
        requestPayload,
        params: requestParams,
    });

    const enrichStats = (stats) => {
        const questionTokens = estimateTokenCount(contentPlainText(content));
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
            temperature: requestParams.temperature,
            maxTokens: requestParams.max_tokens,
            topP: requestParams.top_p,
            onToken: (_delta, full) => {
                store.value.updateMessage(assistant.id, { content: full }, { persist: false });
            },
            onReasoning: (_delta, full) => {
                store.value.updateMessage(assistant.id, { reasoning: full }, { persist: false });
            },
            onThinking: (active) => {
                thinkingMessageId.value = active ? assistant.id : null;
            },
            onFinish: async ({ content: finalContent, reasoning, stats }) => {
                thinkingMessageId.value = null;
                await store.value.updateMessage(assistant.id, {
                    content: finalContent,
                    reasoning: reasoning || null,
                    stats: enrichStats(stats),
                    receivedAt: Date.now(),
                    error: null,
                });
            },
            onError: async ({ message, content: partialContent, reasoning }) => {
                thinkingMessageId.value = null;
                await store.value.updateMessage(assistant.id, {
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
    <ChatLayout
        ref="chatLayout"
        title="Chat"
    >
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
                :is-guest="! isAuthenticated"
                @commit-api-base-url="commitApiBaseUrl"
                @select-conversation="selectConversation"
                @create-conversation="createConversation"
                @rename-conversation="store.renameConversation"
                @delete-conversation="store.deleteConversation"
            />
        </template>

        <MessageThread
            :messages="messages"
            :thinking-message-id="thinkingMessageId"
            :conversation-id="activeConversationId"
        />
        <ChatComposer
            :disabled="isStreaming"
            :streaming="isStreaming"
            :allow-attachments="isAuthenticated"
            @send="sendMessage"
            @stop="cancel"
        />
    </ChatLayout>
</template>
