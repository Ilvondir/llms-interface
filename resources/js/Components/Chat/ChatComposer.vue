<script setup>
import { computed, ref } from 'vue';
import { useToast } from 'vue-toastification';
import { ACCEPTED_IMAGE_TYPES, fileToCompressedDataUrl } from '@/utils/imageAttach';

const props = defineProps({
    disabled: {
        type: Boolean,
        default: false,
    },
    streaming: {
        type: Boolean,
        default: false,
    },
    allowAttachments: {
        type: Boolean,
        default: false,
    },
});

const emit = defineEmits(['send', 'stop']);
const toast = useToast();
const draft = ref('');
const imageDataUrl = ref(null);
const attaching = ref(false);
const fileInput = ref(null);
const isDragging = ref(false);

const canSend = computed(() => (
    draft.value.trim().length > 0 || imageDataUrl.value != null
));

const acceptAttr = ACCEPTED_IMAGE_TYPES.join(',');

const clearImage = () => {
    imageDataUrl.value = null;
};

const attachFile = async (file) => {
    if (! props.allowAttachments) {
        toast.info('Sign in to send images.');

        return;
    }

    if (! file) {
        return;
    }

    attaching.value = true;

    try {
        imageDataUrl.value = await fileToCompressedDataUrl(file);
    } catch (error) {
        toast.error(error?.message || 'Could not attach image.');
        imageDataUrl.value = null;
    } finally {
        attaching.value = false;
    }
};

const onFilePicked = async (event) => {
    const file = event.target.files?.[0] ?? null;
    event.target.value = '';
    await attachFile(file);
};

const onPaste = async (event) => {
    const items = event.clipboardData?.items;

    if (! items) {
        return;
    }

    for (const item of items) {
        if (item.kind === 'file' && item.type.startsWith('image/')) {
            event.preventDefault();
            const file = item.getAsFile();
            await attachFile(file);

            return;
        }
    }
};

const onDrop = async (event) => {
    isDragging.value = false;

    if (! props.allowAttachments || props.disabled) {
        return;
    }

    const file = event.dataTransfer?.files?.[0];

    if (file) {
        event.preventDefault();
        await attachFile(file);
    }
};

const onDragEnter = () => {
    if (props.allowAttachments && ! props.disabled) {
        isDragging.value = true;
    }
};

const submit = () => {
    const text = draft.value.trim();
    const image = imageDataUrl.value;

    if (! text && ! image) {
        return;
    }

    if (props.disabled || attaching.value) {
        return;
    }

    emit('send', { text, imageDataUrl: image });
    draft.value = '';
    imageDataUrl.value = null;
};
</script>

<template>
    <form
        class="shrink-0 border-t border-gray-200 bg-white px-3 py-3 dark:border-gray-800 dark:bg-gray-900 sm:px-4"
        @submit.prevent="submit"
        @paste="onPaste"
    >
        <div class="mx-auto max-w-3xl flex items-end gap-2">
            <div
                class="flex min-w-0 flex-1 flex-col gap-2 rounded-2xl border bg-white dark:bg-gray-950 py-1.5 pl-3 pr-1.5 shadow-sm transition"
                :class="isDragging
                    ? 'border-gray-500 dark:border-gray-400 ring-1 ring-gray-400/40'
                    : 'border-gray-300 dark:border-gray-600 focus-within:border-gray-500 dark:focus-within:border-gray-400'"
                @dragenter.prevent="onDragEnter"
                @dragover.prevent="onDragEnter"
                @dragleave.prevent="isDragging = false"
                @drop.prevent="onDrop"
            >
                <div
                    v-if="imageDataUrl"
                    class="relative mx-1 mt-1 w-fit"
                >
                    <img
                        :src="imageDataUrl"
                        alt="Attachment preview"
                        class="max-h-28 rounded-lg border border-gray-200 dark:border-gray-700 object-cover"
                    >
                    <button
                        type="button"
                        class="absolute -right-2 -top-2 inline-flex size-6 items-center justify-center rounded-full bg-gray-900 text-xs text-white dark:bg-gray-100 dark:text-gray-900"
                        aria-label="Remove image"
                        :disabled="disabled"
                        @click="clearImage"
                    >
                        ×
                    </button>
                </div>

                <div class="flex items-end gap-1">
                    <button
                        v-if="allowAttachments"
                        type="button"
                        class="mb-0.5 inline-flex size-9 shrink-0 items-center justify-center rounded-lg text-gray-500 transition hover:bg-gray-100 hover:text-gray-800 disabled:opacity-40 dark:hover:bg-gray-800 dark:hover:text-gray-100"
                        :disabled="disabled || attaching"
                        aria-label="Attach image"
                        @click="fileInput?.click()"
                    >
                        <svg class="size-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="m2.25 15.75 5.159-5.159a2.25 2.25 0 0 1 3.182 0l5.159 5.159m-1.5-1.5 1.409-1.409a2.25 2.25 0 0 1 3.182 0l2.909 2.909M3.75 21h16.5A2.25 2.25 0 0 0 22.5 18.75V5.25A2.25 2.25 0 0 0 20.25 3H3.75A2.25 2.25 0 0 0 1.5 5.25v13.5A2.25 2.25 0 0 0 3.75 21Z" />
                        </svg>
                    </button>
                    <input
                        ref="fileInput"
                        type="file"
                        class="hidden"
                        :accept="acceptAttr"
                        @change="onFilePicked"
                    >
                    <textarea
                        v-model="draft"
                        rows="1"
                        class="min-h-[2.25rem] flex-1 resize-none border-0 bg-transparent py-1.5 text-sm leading-6 text-gray-900 dark:text-gray-100 placeholder:text-gray-400 focus:ring-0"
                        :placeholder="allowAttachments ? 'Message or paste an image…' : 'Message…'"
                        :disabled="disabled"
                        @keydown.enter.exact.prevent="submit"
                    />
                    <button
                        type="submit"
                        class="mb-0.5 inline-flex size-9 shrink-0 items-center justify-center rounded-lg bg-gray-900 text-white transition disabled:opacity-30 dark:bg-gray-100 dark:text-gray-900"
                        :disabled="disabled || attaching || ! canSend"
                        aria-label="Send"
                    >
                        <svg class="size-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                            <path d="M3.478 2.404a.75.75 0 0 0-.926.941l2.432 7.905H13.5a.75.75 0 0 1 0 1.5H4.984l-2.432 7.905a.75.75 0 0 0 .926.94 60.519 60.519 0 0 0 18.445-8.986.75.75 0 0 0 0-1.218A60.517 60.517 0 0 0 3.478 2.404Z" />
                        </svg>
                    </button>
                </div>
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
