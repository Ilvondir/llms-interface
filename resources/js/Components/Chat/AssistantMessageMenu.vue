<script setup>
import { computed, ref } from 'vue';
import DialogModal from '@/Components/DialogModal.vue';
import Dropdown from '@/Components/Dropdown.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';

const props = defineProps({
    message: {
        type: Object,
        required: true,
    },
});

const showInfo = ref(false);

const formatTimestamp = (value) => {
    if (value == null) {
        return '—';
    }

    const date = new Date(value);

    if (Number.isNaN(date.getTime())) {
        return '—';
    }

    return date.toLocaleString(undefined, {
        year: 'numeric',
        month: '2-digit',
        day: '2-digit',
        hour: '2-digit',
        minute: '2-digit',
        second: '2-digit',
    });
};

const requestJson = computed(() => {
    if (props.message.requestPayload == null) {
        return 'No request payload stored for this message.';
    }

    try {
        return JSON.stringify(props.message.requestPayload, null, 2);
    } catch {
        return String(props.message.requestPayload);
    }
});

const openInfo = () => {
    showInfo.value = true;
};

const closeInfo = () => {
    showInfo.value = false;
};
</script>

<template>
    <div>
        <Dropdown align="right" width="48">
            <template #trigger>
                <button
                    type="button"
                    class="-mr-1 -mt-0.5 inline-flex h-7 w-7 items-center justify-center rounded-md text-gray-400 hover:bg-gray-100 hover:text-gray-600 dark:hover:bg-gray-800 dark:hover:text-gray-200 focus:outline-none focus-visible:ring-2 focus-visible:ring-gray-400"
                    aria-label="Message options"
                >
                    <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                        <path d="M10 6a1.5 1.5 0 110-3 1.5 1.5 0 010 3zm0 5.5a1.5 1.5 0 110-3 1.5 1.5 0 010 3zM10 17a1.5 1.5 0 110-3 1.5 1.5 0 010 3z" />
                    </svg>
                </button>
            </template>

            <template #content>
                <button
                    type="button"
                    class="block w-full px-4 py-2 text-start text-sm leading-5 text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-800 focus:outline-none focus:bg-gray-100 dark:focus:bg-gray-800 transition duration-150 ease-in-out"
                    @click="openInfo"
                >
                    Information
                </button>
            </template>
        </Dropdown>

        <DialogModal
            :show="showInfo"
            max-width="2xl"
            @close="closeInfo"
        >
            <template #title>
                Message information
            </template>

            <template #content>
                <dl class="space-y-3 text-sm">
                    <div>
                        <dt class="font-medium text-gray-900 dark:text-gray-100">
                            Sent
                        </dt>
                        <dd class="mt-0.5 text-gray-600 dark:text-gray-400 font-mono text-xs">
                            {{ formatTimestamp(message.sentAt) }}
                        </dd>
                    </div>
                    <div>
                        <dt class="font-medium text-gray-900 dark:text-gray-100">
                            Response received
                        </dt>
                        <dd class="mt-0.5 text-gray-600 dark:text-gray-400 font-mono text-xs">
                            {{ formatTimestamp(message.receivedAt) }}
                        </dd>
                    </div>
                    <div>
                        <dt class="font-medium text-gray-900 dark:text-gray-100">
                            Upstream request JSON
                        </dt>
                        <dd class="mt-1">
                            <pre
                                class="max-h-80 overflow-auto rounded-md border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-950 px-3 py-2 text-[11px] leading-relaxed text-gray-800 dark:text-gray-200 whitespace-pre font-mono"
                            >{{ requestJson }}</pre>
                        </dd>
                    </div>
                </dl>
            </template>

            <template #footer>
                <SecondaryButton @click="closeInfo">
                    Close
                </SecondaryButton>
            </template>
        </DialogModal>
    </div>
</template>
