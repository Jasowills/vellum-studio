<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import { Check } from '@lucide/vue';
import TextLink from '@/components/TextLink.vue';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { logout } from '@/routes';
import { send } from '@/routes/verification';

defineOptions({
    layout: {
        title: 'Email verification',
        description:
            'Please verify your email address by clicking on the link we just emailed to you.',
    },
});

defineProps<{
    status?: string;
}>();
</script>

<template>
    <Head title="Email verification" />

    <div
        v-if="status === 'verification-link-sent'"
        role="status"
        class="mb-6 flex items-start gap-3 border border-line bg-paper px-4 py-3 text-left text-sm leading-relaxed text-signal"
    >
        <Check class="mt-0.5 size-4 shrink-0 text-signal" aria-hidden="true" />
        <span>
            A new verification link has been sent to the email address you
            provided during registration.
        </span>
    </div>

    <Form
        v-bind="send.form()"
        class="grid gap-6 border-t border-line pt-6 text-center"
        v-slot="{ processing }"
    >
        <Button
            type="submit"
            variant="secondary"
            :class="'h-11 w-full border border-line font-mono text-[0.72rem] tracking-[0.16em] text-signal uppercase hover:border-signal focus-visible:ring-signal disabled:opacity-60'"
            :disabled="processing"
            :aria-busy="processing"
        >
            <Spinner v-if="processing" />
            {{ processing ? 'Sending...' : 'Resend verification email' }}
        </Button>

        <TextLink
            :href="logout()"
            as="button"
            class="mx-auto block font-mono text-[0.68rem] tracking-[0.16em] text-signal uppercase"
        >
            Log out
        </TextLink>
    </Form>
</template>
