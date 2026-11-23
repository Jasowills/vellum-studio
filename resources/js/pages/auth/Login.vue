<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import { AlertCircle, Check } from '@lucide/vue';
import InputError from '@/components/InputError.vue';
import PasswordInput from '@/components/PasswordInput.vue';
import TextLink from '@/components/TextLink.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
/* @chisel-registration */
import { register } from '@/routes';
/* @end-chisel-registration */
import { store } from '@/routes/login';
import { request } from '@/routes/password';
/* @chisel-passkeys */
import PasskeyVerify from '@/components/PasskeyVerify.vue';
/* @end-chisel-passkeys */

defineOptions({
    layout: {
        title: 'Log in to your account',
        description: 'Enter your email and password below to log in',
    },
});

defineProps<{
    status?: string;
    canResetPassword: boolean;
}>();

const inputClass =
    'h-11 rounded-none border-line bg-paper-deep text-signal placeholder:text-signal-muted dark:bg-paper-deep focus-visible:border-signal focus-visible:ring-signal/25';
const labelClass =
    'font-mono text-[0.68rem] font-normal tracking-[0.16em] text-signal uppercase';
const submitClass =
    'mt-1 h-11 w-full border border-signal bg-signal font-mono text-[0.72rem] tracking-[0.16em] text-signal-invert uppercase hover:bg-signal/85 focus-visible:ring-signal disabled:opacity-60';
const errorClass = '[&_p]:text-signal!';
</script>

<template>
    <Head title="Log in" />

    <div
        v-if="status"
        role="status"
        class="mb-6 flex items-start gap-3 border border-line bg-paper px-4 py-3 text-sm leading-relaxed text-signal"
    >
        <Check class="mt-0.5 size-4 shrink-0 text-signal" aria-hidden="true" />
        <span>{{ status }}</span>
    </div>

    <!-- @chisel-passkeys -->
    <PasskeyVerify />
    <!-- @end-chisel-passkeys -->

    <Form
        v-bind="store.form()"
        :reset-on-success="['password']"
        v-slot="{ errors, processing }"
        class="flex flex-col gap-6"
    >
        <div class="grid gap-5">
            <div class="grid gap-2">
                <Label for="email" :class="labelClass">Email address</Label>
                <Input
                    id="email"
                    type="email"
                    name="email"
                    required
                    v-focus
                    :tabindex="1"
                    autocomplete="email"
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
                        class="mt-0.5 size-3.5 shrink-0 text-signal"
                        aria-hidden="true"
                    />
                    <InputError :message="errors.email" />
                </div>
            </div>

            <div class="grid gap-2">
                <div class="flex items-center justify-between gap-4">
                    <Label for="password" :class="labelClass">Password</Label>
                    <TextLink
                        v-if="canResetPassword"
                        :href="request()"
                        class="font-sans text-xs tracking-normal text-signal normal-case"
                        :tabindex="5"
                    >
                        Forgot your password?
                    </TextLink>
                </div>
                <PasswordInput
                    id="password"
                    name="password"
                    required
                    :tabindex="2"
                    autocomplete="current-password"
                    placeholder="Password"
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
                        class="mt-0.5 size-3.5 shrink-0 text-signal"
                        aria-hidden="true"
                    />
                    <InputError :message="errors.password" />
                </div>
            </div>

            <div class="flex items-center">
                <Label
                    for="remember"
                    class="flex cursor-pointer items-center gap-3 font-sans text-sm font-normal tracking-normal text-signal-muted normal-case"
                >
                    <Checkbox
                        id="remember"
                        name="remember"
                        :tabindex="3"
                        class="size-4 border-line bg-paper-deep data-[state=checked]:border-signal data-[state=checked]:bg-signal data-[state=checked]:text-signal-invert"
                    />
                    <span>Remember me</span>
                </Label>
            </div>

            <Button
                type="submit"
                :class="submitClass"
                :tabindex="4"
                :disabled="processing"
                :aria-busy="processing"
                data-test="login-button"
            >
                <Spinner v-if="processing" />
                {{ processing ? 'Logging in...' : 'Log in' }}
            </Button>
        </div>

        <!-- @chisel-registration -->
        <div
            class="border-t border-line pt-5 text-center text-sm text-signal-muted"
        >
            Don't have an account?
            <TextLink :href="register()" :tabindex="5" class="text-signal">
                Sign up
            </TextLink>
        </div>
        <!-- @end-chisel-registration -->
    </Form>
</template>
