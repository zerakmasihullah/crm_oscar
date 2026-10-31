<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import {
    CalendarDays,
    CircleAlert,
    Coins,
    Plus,
    Sparkles,
    Trash2,
} from '@lucide/vue';
import { computed, reactive, ref, watch } from 'vue';
import { toast } from 'vue-sonner';
import InputError from '@/components/InputError.vue';
import {
    MisDataTable,
    MisPagination,
    MisSection,
    MisStatCard,
    MisStatGrid,
    MisStatusBadge,
} from '@/components/mis';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useCalendar } from '@/composables/useCalendar';
import { useTranslations } from '@/composables/useTranslations';
import { formatNumber, type Paginated } from '@/lib/format';
import SettingsPage from '@/components/settings/SettingsPage.vue';
import SettingsPanel from '@/components/settings/SettingsPanel.vue';
import SettingsSwitch from '@/components/settings/SettingsSwitch.vue';
import SettingsToggleRow from '@/components/settings/SettingsToggleRow.vue';
import AiDataAccessMatrix, {
    type AiResourceActions,
} from '@/components/settings/AiDataAccessMatrix.vue';
import AiTabs from '@/pages/settings/AiTabs.vue';

type AiModelForm = {
    id?: string | null;
    label: string;
    provider: string;
    model: string;
    base_url: string;
    api_key: string;
    api_key_set?: boolean;
};

type FeatureKey =
    | 'grammar'
    | 'improve'
    | 'shorten'
    | 'expand'
    | 'describe'
    | 'auto'
    | 'custom'
    | 'fill_form';

type AiSettingsPayload = {
    enabled: boolean;
    chat_enabled: boolean;
    chat_allow_create: boolean;
    chat_allow_delete: boolean;
    chat_allow_analytics: boolean;
    langfuse_enabled: boolean;
    langfuse_public_key_set: boolean;
    langfuse_secret_key_set: boolean;
    langfuse_base_url: string;
    field_edit_enabled: boolean;
    default_model_id: string | null;
    models: Array<{
        id: string;
        label: string;
        provider: string;
        model: string;
        base_url: string | null;
        api_key_set: boolean;
    }>;
    features: Record<FeatureKey, boolean>;
    failover_enabled: boolean;
    base_system_prompt: string;
    action_prompts: Record<FeatureKey, string>;
    action_prompt_defaults: Record<FeatureKey, string>;
    max_input_chars: number;
    max_output_tokens: number;
    daily_token_budget: number;
    panel_size: string;
    panel_sizes: Array<{
        id: string;
        label: string;
        width: number;
        prompt_rows: number;
        result_max_h: number;
    }>;
    resource_access: Record<string, AiResourceActions>;
    resources: Array<{
        key: string;
        label: string;
        table: string | null;
        model: string | null;
    }>;
    env_default: boolean;
    limits_ceiling: {
        max_models: number;
        max_input_chars: number;
        max_output_tokens: number;
        daily_token_budget: number;
        max_instruction_chars: number;
        max_base_system_prompt_chars: number;
        max_action_system_prompt_chars: number;
    };
};

type AiLogRecord = {
    id: number;
    status: string;
    action: string;
    provider: string | null;
    model_name: string | null;
    instruction_preview: string | null;
    input_preview: string | null;
    output_preview: string | null;
    prompt_tokens: number;
    completion_tokens: number;
    total_tokens: number;
    estimated_cost_usd: number | string | null;
    duration_ms: number | null;
    error: string | null;
    logged_at: string;
    user?: { id: number; name: string; email: string } | null;
};

type AiSummary = {
    requests: number;
    success: number;
    failed: number;
    tokens: number;
    cost_usd: number;
    avg_duration_ms: number;
    today_tokens: number;
    today_cost_usd: number;
    month_tokens: number;
    month_cost_usd: number;
};

const props = withDefaults(
    defineProps<{
        tab?: 'general' | 'prompts' | 'assist' | 'tasks' | 'data' | 'logs';
        settings?: AiSettingsPayload | null;
        providers: Array<{
            id: string;
            label: string;
            default_base_url: string | null;
            suggested_model?: string | null;
            requires_base_url?: boolean;
            driver?: string;
        }>;
        actions: Array<{ id: string; label: string }>;
        canManage: boolean;
        canViewLogs: boolean;
        logs?: Paginated<AiLogRecord>;
        summary?: AiSummary | null;
        byAction?: Array<{
            action: string;
            requests: number;
            tokens: number;
            cost_usd: number;
        }>;
        statuses?: string[];
        filters?: {
            search?: string | null;
            status?: string | null;
            action?: string | null;
        };
    }>(),
    {
        tab: 'general',
        settings: null,
        logs: undefined,
        summary: null,
        byAction: () => [],
        statuses: () => [],
        filters: () => ({}),
    },
);

const { t } = useTranslations();
const calendar = useCalendar();

const search = ref(props.filters?.search ?? '');
const statusFilter = ref(props.filters?.status ?? '');
const actionFilter = ref(props.filters?.action ?? '');
const detailLog = ref<AiLogRecord | null>(null);
const selected = ref<Array<string | number>>([]);
let searchTimer: ReturnType<typeof setTimeout> | null = null;

const maxModels = computed(
    () => props.settings?.limits_ceiling.max_models ?? 3,
);

function asAccessBool(value: unknown, fallback: boolean): boolean {
    if (value === true || value === 1 || value === '1' || value === 'true') {
        return true;
    }

    if (value === false || value === 0 || value === '0' || value === 'false') {
        return false;
    }

    return fallback;
}

function defaultResourceAccess(
    access?: Record<string, AiResourceActions | boolean> | null,
    resources?: AiSettingsPayload['resources'] | null,
): Record<string, AiResourceActions> {
    const map: Record<string, AiResourceActions> = {};

    const normalize = (
        value: AiResourceActions | boolean | undefined,
    ): AiResourceActions => {
        if (value === false || value === 0 || value === '0' || value === 'false') {
            return { view: false, create: false, edit: false, delete: false };
        }

        if (value === true || value === 1 || value === '1' || value === 'true' || value == null) {
            return { view: true, create: true, edit: true, delete: true };
        }

        const view = asAccessBool(value.view, true);

        return {
            view,
            create: view && asAccessBool(value.create, true),
            edit: view && asAccessBool(value.edit, true),
            delete: view && asAccessBool(value.delete, true),
        };
    };

    for (const resource of resources ?? []) {
        map[resource.key] = normalize(access?.[resource.key]);
    }

    if (access) {
        for (const [key, value] of Object.entries(access)) {
            if (!(key in map)) {
                map[key] = normalize(value);
            }
        }
    }

    return map;
}

