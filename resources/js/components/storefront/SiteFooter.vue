<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { BUSINESS } from '@/lib/storefront/business';

const year = new Date().getFullYear();
</script>

<template>
    <footer class="border-t border-border bg-background mt-auto">
        <div class="max-w-7xl mx-auto px-4 md:px-6 lg:px-16 xl:px-24 py-8 md:py-10">
            <div class="grid gap-8 sm:grid-cols-2 lg:grid-cols-3">
                <div class="space-y-2">
                    <p class="text-foreground font-bold text-base md:text-lg">{{ BUSINESS.name }}</p>
                    <p class="text-orange-500 text-sm font-semibold">{{ BUSINESS.tagline }}</p>
                    <p class="text-muted-foreground text-sm leading-relaxed">
                        Order pizza, kebab and Mediterranean takeaway direct from our restaurant — no third-party
                        ordering site required. Use this website or our official app.
                    </p>
                </div>

                <div class="space-y-2">
                    <p class="text-foreground font-semibold text-sm">Restaurant</p>
                    <address class="not-italic text-muted-foreground text-sm leading-relaxed">
                        {{ BUSINESS.address.streetAddress }}
                        <br />
                        {{ BUSINESS.address.neighborhood }}, {{ BUSINESS.address.addressLocality }}
                        <br />
                        {{ BUSINESS.address.postalCode }}, Ireland
                    </address>
                    <p class="text-sm">
                        <a :href="`tel:${BUSINESS.telephone}`" class="text-orange-500 hover:underline font-medium">
                            {{ BUSINESS.telephoneDisplay }}
                        </a>
                    </p>
                    <p class="text-sm">
                        <a :href="`mailto:${BUSINESS.email}`" class="text-orange-500 hover:underline break-all">
                            {{ BUSINESS.email }}
                        </a>
                    </p>
                    <p class="text-sm">
                        <a
                            :href="BUSINESS.maps.directionsUrl"
                            target="_blank"
                            rel="noopener noreferrer"
                            class="text-muted-foreground hover:text-orange-500 transition"
                        >
                            Directions on Google Maps
                        </a>
                    </p>
                </div>

                <div class="space-y-2">
                    <p class="text-foreground font-semibold text-sm">Order &amp; help</p>
                    <ul class="space-y-1.5 text-sm text-muted-foreground">
                        <li>
                            <Link href="/" class="hover:text-orange-500 transition">Order online (official site)</Link>
                        </li>
                        <li>
                            <Link href="/support" class="hover:text-orange-500 transition"
                                >Contact &amp; opening hours</Link
                            >
                        </li>
                        <li>
                            <a
                                :href="BUSINESS.apps.ios.url"
                                target="_blank"
                                rel="noopener noreferrer"
                                class="hover:text-orange-500 transition"
                            >
                                Official iOS app
                            </a>
                        </li>
                        <li>
                            <a
                                :href="BUSINESS.apps.android.url"
                                target="_blank"
                                rel="noopener noreferrer"
                                class="hover:text-orange-500 transition"
                            >
                                Official Android app
                            </a>
                        </li>
                        <li>
                            <Link href="/terms-of-use" class="hover:text-orange-500 transition">Terms of Use</Link>
                        </li>
                        <li>
                            <Link href="/privacy-statement" class="hover:text-orange-500 transition"
                                >Privacy Statement</Link
                            >
                        </li>
                    </ul>
                </div>
            </div>

            <div
                class="mt-8 pt-6 border-t border-border flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2 text-xs text-muted-foreground"
            >
                <p>
                    © {{ year }} {{ BUSINESS.name }}. Official website:
                    <span class="text-foreground">oscarlimerick.com</span>
                </p>
                <p>
                    {{ BUSINESS.address.streetAddress }}, {{ BUSINESS.address.addressLocality }} · Pizza &amp; kebab
                    takeaway
                </p>
            </div>
        </div>
    </footer>
</template>
