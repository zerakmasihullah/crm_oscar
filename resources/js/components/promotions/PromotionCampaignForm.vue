<script setup lang="ts">
import { computed, ref } from 'vue';
import { ImageIcon, Users, CalendarClock, Sparkles } from '@lucide/vue';
import FileUploadField from '@/components/FileUploadField.vue';
import {
    MisInputField,
    MisSelectField,
    MisTextareaField,
} from '@/components/mis';
import PromotionEmailPreview from '@/components/promotions/PromotionEmailPreview.vue';
import { useTranslations } from '@/composables/useTranslations';

type TemplateOption = { value: string; label: string; hint?: string };

const props = defineProps<{
    form: {
        subject: string;
        content: string;
        image: File | null;
        remove_image?: boolean;
        template: string;
        recipient_type: string;
        status: string;
        scheduled_at: string;
        errors: Record<string, string>;
        processing: boolean;
    };
    templates: TemplateOption[];
    audience: { users: number; newsletter: number; both: number };
    existingImageUrl?: string | null;
    sentLocked?: boolean;
}>();

const emit = defineEmits<{
    'update:subject': [string];
    'update:content': [string];
    'update:template': [string];
    'update:recipient_type': [string];
    'update:status': [string];
    'update:scheduled_at': [string];
    'update:image': [File | null];
    'update:remove_image': [boolean];
}>();

const { t } = useTranslations();
const dragging = ref(false);

const starters = [
    {
        label: t('Weekend deal'),
        subject: 'Weekend special at Oscar’s',
        content:
            '<p>Hungry this weekend? Enjoy our pizza & kebab specials — order online for collection or delivery in Limerick.</p><p><strong>Order now on oscarlimerick.com</strong></p>',
    },
    {
        label: t('New on menu'),
        subject: 'Just added — try it tonight',
        content:
            '<p>We’ve added something new to the menu. Open the app or website and treat yourself tonight.</p>',
    },
    {
        label: t('Order ahead'),
        subject: 'Skip the queue — order ahead',
        content:
            '<p>Friday nights get busy. Order ahead for collection or local delivery and we’ll have it ready.</p>',
    },
];

const templateSwatch: Record<string, string> = {
    'classic-banner': 'linear-gradient(135deg,#EA7516,#f97316)',
    'discount-badge': 'linear-gradient(135deg,#dc2626,#EA7516)',
    'product-showcase': '#111827',
    'full-width-image': 'linear-gradient(180deg,#f4f4f5 40%,#EA7516)',
    'newsletter-style': 'linear-gradient(180deg,#f8fafc,#e2e8f0)',
    minimalist: '#ffffff',
    'modern-card': 'linear-gradient(135deg,#0f172a,#EA7516)',
    'split-layout': 'linear-gradient(90deg,#EA7516 50%,#f4f4f5 50%)',
    'product-grid': '#18181b',
    elegant: 'linear-gradient(135deg,#1c1917,#a8a29e)',
};

const audienceOptions = computed(() => [
    {
        value: 'users',
        label: t('Customers'),
        count: props.audience.users,
    },
    {
        value: 'newsletter',
        label: t('Newsletter'),
        count: props.audience.newsletter,
    },
    {
        value: 'both',
        label: t('Everyone'),
        count: props.audience.both,
    },
]);

const estimatedReach = computed(() => {
    const key = props.form.recipient_type as keyof typeof props.audience;
    return props.audience[key] ?? 0;
});

const existingFiles = computed(() => {
    if (!props.existingImageUrl || props.form.remove_image) return null;
    return [{ name: 'campaign.jpg', url: props.existingImageUrl }];
});

function applyStarter(starter: (typeof starters)[number]) {
    emit('update:subject', starter.subject);
    emit('update:content', starter.content);
}

function onImageDeferred(files: File[]) {
    const file = files[0] ?? null;
    emit('update:image', file);
    if (file) emit('update:remove_image', false);
}

function onImageRemoved() {
    emit('update:image', null);
    if (props.existingImageUrl) emit('update:remove_image', true);
}

function onDrop(event: DragEvent) {
    dragging.value = false;
    const file = event.dataTransfer?.files?.[0];
    if (!file || !file.type.startsWith('image/')) return;
    emit('update:image', file);
    emit('update:remove_image', false);
}
</script>