function mapModels(
    models: AiSettingsPayload['models'] | undefined,
): AiModelForm[] {
    return (models ?? []).map((m): AiModelForm => ({
        id: m.id,
        label: m.label,
        provider: m.provider,
        model: m.model,
        base_url: m.base_url ?? '',
        api_key: '',
        api_key_set: m.api_key_set,
    }));
}

function defaultFeatures(
    features?: Partial<Record<FeatureKey, boolean>> | null,
): Record<FeatureKey, boolean> {
    return {
        grammar: Boolean(features?.grammar),
        improve: Boolean(features?.improve),
        shorten: Boolean(features?.shorten),
        expand: Boolean(features?.expand),
        describe: Boolean(features?.describe),
        auto: Boolean(features?.auto ?? true),
        custom: Boolean(features?.custom ?? true),
        fill_form: Boolean(features?.fill_form ?? true),
    };
}

function defaultActionPrompts(
    prompts?: Partial<Record<FeatureKey, string>> | null,
): Record<FeatureKey, string> {
    return {
        grammar: prompts?.grammar ?? '',
        improve: prompts?.improve ?? '',
        shorten: prompts?.shorten ?? '',
        expand: prompts?.expand ?? '',
        describe: prompts?.describe ?? '',
        auto: prompts?.auto ?? '',
        custom: prompts?.custom ?? '',
        fill_form: prompts?.fill_form ?? '',
    };
}

/** Local editable state — avoids Inertia form nested-array input bugs. */
const state = reactive({
    enabled: Boolean(props.settings?.enabled),
    chat_enabled: Boolean(props.settings?.chat_enabled ?? true),
    chat_allow_create: Boolean(props.settings?.chat_allow_create ?? false),
    chat_allow_delete: Boolean(props.settings?.chat_allow_delete ?? false),
    chat_allow_analytics: Boolean(
        props.settings?.chat_allow_analytics ?? false,
    ),
    langfuse_enabled: Boolean(props.settings?.langfuse_enabled ?? false),
    langfuse_public_key: '',
    langfuse_secret_key: '',
    langfuse_base_url:
        props.settings?.langfuse_base_url ?? 'https://cloud.langfuse.com',
    field_edit_enabled: Boolean(props.settings?.field_edit_enabled ?? true),
    default_model_id: (props.settings?.default_model_id ?? null) as
        string | null,
    models: mapModels(props.settings?.models),
    features: defaultFeatures(props.settings?.features),
    failover_enabled: Boolean(props.settings?.failover_enabled ?? true),
    base_system_prompt: props.settings?.base_system_prompt ?? '',
    action_prompts: defaultActionPrompts(props.settings?.action_prompts),
    max_input_chars: props.settings?.max_input_chars ?? 2500,
    max_output_tokens: props.settings?.max_output_tokens ?? 400,
    daily_token_budget: props.settings?.daily_token_budget ?? 80_000,
    panel_size: props.settings?.panel_size ?? 'comfortable',
    resource_access: defaultResourceAccess(
        props.settings?.resource_access,
        props.settings?.resources,
    ),
});

const form = useForm({
    enabled: state.enabled,
    chat_enabled: state.chat_enabled,
    chat_allow_create: state.chat_allow_create,
    chat_allow_delete: state.chat_allow_delete,
    chat_allow_analytics: state.chat_allow_analytics,
    langfuse_enabled: state.langfuse_enabled,
    langfuse_public_key: '',
    langfuse_secret_key: '',
    langfuse_base_url: state.langfuse_base_url,
    field_edit_enabled: state.field_edit_enabled,
    default_model_id: state.default_model_id,
    models: [] as Array<Record<string, unknown>>,
    features: { ...state.features },
    failover_enabled: state.failover_enabled,
    base_system_prompt: state.base_system_prompt,
    action_prompts: { ...state.action_prompts },
    max_input_chars: state.max_input_chars,
    max_output_tokens: state.max_output_tokens,
    daily_token_budget: state.daily_token_budget,
    panel_size: state.panel_size,
    resource_access: { ...state.resource_access },
});

const panelSizes = computed(() => props.settings?.panel_sizes ?? []);

const promptActions = computed(() =>
    props.actions.filter((action) => action.id in state.action_prompts),
);

const featureActions = computed(() =>
    props.actions.filter(
        (action) =>
            action.id !== 'custom' &&
            action.id !== 'fill_form' &&
            action.id in state.features,
    ),
);

function providerMeta(providerId: string) {
    return props.providers.find((p) => p.id === providerId);
}

function needsBaseUrl(providerId: string): boolean {
    const meta = providerMeta(providerId);
    return Boolean(meta?.requires_base_url);
}

function suggestedModel(providerId: string): string {
    return providerMeta(providerId)?.suggested_model || '';
}

function onProviderChange(model: AiModelForm): void {
    const suggested = suggestedModel(model.provider);
    if (suggested && (!model.model || model.model === '')) {
        model.model = suggested;
    }
    if (!needsBaseUrl(model.provider) && !model.base_url) {
        // keep empty; server uses provider default
    }
}

const hasUsableModel = computed(() =>
    state.models.some((m) => m.api_key_set || m.api_key.trim() !== ''),
);

const filtersActive = computed(() =>
    Boolean(statusFilter.value || actionFilter.value),
);

const aiLogColumns = computed(() => [
    { key: 'user', label: t('User'), sortable: true },
    { key: 'action', label: t('Action'), sortable: true },
    {
        key: 'status',
        label: t('Status'),
        sortable: true,
        width: '7.5rem',
        type: 'status' as const,
    },
    { key: 'model', label: t('Model'), truncate: true },
    {
        key: 'tokens',
        label: t('Tokens'),
        align: 'end' as const,
        mono: true,
        width: '7rem',
    },
    {
        key: 'cost',
        label: t('Cost'),
        align: 'end' as const,
        mono: true,
        width: '7rem',
    },
    {
        key: 'when',
        label: t('When'),
        sortable: true,
        width: '10rem',
    },
]);

const aiLogRows = computed(() =>
    (props.logs?.data ?? []).map((log) => ({
        ...log,
        user: log.user?.name || t('System'),
        action: log.action,
        status: log.status,
        model: log.model_name || '—',
        tokens: log.total_tokens,
        cost: formatCost(log.estimated_cost_usd),
        when: formatDateTime(log.logged_at),
    })),
);

watch(
    () => props.filters,
    (filters) => {
        search.value = filters?.search ?? '';
        statusFilter.value = filters?.status ?? '';
        actionFilter.value = filters?.action ?? '';
    },
);

