<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { computed } from 'vue';

defineEmits<{
    navigate: [];
}>();

const props = defineProps<{
    content: string;
    tone?: 'user' | 'assistant' | 'system';
}>();

type Segment =
    | { type: 'text'; value: string }
    | { type: 'link'; label: string; href: string };

function toAppPath(href: string): string {
    const trimmed = href.trim();
    if (!trimmed) {
        return '';
    }

    if (trimmed.startsWith('/') && !trimmed.startsWith('//')) {
        return trimmed;
    }

    try {
        const url = new URL(trimmed, window.location.origin);
        if (url.protocol === 'http:' || url.protocol === 'https:') {
            return `${url.pathname}${url.search}${url.hash}` || '/';
        }
    } catch {
        // fall through
    }

    if (/^[a-z0-9][a-z0-9\-_/]*$/i.test(trimmed)) {
        return `/${trimmed}`;
    }

    return trimmed;
}

function isSafeAppPath(href: string): boolean {
    return href.startsWith('/') && !href.startsWith('//');
}

const segments = computed<Segment[]>(() => {
    const source = props.content ?? '';
    const parts: Segment[] = [];
    const pattern = /\[([^\]]+)\]\(([^)\s]+)\)/g;
    let lastIndex = 0;
    let match: RegExpExecArray | null;

    while ((match = pattern.exec(source)) !== null) {
        if (match.index > lastIndex) {
            parts.push({
                type: 'text',
                value: source.slice(lastIndex, match.index),
            });
        }

        const label = match[1] ?? '';
        const href = toAppPath(match[2] ?? '');

        if (label && href && isSafeAppPath(href)) {
            parts.push({
                type: 'link',
                label,
                href,
            });
        } else {
            parts.push({ type: 'text', value: match[0] });
        }

        lastIndex = match.index + match[0].length;
    }

    if (lastIndex < source.length) {
        parts.push({ type: 'text', value: source.slice(lastIndex) });
    }

    if (parts.length === 0) {
        parts.push({ type: 'text', value: source });
    }

    return parts;
});

const linkClass = computed(() =>
    props.tone === 'user'
        ? 'underline underline-offset-2 decoration-white/70 hover:decoration-white font-medium'
        : 'font-medium text-foreground underline underline-offset-2 decoration-foreground/40 hover:decoration-foreground',
);
</script>

<template>
    <span class="whitespace-pre-wrap break-words">
        <template v-for="(segment, index) in segments" :key="index">
            <template v-if="segment.type === 'text'">{{ segment.value }}</template>
            <Link
                v-else
                :href="segment.href"
                :class="linkClass"
                @click="$emit('navigate')"
            >
                {{ segment.label }}
            </Link>
        </template>
    </span>
</template>
