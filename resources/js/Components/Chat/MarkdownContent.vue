<script setup>
import { computed } from 'vue';
import { renderMarkdown } from '@/utils/renderMarkdown';

const props = defineProps({
    content: {
        type: String,
        default: '',
    },
    /**
     * Smaller, muted typography for reasoning / thinking blocks.
     */
    compact: {
        type: Boolean,
        default: false,
    },
});

const html = computed(() => renderMarkdown(props.content));
</script>

<template>
    <div
        class="prose dark:prose-invert max-w-none prose-a:text-blue-600 dark:prose-a:text-blue-400 [&>*:first-child]:mt-0 [&>*:last-child]:mb-0"
        :class="compact
            ? 'prose-sm text-xs leading-relaxed text-gray-600 dark:text-gray-400 prose-p:my-1.5 prose-pre:bg-gray-100/80 dark:prose-pre:bg-gray-900/60'
            : 'prose-sm text-sm prose-pre:bg-gray-100 dark:prose-pre:bg-gray-800 prose-pre:text-gray-800 dark:prose-pre:text-gray-100'"
        v-html="html"
    />
</template>
