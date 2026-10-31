<script setup lang="ts">
import { Smartphone } from '@lucide/vue';
</script>

<template>
    <div
        class="bg-gradient-to-r from-orange-500/10 via-orange-500/5 to-orange-500/10 border-y border-orange-500/20 py-4 px-4 md:px-6 lg:px-16 xl:px-24"
    >
        <div class="max-w-7xl mx-auto flex flex-col sm:flex-row items-center justify-between gap-4">
            <div class="flex items-center gap-3 md:gap-4 flex-1">
                <div
                    class="hidden sm:flex items-center justify-center w-10 h-10 md:w-12 md:h-12 rounded-full bg-orange-500/20"
                >
                    <Smartphone class="w-5 h-5 md:w-6 md:h-6 text-orange-500" />
                </div>
                <div class="flex-1 min-w-0 text-center sm:text-left">
                    <h3 class="text-sm md:text-base font-bold text-foreground mb-1">Official Oscar&apos;s Pizza App</h3>
                    <p class="text-xs md:text-sm text-muted-foreground">
                        Order direct from our Limerick restaurant — same menu as this official website
                    </p>
                </div>
            </div>

            <div class="flex items-center gap-3 md:gap-4 flex-shrink-0">
                <a
                    href="https://apps.apple.com/ie/app/oscars-pizza/id6741105059?itscg=30200&itsct=apps_box_badge&mttnsubad=6741105059"
                    target="_blank"
                    rel="noopener noreferrer"
                    class="hover:opacity-80 transition-opacity inline-flex items-center justify-center"
                    aria-label="Download Oscar's Pizza official app on the App Store"
                    style="height: 40px; width: 120px"
                >
                    <img
                        src="https://toolbox.marketingtools.apple.com/api/v2/badges/download-on-the-app-store/white/en-us?releaseDate=1738195200"
                        alt="Download Oscar's Pizza official app on the App Store"
                        class="h-full w-full object-contain"
                    />
                </a>

                <a
                    href="https://play.google.com/store/apps/details?id=com.kebab.oscarspizza&pcampaignid=web_share"
                    target="_blank"
                    rel="noopener noreferrer"
                    class="hover:opacity-80 transition-opacity inline-flex items-center justify-center"
                    aria-label="Get Oscar's Pizza official app on Google Play"
                    style="height: 45px; width: 135px"
                >
                    <img
                        src="/storefront/agogleplay.png"
                        alt="Get Oscar's Pizza official app on Google Play"
                        class="h-full w-full object-contain"
                    />
                </a>
            </div>
        </div>
    </div>
</template>
