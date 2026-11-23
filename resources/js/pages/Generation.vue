<script setup lang="ts">
defineOptions({ inheritAttrs: false });

import { Head, Link, router } from '@inertiajs/vue3';
import {
    AlertTriangle,
    ArrowLeft,
    Check,
    Clock3,
    Copy,
    Hash,
    ImageOff,
    RefreshCw,
    Sparkles,
} from '@lucide/vue';
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import StatusBadge from '@/components/StatusBadge.vue';
import {
    copyText,
    formatDateTime,
    formatRelative,
    generationAlt,
    qualityLabel,
    ratioLabel,
} from '@/lib/vellum';
import type { Generation } from '@/types';

const props = defineProps<{
    generation: Generation;
}>();

const promptCopied = ref(false);
const idCopied = ref(false);
const refreshed = ref(false);
const refreshing = ref(false);
const imageFailed = ref(false);

const active = computed(() =>
    ['pending', 'queued', 'processing'].includes(props.generation.status),
);

const imageUrl = computed(() => props.generation.image.url);
const canShowImage = computed(
    () =>
        Boolean(imageUrl.value) &&
        props.generation.image.available &&
        !imageFailed.value,
);

function resetImageFailure(): void {
    imageFailed.value = false;
}

watch(imageUrl, resetImageFailure);
watch(() => props.generation.image.available, resetImageFailure);

const imageFallback = computed(() => {
    if (imageFailed.value) {
        return {
            title: 'The plate could not be loaded.',
            detail: 'The image route did not return a usable file. Refresh to try again.',
        };
    }

    if (props.generation.image.expired) {
        return {
            title: 'The signed link expired.',
            detail: 'No archived copy was saved before the provider link lapsed.',
        };
    }

    return {
        title: 'No image was produced.',
        detail: 'The provider returned no image for this attempt. Failed generations are never charged.',
    };
});

const charge = computed(() => props.generation.charge);

const recordedCost = computed(() =>
    charge.value || !props.generation.cost.customer_charge
        ? null
        : props.generation.cost,
);

const usage = computed(() => charge.value?.tokens ?? props.generation.usage);
const hasUsage = computed(() => (usage.value.total_tokens ?? 0) > 0);

/* provider_cost arrives from the resource as an already formatted string. */
const providerCost = computed(
    () => props.generation.provider?.provider_cost ?? null,
);

const tokenRows = computed(() =>
    [
        { label: 'Input text', value: usage.value.input_text_tokens },
        { label: 'Input images', value: usage.value.input_image_tokens },
        { label: 'Output', value: usage.value.output_tokens },
        { label: 'Reasoning', value: usage.value.reasoning_tokens },
        { label: 'Total', value: usage.value.total_tokens },
    ].map((row) => ({
        label: row.label,
        display: (row.value ?? 0).toLocaleString(),
    })),
);

const timeline = computed(() => {
    const events = props.generation.timeline;

    return [
        { label: 'Composed', value: events.submitted_at },
        { label: 'Accepted', value: events.accepted_at },
        { label: 'In the studio', value: events.processing_at },
        { label: 'Finished', value: events.completed_at },
        { label: 'Ledger settled', value: events.settled_at },
    ].filter((event) => event.value !== null);
});

const hasTimeline = computed(() => timeline.value.length > 0);

let pollTimer: ReturnType<typeof setInterval> | undefined;
let promptTimer: number | undefined;
let idTimer: number | undefined;
let refreshTimer: number | undefined;

function stopPolling(): void {
    if (pollTimer !== undefined) {
        window.clearInterval(pollTimer);
        pollTimer = undefined;
    }
}

function startPolling(): void {
    if (pollTimer !== undefined) {
        return;
    }

    pollTimer = window.setInterval(() => {
        if (
            active.value &&
            document.visibilityState === 'visible' &&
            !refreshing.value
        ) {
            void refresh();
        }
    }, 5000);
}

function syncPolling(): void {
    if (active.value && document.visibilityState === 'visible') {
        startPolling();
    } else {
        stopPolling();
    }
}

