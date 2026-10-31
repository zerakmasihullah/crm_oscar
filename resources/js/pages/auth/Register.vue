<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import GoogleSignInButton from '@/components/GoogleSignInButton.vue';
import InputError from '@/components/InputError.vue';
import PasswordInput from '@/components/PasswordInput.vue';
import TextLink from '@/components/TextLink.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { login } from '@/routes';
import { store } from '@/routes/register';

defineProps<{
    passwordRules: string;
    canUseGoogleAuth?: boolean;
}>();

defineOptions({
    layout: {
        title: 'Create an account',
        description: 'Sign up and get started with your workspace',
    },
});
</script>

<template>
    <Head title="Register" />

    <Form
        v-bind="store.form()"
        :reset-on-success="['password', 'password_confirmation']"
        v-slot="{ errors, processing }"
        class="auth-form"
    >
        <div class="auth-fields">
            <div class="auth-field">
                <Label for="name">Full name</Label>
                <Input
                    id="name"
                    type="text"
                    required
                    autofocus
                    :tabindex="1"
                    autocomplete="name"
                    name="name"
                    placeholder="Full name"
                    class="auth-control"
                />
                <InputError :message="errors.name" />
            </div>

            <div class="auth-field">
                <Label for="email">Email</Label>
                <Input
                    id="email"
                    type="email"
                    required
                    :tabindex="2"
                    autocomplete="email"
                    name="email"
                    placeholder="you@company.com"
                    class="auth-control"
                />
                <InputError :message="errors.email" />
            </div>

            <div class="auth-field">
                <Label for="password">Password</Label>
                <PasswordInput
                    id="password"
                    required
                    :tabindex="3"
                    autocomplete="new-password"
                    name="password"
                    placeholder="Create a password"
                    :passwordrules="passwordRules"
                    class="auth-control"
                />
                <InputError :message="errors.password" />
            </div>

            <div class="auth-field">
                <Label for="password_confirmation">Confirm password</Label>
                <PasswordInput
                    id="password_confirmation"
                    required
                    :tabindex="4"
                    autocomplete="new-password"
                    name="password_confirmation"
                    placeholder="Confirm password"
                    :passwordrules="passwordRules"
                    class="auth-control"
                />
                <InputError :message="errors.password_confirmation" />
            </div>

            <Button
                type="submit"
                class="auth-submit"
                tabindex="5"
                :disabled="processing"
                data-test="register-user-button"
            >
                <Spinner v-if="processing" />
                <template v-else>Submit</template>
            </Button>
        </div>

        <div v-if="canUseGoogleAuth" class="auth-alt-methods auth-alt-methods-after">
            <GoogleSignInButton :show-separator="false" label="Continue with Google" />
        </div>

        <p class="auth-footer">
            Have an account?
            <TextLink :href="login()" :tabindex="6">Sign in</TextLink>
        </p>
    </Form>
</template>
