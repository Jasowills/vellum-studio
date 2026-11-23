<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import { AlertCircle } from '@lucide/vue';
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
}>();

defineOptions({
    layout: {
        title: 'Create an account',
        description: 'Enter your details below to create your account',
    },
});

const inputClass =
    'vellum-input focus-ring h-11 rounded-none pr-10 pl-3 text-sm text-document';
const labelClass = 'text-xs font-medium text-document';
const submitClass =
    'vellum-button focus-ring mt-1 h-11 w-full rounded-none text-sm';
const errorClass = '[&_p]:text-document!';
const quietLinkClass =
    '!text-document !decoration-line underline-offset-4 hover:!decoration-document focus-ring text-xs';
</script>

<template>
    <Head title="Register" />

    <Form
        v-bind="store.form()"
        :reset-on-success="['password', 'password_confirmation']"
        v-slot="{ errors, processing }"
        class="flex flex-col gap-6"
    >
        <div class="grid gap-5">
            <div class="grid gap-2">
                <Label for="name" :class="labelClass">Name</Label>
                <Input
                    id="name"
                    type="text"
                    required
                    v-focus
                    :tabindex="1"
                    autocomplete="name"
                    name="name"
                    placeholder="Full name"
                    :class="inputClass"
                    :aria-invalid="Boolean(errors.name)"
                    aria-describedby="name-error"
                />
                <div
                    id="name-error"
                    :class="errorClass"
                    class="flex min-h-5 items-start gap-2"
                    aria-live="polite"
                >
                    <AlertCircle
                        v-if="errors.name"
                        class="mt-0.5 size-3.5 shrink-0 text-document"
                        aria-hidden="true"
                    />
                    <InputError :message="errors.name" />
                </div>
            </div>

            <div class="grid gap-2">
                <Label for="email" :class="labelClass">Email address</Label>
                <Input
                    id="email"
                    type="email"
                    required
                    :tabindex="2"
                    autocomplete="email"
                    name="email"
                    placeholder="email@example.com"
                    :class="inputClass"
                    :aria-invalid="Boolean(errors.email)"
                    aria-describedby="email-error"
                />
                <div
                    id="email-error"
                    :class="errorClass"
                    class="flex min-h-5 items-start gap-2"
                    aria-live="polite"
                >
                    <AlertCircle
                        v-if="errors.email"
                        class="mt-0.5 size-3.5 shrink-0 text-document"
                        aria-hidden="true"
                    />
                    <InputError :message="errors.email" />
                </div>
            </div>

            <div class="grid gap-2">
                <Label for="password" :class="labelClass">Password</Label>
                <PasswordInput
                    id="password"
                    required
                    :tabindex="3"
                    autocomplete="new-password"
                    name="password"
                    placeholder="Password"
                    :passwordrules="passwordRules"
                    :class="inputClass"
                    :aria-invalid="Boolean(errors.password)"
                    aria-describedby="password-error"
                />
                <div
                    id="password-error"
                    :class="errorClass"
                    class="flex min-h-5 items-start gap-2"
                    aria-live="polite"
                >
                    <AlertCircle
                        v-if="errors.password"
                        class="mt-0.5 size-3.5 shrink-0 text-document"
                        aria-hidden="true"
                    />
                    <InputError :message="errors.password" />
                </div>
            </div>

            <div class="grid gap-2">
                <Label for="password_confirmation" :class="labelClass">
                    Confirm password
                </Label>
                <PasswordInput
                    id="password_confirmation"
                    required
                    :tabindex="4"
                    autocomplete="new-password"
                    name="password_confirmation"
                    placeholder="Confirm password"
                    :passwordrules="passwordRules"
                    :class="inputClass"
                    :aria-invalid="Boolean(errors.password_confirmation)"
                    aria-describedby="password-confirmation-error"
                />
                <div
                    id="password-confirmation-error"
                    :class="errorClass"
                    class="flex min-h-5 items-start gap-2"
                    aria-live="polite"
                >
                    <AlertCircle
                        v-if="errors.password_confirmation"
                        class="mt-0.5 size-3.5 shrink-0 text-document"
                        aria-hidden="true"
                    />
                    <InputError :message="errors.password_confirmation" />
                </div>
            </div>

            <Button
                type="submit"
                :class="submitClass"
                tabindex="5"
                :disabled="processing"
                :aria-busy="processing"
                data-test="register-user-button"
            >
                <Spinner v-if="processing" />
                {{ processing ? 'Creating account...' : 'Create account' }}
            </Button>
        </div>

        <div class="border-t border-line pt-5 text-center text-sm text-ash">
            Already have an account?
            <TextLink :href="login()" :class="quietLinkClass" :tabindex="6">
                Log in
            </TextLink>
        </div>
    </Form>
</template>