async function refresh(): Promise<void> {
    if (refreshing.value) {
        return;
    }

    refreshing.value = true;

    router.reload({
        only: ['generation'],
        onFinish: () => {
            refreshing.value = false;
        },
        onSuccess: () => {
            refreshed.value = true;
            window.clearTimeout(refreshTimer);
            refreshTimer = window.setTimeout(() => {
                refreshed.value = false;
            }, 1600);
        },
    });
}

async function copyPrompt(): Promise<void> {
    if (await copyText(props.generation.prompt)) {
        promptCopied.value = true;
        window.clearTimeout(promptTimer);
        promptTimer = window.setTimeout(() => {
            promptCopied.value = false;
        }, 2000);
    }
}

async function copyId(): Promise<void> {
    if (await copyText(props.generation.id)) {
        idCopied.value = true;
        window.clearTimeout(idTimer);
        idTimer = window.setTimeout(() => {
            idCopied.value = false;
        }, 2000);
    }
}

watch(active, syncPolling);

onMounted(() => {
    document.addEventListener('visibilitychange', syncPolling);
    syncPolling();
});

onBeforeUnmount(() => {
    document.removeEventListener('visibilitychange', syncPolling);
    stopPolling();
    window.clearTimeout(promptTimer);
    window.clearTimeout(idTimer);
    window.clearTimeout(refreshTimer);
});
</script>