watch(search, (value) => {
    if (props.tab !== 'logs') {
        return;
    }

    if (searchTimer) {
        clearTimeout(searchTimer);
    }

    searchTimer = setTimeout(() => {
        const next = value.trim();
        const current = (props.filters?.search ?? '').trim();
        if (next === current) {
            return;
        }
        applyFilters();
    }, 300);
});

const isConfigTab = computed(
    () =>
        props.tab === 'general' ||
        props.tab === 'prompts' ||
        props.tab === 'assist' ||
        props.tab === 'tasks' ||
        props.tab === 'data',
);

const catalogResources = computed(() => props.settings?.resources ?? []);

const pageTitle = computed(() => t('AI'));

const langfuseTestBusy = ref(false);
const langfuseTestMessage = ref<string | null>(null);
const langfuseTestOk = ref<boolean | null>(null);

function csrfToken(): string {
    return (
        document
            .querySelector('meta[name="csrf-token"]')
            ?.getAttribute('content') || ''
    );
}

async function testLangfuseConnection(): Promise<void> {
    langfuseTestBusy.value = true;
    langfuseTestMessage.value = null;
    langfuseTestOk.value = null;

    try {
        const response = await fetch('/settings/ai/langfuse/test', {
            method: 'POST',
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken(),
                'X-Requested-With': 'XMLHttpRequest',
            },
            credentials: 'same-origin',
            body: '{}',
        });
        const data = (await response.json()) as {
            ok?: boolean;
            message?: string;
        };
        langfuseTestOk.value = Boolean(data.ok);
        langfuseTestMessage.value =
            data.message ||
            (data.ok
                ? t('Connected to Langfuse.')
                : t('Langfuse connection failed.'));
    } catch {
        langfuseTestOk.value = false;
        langfuseTestMessage.value = t(
            'Could not reach the Langfuse test endpoint.',
        );
    } finally {
        langfuseTestBusy.value = false;
    }
}

function addModel(): void {
    if (state.models.length >= maxModels.value) {
        return;
    }

    const firstProvider = props.providers[0]?.id ?? 'openai';
    state.models.push({
        id: null,
        label: '',
        provider: firstProvider,
        model: suggestedModel(firstProvider) || 'gpt-4o-mini',
        base_url: '',
        api_key: '',
        api_key_set: false,
    });
}

function removeModel(index: number): void {
    const removed = state.models[index];
    state.models.splice(index, 1);

    if (removed?.id && state.default_model_id === removed.id) {
        state.default_model_id = state.models.find((m) => m.id)?.id ?? null;
    }
}

function setDefault(id: string): void {
    state.default_model_id = id;
}

function resetActionPrompt(id: string): void {
    const key = id as FeatureKey;
    if (!(key in state.action_prompts)) {
        return;
    }
    state.action_prompts[key] = '';
}

function actionPromptPlaceholder(id: string): string {
    const key = id as FeatureKey;
    return props.settings?.action_prompt_defaults?.[key] ?? '';
}

function syncFromProps(): void {
    if (!props.settings) {
        return;
    }

    state.enabled = Boolean(props.settings.enabled);
    state.chat_enabled = Boolean(props.settings.chat_enabled ?? true);
    state.chat_allow_create = Boolean(
        props.settings.chat_allow_create ?? false,
    );
    state.chat_allow_delete = Boolean(
        props.settings.chat_allow_delete ?? false,
    );
    state.chat_allow_analytics = Boolean(
        props.settings.chat_allow_analytics ?? false,
    );
    state.langfuse_enabled = Boolean(props.settings.langfuse_enabled ?? false);
    state.langfuse_public_key = '';
    state.langfuse_secret_key = '';
    state.langfuse_base_url =
        props.settings.langfuse_base_url ?? 'https://cloud.langfuse.com';
    state.field_edit_enabled = Boolean(
        props.settings.field_edit_enabled ?? true,
    );
    state.default_model_id = props.settings.default_model_id;
    state.models = mapModels(props.settings.models);
    state.features = defaultFeatures(props.settings.features);
    state.failover_enabled = Boolean(props.settings.failover_enabled);
    state.base_system_prompt = props.settings.base_system_prompt ?? '';
    state.action_prompts = defaultActionPrompts(props.settings.action_prompts);
    state.max_input_chars = props.settings.max_input_chars;
    state.max_output_tokens = props.settings.max_output_tokens;
    state.daily_token_budget = props.settings.daily_token_budget;
    state.panel_size = props.settings.panel_size ?? 'comfortable';
    state.resource_access = defaultResourceAccess(
        props.settings.resource_access,
        props.settings.resources,
    );
}

watch(
    () => props.settings,
    (settings, previous) => {
        if (!settings || settings === previous) {
            return;
        }

        syncFromProps();
    },
);

const saving = ref(false);

function buildResourceAccessPayload(): Record<string, AiResourceActions> {
    const resources =
        catalogResources.value.length > 0
            ? catalogResources.value
            : Object.keys(state.resource_access).map((key) => ({
                  key,
                  label: key,
                  table: null,
                  model: null,
              }));

    return Object.fromEntries(
        resources.map((resource) => {
            const actions = state.resource_access[resource.key];
            const view = asAccessBool(actions?.view, true);

            return [
                resource.key,
                {
                    view,
                    create: view && asAccessBool(actions?.create, true),
                    edit: view && asAccessBool(actions?.edit, true),
                    delete: view && asAccessBool(actions?.delete, true),
                },
            ];
        }),
    );
}

