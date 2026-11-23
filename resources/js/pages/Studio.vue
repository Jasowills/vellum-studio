<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import {
    ArrowRight,
    ChevronDown,
    ExternalLink,
    Link2,
    Plus,
    X,
} from '@lucide/vue';
import {
    computed,
    nextTick,
    onBeforeUnmount,
    onMounted,
    ref,
    watch,
} from 'vue';
import GenerationCard from '@/components/GenerationCard.vue';
import StatusBadge from '@/components/StatusBadge.vue';
import { consumePromptForStudio } from '@/lib/pendingPrompt';
import {
    csrfToken,
    formatRelative,
    generationAlt,
    qualityDescription,
    qualityLabel,
    randomUuid,
    ratioLabel,
} from '@/lib/vellum';
import type { EstimatePayload, Generation, PricingPayload } from '@/types';

interface Props {
    pricing: PricingPayload | null;
    pricingError: string | null;
    sizes: string[];
    qualities: string[];
    defaultSize: string;
    defaultQuality: string;
    maxReferenceImages: number;
    maxPromptCharacters: number;
    recent: Generation[];
    prefill: {
        prompt: string;
        size: string;
        quality: string;
        reference_images: string[];
        source_id: string;
    } | null;
}

type StudioPrefill = NonNullable<Props['prefill']>;
type FormFields = {
    prompt: string;
    size: string;
    quality: string;
    reference_images: string[];
    attempt_token: string;
};

const props = defineProps<Props>();

function initialValues(prefill: StudioPrefill | null): FormFields {
    return {
        prompt: prefill?.prompt ?? '',
        size: prefill?.size ?? props.defaultSize,
        quality: prefill?.quality ?? props.defaultQuality,
        reference_images: [...(prefill?.reference_images ?? [])],
        attempt_token: randomUuid(),
    };
}

const form = useForm<FormFields>(initialValues(props.prefill));

const promptField = ref<HTMLTextAreaElement | null>(null);
const referenceInput = ref<HTMLInputElement | null>(null);
const referenceError = ref<string | null>(null);
const estimate = ref<EstimatePayload | null>(null);
const estimateLoading = ref(false);
const estimateError = ref<string | null>(null);
const attempted = ref(false);
const advancedOpen = ref(false);
const featuredImageFailed = ref(false);

let estimateTimer: ReturnType<typeof setTimeout> | undefined;
let estimateController: AbortController | null = null;
let skipNextEstimateSchedule = false;

const ACTIVE_STATUSES: Generation['status'][] = [
    'pending',
    'queued',
    'processing',
];

const sizeOptions = computed(() =>
    props.sizes.map((value) => {
        const labels: Record<
            string,
            { label: string; note: string; ratio: string }
        > = {
            '1024x1024': {
                label: 'Square',
                note: 'The classic plate',
                ratio: '1 / 1',
            },
            '1536x1024': {
                label: 'Landscape',
                note: 'Scenes and horizons',
                ratio: '3 / 2',
            },
            '1024x1536': {
                label: 'Portrait',
                note: 'Figures and objects',
                ratio: '2 / 3',
            },
        };

        return {
            value,
            label: labels[value]?.label ?? value,
            note: labels[value]?.note ?? '',
            ratio: labels[value]?.ratio ?? '1 / 1',
        };
    }),
);

const qualityOptions = computed(() =>
    props.qualities.map((value) => ({
        value,
        label: qualityLabel(value),
        note: qualityDescription(value),
        output: props.pricing?.tokens.output[value]?.[form.size] ?? null,
        reasoning: props.pricing?.tokens.reasoning[value] ?? null,
    })),
);

const characterCount = computed(() => form.prompt.length);
const atCharacterLimit = computed(
    () => characterCount.value >= props.maxPromptCharacters,
);
const canSubmit = computed(
    () =>
        props.pricing !== null &&
        form.prompt.trim().length > 0 &&
        !form.processing &&
        form.reference_images.length <= props.maxReferenceImages,
);
const estimateReady = computed(
    () => estimate.value !== null && !estimateLoading.value,
);

const currentSizeLabel = computed(
    () =>
        sizeOptions.value.find((option) => option.value === form.size)?.label ??
        form.size,
);

const referenceErrors = computed(() =>
    Object.entries(form.errors)
        .filter(([key]) => key.startsWith('reference_images.'))
        .map(([key, message]) => ({ key, message })),
);

