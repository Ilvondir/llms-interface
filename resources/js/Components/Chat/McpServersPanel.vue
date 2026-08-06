<script setup>
import { computed } from 'vue';

const props = defineProps({
    mcpServers: {
        type: Array,
        default: () => [],
    },
    enabledMcpServerIds: {
        type: Array,
        default: () => [],
    },
    /**
     * In-memory tokens by server id (guest). Account uses hasToken + optional overwrite field.
     * Shape: Record<string, string>
     */
    mcpTokens: {
        type: Object,
        default: () => ({}),
    },
    isGuest: {
        type: Boolean,
        default: false,
    },
    /** When true, omit outer section chrome (used inside a collapsible sidebar details). */
    embedded: {
        type: Boolean,
        default: false,
    },
});

const emit = defineEmits([
    'update:mcpServers',
    'update:enabledMcpServerIds',
    'update:mcpToken',
]);

const enabledSet = computed(() => new Set(props.enabledMcpServerIds ?? []));

const tokenPlaceholder = (server) => {
    if (props.isGuest) {
        return 'API token (session only)';
    }

    return server.hasToken ? '•••••••• (saved — leave blank to keep)' : 'API token';
};

const emitServers = (servers) => {
    emit('update:mcpServers', servers);
};

const addServer = () => {
    const base = 'mcp';
    let n = (props.mcpServers?.length ?? 0) + 1;
    let id = `${base}-${n}`;

    while ((props.mcpServers ?? []).some((server) => server.id === id)) {
        n += 1;
        id = `${base}-${n}`;
    }

    emitServers([
        ...(props.mcpServers ?? []),
        { id, name: 'MCP server', url: '' },
    ]);
};

const updateServerField = (id, field, value) => {
    emitServers((props.mcpServers ?? []).map((server) => (
        server.id === id ? { ...server, [field]: value } : server
    )));
};

const removeServer = (id) => {
    emitServers((props.mcpServers ?? []).filter((server) => server.id !== id));
    emit('update:enabledMcpServerIds', (props.enabledMcpServerIds ?? []).filter((item) => item !== id));
};

const toggleEnabled = (id, checked) => {
    const current = new Set(props.enabledMcpServerIds ?? []);

    if (checked) {
        current.add(id);
    } else {
        current.delete(id);
    }

    emit('update:enabledMcpServerIds', [...current]);
};

const onTokenInput = (id, value) => {
    emit('update:mcpToken', { id, token: value });
};
</script>

<template>
    <div
        class="space-y-2"
        :class="embedded ? '' : 'border-t border-gray-200 dark:border-gray-800 pt-2.5'"
    >
        <div class="flex items-center justify-between gap-2">
            <h2
                v-if="! embedded"
                class="font-semibold uppercase tracking-wide text-[10px] text-gray-500 dark:text-gray-400"
            >
                MCP servers
            </h2>
            <span v-else />
            <button
                type="button"
                class="text-[10px] text-gray-600 dark:text-gray-300 underline-offset-2 hover:underline"
                @click="addServer"
            >
                Add
            </button>
        </div>

        <p
            v-if="isGuest"
            class="text-[10px] leading-snug text-gray-500 dark:text-gray-400"
        >
            Tokens stay in memory for this tab only — not saved to the browser.
        </p>

        <p
            v-if="(mcpServers?.length ?? 0) === 0"
            class="text-[10px] text-gray-500 dark:text-gray-400"
        >
            No servers yet. Add an HTTP MCP endpoint URL.
        </p>

        <div
            v-for="server in mcpServers"
            :key="server.id"
            class="space-y-1.5 rounded border border-gray-200 dark:border-gray-800 p-2"
        >
            <div class="flex items-center justify-between gap-2">
                <label class="flex items-center gap-1.5 min-w-0">
                    <input
                        type="checkbox"
                        class="rounded border-gray-300 dark:border-gray-600 text-gray-900 focus:ring-gray-500"
                        :checked="enabledSet.has(server.id)"
                        @change="toggleEnabled(server.id, $event.target.checked)"
                    >
                    <span class="truncate font-medium text-gray-700 dark:text-gray-200">
                        {{ server.name || server.id }}
                    </span>
                </label>
                <button
                    type="button"
                    class="shrink-0 text-[10px] text-red-600 dark:text-red-400 hover:underline"
                    @click="removeServer(server.id)"
                >
                    Remove
                </button>
            </div>

            <label class="block space-y-0.5">
                <span class="text-gray-500 dark:text-gray-400">Name</span>
                <input
                    type="text"
                    class="w-full rounded border-gray-300 dark:border-gray-700 dark:bg-gray-950 shadow-sm text-xs py-1"
                    :value="server.name"
                    @input="updateServerField(server.id, 'name', $event.target.value)"
                >
            </label>

            <label class="block space-y-0.5">
                <span class="text-gray-500 dark:text-gray-400">URL</span>
                <input
                    type="url"
                    class="w-full rounded border-gray-300 dark:border-gray-700 dark:bg-gray-950 shadow-sm text-xs py-1"
                    :value="server.url"
                    placeholder="https://mcp.example.com/mcp"
                    @input="updateServerField(server.id, 'url', $event.target.value)"
                >
            </label>

            <label class="block space-y-0.5">
                <span class="text-gray-500 dark:text-gray-400">Token</span>
                <input
                    type="password"
                    autocomplete="off"
                    class="w-full rounded border-gray-300 dark:border-gray-700 dark:bg-gray-950 shadow-sm text-xs py-1"
                    :value="mcpTokens[server.id] ?? ''"
                    :placeholder="tokenPlaceholder(server)"
                    @input="onTokenInput(server.id, $event.target.value)"
                >
            </label>
        </div>
    </div>
</template>
