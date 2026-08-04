<script setup>
import { nextTick, ref } from 'vue';

defineProps({
    conversations: {
        type: Array,
        default: () => [],
    },
    activeId: {
        type: String,
        default: null,
    },
});

const emit = defineEmits(['select', 'create', 'rename', 'delete']);

const editingId = ref(null);
const draftTitle = ref('');
const titleInputEl = ref(null);

const setTitleInputRef = (el) => {
    titleInputEl.value = el;
};

const startRename = async (conversation) => {
    editingId.value = conversation.id;
    draftTitle.value = conversation.title || '';
    await nextTick();
    titleInputEl.value?.focus();
    titleInputEl.value?.select();
};

const commitRename = (conversationId) => {
    if (editingId.value !== conversationId) {
        return;
    }

    const title = draftTitle.value.trim() || 'Untitled';
    emit('rename', { id: conversationId, title });
    editingId.value = null;
};

const cancelRename = () => {
    editingId.value = null;
};
</script>

<template>
    <div class="space-y-0.5">
        <button
            type="button"
            class="w-full text-left rounded border border-dashed border-gray-300 dark:border-gray-600 px-2 py-1.5 text-xs text-gray-600 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-800"
            @click="$emit('create')"
        >
            + New chat
        </button>

        <p
            v-if="conversations.length === 0"
            class="px-1 py-2 text-[11px] text-gray-500 dark:text-gray-400"
        >
            No conversations yet
        </p>

        <div
            v-for="conversation in conversations"
            :key="conversation.id"
            class="group flex items-center gap-0.5 rounded"
            :class="conversation.id === activeId
                ? 'bg-gray-100 dark:bg-gray-800'
                : 'hover:bg-gray-50 dark:hover:bg-gray-800/60'"
        >
            <input
                v-if="editingId === conversation.id"
                :ref="setTitleInputRef"
                v-model="draftTitle"
                type="text"
                class="min-w-0 flex-1 rounded border-gray-300 dark:border-gray-600 dark:bg-gray-950 text-xs py-1 px-2"
                @keydown.enter.prevent="commitRename(conversation.id)"
                @keydown.escape.prevent="cancelRename"
                @blur="commitRename(conversation.id)"
            >

            <button
                v-else
                type="button"
                class="min-w-0 flex-1 text-left px-2 py-1.5 text-xs truncate"
                :class="conversation.id === activeId
                    ? 'font-medium text-gray-900 dark:text-gray-100'
                    : 'text-gray-700 dark:text-gray-300'"
                @click="$emit('select', conversation.id)"
                @dblclick.stop="startRename(conversation)"
            >
                {{ conversation.title || 'Untitled' }}
            </button>

            <button
                v-if="editingId !== conversation.id"
                type="button"
                class="shrink-0 rounded p-1 text-gray-400 opacity-0 group-hover:opacity-100 hover:text-gray-700 dark:hover:text-gray-200 focus:opacity-100"
                title="Rename"
                @click.stop="startRename(conversation)"
            >
                <svg class="size-3.5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L10.582 16.07a4.5 4.5 0 01-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 011.13-1.897l8.932-8.931z" />
                </svg>
            </button>

            <button
                v-if="editingId !== conversation.id"
                type="button"
                class="shrink-0 rounded p-1 text-gray-400 opacity-0 group-hover:opacity-100 hover:text-red-600 dark:hover:text-red-400 focus:opacity-100"
                title="Delete"
                @click.stop="$emit('delete', conversation.id)"
            >
                <svg class="size-3.5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M14.74 9l-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 01-2.244 2.077H8.084a2.25 2.25 0 01-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 00-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 013.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 00-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 00-7.5 0" />
                </svg>
            </button>
        </div>
    </div>
</template>