const featured = computed(() => props.recent[0] ?? null);
const featuredActive = computed(() =>
    featured.value ? ACTIVE_STATUSES.includes(featured.value.status) : false,
);
const featuredImageReady = computed(
    () =>
        Boolean(featured.value?.image.available && featured.value.image.url) &&
        !featuredImageFailed.value,
);
const featuredAlt = computed(() =>
    featured.value ? generationAlt(featured.value) : '',
);
const featuredMessage = computed(() => {
    const generation = featured.value;

    if (!generation) {
        return 'The canvas is waiting.';
    }

    if (ACTIVE_STATUSES.includes(generation.status)) {
        return generation.status === 'processing'
            ? 'Developing'
            : 'In the queue';
    }

    if (generation.status === 'failed') {
        return 'No plate was produced';
    }

    if (generation.image.expired) {
        return 'Provider link expired';
    }

    return 'Latest plate';
});
const featuredCopy = computed(() => {
    const generation = featured.value;

    if (!generation) {
        return 'Your first image will appear here. Nothing is shown until the provider returns a result.';
    }

    if (ACTIVE_STATUSES.includes(generation.status)) {
        return generation.status === 'processing'
            ? 'Pixelforge is forming the image. The state changes only when the provider reports one — no progress is guessed.'
            : 'The plate is accepted and waiting. The state changes only when the provider reports one — no progress is guessed.';
    }

    if (generation.status === 'failed') {
        return (
            generation.failure?.message ??
            'This attempt did not produce an image. Nothing was charged for it.'
        );
    }

    if (generation.image.expired) {
        return 'The signed provider link has lapsed. The record and its settled charge remain in the library.';
    }

    return 'Shown in grayscale here. Open the plate for its full prompt, ledger, and timeline.';
});
const featuredMetrics = computed(() => {
    const generation = featured.value;

    if (!generation) {
        return [];
    }

    return [
        { label: 'State', value: generation.status_label },
        {
            label: 'Format',
            value: `${ratioLabel(generation.size)} · ${qualityLabel(generation.quality)}`,
        },
        {
            label: 'Charge',
            value:
                generation.cost.customer_charge ??
                (generation.status === 'failed' ? 'No charge' : 'Pending'),
        },
        {
            label: 'Created',
            value: formatRelative(generation.created_at) || '—',
        },
    ];
});

const suggestions = [
    'An observatory above a sea of cloud, morning light, fine grain.',
    'A ceramic bowl of pale fruit on stone, painterly still life.',
    'A quiet reading room with one chair, long afternoon light, dust in the air.',
];

function scheduleEstimate(): void {
    window.clearTimeout(estimateTimer);
    estimateTimer = window.setTimeout(runEstimate, 350);
}

async function runEstimate(): Promise<void> {
    window.clearTimeout(estimateTimer);

    if (props.pricing === null) {
        return;
    }

    estimateController?.abort();
    estimateController = new AbortController();
    estimateLoading.value = true;
    estimateError.value = null;

    try {
        const response = await fetch('/studio/estimate', {
            method: 'POST',
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken(),
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: JSON.stringify({
                prompt: form.prompt,
                size: form.size,
                quality: form.quality,
                reference_images: form.reference_images,
            }),
            signal: estimateController.signal,
        });

        if (response.status === 422) {
            estimateError.value =
                'Check the reference URLs to see a fresh estimate.';
            estimate.value = null;

            return;
        }

        if (!response.ok) {
            throw new Error('Estimate unavailable');
        }

        estimate.value = (await response.json()) as EstimatePayload;
    } catch (error) {
        if ((error as Error).name !== 'AbortError') {
            estimateError.value =
                'The estimate could not be refreshed. You can still submit; the final charge is based on real usage.';
            estimate.value = null;
        }
    } finally {
        estimateLoading.value = false;
    }
}

function initializeForm(prefill: StudioPrefill | null): void {
    window.clearTimeout(estimateTimer);
    estimateController?.abort();
    estimateController = null;

    const next = initialValues(prefill);

    form.prompt = next.prompt;
    form.size = next.size;
    form.quality = next.quality;
    form.reference_images = next.reference_images;
    form.attempt_token = next.attempt_token;
    attempted.value = false;
    referenceError.value = null;
    form.clearErrors();

    estimate.value = null;
    estimateError.value = null;
    estimateLoading.value = false;

    scheduleEstimate();
}

function useSuggestion(value: string): void {
    form.prompt = value;
    promptField.value?.focus();
}

function addReference(): void {
    const value = referenceInput.value?.value.trim() ?? '';
    referenceError.value = null;

    if (value === '') {
        return;
    }

    if (form.reference_images.length >= props.maxReferenceImages) {
        referenceError.value = `You can add at most ${props.maxReferenceImages} references.`;

        return;
    }

    try {
        const url = new URL(value);

        if (!['http:', 'https:'].includes(url.protocol)) {
            throw new Error('Unsupported protocol');
        }
    } catch {
        referenceError.value = 'Use a public http or https image URL.';

        return;
    }

    if (form.reference_images.includes(value)) {
        referenceError.value = 'That reference is already in the set.';

        return;
    }

    form.reference_images.push(value);

    if (referenceInput.value) {
        referenceInput.value.value = '';
        referenceInput.value.focus();
    }
}

