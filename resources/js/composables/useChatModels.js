import { ref } from 'vue';
import { csrfToken, extractModelIds } from '@/composables/chatApi';

export function useChatModels() {
    const modelOptions = ref([]);
    const modelsLoading = ref(false);
    const modelsError = ref(null);

    const fetchModels = async (apiBaseUrl) => {
        const trimmed = String(apiBaseUrl ?? '').trim();

        if (! trimmed) {
            modelOptions.value = [];
            modelsError.value = null;

            return [];
        }

        modelsLoading.value = true;
        modelsError.value = null;

        try {
            const response = await fetch(route('chat.models'), {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': csrfToken(),
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: JSON.stringify({
                    api_base_url: trimmed,
                }),
            });

            const payload = await response.json().catch(() => ({}));

            if (! response.ok) {
                throw new Error(payload.message || `Failed to load models (${response.status})`);
            }

            const ids = extractModelIds(payload);
            modelOptions.value = ids;

            return ids;
        } catch (error) {
            modelOptions.value = [];
            modelsError.value = error instanceof Error ? error.message : 'Failed to load models';

            return [];
        } finally {
            modelsLoading.value = false;
        }
    };

    return {
        modelOptions,
        modelsLoading,
        modelsError,
        fetchModels,
    };
}
