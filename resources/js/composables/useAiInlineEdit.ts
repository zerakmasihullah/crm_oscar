import { usePage } from '@inertiajs/vue3';
import { computed, ref, shallowRef } from 'vue';
import {
    useAiAssist,
    type AiActionId,
    type AiClientSettings,
} from '@/composables/useAiAssist';
import { usePermissions } from '@/composables/usePermissions';

export type AiTargetKind = 'input' | 'textarea';

export type AiInlineTarget = {
    el: HTMLInputElement | HTMLTextAreaElement;
    kind: AiTargetKind;
    value: string;
    selectionStart: number;
    selectionEnd: number;
    selected: string;
    replaceSelection: boolean;
    rect: DOMRect;
};

export type AiChatTurn = {
    role: 'user' | 'assistant';
    content: string;
};

const SKIP_INPUT_TYPES = new Set([
    'password',
    'hidden',
    'file',
    'checkbox',
    'radio',
    'range',
    'color',
    'date',
    'datetime-local',
    'month',
    'week',
    'time',
    'submit',
    'button',
    'reset',
    'image',
]);

const open = ref(false);
const target = shallowRef<AiInlineTarget | null>(null);
const prompt = ref('');
const draft = ref('');
const turns = ref<AiChatTurn[]>([]);
const modelId = ref<string | null>(null);
const action = ref<string>('custom');
const phase = ref<'prompt' | 'result'>('prompt');
const lastUsage = ref<{
    total_tokens: number;
    duration_ms?: number;
    estimated_cost_usd?: number;
} | null>(null);

function isEligibleField(
    el: Element | null,
): el is HTMLInputElement | HTMLTextAreaElement {
    if (!el || !(el instanceof HTMLElement)) {
        return false;
    }

    if (el.closest('[data-ai-ignore]')) {
        return false;
    }

    if (el instanceof HTMLTextAreaElement) {
        return !el.disabled && !el.readOnly;
    }

    if (el instanceof HTMLInputElement) {
        const type = (el.type || 'text').toLowerCase();
        if (SKIP_INPUT_TYPES.has(type)) {
            return false;
        }
        return !el.disabled && !el.readOnly;
    }

    return false;
}

function captureTarget(
    el: HTMLInputElement | HTMLTextAreaElement,
): AiInlineTarget {
    const value = el.value ?? '';
    const start = el.selectionStart ?? 0;
    const end = el.selectionEnd ?? 0;
    const replaceSelection = end > start;
    const selected = replaceSelection ? value.slice(start, end) : value;

    return {
        el,
        kind: el instanceof HTMLTextAreaElement ? 'textarea' : 'input',
        value,
        selectionStart: start,
        selectionEnd: end,
        selected,
        replaceSelection,
        rect: el.getBoundingClientRect(),
    };
}

function applyToField(nextText: string): void {
    const t = target.value;
    if (!t) {
        return;
    }

    const el = t.el;
    const next = t.replaceSelection
        ? t.value.slice(0, t.selectionStart) +
          nextText +
          t.value.slice(t.selectionEnd)
        : nextText;

    const proto = Object.getOwnPropertyDescriptor(
        el instanceof HTMLTextAreaElement
            ? HTMLTextAreaElement.prototype
            : HTMLInputElement.prototype,
        'value',
    );

    proto?.set?.call(el, next);
    el.dispatchEvent(new Event('input', { bubbles: true }));
    el.dispatchEvent(new Event('change', { bubbles: true }));

    const caret = t.replaceSelection
        ? t.selectionStart + nextText.length
        : nextText.length;
    el.focus();
    try {
        el.setSelectionRange(caret, caret);
    } catch {
        // some input types may not support selection
    }
}

export function useAiInlineEdit() {
    const page = usePage();
    const { can } = usePermissions();
    const { assist, processing, error, settings, enabled } = useAiAssist();

    const aiSettings = computed(
        () =>
            (page.props.aiSettings as AiClientSettings | null | undefined) ??
            settings.value,
    );

    const available = computed(
        () =>
            can('ai.assist') &&
            Boolean(aiSettings.value?.enabled) &&
            Boolean(aiSettings.value?.field_edit_enabled !== false) &&
            (aiSettings.value?.models?.length ?? 0) > 0,
    );

    function openFor(el: HTMLInputElement | HTMLTextAreaElement): void {
        if (!available.value || !isEligibleField(el)) {
            return;
        }

        target.value = captureTarget(el);
        prompt.value = '';
        draft.value = '';
        turns.value = [];
        phase.value = 'prompt';
        lastUsage.value = null;
        modelId.value = aiSettings.value.default_model_id;
        const firstAction =
            aiSettings.value.actions.find((a) => a.id === 'auto')?.id ??
            aiSettings.value.actions.find((a) => a.id === 'improve')?.id ??
            aiSettings.value.actions.find((a) => a.id === 'custom')?.id ??
            aiSettings.value.actions[0]?.id ??
            'custom';
        action.value = firstAction;
        open.value = true;
    }

    function openFromActiveElement(): boolean {
        const el = document.activeElement;
        if (!isEligibleField(el)) {
            return false;
        }
        openFor(el);
        return true;
    }

    function close(): void {
        open.value = false;
        target.value = null;
        prompt.value = '';
        draft.value = '';
        turns.value = [];
        phase.value = 'prompt';
        lastUsage.value = null;
        error.value = null;
    }

    function reject(): void {
        close();
    }

    function accept(): void {
        if (!draft.value) {
            return;
        }
        applyToField(draft.value);
        close();
    }

    async function runPrompt(followUp = false): Promise<void> {
        const t = target.value;
        if (!t) {
            return;
        }

        const selectedAction = action.value || 'custom';
        const instruction = prompt.value.trim();

        if (selectedAction === 'custom' && !instruction) {
            error.value = 'Enter a short instruction.';
            return;
        }

        if (followUp && !instruction) {
            return;
        }

        const baseText =
            followUp && draft.value ? draft.value : t.selected || t.value;

        const textForModel =
            baseText.trim() !== ''
                ? baseText
                : selectedAction === 'custom' ||
                    selectedAction === 'describe' ||
                    selectedAction === 'auto'
                  ? ''
                  : '';

        if (
            !textForModel.trim() &&
            selectedAction !== 'custom' &&
            selectedAction !== 'describe' &&
            selectedAction !== 'auto'
        ) {
            error.value = 'Type or select some text first.';
            return;
        }

        const turnLabel =
            instruction ||
            aiSettings.value.actions.find((a) => a.id === selectedAction)
                ?.label ||
            selectedAction;

        turns.value = [...turns.value, { role: 'user', content: turnLabel }];

        try {
            const result = await assist({
                action: (followUp ? 'custom' : selectedAction) as AiActionId,
                text: textForModel,
                instruction: followUp
                    ? instruction
                    : instruction || undefined,
                modelId: modelId.value,
            });

            draft.value = result.text;
            lastUsage.value = {
                total_tokens: result.usage.total_tokens,
                duration_ms: result.duration_ms,
                estimated_cost_usd: result.estimated_cost_usd,
            };
            turns.value = [
                ...turns.value,
                { role: 'assistant', content: result.text },
            ];
            prompt.value = '';
            phase.value = 'result';
        } catch {
            turns.value = turns.value.slice(0, -1);
        }
    }

    return {
        open,
        target,
        prompt,
        draft,
        turns,
        modelId,
        action,
        phase,
        lastUsage,
        processing,
        error,
        settings: aiSettings,
        available,
        isEligibleField,
        openFor,
        openFromActiveElement,
        close,
        accept,
        reject,
        runPrompt,
    };
}