function validateReference(index: number): void {
    const value = form.reference_images[index]?.trim() ?? '';

    form.reference_images[index] = value;

    if (value === '') {
        referenceError.value = `Reference ${index + 1} needs a public http or https URL.`;

        return;
    }

    try {
        const url = new URL(value);

        if (!['http:', 'https:'].includes(url.protocol)) {
            throw new Error('Unsupported protocol');
        }

        referenceError.value = null;
    } catch {
        referenceError.value = `Reference ${index + 1} needs a public http or https URL.`;
    }
}

function removeReference(index: number): void {
    form.reference_images.splice(index, 1);
    referenceError.value = null;
}

function rotateAttemptToken(): void {
    if (attempted.value && !form.processing) {
        form.attempt_token = randomUuid();
        attempted.value = false;
    }
}

function submit(): void {
    if (!canSubmit.value) {
        return;
    }

    attempted.value = true;
    form.post('/generations', {
        preserveScroll: true,
        onError: () => {
            window.setTimeout(
                () => document.getElementById('prompt')?.focus(),
                50,
            );
        },
    });
}

watch(
    () => [
        form.prompt,
        form.size,
        form.quality,
        JSON.stringify(form.reference_images),
    ],
    () => {
        rotateAttemptToken();

        if (skipNextEstimateSchedule) {
            skipNextEstimateSchedule = false;

            return;
        }

        scheduleEstimate();
    },
);

watch(
    () => props.prefill,
    (next, previous) => {
        if (next === previous) {
            return;
        }

        initializeForm(next);

        if (next) {
            void nextTick(() => promptField.value?.focus());
        }
    },
);

watch(
    () => featured.value?.image.url ?? null,
    () => {
        featuredImageFailed.value = false;
    },
);

onMounted(() => {
    let focusHandoff = false;

    if (props.prefill === null) {
        const handedOff = consumePromptForStudio();

        if (handedOff !== null && handedOff !== '') {
            if (handedOff !== form.prompt) {
                skipNextEstimateSchedule = true;
            }

            form.prompt = handedOff;
            form.attempt_token = randomUuid();
            attempted.value = false;
            focusHandoff = true;
        }
    }

    void runEstimate();

    if (focusHandoff) {
        void nextTick(() => {
            skipNextEstimateSchedule = false;
            promptField.value?.focus();
        });
    }
});

onBeforeUnmount(() => {
    window.clearTimeout(estimateTimer);
    estimateController?.abort();
});
</script>