function submit(): void {
    if (!props.settings || saving.value) {
        return;
    }

    // Data access tab: save only the matrix so other tab fields can't block it.
    if (props.tab === 'data') {
        saving.value = true;
        router.put(
            '/settings/ai/resource-access',
            { resource_access: buildResourceAccessPayload() },
            {
                preserveScroll: true,
                preserveState: false,
                onError: (errors) => {
                    form.clearErrors();
                    form.setError(errors as Record<string, string>);
                    const first = Object.values(errors)[0];
                    if (typeof first === 'string' && first !== '') {
                        toast.error(first);
                    } else {
                        toast.error(t('Could not save AI settings.'));
                    }
                },
                onFinish: () => {
                    saving.value = false;
                },
            },
        );
        return;
    }

    state.features.custom = true;

    const resourceAccess = buildResourceAccessPayload();

    saving.value = true;

    router.put(
        '/settings/ai',
        {
            enabled: state.enabled,
            chat_enabled: state.chat_enabled,
            chat_allow_create: state.chat_allow_create,
            chat_allow_delete: state.chat_allow_delete,
            chat_allow_analytics: state.chat_allow_analytics,
            langfuse_enabled: state.langfuse_enabled,
            langfuse_public_key:
                state.langfuse_public_key.trim() !== ''
                    ? state.langfuse_public_key.trim()
                    : '',
            langfuse_secret_key:
                state.langfuse_secret_key.trim() !== ''
                    ? state.langfuse_secret_key.trim()
                    : '',
            langfuse_base_url: state.langfuse_base_url.trim(),
            field_edit_enabled: state.field_edit_enabled,
            default_model_id: state.default_model_id,
            features: { ...state.features, custom: true },
            failover_enabled: state.failover_enabled,
            base_system_prompt: state.base_system_prompt,
            action_prompts: { ...state.action_prompts },
            max_input_chars: state.max_input_chars,
            max_output_tokens: state.max_output_tokens,
            daily_token_budget: state.daily_token_budget,
            panel_size: state.panel_size,
            return_tab: props.tab,
            resource_access: resourceAccess,
            models: state.models.map((m) => ({
                id: m.id || undefined,
                label: m.label,
                provider: m.provider,
                model: m.model,
                base_url: m.base_url || null,
                api_key: m.api_key.trim() !== '' ? m.api_key : undefined,
            })),
        },
        {
            preserveScroll: true,
            preserveState: false,
            onError: (errors) => {
                form.clearErrors();
                form.setError(errors as Record<string, string>);
                const first = Object.values(errors)[0];
                if (typeof first === 'string' && first !== '') {
                    toast.error(first);
                } else {
                    toast.error(t('Could not save AI settings.'));
                }
            },
            onFinish: () => {
                saving.value = false;
            },
        },
    );
}

function applyFilters(): void {
    router.get(
        '/settings/ai/logs',
        {
            search: search.value || undefined,
            status: statusFilter.value || undefined,
            action: actionFilter.value || undefined,
        },
        { preserveState: true, replace: true },
    );
}

function clearFilters(): void {
    statusFilter.value = '';
    actionFilter.value = '';
    applyFilters();
}

function openAiDetail(payload: { row: Record<string, unknown> }): void {
    const id = Number(payload.row.id);
    detailLog.value = props.logs?.data.find((log) => log.id === id) ?? null;
}

function formatDateTime(value: string): string {
    return calendar.formatDateTime(value) || value;
}

function formatCost(value: number | string | null | undefined): string {
    const amount = typeof value === 'string' ? Number(value) : (value ?? 0);

    if (!Number.isFinite(amount)) {
        return '—';
    }

    return `$${formatNumber(amount, {
        minimumFractionDigits: 2,
        maximumFractionDigits: 4,
    })}`;
}

function formatDuration(ms: number | null | undefined): string {
    if (ms == null) {
        return '—';
    }

    return `${formatNumber(ms)} ms`;
}

function statusLabel(value: string): string {
    if (value === 'success') {
        return t('Success');
    }

    if (value === 'failed') {
        return t('Failed');
    }

    return value;
}

function actionLabel(id: string): string {
    const match = props.actions.find((item) => item.id === id);

    return match ? t(match.label) : id;
}

function previewText(value: string | null | undefined): string {
    if (!value) {
        return '—';
    }

    return value.trim() || '—';
}

function logSnippet(log: AiLogRecord): string | null {
    if (log.error) {
        return log.error;
    }

    return (
        log.instruction_preview?.trim() ||
        log.input_preview?.trim() ||
        log.output_preview?.trim() ||
        null
    );
}

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Settings', href: '/settings/appearance' },
            { title: 'AI', href: '/settings/ai' },
        ],
    },
});
</script>

