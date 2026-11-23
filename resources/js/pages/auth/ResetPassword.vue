<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import { ref } from 'vue';
import { AlertCircle } from '@lucide/vue';
import InputError from '@/components/InputError.vue';
import PasswordInput from '@/components/PasswordInput.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { update } from '@/routes/password';

defineOptions({
    layout: {
        title: 'Reset password',
        description: 'Please enter your new password below',
    },
});

const props = defineProps<{
    token: string;
    email: string;
    passwordRules: string;
}>();

const inputEmail = ref(props.email);

const inputClass =
    'h-11 rounded-none border-line bg-paper-deep text-signal placeholder:text-signal-muted dark:bg-paper-deep focus-visible:border-signal focus-visible:ring-signal/25';
const labelClass =
    'font-mono text-[0.68rem] font-normal tracking-[0.16em] text-signal uppercase';
const submitClass =
    'mt-1 h-11 w-full border border-signal bg-signal font-mono text-[0.72rem] tracking-[0.16em] text-signal-invert uppercase hover:bg-signal/85 focus-visible:ring-signal disabled:opacity-60';
const errorClass = '[&_p]:text-signal!';
</script>

<template>
    <Head title="Reset password" />

    <Form
        v-bind="update.form()"
        :transform="(data) => ({ ...data, token, email })"
        :reset-on-success="['password', 'password_confirmation']"
        v-slot="{ errors, processing }"
    >
        <div class="grid gap-5">
            <div class="grid gap-2">
                <Label for="email" :class="labelClass">Email</Label>
                <Input
                    id="email"
                    type="email"
                    name="email"
                    autocomplete="email"
                    v-model="inputEmail"
                    readonly
                    :class="[inputClass, 'text-signal-muted']"
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
                <Label for="password" :class="labelClass">Password</Label>
                <PasswordInput
                    id="password"
                    name="password"
                    autocomplete="new-password"
                    autofocus
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
                        class="mt-0.5 size-3.5 shrink-0 text-signal"
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
                    name="password_confirmation"
                    autocomplete="new-password"
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
                        class="mt-0.5 size-3.5 shrink-0 text-signal"
                        aria-hidden="true"
                    />
                    <InputError :message="errors.password_confirmation" />
                </div>
            </div>

            <Button
                type="submit"
                :class="submitClass"
                :disabled="processing"
                :aria-busy="processing"
                data-test="reset-password-button"
            >
                <Spinner v-if="processing" />
                {{ processing ? 'Resetting password...' : 'Reset password' }}
            </Button>
        </div>
    </Form>
</template>
