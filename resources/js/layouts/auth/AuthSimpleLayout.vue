<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import BrandMark from '@/components/BrandMark.vue';

defineProps<{
    title?: string;
    description?: string;
}>();
</script>

<template>
    <div class="grid min-h-svh bg-background lg:grid-cols-[1.05fr_0.95fr]">
        <!-- Signal panel: geometry and product position, no decorative wallpaper. -->
        <aside
            class="relative hidden min-h-svh flex-col justify-between gap-12 overflow-hidden border-r border-line bg-paper px-10 py-10 text-signal lg:flex xl:px-16 xl:py-12"
        >
            <div
                class="signal-grid pointer-events-none absolute inset-0 opacity-30"
                aria-hidden="true"
            />
            <div
                class="pointer-events-none absolute inset-y-0 left-1/2 w-px bg-line-soft"
                aria-hidden="true"
            />
            <div
                class="pointer-events-none absolute inset-x-0 top-1/2 h-px bg-line-soft"
                aria-hidden="true"
            />

            <div class="relative z-10 flex items-start justify-between gap-6">
                <Link href="/" class="focus-ring inline-flex w-fit">
                    <BrandMark />
                </Link>
                <span
                    class="font-mono text-[0.6rem] tracking-[0.18em] text-signal-muted uppercase"
                >
                    Access / Verify
                </span>
            </div>

            <div class="relative z-10 max-w-lg space-y-8">
                <p class="eyebrow">Signal studio — Vellum</p>

                <p
                    class="display-title text-[clamp(2.4rem,4vw,4.2rem)] text-signal"
                >
                    Precision first.<br />
                    <span class="text-signal-muted">Then possibility.</span>
                </p>

                <p class="max-w-md text-sm leading-relaxed text-signal-muted">
                    A focused image studio built on Pixelforge. Compose a
                    prompt, choose a proportion, and keep the result. The ledger
                    stays exact, and a failed generation is always free.
                </p>

                <!-- Restrained diagnostic marks: a rule, a square, a crosshair, a trace. -->
                <svg
                    class="h-20 w-full max-w-sm text-signal"
                    viewBox="0 0 320 80"
                    fill="none"
                    aria-hidden="true"
                    focusable="false"
                >
                    <path
                        d="M0 40h320"
                        stroke="currentColor"
                        stroke-opacity="0.2"
                    />
                    <rect
                        x="24"
                        y="16"
                        width="48"
                        height="48"
                        stroke="currentColor"
                        stroke-opacity="0.55"
                    />
                    <path
                        d="M24 64 72 16"
                        stroke="currentColor"
                        stroke-opacity="0.35"
                    />
                    <path
                        d="M132 16v48M108 40h48"
                        stroke="currentColor"
                        stroke-opacity="0.6"
                    />
                    <rect
                        x="216"
                        y="28"
                        width="24"
                        height="24"
                        stroke="currentColor"
                        stroke-opacity="0.4"
                    />
                    <path
                        d="M252 40h44M252 32v16M296 32v16"
                        stroke="currentColor"
                        stroke-opacity="0.3"
                    />
                </svg>
            </div>

            <div
                class="relative z-10 flex items-end justify-between gap-6 border-t border-line pt-5 font-mono text-[0.6rem] tracking-[0.16em] text-signal-muted uppercase"
            >
                <span>Monochrome by design</span>
                <span>Vellum / Signal studio</span>
            </div>
        </aside>

        <main
            class="flex min-h-svh flex-col bg-background px-6 py-7 sm:px-10 lg:px-14 xl:px-20"
            aria-labelledby="auth-title"
        >
            <div class="flex items-center justify-between gap-4">
                <Link href="/" class="focus-ring inline-flex lg:hidden">
                    <BrandMark compact />
                </Link>
                <span
                    class="ml-auto hidden font-mono text-[0.6rem] tracking-[0.16em] text-signal-muted uppercase lg:block"
                >
                    Vellum / Access
                </span>
            </div>

            <div class="flex flex-1 items-center justify-center py-12">
                <div class="animate-rise w-full max-w-[25rem]">
                    <header class="mb-8 space-y-3">
                        <p class="eyebrow">The studio door</p>
                        <h1
                            id="auth-title"
                            class="display-title text-[2.5rem] text-signal sm:text-[2.85rem]"
                        >
                            {{ title }}
                        </h1>
                        <p
                            v-if="description"
                            class="max-w-sm text-sm leading-relaxed text-signal-muted"
                        >
                            {{ description }}
                        </p>
                    </header>

                    <slot />
                </div>
            </div>

            <footer
                class="flex flex-wrap items-center justify-between gap-3 border-t border-line pt-5 font-mono text-[0.58rem] tracking-[0.14em] text-signal-muted uppercase"
            >
                <span>Exact accounting</span>
                <span>Failed generations free</span>
            </footer>
        </main>
    </div>
</template>
