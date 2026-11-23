<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ArrowRight, Link2, Plus, Sparkles, X } from '@lucide/vue';
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import GenerationCard from '@/components/GenerationCard.vue';
import {
    csrfToken,
    qualityDescription,
    qualityLabel,
    randomUuid,
} from '@/lib/vellum';
import type { EstimatePayload, Generation, PricingPayload } from '@/types';

const props = defineProps<{
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
}>();

const form = useForm<{
    prompt: string;
    size: string;
    quality: string;
    reference_images: string[];
    attempt_token: string;
}>({
    prompt: props.prefill?.prompt ?? '',
    size: props.prefill?.size ?? props.defaultSize,
    quality: props.prefill?.quality ?? props.defaultQuality,
    reference_images: props.prefill?.reference_images ?? [],
    attempt_token: randomUuid(),
});

const promptField = ref<HTMLTextAreaElement | null>(null);
const referenceInput = ref<HTMLInputElement | null>(null);
const referenceError = ref<string | null>(null);
const estimate = ref<EstimatePayload | null>(null);
const estimateLoading = ref(false);
const estimateError = ref<string | null>(null);
const attempted = ref(false);

let estimateTimer: ReturnType<typeof setTimeout> | undefined;
let estimateController: AbortController | null = null;

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

const suggestions = [
    'A pale observatory above a sea of cloud, morning light, fine grain.',
    'A vermilion ceramic bowl of pale fruit on warm stone, painterly still life.',
    'A quiet reading room with one chair, long afternoon light, dust in the air.',
];

function scheduleEstimate(): void {
    window.clearTimeout(estimateTimer);
    estimateTimer = window.setTimeout(runEstimate, 350);
}

