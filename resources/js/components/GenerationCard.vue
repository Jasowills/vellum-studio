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
        class="group block focus-visible:outline-none"
        :aria-label="`Open generation: ${generation.prompt}`"
    >
        <article
            class="overflow-hidden border border-line bg-paper transition-transform duration-200 group-focus-visible:ring-2 group-focus-visible:ring-signal group-focus-visible:ring-offset-2 group-focus-visible:ring-offset-background hover:-translate-y-0.5 motion-reduce:transition-none motion-reduce:hover:translate-y-0"
        >
            <div
                class="relative overflow-hidden bg-paper-deep"
                :style="{ aspectRatio: ratioCss(generation.size) }"
            >
                <div
                    class="signal-grid pointer-events-none absolute inset-0 opacity-30"
                    aria-hidden="true"
                />

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
                    class="relative z-10 flex h-full w-full flex-col items-center justify-center gap-3 px-6 text-center"
                    aria-live="polite"
                    aria-atomic="true"
                >
                    <span
                        class="font-mono text-[0.625rem] tracking-[0.2em] text-signal-muted uppercase"
                    >
                        {{ activeCopy }}
                    </span>
                    <span class="h-px w-16 bg-signal-line" aria-hidden="true" />
                </div>

                <div
                    v-else
                    class="relative z-10 flex h-full w-full flex-col items-center justify-center gap-3 px-6 text-center"
                >
                    <span
                        class="font-display text-4xl leading-none text-signal-faint"
                        aria-hidden="true"
                    >
                        ×
                    </span>
                    <span
                        class="font-mono text-[0.625rem] tracking-[0.18em] text-signal-muted uppercase"
                    >
                        {{ placeholderTitle }}
                    </span>
                    <span
                        class="max-w-[16rem] text-xs leading-relaxed text-signal-muted"
                    >
                        {{ placeholderCopy }}
                    </span>
                </div>

                <div class="absolute top-3 left-3 z-20">
                    <StatusBadge
                        :label="generation.status_label"
                        :tone="generation.status_tone"
                        :pulse="active"
                    />
                </div>
            </div>

            <div class="space-y-3 p-4" :class="compact ? 'sm:p-3.5' : ''">
                <p
                    class="line-clamp-2 font-display text-[1.05rem] leading-[1.25] tracking-[-0.015em] text-signal"
                    :class="compact ? 'text-[0.98rem]' : ''"
                >
                    {{ generation.prompt }}
                </p>
                <div
                    class="flex items-center justify-between gap-3 font-mono text-[0.64rem] tracking-[0.08em] text-signal-muted uppercase"
                >
                    <span class="min-w-0 truncate">
                        {{ ratioLabel(generation.size) }} ·
                        {{ generation.quality }}
                    </span>
                    <span class="shrink-0 text-signal">
                        {{
                            generation.cost.customer_charge ??
                            (generation.status === 'failed' ? 'No charge' : '—')
                        }}
                    </span>
                </div>
                <div
                    class="flex items-center justify-between gap-3 text-xs text-signal-muted"
                >
                    <span>{{ formatRelative(generation.created_at) }}</span>
                    <span
                        class="font-mono text-[0.6rem] tracking-[0.12em] text-signal uppercase transition-opacity group-hover:opacity-100 motion-reduce:transition-none"
                    >
                        Open →
                    </span>
                </div>
            </div>
        </article>
    </Link>
</template>
