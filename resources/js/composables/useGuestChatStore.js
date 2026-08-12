import { computed, reactive, readonly } from 'vue';

export const GUEST_CHAT_STORAGE_KEY = 'llms.guest.v1';
export const GUEST_CHAT_TTL_MS = 86_400_000;
export const GUEST_CHAT_VERSION = 3;
/** In-memory only — never written to the conversations list until the first message. */
export const GUEST_DRAFT_ID = '__draft__';

const defaultParams = () => ({
    temperature: 0.7,
    max_tokens: null,
    top_p: 1,
});

const normalizeMcpServers = (servers) => {
    if (! Array.isArray(servers)) {
        return [];
    }

    const seen = new Set();

    return servers
        .filter((server) => server && typeof server.id === 'string' && server.id.trim() !== '')
        .map((server) => {
            const id = server.id.trim();

            return {
                id,
                // Keep spaces while typing — trim only when writing to localStorage.
                name: typeof server.name === 'string' ? server.name : id,
                url: typeof server.url === 'string' ? server.url.trim() : '',
            };
        })
        .filter((server) => {
            if (seen.has(server.id)) {
                return false;
            }

            seen.add(server.id);

            return true;
        });
};

const normalizeEnabledMcpServerIds = (ids) => {
    if (! Array.isArray(ids)) {
        return [];
    }

    const seen = new Set();

    return ids.filter((id) => {
        if (typeof id !== 'string' || id.trim() === '' || seen.has(id)) {
            return false;
        }

        seen.add(id);

        return true;
    });
};

const emptyState = () => ({
    version: GUEST_CHAT_VERSION,
    settings: {
        apiBaseUrl: '',
        defaultParams: defaultParams(),
        mcpServers: [],
    },
    conversations: [],
    activeConversationId: null,
});

const now = () => Date.now();

const purgeExpired = (conversations) => {
    const cutoff = now() - GUEST_CHAT_TTL_MS;

    return conversations.filter((conversation) => conversation.updatedAt > cutoff);
};

const readStorage = () => {
    if (typeof window === 'undefined' || ! window.localStorage) {
        return emptyState();
    }

    try {
        const raw = window.localStorage.getItem(GUEST_CHAT_STORAGE_KEY);

        if (! raw) {
            return emptyState();
        }

        const parsed = JSON.parse(raw);

        if (! parsed || parsed.version !== GUEST_CHAT_VERSION) {
            return emptyState();
        }

        const conversations = purgeExpired(
            (Array.isArray(parsed.conversations) ? parsed.conversations : []).map((conversation) => ({
                ...conversation,
                params: {
                    ...defaultParams(),
                    ...(conversation.params ?? {}),
                },
                enabledMcpServerIds: normalizeEnabledMcpServerIds(conversation.enabledMcpServerIds),
            })),
        );
        let activeConversationId = parsed.activeConversationId ?? null;

        if (activeConversationId && ! conversations.some((conversation) => conversation.id === activeConversationId)) {
            activeConversationId = conversations[0]?.id ?? null;
        }

        return {
            version: GUEST_CHAT_VERSION,
            settings: {
                apiBaseUrl: parsed.settings?.apiBaseUrl ?? '',
                defaultParams: {
                    ...defaultParams(),
                    ...(parsed.settings?.defaultParams ?? {}),
                },
                mcpServers: normalizeMcpServers(parsed.settings?.mcpServers),
            },
            conversations,
            activeConversationId,
        };
    } catch {
        return emptyState();
    }
};

const writeStorage = (state) => {
    if (typeof window === 'undefined' || ! window.localStorage) {
        return;
    }

    const conversations = purgeExpired(state.conversations);
    const onDraft = state.activeConversationId === GUEST_DRAFT_ID;

    const payload = {
        version: GUEST_CHAT_VERSION,
        settings: {
            apiBaseUrl: state.settings.apiBaseUrl ?? '',
            defaultParams: {
                ...defaultParams(),
                ...(state.settings.defaultParams ?? {}),
            },
            mcpServers: normalizeMcpServers(state.settings.mcpServers).map((server) => {
                const trimmedName = typeof server.name === 'string' ? server.name.trim() : '';

                return {
                    ...server,
                    name: trimmedName !== '' ? trimmedName : server.id,
                };
            }),
        },
        conversations: conversations.map((conversation) => ({
            ...conversation,
            enabledMcpServerIds: normalizeEnabledMcpServerIds(conversation.enabledMcpServerIds),
        })),
        activeConversationId: onDraft
            ? null
            : (conversations.some((conversation) => conversation.id === state.activeConversationId)
                ? state.activeConversationId
                : (conversations[0]?.id ?? null)),
    };

    window.localStorage.setItem(GUEST_CHAT_STORAGE_KEY, JSON.stringify(payload));

    state.conversations = conversations;

    if (! onDraft) {
        state.activeConversationId = payload.activeConversationId;
    }
};

