import { usePage } from '@inertiajs/vue3';
import { computed, ref, shallowRef } from 'vue';
import {
    useAiAssist,
    type AiClientSettings,
} from '@/composables/useAiAssist';
import { usePermissions } from '@/composables/usePermissions';

export type AiFormField = {
    id: string;
    name: string | null;
    type: string;
    label: string;
    options?: Array<{ value: string; label: string }>;
    el: HTMLInputElement | HTMLTextAreaElement | HTMLSelectElement;
};

export type AiFormFillPreview = {
    id: string;
    label: string;
    value: string;
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
const root = shallowRef<HTMLElement | null>(null);
const fields = shallowRef<AiFormField[]>([]);
const prompt = ref('');
const modelId = ref<string | null>(null);
const phase = ref<'prompt' | 'result'>('prompt');
const preview = ref<AiFormFillPreview[]>([]);
const lastUsage = ref<{
    total_tokens: number;
    duration_ms?: number;
    estimated_cost_usd?: number;
} | null>(null);

function labelFor(el: HTMLElement): string {
    const id = el.id;
    if (id) {
        const byFor = document.querySelector(`label[for="${CSS.escape(id)}"]`);
        if (byFor?.textContent?.trim()) {
            return byFor.textContent.trim().replace(/\s+/g, ' ');
        }
    }

    const wrapping = el.closest('label');
    if (wrapping?.textContent?.trim()) {
        return wrapping.textContent.trim().replace(/\s+/g, ' ').slice(0, 120);
    }

    const aria = el.getAttribute('aria-label')?.trim();
    if (aria) {
        return aria;
    }

    const placeholder = (el as HTMLInputElement).placeholder?.trim();
    if (placeholder) {
        return placeholder;
    }

    return el.getAttribute('name') || el.id || 'field';
}

function selectOptions(
    el: HTMLSelectElement,
): Array<{ value: string; label: string }> {
    return Array.from(el.options)
        .filter((opt) => opt.value !== '')
        .map((opt) => ({
            value: opt.value,
            label: (opt.textContent || opt.value).trim(),
        }));
}

function isFillableControl(
    el: Element,
): el is HTMLInputElement | HTMLTextAreaElement | HTMLSelectElement {
    if (!(el instanceof HTMLElement)) {
        return false;
    }

    if (el.closest('[data-ai-ignore]')) {
        return false;
    }

    if (el instanceof HTMLSelectElement) {
        return !el.disabled;
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

function fieldKey(
    el: HTMLInputElement | HTMLTextAreaElement | HTMLSelectElement,
    index: number,
): string {
    const dataKey = el.getAttribute('data-ai-field')?.trim();
    if (dataKey) {
        return dataKey;
    }
    if (el.id?.trim()) {
        return el.id.trim();
    }
    const name = el.getAttribute('name')?.trim();
    if (name) {
        // Strip PHP array suffixes like roles[] → roles
        return name.replace(/\[\]$/, '');
    }
    return `field_${index}`;
}

export function findFormRoot(from?: Element | null): HTMLElement | null {
    const start =
        from ??
        (document.activeElement instanceof Element
            ? document.activeElement
            : null);

    if (start) {
        const scoped =
            start.closest<HTMLElement>('form') ||
            start.closest<HTMLElement>('[data-ai-form]');
        if (scoped) {
            return scoped;
        }
    }

    const marked = document.querySelector<HTMLElement>('[data-ai-form]');
    if (marked) {
        return marked;
    }

    const forms = Array.from(document.querySelectorAll('form')).filter(
        (form) => !form.closest('[data-ai-ignore]'),
    );

    if (forms.length === 1) {
        return forms[0] as HTMLElement;
    }

    // Prefer the largest visible form with fillable fields.
    let best: HTMLElement | null = null;
    let bestCount = 0;
    for (const form of forms) {
        const count = collectFields(form as HTMLElement).length;
        if (count > bestCount) {
            best = form as HTMLElement;
            bestCount = count;
        }
    }

    return bestCount >= 2 ? best : null;
}

export function collectFields(
    formRoot: HTMLElement,
    maxFields = 40,
): AiFormField[] {
    const nodes = formRoot.querySelectorAll('input, textarea, select');
    const out: AiFormField[] = [];
    const seen = new Set<string>();

    nodes.forEach((node, index) => {
        if (!isFillableControl(node)) {
            return;
        }

        const id = fieldKey(node, index);
        if (seen.has(id)) {
            return;
        }
        seen.add(id);

        const type =
            node instanceof HTMLSelectElement
                ? 'select'
                : node instanceof HTMLTextAreaElement
                  ? 'textarea'
                  : (node.type || 'text').toLowerCase();

        const field: AiFormField = {
            id,
            name: node.getAttribute('name'),
            type,
            label: labelFor(node),
            el: node,
        };

        if (node instanceof HTMLSelectElement) {
            field.options = selectOptions(node);
        }

        out.push(field);
    });

    return out.slice(0, maxFields);
}

function applyValue(
    el: HTMLInputElement | HTMLTextAreaElement | HTMLSelectElement,
    value: string,
): void {
    if (el instanceof HTMLSelectElement) {
        const match = Array.from(el.options).find(
            (opt) =>
                opt.value === value ||
                opt.textContent?.trim().toLowerCase() === value.toLowerCase(),
        );
        el.value = match ? match.value : value;
        el.dispatchEvent(new Event('input', { bubbles: true }));
        el.dispatchEvent(new Event('change', { bubbles: true }));
        return;
    }

    const proto = Object.getOwnPropertyDescriptor(
        el instanceof HTMLTextAreaElement
            ? HTMLTextAreaElement.prototype
            : HTMLInputElement.prototype,
        'value',
    );
    proto?.set?.call(el, value);
    el.dispatchEvent(new Event('input', { bubbles: true }));
    el.dispatchEvent(new Event('change', { bubbles: true }));
}

export function useAiFormFill() {
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
            Boolean(aiSettings.value?.form_fill_enabled) &&
            Boolean(aiSettings.value?.enabled) &&
            (aiSettings.value?.models?.length ?? 0) > 0,
    );

    function openFor(formRoot?: HTMLElement | null): boolean {
        if (!available.value) {
            return false;
        }

        const found = formRoot ?? findFormRoot();
        if (!found) {
            error.value = 'No form with fillable fields was found on this page.';
            return false;
        }

        const collected = collectFields(
            found,
            aiSettings.value.max_form_fields ?? 40,
        );
        if (collected.length < 1) {
            error.value = 'No fillable fields were found in this form.';
            return false;
        }

        root.value = found;
        fields.value = collected;
        prompt.value = '';
        preview.value = [];
        phase.value = 'prompt';
        lastUsage.value = null;
        modelId.value = aiSettings.value.default_model_id;
        error.value = null;
        open.value = true;
        return true;
    }

    function openFromActiveElement(): boolean {
        const el = document.activeElement;
        const formRoot =
            el instanceof Element
                ? el.closest<HTMLElement>('form') ||
                  el.closest<HTMLElement>('[data-ai-form]')
                : null;
        return openFor(formRoot ?? findFormRoot());
    }

    function close(): void {
        open.value = false;
        root.value = null;
        fields.value = [];
        prompt.value = '';
        preview.value = [];
        phase.value = 'prompt';
        lastUsage.value = null;
        error.value = null;
    }

    function reject(): void {
        close();
    }

    function accept(): void {
        if (preview.value.length === 0) {
            return;
        }

        const byId = new Map(fields.value.map((f) => [f.id, f]));
        for (const row of preview.value) {
            const field = byId.get(row.id);
            if (!field) {
                continue;
            }
            applyValue(field.el, row.value);
        }

        close();
    }

    async function run(): Promise<void> {
        const instruction = prompt.value.trim();
        if (!instruction) {
            error.value = 'Describe the person or details to fill.';
            return;
        }

        if (fields.value.length === 0) {
            error.value = 'No fillable fields were found.';
            return;
        }

        const schema = {
            fields: fields.value.map((f) => ({
                id: f.id,
                label: f.label,
                type: f.type,
                ...(f.name ? { name: f.name } : {}),
                ...(f.options?.length ? { options: f.options } : {}),
            })),
        };

        try {
            const result = await assist({
                action: 'fill_form',
                text: JSON.stringify(schema),
                instruction,
                modelId: modelId.value,
            });

            const mapped = JSON.parse(result.text) as Record<string, string>;
            const byId = new Map(fields.value.map((f) => [f.id, f]));
            const next: AiFormFillPreview[] = [];

            for (const [id, value] of Object.entries(mapped)) {
                const field = byId.get(id);
                if (!field || typeof value !== 'string' || !value.trim()) {
                    continue;
                }
                next.push({
                    id,
                    label: field.label,
                    value: value.trim(),
                });
            }

            if (next.length === 0) {
                error.value =
                    'AI could not map your description to these fields. Try being more specific.';
                return;
            }

            preview.value = next;
            lastUsage.value = {
                total_tokens: result.usage.total_tokens,
                duration_ms: result.duration_ms,
                estimated_cost_usd: result.estimated_cost_usd,
            };
            phase.value = 'result';
        } catch {
            // error already set by useAiAssist
        }
    }

    return {
        open,
        root,
        fields,
        prompt,
        modelId,
        phase,
        preview,
        lastUsage,
        processing,
        error,
        settings: aiSettings,
        available,
        enabled,
        openFor,
        openFromActiveElement,
        close,
        accept,
        reject,
        run,
        findFormRoot,
        collectFields,
    };
}
