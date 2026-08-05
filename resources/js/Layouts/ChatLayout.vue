<script setup>
import { onMounted, onUnmounted, ref, watch } from 'vue';
import AppShellLayout from '@/Layouts/AppShellLayout.vue';

defineProps({
    title: {
        type: String,
        default: 'Chat',
    },
});

const sidebarOpen = ref(false);
const isMdUp = ref(false);
let mq = null;

const openSidebar = () => {
    sidebarOpen.value = true;
};

const closeSidebar = () => {
    sidebarOpen.value = false;
};

const toggleSidebar = () => {
    sidebarOpen.value = ! sidebarOpen.value;
};

defineExpose({
    openSidebar,
    closeSidebar,
    toggleSidebar,
});

const onKeydown = (event) => {
    if (event.key === 'Escape' && sidebarOpen.value && ! isMdUp.value) {
        closeSidebar();
    }
};

const syncBreakpoint = () => {
    isMdUp.value = mq?.matches ?? false;

    if (isMdUp.value) {
        closeSidebar();
    }
};

const syncBodyScrollLock = () => {
    if (typeof document === 'undefined') {
        return;
    }

    document.body.classList.toggle('overflow-hidden', sidebarOpen.value && ! isMdUp.value);
};

watch([sidebarOpen, isMdUp], syncBodyScrollLock);

onMounted(() => {
    window.addEventListener('keydown', onKeydown);
    mq = window.matchMedia('(min-width: 768px)');
    isMdUp.value = mq.matches;
    mq.addEventListener('change', syncBreakpoint);
});

onUnmounted(() => {
    window.removeEventListener('keydown', onKeydown);
    mq?.removeEventListener('change', syncBreakpoint);
    document.body.classList.remove('overflow-hidden');
});
</script>

<template>
    <AppShellLayout :title="title" full-height>
        <div class="relative flex min-h-0 flex-1">
            <Transition
                enter-active-class="transition-opacity duration-200 ease-out"
                enter-from-class="opacity-0"
                enter-to-class="opacity-100"
                leave-active-class="transition-opacity duration-150 ease-in"
                leave-from-class="opacity-100"
                leave-to-class="opacity-0"
            >
                <div
                    v-if="sidebarOpen && ! isMdUp"
                    class="fixed inset-0 z-40 bg-gray-900/50 md:hidden"
                    aria-hidden="true"
                    @click="closeSidebar"
                />
            </Transition>

            <aside
                id="chat-sidebar"
                class="chat-scroll fixed inset-y-0 left-0 z-50 flex w-[min(20rem,100vw)] max-w-full flex-col border-r border-gray-200 bg-white transition-transform duration-200 ease-out dark:border-gray-800 dark:bg-gray-900 md:static md:z-auto md:w-72 md:shrink-0 md:translate-x-0 md:visible md:pointer-events-auto"
                :class="sidebarOpen
                    ? 'translate-x-0'
                    : '-translate-x-full max-md:pointer-events-none max-md:invisible'"
            >
                <div class="flex shrink-0 items-center justify-between gap-2 border-b border-gray-200 px-3 py-2.5 dark:border-gray-800 md:hidden">
                    <span class="text-sm font-semibold text-gray-900 dark:text-gray-100">
                        Settings & chats
                    </span>
                    <button
                        type="button"
                        class="inline-flex size-9 items-center justify-center rounded-lg text-gray-500 hover:bg-gray-100 hover:text-gray-900 dark:hover:bg-gray-800 dark:hover:text-gray-100"
                        aria-label="Close sidebar"
                        @click="closeSidebar"
                    >
                        <svg class="size-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>
                <div class="min-h-0 flex-1 overflow-y-auto">
                    <slot name="sidebar" />
                </div>
            </aside>

            <main class="flex min-h-0 min-w-0 flex-1 flex-col">
                <div class="flex shrink-0 items-center gap-2 border-b border-gray-200 bg-white px-3 py-2 dark:border-gray-800 dark:bg-gray-900 md:hidden">
                    <button
                        type="button"
                        class="inline-flex size-9 items-center justify-center rounded-lg text-gray-600 hover:bg-gray-100 hover:text-gray-900 dark:text-gray-300 dark:hover:bg-gray-800 dark:hover:text-white"
                        aria-label="Open settings and chats"
                        :aria-expanded="sidebarOpen"
                        aria-controls="chat-sidebar"
                        @click="openSidebar"
                    >
                        <svg class="size-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5" />
                        </svg>
                    </button>
                    <span class="truncate text-sm font-medium text-gray-700 dark:text-gray-200">
                        Settings & chats
                    </span>
                </div>

                <slot />
            </main>
        </div>
    </AppShellLayout>
</template>
