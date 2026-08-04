<script setup>
import { computed, onMounted } from 'vue';
import ChatLayout from '@/Layouts/ChatLayout.vue';
import ChatSidebar from '@/Components/Chat/ChatSidebar.vue';
import MessageThread from '@/Components/Chat/MessageThread.vue';
import ChatComposer from '@/Components/Chat/ChatComposer.vue';
import { useChatModels } from '@/composables/useChatModels';
import { useGuestChatStore } from '@/composables/useGuestChatStore';

const store = useGuestChatStore();
const { modelOptions, modelsLoading, modelsError, fetchModels } = useChatModels();

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

const sendMessage = (content) => {
    store.appendMessage({
        role: 'user',
        content,
    });
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
                @commit-api-base-url="commitApiBaseUrl"
                @select-conversation="store.selectConversation"
                @create-conversation="store.createConversation"
                @rename-conversation="store.renameConversation"
                @delete-conversation="store.deleteConversation"
            />
        </template>

        <MessageThread :messages="messages" />
        <ChatComposer @send="sendMessage" />
    </ChatLayout>
</template>
