<script setup lang="ts">
import { Form, Head, setLayoutProps } from '@inertiajs/vue3';
import { computed, ref, watchEffect } from 'vue';
import { AlertCircle } from '@lucide/vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import {
    InputOTP,
    InputOTPGroup,
    InputOTPSlot,
} from '@/components/ui/input-otp';
import { store } from '@/routes/two-factor/login';
import type { TwoFactorConfigContent } from '@/types';

const showRecoveryInput = ref<boolean>(false);
const code = ref<string>('');

const authConfigContent = computed<TwoFactorConfigContent>(() => {
    if (showRecoveryInput.value) {
        return {
            title: 'Recovery code',
            description:
                'Please confirm access to your account by entering one of your emergency recovery codes.',
            buttonText: 'login using an authentication code',
        };
    }

    return {
        title: 'Authentication code',
        description:
            'Enter the authentication code provided by your authenticator application.',
        buttonText: 'login using a recovery code',
    };
});

watchEffect(() => {
    setLayoutProps({
        title: authConfigContent.value.title,
        description: authConfigContent.value.description,
    });
});

const toggleRecoveryMode = (clearErrors: () => void): void => {
    showRecoveryInput.value = !showRecoveryInput.value;
    clearErrors();
    code.value = '';
};

const inputClass =
    'h-11 rounded-none border-line bg-paper-deep text-signal placeholder:text-signal-muted dark:bg-paper-deep focus-visible:border-signal focus-visible:ring-signal/25';
const labelClass =
    'font-mono text-[0.68rem] font-normal tracking-[0.16em] text-signal uppercase';
const submitClass =
    'mt-1 h-11 w-full border border-signal bg-signal font-mono text-[0.72rem] tracking-[0.16em] text-signal-invert uppercase hover:bg-signal/85 focus-visible:ring-signal disabled:opacity-60';
const errorClass = '[&_p]:text-signal!';
const toggleClass =
    'text-signal underline decoration-signal-line underline-offset-4 transition-colors hover:decoration-signal focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-signal';
</script>

<template>
    <Head title="Two-factor authentication" />

    <div class="grid gap-6">
        <template v-if="!showRecoveryInput">
            <Form
                v-bind="store.form()"
                class="grid gap-5"
                reset-on-error
                @error="code = ''"
                #default="{ errors, processing, clearErrors }"
            >
                <input type="hidden" name="code" :value="code" />
                <div class="grid gap-2">
                    <Label for="otp" :class="labelClass"
                        >Authentication code</Label
                    >
                    <div class="flex w-full items-center justify-center">
                        <InputOTP
                            id="otp"
                            v-model="code"
                            :maxlength="6"
                            :disabled="processing"
                            autofocus
                            class="w-full justify-center"
                            :aria-invalid="Boolean(errors.code)"
                            aria-describedby="otp-error"
                        >
                            <InputOTPGroup>
                                <InputOTPSlot
                                    v-for="index in 6"
                                    :key="index"
                                    :index="index - 1"
                                    class="size-11 border-line bg-paper-deep text-signal first:border-l data-[active=true]:border-signal data-[active=true]:ring-signal/25"
                                />
                            </InputOTPGroup>
                        </InputOTP>
                    </div>
                    <div
                        id="otp-error"
                        :class="errorClass"
                        class="flex min-h-5 items-start gap-2"
                        aria-live="polite"
                    >
                        <AlertCircle
                            v-if="errors.code"
                            class="mt-0.5 size-3.5 shrink-0 text-signal"
                            aria-hidden="true"
                        />
                        <InputError :message="errors.code" />
                    </div>
                </div>
                <Button
                    type="submit"
                    :class="submitClass"
                    :disabled="processing"
                    :aria-busy="processing"
                >
                    <Spinner v-if="processing" />
                    {{ processing ? 'Verifying...' : 'Continue' }}
                </Button>
                <div
                    class="border-t border-line pt-5 text-center text-sm text-signal-muted"
                >
                    <span>or you can </span>
                    <button
                        type="button"
                        :class="toggleClass"
                        @click="() => toggleRecoveryMode(clearErrors)"
                    >
                        {{ authConfigContent.buttonText }}
                    </button>
                </div>
            </Form>
        </template>

        <template v-else>
            <Form
                v-bind="store.form()"
                class="grid gap-5"
                reset-on-error
                #default="{ errors, processing, clearErrors }"
            >
                <div class="grid gap-2">
                    <Label for="recovery_code" :class="labelClass">
                        Recovery code
                    </Label>
                    <Input
                        id="recovery_code"
                        name="recovery_code"
                        type="text"
                        placeholder="Enter recovery code"
                        v-focus
                        required
                        :class="inputClass"
                        :aria-invalid="Boolean(errors.recovery_code)"
                        aria-describedby="recovery-code-error"
                    />
                    <div
                        id="recovery-code-error"
                        :class="errorClass"
                        class="flex min-h-5 items-start gap-2"
                        aria-live="polite"
                    >
                        <AlertCircle
                            v-if="errors.recovery_code"
                            class="mt-0.5 size-3.5 shrink-0 text-signal"
                            aria-hidden="true"
                        />
                        <InputError :message="errors.recovery_code" />
                    </div>
                </div>
                <Button
                    type="submit"
                    :class="submitClass"
                    :disabled="processing"
                    :aria-busy="processing"
                >
                    <Spinner v-if="processing" />
                    {{ processing ? 'Verifying...' : 'Continue' }}
                </Button>

                <div
                    class="border-t border-line pt-5 text-center text-sm text-signal-muted"
                >
                    <span>or you can </span>
                    <button
                        type="button"
                        :class="toggleClass"
                        @click="() => toggleRecoveryMode(clearErrors)"
                    >
                        {{ authConfigContent.buttonText }}
                    </button>
                </div>
            </Form>
        </template>
    </div>
</template>