<template>
    <Head title="Studio" />

    <div class="space-y-8 bg-background pb-28 text-signal lg:pb-2">
        <header
            class="flex flex-col gap-5 border-b border-line pb-6 sm:flex-row sm:items-end sm:justify-between"
        >
            <div>
                <p class="eyebrow flex items-center gap-3">
                    <span class="h-px w-8 bg-signal" aria-hidden="true" />
                    Studio / instrument 01
                </p>
                <h1
                    class="display-title mt-4 max-w-[14ch] text-[clamp(2.4rem,4.6vw,3.75rem)] text-signal"
                >
                    Make an image.
                </h1>
            </div>
            <p class="max-w-sm text-sm leading-relaxed text-signal-muted">
                Compose a prompt. The estimate stays live; the charge is settled
                only when the plate arrives.
            </p>
        </header>

        <div
            class="grid min-w-0 items-start gap-8 lg:grid-cols-[minmax(0,1fr)_23rem] lg:gap-10 xl:grid-cols-[minmax(0,1fr)_25rem]"
        >
            <section
                class="order-2 min-w-0 lg:order-1"
                aria-labelledby="canvas-heading"
            >
                <div
                    class="flex flex-wrap items-end justify-between gap-3 border-b border-line pb-3"
                >
                    <div>
                        <p class="eyebrow">Canvas / latest output</p>
                        <h2
                            id="canvas-heading"
                            class="mt-2 font-display text-3xl tracking-[-0.03em] text-signal"
                        >
                            Recent plate
                        </h2>
                    </div>
                    <Link
                        href="/library"
                        class="link-underline font-mono text-[0.62rem] tracking-[0.14em] text-signal-muted uppercase"
                    >
                        Full library
                    </Link>
                </div>

                <div
                    v-if="featured"
                    class="mt-5 border border-line bg-paper-deep"
                >
                    <div
                        class="relative flex min-h-[20rem] items-center justify-center overflow-hidden bg-paper-deep sm:min-h-[26rem] lg:min-h-[32rem]"
                    >
                        <div
                            class="signal-grid pointer-events-none absolute inset-0 opacity-40"
                            aria-hidden="true"
                        />
                        <img
                            v-if="featuredImageReady"
                            :src="featured.image.url ?? undefined"
                            :alt="featuredAlt"
                            loading="lazy"
                            decoding="async"
                            class="relative z-10 max-h-[32rem] w-full object-contain grayscale"
                            @error="featuredImageFailed = true"
                        />
                        <div
                            v-else
                            class="relative z-10 flex max-w-md flex-col items-center gap-4 px-6 py-12 text-center"
                            aria-live="polite"
                            aria-atomic="true"
                        >
                            <span
                                class="font-display text-5xl leading-none text-signal-faint"
                                aria-hidden="true"
                            >
                                {{ featuredActive ? '◌' : '×' }}
                            </span>
                            <p
                                class="font-mono text-[0.65rem] tracking-[0.18em] text-signal-muted uppercase"
                            >
                                {{ featuredMessage }}
                            </p>
                            <p
                                class="text-sm leading-relaxed text-signal-muted"
                            >
                                {{ featuredCopy }}
                            </p>
                        </div>

                        <div class="absolute top-4 left-4 z-20">
                            <StatusBadge
                                :label="featured.status_label"
                                :tone="featured.status_tone"
                                :pulse="featuredActive"
                            />
                        </div>
                    </div>

                    <div
                        class="flex flex-wrap items-center justify-between gap-3 border-t border-line px-4 py-4"
                    >
                        <p
                            class="line-clamp-2 max-w-[42rem] min-w-0 font-display text-lg leading-snug tracking-[-0.015em] text-signal"
                        >
                            {{ featured.prompt }}
                        </p>
                        <Link
                            :href="`/generations/${featured.id}`"
                            class="focus-ring inline-flex shrink-0 items-center gap-2 font-mono text-[0.62rem] tracking-[0.14em] text-signal uppercase"
                        >
                            Open plate
                            <ArrowRight class="size-3.5" aria-hidden="true" />
                        </Link>
                    </div>

                    <dl
                        class="grid grid-cols-2 gap-px border-t border-line bg-line sm:grid-cols-4"
                    >
                        <div
                            v-for="metric in featuredMetrics"
                            :key="metric.label"
                            class="bg-paper px-4 py-3"
                        >
                            <dt class="eyebrow">{{ metric.label }}</dt>
                            <dd
                                class="mt-1 truncate font-mono text-[0.7rem] tracking-[0.06em] text-signal"
                            >
                                {{ metric.value }}
                            </dd>
                        </div>
                    </dl>
                </div>

                <div
                    v-else
                    class="relative mt-5 flex min-h-[20rem] flex-col items-center justify-center overflow-hidden border border-dashed border-line bg-paper px-6 py-12 text-center sm:min-h-[26rem] lg:min-h-[32rem]"
                >
                    <div
                        class="signal-grid pointer-events-none absolute inset-0 opacity-40"
                        aria-hidden="true"
                    />
                    <div class="relative z-10 max-w-md">
                        <p
                            class="font-display text-4xl tracking-[-0.03em] text-signal"
                        >
                            The canvas is empty.
                        </p>
                        <p
                            class="mt-3 text-sm leading-relaxed text-signal-muted"
                        >
                            Your first image will appear here. Nothing is shown
                            until the provider returns a result.
                        </p>
                    </div>
                </div>

                <div v-if="recent.length > 0" class="mt-10">
                    <div
                        class="flex items-baseline justify-between border-b border-line pb-3"
                    >
                        <p class="eyebrow">Archive / most recent</p>
                        <span
                            class="font-mono text-[0.6rem] tracking-[0.14em] text-signal-faint uppercase"
                        >
                            {{ recent.length }} plates
                        </span>
                    </div>
                    <div class="mt-4 grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                        <GenerationCard
                            v-for="generation in recent"
                            :key="generation.id"
                            :generation="generation"
                            compact
                        />
                    </div>
                </div>
            </section>

            <section
                class="order-1 min-w-0 lg:sticky lg:top-20 lg:order-2 lg:max-h-[calc(100vh-6rem)] lg:self-start lg:overflow-y-auto"
                aria-labelledby="composer-heading"
            >
                <form
                    id="studio-form"
                    class="border border-line bg-paper"
                    novalidate
                    @submit.prevent="submit"
                >
                    <div
                        class="flex items-start justify-between gap-4 border-b border-line px-5 py-4"
                    >
                        <div>
                            <p class="eyebrow">Composer / new plate</p>
                            <h2
                                id="composer-heading"
                                class="mt-1 font-display text-2xl tracking-[-0.02em] text-signal"
                            >
                                Describe the image.
                            </h2>
                        </div>
                        <span
                            class="shrink-0 font-mono text-[0.58rem] tracking-[0.14em] text-signal-faint uppercase"
                        >
                            01 / Prompt
                        </span>
                    </div>

                    <div class="space-y-5 px-5 py-5">
                        <div
                            v-if="pricingError"
                            class="border border-signal-line bg-paper-deep px-3 py-2.5 text-xs leading-relaxed text-signal"
                            role="alert"
                        >
                            {{ pricingError }}
                        </div>

                        <div
                            v-if="prefill"
                            class="flex items-start justify-between gap-3 border border-line-soft bg-paper-deep px-3 py-2.5"
                            role="status"
                        >
                            <p
                                class="text-xs leading-relaxed text-signal-muted"
                            >
                                Setup copied from plate
                                <Link
                                    :href="`/generations/${prefill.source_id}`"
                                    class="link-underline font-mono text-signal"
                                >
                                    {{ prefill.source_id }}
                                </Link>
                                . This will be a new generation.
                            </p>
                            <span
                                class="shrink-0 font-mono text-[0.55rem] tracking-[0.12em] text-signal-faint uppercase"
                            >
                                Fresh key
                            </span>
                        </div>

                        <div>
                            <div
                                class="flex items-baseline justify-between gap-3"
                            >
                                <label
                                    for="prompt"
                                    class="font-mono text-[0.62rem] tracking-[0.18em] text-signal-muted uppercase"
                                >
                                    Image prompt
                                </label>
                                <span
                                    id="prompt-count"
                                    class="ledger text-[0.62rem]"
                                    :class="
                                        atCharacterLimit
                                            ? 'text-signal'
                                            : 'text-signal-faint'
                                    "
                                    aria-live="polite"
                                >
                                    {{ characterCount.toLocaleString() }} /
                                    {{ maxPromptCharacters.toLocaleString() }}
                                </span>
                            </div>
                            <textarea
                                id="prompt"
                                ref="promptField"
                                v-model="form.prompt"
                                rows="6"
                                :maxlength="maxPromptCharacters"
                                class="mt-2 block w-full resize-y border border-line bg-background px-4 py-3.5 font-display text-[1.05rem] leading-[1.5] tracking-[-0.01em] text-signal outline-none placeholder:text-signal-faint focus:border-signal"
                                placeholder="A stone observatory above a sea of cloud at first light."
                                :aria-describedby="
                                    form.errors.prompt
                                        ? 'prompt-help prompt-count prompt-error'
                                        : 'prompt-help prompt-count'
                                "
                                :aria-invalid="
                                    form.errors.prompt ? 'true' : undefined
                                "
                                required
                            />
                            <p
                                id="prompt-help"
                                class="mt-2 text-xs leading-relaxed text-signal-muted"
                            >
                                Be specific about light, material, and mood. Up
                                to {{ maxPromptCharacters.toLocaleString() }}
                                Unicode characters.
                            </p>
                            <p
                                v-if="form.errors.prompt"
                                id="prompt-error"
                                class="mt-2 border-l border-signal pl-3 text-xs leading-relaxed text-signal"
                                role="alert"
                            >
                                {{ form.errors.prompt }}
                            </p>
                        </div>

                        <div>
                            <p class="eyebrow">Or begin with a study</p>
                            <div class="mt-2 flex flex-wrap gap-2">
                                <button
                                    v-for="suggestion in suggestions"
                                    :key="suggestion"
                                    type="button"
                                    class="focus-ring border border-line-soft px-2.5 py-1.5 text-left font-mono text-[0.6rem] leading-snug text-signal-muted transition-opacity hover:border-signal-line hover:text-signal hover:opacity-80 motion-reduce:transition-none"
                                    @click="useSuggestion(suggestion)"
                                >
                                    {{ suggestion.split(',')[0] }}
                                </button>
                            </div>
                        </div>

                        <div class="border-t border-line-soft pt-4">
                            <div
                                class="flex items-baseline justify-between gap-3"
                            >
                                <p class="eyebrow">Reference URLs</p>
                                <span
                                    class="ledger text-[0.6rem] text-signal-faint"
                                >
                                    {{ form.reference_images.length }} /
                                    {{ maxReferenceImages }}
                                </span>
                            </div>
                            <p
                                class="mt-2 text-xs leading-relaxed text-signal-muted"
                            >
                                Public http(s) links. Each reference is billed
                                as 765 input image tokens.
                            </p>

                            <div class="mt-3 flex flex-col gap-2 sm:flex-row">
                                <div class="relative min-w-0 flex-1">
                                    <Link2
                                        class="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-signal-faint"
                                        aria-hidden="true"
                                    />
                                    <label for="reference-url" class="sr-only">
                                        Reference image URL
                                    </label>
                                    <input
                                        id="reference-url"
                                        ref="referenceInput"
                                        type="url"
                                        inputmode="url"
                                        placeholder="https://example.com/reference.jpg"
                                        class="h-10 w-full border border-line bg-background pr-3 pl-9 font-mono text-[0.7rem] text-signal outline-none placeholder:text-signal-faint focus:border-signal"
                                        :disabled="
                                            form.reference_images.length >=
                                            maxReferenceImages
                                        "
                                        @keydown.enter.prevent="addReference"
                                    />
                                </div>
                                <button
                                    type="button"
                                    class="focus-ring inline-flex h-10 shrink-0 items-center justify-center gap-2 border border-signal px-4 font-mono text-[0.62rem] tracking-[0.12em] text-signal uppercase transition-opacity hover:opacity-80 disabled:cursor-not-allowed disabled:opacity-40 motion-reduce:transition-none"
                                    :disabled="
                                        form.reference_images.length >=
                                        maxReferenceImages
                                    "
                                    @click="addReference"
                                >
                                    <Plus class="size-3.5" aria-hidden="true" />
                                    Add
                                </button>
                            </div>

                            <p
                                v-if="referenceError"
                                class="mt-2 border-l border-signal pl-3 text-xs leading-relaxed text-signal"
                                role="alert"
                            >
                                {{ referenceError }}
                            </p>
                            <div
                                v-for="error in referenceErrors"
                                :key="error.key"
                                class="mt-2 border-l border-signal pl-3 text-xs leading-relaxed text-signal"
                                role="alert"
                            >
                                {{ error.message }}
                            </div>

                            <ul
                                v-if="form.reference_images.length > 0"
                                class="mt-3 space-y-2"
                            >
                                <li
                                    v-for="(
                                        reference, index
                                    ) in form.reference_images"
                                    :key="index"
                                    class="flex items-center gap-2 border border-line-soft bg-background px-2 py-2"
                                >
                                    <span
                                        class="shrink-0 font-mono text-[0.58rem] tracking-[0.1em] text-signal-faint"
                                    >
                                        R{{
                                            String(index + 1).padStart(2, '0')
                                        }}
                                    </span>
                                    <label
                                        :for="`reference-${index}`"
                                        class="sr-only"
                                    >
                                        Reference URL {{ index + 1 }}
                                    </label>
                                    <input
                                        :id="`reference-${index}`"
                                        v-model="form.reference_images[index]"
                                        type="url"
                                        inputmode="url"
                                        spellcheck="false"
                                        class="min-w-0 flex-1 bg-transparent font-mono text-[0.65rem] text-signal-muted outline-none focus:text-signal"
                                        @blur="validateReference(index)"
                                    />
                                    <a
                                        :href="reference"
                                        target="_blank"
                                        rel="noreferrer noopener"
                                        class="focus-ring shrink-0 text-signal-faint hover:text-signal"
                                        :aria-label="`Open reference ${index + 1}`"
                                    >
                                        <ExternalLink
                                            class="size-3.5"
                                            aria-hidden="true"
                                        />
                                    </a>
                                    <button
                                        type="button"
                                        class="focus-ring shrink-0 text-signal-faint hover:text-signal"
                                        :aria-label="`Remove reference ${index + 1}`"
                                        @click="removeReference(index)"
                                    >
                                        <X
                                            class="size-3.5"
                                            aria-hidden="true"
                                        />
                                    </button>
                                </li>
                            </ul>
                        </div>

                        <div class="border-t border-line-soft pt-4">
                            <button
                                type="button"
                                class="focus-ring flex w-full items-center justify-between gap-4 py-1 text-left"
                                :aria-expanded="advancedOpen"
                                aria-controls="advanced-settings"
                                @click="advancedOpen = !advancedOpen"
                            >
                                <span class="min-w-0">
                                    <span class="eyebrow">02 / Settings</span>
                                    <span
                                        class="mt-1 block truncate text-sm text-signal"
                                    >
                                        {{ currentSizeLabel }} ·
                                        {{ qualityLabel(form.quality) }}
                                    </span>
                                </span>
                                <ChevronDown
                                    class="size-4 shrink-0 text-signal-muted transition-transform duration-200 motion-reduce:transition-none"
                                    :class="advancedOpen ? 'rotate-180' : ''"
                                    aria-hidden="true"
                                />
                            </button>

                            <div
                                v-show="advancedOpen"
                                id="advanced-settings"
                                class="mt-4 space-y-5"
                            >
                                <fieldset>
                                    <legend class="eyebrow">Proportion</legend>
                                    <div class="mt-2 grid grid-cols-3 gap-2">
                                        <label
                                            v-for="option in sizeOptions"
                                            :key="option.value"
                                            class="cursor-pointer"
                                        >
                                            <input
                                                v-model="form.size"
                                                type="radio"
                                                name="size"
                                                :value="option.value"
                                                class="peer sr-only"
                                            />
                                            <span
                                                class="flex h-full flex-col gap-2 border border-line-soft px-2.5 py-2.5 transition-opacity peer-checked:border-signal peer-checked:bg-paper-deep peer-focus-visible:ring-2 peer-focus-visible:ring-signal peer-focus-visible:ring-offset-2 peer-focus-visible:ring-offset-background motion-reduce:transition-none"
                                            >
                                                <span
                                                    class="block w-6 border border-signal-line bg-background"
                                                    :style="{
                                                        aspectRatio:
                                                            option.ratio,
                                                    }"
                                                    aria-hidden="true"
                                                />
                                                <span
                                                    class="font-mono text-[0.62rem] text-signal"
                                                >
                                                    {{ option.label }}
                                                </span>
                                                <span
                                                    class="font-mono text-[0.55rem] text-signal-faint"
                                                >
                                                    {{ option.value }}
                                                </span>
                                            </span>
                                        </label>
                                    </div>
                                </fieldset>

                                <fieldset>
                                    <legend class="eyebrow">Quality</legend>
                                    <div class="mt-2 space-y-2">
                                        <label
                                            v-for="option in qualityOptions"
                                            :key="option.value"
                                            class="block cursor-pointer"
                                        >
                                            <input
                                                v-model="form.quality"
                                                type="radio"
                                                name="quality"
                                                :value="option.value"
                                                class="peer sr-only"
                                            />
                                            <span
                                                class="flex items-start justify-between gap-3 border border-line-soft px-3 py-2.5 transition-opacity peer-checked:border-signal peer-checked:bg-paper-deep peer-focus-visible:ring-2 peer-focus-visible:ring-signal peer-focus-visible:ring-offset-2 peer-focus-visible:ring-offset-background motion-reduce:transition-none"
                                            >
                                                <span class="min-w-0">
                                                    <span
                                                        class="block text-sm text-signal"
                                                    >
                                                        {{ option.label }}
                                                    </span>
                                                    <span
                                                        class="mt-0.5 block text-[0.68rem] leading-snug text-signal-muted"
                                                    >
                                                        {{ option.note }}
                                                    </span>
                                                </span>
                                                <span
                                                    v-if="option.output"
                                                    class="ledger shrink-0 text-[0.55rem] text-signal-faint"
                                                >
                                                    {{
                                                        option.output.toLocaleString()
                                                    }}
                                                    out
                                                </span>
                                            </span>
                                        </label>
                                    </div>
                                </fieldset>
                            </div>
                        </div>
                    </div>

                    <div class="border-t border-line bg-background px-5 py-5">
                        <div class="flex items-baseline justify-between gap-3">
                            <p class="eyebrow">Estimate / live range</p>
                            <span
                                class="font-mono text-[0.55rem] tracking-[0.12em] text-signal-faint uppercase"
                            >
                                USD / tax excl.
                            </span>
                        </div>

                        <div class="mt-3" aria-live="polite" aria-atomic="true">
                            <div
                                v-if="estimateLoading && !estimate"
                                class="flex items-center gap-3 py-2"
                                role="status"
                            >
                                <span
                                    class="size-3 border border-signal-line"
                                    aria-hidden="true"
                                />
                                <span
                                    class="font-mono text-[0.65rem] tracking-[0.14em] text-signal-muted uppercase"
                                >
                                    Refreshing estimate…
                                </span>
                            </div>

                            <template v-else-if="estimate">
                                <div
                                    class="flex items-baseline justify-between gap-3"
                                >
                                    <p
                                        class="font-display text-[2rem] leading-none tracking-[-0.04em] text-signal sm:text-[2.25rem]"
                                    >
                                        {{ estimate.customer_charge.min }}
                                        <span class="text-signal-faint">—</span>
                                        {{ estimate.customer_charge.max }}
                                    </p>
                                    <span
                                        v-if="estimateLoading"
                                        class="font-mono text-[0.55rem] tracking-[0.12em] text-signal-faint uppercase"
                                    >
                                        Updating
                                    </span>
                                </div>
                                <p
                                    class="mt-2 font-mono text-[0.58rem] leading-relaxed tracking-[0.1em] text-signal-muted uppercase"
                                >
                                    {{
                                        estimate.total_tokens.min.toLocaleString()
                                    }}–{{
                                        estimate.total_tokens.max.toLocaleString()
                                    }}
                                    tokens · {{ estimate.markup_percent }}%
                                    studio fee
                                </p>

                                <details
                                    v-if="estimateReady"
                                    class="mt-3 border-t border-line-soft pt-3"
                                >
                                    <summary
                                        class="cursor-pointer list-none font-mono text-[0.58rem] tracking-[0.14em] text-signal-muted uppercase"
                                    >
                                        Token ledger
                                    </summary>
                                    <dl
                                        class="mt-3 space-y-2 font-mono text-[0.6rem]"
                                    >
                                        <div
                                            class="flex items-baseline justify-between gap-3"
                                        >
                                            <dt class="text-signal-faint">
                                                Input text
                                            </dt>
                                            <dd class="ledger text-signal">
                                                {{
                                                    estimate.text_tokens.min.toLocaleString()
                                                }}–{{
                                                    estimate.text_tokens.max.toLocaleString()
                                                }}
                                            </dd>
                                        </div>
                                        <div
                                            class="flex items-baseline justify-between gap-3"
                                        >
                                            <dt class="text-signal-faint">
                                                Input images
                                            </dt>
                                            <dd class="ledger text-signal">
                                                {{
                                                    estimate.input_image_tokens.toLocaleString()
                                                }}
                                            </dd>
                                        </div>
                                        <div
                                            class="flex items-baseline justify-between gap-3"
                                        >
                                            <dt class="text-signal-faint">
                                                Output
                                            </dt>
                                            <dd class="ledger text-signal">
                                                {{
                                                    estimate.output_tokens.toLocaleString()
                                                }}
                                            </dd>
                                        </div>
                                        <div
                                            class="flex items-baseline justify-between gap-3"
                                        >
                                            <dt class="text-signal-faint">
                                                Reasoning
                                            </dt>
                                            <dd class="ledger text-signal">
                                                {{
                                                    estimate.reasoning_tokens.min.toLocaleString()
                                                }}–{{
                                                    estimate.reasoning_tokens.max.toLocaleString()
                                                }}
                                            </dd>
                                        </div>
                                    </dl>
                                </details>
                            </template>

                            <div v-else>
                                <p
                                    class="font-display text-2xl leading-none text-signal-faint"
                                >
                                    —
                                </p>
                                <p
                                    class="mt-2 text-xs leading-relaxed text-signal-muted"
                                >
                                    {{
                                        pricingError
                                            ? 'Pricing is unavailable; estimates are paused.'
                                            : 'Write a prompt to see a live range.'
                                    }}
                                </p>
                            </div>
                        </div>

                        <p
                            v-if="estimateError"
                            class="mt-3 border-l border-signal pl-3 text-xs leading-relaxed text-signal-muted"
                            role="alert"
                        >
                            {{ estimateError }}
                        </p>

                        <button
                            type="submit"
                            class="focus-ring mt-5 hidden w-full items-center justify-center gap-3 bg-signal px-5 py-3.5 font-mono text-[0.68rem] tracking-[0.16em] text-signal-invert uppercase transition-opacity hover:opacity-85 disabled:cursor-not-allowed disabled:opacity-40 motion-reduce:transition-none lg:inline-flex"
                            :disabled="!canSubmit"
                        >
                            <template v-if="form.processing">Sending…</template>
                            <template v-else>
                                Make this image
                                <ArrowRight class="size-4" aria-hidden="true" />
                            </template>
                        </button>

                        <p
                            class="mt-3 text-center font-mono text-[0.55rem] leading-relaxed tracking-[0.1em] text-signal-faint uppercase"
                        >
                            Estimate only. Final charge follows real token
                            usage.
                        </p>
                    </div>
                </form>

                <p
                    v-if="!pricing"
                    class="mt-4 border border-line bg-paper px-4 py-3 text-xs leading-relaxed text-signal-muted"
                    role="status"
                >
                    Prompt composition stays open, but submission is paused
                    until Pixelforge pricing is reachable.
                </p>
            </section>
        </div>
    </div>

    <div
        class="fixed inset-x-0 bottom-0 z-30 border-t border-line bg-background px-5 py-3 lg:hidden"
    >
        <button
            type="submit"
            form="studio-form"
            class="focus-ring inline-flex w-full items-center justify-center gap-3 bg-signal px-5 py-3.5 font-mono text-[0.68rem] tracking-[0.16em] text-signal-invert uppercase transition-opacity hover:opacity-85 disabled:cursor-not-allowed disabled:opacity-40 motion-reduce:transition-none"
            :disabled="!canSubmit"
        >
            <template v-if="form.processing">Sending…</template>
            <template v-else>
                Make this image
                <ArrowRight class="size-4" aria-hidden="true" />
            </template>
        </button>
    </div>
</template>
