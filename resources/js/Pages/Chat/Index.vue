<script setup>
import { ref } from 'vue';
import ChatLayout from '@/Layouts/ChatLayout.vue';
import ChatSidebar from '@/Components/Chat/ChatSidebar.vue';
import MessageThread from '@/Components/Chat/MessageThread.vue';
import ChatComposer from '@/Components/Chat/ChatComposer.vue';

const apiBaseUrl = ref('');
const model = ref('');
const temperature = ref(0.7);
const maxTokens = ref(2048);
const topP = ref(1);
const systemPrompt = ref('');
const conversations = ref([]);
const activeConversationId = ref(null);
const messages = ref([]);

const createConversation = () => {
    const id = crypto.randomUUID();
    conversations.value = [
        {
            id,
            title: 'New chat',
        },
        ...conversations.value,
    ];
    activeConversationId.value = id;
    messages.value = [];
};

const selectConversation = (id) => {
    activeConversationId.value = id;
    messages.value = [];
};

const renameConversation = ({ id, title }) => {
    conversations.value = conversations.value.map((conversation) => (
        conversation.id === id
            ? { ...conversation, title }
            : conversation
    ));
};

const sendMessage = (content) => {
    if (! activeConversationId.value) {
        createConversation();
    }

    messages.value = [
        ...messages.value,
        {
            id: crypto.randomUUID(),
            role: 'user',
            content,
        },
    ];
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
                :conversations="conversations"
                :active-conversation-id="activeConversationId"
                @select-conversation="selectConversation"
                @create-conversation="createConversation"
                @rename-conversation="renameConversation"
            />
        </template>

        <MessageThread :messages="messages" />
        <ChatComposer @send="sendMessage" />
    </ChatLayout>
</template>