export const toModelMessages = (conversation) => {
    const messages = [];

    if (conversation?.systemPrompt?.trim()) {
        messages.push({
            role: 'system',
            content: conversation.systemPrompt.trim(),
        });
    }

    for (const message of conversation?.messages ?? []) {
        if (! message?.content || (message.role !== 'user' && message.role !== 'assistant')) {
            continue;
        }

        messages.push({
            role: message.role,
            content: message.content,
        });
    }

    return messages;
};

const createConversationRecord = ({ draft = false, source = null, knownMcpServerIds = null } = {}) => {
    const timestamp = now();
    const known = knownMcpServerIds instanceof Set
        ? knownMcpServerIds
        : null;

    return {
        id: draft ? GUEST_DRAFT_ID : crypto.randomUUID(),
        title: 'New chat',
        createdAt: timestamp,
        updatedAt: timestamp,
        systemPrompt: source?.systemPrompt ?? '',
        model: source?.model ?? '',
        params: {
            ...defaultParams(),
            ...(source?.params ?? {}),
        },
        enabledMcpServerIds: normalizeEnabledMcpServerIds(source?.enabledMcpServerIds ?? [])
            .filter((id) => known == null || known.has(id)),
        messages: [],
    };
};

let store = null;

export function useGuestChatStore() {
    if (store) {
        return store;
    }

    const loaded = readStorage();
    const state = reactive({
        ...loaded,
        draft: null,
    });

    writeStorage(state);

    const persist = () => {
        writeStorage(state);
    };

    const activeConversation = computed(() => {
        if (state.activeConversationId === GUEST_DRAFT_ID) {
            return state.draft;
        }

        return state.conversations.find((conversation) => conversation.id === state.activeConversationId) ?? null;
    });

    const messages = computed(() => activeConversation.value?.messages ?? []);

    const touch = (conversation) => {
        conversation.updatedAt = now();
    };

    const isEmptyConversation = (conversation) => (
        !! conversation && (conversation.messages?.length ?? 0) === 0
    );

    const beginDraft = (source = null) => {
        const knownMcpServerIds = new Set(state.settings.mcpServers.map((server) => server.id));
        state.draft = createConversationRecord({ draft: true, source, knownMcpServerIds });
        if (! source) {
            state.draft.params = {
                ...defaultParams(),
                ...state.settings.defaultParams,
            };
        }
        state.activeConversationId = GUEST_DRAFT_ID;
        persist();

        return state.draft;
    };

    const promoteDraft = (conversation) => {
        if (! conversation || conversation.id !== GUEST_DRAFT_ID) {
            return conversation;
        }

        conversation.id = crypto.randomUUID();
        state.conversations = [conversation, ...state.conversations];
        state.activeConversationId = conversation.id;
        state.draft = null;

        return conversation;
    };

    const ensureActiveConversation = () => {
        if (activeConversation.value) {
            return activeConversation.value;
        }

        return beginDraft();
    };

    const createConversation = () => {
        const active = activeConversation.value;

        // Already on a blank chat — do not spam empty conversations.
        if (isEmptyConversation(active)) {
            return active;
        }

        // Plain snapshot so carry-over (incl. MCP enables) matches model/params.
        return beginDraft({
            systemPrompt: active.systemPrompt ?? '',
            model: active.model ?? '',
            params: {
                ...defaultParams(),
                ...(active.params ?? {}),
            },
            enabledMcpServerIds: normalizeEnabledMcpServerIds(active.enabledMcpServerIds),
        });
    };

    const selectConversation = (id) => {
        if (! state.conversations.some((conversation) => conversation.id === id)) {
            return;
        }

        state.draft = null;
        state.activeConversationId = id;
        persist();
    };

    const renameConversation = ({ id, title }) => {
        const conversation = state.conversations.find((item) => item.id === id);

        if (! conversation) {
            return;
        }

        conversation.title = title.trim() || 'Untitled';
        touch(conversation);
        persist();
    };

    const deleteConversation = (id) => {
        state.conversations = state.conversations.filter((conversation) => conversation.id !== id);

        if (state.activeConversationId === id) {
            state.activeConversationId = state.conversations[0]?.id ?? null;
        }

        persist();
    };

    const setApiBaseUrl = (apiBaseUrl) => {
        state.settings.apiBaseUrl = apiBaseUrl;
        persist();
    };

    const setModel = (model) => {
        const conversation = ensureActiveConversation();
        conversation.model = model;
        touch(conversation);
        persist();
    };

    const setTemperature = (temperature) => {
        const conversation = ensureActiveConversation();
        conversation.params.temperature = Number(temperature);
        state.settings.defaultParams.temperature = Number(temperature);
        touch(conversation);
        persist();
    };

    const setMaxTokens = (maxTokens) => {
        const conversation = ensureActiveConversation();
        const value = maxTokens === '' || maxTokens === null || maxTokens === undefined
            ? null
            : Number(maxTokens);

        conversation.params.max_tokens = Number.isFinite(value) ? value : null;
        state.settings.defaultParams.max_tokens = conversation.params.max_tokens;
        touch(conversation);
        persist();
    };

    const setTopP = (topP) => {
        const conversation = ensureActiveConversation();
        conversation.params.top_p = Number(topP);
        state.settings.defaultParams.top_p = Number(topP);
        touch(conversation);
        persist();
    };

    const setSystemPrompt = (systemPrompt) => {
        const conversation = ensureActiveConversation();
        // Memory only while typing — persist on blur via persistSystemPrompt.
        conversation.systemPrompt = systemPrompt;
    };

    const persistSystemPrompt = () => {
        const conversation = activeConversation.value;

        if (! conversation) {
            return;
        }

        touch(conversation);
        persist();
    };

    const setMcpServers = (mcpServers) => {
        // Metadata only — never persist tokens (kept in Index.vue memory for stream requests).
        const previousIds = new Set(state.settings.mcpServers.map((server) => server.id));
        state.settings.mcpServers = normalizeMcpServers(mcpServers);
        const addedIds = state.settings.mcpServers
            .map((server) => server.id)
            .filter((id) => ! previousIds.has(id));

        if (addedIds.length > 0) {
            const conversation = ensureActiveConversation();
            const known = new Set(state.settings.mcpServers.map((server) => server.id));
            const enabled = new Set(
                normalizeEnabledMcpServerIds(conversation.enabledMcpServerIds)
                    .filter((id) => known.has(id)),
            );

            for (const id of addedIds) {
                enabled.add(id);
            }

            conversation.enabledMcpServerIds = [...enabled];
            touch(conversation);
        }

        persist();
    };

    const setEnabledMcpServerIds = (enabledMcpServerIds) => {
        const conversation = ensureActiveConversation();
        const known = new Set(state.settings.mcpServers.map((server) => server.id));
        conversation.enabledMcpServerIds = normalizeEnabledMcpServerIds(enabledMcpServerIds)
            .filter((id) => known.has(id));
        touch(conversation);
        persist();
    };

    const appendMessage = ({
        role,
        content,
        reasoning = null,
        stats = null,
        error = null,
        model = null,
        sentAt = null,
        receivedAt = null,
        requestPayload = null,
        toolCalls = null,
        toolCallId = null,
        mcpCalls = null,
    }) => {
        if (Array.isArray(content)) {
            throw new Error('Guest chat does not support image attachments.');
        }

        const conversation = ensureActiveConversation();

        if (role === 'user') {
            promoteDraft(conversation);
        }

        const message = {
            id: crypto.randomUUID(),
            role,
            content,
            createdAt: now(),
        };

        if (reasoning != null) {
            message.reasoning = reasoning;
        }

        if (stats != null) {
            message.stats = stats;
        }

        if (error != null) {
            message.error = error;
        }

        if (model != null && String(model).trim() !== '') {
            message.model = String(model).trim();
        }

        if (sentAt != null) {
            message.sentAt = sentAt;
        }

        if (receivedAt != null) {
            message.receivedAt = receivedAt;
        }

        if (requestPayload != null) {
            message.requestPayload = requestPayload;
        }

        if (Array.isArray(toolCalls) && toolCalls.length > 0) {
            message.toolCalls = toolCalls;
        }

        if (typeof toolCallId === 'string' && toolCallId !== '') {
            message.toolCallId = toolCallId;
        }

        if (mcpCalls != null) {
            message.mcpCalls = mcpCalls;
        }

        conversation.messages.push(message);

        if (role === 'user' && conversation.title === 'New chat') {
            conversation.title = content.trim().slice(0, 48) || 'New chat';
        }

        touch(conversation);
        persist();

        return message;
    };

    const updateMessage = (messageId, patch, options = {}) => {
        const conversation = activeConversation.value;

        if (! conversation) {
            return;
        }

        const message = conversation.messages.find((item) => item.id === messageId);

        if (! message) {
            return;
        }

        Object.assign(message, patch);

        if (options.persist === false) {
            return;
        }

        touch(conversation);
        persist();
    };

    store = {
        state: readonly(state),
        activeConversation,
        messages,
        isEmptyConversation,
        createConversation,
        selectConversation,
        renameConversation,
        deleteConversation,
        setApiBaseUrl,
        setModel,
        setTemperature,
        setMaxTokens,
        setTopP,
        setSystemPrompt,
        persistSystemPrompt,
        setMcpServers,
        setEnabledMcpServerIds,
        appendMessage,
        updateMessage,
        toModelMessages: () => toModelMessages(activeConversation.value),
        persist,
    };

    return store;
}
