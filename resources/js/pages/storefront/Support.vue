<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { ArrowLeft, Mail, MapPin, Phone } from '@lucide/vue';
import { ref } from 'vue';
import Header from '@/components/storefront/Header.vue';
import JsonLd from '@/components/storefront/JsonLd.vue';
import MyAccountModal from '@/components/storefront/MyAccountModal.vue';
import StorefrontSeo from '@/components/storefront/StorefrontSeo.vue';
import StorefrontLayout from '@/layouts/StorefrontLayout.vue';
import { BUSINESS } from '@/lib/storefront/business';
import { breadcrumbJsonLd } from '@/lib/storefront/json-ld';

defineOptions({ layout: StorefrontLayout });

function goBack() {
    window.history.back();
}

const isAccountModalOpen = ref(false);

function supportFaqJsonLd() {
    return {
        '@context': 'https://schema.org',
        '@type': 'FAQPage',
        mainEntity: [
            {
                '@type': 'Question',
                name: "Where is Oscar's Pizza & Kebab in Limerick?",
                acceptedAnswer: {
                    '@type': 'Answer',
                    text: `${BUSINESS.name} is at ${BUSINESS.address.full}. You can collect orders from the restaurant or order online for local delivery in Limerick.`,
                },
            },
            {
                '@type': 'Question',
                name: "What are the opening hours for Oscar's Pizza Limerick?",
                acceptedAnswer: {
                    '@type': 'Answer',
                    text: 'Monday to Thursday 15:00–00:30. Friday to Sunday 15:00–01:30.',
                },
            },
            {
                '@type': 'Question',
                name: "How can I contact Oscar's Pizza & Kebab?",
                acceptedAnswer: {
                    '@type': 'Answer',
                    text: `Call ${BUSINESS.telephoneDisplay} or email ${BUSINESS.email}. For order issues during opening hours, phone is fastest.`,
                },
            },
            {
                '@type': 'Question',
                name: 'Do you deliver pizza and kebab in Limerick?',
                acceptedAnswer: {
                    '@type': 'Answer',
                    text: "Yes. Order online at oscarlimerick.com for local delivery in and around Limerick, or collect from 17 John's Street, Irishtown.",
                },
            },
            {
                '@type': 'Question',
                name: "What is the official website for Oscar's Pizza Limerick?",
                acceptedAnswer: {
                    '@type': 'Answer',
                    text: "The official website is https://www.oscarlimerick.com. Order pizza and kebab there for collection or delivery, or use the official Oscar's Pizza app on iOS and Android. Ordering on oscarlimerick.com or the official app goes direct to the restaurant.",
                },
            },
            {
                '@type': 'Question',
                name: "How do I order Oscar's Pizza & Kebab online without a third-party site?",
                acceptedAnswer: {
                    '@type': 'Answer',
                    text: "Visit www.oscarlimerick.com or download the official Oscar's Pizza app. Choose delivery or collection, add items from the menu, and checkout. Support is available at info@oscarlimerick.com or 061 597 178.",
                },
            },
        ],
    };
}

const openingHoursRows = [
    { day: 'Monday – Thursday', time: '15:00 – 00:30' },
    { day: 'Friday – Sunday', time: '15:00 – 01:30' },
];
</script>

