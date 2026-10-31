import { usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

export type AiActionId =
    | 'grammar'
    | 'improve'
    | 'shorten'
    | 'expand'
    | 'describe'
    | 'auto'
    | 'custom';

export type AiClientSettings = {
    enabled: boolean;
    chat_enabled?: boolean;
    default_model_id: string | null;
    models: Array<{
        id: string;
        label: string;
        provider: string;
        model: string;
    }>;
    features: Record<string, boolean>;
    actions: Array<{ id: string; label: string }>;
    form_fill_enabled?: boolean;
    field_edit_enabled?: boolean;
    max_input_chars: number;
    max_instruction_chars: number;
    max_form_fields?: number;
    panel_size?: string;
    panel_sizes?: Array<{
        id: string;
        label: string;
        width: number;
        prompt_rows: number;
        result_max_h: number;
    }>;
};

type AssistResult = {
    text: string;
    model_id: string;
    usage: {
        prompt_tokens: number;
        completion_tokens: number;
        total_tokens: number;
    };
    duration_ms?: number;
    estimated_cost_usd?: number;
    log_id?: number | null;
};

function readCsrfToken(): string | null {
    const match = document.cookie.match(/(?:^|; )XSRF-TOKEN=([^;]*)/);
    if (!match?.[1]) {
        return null;
    }

    return decodeURIComponent(match[1]);
}

export function useAiAssist() {
    const page = usePage();
    const processing = ref(false);
    const error = ref<string | null>(null);

    const settings = computed(() => {
        const raw = page.props.aiSettings as AiClientSettings | undefined;
        return (
            raw ?? {
                enabled: false,
                default_model_id: null,
                models: [],
                features: {},
                actions: [],
                form_fill_enabled: false,
                field_edit_enabled: false,
                max_input_chars: 2500,
                max_instruction_chars: 1000,
                max_form_fields: 40,
                panel_size: 'comfortable',
                panel_sizes: [],
            }
        );
    });

    const enabled = computed(
        () => settings.value.enabled && settings.value.models.length > 0,
    );

    async function assist(options: {
        action: AiActionId | string;
        text: string;
        instruction?: string;
        modelId?: string | null;
    }): Promise<AssistResult> {
        error.value = null;
        processing.value = true;

        try {
            const csrf = readCsrfToken();
            const response = await fetch('/ai/assist', {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    ...(csrf ? { 'X-XSRF-TOKEN': csrf } : {}),
                },
                body: JSON.stringify({
                    action: options.action,
                    text: options.text,
                    instruction: options.instruction || undefined,
                    model_id: options.modelId?.trim() || undefined,
                }),
            });

            const data = (await response.json().catch(() => ({}))) as {
                message?: string;
                text?: string;
                model_id?: string;
                usage?: AssistResult['usage'];
                duration_ms?: number;
                estimated_cost_usd?: number;
                log_id?: number | null;
            };

            if (!response.ok) {
                throw new Error(
                    data.message || 'AI assist failed. Please try again.',
                );
            }

            if (!data.text) {
                throw new Error('AI assist returned no text.');
            }

            return {
                text: data.text,
                model_id: String(data.model_id ?? ''),
                usage: data.usage ?? {
                    prompt_tokens: 0,
                    completion_tokens: 0,
                    total_tokens: 0,
                },
                duration_ms: data.duration_ms,
                estimated_cost_usd: data.estimated_cost_usd,
                log_id: data.log_id,
            };
        } catch (err) {
            const message =
                err instanceof Error ? err.message : 'AI assist failed.';
            error.value = message;
            throw err;
        } finally {
            processing.value = false;
        }
    }

    return {
        settings,
        enabled,
        processing,
        error,
        assist,
    };
}
