<script setup>
import { Link } from '@inertiajs/vue3';
import ApplicationMark from '@/Components/ApplicationMark.vue';
</script>

<template>
    <Link :href="route('home')" class="flex flex-col items-center gap-2">
        <ApplicationMark class="block h-10 w-auto" />
        <span class="font-semibold text-sm text-gray-900 dark:text-gray-100">LLMsInterface</span>
    </Link>
</template>
