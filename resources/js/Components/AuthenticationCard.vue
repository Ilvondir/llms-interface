<template>
    <div class="w-full max-w-md border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900 rounded-lg px-6 py-5">
        <div v-if="$slots.logo" class="mb-6 flex flex-col items-center gap-2">
            <slot name="logo" />
        </div>

        <slot />
    </div>
</template>
