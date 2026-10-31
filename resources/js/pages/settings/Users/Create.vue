<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ArrowLeft, Shield, UserPlus } from '@lucide/vue';
import AccessSettingsTabs from '@/components/settings/AccessSettingsTabs.vue';
import SettingsPage from '@/components/settings/SettingsPage.vue';
import SettingsPanel from '@/components/settings/SettingsPanel.vue';
import InputError from '@/components/InputError.vue';
import { MisSection } from '@/components/mis';
import PasswordInput from '@/components/PasswordInput.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useTranslations } from '@/composables/useTranslations';

const props = defineProps<{
    roles: Array<{ id: number; name: string }>;
}>();

const { t } = useTranslations();

const form = useForm({
    name: '',
    email: '',
    password: '',
    password_confirmation: '',
    roles: [] as string[],
});

function toggleRole(name: string, enable: boolean): void {
    if (enable) {
        if (!form.roles.includes(name)) {
            form.roles = [...form.roles, name];
        }
        return;
    }

    form.roles = form.roles.filter((role) => role !== name);
}

function submit(): void {
    form.post('/settings/users');
}

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Users', href: '/settings/users' },
            { title: 'Create', href: '/settings/users/create' },
        ],
    },
});
</script>

<template>
    <Head :title="t('Create user')" />

    <SettingsPage>
        <template #tabs>
            <AccessSettingsTabs />
        </template>

        <SettingsPanel>
            <MisSection
                :title="t('Create user')"
                :icon="UserPlus"
            >
                <template #actions>
                    <Button as-child variant="outline" size="sm">
                        <Link href="/settings/users">
                            <ArrowLeft class="size-4" />
                            {{ t('Back') }}
                        </Link>
                    </Button>
                </template>

                <form class="space-y-6" @submit.prevent="submit">
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div class="space-y-2">
                            <Label for="name">{{ t('Name') }}</Label>
                            <Input id="name" v-model="form.name" required />
                            <InputError :message="form.errors.name" />
                        </div>

                        <div class="space-y-2">
                            <Label for="email">{{ t('Email') }}</Label>
                            <Input
                                id="email"
                                v-model="form.email"
                                type="email"
                                required
                            />
                            <InputError :message="form.errors.email" />
                        </div>

                        <div class="space-y-2">
                            <Label for="password">{{ t('Password') }}</Label>
                            <PasswordInput
                                id="password"
                                v-model="form.password"
                                required
                                autocomplete="new-password"
                            />
                            <InputError :message="form.errors.password" />
                        </div>

                        <div class="space-y-2">
                            <Label for="password_confirmation">
                                {{ t('Confirm password') }}
                            </Label>
                            <PasswordInput
                                id="password_confirmation"
                                v-model="form.password_confirmation"
                                required
                                autocomplete="new-password"
                            />
                        </div>
                    </div>

                    <div class="space-y-3">
                        <Label>{{ t('Assign roles') }}</Label>
                        <div class="flex flex-wrap gap-2">
                            <button
                                v-for="role in props.roles"
                                :key="role.id"
                                type="button"
                                class="kit-chip-toggle inline-flex items-center gap-2 rounded-full px-3 py-1.5 text-sm"
                                :class="
                                    form.roles.includes(role.name)
                                        ? 'is-on'
                                        : 'bg-card text-foreground'
                                "
                                @click="
                                    toggleRole(
                                        role.name,
                                        !form.roles.includes(role.name),
                                    )
                                "
                            >
                                <Shield class="size-3.5 opacity-80" />
                                {{ role.name }}
                            </button>
                        </div>
                        <InputError :message="form.errors.roles" />
                    </div>

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
