<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import InputError from '@/components/InputError.vue';
import TextLink from '@/components/TextLink.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { login } from '@/routes';
import { email } from '@/routes/password';

defineOptions({
    layout: {
        title: 'Reset password',
        description: 'We will email you a secure reset link',
    },
});

defineProps<{
    status?: string;
}>();
</script>

<template>
    <Head title="Forgot password" />

    <div v-if="status" class="auth-alert auth-alert-success" role="status">
        {{ status }}
    </div>

    <Form v-bind="email.form()" v-slot="{ errors, processing }" class="auth-form">
        <div class="auth-fields">
            <div class="auth-field">
                <Label for="email">Email</Label>
                <Input
                    id="email"
                    type="email"
                    name="email"
                    autocomplete="email"
                    autofocus
                    placeholder="you@company.com"
                    class="auth-control"
                />
                <InputError :message="errors.email" />
            </div>

            <Button
                class="auth-submit"
                :disabled="processing"
                data-test="email-password-reset-link-button"
            >
                <Spinner v-if="processing" />
                Send reset link
            </Button>
        </div>

        <p class="auth-footer">
            <TextLink :href="login()">Back to log in</TextLink>
        </p>
    </Form>
</template>