<template>
    <Head :title="`Plate ${generation.id}`" />

    <div class="min-w-0 space-y-8 pb-12 text-document lg:space-y-10">
        <div
            class="flex min-w-0 flex-col gap-3 border-b border-line pb-3 sm:flex-row sm:items-center sm:justify-between"
        >
            <Link
                href="/library"
                class="focus-ring group inline-flex items-center gap-2 font-sans text-xs text-ash transition-colors hover:text-document"
            >
                <ArrowLeft
                    class="size-3.5 transition-transform group-hover:-translate-x-0.5 motion-reduce:transform-none"
                    aria-hidden="true"
                />
                Index / Library
            </Link>

            <div class="flex min-w-0 flex-wrap items-center gap-4">
                <span
                    v-if="refreshed"
                    class="font-mono text-[0.6rem] text-document"
                    role="status"
                    aria-live="polite"
                    >Updated</span
                >
                <span
                    v-if="active"
                    class="inline-flex items-center gap-2 font-mono text-[0.6rem] text-ash"
                >
                    <span
                        class="status-dot animate-breathe bg-document"
                        aria-hidden="true"
                    />
                    Live · checks every 5s
                </span>
                <button
                    type="button"
                    class="focus-ring vellum-button-quiet inline-flex items-center gap-2 px-3 py-1.5 font-sans text-xs text-document disabled:cursor-wait disabled:opacity-60"
                    :disabled="refreshing"
                    @click="refresh"
                >
                    <RefreshCw
                        class="size-3 motion-reduce:animate-none"
                        :class="refreshing ? 'animate-spin' : ''"
                        aria-hidden="true"
                    />
                    Refresh
                </button>
            </div>
        </div>

        <header
            class="flex min-w-0 flex-col gap-5 lg:flex-row lg:items-end lg:justify-between"
        >
            <div class="min-w-0 space-y-3">
                <div class="flex min-w-0 flex-wrap items-center gap-3">
                    <StatusBadge
                        :label="generation.status_label"
                        :tone="generation.status_tone"
                        :pulse="active"
                    />
                    <button
                        type="button"
                        class="focus-ring inline-flex max-w-full items-center gap-2 border border-line px-2 py-1 font-mono text-[0.6rem] text-ash transition-colors hover:border-document hover:text-document"
                        :aria-label="
                            idCopied
                                ? `Generation ID ${generation.id} copied`
                                : `Copy generation ID ${generation.id}`
                        "
                        @click="copyId"
                    >
                        <Hash class="size-3 shrink-0" aria-hidden="true" />
                        <span class="min-w-0 truncate">{{
                            generation.id
                        }}</span>
                        <Check
                            v-if="idCopied"
                            class="size-3 shrink-0"
                            aria-hidden="true"
                        />
                        <Copy
                            v-else
                            class="size-3 shrink-0"
                            aria-hidden="true"
                        />
                    </button>
                </div>

                <h1
                    class="vellum-title max-w-[24ch] text-[clamp(1.9rem,3.4vw,3rem)] text-document"
                >
                    <template v-if="active">The plate is developing.</template>
                    <template v-else-if="generation.status === 'failed'">
                        This plate did not arrive.
                    </template>
                    <template v-else>Your plate is ready.</template>
                </h1>

                <p class="max-w-xl text-sm leading-relaxed text-ash">
                    <template v-if="active">
                        The provider is rendering this plate. This page checks
                        again every five seconds while it is visible, and a
                        signed webhook settles the charge when the work
                        finishes. You may leave safely.
                    </template>
                    <template v-else-if="generation.status === 'failed'">
                        {{
                            generation.failure?.message ??
                            'The provider could not complete this generation.'
                        }}
                        Nothing was charged.
                    </template>
                    <template v-else>
                        Archived privately by Vellum and served from your
                        library, even after the provider’s signed link expires.
                    </template>
                </p>
            </div>

            <div class="flex shrink-0 flex-wrap gap-2">
                <button
                    type="button"
                    class="focus-ring vellum-button-quiet inline-flex items-center gap-2 px-3.5 py-2 font-sans text-xs text-document"
                    :aria-live="promptCopied ? 'polite' : undefined"
                    @click="copyPrompt"
                >
                    <Check
                        v-if="promptCopied"
                        class="size-3.5"
                        aria-hidden="true"
                    />
                    <Copy v-else class="size-3.5" aria-hidden="true" />
                    {{ promptCopied ? 'Prompt copied' : 'Copy prompt' }}
                </button>
                <Link
                    :href="generation.prefill_url"
                    class="focus-ring vellum-button px-3.5 py-2 font-sans text-xs"
                >
                    <Sparkles class="size-3.5" aria-hidden="true" />
                    {{
                        generation.can_retry
                            ? 'Retry this setup'
                            : 'Make a variation'
                    }}
                </Link>
            </div>
        </header>

        <div
            class="grid min-w-0 gap-8 lg:grid-cols-[minmax(0,1fr)_19rem] lg:gap-10 xl:grid-cols-[minmax(0,1fr)_21rem]"
        >
            <div class="min-w-0 space-y-8">
                <figure class="min-w-0 border border-line bg-void">
                    <div
                        class="flex min-w-0 flex-wrap items-center justify-between gap-3 border-b border-line px-4 py-2.5"
                    >
                        <p class="vellum-meta text-document">
                            Plate / {{ generation.id }}
                        </p>
                        <p class="vellum-meta text-stone">
                            {{ ratioLabel(generation.size) }} ·
                            {{ qualityLabel(generation.quality) }} ·
                            {{ generation.size }}
                        </p>
                    </div>

                    <div
                        class="relative flex min-h-[20rem] items-center justify-center overflow-hidden bg-void px-4 py-8 sm:min-h-[28rem] lg:min-h-[38rem] xl:min-h-[46rem]"
                    >
                        <img
                            v-if="canShowImage"
                            :src="imageUrl ?? undefined"
                            :alt="generationAlt(generation)"
                            class="max-h-[68vh] w-full object-contain grayscale"
                            @error="imageFailed = true"
                        />

                        <div
                            v-else-if="active"
                            class="flex max-w-sm flex-col items-center gap-4 text-center"
                        >
                            <span
                                class="font-mono text-[0.65rem] tracking-[0.16em] text-document"
                                >Developing</span
                            >
                            <span
                                class="h-px w-12 bg-line"
                                aria-hidden="true"
                            />
                            <p class="text-xs leading-relaxed text-stone">
                                The provider typically returns within 5–30
                                seconds. No image is shown until it exists.
                            </p>
                        </div>

                        <div
                            v-else
                            class="flex max-w-sm flex-col items-center gap-4 text-center"
                        >
                            <ImageOff
                                class="size-7 text-stone"
                                aria-hidden="true"
                            />
                            <p class="text-sm text-document">
                                {{ imageFallback.title }}
                            </p>
                            <p class="text-xs leading-relaxed text-stone">
                                {{ imageFallback.detail }}
                            </p>
                        </div>
                    </div>

                    <figcaption
                        class="flex min-w-0 flex-wrap items-center justify-between gap-3 border-t border-line px-4 py-2.5 font-mono text-[0.58rem] leading-snug text-stone"
                    >
                        <span>Source / Vellum archive</span>
                        <span v-if="generation.image.expires_at">
                            Provider link until
                            {{ formatRelative(generation.image.expires_at) }}
                        </span>
                        <span v-else>Provider link / recorded</span>
                    </figcaption>
                </figure>

                <div class="min-w-0 border-t border-line">
                    <section class="py-6" aria-labelledby="prompt-heading">
                        <div class="flex items-baseline justify-between gap-4">
                            <h2
                                id="prompt-heading"
                                class="vellum-meta text-document"
                            >
                                Prompt
                            </h2>
                            <span class="ledger text-[0.62rem] text-stone">
                                {{ generation.prompt.length.toLocaleString() }}
                                characters
                            </span>
                        </div>
                        <p
                            class="mt-4 font-display text-lg leading-[1.6] tracking-[-0.015em] whitespace-pre-wrap text-document sm:text-xl"
                        >
                            {{ generation.prompt }}
                        </p>

                        <div
                            v-if="generation.reference_images.length > 0"
                            class="mt-6 border-t border-line pt-4"
                        >
                            <h3 class="vellum-meta">References</h3>
                            <ul class="mt-3 space-y-2">
                                <li
                                    v-for="(
                                        reference, index
                                    ) in generation.reference_images"
                                    :key="reference"
                                    class="flex min-w-0 items-center gap-3"
                                >
                                    <span
                                        class="ledger shrink-0 text-[0.6rem] text-stone"
                                    >
                                        {{ String(index + 1).padStart(2, '0') }}
                                    </span>
                                    <a
                                        :href="reference"
                                        target="_blank"
                                        rel="noreferrer noopener"
                                        class="link-underline min-w-0 truncate font-mono text-[0.65rem] text-ash"
                                        >{{ reference }}</a
                                    >
                                </li>
                            </ul>
                        </div>
                    </section>

                    <section
                        class="border-t border-line py-6"
                        aria-labelledby="ledger-heading"
                    >
                        <div class="flex items-baseline justify-between gap-4">
                            <h2
                                id="ledger-heading"
                                class="vellum-meta text-document"
                            >
                                Charge & usage
                            </h2>
                            <span class="vellum-meta text-stone"
                                >Exact accounting</span
                            >
                        </div>

                        <dl
                            v-if="charge || hasUsage"
                            class="mt-4 grid gap-x-8 sm:grid-cols-2"
                        >
                            <div
                                v-for="row in tokenRows"
                                :key="row.label"
                                class="flex items-baseline justify-between gap-4 border-b border-line py-2.5"
                            >
                                <dt class="vellum-meta">{{ row.label }}</dt>
                                <dd class="ledger text-sm text-document">
                                    {{ row.display }}
                                </dd>
                            </div>
                        </dl>

                        <div
                            v-if="charge || recordedCost"
                            class="mt-6 flex min-w-0 flex-wrap items-end justify-between gap-5 border-t border-line pt-4"
                        >
                            <div class="min-w-0">
                                <p class="vellum-meta">Charged to you</p>
                                <p
                                    class="mt-1 font-mono text-[0.6rem] leading-relaxed text-stone"
                                >
                                    Incl.
                                    {{ generation.estimate.markup_percent }}%
                                    studio fee<span v-if="charge">
                                        · settled
                                        {{
                                            formatDateTime(charge.settled_at)
                                        }}</span
                                    ><span v-else> · recorded</span>
                                </p>
                            </div>
                            <div class="text-right">
                                <p
                                    class="ledger text-2xl text-document sm:text-3xl"
                                >
                                    {{
                                        charge?.customer_charge ??
                                        recordedCost?.customer_charge
                                    }}
                                </p>
                                <p
                                    class="ledger mt-1 text-[0.62rem] text-stone"
                                >
                                    {{
                                        charge?.customer_charge_exact ??
                                        recordedCost?.customer_charge_exact
                                    }}
                                </p>
                            </div>
                        </div>

                        <div
                            v-if="providerCost"
                            class="mt-4 flex items-baseline justify-between gap-4 border-t border-line pt-3"
                        >
                            <span class="vellum-meta">Provider cost</span>
                            <span class="ledger text-sm text-document">{{
                                providerCost
                            }}</span>
                        </div>

                        <div v-else-if="active" class="mt-5 space-y-3">
                            <p class="text-sm leading-relaxed text-ash">
                                Token usage and the final charge are only known
                                once the provider finishes. The figures below
                                are the estimate shown before submission.
                            </p>
                            <dl class="space-y-2.5 font-mono text-[0.7rem]">
                                <div
                                    class="flex justify-between gap-4 border-b border-line pb-2.5"
                                >
                                    <dt class="text-ash">Estimated</dt>
                                    <dd class="ledger text-document">
                                        {{
                                            generation.estimate.customer_charge
                                                .min ?? '—'
                                        }}
                                        —
                                        {{
                                            generation.estimate.customer_charge
                                                .max ?? '—'
                                        }}
                                    </dd>
                                </div>
                                <div
                                    class="flex justify-between gap-4 border-b border-line pb-2.5"
                                >
                                    <dt class="text-ash">Studio fee</dt>
                                    <dd class="ledger text-document">
                                        {{
                                            generation.estimate.markup_percent
                                        }}%
                                    </dd>
                                </div>
                            </dl>
                        </div>

                        <div
                            v-else-if="!charge && !recordedCost"
                            class="mt-5 border-l border-document pl-4"
                        >
                            <p class="text-sm text-document">
                                Nothing was charged.
                            </p>
                            <p class="mt-1 text-xs leading-relaxed text-ash">
                                Failed generations are free. The setup is kept
                                so it can be retried with a fresh attempt token.
                            </p>
                        </div>
                    </section>
                </div>
            </div>

            <aside class="min-w-0 space-y-8">
                <section
                    class="border-t border-line pt-4"
                    aria-labelledby="timeline-heading"
                >
                    <div class="flex items-center justify-between gap-3">
                        <h2
                            id="timeline-heading"
                            class="vellum-meta text-document"
                        >
                            Timeline
                        </h2>
                        <Clock3
                            class="size-3.5 text-stone"
                            aria-hidden="true"
                        />
                    </div>

                    <ol v-if="hasTimeline" class="mt-4">
                        <li
                            v-for="(event, index) in timeline"
                            :key="event.label"
                            class="relative border-l border-line pb-5 pl-5 last:border-l-transparent last:pb-0"
                        >
                            <span
                                class="absolute top-1 -left-[3px] size-[5px] border"
                                :class="
                                    index === timeline.length - 1
                                        ? 'border-document bg-document'
                                        : 'border-stone bg-void'
                                "
                                aria-hidden="true"
                            />
                            <p class="text-xs text-document">
                                {{ event.label }}
                            </p>
                            <p class="ledger mt-1 text-[0.6rem] text-stone">
                                {{ formatDateTime(event.value) }}
                            </p>
                        </li>
                    </ol>

                    <p v-else class="mt-4 text-sm text-ash">
                        Waiting for the first confirmation from the provider.
                    </p>

                    <p
                        v-if="active"
                        class="mt-4 border-t border-line pt-3 font-mono text-[0.58rem] leading-relaxed text-stone"
                    >
                        Live · automatic checks every 5s while this tab is
                        visible
                    </p>
                </section>

                <section
                    class="border-t border-line pt-4"
                    aria-labelledby="setup-heading"
                >
                    <h2 id="setup-heading" class="vellum-meta text-document">
                        Setup
                    </h2>
                    <dl class="mt-4 space-y-3 font-mono text-[0.65rem]">
                        <div
                            class="flex justify-between gap-3 border-b border-line pb-3"
                        >
                            <dt class="text-ash">Proportion</dt>
                            <dd class="text-right text-document">
                                {{ ratioLabel(generation.size) }}
                            </dd>
                        </div>
                        <div
                            class="flex justify-between gap-3 border-b border-line pb-3"
                        >
                            <dt class="text-ash">Quality</dt>
                            <dd class="text-right text-document">
                                {{ qualityLabel(generation.quality) }}
                            </dd>
                        </div>
                        <div
                            class="flex justify-between gap-3 border-b border-line pb-3"
                        >
                            <dt class="text-ash">References</dt>
                            <dd class="ledger text-right text-document">
                                {{ generation.reference_images.length }}
                            </dd>
                        </div>
                        <div
                            class="flex justify-between gap-3 border-b border-line pb-3"
                        >
                            <dt class="text-ash">Composed</dt>
                            <dd class="text-right text-document">
                                {{ formatDateTime(generation.created_at) }}
                            </dd>
                        </div>
                        <div
                            class="flex justify-between gap-3 border-b border-line pb-3"
                        >
                            <dt class="text-ash">Updated</dt>
                            <dd class="text-right text-document">
                                {{ formatDateTime(generation.updated_at) }}
                            </dd>
                        </div>
                    </dl>
                </section>

                <section
                    v-if="generation.failure"
                    class="border-l border-document pl-4"
                    aria-labelledby="failure-heading"
                >
                    <div class="flex min-w-0 items-start gap-3">
                        <AlertTriangle
                            class="mt-0.5 size-4 shrink-0 text-document"
                            aria-hidden="true"
                        />
                        <div class="min-w-0">
                            <h2
                                id="failure-heading"
                                class="font-mono text-[0.6rem] text-document"
                            >
                                Failure ·
                                {{
                                    generation.failure.stage ?? 'unknown stage'
                                }}
                            </h2>
                            <p
                                class="mt-2 text-sm leading-relaxed text-document"
                            >
                                {{ generation.failure.message }}
                            </p>
                            <p class="ledger mt-3 text-[0.6rem] text-stone">
                                {{ generation.failure.code }}
                            </p>
                        </div>
                    </div>
                    <Link
                        :href="generation.prefill_url"
                        class="focus-ring vellum-button-quiet mt-5 inline-flex items-center gap-2 px-4 py-2 font-sans text-xs text-document"
                    >
                        <RefreshCw class="size-3.5" aria-hidden="true" />
                        Retry this setup
                    </Link>
                </section>

                <section
                    v-if="generation.provider"
                    class="border-t border-line pt-4"
                    aria-labelledby="provider-heading"
                >
                    <div class="flex items-baseline justify-between gap-3">
                        <h2
                            id="provider-heading"
                            class="vellum-meta text-document"
                        >
                            Operator view
                        </h2>
                        <span class="vellum-meta text-stone">Admin only</span>
                    </div>
                    <dl class="mt-4 space-y-3 font-mono text-[0.6rem]">
                        <div
                            class="flex justify-between gap-3 border-b border-line pb-2.5"
                        >
                            <dt class="text-ash">Provider id</dt>
                            <dd
                                class="max-w-[60%] truncate text-right text-document"
                            >
                                {{ generation.provider.generation_id ?? '—' }}
                            </dd>
                        </div>
                        <div
                            class="flex justify-between gap-3 border-b border-line pb-2.5"
                        >
                            <dt class="text-ash">Provider cost</dt>
                            <dd class="ledger text-right text-document">
                                {{ generation.provider.provider_cost ?? '—' }}
                            </dd>
                        </div>
                        <div
                            class="flex justify-between gap-3 border-b border-line pb-2.5"
                        >
                            <dt class="text-ash">Submits</dt>
                            <dd class="ledger text-right text-document">
                                {{ generation.provider.submit_attempts }}
                            </dd>
                        </div>
                        <div
                            class="flex justify-between gap-3 border-b border-line pb-2.5"
                        >
                            <dt class="text-ash">Reconciles</dt>
                            <dd class="ledger text-right text-document">
                                {{ generation.provider.reconcile_attempts }}
                            </dd>
                        </div>
                    </dl>
                    <pre
                        v-if="generation.provider.error"
                        class="mt-4 max-h-40 overflow-auto border border-line bg-abyss p-3 font-mono text-[0.55rem] leading-relaxed text-ash"
                        >{{
                            JSON.stringify(generation.provider.error, null, 2)
                        }}</pre>
                </section>

                <p
                    v-if="hasUsage && !providerCost"
                    class="vellum-meta leading-relaxed"
                >
                    Provider cost is visible to studio operators only. Your
                    charge includes the flat
                    {{ generation.estimate.markup_percent }}% studio fee.
                </p>
            </aside>
        </div>
    </div>
</template>
