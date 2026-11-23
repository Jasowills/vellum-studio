<script setup lang="ts">
defineOptions({ inheritAttrs: false });

import { Head } from '@inertiajs/vue3';
import { Check } from '@lucide/vue';
import AppLayout from '@/layouts/AppLayout.vue';
import SettingsLayout from '@/layouts/settings/Layout.vue';

const appearance = {
    mode: 'Midnight',
    description:
        'A fixed monochrome system. Black ground, white signal, no chromatic accent.',
    swatches: ['#000000', '#070707', '#232323', '#737373', '#F5F5F5'],
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
            <div class="space-y-10">
                <section aria-labelledby="appearance-heading">
                    <h2
                        id="appearance-heading"
                        class="vellum-title text-xl text-document"
                    >
                        Appearance
                    </h2>
                    <p class="mt-2 text-sm leading-relaxed text-ash">
                        Vellum uses one deliberate visual system across every
                        surface.
                    </p>

                    <div
                        class="vellum-panel mt-6"
                        aria-labelledby="appearance-current"
                    >
                        <div
                            class="flex items-start justify-between gap-4 border-b border-line px-5 py-4"
                        >
                            <div>
                                <p id="appearance-current" class="vellum-meta">
                                    Active system
                                </p>
                                <p class="mt-2 text-sm text-document">
                                    {{ appearance.mode }}
                                </p>
                                <p
                                    class="mt-1 text-xs leading-relaxed text-ash"
                                >
                                    {{ appearance.description }}
                                </p>
                            </div>
                            <Check
                                class="mt-0.5 size-4 shrink-0 text-document"
                                aria-hidden="true"
                            />
                        </div>

                        <div class="grid grid-cols-5 divide-x divide-line">
                            <div
                                v-for="swatch in appearance.swatches"
                                :key="swatch"
                                class="aspect-square"
                                :style="{ backgroundColor: swatch }"
                                role="img"
                                :aria-label="swatch"
                            />
                        </div>

                        <dl
                            class="grid gap-4 border-t border-line px-5 py-4 sm:grid-cols-3"
                        >
                            <div>
                                <dt class="vellum-meta">Display</dt>
                                <dd class="mt-1 text-sm text-document">Sora</dd>
                            </div>
                            <div>
                                <dt class="vellum-meta">Interface</dt>
                                <dd class="mt-1 text-sm text-document">
                                    Geist Sans
                                </dd>
                            </div>
                            <div>
                                <dt class="vellum-meta">Metadata</dt>
                                <dd class="mt-1 text-sm text-document">
                                    Geist Mono
                                </dd>
                            </div>
                        </dl>
                    </div>
                </section>
            </div>
        </SettingsLayout>
    </AppLayout>
</template>
