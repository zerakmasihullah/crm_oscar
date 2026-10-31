<script setup lang="ts">
import { Form, Head, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import GoogleSignInButton from '@/components/GoogleSignInButton.vue';
import InputError from '@/components/InputError.vue';
import PasskeyVerify from '@/components/PasskeyVerify.vue';
import PasswordInput from '@/components/PasswordInput.vue';
import TextLink from '@/components/TextLink.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { register } from '@/routes';
import { store } from '@/routes/login';
import { request } from '@/routes/password';

defineOptions({
    layout: {
        title: 'Welcome back',
        description: 'Enter your details to continue',
    },
});

defineProps<{
    status?: string;
    canResetPassword: boolean;
    canRegister?: boolean;
    canUseGoogleAuth?: boolean;
}>();

const flashError = computed(() => {
    const flash = usePage().props.flash as
        | { error?: string | null }
        | undefined;

    return flash?.error ?? null;
});
</script>

<template>
    <Head title="Log in" />

    <div v-if="status" class="auth-alert auth-alert-success" role="status">
        {{ status }}
    </div>

    <div v-if="flashError" class="auth-alert auth-alert-error" role="alert">
        {{ flashError }}
    </div>

    <div class="auth-alt-methods">
        <PasskeyVerify :show-separator="!canUseGoogleAuth" />
        <GoogleSignInButton v-if="canUseGoogleAuth" />
    </div>

    <Form
        v-bind="store.form()"
        :reset-on-success="['password']"
        v-slot="{ errors, processing }"
        class="auth-form"
    >
        <div class="auth-fields">
            <div class="auth-field">
                <Label for="email">Email</Label>
                <Input
                    id="email"
                    type="email"
                    name="email"
                    required
                    autofocus
                    :tabindex="1"
                    autocomplete="email"
                    placeholder="you@company.com"
                    class="auth-control"
                />
                <InputError :message="errors.email" />
            </div>

            <div class="auth-field">
                <div class="auth-field-row">
                    <Label for="password">Password</Label>
                    <TextLink
                        v-if="canResetPassword"
                        :href="request()"
                        class="auth-inline-link"
                        :tabindex="5"
                    >
                        Forgot?
                    </TextLink>
                </div>
                <PasswordInput
                    id="password"
                    name="password"
                    required
                    :tabindex="2"
                    autocomplete="current-password"
                    placeholder="Your password"
                    class="auth-control"
                />
                <InputError :message="errors.password" />
            </div>

            <Label for="remember" class="auth-remember">
                <Checkbox id="remember" name="remember" :tabindex="3" />
                <span>Keep me signed in</span>
            </Label>

            <Button
                type="submit"
                class="auth-submit"
                :tabindex="4"
                :disabled="processing"
                data-test="login-button"
            >
                <Spinner v-if="processing" />
                <template v-else>Sign in</template>
            </Button>
        </div>

        <p v-if="canRegister" class="auth-footer">
            New here?
            <TextLink :href="register()" :tabindex="6">Create an account</TextLink>
        </p>
    </Form>
</template>
