import { computed, reactive, readonly } from 'vue';

export const GUEST_CHAT_STORAGE_KEY = 'llms.guest.v1';
export const GUEST_CHAT_TTL_MS = 86_400_000;
export const GUEST_CHAT_VERSION = 2;
/** In-memory only — never written to the conversations list until the first message. */
export const GUEST_DRAFT_ID = '__draft__';

const defaultParams = () => ({
    temperature: 0.7,
    max_tokens: null,
    top_p: 1,
});

const emptyState = () => ({
    version: GUEST_CHAT_VERSION,
    settings: {
        apiBaseUrl: '',
        defaultParams: defaultParams(),
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
        settings: state.settings,
        conversations,
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

const createConversationRecord = ({ draft = false } = {}) => {
    const timestamp = now();

    return {
        id: draft ? GUEST_DRAFT_ID : crypto.randomUUID(),
        title: 'New chat',
        createdAt: timestamp,
        updatedAt: timestamp,
        systemPrompt: '',
        model: '',
        params: defaultParams(),
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

    const beginDraft = () => {
        state.draft = createConversationRecord({ draft: true });
        state.draft.params = {
            ...defaultParams(),
            ...state.settings.defaultParams,
        };
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

        return beginDraft();
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
        conversation.systemPrompt = systemPrompt;
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
        appendMessage,
        updateMessage,
        toModelMessages: () => toModelMessages(activeConversation.value),
        persist,
    };

    return store;
}
