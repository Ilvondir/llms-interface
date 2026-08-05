import { computed, reactive, readonly, watch } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import { useToast } from 'vue-toastification';
import { csrfToken } from '@/composables/chatApi';

const defaultParams = () => ({
    temperature: 0.7,
    max_tokens: null,
    top_p: 1,
});

const emptySettings = () => ({
    apiBaseUrl: '',
    defaultParams: defaultParams(),
    activeConversationId: null,
});

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

    const syncAfterJsonMutation = (props, { expectedConversationId = null } = {}) => {
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

    const flushSettingsPatch = async ({ generation = null } = {}) => {
        if (generation != null && generation !== mutationGeneration) {
            return;
        }

        const expectedGeneration = mutationGeneration;
        const expectedConversationId = state.activeConversation?.id ?? null;

        try {
            const props = await jsonRequest('PATCH', route('chat-settings.update'), {
                default_params: state.settings.defaultParams,
                api_base_url: state.settings.apiBaseUrl,
                active_conversation_id: state.settings.activeConversationId,
            });

            if (expectedGeneration !== mutationGeneration) {
                return;
            }

            syncAfterJsonMutation(props, { expectedConversationId });
        } catch (error) {
            if (expectedGeneration === mutationGeneration) {
                notifyPersistError(error);
            }

            throw error;
        }
    };

    const flushPendingPersists = async () => {
        clearTimeout(conversationPersistTimer);
        clearTimeout(settingsPersistTimer);
        conversationPersistTimer = null;
        settingsPersistTimer = null;

        await Promise.allSettled([
            flushConversationPatch(),
            flushSettingsPatch(),
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

    const ensureConversationId = async () => {
        if (state.activeConversation?.id) {
            return state.activeConversation.id;
        }

        const props = await jsonRequest('POST', route('conversations.store'));
        applyProps(props);

        return state.activeConversation?.id;
    };

    const createConversation = async () => {
        if (isEmptyConversation(state.activeConversation)) {
            return state.activeConversation;
        }

        await prepareNavigation();
        state.pendingAssistant = null;
        await inertiaVisit('post', route('conversations.store'));

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

        await ensureConversationId();

        return state.activeConversation;
    };

    const setModel = async (model) => {
        const conversation = await ensureLocalConversation();

        if (! conversation || conversation.model === model) {
            return;
        }

        conversation.model = model;
        scheduleConversationPersist();
    };

    const setTemperature = async (temperature) => {
        const value = Number(temperature);
        const conversation = await ensureLocalConversation();

        if (conversation) {
            conversation.params.temperature = value;
        }

        state.settings.defaultParams.temperature = value;
        scheduleConversationPersist();
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
        scheduleConversationPersist();
        scheduleSettingsPersist();
    };

    const setTopP = async (topP) => {
        const value = Number(topP);
        const conversation = await ensureLocalConversation();

        if (conversation) {
            conversation.params.top_p = value;
        }

        state.settings.defaultParams.top_p = value;
        scheduleConversationPersist();
        scheduleSettingsPersist();
    };

    const setSystemPrompt = async (systemPrompt) => {
        const conversation = await ensureLocalConversation();

        if (! conversation) {
            return;
        }

        conversation.systemPrompt = systemPrompt;
        scheduleConversationPersist();
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

        const conversationId = await ensureConversationId();

        const props = await jsonRequest('POST', route('conversations.prompts.store', conversationId), {
            role: 'user',
            content,
            sent_at: sentAt,
            model,
            request_payload: requestPayload,
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
                request_payload: pending.requestPayload ?? null,
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
            request_payload: message.requestPayload ?? null,
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
                if (! message?.content || (message.role !== 'user' && message.role !== 'assistant')) {
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
