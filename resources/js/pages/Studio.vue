<script setup lang="ts">
defineOptions({ inheritAttrs: false });

import { Head, Link, useForm } from '@inertiajs/vue3';
import {
    ArrowRight,
    ArrowUpRight,
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

function exactMoney(nanos: number): string {
    return `$${(nanos / 1e9).toFixed(9)}`;
}

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
                generation.cost.customer_charge_exact ??
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

    <div class="min-h-svh bg-void pb-10 text-document">
        <div class="vellum-shell max-w-[1180px] pt-6">
            <header
                class="flex flex-wrap items-center justify-between gap-3 border-b border-line pb-4"
            >
                <div class="flex items-baseline gap-3">
                    <h1 class="text-base font-medium text-document">Studio</h1>
                    <p class="vellum-meta">/ new plate</p>
                </div>
                <p class="vellum-meta" role="status">
                    {{
                        pricing
                            ? 'Live estimate · Pixelforge connected'
                            : 'Submission paused · pricing unavailable'
                    }}
                </p>
            </header>

            <section class="mt-6" aria-labelledby="canvas-heading">
                <div
                    class="flex flex-wrap items-end justify-between gap-3 pb-3"
                >
                    <div>
                        <p class="vellum-meta">Canvas</p>
                        <h2
                            id="canvas-heading"
                            class="mt-1 text-sm font-medium text-document"
                        >
                            {{
                                featuredActive ? 'Working now' : 'Latest output'
                            }}
                        </h2>
                    </div>
                    <Link
                        href="/library"
                        class="focus-ring inline-flex items-center gap-2 text-sm text-ash transition-colors hover:text-document"
                    >
                        Library
                        <ArrowUpRight class="size-3.5" aria-hidden="true" />
                    </Link>
                </div>

                <div
                    v-if="featured"
                    class="relative min-h-[22rem] overflow-hidden border border-line bg-void sm:min-h-[30rem] lg:min-h-[56vh]"
                >
                    <div
                        class="vellum-grid pointer-events-none absolute inset-0 opacity-40"
                        aria-hidden="true"
                    />
                    <img
                        v-if="featuredImageReady"
                        :src="featured.image.url ?? undefined"
                        :alt="featuredAlt"
                        loading="lazy"
                        decoding="async"
                        class="absolute inset-0 z-10 size-full object-contain p-5 sm:p-10"
                        @error="featuredImageFailed = true"
                    />
                    <div
                        v-else
                        class="absolute inset-0 z-10 flex flex-col items-center justify-center px-6 py-16 text-center"
                        aria-live="polite"
                        aria-atomic="true"
                    >
                        <svg
                            viewBox="0 0 120 120"
                            class="size-20 text-edge-strong"
                            fill="none"
                            aria-hidden="true"
                        >
                            <path
                                d="M16 18h24l20 62 20-62h24L66 106H54L16 18Z"
                                stroke="currentColor"
                                stroke-width="2"
                            />
                            <path
                                d="M10 112h100"
                                stroke="currentColor"
                                stroke-opacity=".45"
                            />
                        </svg>
                        <p class="vellum-title mt-5 text-2xl text-document">
                            {{ featuredMessage }}
                        </p>
                        <p
                            class="mt-3 max-w-md text-sm leading-relaxed text-ash"
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

                    <div
                        class="absolute right-4 bottom-4 left-4 z-20 flex flex-wrap items-end justify-between gap-4 border-t border-line bg-void/85 pt-3 backdrop-blur-[1px]"
                    >
                        <p
                            class="line-clamp-2 max-w-[46rem] min-w-0 text-sm leading-relaxed text-ash"
                        >
                            {{ featured.prompt }}
                        </p>
                        <Link
                            :href="`/generations/${featured.id}`"
                            class="focus-ring inline-flex shrink-0 items-center gap-2 text-sm text-document"
                        >
                            Open plate
                            <ArrowRight class="size-3.5" aria-hidden="true" />
                        </Link>
                    </div>
                </div>

                <div
                    v-else
                    class="relative flex min-h-[22rem] flex-col items-center justify-center overflow-hidden border border-line bg-void px-6 py-16 text-center sm:min-h-[30rem] lg:min-h-[56vh]"
                >
                    <div
                        class="vellum-grid pointer-events-none absolute inset-0 opacity-40"
                        aria-hidden="true"
                    />
                    <div class="relative z-10 max-w-md">
                        <svg
                            viewBox="0 0 120 120"
                            class="mx-auto size-20 text-edge-strong"
                            fill="none"
                            aria-hidden="true"
                        >
                            <path
                                d="M16 18h24l20 62 20-62h24L66 106H54L16 18Z"
                                stroke="currentColor"
                                stroke-width="2"
                            />
                            <path
                                d="M10 112h100"
                                stroke="currentColor"
                                stroke-opacity=".45"
                            />
                        </svg>
                        <p class="vellum-title mt-5 text-2xl text-document">
                            The canvas is waiting.
                        </p>
                        <p class="mt-3 text-sm leading-relaxed text-ash">
                            Your first image will appear here. Nothing is shown
                            until the provider returns a result.
                        </p>
                    </div>
                </div>

                <dl
                    v-if="featured"
                    class="mt-px grid grid-cols-2 border border-t-0 border-line bg-elevated sm:grid-cols-4"
                >
                    <div
                        v-for="metric in featuredMetrics"
                        :key="metric.label"
                        class="border-line px-4 py-3 sm:border-r sm:last:border-r-0"
                    >
                        <dt class="vellum-meta">{{ metric.label }}</dt>
                        <dd
                            class="mt-1 truncate font-mono text-xs text-document"
                        >
                            {{ metric.value }}
                        </dd>
                    </div>
                </dl>
            </section>

            <form
                id="studio-form"
                class="mt-6 border border-line bg-elevated"
                novalidate
                @submit.prevent="submit"
            >
                <div
                    class="flex flex-wrap items-center justify-between gap-3 border-b border-line px-4 py-3 sm:px-5"
                >
                    <div>
                        <label
                            for="prompt"
                            class="text-sm font-medium text-document"
                        >
                            Describe the image
                        </label>
                        <p class="vellum-meta mt-0.5">
                            Light, material, mood, composition.
                        </p>
                    </div>
                    <span
                        id="prompt-count"
                        class="vellum-meta"
                        aria-live="polite"
                    >
                        {{ characterCount.toLocaleString() }} /
                        {{ maxPromptCharacters.toLocaleString() }}
                    </span>
                </div>

                <div class="space-y-3 px-4 py-4 sm:px-5">
                    <div
                        v-if="pricingError"
                        class="border border-line bg-void px-3 py-2.5 text-xs leading-relaxed text-document"
                        role="alert"
                    >
                        {{ pricingError }}
                    </div>

                    <div
                        v-if="prefill"
                        class="flex flex-wrap items-start justify-between gap-3 border border-edge bg-void px-3 py-2.5"
                        role="status"
                    >
                        <p class="text-xs leading-relaxed text-ash">
                            Setup copied from plate
                            <Link
                                :href="`/generations/${prefill.source_id}`"
                                class="link-underline font-mono text-document"
                            >
                                {{ prefill.source_id }}
                            </Link>
                            . This will be a new generation.
                        </p>
                        <span class="vellum-meta shrink-0">Fresh key</span>
                    </div>

                    <textarea
                        id="prompt"
                        ref="promptField"
                        v-model="form.prompt"
                        rows="3"
                        :maxlength="maxPromptCharacters"
                        class="vellum-input resize-y bg-void px-4 py-3.5 text-[0.98rem] leading-relaxed text-document sm:text-base"
                        placeholder="A stone observatory above a sea of cloud at first light."
                        :aria-describedby="
                            form.errors.prompt
                                ? 'prompt-help prompt-count prompt-error'
                                : 'prompt-help prompt-count'
                        "
                        :aria-invalid="form.errors.prompt ? 'true' : undefined"
                        required
                    />
                    <p id="prompt-help" class="vellum-meta">
                        Up to
                        {{ maxPromptCharacters.toLocaleString() }} Unicode
                        characters. The estimate follows every change.
                    </p>
                    <p
                        v-if="form.errors.prompt"
                        id="prompt-error"
                        class="border-l border-document pl-3 text-xs leading-relaxed text-document"
                        role="alert"
                    >
                        {{ form.errors.prompt }}
                    </p>
                </div>

                <div
                    class="flex flex-wrap items-center gap-2 border-t border-line px-4 py-3 sm:px-5"
                >
                    <span class="vellum-meta mr-1">Try</span>
                    <button
                        v-for="suggestion in suggestions"
                        :key="suggestion"
                        type="button"
                        class="focus-ring border border-edge px-2.5 py-1.5 text-left text-xs text-ash transition-colors hover:border-line hover:text-document motion-reduce:transition-none"
                        @click="useSuggestion(suggestion)"
                    >
                        {{ suggestion.split(',')[0] }}
                    </button>
                </div>

                <button
                    type="button"
                    class="focus-ring flex w-full items-center justify-between gap-4 border-t border-line px-4 py-3 text-left sm:px-5"
                    :aria-expanded="advancedOpen"
                    aria-controls="advanced-settings"
                    @click="advancedOpen = !advancedOpen"
                >
                    <span class="flex flex-wrap items-baseline gap-x-3 gap-y-1">
                        <span class="text-sm font-medium text-document">
                            Settings
                        </span>
                        <span class="vellum-meta">
                            {{ currentSizeLabel }} ·
                            {{ qualityLabel(form.quality) }} ·
                            {{ form.reference_images.length }} references
                        </span>
                    </span>
                    <ChevronDown
                        class="size-4 shrink-0 text-ash transition-transform duration-200 motion-reduce:transition-none"
                        :class="advancedOpen ? 'rotate-180' : ''"
                        aria-hidden="true"
                    />
                </button>

                <div
                    v-show="advancedOpen"
                    id="advanced-settings"
                    class="space-y-6 border-t border-line bg-void px-4 py-5 sm:px-5"
                >
                    <div>
                        <div
                            class="flex flex-wrap items-baseline justify-between gap-3"
                        >
                            <p class="text-sm font-medium text-document">
                                Reference URLs
                            </p>
                            <p class="vellum-meta">
                                {{ form.reference_images.length }} /
                                {{ maxReferenceImages }}
                            </p>
                        </div>
                        <p class="vellum-meta mt-2 tracking-normal normal-case">
                            Public http(s) links. Each reference is billed as
                            765 input image tokens.
                        </p>
                        <div class="mt-3 flex flex-col gap-2 sm:flex-row">
                            <div class="relative min-w-0 flex-1">
                                <Link2
                                    class="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-stone"
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
                                    class="vellum-input h-10 pr-3 pl-9 font-mono text-xs"
                                    :disabled="
                                        form.reference_images.length >=
                                        maxReferenceImages
                                    "
                                    @keydown.enter.prevent="addReference"
                                />
                            </div>
                            <button
                                type="button"
                                class="vellum-button vellum-button-quiet focus-ring h-10 shrink-0 px-4 text-xs"
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
                            class="mt-2 border-l border-document pl-3 text-xs leading-relaxed text-document"
                            role="alert"
                        >
                            {{ referenceError }}
                        </p>
                        <div
                            v-for="error in referenceErrors"
                            :key="error.key"
                            class="mt-2 border-l border-document pl-3 text-xs leading-relaxed text-document"
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
                                class="flex items-center gap-2 border border-edge bg-elevated px-2 py-2"
                            >
                                <span class="vellum-meta shrink-0">
                                    R{{ String(index + 1).padStart(2, '0') }}
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
                                    class="min-w-0 flex-1 bg-transparent font-mono text-[0.65rem] text-ash outline-none focus:text-document"
                                    @blur="validateReference(index)"
                                />
                                <a
                                    :href="reference"
                                    target="_blank"
                                    rel="noreferrer noopener"
                                    class="focus-ring shrink-0 text-stone hover:text-document"
                                    :aria-label="`Open reference ${index + 1}`"
                                >
                                    <ExternalLink
                                        class="size-3.5"
                                        aria-hidden="true"
                                    />
                                </a>
                                <button
                                    type="button"
                                    class="focus-ring shrink-0 text-stone hover:text-document"
                                    :aria-label="`Remove reference ${index + 1}`"
                                    @click="removeReference(index)"
                                >
                                    <X class="size-3.5" aria-hidden="true" />
                                </button>
                            </li>
                        </ul>
                    </div>

                    <fieldset>
                        <legend class="text-sm font-medium text-document">
                            Proportion
                        </legend>
                        <div class="mt-3 grid grid-cols-3 gap-2">
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
                                    class="flex h-full flex-col gap-2 border border-edge bg-elevated px-3 py-3 transition-colors peer-checked:border-document peer-checked:bg-void peer-focus-visible:ring-2 peer-focus-visible:ring-document peer-focus-visible:ring-offset-2 peer-focus-visible:ring-offset-void motion-reduce:transition-none"
                                >
                                    <span
                                        class="block w-6 border border-line bg-void"
                                        :style="{
                                            aspectRatio: option.ratio,
                                        }"
                                        aria-hidden="true"
                                    />
                                    <span class="text-xs text-document">
                                        {{ option.label }}
                                    </span>
                                    <span class="vellum-meta">{{
                                        option.value
                                    }}</span>
                                </span>
                            </label>
                        </div>
                    </fieldset>

                    <fieldset>
                        <legend class="text-sm font-medium text-document">
                            Quality
                        </legend>
                        <div class="mt-3 grid gap-2 sm:grid-cols-3">
                            <label
                                v-for="option in qualityOptions"
                                :key="option.value"
                                class="cursor-pointer"
                            >
                                <input
                                    v-model="form.quality"
                                    type="radio"
                                    name="quality"
                                    :value="option.value"
                                    class="peer sr-only"
                                />
                                <span
                                    class="flex h-full flex-col justify-between gap-3 border border-edge bg-elevated px-3 py-3 transition-colors peer-checked:border-document peer-checked:bg-void peer-focus-visible:ring-2 peer-focus-visible:ring-document peer-focus-visible:ring-offset-2 peer-focus-visible:ring-offset-void motion-reduce:transition-none"
                                >
                                    <span>
                                        <span
                                            class="block text-sm text-document"
                                        >
                                            {{ option.label }}
                                        </span>
                                        <span
                                            class="mt-1 block text-xs leading-snug text-ash"
                                        >
                                            {{ option.note }}
                                        </span>
                                    </span>
                                    <span
                                        v-if="option.output"
                                        class="ledger text-[0.6rem] text-stone"
                                    >
                                        {{ option.output.toLocaleString() }}
                                        out
                                    </span>
                                </span>
                            </label>
                        </div>
                    </fieldset>
                </div>

                <div class="border-t border-line bg-void px-4 py-4 sm:px-5">
                    <div class="flex flex-wrap items-end justify-between gap-5">
                        <div
                            class="min-w-[15rem] flex-1"
                            aria-live="polite"
                            aria-atomic="true"
                        >
                            <p class="vellum-meta">Live estimate</p>

                            <div
                                v-if="estimateLoading && !estimate"
                                class="mt-2 flex items-center gap-3"
                                role="status"
                            >
                                <span
                                    class="size-3 border border-line"
                                    aria-hidden="true"
                                />
                                <span class="vellum-meta">Refreshing…</span>
                            </div>

                            <template v-else-if="estimate">
                                <p
                                    class="vellum-title mt-2 text-2xl text-document sm:text-[1.75rem]"
                                >
                                    {{
                                        exactMoney(
                                            estimate.customer_charge_nanos.min,
                                        )
                                    }}
                                    <span class="text-stone">—</span>
                                    {{
                                        exactMoney(
                                            estimate.customer_charge_nanos.max,
                                        )
                                    }}
                                </p>
                                <p
                                    class="vellum-meta mt-1.5 tracking-normal normal-case"
                                >
                                    {{
                                        estimate.total_tokens.min.toLocaleString()
                                    }}–{{
                                        estimate.total_tokens.max.toLocaleString()
                                    }}
                                    tokens · {{ estimate.markup_percent }}%
                                    studio fee
                                </p>
                            </template>

                            <div v-else>
                                <p
                                    class="vellum-title mt-2 text-2xl text-stone"
                                >
                                    —
                                </p>
                                <p
                                    class="vellum-meta mt-1.5 tracking-normal normal-case"
                                >
                                    {{
                                        pricingError
                                            ? 'Pricing is unavailable; estimates are paused.'
                                            : 'Write a prompt to see a live range.'
                                    }}
                                </p>
                            </div>
                        </div>

                        <button
                            type="submit"
                            class="vellum-button focus-ring w-full px-5 py-3.5 text-sm sm:w-auto"
                            :disabled="!canSubmit"
                        >
                            <template v-if="form.processing">Sending…</template>
                            <template v-else>
                                Make this image
                                <ArrowRight class="size-4" aria-hidden="true" />
                            </template>
                        </button>
                    </div>

                    <details
                        v-if="estimateReady"
                        class="mt-4 border-t border-line pt-3"
                    >
                        <summary class="vellum-meta cursor-pointer list-none">
                            Token ledger
                        </summary>
                        <dl
                            class="mt-3 grid gap-2 font-mono text-[0.62rem] sm:grid-cols-2"
                        >
                            <div
                                class="flex items-baseline justify-between gap-3"
                            >
                                <dt class="text-stone">Input text</dt>
                                <dd class="ledger text-document">
                                    {{
                                        estimate?.text_tokens.min.toLocaleString()
                                    }}–
                                    {{
                                        estimate?.text_tokens.max.toLocaleString()
                                    }}
                                </dd>
                            </div>
                            <div
                                class="flex items-baseline justify-between gap-3"
                            >
                                <dt class="text-stone">Input images</dt>
                                <dd class="ledger text-document">
                                    {{
                                        estimate?.input_image_tokens.toLocaleString()
                                    }}
                                </dd>
                            </div>
                            <div
                                class="flex items-baseline justify-between gap-3"
                            >
                                <dt class="text-stone">Output</dt>
                                <dd class="ledger text-document">
                                    {{
                                        estimate?.output_tokens.toLocaleString()
                                    }}
                                </dd>
                            </div>
                            <div
                                class="flex items-baseline justify-between gap-3"
                            >
                                <dt class="text-stone">Reasoning</dt>
                                <dd class="ledger text-document">
                                    {{
                                        estimate?.reasoning_tokens.min.toLocaleString()
                                    }}–
                                    {{
                                        estimate?.reasoning_tokens.max.toLocaleString()
                                    }}
                                </dd>
                            </div>
                        </dl>
                    </details>

                    <p
                        v-if="estimateError"
                        class="mt-3 border-l border-document pl-3 text-xs leading-relaxed text-ash"
                        role="alert"
                    >
                        {{ estimateError }}
                    </p>
                    <p class="vellum-meta mt-3 tracking-normal normal-case">
                        Estimate only. The final charge follows real token
                        usage.
                    </p>
                    <p
                        v-if="!pricing"
                        class="mt-3 border border-line bg-elevated px-4 py-3 text-xs leading-relaxed text-ash"
                        role="status"
                    >
                        Prompt composition stays open, but submission is paused
                        until Pixelforge pricing is reachable.
                    </p>
                </div>
            </form>

            <details class="mt-6 border border-line bg-void">
                <summary
                    class="flex cursor-pointer list-none flex-wrap items-center justify-between gap-3 px-4 py-3"
                >
                    <span class="text-sm font-medium text-document">
                        Recent plates
                    </span>
                    <span class="vellum-meta">
                        {{ recent.length }} archived · open to review
                    </span>
                </summary>
                <div
                    v-if="recent.length > 0"
                    class="grid gap-4 border-t border-line p-4 sm:grid-cols-2 xl:grid-cols-3"
                >
                    <GenerationCard
                        v-for="generation in recent"
                        :key="generation.id"
                        :generation="generation"
                        compact
                    />
                </div>
                <p
                    v-else
                    class="border-t border-line px-4 py-6 text-sm text-ash"
                >
                    Nothing is archived yet.
                </p>
            </details>
        </div>
    </div>
</template>
