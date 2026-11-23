<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import BrandMark from '@/components/BrandMark.vue';

defineProps<{
    title?: string;
    description?: string;
}>();
</script>

<template>
    <div class="grid min-h-svh lg:grid-cols-[1.04fr_0.96fr]">
        <aside
            class="relative hidden flex-col justify-between overflow-hidden bg-ink p-12 text-paper lg:flex xl:p-16"
        >
            <div
                class="pointer-events-none absolute inset-0 opacity-[0.14]"
                aria-hidden="true"
                style="
                    background-image:
                        linear-gradient(
                            to right,
                            rgba(251, 247, 238, 0.35) 1px,
                            transparent 1px
                        ),
                        linear-gradient(
                            to bottom,
                            rgba(251, 247, 238, 0.35) 1px,
                            transparent 1px
                        );
                    background-size: 72px 72px;
                "
            />
            <div
                class="pointer-events-none absolute -top-20 -right-24 size-[26rem] rounded-full border border-paper/15"
                aria-hidden="true"
            />
            <div
                class="pointer-events-none absolute -right-6 bottom-24 size-[14rem] rounded-full border border-paper/10"
                aria-hidden="true"
            />

            <Link href="/" class="relative z-10 w-fit">
                <BrandMark inverted />
            </Link>

            <div class="relative z-10 max-w-lg space-y-8">
                <p
                    class="font-mono text-[0.65rem] tracking-[0.2em] text-paper/50 uppercase"
                >
                    Plate No. 01 — The quiet room
                </p>
                <blockquote
                    class="font-display text-[clamp(2.6rem,4.2vw,4.6rem)] leading-[0.98] tracking-[-0.04em] text-paper"
                >
                    A slower room
                    <span class="text-paper/70 italic">for faster</span>
                    ideas.
                </blockquote>
                <p class="max-w-md text-sm leading-relaxed text-paper/60">
                    Vellum is a small image studio built on Pixelforge. Compose
                    a prompt, choose a proportion, and keep the result. The
                    ledger stays exact, and a failed generation is always free.
                </p>
            </div>

            <div
                class="relative z-10 flex items-end justify-between border-t border-paper/15 pt-5 font-mono text-[0.6rem] tracking-[0.14em] text-paper/45 uppercase"
            >
                <span>Est. 2026</span>
                <span>Images, kept on paper</span>
            </div>
        </aside>

        <main
            class="flex min-h-svh flex-col bg-background px-6 py-7 sm:px-10 lg:px-14"
        >
            <div class="flex items-center justify-between">
                <Link href="/" class="lg:hidden">
                    <BrandMark />
                </Link>
                <span
                    class="ml-auto hidden font-mono text-[0.6rem] tracking-[0.16em] text-ink-soft uppercase lg:block"
                    >Vellum / Access</span
                >
            </div>

            <div class="flex flex-1 items-center justify-center py-12">
                <div class="animate-rise w-full max-w-[24rem]">
                    <div class="mb-8 space-y-3">
                        <p class="eyebrow">The studio door</p>
                        <h1
                            class="display-title text-[2.55rem] text-ink sm:text-[2.9rem]"
                        >
                            {{ title }}
                        </h1>
                        <p
                            v-if="description"
                            class="max-w-sm text-sm leading-relaxed text-ink-soft"
                        >
                            {{ description }}
                        </p>
                    </div>
                    <slot />
                </div>
            </div>

            <div
                class="flex flex-wrap items-center justify-between gap-3 border-t border-line pt-5 font-mono text-[0.58rem] tracking-[0.14em] text-ink-soft uppercase"
            >
                <span>Exact accounting</span>
                <span>Failed generations free</span>
            </div>
        </main>
    </div>
</template>
