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
        class="mb-6 flex items-start gap-3 border border-line bg-raised px-4 py-3 text-left text-sm leading-relaxed text-document"
    >
        <Check
            class="mt-0.5 size-4 shrink-0 text-document"
            aria-hidden="true"
        />
        <span>
            A new verification link has been sent to the email address you
            provided during registration.
        </span>
    </div>

    <Form
        v-bind="send.form()"
        class="grid gap-4 border-t border-line pt-6 text-left"
        v-slot="{ processing }"
    >
        <Button
            type="submit"
            variant="secondary"
            class="vellum-button-quiet focus-ring h-11 w-full rounded-none bg-transparent text-sm"
            :disabled="processing"
            :aria-busy="processing"
        >
            <Spinner v-if="processing" />
            {{ processing ? 'Sending...' : 'Resend verification email' }}
        </Button>

        <TextLink
            :href="logout()"
            as="button"
            class="vellum-meta focus-ring mx-auto block !text-ash hover:!text-document"
        >
            Log out
        </TextLink>
    </Form>
</template>
