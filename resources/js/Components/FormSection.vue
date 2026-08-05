<script setup>
import { computed, useSlots } from 'vue';
import SectionTitle from './SectionTitle.vue';

defineEmits(['submitted']);

const hasActions = computed(() => !! useSlots().actions);
</script>

<template>
    <div class="md:grid md:grid-cols-3 md:gap-6">
        <SectionTitle>
            <template #title>
                <slot name="title" />
            </template>
            <template #description>
                <slot name="description" />
            </template>
        </SectionTitle>

        <div class="mt-5 md:mt-0 md:col-span-2">
            <form @submit.prevent="$emit('submitted')">
                <div
                    class="px-4 py-5 bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 sm:p-6 sm:rounded-t-lg"
                    :class="hasActions ? '' : 'sm:rounded-lg'"
                >
                    <div class="grid grid-cols-6 gap-6">
                        <slot name="form" />
                    </div>
                </div>

                <div v-if="hasActions" class="flex items-center justify-end px-4 py-3 bg-gray-50 dark:bg-gray-950/50 text-end sm:px-6 border border-t-0 border-gray-200 dark:border-gray-800 sm:rounded-b-lg">
                    <slot name="actions" />
                </div>
            </form>
        </div>
    </div>
</template>