<template>
    <Head :title="pageTitle" />

    <SettingsPage>
        <template #tabs>
            <AiTabs
                :active="tab"
                :can-manage="canManage"
                :can-view-logs="canViewLogs"
            />
        </template>
        <template #actions>
            <SettingsSwitch
                v-if="isConfigTab && canManage && settings"
                :model-value="state.enabled"
                @update:model-value="(value) => (state.enabled = value)"
            />
        </template>

        <form
            v-if="isConfigTab && canManage && settings"
            class="space-y-6"
            @submit.prevent="submit"
        >
            <template v-if="tab === 'general'">
                <SettingsPanel class="space-y-8">
                    <section class="space-y-5">
                        <MisSection
                            :title="t('Models')"
                            :description="
                                t('Models and providers used for AI features.')
                            "
                        >
                            <template #actions>
                                <Button
                                    type="button"
                                    variant="outline"
                                    size="sm"
                                    :disabled="state.models.length >= maxModels"
                                    @click="addModel"
                                >
                                    <Plus class="size-4" />
                                    {{ t('Add model') }}
                                </Button>
                            </template>
                        </MisSection>

                        <div
                            v-if="state.models.length === 0"
                            class="flex flex-col items-center justify-center gap-3 rounded-xl border border-dashed border-border/80 bg-muted/20 px-4 py-12"
                        >
                            <p class="text-sm text-muted-foreground">
                                {{ t('No models yet') }}
                            </p>
                            <Button type="button" size="sm" @click="addModel">
                                <Plus class="size-4" />
                                {{ t('Add model') }}
                            </Button>
                        </div>

                        <div class="space-y-4">
                            <div
                                v-for="(model, index) in state.models"
                                :key="model.id || `new-${index}`"
                                class="space-y-4 rounded-xl border border-border/80 bg-background/40 p-4 sm:p-5"
                            >
                                <div
                                    class="flex items-center justify-between gap-2"
                                >
                                    <div class="flex items-center gap-2">
                                        <button
                                            v-if="model.id"
                                            type="button"
                                            class="rounded-md px-2 py-1 text-[11px] font-semibold"
                                            :class="
                                                state.default_model_id ===
                                                model.id
                                                    ? 'bg-zinc-900 text-white dark:bg-white dark:text-zinc-900'
                                                    : 'bg-muted text-muted-foreground hover:text-foreground'
                                            "
                                            @click="
                                                setDefault(String(model.id))
                                            "
                                        >
                                            {{
                                                state.default_model_id ===
                                                model.id
                                                    ? t('Default')
                                                    : t('Use as default')
                                            }}
                                        </button>
                                        <span
                                            v-else
                                            class="text-xs font-medium text-muted-foreground"
                                        >
                                            {{ t('Unsaved') }}
                                        </span>
                                        <span
                                            v-if="model.api_key_set"
                                            class="text-[11px] text-muted-foreground"
                                        >
                                            {{ t('Key saved') }}
                                        </span>
                                    </div>
                                    <Button
                                        type="button"
                                        variant="ghost"
                                        size="icon"
                                        class="size-8 text-muted-foreground hover:text-destructive"
                                        :aria-label="t('Remove')"
                                        @click="removeModel(index)"
                                    >
                                        <Trash2 class="size-4" />
                                    </Button>
                                </div>

                                <div class="grid gap-4 sm:grid-cols-2">
                                    <div class="space-y-1.5">
                                        <Label :for="`ai-label-${index}`">{{
                                            t('Name')
                                        }}</Label>
                                        <input
                                            :id="`ai-label-${index}`"
                                            v-model="model.label"
                                            type="text"
                                            autocomplete="off"
                                            :placeholder="t('Fast')"
                                            class="h-9 w-full rounded-md border border-input bg-transparent px-3 text-sm outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                                        />
                                    </div>

                                    <div class="space-y-1.5">
                                        <Label :for="`ai-provider-${index}`">
                                            {{ t('Provider') }}
                                        </Label>
                                        <select
                                            :id="`ai-provider-${index}`"
                                            v-model="model.provider"
                                            class="h-9 w-full rounded-md border border-input bg-transparent px-3 text-sm outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                                            @change="onProviderChange(model)"
                                        >
                                            <option
                                                v-for="provider in providers"
                                                :key="provider.id"
                                                :value="provider.id"
                                            >
                                                {{ provider.label }}
                                            </option>
                                        </select>
                                    </div>

                                    <div class="space-y-1.5">
                                        <Label :for="`ai-model-name-${index}`">
                                            {{ t('Model') }}
                                        </Label>
                                        <input
                                            :id="`ai-model-name-${index}`"
                                            v-model="model.model"
                                            type="text"
                                            autocomplete="off"
                                            :placeholder="
                                                suggestedModel(
                                                    model.provider,
                                                ) || 'model-name'
                                            "
                                            class="h-9 w-full rounded-md border border-input bg-transparent px-3 text-sm outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                                        />
                                    </div>

                                    <div class="space-y-1.5">
                                        <Label :for="`ai-api-key-${index}`">
                                            {{ t('API key') }}
                                        </Label>
                                        <input
                                            :id="`ai-api-key-${index}`"
                                            v-model="model.api_key"
                                            type="password"
                                            autocomplete="new-password"
                                            :placeholder="
                                                model.api_key_set
                                                    ? '••••••••'
                                                    : 'sk-…'
                                            "
                                            class="h-9 w-full rounded-md border border-input bg-transparent px-3 text-sm outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                                        />
                                    </div>

                                    <div class="space-y-1.5 sm:col-span-2">
                                        <Label :for="`ai-base-${index}`">
                                            {{
                                                needsBaseUrl(model.provider)
                                                    ? t('Base URL')
                                                    : t('Base URL (optional)')
                                            }}
                                        </Label>
                                        <input
                                            :id="`ai-base-${index}`"
                                            v-model="model.base_url"
                                            type="url"
                                            autocomplete="off"
                                            :placeholder="
                                                providerMeta(model.provider)
                                                    ?.default_base_url ||
                                                'https://api.example.com/v1'
                                            "
                                            class="h-9 w-full rounded-md border border-input bg-transparent px-3 text-sm outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                                        />
                                        <p
                                            v-if="needsBaseUrl(model.provider)"
                                            class="text-[11px] text-muted-foreground"
                                        >
                                            {{
                                                t(
                                                    'Required for Custom / Other endpoints.',
                                                )
                                            }}
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </section>

                    <section class="space-y-4 border-t border-border/60 pt-8">
                        <MisSection
                            :title="t('Ctrl+K panel size')"
                            :description="
                                t(
                                    'How large the Ctrl+K assist panel should open.',
                                )
                            "
                        />
                        <div class="flex flex-wrap gap-2.5">
                            <button
                                v-for="size in panelSizes"
                                :key="size.id"
                                type="button"
                                class="rounded-xl border px-3.5 py-2.5 text-sm font-medium transition"
                                :class="
                                    state.panel_size === size.id
                                        ? 'border-zinc-900 bg-zinc-900 text-white dark:border-white dark:bg-white dark:text-zinc-900'
                                        : 'border-border bg-background text-muted-foreground hover:text-foreground'
                                "
                                @click="state.panel_size = size.id"
                            >
                                {{ t(size.label) }}
                            </button>
                        </div>
                    </section>

                    <section class="space-y-4 border-t border-border/60 pt-8">
                        <MisSection
                            :title="t('Limits')"
                            :description="
                                t(
                                    'Safety caps for prompts, responses, and daily usage.',
                                )
                            "
                        />
                        <div class="grid gap-5 sm:grid-cols-3">
                            <div class="space-y-1.5">
                                <Label for="max_input_chars">{{
                                    t('Max characters')
                                }}</Label>
                                <Input
                                    id="max_input_chars"
                                    v-model.number="state.max_input_chars"
                                    type="number"
                                    min="100"
                                    :max="
                                        settings.limits_ceiling.max_input_chars
                                    "
                                />
                                <InputError
                                    :message="form.errors.max_input_chars"
                                />
                            </div>
                            <div class="space-y-1.5">
                                <Label for="max_output_tokens">{{
                                    t('Max tokens')
                                }}</Label>
                                <Input
                                    id="max_output_tokens"
                                    v-model.number="state.max_output_tokens"
                                    type="number"
                                    min="50"
                                    :max="
                                        settings.limits_ceiling
                                            .max_output_tokens
                                    "
                                />
                                <InputError
                                    :message="form.errors.max_output_tokens"
                                />
                            </div>
                            <div class="space-y-1.5">
                                <Label for="daily_token_budget">{{
                                    t('Daily budget')
                                }}</Label>
                                <Input
                                    id="daily_token_budget"
                                    v-model.number="state.daily_token_budget"
                                    type="number"
                                    min="1000"
                                    :max="
                                        settings.limits_ceiling
                                            .daily_token_budget
                                    "
                                />
                                <InputError
                                    :message="form.errors.daily_token_budget"
                                />
                            </div>
                        </div>
                    </section>
                </SettingsPanel>
            </template>

            <template v-else-if="tab === 'prompts'">
                <div class="space-y-5">
                    <SettingsPanel class="space-y-5">
                        <MisSection
                            :title="t('Base instructions (optional)')"
                            :description="
                                t(
                                    'Shared guidance applied before every AI action.',
                                )
                            "
                        />
                        <div class="space-y-1.5">
                            <textarea
                                id="ai-base-system"
                                v-model="state.base_system_prompt"
                                rows="4"
                                :maxlength="
                                    settings.limits_ceiling
                                        .max_base_system_prompt_chars
                                "
                                :placeholder="
                                    t(
                                        'Applied to every action — e.g. keep a formal school tone, never invent IDs.',
                                    )
                                "
                                class="min-h-[5.5rem] w-full resize-y rounded-xl border border-input bg-transparent px-3.5 py-3 text-sm outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                            />
                            <InputError
                                :message="form.errors.base_system_prompt"
                            />
                        </div>
                    </SettingsPanel>

                    <SettingsPanel class="space-y-6">
                        <MisSection
                            :title="t('Action prompts')"
                            :description="
                                t(
                                    'Customize the system prompt used for each writing action.',
                                )
                            "
                        />

                        <div class="space-y-6">
                            <div
                                v-for="action in promptActions"
                                :key="action.id"
                                class="space-y-2.5 border-b border-border/60 pb-6 last:border-b-0 last:pb-0"
                            >
                                <div
                                    class="flex flex-wrap items-center justify-between gap-2"
                                >
                                    <Label :for="`ai-prompt-${action.id}`">
                                        {{ t(action.label) }}
                                    </Label>
                                    <button
                                        type="button"
                                        class="text-[11px] font-medium text-muted-foreground hover:text-foreground"
                                        @click="resetActionPrompt(action.id)"
                                    >
                                        {{ t('Use default') }}
                                    </button>
                                </div>
                                <textarea
                                    :id="`ai-prompt-${action.id}`"
                                    v-model="
                                        state.action_prompts[
                                            action.id as FeatureKey
                                        ]
                                    "
                                    rows="3"
                                    :maxlength="
                                        settings.limits_ceiling
                                            .max_action_system_prompt_chars
                                    "
                                    :placeholder="
                                        actionPromptPlaceholder(action.id)
                                    "
                                    class="min-h-[4.5rem] w-full resize-y rounded-xl border border-input bg-transparent px-3.5 py-3 text-sm outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                                />
                            </div>
                        </div>
                    </SettingsPanel>
                </div>
            </template>

            <template v-else-if="tab === 'assist'">
                <div class="space-y-5">
                    <SettingsPanel class="space-y-1">
                        <MisSection
                            :title="t('Assist features')"
                            :description="
                                t(
                                    'Choose which writing and form helpers are available in the app.',
                                )
                            "
                        >
                            <div class="divide-y divide-border/60">
                                <SettingsToggleRow
                                    :title="t('Field editing')"
                                    :description="
                                        t(
                                            'Enable Ctrl+K writing tools on text fields across the app.',
                                        )
                                    "
                                    :model-value="state.field_edit_enabled"
                                    :disabled="!state.enabled"
                                    @update:model-value="
                                        (value) =>
                                            (state.field_edit_enabled = value)
                                    "
                                />
                                <SettingsToggleRow
                                    :title="t('Fill form')"
                                    :description="
                                        t(
                                            'Describe a record and map it onto labelled form fields.',
                                        )
                                    "
                                    :model-value="state.features.fill_form"
                                    :disabled="!state.enabled"
                                    @update:model-value="
                                        (value) =>
                                            (state.features.fill_form = value)
                                    "
                                />
                                <SettingsToggleRow
                                    :title="t('Provider failover')"
                                    :description="
                                        t(
                                            'Try the next configured model when a provider fails.',
                                        )
                                    "
                                    :model-value="state.failover_enabled"
                                    @update:model-value="
                                        (value) =>
                                            (state.failover_enabled = value)
                                    "
                                />
                            </div>
                        </MisSection>
                    </SettingsPanel>

                    <SettingsPanel class="space-y-1">
                        <MisSection
                            :title="t('Writing actions')"
                            :description="
                                t(
                                    'Toggle individual Ctrl+K writing tools such as grammar, shorten, and expand.',
                                )
                            "
                        >
                            <div class="divide-y divide-border/60">
                                <SettingsToggleRow
                                    v-for="action in featureActions"
                                    :key="action.id"
                                    :title="t(action.label)"
                                    :model-value="
                                        state.features[action.id as FeatureKey]
                                    "
                                    :disabled="!state.field_edit_enabled"
                                    @update:model-value="
                                        (value) =>
                                            (state.features[
                                                action.id as FeatureKey
                                            ] = value)
                                    "
                                />
                            </div>
                        </MisSection>
                    </SettingsPanel>
                </div>
            </template>

            <template v-else-if="tab === 'data'">
                <div class="space-y-5">
                    <SettingsPanel>
                        <AiDataAccessMatrix
                            :model-value="state.resource_access"
                            :resources="catalogResources"
                            :disabled="!state.enabled"
                            @update:model-value="
                                (value) => (state.resource_access = value)
                            "
                        />
                    </SettingsPanel>
                </div>
            </template>

            <template v-else-if="tab === 'tasks'">
                <div class="space-y-5">
                    <SettingsPanel class="space-y-1">
                        <MisSection
                            :title="t('Task permissions')"
                            :description="
                                t(
                                    'Control what the AI agent can do when managing tasks and records.',
                                )
                            "
                        >
                            <div class="divide-y divide-border/60">
                                <SettingsToggleRow
                                    :title="t('Task management AI')"
                                    :description="
                                        t(
                                            'Turn on the AI agent for task workflows and record operations.',
                                        )
                                    "
                                    :model-value="state.chat_enabled"
                                    :disabled="!state.enabled"
                                    @update:model-value="
                                        (value) => (state.chat_enabled = value)
                                    "
                                />
                                <SettingsToggleRow
                                    :title="t('Allow creating & updating records')"
                                    :description="
                                        t(
                                            'Let the agent create and edit records you already have permission to manage.',
                                        )
                                    "
                                    :model-value="state.chat_allow_create"
                                    :disabled="!state.enabled || !state.chat_enabled"
                                    @update:model-value="
                                        (value) => (state.chat_allow_create = value)
                                    "
                                />
                                <SettingsToggleRow
                                    :title="t('Allow deleting records')"
                                    :description="
                                        t(
                                            'Let the agent permanently remove records when you ask it to.',
                                        )
                                    "
                                    :model-value="state.chat_allow_delete"
                                    :disabled="!state.enabled || !state.chat_enabled"
                                    @update:model-value="
                                        (value) => (state.chat_allow_delete = value)
                                    "
                                />
                                <SettingsToggleRow
                                    :title="t('Allow analytics (read-only SQL)')"
                                    :description="
                                        t(
                                            'Allow read-only analytics queries for charts and summaries.',
                                        )
                                    "
                                    :model-value="state.chat_allow_analytics"
                                    :disabled="!state.enabled || !state.chat_enabled"
                                    @update:model-value="
                                        (value) =>
                                            (state.chat_allow_analytics = value)
                                    "
                                />
                            </div>
                        </MisSection>
                    </SettingsPanel>

                    <SettingsPanel class="space-y-6">
                        <MisSection
                            :title="t('Observability (Langfuse)')"
                            :description="
                                t(
                                    'Trace AI requests for debugging and cost visibility.',
                                )
                            "
                        >
                            <SettingsToggleRow
                                :title="t('Enable Langfuse')"
                                :description="
                                    t(
                                        'Send traces for chat, assist, and agent tools to Langfuse.',
                                    )
                                "
                                :model-value="state.langfuse_enabled"
                                :disabled="!state.enabled"
                                class="rounded-xl border border-border/70 bg-muted/20 px-4 py-4"
                                @update:model-value="
                                    (value) => (state.langfuse_enabled = value)
                                "
                            />

                            <div class="mt-5 grid gap-5 sm:grid-cols-2">
                                <div class="grid gap-1.5">
                                    <Label for="langfuse_public_key">{{
                                        t('Public key')
                                    }}</Label>
                                    <Input
                                        id="langfuse_public_key"
                                        v-model="state.langfuse_public_key"
                                        type="text"
                                        autocomplete="off"
                                        :placeholder="
                                            props.settings?.langfuse_public_key_set
                                                ? t('Saved — enter to replace')
                                                : 'pk-lf-…'
                                        "
                                        :disabled="!state.langfuse_enabled"
                                    />
                                    <InputError
                                        :message="form.errors.langfuse_public_key"
                                    />
                                </div>
                                <div class="grid gap-1.5">
                                    <Label for="langfuse_secret_key">{{
                                        t('Secret key')
                                    }}</Label>
                                    <Input
                                        id="langfuse_secret_key"
                                        v-model="state.langfuse_secret_key"
                                        type="password"
                                        autocomplete="new-password"
                                        :placeholder="
                                            props.settings?.langfuse_secret_key_set
                                                ? t('Saved — enter to replace')
                                                : 'sk-lf-…'
                                        "
                                        :disabled="!state.langfuse_enabled"
                                    />
                                    <InputError
                                        :message="form.errors.langfuse_secret_key"
                                    />
                                </div>
                                <div class="grid gap-1.5 sm:col-span-2">
                                    <Label for="langfuse_base_url">{{
                                        t('Base URL')
                                    }}</Label>
                                    <Input
                                        id="langfuse_base_url"
                                        v-model="state.langfuse_base_url"
                                        type="url"
                                        autocomplete="off"
                                        placeholder="https://us.cloud.langfuse.com"
                                        :disabled="!state.langfuse_enabled"
                                    />
                                    <InputError
                                        :message="form.errors.langfuse_base_url"
                                    />
                                    <p class="text-[12px] leading-relaxed text-muted-foreground">
                                        {{
                                            t(
                                                'Trace General Chat, Ctrl+K assist, and agent tools. Open the same Langfuse region as your Base URL (US projects use https://us.cloud.langfuse.com). Leave secret blank to keep the existing key.',
                                            )
                                        }}
                                    </p>
                                </div>
                            </div>

                            <div class="flex flex-wrap items-center gap-3 pt-5">
                                <Button
                                    type="button"
                                    variant="outline"
                                    :disabled="
                                        !state.enabled ||
                                        !state.langfuse_enabled ||
                                        langfuseTestBusy ||
                                        !(
                                            props.settings
                                                ?.langfuse_public_key_set &&
                                            props.settings?.langfuse_secret_key_set
                                        )
                                    "
                                    @click="testLangfuseConnection"
                                >
                                    {{
                                        langfuseTestBusy
                                            ? t('Testing…')
                                            : t('Test connection')
                                    }}
                                </Button>
                                <p
                                    v-if="langfuseTestMessage"
                                    class="text-xs"
                                    :class="
                                        langfuseTestOk
                                            ? 'text-[color-mix(in_srgb,var(--brand-accent)_55%,#0a0a0a)] dark:text-[var(--brand-accent)]'
                                            : 'text-destructive'
                                    "
                                >
                                    {{ langfuseTestMessage }}
                                </p>
                            </div>
                        </MisSection>
                    </SettingsPanel>
                </div>

            </template>

            <div
                class="flex justify-end border-t border-border/50 pt-4"
            >
                <Button type="submit" :disabled="saving || form.processing">
                    {{ t('Save') }}
                </Button>
            </div>
        </form>

        <template v-else-if="tab === 'logs' && canViewLogs && logs">
            <div class="space-y-4">
                <MisStatGrid :columns="4">
                    <MisStatCard
                        :label="t('Today')"
                        :value="formatNumber(summary?.today_tokens ?? 0)"
                        :hint="formatCost(summary?.today_cost_usd ?? 0)"
                        :icon="CalendarDays"
                        tone="info"
                    />
                    <MisStatCard
                        :label="t('This month')"
                        :value="formatNumber(summary?.month_tokens ?? 0)"
                        :hint="formatCost(summary?.month_cost_usd ?? 0)"
                        :icon="Coins"
                    />
                    <MisStatCard
                        :label="t('Requests')"
                        :value="formatNumber(summary?.requests ?? 0)"
                        :hint="
                            `${formatNumber(summary?.success ?? 0)} ${t('ok')} · ${formatNumber(summary?.failed ?? 0)} ${t('failed')}`
                        "
                        :icon="Sparkles"
                        tone="success"
                    />
                    <MisStatCard
                        :label="t('Failed')"
                        :value="formatNumber(summary?.failed ?? 0)"
                        :icon="CircleAlert"
                        :tone="(summary?.failed ?? 0) > 0 ? 'danger' : 'muted'"
                    />
                </MisStatGrid>

                <MisDataTable
                    v-model:selected="selected"
                    v-model:search="search"
                    :columns="aiLogColumns"
                    :rows="aiLogRows"
                    row-key="id"
                    toolbar
                    searchable
                    selectable
                    selection-toggle
                    filterable
                    :filters-active="filtersActive"
                    column-toggle
                    clickable
                    export-filename="ai-logs"
                    :search-placeholder="t('User, model, prompt…')"
                    :empty-label="t('No AI usage yet')"
                    @row-click="openAiDetail"
                >
                    <template #filters="{ close }">
                        <div class="space-y-3">
                            <div class="space-y-1.5">
                                <p class="text-xs font-medium text-muted-foreground">
                                    {{ t('Status') }}
                                </p>
                                <div class="flex flex-wrap gap-1.5">
                                    <button
                                        type="button"
                                        class="kit-chip-toggle"
                                        :class="statusFilter === '' ? 'is-on' : ''"
                                        @click="statusFilter = ''"
                                    >
                                        {{ t('All') }}
                                    </button>
                                    <button
                                        v-for="item in statuses || []"
                                        :key="item"
                                        type="button"
                                        class="kit-chip-toggle"
                                        :class="statusFilter === item ? 'is-on' : ''"
                                        @click="statusFilter = item"
                                    >
                                        {{ statusLabel(item) }}
                                    </button>
                                </div>
                            </div>
                            <div class="space-y-1.5">
                                <p class="text-xs font-medium text-muted-foreground">
                                    {{ t('Action') }}
                                </p>
                                <div class="flex flex-wrap gap-1.5">
                                    <button
                                        type="button"
                                        class="kit-chip-toggle"
                                        :class="actionFilter === '' ? 'is-on' : ''"
                                        @click="actionFilter = ''"
                                    >
                                        {{ t('All') }}
                                    </button>
                                    <button
                                        v-for="item in actions"
                                        :key="item.id"
                                        type="button"
                                        class="kit-chip-toggle"
                                        :class="actionFilter === item.id ? 'is-on' : ''"
                                        @click="actionFilter = item.id"
                                    >
                                        {{ t(item.label) }}
                                    </button>
                                </div>
                            </div>
                            <div class="flex items-center justify-end gap-2 pt-1">
                                <Button
                                    v-if="filtersActive"
                                    type="button"
                                    variant="ghost"
                                    size="sm"
                                    class="h-8"
                                    @click="clearFilters"
                                >
                                    {{ t('Clear') }}
                                </Button>
                                <Button
                                    type="button"
                                    size="sm"
                                    class="h-8"
                                    @click="applyFilters(); close()"
                                >
                                    {{ t('Done') }}
                                </Button>
                            </div>
                        </div>
                    </template>

                    <template #cell-action="{ row }">
                        <Badge variant="secondary">
                            {{ actionLabel(String(row.action ?? '')) }}
                        </Badge>
                    </template>

                    <template #cell-status="{ row }">
                        <MisStatusBadge
                            :status="String(row.status ?? '')"
                            :label="statusLabel(String(row.status ?? ''))"
                        />
                    </template>

                    <template #cell-tokens="{ value }">
                        <span class="tabular-nums text-muted-foreground">
                            {{ formatNumber(Number(value ?? 0)) }}
                        </span>
                    </template>

                    <template #footer>
                        <MisPagination :pagination="logs" />
                    </template>
                </MisDataTable>
            </div>

            <Dialog
                :open="detailLog !== null"
                @update:open="(open) => !open && (detailLog = null)"
            >
                <DialogContent class="sm:max-w-3xl">
                    <DialogHeader>
                        <DialogTitle>
                            {{ detailLog?.user?.name || t('System') }}
                        </DialogTitle>
                        <DialogDescription>
                            <template v-if="detailLog">
                                {{ actionLabel(detailLog.action) }}
                                ·
                                {{ formatDateTime(detailLog.logged_at) }}
                            </template>
                        </DialogDescription>
                    </DialogHeader>

                    <div v-if="detailLog" class="space-y-4 text-sm">
                        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                            <div class="min-w-0">
                                <p class="text-xs font-medium tracking-wide text-muted-foreground uppercase">
                                    {{ t('User') }}
                                </p>
                                <p class="mt-0.5">
                                    {{ detailLog.user?.name || t('System') }}
                                </p>
                                <p
                                    v-if="detailLog.user?.email"
                                    class="truncate text-muted-foreground"
                                >
                                    {{ detailLog.user.email }}
                                </p>
                            </div>
                            <div class="min-w-0">
                                <p class="text-xs font-medium tracking-wide text-muted-foreground uppercase">
                                    {{ t('Model') }}
                                </p>
                                <p class="mt-0.5">
                                    {{ detailLog.model_name || '—' }}
                                </p>
                                <p
                                    v-if="detailLog.provider"
                                    class="text-muted-foreground"
                                >
                                    {{ detailLog.provider }}
                                </p>
                            </div>
                            <div class="min-w-0">
                                <p class="text-xs font-medium tracking-wide text-muted-foreground uppercase">
                                    {{ t('Tokens') }}
                                </p>
                                <p class="mt-0.5 tabular-nums">
                                    {{ formatNumber(detailLog.total_tokens) }}
                                </p>
                                <p class="text-muted-foreground tabular-nums">
                                    {{ formatNumber(detailLog.prompt_tokens) }}
                                    /
                                    {{ formatNumber(detailLog.completion_tokens) }}
                                </p>
                            </div>
                            <div class="min-w-0">
                                <p class="text-xs font-medium tracking-wide text-muted-foreground uppercase">
                                    {{ t('Cost') }}
                                </p>
                                <p class="mt-0.5 tabular-nums">
                                    {{ formatCost(detailLog.estimated_cost_usd) }}
                                </p>
                                <p class="text-muted-foreground tabular-nums">
                                    {{ formatDuration(detailLog.duration_ms) }}
                                </p>
                            </div>
                        </div>

                        <div
                            v-if="detailLog.error"
                            class="rounded-md border border-destructive/20 bg-destructive/5 px-3 py-2 text-destructive"
                        >
                            <p class="text-xs font-medium tracking-wide uppercase">
                                {{ t('Error') }}
                            </p>
                            <p class="mt-1 break-words whitespace-pre-wrap">
                                {{ detailLog.error }}
                            </p>
                        </div>

                        <div class="grid gap-3 lg:grid-cols-3">
                            <div class="min-w-0">
                                <p class="text-xs font-medium tracking-wide text-muted-foreground uppercase">
                                    {{ t('Instruction') }}
                                </p>
                                <p class="mt-1 break-words whitespace-pre-wrap text-muted-foreground">
                                    {{ previewText(detailLog.instruction_preview) }}
                                </p>
                            </div>
                            <div class="min-w-0">
                                <p class="text-xs font-medium tracking-wide text-muted-foreground uppercase">
                                    {{ t('Input') }}
                                </p>
                                <p class="mt-1 break-words whitespace-pre-wrap text-muted-foreground">
                                    {{ previewText(detailLog.input_preview) }}
                                </p>
                            </div>
                            <div class="min-w-0">
                                <p class="text-xs font-medium tracking-wide text-muted-foreground uppercase">
                                    {{ t('Output') }}
                                </p>
                                <p class="mt-1 break-words whitespace-pre-wrap text-muted-foreground">
                                    {{ previewText(detailLog.output_preview) }}
                                </p>
                            </div>
                        </div>
                    </div>
                </DialogContent>
            </Dialog>
        </template>

    </SettingsPage>
</template>
