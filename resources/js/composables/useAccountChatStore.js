import { computed, reactive, readonly, watch } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import { useToast } from 'vue-toastification';
import { csrfToken } from '@/composables/chatApi';
import { contentPlainText, isContentEmpty, sanitizeRequestPayloadForStorage } from '@/utils/contentParts';

const defaultParams = () => ({
    temperature: 0.7,
    max_tokens: null,
    top_p: 1,
});

const emptySettings = () => ({
    apiBaseUrl: '',
    defaultParams: defaultParams(),
    mcpServers: [],
    activeConversationId: null,
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
                name: typeof server.name === 'string' && server.name.trim() !== ''
                    ? server.name.trim()
                    : id,
                url: typeof server.url === 'string' ? server.url.trim() : '',
                hasToken: Boolean(server.hasToken),
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

const inertiaVisit = (method, url, data = {}, options = {}) => new Promise((resolve, reject) => {
    router.visit(url, {
        method,
        data,
        preserveScroll: true,
        ...options,
        onSuccess: (page) => {
            options.onSuccess?.(page);
            resolve(page);
        },
        onError: (errors) => {
            options.onError?.(errors);
            reject(errors);
        },
        onCancel: () => reject(new Error('Visit cancelled')),
    });
});

const jsonRequest = async (method, url, data = {}) => {
    const response = await fetch(url, {
        method,
        credentials: 'same-origin',
        headers: {
            Accept: 'application/json',
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': csrfToken(),
            'X-Requested-With': 'XMLHttpRequest',
        },
        body: method === 'GET' ? undefined : JSON.stringify(data),
    });

    if (! response.ok) {
        let message = `Request failed (${response.status})`;

        try {
            const payload = await response.json();
            message = payload.message ?? Object.values(payload.errors ?? {})[0]?.[0] ?? message;
        } catch {
            // ignore
        }

        throw new Error(message);
    }

    if (response.status === 204) {
        return null;
    }

    return response.json();
};

/**
 * Account-backed chat store.
 * Navigation (select/create/delete) uses Inertia; field + prompt mutations use fetch/JSON.
 */
export function useAccountChatStore(initialProps = {}) {
    const page = usePage();
    const toast = useToast();

    const state = reactive({
        settings: emptySettings(),
        conversations: [],
        activeConversation: null,
        pendingAssistant: null,
    });

    let conversationPersistTimer = null;
    let settingsPersistTimer = null;
    let mutationGeneration = 0;
    let lastPersistedApiBaseUrl = initialProps?.chatSettings?.apiBaseUrl ?? '';
    /** In-memory token overwrites awaiting settings PATCH (never read from localStorage). */
    const pendingMcpTokens = reactive({});

    const bumpMutationGeneration = () => {
        mutationGeneration += 1;

        return mutationGeneration;
    };

    const applyProps = (props, { replaceMessages = true } = {}) => {
        const chatSettings = props?.chatSettings;
        const conversations = props?.conversations;
        const activeConversation = props?.activeConversation;

        if (chatSettings) {
            state.settings = {
                apiBaseUrl: chatSettings.apiBaseUrl ?? '',
                defaultParams: {
                    ...defaultParams(),
                    ...(chatSettings.defaultParams ?? {}),
                },
                mcpServers: normalizeMcpServers(chatSettings.mcpServers),
                activeConversationId: chatSettings.activeConversationId ?? null,
            };
            lastPersistedApiBaseUrl = state.settings.apiBaseUrl;
        }

        if (Array.isArray(conversations)) {
            state.conversations = conversations.map((conversation) => ({ ...conversation }));
        }

        if (activeConversation) {
            const nextMessages = Array.isArray(activeConversation.messages)
                ? activeConversation.messages.map((message) => ({ ...message }))
                : [];

            state.activeConversation = {
                ...activeConversation,
                params: {
                    ...defaultParams(),
                    ...(activeConversation.params ?? {}),
                },
                enabledMcpServerIds: normalizeEnabledMcpServerIds(activeConversation.enabledMcpServerIds),
                messages: replaceMessages
                    ? nextMessages
                    : (state.activeConversation?.messages ?? nextMessages),
            };
            state.settings.activeConversationId = activeConversation.id;
        } else if (activeConversation === null) {
            state.activeConversation = null;
        }
    };

    applyProps(initialProps);

    watch(
        () => [
            page.props.chatSettings,
            page.props.conversations,
            page.props.activeConversation,
        ],
        () => {
            if (page.props.chatSettings !== undefined || page.props.conversations !== undefined || page.props.activeConversation !== undefined) {
                applyProps(page.props);
            }
        },
    );

    const activeConversation = computed(() => state.activeConversation);

    const messages = computed(() => {
        const base = state.activeConversation?.messages ?? [];

        if (! state.pendingAssistant) {
            return base;
        }

        return [...base, state.pendingAssistant];
    });

    const isEmptyConversation = (conversation) => (
        !! conversation && (conversation.messages?.length ?? 0) === 0 && ! state.pendingAssistant
    );

    const notifyPersistError = (error) => {
        const message = error?.message || 'Failed to save changes.';
        toast.error(message);
    };

    const syncAfterJsonMutation = (props, { expectedConversationId = null, syncMcpServers = false } = {}) => {
        if (expectedConversationId != null && state.activeConversation?.id != expectedConversationId) {
            return;
        }

        if (props?.activeConversation && expectedConversationId != null
            && props.activeConversation.id != expectedConversationId) {
            return;
        }

        if (props?.chatSettings) {
            lastPersistedApiBaseUrl = props.chatSettings.apiBaseUrl ?? lastPersistedApiBaseUrl;
            state.settings.activeConversationId = props.chatSettings.activeConversationId
                ?? state.settings.activeConversationId;
            state.settings.defaultParams = {
                ...defaultParams(),
                ...(props.chatSettings.defaultParams ?? state.settings.defaultParams),
            };
            if (props.chatSettings.apiBaseUrl !== undefined) {
                state.settings.apiBaseUrl = props.chatSettings.apiBaseUrl ?? state.settings.apiBaseUrl;
            }
            // Incomplete MCP drafts live only in local state until URL is valid — do not
            // overwrite them from unrelated PATCH acks (conversation / default_params).
            if (syncMcpServers && props.chatSettings.mcpServers !== undefined) {
                state.settings.mcpServers = normalizeMcpServers(props.chatSettings.mcpServers);
            }
        }

        if (Array.isArray(props?.conversations)) {
            state.conversations = props.conversations.map((conversation) => ({ ...conversation }));
        }

        if (props?.activeConversation && state.activeConversation) {
            if (props.activeConversation.id != state.activeConversation.id) {
                return;
            }

            state.activeConversation.id = props.activeConversation.id;
            state.activeConversation.title = props.activeConversation.title;
            state.activeConversation.createdAt = props.activeConversation.createdAt;
            state.activeConversation.updatedAt = props.activeConversation.updatedAt;

            if (props.activeConversation.systemPrompt !== undefined) {
                state.activeConversation.systemPrompt = props.activeConversation.systemPrompt;
            }

            if (props.activeConversation.model !== undefined) {
                state.activeConversation.model = props.activeConversation.model;
            }

            if (props.activeConversation.params) {
                state.activeConversation.params = {
                    ...defaultParams(),
                    ...props.activeConversation.params,
                };
            }

            if (props.activeConversation.enabledMcpServerIds !== undefined) {
                state.activeConversation.enabledMcpServerIds = normalizeEnabledMcpServerIds(
                    props.activeConversation.enabledMcpServerIds,
                );
            }

            state.settings.activeConversationId = props.activeConversation.id;
        } else if (props?.activeConversation && ! state.activeConversation) {
            applyProps(props);
        }
    };

    const flushConversationPatch = async ({ scheduledForId = null, generation = null } = {}) => {
        const conversation = state.activeConversation;

        if (! conversation?.id) {
            return;
        }

        if (scheduledForId != null && conversation.id != scheduledForId) {
            return;
        }

        if (generation != null && generation !== mutationGeneration) {
            return;
        }

        const expectedId = conversation.id;
        const expectedGeneration = mutationGeneration;

        try {
            const props = await jsonRequest('PATCH', route('conversations.update', conversation.id), {
                title: conversation.title,
                system_prompt: conversation.systemPrompt ?? '',
                model: conversation.model ?? '',
                params: conversation.params ?? defaultParams(),
                enabled_mcp_server_ids: normalizeEnabledMcpServerIds(conversation.enabledMcpServerIds),
            });

            if (expectedGeneration !== mutationGeneration || state.activeConversation?.id != expectedId) {
                return;
            }

            syncAfterJsonMutation(props, { expectedConversationId: expectedId });
        } catch (error) {
            if (expectedGeneration === mutationGeneration) {
                notifyPersistError(error);
            }

            throw error;
        }
    };

    const mcpServersReadyToPersist = (servers) => {
        if (! Array.isArray(servers) || servers.length === 0) {
            return true;
        }

        return servers.every((server) => {
            const url = typeof server.url === 'string' ? server.url.trim() : '';

            if (url === '') {
                return false;
            }

            try {
                const parsed = new URL(url);

                return parsed.protocol === 'http:' || parsed.protocol === 'https:';
            } catch {
                return false;
            }
        });
    };

    const flushSettingsPatch = async ({ generation = null, includeMcpServers = false } = {}) => {
        if (generation != null && generation !== mutationGeneration) {
            return;
        }

        const expectedGeneration = mutationGeneration;
        const expectedConversationId = state.activeConversation?.id ?? null;

        try {
            const payload = {
                default_params: state.settings.defaultParams,
                api_base_url: state.settings.apiBaseUrl,
                active_conversation_id: state.settings.activeConversationId,
            };

            const tokenIdsSent = [];

            if (includeMcpServers) {
                const servers = normalizeMcpServers(state.settings.mcpServers);

                if (! mcpServersReadyToPersist(servers)) {
                    return;
                }

                payload.mcp_servers = servers.map((server) => {
                    const row = {
                        id: server.id,
                        name: server.name,
                        url: server.url,
                    };
                    const pending = pendingMcpTokens[server.id];

                    if (typeof pending === 'string' && pending.trim() !== '') {
                        row.token = pending.trim();
                        tokenIdsSent.push(server.id);
                    }

                    return row;
                });
            }

            const props = await jsonRequest('PATCH', route('chat-settings.update'), payload);

            if (expectedGeneration !== mutationGeneration) {
                return;
            }

            for (const id of tokenIdsSent) {
                delete pendingMcpTokens[id];
            }

            syncAfterJsonMutation(props, {
                expectedConversationId,
                syncMcpServers: includeMcpServers,
            });
        } catch (error) {
            if (expectedGeneration === mutationGeneration) {
                notifyPersistError(error);
            }

            throw error;
        }
    };

    const flushMcpServersPatch = async ({ generation = null } = {}) => {
        await flushSettingsPatch({ generation, includeMcpServers: true });
    };

    const flushPendingPersists = async () => {
        clearTimeout(conversationPersistTimer);
        clearTimeout(settingsPersistTimer);
        clearTimeout(mcpSettingsPersistTimer);
        conversationPersistTimer = null;
        settingsPersistTimer = null;
        mcpSettingsPersistTimer = null;

        await Promise.allSettled([
            flushConversationPatch(),
            flushSettingsPatch(),
            flushMcpServersPatch(),
        ]);
    };

    const prepareNavigation = async () => {
        await flushPendingPersists();
        bumpMutationGeneration();
    };

    const scheduleConversationPersist = () => {
        clearTimeout(conversationPersistTimer);
        const scheduledForId = state.activeConversation?.id ?? null;
        const generation = mutationGeneration;

        conversationPersistTimer = setTimeout(() => {
            flushConversationPatch({ scheduledForId, generation }).catch(() => {});
        }, 400);
    };

    const scheduleSettingsPersist = () => {
        clearTimeout(settingsPersistTimer);
        const generation = mutationGeneration;

        settingsPersistTimer = setTimeout(() => {
            flushSettingsPatch({ generation }).catch(() => {});
        }, 400);
    };

    let mcpSettingsPersistTimer = null;

    const scheduleMcpSettingsPersist = () => {
        clearTimeout(mcpSettingsPersistTimer);
        const generation = mutationGeneration;

        mcpSettingsPersistTimer = setTimeout(() => {
            flushMcpServersPatch({ generation }).catch(() => {});
        }, 400);
    };

    const blankDraft = () => ({
        id: null,
        title: 'New chat',
        systemPrompt: '',
        model: '',
        params: {
            ...defaultParams(),
            ...state.settings.defaultParams,
        },
        enabledMcpServerIds: [],
        messages: [],
        createdAt: Date.now(),
        updatedAt: Date.now(),
    });

    const ensureConversationId = async ({ title = null } = {}) => {
        if (state.activeConversation?.id) {
            return state.activeConversation.id;
        }

        const draft = state.activeConversation ?? blankDraft();

        const props = await jsonRequest('POST', route('conversations.store'), {
            title: title ?? draft.title ?? 'New chat',
            system_prompt: draft.systemPrompt ?? '',
            model: draft.model ?? '',
            params: draft.params ?? defaultParams(),
            enabled_mcp_server_ids: normalizeEnabledMcpServerIds(draft.enabledMcpServerIds),
        });
        applyProps(props);

        return state.activeConversation?.id;
    };

    const createConversation = async () => {
        if (isEmptyConversation(state.activeConversation)) {
            return state.activeConversation;
        }

        await prepareNavigation();
        state.pendingAssistant = null;
        state.activeConversation = blankDraft();
        state.settings.activeConversationId = null;

        return state.activeConversation;
    };

    const selectConversation = async (id) => {
        if (id == null) {
            return;
        }

        if (state.activeConversation?.id == id) {
            return;
        }

        await prepareNavigation();
        state.pendingAssistant = null;
        await inertiaVisit('get', route('conversations.show', id));
    };

    const renameConversation = async ({ id, title }) => {
        const props = await jsonRequest('PATCH', route('conversations.update', id), {
            title: title.trim() || 'Untitled',
        });

        if (state.activeConversation?.id == id || ! state.activeConversation) {
            syncAfterJsonMutation(props, { expectedConversationId: id });
        } else if (Array.isArray(props?.conversations)) {
            state.conversations = props.conversations.map((conversation) => ({ ...conversation }));
        }
    };

    const deleteConversation = async (id) => {
        await prepareNavigation();
        state.pendingAssistant = null;
        await inertiaVisit('delete', route('conversations.destroy', id));
    };

    const setApiBaseUrl = (apiBaseUrl) => {
        state.settings.apiBaseUrl = apiBaseUrl;
    };

    const persistApiBaseUrl = async () => {
        const current = state.settings.apiBaseUrl ?? '';

        if (current === lastPersistedApiBaseUrl) {
            return;
        }

        const expectedGeneration = mutationGeneration;

        try {
            const props = await jsonRequest('PATCH', route('chat-settings.update'), {
                api_base_url: current,
            });

            if (expectedGeneration !== mutationGeneration) {
                return;
            }

            syncAfterJsonMutation(props, {
                expectedConversationId: state.activeConversation?.id ?? null,
            });
            lastPersistedApiBaseUrl = current;
        } catch (error) {
            if (expectedGeneration === mutationGeneration) {
                notifyPersistError(error);
            }

            throw error;
        }
    };

    const ensureLocalConversation = async () => {
        if (state.activeConversation) {
            return state.activeConversation;
        }

        state.activeConversation = blankDraft();
        state.settings.activeConversationId = null;

        return state.activeConversation;
    };

    const setModel = async (model) => {
        const conversation = await ensureLocalConversation();

        if (! conversation || conversation.model === model) {
            return;
        }

        conversation.model = model;
        if (conversation.id) {
            scheduleConversationPersist();
        }
    };

    const setTemperature = async (temperature) => {
        const value = Number(temperature);
        const conversation = await ensureLocalConversation();

        if (conversation) {
            conversation.params.temperature = value;
        }

        state.settings.defaultParams.temperature = value;
        if (conversation?.id) {
            scheduleConversationPersist();
        }
        scheduleSettingsPersist();
    };

    const setMaxTokens = async (maxTokens) => {
        const value = maxTokens === '' || maxTokens === null || maxTokens === undefined
            ? null
            : Number(maxTokens);
        const normalized = Number.isFinite(value) ? value : null;
        const conversation = await ensureLocalConversation();

        if (conversation) {
            conversation.params.max_tokens = normalized;
        }

        state.settings.defaultParams.max_tokens = normalized;
        if (conversation?.id) {
            scheduleConversationPersist();
        }
        scheduleSettingsPersist();
    };

    const setTopP = async (topP) => {
        const value = Number(topP);
        const conversation = await ensureLocalConversation();

        if (conversation) {
            conversation.params.top_p = value;
        }

        state.settings.defaultParams.top_p = value;
        if (conversation?.id) {
            scheduleConversationPersist();
        }
        scheduleSettingsPersist();
    };

    const setSystemPrompt = async (systemPrompt) => {
        const conversation = await ensureLocalConversation();

        if (! conversation) {
            return;
        }

        conversation.systemPrompt = systemPrompt;
        if (conversation.id) {
            scheduleConversationPersist();
        }
    };

    const setMcpServers = (mcpServers) => {
        const next = normalizeMcpServers(mcpServers);
        const known = new Set(next.map((server) => server.id));

        state.settings.mcpServers = next;

        for (const id of Object.keys(pendingMcpTokens)) {
            if (! known.has(id)) {
                delete pendingMcpTokens[id];
            }
        }

        let enabledChanged = false;

        if (state.activeConversation) {
            const before = normalizeEnabledMcpServerIds(state.activeConversation.enabledMcpServerIds);
            const after = before.filter((id) => known.has(id));
            enabledChanged = before.length !== after.length || before.some((id, index) => id !== after[index]);
            state.activeConversation.enabledMcpServerIds = after;
        }

        scheduleMcpSettingsPersist();

        if (enabledChanged && state.activeConversation?.id) {
            scheduleConversationPersist();
        }
    };

    const setEnabledMcpServerIds = async (enabledMcpServerIds) => {
        const conversation = await ensureLocalConversation();
        const known = new Set(state.settings.mcpServers.map((server) => server.id));

        conversation.enabledMcpServerIds = normalizeEnabledMcpServerIds(enabledMcpServerIds)
            .filter((id) => known.has(id));

        if (conversation.id) {
            scheduleConversationPersist();
        }
    };

    const setMcpToken = (id, token) => {
        if (typeof id !== 'string' || id.trim() === '') {
            return;
        }

        const trimmed = typeof token === 'string' ? token : '';

        if (trimmed === '') {
            delete pendingMcpTokens[id];
        } else {
            pendingMcpTokens[id] = trimmed;
        }

        scheduleMcpSettingsPersist();
    };

    const appendMessage = async ({
        role,
        content,
        reasoning = null,
        stats = null,
        error = null,
        model = null,
        sentAt = null,
        receivedAt = null,
        requestPayload = null,
        params = null,
    }) => {
        if (role === 'assistant') {
            const pending = {
                id: `pending-${crypto.randomUUID()}`,
                role: 'assistant',
                content: content ?? '',
                createdAt: Date.now(),
                pending: true,
            };

            if (reasoning != null) {
                pending.reasoning = reasoning;
            }

            if (stats != null) {
                pending.stats = stats;
            }

            if (error != null) {
                pending.error = error;
            }

            if (model != null && String(model).trim() !== '') {
                pending.model = String(model).trim();
            }

            if (sentAt != null) {
                pending.sentAt = sentAt;
            }

            if (receivedAt != null) {
                pending.receivedAt = receivedAt;
            }

            if (requestPayload != null) {
                pending.requestPayload = requestPayload;
            }

            if (params != null) {
                pending.params = params;
            }

            state.pendingAssistant = pending;

            return pending;
        }

        await flushPendingPersists();

        const plain = contentPlainText(content);
        const titleFromPrompt = (plain.trim().slice(0, 48) || (Array.isArray(content) ? 'Image' : 'New chat'));
        const conversationId = await ensureConversationId({ title: titleFromPrompt });

        const props = await jsonRequest('POST', route('conversations.prompts.store', conversationId), {
            role: 'user',
            content,
            sent_at: sentAt,
            model,
            request_payload: requestPayload != null
                ? sanitizeRequestPayloadForStorage(requestPayload)
                : null,
        });
        applyProps(props);

        const messagesList = state.activeConversation?.messages ?? [];

        return messagesList[messagesList.length - 1] ?? null;
    };

    const updateMessage = async (messageId, patch, options = {}) => {
        if (state.pendingAssistant && state.pendingAssistant.id === messageId) {
            Object.assign(state.pendingAssistant, patch);

            if (options.persist === false) {
                return;
            }

            const pending = state.pendingAssistant;
            const conversationId = state.activeConversation?.id;

            if (! conversationId) {
                return;
            }

            const props = await jsonRequest('POST', route('conversations.prompts.store', conversationId), {
                role: 'assistant',
                content: pending.content ?? '',
                reasoning: pending.reasoning ?? null,
                stats: pending.stats ?? null,
                error: pending.error ?? null,
                model: pending.model ?? null,
                params: pending.params ?? state.activeConversation?.params ?? defaultParams(),
                sent_at: pending.sentAt ?? null,
                received_at: pending.receivedAt ?? null,
                request_payload: pending.requestPayload != null
                    ? sanitizeRequestPayloadForStorage(pending.requestPayload)
                    : null,
            });
            state.pendingAssistant = null;
            applyProps(props);

            return;
        }

        const conversation = state.activeConversation;

        if (! conversation) {
            return;
        }

        const message = conversation.messages.find((item) => item.id == messageId);

        if (! message) {
            return;
        }

        Object.assign(message, patch);

        if (options.persist === false) {
            return;
        }

        const props = await jsonRequest('PATCH', route('conversations.prompts.update', [conversation.id, messageId]), {
            content: message.content,
            reasoning: message.reasoning ?? null,
            stats: message.stats ?? null,
            error: message.error ?? null,
            model: message.model ?? null,
            params: message.params ?? null,
            sent_at: message.sentAt ?? null,
            received_at: message.receivedAt ?? null,
            request_payload: message.requestPayload != null
                ? sanitizeRequestPayloadForStorage(message.requestPayload)
                : null,
        });
        applyProps(props);
    };

    return {
        state: readonly(state),
        activeConversation,
        messages,
        isEmptyConversation,
        createConversation,
        selectConversation,
        renameConversation,
        deleteConversation,
        setApiBaseUrl,
        persistApiBaseUrl,
        setModel,
        setTemperature,
        setMaxTokens,
        setTopP,
        setSystemPrompt,
        setMcpServers,
        setEnabledMcpServerIds,
        setMcpToken,
        pendingMcpTokens: readonly(pendingMcpTokens),
        flushPendingPersists,
        appendMessage,
        updateMessage,
        toModelMessages: () => {
            const conversation = state.activeConversation;

            if (! conversation) {
                return [];
            }

            const result = [];

            if (conversation.systemPrompt?.trim()) {
                result.push({
                    role: 'system',
                    content: conversation.systemPrompt.trim(),
                });
            }

            for (const message of conversation.messages ?? []) {
                if (isContentEmpty(message?.content) || (message.role !== 'user' && message.role !== 'assistant')) {
                    continue;
                }

                result.push({
                    role: message.role,
                    content: message.content,
                });
            }

            return result;
        },
        persist: () => {},
    };
}
