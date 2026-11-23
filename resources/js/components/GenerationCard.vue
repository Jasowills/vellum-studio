<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { computed } from 'vue';
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

const active = computed(() =>
    ['pending', 'queued', 'processing'].includes(props.generation.status),
);
</script>

<template>
    <Link
        :href="`/generations/${generation.id}`"
        class="group block focus-visible:outline-none"
        :aria-label="`Open generation: ${generation.prompt}`"
    >
        <article
            class="overflow-hidden border border-line bg-paper transition-[transform,box-shadow,border-color] duration-300 group-hover:-translate-y-0.5 group-hover:border-ink/30 group-hover:shadow-[0_24px_50px_-38px_rgba(33,29,23,0.55)] group-focus-visible:ring-2 group-focus-visible:ring-vermilion group-focus-visible:ring-offset-4 group-focus-visible:ring-offset-background"
        >
            <div
                class="relative overflow-hidden bg-paper-deep"
                :style="{ aspectRatio: ratioCss(generation.size) }"
            >
                <img
                    v-if="generation.image.available && generation.image.url"
                    :src="generation.image.url"
                    :alt="generationAlt(generation)"
                    loading="lazy"
                    decoding="async"
                    class="h-full w-full object-cover transition-transform duration-700 group-hover:scale-[1.015]"
                />
                <div
                    v-else-if="active"
                    class="skeleton-sheen absolute inset-0 flex flex-col items-center justify-center gap-3"
                >
                    <span
                        class="font-mono text-[0.625rem] tracking-[0.2em] text-ink-soft uppercase"
                        >Developing</span
                    >
                    <span class="animate-breathe h-px w-16 bg-vermilion/60" />
                </div>
                <div
                    v-else
                    class="absolute inset-0 flex flex-col items-center justify-center gap-3 bg-[linear-gradient(135deg,var(--paper)_0%,var(--paper-deep)_100%)] px-6 text-center"
                >
                    <span
                        class="font-display text-4xl text-ink/30"
                        aria-hidden="true"
                        >×</span
                    >
                    <span class="eyebrow">{{
                        generation.image.expired ? 'Image expired' : 'Not made'
                    }}</span>
                    <span class="max-w-[16rem] text-xs text-ink-soft">
                        {{
                            generation.image.expired
                                ? 'The provider’s signed link has lapsed.'
                                : 'Nothing was charged for this attempt.'
                        }}
                    </span>
                </div>

                <div class="absolute top-3 left-3">
                    <StatusBadge
                        :label="generation.status_label"
                        :tone="generation.status_tone"
                        :pulse="active"
                    />
                </div>
            </div>

            <div class="space-y-3 p-4" :class="compact ? 'sm:p-3.5' : ''">
                <p
                    class="line-clamp-2 font-display text-[1.05rem] leading-[1.25] tracking-[-0.015em] text-ink"
                    :class="compact ? 'text-[0.98rem]' : ''"
                >
                    {{ generation.prompt }}
                </p>
                <div
                    class="flex items-center justify-between gap-3 font-mono text-[0.64rem] tracking-[0.08em] text-ink-soft uppercase"
                >
                    <span
                        >{{ ratioLabel(generation.size) }} ·
                        {{ generation.quality }}</span
                    >
                    <span class="text-ink">{{
                        generation.cost.customer_charge ??
                        (generation.status === 'failed' ? 'No charge' : '—')
                    }}</span>
                </div>
                <div
                    class="flex items-center justify-between text-xs text-ink-soft"
                >
                    <span>{{ formatRelative(generation.created_at) }}</span>
                    <span
                        class="font-mono text-[0.6rem] tracking-[0.12em] uppercase opacity-0 transition-opacity group-hover:opacity-100"
                        >Open →</span
                    >
                </div>
            </div>
        </article>
    </Link>
</template>
