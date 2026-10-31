<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ArrowLeft, Shield } from '@lucide/vue';
import AccessSettingsTabs from '@/components/settings/AccessSettingsTabs.vue';
import PermissionMatrix from '@/components/settings/PermissionMatrix.vue';
import SettingsPage from '@/components/settings/SettingsPage.vue';
import SettingsPanel from '@/components/settings/SettingsPanel.vue';
import InputError from '@/components/InputError.vue';
import { MisSection } from '@/components/mis';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useTranslations } from '@/composables/useTranslations';

type Permission = { id: number; name: string };

defineProps<{
    permissions: Permission[];
}>();

const { t } = useTranslations();

const form = useForm({
    name: '',
    permissions: [] as string[],
});

function submit(): void {
    form.post('/settings/roles');
}

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Roles', href: '/settings/roles' },
            { title: 'Create', href: '/settings/roles/create' },
        ],
    },
});
</script>

<template>
    <Head :title="t('Create role')" />

    <SettingsPage>
        <template #tabs>
            <AccessSettingsTabs />
        </template>

        <SettingsPanel>
            <MisSection
                :title="t('Create role')"
                :icon="Shield"
            >
                <template #actions>
                    <Button as-child variant="outline" size="sm">
                        <Link href="/settings/roles">
                            <ArrowLeft class="size-4" />
                            {{ t('Back') }}
                        </Link>
                    </Button>
                </template>

                <form class="space-y-6" @submit.prevent="submit">
                    <div class="max-w-md space-y-2">
                        <Label for="name">{{ t('Name') }}</Label>
                        <Input
                            id="name"
                            v-model="form.name"
                            required
                            autocomplete="off"
                            :placeholder="t('e.g. Editor')"
                        />
                        <InputError :message="form.errors.name" />
                    </div>

                    <PermissionMatrix
                        v-model="form.permissions"
                        :permissions="permissions"
                    />
                    <InputError :message="form.errors.permissions" />

                    <div class="flex justify-end border-t border-border/50 pt-4">
                        <Button type="submit" :disabled="form.processing">
                            {{ t('Save') }}
                        </Button>
                    </div>
                </form>
            </MisSection>
        </SettingsPanel>
    </SettingsPage>
</template>
