<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import {
    ArrowLeft,
    Check,
    Clock3,
    Copy,
    ImageOff,
    RefreshCw,
    Sparkles,
} from '@lucide/vue';
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import StatusBadge from '@/components/StatusBadge.vue';
import {
    copyText,
    formatDateTime,
    formatRelative,
    generationAlt,
    qualityLabel,
    ratioCss,
    ratioLabel,
} from '@/lib/vellum';
import type { Generation } from '@/types';

const props = defineProps<{
    generation: Generation;
}>();

const copied = ref(false);
const refreshed = ref(false);

const active = computed(() =>
    ['pending', 'queued', 'processing'].includes(props.generation.status),
);

const charge = computed(() => props.generation.charge);
const usage = computed(() => charge.value?.tokens ?? props.generation.usage);
const hasUsage = computed(() => (usage.value.total_tokens ?? 0) > 0);
const providerCost = computed(
    () => props.generation.provider?.provider_cost ?? null,
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

function poll(): void {
    if (!active.value) {
        return;
    }

    router.reload({
        only: ['generation'],
        onSuccess: () => {
            refreshed.value = true;
            window.setTimeout(() => (refreshed.value = false), 1600);
        },
    });
}

async function copyPrompt(): Promise<void> {
    if (await copyText(props.generation.prompt)) {
        copied.value = true;
        window.setTimeout(() => (copied.value = false), 2000);
    }
}

onMounted(() => {
    pollTimer = window.setInterval(poll, 5000);
});

onBeforeUnmount(() => {
    window.clearInterval(pollTimer);
});
</script>

<template>
    <Head :title="`Plate ${generation.id}`" />

    <div class="space-y-10">
        <div class="flex flex-wrap items-center justify-between gap-4">
            <Link
                href="/library"
                class="group inline-flex items-center gap-2 font-mono text-[0.6rem] tracking-[0.14em] text-ink-soft uppercase transition-colors hover:text-vermilion"
            >
                <ArrowLeft
                    class="size-3.5 transition-transform group-hover:-translate-x-0.5"
                    aria-hidden="true"
                />
                Back to the library
            </Link>
            <div class="flex items-center gap-3">
                <span
                    v-if="refreshed"
                    class="font-mono text-[0.55rem] tracking-[0.14em] text-moss uppercase"
                    >Refreshed</span
                >
                <button
                    type="button"
                    class="focus-ring inline-flex items-center gap-2 border border-line px-3 py-1.5 font-mono text-[0.58rem] tracking-[0.1em] text-ink-soft uppercase transition-colors hover:border-ink hover:text-ink"
                    @click="poll"
                >
                    <RefreshCw class="size-3" aria-hidden="true" />
                    Refresh
                </button>
            </div>
        </div>

        <header
            class="flex flex-col justify-between gap-6 border-b border-line pb-8 lg:flex-row lg:items-end"
        >
            <div class="min-w-0 space-y-4">
                <div class="flex flex-wrap items-center gap-3">
                    <StatusBadge
                        :label="generation.status_label"
                        :tone="generation.status_tone"
                        :pulse="active"
                    />
                    <span
                        class="font-mono text-[0.58rem] tracking-[0.12em] text-ink-soft uppercase"
                    >
                        Plate {{ generation.id }}
                    </span>
                </div>
                <h1
                    class="display-title max-w-[24ch] text-[clamp(2.1rem,4vw,3.5rem)] text-ink"
                >
                    <template v-if="active">The image is developing.</template>
                    <template v-else-if="generation.status === 'failed'">
                        This plate did not arrive.
                    </template>
                    <template v-else>Your image is ready.</template>
                </h1>
                <p
                    v-if="active"
                    class="max-w-xl text-sm leading-relaxed text-ink-soft"
                >
                    Pixelforge is working. This page checks again every five
                    seconds, and a signed webhook will settle the charge the
                    moment it finishes. You may leave safely.
                </p>
                <p
                    v-else-if="generation.status === 'failed'"
                    class="max-w-xl text-sm leading-relaxed text-ink-soft"
                >
                    {{
                        generation.failure?.message ??
                        'Pixelforge could not complete this generation.'
                    }}
                    <span class="text-ink">Nothing was charged.</span>
                </p>
                <p
                    v-else
                    class="max-w-xl text-sm leading-relaxed text-ink-soft"
                >
                    Archived privately by Vellum and served through your
                    library, even after the provider’s signed link expires.
                </p>
            </div>

            <div class="flex shrink-0 flex-wrap items-center gap-2">
                <button
                    type="button"
                    class="focus-ring inline-flex items-center gap-2 border border-line px-3.5 py-2 text-xs font-medium text-ink transition-colors hover:border-ink"
                    @click="copyPrompt"
                >
                    <Check
                        v-if="copied"
                        class="size-3.5 text-moss"
                        aria-hidden="true"
                    />
                    <Copy v-else class="size-3.5" aria-hidden="true" />
                    {{ copied ? 'Prompt copied' : 'Copy prompt' }}
                </button>
                <Link
                    :href="generation.prefill_url"
                    class="focus-ring inline-flex items-center gap-2 bg-ink px-3.5 py-2 text-xs font-medium text-paper transition-colors hover:bg-vermilion"
                >
                    <Sparkles class="size-3.5" aria-hidden="true" />
                    {{
                        generation.can_retry
                            ? 'Try this setup again'
                            : 'Make a variation'
                    }}
                </Link>
            </div>
        </header>

        <div class="grid gap-10 lg:grid-cols-[minmax(0,1fr)_23rem] lg:gap-12">
            <div class="space-y-8">
                <figure class="paper-panel p-2 sm:p-3">
                    <div
                        class="relative overflow-hidden bg-paper-deep"
                        :style="{ aspectRatio: ratioCss(generation.size) }"
                    >
                        <img
                            v-if="
                                generation.image.available &&
                                generation.image.url
                            "
                            :src="generation.image.url"
                            :alt="generationAlt(generation)"
                            class="h-full w-full object-cover"
                        />

                        <div
                            v-else-if="active"
                            class="skeleton-sheen absolute inset-0 flex flex-col items-center justify-center gap-4"
                        >
                            <span
                                class="font-mono text-[0.65rem] tracking-[0.22em] text-ink-soft uppercase"
                                >Developing</span
                            >
                            <span
                                class="animate-breathe h-px w-20 bg-vermilion/70"
                            />
                            <span
                                class="max-w-xs px-6 text-center text-xs leading-relaxed text-ink-soft"
                            >
                                The provider typically returns in 5–30 seconds.
                            </span>
                        </div>

                        <div
                            v-else
                            class="absolute inset-0 flex flex-col items-center justify-center gap-4 bg-[linear-gradient(135deg,var(--paper)_0%,var(--paper-deep)_100%)] px-6 text-center"
                        >
                            <ImageOff
                                class="size-7 text-ink/35"
                                aria-hidden="true"
                            />
                            <span class="eyebrow">{{
                                generation.image.expired
                                    ? 'Signed link expired'
                                    : 'No image was produced'
                            }}</span>
                            <p
                                class="max-w-sm text-sm leading-relaxed text-ink-soft"
                            >
                                {{
                                    generation.image.expired
                                        ? 'This plate finished before Vellum archived a copy, and Pixelforge’s 24-hour link has lapsed.'
                                        : 'The provider did not return an image. Failed generations are never charged.'
                                }}
                            </p>
                        </div>
                    </div>
                    <figcaption
                        class="flex flex-wrap items-center justify-between gap-3 px-1 pt-3 font-mono text-[0.58rem] tracking-[0.1em] text-ink-soft uppercase"
                    >
                        <span
                            >{{ ratioLabel(generation.size) }} /
                            {{ generation.size }}</span
                        >
                        <span
                            >{{ qualityLabel(generation.quality) }} finish</span
                        >
                        <span v-if="generation.image.expires_at">
                            Provider link until
                            {{ formatRelative(generation.image.expires_at) }}
                        </span>
                    </figcaption>
                </figure>

                <section class="paper-panel p-5 sm:p-6">
                    <div class="flex items-baseline justify-between gap-4">
                        <p class="eyebrow">The prompt</p>
                        <span class="ledger text-[0.58rem] text-ink-soft"
                            >{{
                                generation.prompt.length.toLocaleString()
                            }}
                            characters</span
                        >
                    </div>
                    <p
                        class="mt-4 font-display text-[1.2rem] leading-[1.55] tracking-[-0.01em] whitespace-pre-wrap text-ink"
                    >
                        {{ generation.prompt }}
                    </p>

                    <div
                        v-if="generation.reference_images.length > 0"
                        class="mt-6 border-t border-line-soft pt-4"
                    >
                        <p class="eyebrow">References</p>
                        <ul class="mt-3 space-y-2">
                            <li
                                v-for="(
                                    reference, index
                                ) in generation.reference_images"
                                :key="reference"
                                class="flex items-center gap-3"
                            >
                                <span
                                    class="ledger text-[0.58rem] text-ink-soft"
                                    >{{
                                        String(index + 1).padStart(2, '0')
                                    }}</span
                                >
                                <a
                                    :href="reference"
                                    target="_blank"
                                    rel="noreferrer noopener"
                                    class="link-underline min-w-0 truncate font-mono text-[0.65rem] text-ink-soft"
                                    >{{ reference }}</a
                                >
                            </li>
                        </ul>
                    </div>
                </section>

                <section class="paper-panel p-5 sm:p-6">
                    <p class="eyebrow">The ledger</p>

                    <div
                        v-if="charge"
                        class="mt-4 divide-y divide-line-soft font-mono text-[0.72rem]"
                    >
                        <div class="flex justify-between gap-4 py-2.5">
                            <span class="text-ink-soft">Input text</span>
                            <span class="text-ink"
                                >{{
                                    (
                                        usage.input_text_tokens ?? 0
                                    ).toLocaleString()
                                }}
                                tokens</span
                            >
                        </div>
                        <div class="flex justify-between gap-4 py-2.5">
                            <span class="text-ink-soft">Input images</span>
                            <span class="text-ink"
                                >{{
                                    (
                                        usage.input_image_tokens ?? 0
                                    ).toLocaleString()
                                }}
                                tokens</span
                            >
                        </div>
                        <div class="flex justify-between gap-4 py-2.5">
                            <span class="text-ink-soft">Output</span>
                            <span class="text-ink"
                                >{{
                                    (usage.output_tokens ?? 0).toLocaleString()
                                }}
                                tokens</span
                            >
                        </div>
                        <div class="flex justify-between gap-4 py-2.5">
                            <span class="text-ink-soft">Reasoning</span>
                            <span class="text-ink"
                                >{{
                                    (
                                        usage.reasoning_tokens ?? 0
                                    ).toLocaleString()
                                }}
                                tokens</span
                            >
                        </div>
                        <div class="flex justify-between gap-4 py-2.5">
                            <span class="text-ink-soft">Total</span>
                            <span class="text-ink"
                                >{{
                                    (usage.total_tokens ?? 0).toLocaleString()
                                }}
                                tokens</span
                            >
                        </div>

                        <div
                            v-if="providerCost"
                            class="flex justify-between gap-4 py-2.5"
                        >
                            <span class="text-ink-soft">Provider cost</span>
                            <span class="text-ink">${{ providerCost }}</span>
                        </div>

                        <div class="flex items-end justify-between gap-4 pt-4">
                            <div>
                                <p
                                    class="font-mono text-[0.58rem] tracking-[0.14em] text-ink-soft uppercase"
                                >
                                    Charged to you
                                </p>
                                <p class="mt-1 text-[0.6rem] text-ink-soft">
                                    Incl.
                                    {{ generation.estimate.markup_percent }}%
                                    studio fee · settled
                                    {{ formatDateTime(charge.settled_at) }}
                                </p>
                            </div>
                            <p
                                class="font-display text-3xl tracking-[-0.035em] text-vermilion"
                            >
                                {{ charge.customer_charge }}
                            </p>
                        </div>
                        <p
                            class="ledger pt-2 text-right text-[0.55rem] text-ink-soft"
                        >
                            {{ charge.customer_charge_exact }}
                        </p>
                    </div>

                    <div v-else-if="active" class="mt-4 space-y-3">
                        <p class="text-sm leading-relaxed text-ink-soft">
                            Token usage is only known when Pixelforge finishes.
                            The estimate below is the range you were shown
                            before submitting.
                        </p>
                        <dl class="space-y-2 font-mono text-[0.7rem]">
                            <div class="flex justify-between gap-4">
                                <dt class="text-ink-soft">Estimated</dt>
                                <dd class="text-ink">
                                    {{
                                        generation.estimate.customer_charge.min
                                    }}
                                    —
                                    {{
                                        generation.estimate.customer_charge.max
                                    }}
                                </dd>
                            </div>
                            <div class="flex justify-between gap-4">
                                <dt class="text-ink-soft">Studio fee</dt>
                                <dd class="text-ink">
                                    {{ generation.estimate.markup_percent }}%
                                </dd>
                            </div>
                        </dl>
                    </div>

                    <div
                        v-else
                        class="mt-4 border border-moss/25 bg-moss-soft px-4 py-3 text-sm text-moss"
                    >
                        <p class="font-medium">Nothing was charged.</p>
                        <p class="mt-1 text-xs leading-relaxed">
                            Failed generations are free. The setup is kept so
                            you can try again with a fresh attempt token.
                        </p>
                    </div>
                </section>
            </div>

            <aside class="space-y-6">
                <div class="paper-panel p-5">
                    <div class="flex items-center gap-2">
                        <Clock3
                            class="size-4 text-vermilion"
                            aria-hidden="true"
                        />
                        <p class="eyebrow">Timeline</p>
                    </div>

                    <ol v-if="hasTimeline" class="mt-5 space-y-4">
                        <li
                            v-for="(event, index) in timeline"
                            :key="event.label"
                            class="relative flex gap-3"
                        >
                            <span
                                class="mt-1 flex size-4 shrink-0 items-center justify-center border"
                                :class="
                                    index === timeline.length - 1
                                        ? 'border-vermilion bg-vermilion-soft'
                                        : 'border-line bg-paper'
                                "
                                aria-hidden="true"
                            >
                                <span
                                    class="size-1.5"
                                    :class="
                                        index === timeline.length - 1
                                            ? 'bg-vermilion'
                                            : 'bg-line'
                                    "
                                />
                            </span>
                            <div class="min-w-0">
                                <p class="text-xs font-medium text-ink">
                                    {{ event.label }}
                                </p>
                                <p
                                    class="mt-0.5 font-mono text-[0.55rem] tracking-[0.05em] text-ink-soft"
                                >
                                    {{ formatDateTime(event.value) }}
                                </p>
                            </div>
                        </li>
                    </ol>

                    <p v-else class="mt-4 text-sm text-ink-soft">
                        Waiting for the first confirmation from Pixelforge.
                    </p>

                    <div
                        v-if="active"
                        class="mt-5 flex items-center gap-2 border-t border-line-soft pt-3 font-mono text-[0.55rem] tracking-[0.1em] text-ink-soft uppercase"
                    >
                        <span
                            class="status-dot animate-breathe bg-vermilion"
                            aria-hidden="true"
                        />
                        Live · checks every 5s
                    </div>
                </div>

                <div class="paper-panel p-5">
                    <p class="eyebrow">Set up</p>
                    <dl class="mt-4 space-y-3 font-mono text-[0.65rem]">
                        <div class="flex justify-between gap-3">
                            <dt class="text-ink-soft">Proportion</dt>
                            <dd class="text-right text-ink">
                                {{ ratioLabel(generation.size) }}
                            </dd>
                        </div>
                        <div class="flex justify-between gap-3">
                            <dt class="text-ink-soft">Quality</dt>
                            <dd class="text-right text-ink">
                                {{ qualityLabel(generation.quality) }}
                            </dd>
                        </div>
                        <div class="flex justify-between gap-3">
                            <dt class="text-ink-soft">References</dt>
                            <dd class="text-right text-ink">
                                {{ generation.reference_images.length }}
                            </dd>
                        </div>
                        <div class="flex justify-between gap-3">
                            <dt class="text-ink-soft">Composed</dt>
                            <dd class="text-right text-ink">
                                {{ formatDateTime(generation.created_at) }}
                            </dd>
                        </div>
                    </dl>
                </div>

                <div
                    v-if="generation.failure"
                    class="border border-vermilion/30 bg-vermilion-soft p-5"
                >
                    <p
                        class="font-mono text-[0.58rem] tracking-[0.14em] text-vermilion uppercase"
                    >
                        Failure ·
                        {{ generation.failure.stage ?? 'unknown stage' }}
                    </p>
                    <p class="mt-2 text-sm leading-relaxed text-ink">
                        {{ generation.failure.message }}
                    </p>
                    <p
                        class="mt-3 font-mono text-[0.55rem] tracking-[0.06em] text-ink-soft"
                    >
                        {{ generation.failure.code }}
                    </p>
                    <Link
                        :href="generation.prefill_url"
                        class="focus-ring mt-4 inline-flex items-center gap-2 bg-ink px-4 py-2 text-xs font-medium text-paper hover:bg-vermilion"
                    >
                        <RefreshCw class="size-3.5" aria-hidden="true" />
                        Retry this setup
                    </Link>
                </div>

                <div v-if="generation.provider" class="paper-panel p-5">
                    <p class="eyebrow">Operator view</p>
                    <dl class="mt-4 space-y-2 font-mono text-[0.6rem]">
                        <div class="flex justify-between gap-3">
                            <dt class="text-ink-soft">Provider id</dt>
                            <dd class="truncate text-right text-ink">
                                {{ generation.provider.generation_id ?? '—' }}
                            </dd>
                        </div>
                        <div class="flex justify-between gap-3">
                            <dt class="text-ink-soft">Provider cost</dt>
                            <dd class="text-right text-ink">
                                {{
                                    generation.provider.provider_cost
                                        ? `$${generation.provider.provider_cost}`
                                        : '—'
                                }}
                            </dd>
                        </div>
                        <div class="flex justify-between gap-3">
                            <dt class="text-ink-soft">Submits</dt>
                            <dd class="text-right text-ink">
                                {{ generation.provider.submit_attempts }}
                            </dd>
                        </div>
                        <div class="flex justify-between gap-3">
                            <dt class="text-ink-soft">Reconciles</dt>
                            <dd class="text-right text-ink">
                                {{ generation.provider.reconcile_attempts }}
                            </dd>
                        </div>
                    </dl>
                    <pre
                        v-if="generation.provider.error"
                        class="mt-3 max-h-40 overflow-auto border border-line-soft bg-background/50 p-2 text-[0.55rem] leading-relaxed text-ink-soft"
                        >{{
                            JSON.stringify(generation.provider.error, null, 2)
                        }}</pre>
                </div>

                <p
                    v-if="hasUsage && !providerCost"
                    class="px-1 text-[0.65rem] leading-relaxed text-ink-soft"
                >
                    Provider cost is visible to studio operators only. Your
                    charge includes the flat
                    {{ generation.estimate.markup_percent }}% studio fee.
                </p>
            </aside>
        </div>
    </div>
</template>
