<script setup lang="ts">
import { Head, usePage } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, watch } from 'vue';
import {
    absoluteUrl,
    pickDescription,
    type SeoJsonLd,
    type SharedSeo,
} from '@/lib/seo';

export type DocumentSeo = {
    title?: string | null;
    description?: string | null;
    image?: string | null;
    image_alt?: string | null;
    url?: string | null;
    path?: string | null;
    type?: string | null;
    robots?: string | null;
    site_name_only?: boolean | null;
    json_ld?: SeoJsonLd | null;
};

const props = withDefaults(
    defineProps<{
        title?: string | null;
        description?: string | null;
        image?: string | null;
        imageAlt?: string | null;
        path?: string | null;
        type?: 'website' | 'article' | 'profile' | null;
        robots?: string | null;
        jsonLd?: SeoJsonLd | null;
        siteNameOnly?: boolean;
    }>(),
    {
        siteNameOnly: false,
    },
);

const page = usePage();

const shared = computed(
    () =>
        (page.props.seo as SharedSeo | undefined) ?? {
            app_url: '',
            site_name: '',
            default_description: '',
            default_image: '/favicon.svg',
            locale: 'en',
            alternate_locales: [],
            keywords: '',
        },
);

const documentSeo = computed(() => (page.props.documentSeo as DocumentSeo | undefined) ?? null);

const siteNameOnly = computed(
    () => props.siteNameOnly || Boolean(documentSeo.value?.site_name_only),
);

const displayTitle = computed(
    () => props.title?.trim() || documentSeo.value?.title?.trim() || shared.value.site_name,
);

const resolvedTitle = computed(() => {
    if (siteNameOnly.value) {
        return '';
    }

    const title = props.title?.trim() || documentSeo.value?.title?.trim();

    return title || undefined;
});

const resolvedDescription = computed(() =>
    pickDescription(props.description, documentSeo.value?.description, shared.value.default_description),
);

const canonical = computed(() => {
    if (documentSeo.value?.url) {
        return documentSeo.value.url;
    }

    if (props.path) {
        return absoluteUrl(props.path, shared.value.app_url);
    }

    if (documentSeo.value?.path) {
        return absoluteUrl(documentSeo.value.path, shared.value.app_url);
    }

    const path = page.url.split('?')[0] || '/';

    return absoluteUrl(path, shared.value.app_url);
});

const ogType = computed(() => props.type || documentSeo.value?.type || 'website');

const ogImage = computed(() =>
    absoluteUrl(props.image || documentSeo.value?.image || shared.value.default_image, shared.value.app_url),
);

const ogImageAlt = computed(
    () => props.imageAlt || documentSeo.value?.image_alt || displayTitle.value || shared.value.site_name,
);

const ogLocale = computed(() => (shared.value.locale || 'en').replace('-', '_'));

const robots = computed(() => props.robots || documentSeo.value?.robots || null);

const jsonLdScripts = computed(() => {
    const source = props.jsonLd ?? documentSeo.value?.json_ld;

    if (!source) {
        return [] as string[];
    }

    const blocks = Array.isArray(source) ? source : [source];

    return blocks.map((block) => JSON.stringify(block).replace(/</g, '\\u003c'));
});

watch(
    jsonLdScripts,
    (payloads) => {
        if (typeof document === 'undefined') {
            return;
        }

        document.querySelectorAll('script[data-seo-jsonld]').forEach((el) => el.remove());

        payloads.forEach((payload, index) => {
            const el = document.createElement('script');
            el.type = 'application/ld+json';
            el.textContent = payload;
            el.setAttribute('data-seo-jsonld', String(index));
            document.head.appendChild(el);
        });
    },
    { immediate: true },
);

onBeforeUnmount(() => {
    if (typeof document === 'undefined') {
        return;
    }

    document.querySelectorAll('script[data-seo-jsonld]').forEach((el) => el.remove());
});
</script>

<template>
    <Head :title="resolvedTitle">
        <meta
            v-if="resolvedDescription"
            head-key="description"
            name="description"
            :content="resolvedDescription"
        />
        <meta v-if="shared.keywords" head-key="keywords" name="keywords" :content="shared.keywords" />
        <meta v-if="robots" head-key="robots" name="robots" :content="robots" />
        <link head-key="canonical" rel="canonical" :href="canonical" />

        <meta head-key="og:type" property="og:type" :content="ogType" />
        <meta head-key="og:site_name" property="og:site_name" :content="shared.site_name" />
        <meta head-key="og:locale" property="og:locale" :content="ogLocale" />
        <meta
            v-for="locale in shared.alternate_locales ?? []"
            :key="`og-locale-${locale}`"
            :head-key="`og:locale:alternate:${locale}`"
            property="og:locale:alternate"
            :content="String(locale).replace('-', '_')"
        />
        <meta head-key="og:url" property="og:url" :content="canonical" />
        <meta head-key="og:title" property="og:title" :content="displayTitle" />
        <meta
            v-if="resolvedDescription"
            head-key="og:description"
            property="og:description"
            :content="resolvedDescription"
        />
        <meta head-key="og:image" property="og:image" :content="ogImage" />
        <meta head-key="og:image:alt" property="og:image:alt" :content="ogImageAlt" />

        <meta head-key="twitter:card" name="twitter:card" content="summary_large_image" />
        <meta head-key="twitter:title" name="twitter:title" :content="displayTitle" />
        <meta
            v-if="resolvedDescription"
            head-key="twitter:description"
            name="twitter:description"
            :content="resolvedDescription"
        />
        <meta head-key="twitter:image" name="twitter:image" :content="ogImage" />
    </Head>
</template>
