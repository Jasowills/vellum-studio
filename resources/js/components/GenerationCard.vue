<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import StatusBadge from '@/components/StatusBadge.vue';
import {
    formatRelative,
    generationAlt,
    ratioCss,
    ratioLabel,
} from '@/lib/vellum';
import type { Generation } from '@/types';

const props = withDefaults(
    defineProps<{
        generation: Generation;
        compact?: boolean;
    }>(),
    {
        compact: false,
    },
);

const imageFailed = ref(false);

const ACTIVE_STATUSES: Generation['status'][] = [
    'pending',
    'queued',
    'processing',
];

const active = computed(() =>
    ACTIVE_STATUSES.includes(props.generation.status),
);

const showImage = computed(
    () =>
        Boolean(
            props.generation.image.available && props.generation.image.url,
        ) && !imageFailed.value,
);

const placeholderTitle = computed(() => {
    if (imageFailed.value) {
        return 'Image unavailable';
    }

    if (props.generation.image.expired) {
        return 'Link expired';
    }

    if (props.generation.status === 'failed') {
        return 'No plate';
    }

    return 'Not made';
});

const placeholderCopy = computed(() => {
    if (imageFailed.value) {
        return 'The signed image could not be loaded.';
    }

    if (props.generation.image.expired) {
        return 'The provider’s signed link has lapsed.';
    }

    if (props.generation.status === 'failed') {
        return 'Nothing was charged for this attempt.';
    }

    return 'The image is still being prepared.';
});

const activeCopy = computed(() => {
    if (props.generation.status === 'processing') {
        return 'Developing';
    }

    if (props.generation.status === 'queued') {
        return 'Queued';
    }

    return 'Submitted';
});

watch(
    () => props.generation.image.url,
    () => {
        imageFailed.value = false;
    },
);
</script>

<template>
    <Link
        :href="`/generations/${generation.id}`"
        class="group block min-w-0 focus-visible:outline-none"
    >
        <span class="sr-only">Open generation: </span>
        <article
            class="flex h-full min-w-0 flex-col border border-line bg-void transition-colors duration-200 group-focus-visible:ring-2 group-focus-visible:ring-document group-focus-visible:ring-offset-2 group-focus-visible:ring-offset-void hover:border-edge-strong motion-reduce:transition-none"
        >
            <div
                class="relative min-w-0 overflow-hidden bg-abyss"
                :style="{ aspectRatio: ratioCss(generation.size) }"
            >
                <img
                    v-if="showImage"
                    :src="generation.image.url ?? undefined"
                    :alt="generationAlt(generation)"
                    loading="lazy"
                    decoding="async"
                    class="relative z-10 h-full w-full object-contain grayscale"
                    @error="imageFailed = true"
                />

                <div
                    v-else-if="active"
                    class="relative z-10 flex h-full w-full flex-col items-center justify-center gap-2 px-4 text-center"
                    aria-live="polite"
                    aria-atomic="true"
                >
                    <span
                        class="font-mono text-[0.6rem] tracking-[0.14em] text-document"
                    >
                        {{ activeCopy }}
                    </span>
                    <span class="h-px w-8 bg-line" aria-hidden="true" />
                </div>

                <div
                    v-else
                    class="relative z-10 flex h-full w-full flex-col items-center justify-center gap-2 px-4 text-center"
                >
                    <span
                        class="font-display text-3xl leading-none text-stone"
                        aria-hidden="true"
                    >
                        ×
                    </span>
                    <span
                        class="font-mono text-[0.6rem] tracking-[0.12em] text-document"
                    >
                        {{ placeholderTitle }}
                    </span>
                    <span
                        class="max-w-[15rem] text-[0.7rem] leading-snug text-ash"
                    >
                        {{ placeholderCopy }}
                    </span>
                </div>

                <div class="absolute top-2 left-2 z-20">
                    <StatusBadge
                        :label="generation.status_label"
                        :tone="generation.status_tone"
                        :pulse="active"
                    />
                </div>

                <div
                    class="absolute right-2 bottom-2 z-20 border border-line bg-void px-1.5 py-1"
                >
                    <span
                        class="ledger text-[0.65rem] leading-none text-document"
                    >
                        {{
                            generation.cost.customer_charge_exact ??
                            generation.cost.customer_charge ??
                            (generation.status === 'failed' ? 'No charge' : '—')
                        }}
                    </span>
                </div>
            </div>

            <div
                class="flex min-w-0 flex-1 flex-col gap-2 px-3 py-3"
                :class="compact ? 'sm:px-2.5 sm:py-2.5' : 'sm:px-3.5 sm:py-3'"
            >
                <p
                    class="vellum-title line-clamp-2 text-[0.98rem] text-document"
                    :class="compact ? 'text-[0.9rem]' : ''"
                >
                    {{ generation.prompt }}
                </p>
                <div
                    class="mt-auto flex min-w-0 flex-wrap items-baseline justify-between gap-x-2 gap-y-1 font-mono text-[0.6rem] leading-snug tracking-[0.06em] text-stone"
                >
                    <span class="min-w-0 truncate">
                        {{ ratioLabel(generation.size) }} ·
                        {{ generation.quality }}
                    </span>
                    <span class="shrink-0 text-document">
                        {{ formatRelative(generation.created_at) }}
                    </span>
                </div>
            </div>
        </article>
    </Link>
</template>
