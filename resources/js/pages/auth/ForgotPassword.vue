<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import { AlertCircle, Check } from '@lucide/vue';
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
        title: 'Forgot password',
        description: 'Enter your email to receive a password reset link',
    },
});

defineProps<{
    status?: string;
}>();

const inputClass =
    'h-11 rounded-none border-line bg-paper-deep text-signal placeholder:text-signal-muted dark:bg-paper-deep focus-visible:border-signal focus-visible:ring-signal/25';
const labelClass =
    'font-mono text-[0.68rem] font-normal tracking-[0.16em] text-signal uppercase';
const submitClass =
    'mt-2 h-11 w-full border border-signal bg-signal font-mono text-[0.72rem] tracking-[0.16em] text-signal-invert uppercase hover:bg-signal/85 focus-visible:ring-signal disabled:opacity-60';
const errorClass = '[&_p]:text-signal!';
</script>

<template>
    <Head title="Forgot password" />

    <div
        v-if="status"
        role="status"
        class="mb-6 flex items-start gap-3 border border-line bg-paper px-4 py-3 text-sm leading-relaxed text-signal"
    >
        <Check class="mt-0.5 size-4 shrink-0 text-signal" aria-hidden="true" />
        <span>{{ status }}</span>
    </div>

    <div class="grid gap-6">
        <Form v-bind="email.form()" v-slot="{ errors, processing }">
            <div class="grid gap-2">
                <Label for="email" :class="labelClass">Email address</Label>
                <Input
                    id="email"
                    type="email"
                    name="email"
                    autocomplete="off"
                    v-focus
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

            <div class="mt-6 flex items-center justify-start">
                <Button
                    type="submit"
                    :class="submitClass"
                    :disabled="processing"
                    :aria-busy="processing"
                    data-test="email-password-reset-link-button"
                >
                    <Spinner v-if="processing" />
                    {{
                        processing
                            ? 'Sending reset link...'
                            : 'Email password reset link'
                    }}
                </Button>
            </div>
        </Form>

        <div
            class="border-t border-line pt-5 text-center text-sm text-signal-muted"
        >
            <span>Or, return to </span>
            <TextLink :href="login()" class="text-signal">log in</TextLink>
        </div>
    </div>
</template>