<template>
    <StorefrontSeo
        title="Contact & Support | Pizza Takeaway Limerick"
        description="Contact Oscar's Pizza & Kebab in Limerick — phone, email, opening hours and FAQs."
        path="/support"
    />

    <JsonLd
        :data="[
            breadcrumbJsonLd([
                { name: 'Home', path: '/' },
                { name: 'Support', path: '/support' },
            ]),
            supportFaqJsonLd(),
        ]"
    />

    <div class="min-h-screen bg-background transition-colors duration-300">
        <Header @menu-click="isAccountModalOpen = true" />

        <div class="max-w-4xl mx-auto px-3 sm:px-4 py-3 sm:py-4 pb-6">
            <button
                type="button"
                class="flex items-center gap-1.5 text-muted-foreground hover:text-foreground mb-3 sm:mb-4 transition text-sm touch-manipulation"
                @click="goBack"
            >
                <ArrowLeft class="w-4 h-4" />
                <span>Back</span>
            </button>

            <div class="bg-card rounded-xl p-4 sm:p-6 md:p-8 border border-border shadow-sm">
                <h1 class="text-2xl sm:text-3xl md:text-4xl font-bold text-foreground mb-3">Customer Support</h1>
                <p class="text-sm sm:text-base text-muted-foreground leading-relaxed mb-8">
                    Need help with an order, the Oscar&apos;s Pizza app, or anything else? We serve pizza and kebab
                    takeaway from John&apos;s Street in Limerick — contact us using the details below and we will get
                    back to you as soon as possible.
                </p>

                <div class="space-y-6 text-muted-foreground">
                    <section class="space-y-4">
                        <h2 class="text-xl sm:text-2xl font-bold text-foreground">How to Reach Us</h2>
                        <div class="grid gap-3 sm:gap-4">
                            <a
                                href="mailto:info@oscarlimerick.com"
                                class="flex items-start gap-3 rounded-xl border border-border bg-muted/50 p-4 hover:bg-muted transition"
                            >
                                <div
                                    class="w-10 h-10 rounded-lg bg-orange-500/20 flex items-center justify-center flex-shrink-0"
                                >
                                    <Mail class="w-5 h-5 text-orange-500" />
                                </div>
                                <div>
                                    <p class="text-foreground font-semibold text-sm sm:text-base">Email</p>
                                    <p class="text-sm sm:text-base text-orange-500 break-all">info@oscarlimerick.com</p>
                                    <p class="text-xs sm:text-sm mt-1">
                                        Best for app issues, refunds, and account questions.
                                    </p>
                                </div>
                            </a>

                            <a
                                href="tel:+35361597178"
                                class="flex items-start gap-3 rounded-xl border border-border bg-muted/50 p-4 hover:bg-muted transition"
                            >
                                <div
                                    class="w-10 h-10 rounded-lg bg-orange-500/20 flex items-center justify-center flex-shrink-0"
                                >
                                    <Phone class="w-5 h-5 text-orange-500" />
                                </div>
                                <div>
                                    <p class="text-foreground font-semibold text-sm sm:text-base">Phone</p>
                                    <p class="text-sm sm:text-base text-orange-500">061 597 178</p>
                                    <p class="text-xs sm:text-sm mt-1">
                                        Call for order status, allergies, or urgent help during opening hours.
                                    </p>
                                </div>
                            </a>

                            <div class="flex items-start gap-3 rounded-xl border border-border bg-muted/50 p-4">
                                <div
                                    class="w-10 h-10 rounded-lg bg-orange-500/20 flex items-center justify-center flex-shrink-0"
                                >
                                    <MapPin class="w-5 h-5 text-orange-500" />
                                </div>
                                <div>
                                    <p class="text-foreground font-semibold text-sm sm:text-base">Restaurant Address</p>
                                    <p class="text-sm sm:text-base text-foreground">
                                        Oscar&apos;s Pizza &amp; Kebab<br />
                                        17 John&apos;s Street<br />
                                        Irishtown, Limerick<br />
                                        V94 KR22, Ireland
                                    </p>
                                    <a
                                        href="https://www.google.com/maps/search/?api=1&query=Oscar%27s+Pizza+%26+Kebab%2C+17+John%27s+Street%2C+Limerick%2C+Ireland"
                                        target="_blank"
                                        rel="noopener noreferrer"
                                        class="text-xs sm:text-sm text-orange-500 hover:underline mt-1 inline-block"
                                    >
                                        Get directions on Google Maps
                                    </a>
                                </div>
                            </div>
                        </div>
                    </section>

                    <section class="space-y-4">
                        <h2 class="text-xl sm:text-2xl font-bold text-foreground">Collection &amp; Delivery in Limerick</h2>
                        <p class="text-sm sm:text-base leading-relaxed">
                            Collect your order from our restaurant on John&apos;s Street in Irishtown, Limerick, or order
                            online for local delivery across Limerick city. We offer pizza, kebab, shawarma, and
                            Mediterranean takeaway for lunch, evening, and late-night orders during opening hours.
                        </p>
                    </section>

                    <section class="space-y-4">
                        <h2 class="text-xl sm:text-2xl font-bold text-foreground">Opening Hours</h2>
                        <div class="rounded-xl border border-border bg-muted/50 p-4">
                            <div class="space-y-2">
                                <div
                                    v-for="item in openingHoursRows"
                                    :key="item.day"
                                    class="flex justify-between items-center gap-4 py-1.5 border-b border-border last:border-0"
                                >
                                    <span class="text-sm sm:text-base">{{ item.day }}</span>
                                    <span class="text-foreground text-sm sm:text-base font-semibold">{{ item.time }}</span>
                                </div>
                            </div>
                        </div>
                    </section>

                    <section class="space-y-4">
                        <h2 class="text-xl sm:text-2xl font-bold text-foreground">Official Website</h2>
                        <p class="text-sm sm:text-base leading-relaxed">
                            <strong class="text-foreground">oscarlimerick.com</strong> is the official website of
                            Oscar&apos;s Pizza &amp; Kebab in Limerick. Order pizza and kebab here for collection or
                            local delivery, or use our official Oscar&apos;s Pizza app on the App Store and Google Play.
                            Ordering on this site or our app goes direct to the restaurant.
                        </p>
                        <p class="text-sm sm:text-base leading-relaxed">
                            Prefer not to use third-party ordering websites? Bookmark
                            <Link href="/" class="text-orange-500 hover:underline"> www.oscarlimerick.com </Link>
                            and order with us directly.
                        </p>
                    </section>

                    <section class="space-y-4">
                        <h2 class="text-xl sm:text-2xl font-bold text-foreground">What We Can Help With</h2>
                        <ul class="list-disc list-inside space-y-2 ml-1 text-sm sm:text-base">
                            <li>Questions about existing or recent orders</li>
                            <li>Delivery or collection issues</li>
                            <li>App login, account, or payment problems</li>
                            <li>Allergy and dietary information</li>
                            <li>Refunds and order corrections</li>
                            <li>General feedback about Oscar&apos;s Pizza</li>
                        </ul>
                    </section>

                    <section class="space-y-4">
                        <h2 class="text-xl sm:text-2xl font-bold text-foreground">Response Time</h2>
                        <p class="text-sm sm:text-base leading-relaxed">
                            We aim to reply to emails within 1–2 business days. For urgent order issues, please call us
                            during opening hours so we can help more quickly.
                        </p>
                    </section>

                    <section class="space-y-3 pt-2 border-t border-border">
                        <p class="text-sm sm:text-base">
                            You can also review our
                            <Link href="/privacy-statement" class="text-orange-500 hover:underline"> Privacy Statement </Link>
                            and
                            <Link href="/terms-of-use" class="text-orange-500 hover:underline"> Terms of Use </Link>.
                        </p>
                    </section>
                </div>
            </div>
        </div>

        <MyAccountModal :is-open="isAccountModalOpen" @close="isAccountModalOpen = false" />
    </div>
</template>