<template>
    <div class="grid gap-8 xl:grid-cols-[minmax(0,1fr)_380px] xl:items-start">
        <!-- Composer -->
        <div class="space-y-8">
            <!-- Message -->
            <div class="space-y-5">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div class="flex items-center gap-2 text-sm font-medium text-foreground">
                        <Sparkles class="size-4 text-orange-500" />
                        {{ t('Message') }}
                    </div>
                    <div class="flex flex-wrap gap-1.5">
                        <button
                            v-for="starter in starters"
                            :key="starter.label"
                            type="button"
                            class="rounded-full bg-muted/70 px-3 py-1 text-xs font-medium text-muted-foreground transition hover:bg-orange-500/10 hover:text-orange-600"
                            @click="applyStarter(starter)"
                        >
                            {{ starter.label }}
                        </button>
                    </div>
                </div>

                <MisInputField
                    id="subject"
                    :model-value="form.subject"
                    :label="t('Subject')"
                    :error="form.errors.subject"
                    :placeholder="t('Friday pizza deal — order ahead')"
                    required
                    @update:model-value="emit('update:subject', String($event))"
                />

                <MisTextareaField
                    id="content"
                    :model-value="form.content"
                    :label="t('Body')"
                    :error="form.errors.content"
                    :rows="7"
                    :placeholder="t('Keep it short. One offer, one clear next step.')"
                    required
                    @update:model-value="emit('update:content', String($event))"
                />
            </div>

            <!-- Image -->
            <div class="space-y-3">
                <div class="flex items-center gap-2 text-sm font-medium text-foreground">
                    <ImageIcon class="size-4 text-orange-500" />
                    {{ t('Hero image') }}
                    <span class="font-normal text-muted-foreground">({{ t('optional') }})</span>
                </div>

                <div
                    class="relative overflow-hidden rounded-2xl border border-dashed transition"
                    :class="
                        dragging
                            ? 'border-orange-500 bg-orange-500/5'
                            : 'border-border/80 bg-muted/20 hover:border-orange-500/40'
                    "
                    @dragenter.prevent="dragging = true"
                    @dragover.prevent="dragging = true"
                    @dragleave.prevent="dragging = false"
                    @drop.prevent="onDrop"
                >
                    <FileUploadField
                        mode="deferred"
                        accept="image/*"
                        preview="image"
                        :max-bytes="5 * 1024 * 1024"
                        :existing="existingFiles"
                        :hint="t('PNG or JPG · up to 5MB · drag & drop')"
                        :error="form.errors.image"
                        class="border-0 bg-transparent p-1"
                        @update:deferred="onImageDeferred"
                        @remove="onImageRemoved"
                    />
                </div>
            </div>

            <!-- Layout -->
            <div class="space-y-3">
                <p class="text-sm font-medium">{{ t('Layout') }}</p>
                <div class="grid grid-cols-2 gap-2 sm:grid-cols-3 lg:grid-cols-5">
                    <button
                        v-for="tpl in templates"
                        :key="tpl.value"
                        type="button"
                        class="group flex flex-col overflow-hidden rounded-xl border text-left transition"
                        :class="
                            form.template === tpl.value
                                ? 'border-orange-500 ring-2 ring-orange-500/20'
                                : 'border-border/70 hover:border-orange-500/40'
                        "
                        @click="emit('update:template', tpl.value)"
                    >
                        <span
                            class="block h-10 w-full"
                            :style="{
                                background:
                                    templateSwatch[tpl.value] ||
                                    'linear-gradient(135deg,#EA7516,#f97316)',
                            }"
                        />
                        <span class="space-y-0.5 px-2.5 py-2">
                            <span class="block truncate text-xs font-semibold">{{ tpl.label }}</span>
                            <span class="block truncate text-[10px] text-muted-foreground">
                                {{ tpl.hint }}
                            </span>
                        </span>
                    </button>
                </div>
                <p v-if="form.errors.template" class="text-sm text-destructive">
                    {{ form.errors.template }}
                </p>
            </div>

            <!-- Audience -->
            <div class="space-y-3">
                <div class="flex items-center justify-between gap-3">
                    <div class="flex items-center gap-2 text-sm font-medium">
                        <Users class="size-4 text-orange-500" />
                        {{ t('Audience') }}
                    </div>
                    <span class="text-xs font-medium tabular-nums text-muted-foreground">
                        ~{{ estimatedReach.toLocaleString() }} {{ t('people') }}
                    </span>
                </div>

                <div class="grid grid-cols-3 gap-2">
                    <button
                        v-for="opt in audienceOptions"
                        :key="opt.value"
                        type="button"
                        class="rounded-xl border px-3 py-3 text-left transition"
                        :class="
                            form.recipient_type === opt.value
                                ? 'border-orange-500 bg-orange-500/[0.06] ring-2 ring-orange-500/15'
                                : 'border-border/70 hover:border-orange-500/35'
                        "
                        @click="emit('update:recipient_type', opt.value)"
                    >
                        <span class="block text-sm font-semibold">{{ opt.label }}</span>
                        <span class="mt-1 block text-lg font-semibold tabular-nums tracking-tight">
                            {{ opt.count.toLocaleString() }}
                        </span>
                    </button>
                </div>
                <p v-if="form.errors.recipient_type" class="text-sm text-destructive">
                    {{ form.errors.recipient_type }}
                </p>
            </div>

            <!-- When -->
            <div class="space-y-3">
                <div class="flex items-center gap-2 text-sm font-medium">
                    <CalendarClock class="size-4 text-orange-500" />
                    {{ t('When') }}
                </div>
                <div class="grid gap-4 sm:grid-cols-2">
                    <MisSelectField
                        id="status"
                        :model-value="form.status"
                        :label="t('Status')"
                        :disabled="sentLocked"
                        :options="
                            sentLocked
                                ? [{ value: 'sent', label: t('Sent') }]
                                : [
                                      { value: 'draft', label: t('Draft') },
                                      { value: 'scheduled', label: t('Schedule') },
                                  ]
                        "
                        :error="form.errors.status"
                        required
                        @update:model-value="emit('update:status', String($event))"
                    />
                    <MisInputField
                        v-if="form.status === 'scheduled'"
                        id="scheduled_at"
                        :model-value="form.scheduled_at"
                        type="datetime-local"
                        :label="t('Send at')"
                        :error="form.errors.scheduled_at"
                        @update:model-value="emit('update:scheduled_at', String($event))"
                    />
                </div>
            </div>
        </div>

        <!-- Live design -->
        <aside class="xl:sticky xl:top-4">
            <p class="mb-3 text-[11px] font-semibold tracking-[0.16em] text-muted-foreground uppercase">
                {{ t('Final design') }}
            </p>
            <PromotionEmailPreview
                :subject="form.subject"
                :content="form.content"
                :template="form.template"
                :image-file="form.image"
                :existing-image-url="existingImageUrl"
                :remove-image="form.remove_image === true"
            />
        </aside>
    </div>
</template>
