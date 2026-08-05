<script setup>
import { computed } from 'vue';
import { Head, Link, usePage } from '@inertiajs/vue3';
import ApplicationMark from '@/Components/ApplicationMark.vue';

defineProps({
    title: {
        type: String,
        default: 'LLMsInterface',
    },
    fullHeight: {
        type: Boolean,
        default: false,
    },
});

const page = usePage();
const user = computed(() => page.props.auth?.user ?? null);

const logout = () => {
    const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';

    fetch(route('logout'), {
        method: 'POST',
        credentials: 'same-origin',
        headers: {
            Accept: 'application/json',
            'X-CSRF-TOKEN': token,
            'X-Requested-With': 'XMLHttpRequest',
        },
    }).finally(() => {
        // Full document navigation remounts Chat/Index as guest (Inertia reuse
        // of the same page after auth→guest left guestStore null → white screen).
        window.location.assign(route('home'));
    });
};
</script>

<template>
    <div
        class="flex flex-col bg-gray-50 dark:bg-gray-950 text-gray-900 dark:text-gray-100"
        :class="fullHeight ? 'h-screen' : 'min-h-screen'"
    >
        <Head :title="title" />

        <header class="flex h-14 shrink-0 items-center justify-between gap-2 border-b border-gray-200 bg-white px-3 dark:border-gray-800 dark:bg-gray-900 sm:gap-4 sm:px-4">
            <Link :href="route('home')" class="flex min-w-0 items-center gap-2">
                <ApplicationMark class="block h-8 w-auto shrink-0" />
                <span class="truncate text-sm font-semibold max-sm:hidden">LLMsInterface</span>
            </Link>

            <nav class="flex shrink-0 items-center gap-2 text-sm sm:gap-3">
                <template v-if="user">
                    <Link
                        :href="route('home')"
                        class="text-gray-600 hover:text-gray-900 dark:text-gray-300 dark:hover:text-white"
                    >
                        Chat
                    </Link>
                    <Link
                        :href="route('profile.show')"
                        class="text-gray-600 hover:text-gray-900 dark:text-gray-300 dark:hover:text-white"
                    >
                        Profile
                    </Link>
                    <Link
                        v-if="page.props.jetstream?.hasApiFeatures"
                        :href="route('api-tokens.index')"
                        class="hidden text-gray-600 hover:text-gray-900 dark:text-gray-300 dark:hover:text-white sm:inline"
                    >
                        API Tokens
                    </Link>
                    <button
                        type="button"
                        class="text-gray-600 hover:text-gray-900 dark:text-gray-300 dark:hover:text-white"
                        @click="logout"
                    >
                        Log out
                    </button>
                </template>
                <template v-else>
                    <Link
                        :href="route('login')"
                        class="text-gray-600 hover:text-gray-900 dark:text-gray-300 dark:hover:text-white"
                    >
                        Log in
                    </Link>
                    <Link
                        :href="route('register')"
                        class="rounded-md bg-gray-900 px-2.5 py-1.5 font-medium text-white hover:opacity-90 dark:bg-gray-100 dark:text-gray-900 sm:px-3"
                    >
                        Register
                    </Link>
                </template>
            </nav>
        </header>

        <div class="flex-1 min-h-0 flex flex-col">
            <slot />
        </div>
    </div>
</template>
