<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import SecurityController from '@/actions/App/Http/Controllers/Settings/SecurityController';
import InputError from '@/components/InputError.vue';
import PasswordInput from '@/components/PasswordInput.vue';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { edit } from '@/routes/security';
/* @chisel-passkeys */
import type { Props as ManagePasskeysProps } from '@/components/ManagePasskeys.vue';
import ManagePasskeys from '@/components/ManagePasskeys.vue';
/* @end-chisel-passkeys */
/* @chisel-2fa */
import type { Props as ManageTwoFactorProps } from '@/components/ManageTwoFactor.vue';
import ManageTwoFactor from '@/components/ManageTwoFactor.vue';
/* @end-chisel-2fa */

// oxfmt-ignore
type Props = {
    passwordRules: string;
} /* @chisel-passkeys */ & ManagePasskeysProps /* @end-chisel-passkeys */ /* @chisel-2fa */ &
    ManageTwoFactorProps; /* @end-chisel-2fa */

const props = defineProps<Props>();

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Security settings',
                href: edit(),
            },
        ],
    },
});

const labelClass = 'text-xs font-medium text-document';
const inputClass =
    'vellum-input focus-ring h-10 rounded-none pr-10 pl-3 text-sm text-document shadow-none';
const buttonClass = 'vellum-button focus-ring h-10 rounded-none px-5 text-sm';
</script>

<template>
    <Head title="Security settings" />

    <h1 class="sr-only">Security settings</h1>

    <div class="space-y-10">
        <section aria-labelledby="password-heading">
            <h2
                id="password-heading"
                class="vellum-title text-xl text-document"
            >
                Update password
            </h2>
            <p class="mt-2 text-sm leading-relaxed text-ash">
                Ensure your account is using a long, random password to stay
                secure
            </p>

            <Form
                v-bind="SecurityController.update.form()"
                :options="{
                    preserveScroll: true,
                }"
                reset-on-success
                :reset-on-error="[
                    'password',
                    'password_confirmation',
                    'current_password',
                ]"
                class="mt-6 grid gap-5"
                v-slot="{ errors, processing }"
            >
                <div class="grid gap-2">
                    <Label for="current_password" :class="labelClass">
                        Current password
                    </Label>
                    <PasswordInput
                        id="current_password"
                        name="current_password"
                        :class="inputClass"
                        autocomplete="current-password"
                        placeholder="Current password"
                    />
                    <InputError
                        class="mt-1"
                        :message="errors.current_password"
                    />
                </div>

                <div class="grid gap-2">
                    <Label for="password" :class="labelClass">
                        New password
                    </Label>
                    <PasswordInput
                        id="password"
                        name="password"
                        :class="inputClass"
                        autocomplete="new-password"
                        placeholder="New password"
                        :passwordrules="props.passwordRules"
                    />
                    <InputError class="mt-1" :message="errors.password" />
                </div>

                <div class="grid gap-2">
                    <Label for="password_confirmation" :class="labelClass">
                        Confirm password
                    </Label>
                    <PasswordInput
                        id="password_confirmation"
                        name="password_confirmation"
                        :class="inputClass"
                        autocomplete="new-password"
                        placeholder="Confirm password"
                        :passwordrules="props.passwordRules"
                    />
                    <InputError
                        class="mt-1"
                        :message="errors.password_confirmation"
                    />
                </div>

                <div class="flex items-center gap-4 pt-1">
                    <Button
                        :class="buttonClass"
                        :disabled="processing"
                        data-test="update-password-button"
                    >
                        Save
                    </Button>
                </div>
            </Form>
        </section>

        <!-- @chisel-2fa -->
        <section
            v-if="props.canManageTwoFactor"
            class="border-t border-line pt-8"
            aria-label="Two-factor authentication"
        >
            <ManageTwoFactor
                :canManageTwoFactor="canManageTwoFactor"
                :requiresConfirmation="requiresConfirmation"
                :twoFactorEnabled="twoFactorEnabled"
            />
        </section>
        <!-- @end-chisel-2fa -->

        <!-- @chisel-passkeys -->
        <section
            v-if="props.canManagePasskeys"
            class="border-t border-line pt-8"
            aria-label="Passkeys"
        >
            <ManagePasskeys
                :canManagePasskeys="canManagePasskeys"
                :passkeys="passkeys"
            />
        </section>
        <!-- @end-chisel-passkeys -->
    </div>
</template>