async function runEstimate(): Promise<void> {
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

function removeReference(index: number): void {
    form.reference_images.splice(index, 1);
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
    () => [form.prompt, form.size, form.quality, form.reference_images.length],
    () => {
        rotateAttemptToken();
        scheduleEstimate();
    },
);

onMounted(() => {
    void runEstimate();
});

onBeforeUnmount(() => {
    window.clearTimeout(estimateTimer);
    estimateController?.abort();
});
</script>

<template>
    <Head title="The studio" />

    <div class="space-y-12">
        <header
            class="flex flex-col justify-between gap-8 border-b border-line pb-8 lg:flex-row lg:items-end"
        >
            <div>
                <p class="eyebrow flex items-center gap-3">
                    <span class="h-px w-8 bg-vermilion" aria-hidden="true" />
                    Studio / new plate
                </p>
                <h1
                    class="display-title mt-5 max-w-[12ch] text-[clamp(2.9rem,5.6vw,4.8rem)] text-ink"
                >
                    The next
                    <em class="text-vermilion">plate.</em>
                </h1>
            </div>
            <div class="max-w-sm space-y-2 lg:text-right">
                <p class="text-sm leading-relaxed text-ink-soft">
                    Compose the image you want. Vellum sends it to Pixelforge,
                    watches the webhooks, and records the exact cost when it is
                    finished.
                </p>
                <p
                    class="font-mono text-[0.6rem] tracking-[0.13em] text-ink-soft uppercase"
                >
                    Failed work is never charged
                </p>
            </div>
        </header>

        <div
            v-if="pricingError"
            class="border border-ochre/35 bg-[#f4ead8] px-4 py-3 text-sm text-ochre"
            role="alert"
        >
            {{ pricingError }}
        </div>

        <div
            v-if="prefill"
            class="flex flex-wrap items-center justify-between gap-3 border border-line bg-paper px-4 py-3 text-xs text-ink-soft"
        >
            <span>
                Setup copied from plate
                <Link
                    :href="`/generations/${prefill.source_id}`"
                    class="font-mono text-ink underline decoration-line underline-offset-4 hover:text-vermilion"
                    >{{ prefill.source_id }}</Link
                >. This will be a new generation.
            </span>
            <span class="font-mono text-[0.58rem] tracking-[0.13em] uppercase"
                >Fresh attempt</span
            >
        </div>

        <div class="grid gap-10 lg:grid-cols-[minmax(0,1fr)_23rem] lg:gap-12">
            <form
                id="studio-form"
                class="space-y-8"
                novalidate
                @submit.prevent="submit"
            >
                <section class="paper-panel p-5 sm:p-7">
                    <div class="flex items-baseline justify-between gap-4">
                        <div>
                            <p class="eyebrow">01 / The prompt</p>
                            <h2
                                class="mt-2 font-display text-2xl tracking-[-0.02em] text-ink"
                            >
                                What should we make?
                            </h2>
                        </div>
                        <span
                            class="ledger text-[0.6rem] tracking-[0.08em]"
                            :class="
                                atCharacterLimit
                                    ? 'text-vermilion'
                                    : 'text-ink-soft'
                            "
                            >{{ characterCount.toLocaleString() }} /
                            {{ maxPromptCharacters.toLocaleString() }}</span
                        >
                    </div>

                    <label for="prompt" class="sr-only">Image prompt</label>
                    <textarea
                        id="prompt"
                        ref="promptField"
                        v-model="form.prompt"
                        rows="7"
                        :maxlength="maxPromptCharacters"
                        class="mt-5 w-full resize-y border border-line bg-background/45 px-4 py-4 font-display text-[1.15rem] leading-[1.55] tracking-[-0.01em] text-ink placeholder:text-ink-soft/60 focus:border-ink focus:outline-none"
                        placeholder="A quiet observatory above a sea of cloud, morning light, fine grain…"
                        aria-describedby="prompt-help prompt-error"
                        :aria-invalid="Boolean(form.errors.prompt)"
                        required
                    />
                    <p id="prompt-help" class="mt-2 text-xs text-ink-soft">
                        Be specific about light, material, and mood. Up to 4,000
                        Unicode characters.
                    </p>
                    <p
                        v-if="form.errors.prompt"
                        id="prompt-error"
                        class="mt-2 text-sm text-vermilion"
                    >
                        {{ form.errors.prompt }}
                    </p>

                    <div class="mt-5 border-t border-line-soft pt-4">
                        <p
                            class="font-mono text-[0.58rem] tracking-[0.14em] text-ink-soft uppercase"
                        >
                            Or begin with a study
                        </p>
                        <div class="mt-3 flex flex-wrap gap-2">
                            <button
                                v-for="suggestion in suggestions"
                                :key="suggestion"
                                type="button"
                                class="border border-line px-3 py-2 text-left text-[0.7rem] leading-snug text-ink-soft transition-colors hover:border-ink hover:text-ink"
                                @click="useSuggestion(suggestion)"
                            >
                                {{ suggestion.split(',')[0] }}
                            </button>
                        </div>
                    </div>
                </section>

                <section class="paper-panel p-5 sm:p-7">
                    <div class="flex items-baseline justify-between gap-4">
                        <div>
                            <p class="eyebrow">02 / References</p>
                            <h2
                                class="mt-2 font-display text-2xl tracking-[-0.02em] text-ink"
                            >
                                Set the direction.
                            </h2>
                        </div>
                        <span class="ledger text-[0.6rem] text-ink-soft"
                            >{{ form.reference_images.length }} /
                            {{ maxReferenceImages }}</span
                        >
                    </div>
                    <p
                        class="mt-2 max-w-xl text-xs leading-relaxed text-ink-soft"
                    >
                        Optional public image URLs. Pixelforge does not download
                        them, but bills each one at 765 input image tokens.
                    </p>

                    <div class="mt-4 flex flex-col gap-2 sm:flex-row">
                        <div class="relative flex-1">
                            <Link2
                                class="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-ink-soft"
                                aria-hidden="true"
                            />
                            <label for="reference-url" class="sr-only"
                                >Reference image URL</label
                            >
                            <input
                                id="reference-url"
                                ref="referenceInput"
                                type="url"
                                inputmode="url"
                                placeholder="https://example.com/reference.jpg"
                                class="h-10 w-full border border-line bg-background/45 pr-3 pl-9 text-sm text-ink placeholder:text-ink-soft/60 focus:border-ink focus:outline-none"
                                :disabled="
                                    form.reference_images.length >=
                                    maxReferenceImages
                                "
                                @keydown.enter.prevent="addReference"
                            />
                        </div>
                        <button
                            type="button"
                            class="inline-flex h-10 items-center justify-center gap-2 border border-ink px-4 text-xs font-medium text-ink transition-colors hover:bg-ink hover:text-paper disabled:cursor-not-allowed disabled:opacity-40"
                            :disabled="
                                form.reference_images.length >=
                                maxReferenceImages
                            "
                            @click="addReference"
                        >
                            <Plus class="size-3.5" aria-hidden="true" />
                            Add reference
                        </button>
                    </div>
                    <p
                        v-if="referenceError"
                        class="mt-2 text-xs text-vermilion"
                    >
                        {{ referenceError }}
                    </p>
                    <p
                        v-if="form.errors['reference_images.0']"
                        class="mt-2 text-xs text-vermilion"
                    >
                        {{ form.errors['reference_images.0'] }}
                    </p>

                    <ul
                        v-if="form.reference_images.length > 0"
                        class="mt-4 space-y-2"
                    >
                        <li
                            v-for="(reference, index) in form.reference_images"
                            :key="reference"
                            class="flex items-center gap-3 border border-line-soft bg-background/35 px-3 py-2"
                        >
                            <span class="ledger text-[0.6rem] text-ink-soft">{{
                                String(index + 1).padStart(2, '0')
                            }}</span>
                            <a
                                :href="reference"
                                target="_blank"
                                rel="noreferrer noopener"
                                class="min-w-0 flex-1 truncate font-mono text-[0.65rem] text-ink-soft hover:text-vermilion"
                                >{{ reference }}</a
                            >
                            <button
                                type="button"
                                class="focus-ring text-ink-soft hover:text-vermilion"
                                :aria-label="`Remove reference ${index + 1}`"
                                @click="removeReference(index)"
                            >
                                <X class="size-3.5" aria-hidden="true" />
                            </button>
                        </li>
                    </ul>
                </section>

                <div class="grid gap-8 sm:grid-cols-2">
                    <fieldset class="paper-panel p-5 sm:p-6">
                        <legend class="eyebrow">03 / Proportion</legend>
                        <div class="mt-4 space-y-2">
                            <label
                                v-for="option in sizeOptions"
                                :key="option.value"
                                class="block cursor-pointer"
                            >
                                <input
                                    v-model="form.size"
                                    type="radio"
                                    name="size"
                                    :value="option.value"
                                    class="peer sr-only"
                                />
                                <span
                                    class="flex items-center gap-3 border border-line-soft px-3 py-3 transition-colors peer-checked:border-ink peer-checked:bg-background/45 peer-focus-visible:ring-2 peer-focus-visible:ring-vermilion peer-focus-visible:ring-offset-2"
                                >
                                    <span
                                        class="block h-7 shrink-0 border border-ink/50 bg-paper"
                                        :style="{ aspectRatio: option.ratio }"
                                        aria-hidden="true"
                                    />
                                    <span class="min-w-0 flex-1">
                                        <span
                                            class="block text-sm font-medium text-ink"
                                            >{{ option.label }}</span
                                        >
                                        <span
                                            class="block text-[0.68rem] text-ink-soft"
                                            >{{ option.note }}</span
                                        >
                                    </span>
                                    <span
                                        class="font-mono text-[0.55rem] text-ink-soft"
                                        >{{ option.value }}</span
                                    >
                                </span>
                            </label>
                        </div>
                    </fieldset>

                    <fieldset class="paper-panel p-5 sm:p-6">
                        <legend class="eyebrow">04 / Quality</legend>
                        <div class="mt-4 space-y-2">
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
                                    class="block border border-line-soft px-3 py-3 transition-colors peer-checked:border-ink peer-checked:bg-background/45 peer-focus-visible:ring-2 peer-focus-visible:ring-vermilion peer-focus-visible:ring-offset-2"
                                >
                                    <span
                                        class="flex items-baseline justify-between gap-3"
                                    >
                                        <span
                                            class="text-sm font-medium text-ink"
                                            >{{ option.label }}</span
                                        >
                                        <span
                                            v-if="option.output"
                                            class="ledger text-[0.55rem] text-ink-soft"
                                            >{{
                                                option.output.toLocaleString()
                                            }}
                                            out</span
                                        >
                                    </span>
                                    <span
                                        class="mt-1 block text-[0.68rem] leading-snug text-ink-soft"
                                        >{{ option.note }}</span
                                    >
                                </span>
                            </label>
                        </div>
                    </fieldset>
                </div>

                <div
                    class="flex items-start gap-3 border border-line bg-paper px-4 py-3 text-xs leading-relaxed text-ink-soft"
                >
                    <Sparkles
                        class="mt-0.5 size-3.5 shrink-0 text-vermilion"
                        aria-hidden="true"
                    />
                    <p>
                        A generation typically takes 5–30 seconds. You can leave
                        this page; the studio will keep working, and the plate
                        will wait in your library. Every attempt gets a stable
                        idempotency key, so a retry can never create or charge
                        twice.
                    </p>
                </div>

                <button
                    type="submit"
                    class="focus-ring inline-flex w-full items-center justify-center gap-3 bg-ink px-6 py-4 text-sm font-medium text-paper transition-colors hover:bg-vermilion disabled:cursor-not-allowed disabled:opacity-45 lg:hidden"
                    :disabled="!canSubmit"
                >
                    <span v-if="form.processing">Sending to the studio…</span>
                    <template v-else>
                        Make this image
                        <ArrowRight class="size-4" aria-hidden="true" />
                    </template>
                </button>
            </form>

            <aside class="space-y-5 lg:sticky lg:top-24 lg:self-start">
                <div
                    class="bg-ink p-5 text-paper shadow-[0_30px_70px_-45px_rgba(33,29,23,0.8)] sm:p-6"
                >
                    <div class="flex items-center justify-between">
                        <p
                            class="font-mono text-[0.58rem] tracking-[0.16em] text-paper/50 uppercase"
                        >
                            Estimated charge
                        </p>
                        <span
                            class="font-mono text-[0.55rem] tracking-[0.12em] text-paper/40 uppercase"
                            >USD / tax excl.</span
                        >
                    </div>

                    <div
                        v-if="estimateLoading && !estimate"
                        class="mt-5 space-y-3"
                    >
                        <div class="h-11 w-4/5 animate-pulse bg-paper/10" />
                        <div class="h-3 w-3/5 animate-pulse bg-paper/10" />
                    </div>
                    <div v-else-if="estimate" class="mt-4">
                        <p
                            class="font-display text-[2.15rem] leading-[1.05] tracking-[-0.045em] text-paper sm:text-[2.45rem]"
                        >
                            {{ estimate.customer_charge.min }}
                            <span class="text-paper/35">—</span>
                            {{ estimate.customer_charge.max }}
                        </p>
                        <p
                            class="mt-2 font-mono text-[0.6rem] leading-relaxed tracking-[0.08em] text-paper/50 uppercase"
                        >
                            {{ estimate.total_tokens.min.toLocaleString() }}–{{
                                estimate.total_tokens.max.toLocaleString()
                            }}
                            tokens · includes a {{ estimate.markup_percent }}%
                            studio fee
                        </p>
                    </div>
                    <div v-else class="mt-4">
                        <p class="font-display text-[2rem] text-paper/40">—</p>
                        <p class="mt-2 text-xs text-paper/50">
                            {{
                                pricingError
                                    ? 'Pricing is paused.'
                                    : 'Write a prompt to see a live range.'
                            }}
                        </p>
                    </div>

                    <p
                        v-if="estimateError"
                        class="mt-4 border-t border-paper/15 pt-3 text-xs leading-relaxed text-paper/60"
                    >
                        {{ estimateError }}
                    </p>

                    <dl
                        v-if="estimate"
                        class="mt-5 space-y-2 border-t border-paper/15 pt-4 font-mono text-[0.62rem]"
                    >
                        <div class="flex justify-between gap-3">
                            <dt class="text-paper/45">Input text</dt>
                            <dd class="text-paper/80">
                                {{
                                    estimate.text_tokens.min.toLocaleString()
                                }}–{{
                                    estimate.text_tokens.max.toLocaleString()
                                }}
                            </dd>
                        </div>
                        <div class="flex justify-between gap-3">
                            <dt class="text-paper/45">Input images</dt>
                            <dd class="text-paper/80">
                                {{
                                    estimate.input_image_tokens.toLocaleString()
                                }}
                            </dd>
                        </div>
                        <div class="flex justify-between gap-3">
                            <dt class="text-paper/45">Output</dt>
                            <dd class="text-paper/80">
                                {{ estimate.output_tokens.toLocaleString() }}
                            </dd>
                        </div>
                        <div class="flex justify-between gap-3">
                            <dt class="text-paper/45">Reasoning</dt>
                            <dd class="text-paper/80">
                                {{
                                    estimate.reasoning_tokens.min.toLocaleString()
                                }}–{{
                                    estimate.reasoning_tokens.max.toLocaleString()
                                }}
                            </dd>
                        </div>
                    </dl>

                    <button
                        type="submit"
                        form="studio-form"
                        class="focus-ring mt-6 hidden w-full items-center justify-center gap-3 bg-vermilion px-5 py-3.5 text-sm font-medium text-paper transition-colors hover:bg-paper hover:text-ink disabled:cursor-not-allowed disabled:opacity-40 lg:inline-flex"
                        :disabled="!canSubmit"
                    >
                        <span v-if="form.processing"
                            >Sending to the studio…</span
                        >
                        <template v-else>
                            Make this image
                            <ArrowRight class="size-4" aria-hidden="true" />
                        </template>
                    </button>

                    <p
                        class="mt-3 text-center font-mono text-[0.55rem] leading-relaxed tracking-[0.08em] text-paper/40 uppercase"
                    >
                        Final charge follows real token usage. Failed
                        generations are free.
                    </p>
                </div>

                <div class="border border-line bg-paper p-5">
                    <p class="eyebrow">After the prompt</p>
                    <ol class="mt-4 space-y-3">
                        <li
                            class="flex gap-3 text-xs leading-relaxed text-ink-soft"
                        >
                            <span class="ledger text-[0.6rem] text-vermilion"
                                >01</span
                            >
                            <span
                                >Pixelforge accepts the generation and queues
                                it.</span
                            >
                        </li>
                        <li
                            class="flex gap-3 text-xs leading-relaxed text-ink-soft"
                        >
                            <span class="ledger text-[0.6rem] text-vermilion"
                                >02</span
                            >
                            <span
                                >Signed webhooks move it through the studio;
                                polling is the safety net.</span
                            >
                        </li>
                        <li
                            class="flex gap-3 text-xs leading-relaxed text-ink-soft"
                        >
                            <span class="ledger text-[0.6rem] text-vermilion"
                                >03</span
                            >
                            <span
                                >The exact usage is priced, charged once, and
                                archived here.</span
                            >
                        </li>
                    </ol>
                </div>

                <p
                    v-if="!pricing"
                    class="border border-line bg-paper px-4 py-3 text-xs text-ink-soft"
                >
                    Prompt composition stays open, but the studio cannot submit
                    until Pixelforge pricing is reachable.
                </p>
            </aside>
        </div>

        <section aria-labelledby="recent-plates">
            <div
                class="flex flex-wrap items-end justify-between gap-4 border-b border-line pb-4"
            >
                <div>
                    <p class="eyebrow">Recent work</p>
                    <h2
                        id="recent-plates"
                        class="mt-2 font-display text-3xl tracking-[-0.03em] text-ink"
                    >
                        Plates from this desk
                    </h2>
                </div>
                <Link
                    href="/library"
                    class="link-underline text-xs font-medium text-ink-soft"
                    >View the full library</Link
                >
            </div>

            <div
                v-if="recent.length > 0"
                class="mt-6 grid gap-5 sm:grid-cols-2 lg:grid-cols-3"
            >
                <GenerationCard
                    v-for="generation in recent"
                    :key="generation.id"
                    :generation="generation"
                    compact
                />
            </div>
            <div
                v-else
                class="mt-6 border border-dashed border-line bg-paper/60 px-6 py-10 text-center"
            >
                <p class="font-display text-2xl text-ink">
                    The first plate is yours.
                </p>
                <p class="mt-2 text-sm text-ink-soft">
                    Your finished images, estimates, and exact charges will
                    appear here.
                </p>
            </div>
        </section>
    </div>
</template>
