<script setup lang="ts">
import { Sparkles } from '@lucide/vue';
import { Button } from '@/components/ui/button';
import { useAiChat } from '@/composables/useAiChat';
import { useTranslations } from '@/composables/useTranslations';

const { t } = useTranslations();
const { available: chatAvailable, openChat } = useAiChat();
</script>

<template>
    <Button
        v-if="chatAvailable"
        type="button"
        variant="ghost"
        size="icon"
        class="size-9 rounded-full text-foreground hover:bg-[var(--brand-accent)]/20 hover:text-foreground"
        :aria-label="t('General Chat')"
        :title="t('General Chat')"
        @click="openChat"
    >
        <Sparkles class="size-4 shrink-0" :stroke-width="1.75" />
    </Button>
</template>
