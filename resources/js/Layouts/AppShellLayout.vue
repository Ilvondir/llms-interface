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

        <header class="shrink-0 flex items-center justify-between gap-4 border-b border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900 px-4 h-14">
            <Link :href="route('home')" class="flex items-center gap-2 min-w-0">
                <ApplicationMark class="block h-8 w-auto shrink-0" />
                <span class="font-semibold text-sm truncate">LLMsInterface</span>
            </Link>

            <nav class="flex items-center gap-3 text-sm">
                <template v-if="user">
                    <Link
                        :href="route('home')"
                        class="text-gray-600 dark:text-gray-300 hover:text-gray-900 dark:hover:text-white"
                    >
                        Chat
                    </Link>
                    <Link
                        :href="route('profile.show')"
                        class="text-gray-600 dark:text-gray-300 hover:text-gray-900 dark:hover:text-white"
                    >
                        Profile
                    </Link>
                    <Link
                        v-if="page.props.jetstream?.hasApiFeatures"
                        :href="route('api-tokens.index')"
                        class="text-gray-600 dark:text-gray-300 hover:text-gray-900 dark:hover:text-white"
                    >
                        API Tokens
                    </Link>
                    <button
                        type="button"
                        class="text-gray-600 dark:text-gray-300 hover:text-gray-900 dark:hover:text-white"
                        @click="logout"
                    >
                        Log out
                    </button>
                </template>
                <template v-else>
                    <Link
                        :href="route('login')"
                        class="text-gray-600 dark:text-gray-300 hover:text-gray-900 dark:hover:text-white"
                    >
                        Log in
                    </Link>
                    <Link
                        :href="route('register')"
                        class="rounded-md bg-gray-900 dark:bg-gray-100 text-white dark:text-gray-900 px-3 py-1.5 font-medium hover:opacity-90"
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
