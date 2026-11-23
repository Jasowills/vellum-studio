<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import VellumGlyph from '@/components/VellumGlyph.vue';

defineProps<{
    title?: string;
    description?: string;
}>();
</script>

<template>
    <div class="flex min-h-svh flex-col bg-void text-document">
        <main
            class="mx-auto flex w-full max-w-[28rem] flex-1 flex-col justify-center px-5 py-10 sm:px-8 sm:py-14"
            aria-labelledby="auth-title"
        >
            <div class="animate-rise w-full">
                <Link
                    href="/"
                    class="focus-ring group inline-flex items-center gap-3.5"
                    aria-label="Vellum home"
                >
                    <span
                        class="flex size-11 items-center justify-center border border-line bg-surface transition-colors group-hover:border-edge-strong"
                        aria-hidden="true"
                    >
                        <VellumGlyph :size="24" />
                    </span>
                    <span class="flex flex-col gap-1">
                        <span
                            class="vellum-title text-[1.05rem] leading-none text-document"
                        >
                            Vellum
                        </span>
                        <span class="vellum-meta">Creative instrument</span>
                    </span>
                </Link>

                <p class="vellum-title mt-7 text-[1.35rem] text-document">
                    Turn words into worlds.
                </p>

                <header class="mt-9 border-t border-line pt-6">
                    <p class="vellum-meta">Account access</p>
                    <h1
                        id="auth-title"
                        class="vellum-title mt-2 text-[2rem] text-document sm:text-[2.25rem]"
                    >
                        {{ title }}
                    </h1>
                    <p
                        v-if="description"
                        class="mt-3 max-w-sm text-sm leading-relaxed text-ash"
                    >
                        {{ description }}
                    </p>
                </header>

                <div class="vellum-panel mt-7 px-5 py-6 sm:px-7 sm:py-7">
                    <slot />
                </div>
            </div>
        </main>

        <footer class="border-t border-line">
            <div
                class="mx-auto flex w-full max-w-[88rem] flex-wrap items-center justify-between gap-x-6 gap-y-1 px-5 py-4 sm:px-8"
            >
                <span class="vellum-meta">Vellum / Creative instrument</span>
                <span class="vellum-meta">
                    Exact accounting · Failed generations free
                </span>
            </div>
        </footer>
    </div>
</template>
