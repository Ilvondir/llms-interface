<script setup>
defineProps({
    reasoning: {
        type: String,
        default: '',
    },
});
</script>

<template>
    <details
        v-if="reasoning"
        class="mt-2 rounded-md border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-900/50"
    >
        <summary class="cursor-pointer px-3 py-2 text-xs font-medium text-gray-600 dark:text-gray-400">
            Reasoning
        </summary>
        <pre class="px-3 pb-3 text-xs text-gray-700 dark:text-gray-300 whitespace-pre-wrap font-sans">{{ reasoning }}</pre>
    </details>
</template>
