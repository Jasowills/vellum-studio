<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import { AlertCircle } from '@lucide/vue';
import InputError from '@/components/InputError.vue';
import PasswordInput from '@/components/PasswordInput.vue';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { store } from '@/routes/password/confirm';
/* @chisel-passkeys */
import {
    index as confirmOptions,
    store as confirmStore,
} from '@/actions/Laravel/Passkeys/Http/Controllers/PasskeyConfirmationController';
import PasskeyVerify from '@/components/PasskeyVerify.vue';
/* @end-chisel-passkeys */

defineOptions({
    layout: {
        title: 'Confirm password',
        description:
            'This is a secure area of the application. Please confirm your password before continuing.',
    },
});

const inputClass =
    'h-11 rounded-none border-line bg-paper-deep text-signal placeholder:text-signal-muted dark:bg-paper-deep focus-visible:border-signal focus-visible:ring-signal/25';
const labelClass =
    'font-mono text-[0.68rem] font-normal tracking-[0.16em] text-signal uppercase';
const submitClass =
    'mt-1 h-11 w-full border border-signal bg-signal font-mono text-[0.72rem] tracking-[0.16em] text-signal-invert uppercase hover:bg-signal/85 focus-visible:ring-signal disabled:opacity-60';
const errorClass = '[&_p]:text-signal!';
</script>

<template>
    <Head title="Confirm password" />

    <!-- @chisel-passkeys -->
    <PasskeyVerify
        :routes="{
            options: confirmOptions(),
            submit: confirmStore(),
        }"
        label="Confirm with passkey"
        loading-label="Confirming..."
        separator="Or confirm with password"
    />
    <!-- @end-chisel-passkeys -->

    <Form
        v-bind="store.form()"
        reset-on-success
        v-slot="{ errors, processing }"
    >
        <div class="grid gap-5">
            <div class="grid gap-2">
                <Label for="password" :class="labelClass">Password</Label>
                <PasswordInput
                    id="password"
                    name="password"
                    class="mt-1 block w-full"
                    required
                    autocomplete="current-password"
                    autofocus
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
                <Button
                    type="submit"
                    :class="submitClass"
                    :disabled="processing"
                    :aria-busy="processing"
                    data-test="confirm-password-button"
                >
                    <Spinner v-if="processing" />
                    {{ processing ? 'Confirming...' : 'Confirm password' }}
                </Button>
            </div>
        </div>
    </Form>
</template>
