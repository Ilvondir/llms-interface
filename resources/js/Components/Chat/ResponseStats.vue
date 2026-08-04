<script setup>
defineProps({
    stats: {
        type: Object,
        default: null,
    },
});
</script>

<template>
    <div
        v-if="stats"
        class="mt-2 flex flex-wrap gap-x-3 gap-y-1 text-xs text-gray-500 dark:text-gray-400"
    >
        <span v-if="stats.inputTokens != null">in: {{ stats.inputTokens }}</span>
        <span v-if="stats.outputTokens != null">out: {{ stats.outputTokens }}</span>
        <span v-if="stats.reasoningTokens != null">reasoning: {{ stats.reasoningTokens }}</span>
        <span v-if="stats.tokensPerSecond != null">{{ Number(stats.tokensPerSecond).toFixed(1) }} t/s</span>
        <span v-if="stats.ttftMs != null">TTFT: {{ stats.ttftMs }} ms</span>
    </div>
</template>
