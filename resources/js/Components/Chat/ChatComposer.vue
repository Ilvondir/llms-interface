<script setup>
import { ref } from 'vue';

defineProps({
    disabled: {
        type: Boolean,
        default: false,
    },
    streaming: {
        type: Boolean,
        default: false,
    },
});

const emit = defineEmits(['send', 'stop']);
const draft = ref('');

const canSend = () => draft.value.trim().length > 0;

const submit = () => {
    const content = draft.value.trim();

    if (! content) {
        return;
    }

    emit('send', content);
    draft.value = '';
};
</script>

<template>
    <form
        class="shrink-0 border-t border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900 px-4 py-3"
        @submit.prevent="submit"
    >
        <div class="mx-auto max-w-3xl flex items-center gap-2">
            <div class="flex min-w-0 flex-1 items-center gap-2 rounded-2xl border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-950 py-1.5 pl-4 pr-1.5 shadow-sm focus-within:border-gray-500 dark:focus-within:border-gray-400">
                <textarea
                    v-model="draft"
                    rows="1"
                    class="min-h-[2.25rem] flex-1 resize-none border-0 bg-transparent py-1.5 text-sm leading-6 text-gray-900 dark:text-gray-100 placeholder:text-gray-400 focus:ring-0"
                    placeholder="Message…"
                    :disabled="disabled"
                    @keydown.enter.exact.prevent="submit"
                />
                <button
                    type="submit"
                    class="inline-flex size-9 shrink-0 items-center justify-center rounded-lg bg-gray-900 text-white transition disabled:opacity-30 dark:bg-gray-100 dark:text-gray-900"
                    :disabled="disabled || ! canSend()"
                    aria-label="Send"
                >
                    <svg class="size-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                        <path d="M3.478 2.404a.75.75 0 0 0-.926.941l2.432 7.905H13.5a.75.75 0 0 1 0 1.5H4.984l-2.432 7.905a.75.75 0 0 0 .926.94 60.519 60.519 0 0 0 18.445-8.986.75.75 0 0 0 0-1.218A60.517 60.517 0 0 0 3.478 2.404Z" />
                    </svg>
                </button>
            </div>
            <button
                v-if="streaming"
                type="button"
                class="shrink-0 rounded-xl border border-red-600/40 bg-red-600 px-3 py-2 text-xs font-semibold text-white hover:bg-red-500 focus:outline-none focus:ring-2 focus:ring-red-500/40"
                @click="emit('stop')"
            >
                Stop
            </button>
        </div>
    </form>
</template>
