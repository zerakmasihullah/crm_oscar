<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { computed } from 'vue';
import { absoluteUrl, BUSINESS, SITE_NAME } from '@/lib/storefront/business';

const props = withDefaults(
    defineProps<{
        title?: string;
        description?: string;
        path?: string;
        image?: string;
        imageAlt?: string;
        noIndex?: boolean;
    }>(),
    {
        noIndex: false,
    },
);

const pageTitle = computed(
    () =>
        props.title?.trim() ||
        `${BUSINESS.name} | Official Website — Order Pizza & Kebab in Limerick`,
);

const pageDescription = computed(
    () => props.description?.trim() || BUSINESS.shortDescription,
);

const canonical = computed(() => absoluteUrl(props.path || '/'));

const ogImage = computed(() => absoluteUrl(props.image || '/storefront/hero.png'));

const ogImageAlt = computed(
    () =>
        props.imageAlt ||
        `${BUSINESS.name} official website — pizza and kebab takeaway on John's Street, Limerick`,
);

const keywords = [
    "Oscar's Pizza Limerick official website",
    "Oscar's Pizza & Kebab",
    'oscarlimerick.com',
    'order pizza direct Limerick',
    'pizza takeaway Limerick',
    'kebab Limerick',
    'pizza delivery Limerick',
    "kebab delivery John's Street",
    'Irishtown pizza',
    'Limerick takeaway',
    'order pizza online Limerick',
    "17 John's Street Limerick",
].join(', ');
</script>

<template>
    <Head :title="pageTitle">
        <meta head-key="description" name="description" :content="pageDescription" />
        <meta head-key="keywords" name="keywords" :content="keywords" />
        <meta head-key="author" name="author" :content="BUSINESS.name" />
        <meta head-key="application-name" name="application-name" :content="BUSINESS.name" />
        <meta
            head-key="robots"
            name="robots"
            :content="
                noIndex
                    ? 'noindex, nofollow'
                    : 'index, follow, max-image-preview:large, max-snippet:-1, max-video-preview:-1'
            "
        />
        <link head-key="canonical" rel="canonical" :href="canonical" />
        <link head-key="manifest" rel="manifest" href="/storefront/site.webmanifest" />
        <link head-key="apple-touch-icon" rel="apple-touch-icon" href="/storefront/logo.png" />

        <meta head-key="theme-color-light" name="theme-color" media="(prefers-color-scheme: light)" content="#ffffff" />
        <meta head-key="theme-color-dark" name="theme-color" media="(prefers-color-scheme: dark)" content="#0a0a0a" />
        <meta head-key="apple-mobile-web-app-capable" name="apple-mobile-web-app-capable" content="yes" />
        <meta head-key="apple-mobile-web-app-title" name="apple-mobile-web-app-title" :content="BUSINESS.name" />

        <meta head-key="geo.region" name="geo.region" content="IE-LK" />
        <meta head-key="geo.placename" name="geo.placename" content="Limerick" />
        <meta
            head-key="geo.position"
            name="geo.position"
            :content="`${BUSINESS.geo.latitude};${BUSINESS.geo.longitude}`"
        />
        <meta
            head-key="ICBM"
            name="ICBM"
            :content="`${BUSINESS.geo.latitude}, ${BUSINESS.geo.longitude}`"
        />

        <meta
            head-key="business:street"
            property="business:contact_data:street_address"
            :content="BUSINESS.address.streetAddress"
        />
        <meta
            head-key="business:locality"
            property="business:contact_data:locality"
            :content="BUSINESS.address.addressLocality"
        />
        <meta
            head-key="business:region"
            property="business:contact_data:region"
            :content="BUSINESS.address.addressRegion"
        />
        <meta
            head-key="business:postal"
            property="business:contact_data:postal_code"
            :content="BUSINESS.address.postalCode"
        />
        <meta head-key="business:country" property="business:contact_data:country_name" content="Ireland" />
        <meta head-key="business:email" property="business:contact_data:email" :content="BUSINESS.email" />
        <meta
            head-key="business:phone"
            property="business:contact_data:phone_number"
            :content="BUSINESS.telephone"
        />

        <meta
            head-key="apple-itunes-app"
            name="apple-itunes-app"
            :content="`app-id=${BUSINESS.apps.ios.appId}, app-argument=${BUSINESS.url}`"
        />
        <meta head-key="al:ios:url" property="al:ios:url" :content="BUSINESS.apps.ios.url" />
        <meta head-key="al:ios:app_store_id" property="al:ios:app_store_id" :content="BUSINESS.apps.ios.appId" />
        <meta head-key="al:ios:app_name" property="al:ios:app_name" :content="BUSINESS.apps.ios.name" />
        <meta head-key="al:android:url" property="al:android:url" :content="BUSINESS.apps.android.url" />
        <meta
            head-key="al:android:package"
            property="al:android:package"
            :content="BUSINESS.apps.android.appId"
        />
        <meta
            head-key="al:android:app_name"
            property="al:android:app_name"
            :content="BUSINESS.apps.android.name"
        />
        <meta head-key="al:web:url" property="al:web:url" :content="BUSINESS.url" />

        <meta head-key="og:type" property="og:type" content="website" />
        <meta head-key="og:site_name" property="og:site_name" :content="BUSINESS.name" />
        <meta head-key="og:locale" property="og:locale" content="en_IE" />
        <meta head-key="og:locale:alternate" property="og:locale:alternate" content="en_GB" />
        <meta head-key="og:url" property="og:url" :content="canonical" />
        <meta head-key="og:title" property="og:title" :content="pageTitle" />
        <meta head-key="og:description" property="og:description" :content="pageDescription" />
        <meta head-key="og:image" property="og:image" :content="ogImage" />
        <meta head-key="og:image:alt" property="og:image:alt" :content="ogImageAlt" />

        <meta head-key="twitter:card" name="twitter:card" content="summary_large_image" />
        <meta head-key="twitter:title" name="twitter:title" :content="pageTitle" />
        <meta head-key="twitter:description" name="twitter:description" :content="pageDescription" />
        <meta head-key="twitter:image" name="twitter:image" :content="ogImage" />

        <!-- Keep SITE_NAME available for tooling / future templates -->
        <meta head-key="storefront:site" name="storefront:site" :content="SITE_NAME" />
    </Head>
</template>
