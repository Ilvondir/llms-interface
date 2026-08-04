<script setup>
import { computed, ref } from 'vue';

const props = defineProps({
    reasoning: {
        type: String,
        default: '',
    },
    thinking: {
        type: Boolean,
        default: false,
    },
});

const isOpen = ref(false);

const hasReasoning = computed(() => typeof props.reasoning === 'string' && props.reasoning.trim() !== '');
const visible = computed(() => props.thinking || hasReasoning.value);

const onToggle = (event) => {
    isOpen.value = event.target.open;
};
</script>

<template>
    <details
        v-if="visible"
        class="mb-3 rounded-md border border-gray-200/80 dark:border-gray-700/80 bg-gray-50/80 dark:bg-gray-950/40"
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
        <pre
            v-if="hasReasoning"
            class="border-t border-gray-200/80 dark:border-gray-700/80 px-3 py-2.5 text-xs leading-relaxed text-gray-600 dark:text-gray-400 whitespace-pre-wrap font-sans"
        >{{ reasoning }}</pre>
        <p
            v-else-if="thinking"
            class="border-t border-gray-200/80 dark:border-gray-700/80 px-3 py-2.5 text-xs text-gray-500 dark:text-gray-500"
        >
            Waiting for reasoning…
        </p>
    </details>
</template>
