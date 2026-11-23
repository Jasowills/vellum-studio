<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { Check } from '@lucide/vue';
import AppLayout from '@/layouts/AppLayout.vue';
import SettingsLayout from '@/layouts/settings/Layout.vue';
import HeadingSmall from '@/components/HeadingSmall.vue';

const appearance = {
    mode: 'Signal',
    description:
        'A fixed monochrome system. Black ground, white signal, no chromatic accent.',
    swatches: ['#050505', '#0d0d0d', '#2b2b2b', '#9a9a9a', '#f4f4f4'],
};
</script>

<template>
    <AppLayout
        :breadcrumbs="[
            { title: 'Settings', href: '/settings/profile' },
            { title: 'Appearance', href: '/settings/appearance' },
        ]"
    >
        <Head title="Appearance settings" />

        <SettingsLayout>
            <div class="space-y-8">
                <HeadingSmall
                    title="Appearance"
                    description="Vellum uses one deliberate visual system across every surface."
                />

                <section
                    class="border border-line bg-paper"
                    aria-labelledby="appearance-current"
                >
                    <div
                        class="flex items-center justify-between border-b border-line px-5 py-4"
                    >
                        <div>
                            <h3
                                id="appearance-current"
                                class="font-mono text-[0.65rem] tracking-[0.16em] text-signal uppercase"
                            >
                                Active system
                            </h3>
                            <p class="mt-1.5 text-xs text-signal-muted">
                                {{ appearance.description }}
                            </p>
                        </div>
                        <Check class="size-4 text-signal" aria-hidden="true" />
                    </div>
                    <div class="grid grid-cols-5 divide-x divide-line">
                        <div
                            v-for="swatch in appearance.swatches"
                            :key="swatch"
                            class="aspect-square"
                            :style="{ backgroundColor: swatch }"
                            :title="swatch"
                        />
                    </div>
                    <div
                        class="flex flex-wrap gap-x-6 gap-y-2 px-5 py-4 font-mono text-[0.6rem] tracking-[0.12em] text-signal-faint uppercase"
                    >
                        <span>Geist Sans</span>
                        <span>Instrument Serif</span>
                        <span>Geist Mono</span>
                    </div>
                </section>
            </div>
        </SettingsLayout>
    </AppLayout>
</template>
