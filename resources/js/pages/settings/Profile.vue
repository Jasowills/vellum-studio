<script setup lang="ts">
import { Form, Head, usePage } from '@inertiajs/vue3';
/* @chisel-email-verification */
import { Link } from '@inertiajs/vue3';
/* @end-chisel-email-verification */
import { computed } from 'vue';
import ProfileController from '@/actions/App/Http/Controllers/Settings/ProfileController';
import DeleteUser from '@/components/DeleteUser.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { edit } from '@/routes/profile';
/* @chisel-email-verification */
import { send } from '@/routes/verification';
/* @end-chisel-email-verification */

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Profile settings',
                href: edit(),
            },
        ],
    },
});

const page = usePage();
const user = computed(() => page.props.auth.user);

const labelClass = 'text-xs font-medium text-document';
const inputClass =
    'vellum-input focus-ring h-10 rounded-none px-3 text-sm text-document shadow-none';
const buttonClass = 'vellum-button focus-ring h-10 rounded-none px-5 text-sm';
</script>

<template>
    <Head title="Profile settings" />

    <h1 class="sr-only">Profile settings</h1>

    <div class="space-y-10">
        <section aria-labelledby="profile-heading">
            <h2 id="profile-heading" class="vellum-title text-xl text-document">
                Profile
            </h2>
            <p class="mt-2 text-sm leading-relaxed text-ash">
                Update your name and email address
            </p>

            <Form
                v-bind="ProfileController.update.form()"
                class="mt-6 grid gap-5"
                v-slot="{ errors, processing }"
            >
                <div class="grid gap-2">
                    <Label for="name" :class="labelClass">Name</Label>
                    <Input
                        id="name"
                        :class="inputClass"
                        name="name"
                        :default-value="user.name"
                        required
                        autocomplete="name"
                        placeholder="Full name"
                    />
                    <InputError class="mt-1" :message="errors.name" />
                </div>

                <div class="grid gap-2">
                    <Label for="email" :class="labelClass">Email address</Label>
                    <Input
                        id="email"
                        type="email"
                        :class="inputClass"
                        name="email"
                        :default-value="user.email"
                        required
                        autocomplete="username"
                        placeholder="Email address"
                    />
                    <InputError class="mt-1" :message="errors.email" />
                </div>

                <!-- @chisel-email-verification -->
                <div
                    v-if="page.props.mustVerifyEmail && !user.email_verified_at"
                    class="border-l-2 border-document bg-surface px-4 py-3 text-sm leading-relaxed text-ash"
                >
                    <p>
                        Your email address is unverified.
                        <Link
                            :href="send()"
                            as="button"
                            class="focus-ring text-document underline decoration-line underline-offset-4 hover:decoration-document"
                        >
                            Click here to re-send the verification email.
                        </Link>
                    </p>

                    <div
                        v-if="page.props.status === 'verification-link-sent'"
                        class="mt-2 border-l-2 border-document pl-2 text-sm text-document"
                    >
                        A new verification link has been sent to your email
                        address.
                    </div>
                </div>
                <!-- @end-chisel-email-verification -->

                <div class="flex items-center gap-4 pt-1">
                    <Button
                        :class="buttonClass"
                        :disabled="processing"
                        data-test="update-profile-button"
                    >
                        Save
                    </Button>
                </div>
            </Form>
        </section>

        <section class="border-t border-line pt-8" aria-label="Delete account">
            <DeleteUser />
        </section>
    </div>
</template>
