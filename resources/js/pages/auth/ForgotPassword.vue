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
    'vellum-input focus-ring h-11 rounded-none pr-10 pl-3 text-sm text-document';
const labelClass = 'text-xs font-medium text-document';
const submitClass =
    'vellum-button focus-ring mt-2 h-11 w-full rounded-none text-sm';
const errorClass = '[&_p]:text-document!';
const quietLinkClass =
    '!text-document !decoration-line underline-offset-4 hover:!decoration-document focus-ring text-xs';
</script>

<template>
    <Head title="Forgot password" />

    <div
        v-if="status"
        role="status"
        class="mb-6 flex items-start gap-3 border border-line bg-raised px-4 py-3 text-sm leading-relaxed text-document"
    >
        <Check
            class="mt-0.5 size-4 shrink-0 text-document"
            aria-hidden="true"
        />
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
                        class="mt-0.5 size-3.5 shrink-0 text-document"
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

        <div class="border-t border-line pt-5 text-center text-sm text-ash">
            <span>Or, return to </span>
            <TextLink :href="login()" :class="quietLinkClass">log in</TextLink>
        </div>
    </div>
</template>
