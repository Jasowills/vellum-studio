<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import {
    ArrowLeft,
    ArrowRight,
    Search,
    SlidersHorizontal,
    X,
} from '@lucide/vue';
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import GenerationCard from '@/components/GenerationCard.vue';
import type { Generation, Paginated } from '@/types';

const props = defineProps<{
    generations: Paginated<Generation>;
    filters: {
        q: string;
        status: string;
        quality: string;
        size: string;
    };
    statuses: Array<{ value: string; label: string }>;
    qualities: string[];
    sizes: string[];
}>();

const query = ref(props.filters.q);
const showFilters = ref(false);

const activeFilters = computed(
    () =>
        [
            props.filters.status,
            props.filters.quality,
            props.filters.size,
        ].filter(Boolean).length + (props.filters.q ? 1 : 0),
);

const hasActiveGenerations = computed(() =>
    props.generations.data.some((generation) =>
        ['pending', 'queued', 'processing'].includes(generation.status),
    ),
);

let pollTimer: ReturnType<typeof setInterval> | undefined;

function apply(overrides: Record<string, string | null> = {}): void {
    const params: Record<string, string> = {};

    const next = {
        q: props.filters.q,
        status: props.filters.status,
        quality: props.filters.quality,
        size: props.filters.size,
        ...overrides,
    };

    Object.entries(next).forEach(([key, value]) => {
        if (value) {
            params[key] = value;
        }
    });

    router.get('/library', params, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    });
}

function search(): void {
    apply({ q: query.value.trim() || null });
}

function clearAll(): void {
    query.value = '';
    apply({ q: null, status: null, quality: null, size: null });
}

function sizeLabel(size: string): string {
    return (
        {
            '1024x1024': 'Square',
            '1536x1024': 'Landscape',
            '1024x1536': 'Portrait',
        }[size] ?? size
    );
}

onMounted(() => {
    pollTimer = window.setInterval(() => {
        if (
            hasActiveGenerations.value &&
            document.visibilityState === 'visible'
        ) {
            router.reload({
                only: ['generations'],
            });
        }
    }, 10000);
});

onBeforeUnmount(() => {
    window.clearInterval(pollTimer);
});
</script>

<template>
    <Head title="The library" />

    <div class="space-y-10">
        <header
            class="flex flex-col justify-between gap-6 border-b border-line pb-8 lg:flex-row lg:items-end"
        >
            <div>
                <p class="eyebrow flex items-center gap-3">
                    <span class="h-px w-8 bg-vermilion" aria-hidden="true" />
                    Library / all plates
                </p>
                <h1
                    class="display-title mt-5 max-w-[14ch] text-[clamp(2.8rem,5.2vw,4.4rem)] text-ink"
                >
                    Everything
                    <em class="text-vermilion">kept.</em>
                </h1>
            </div>
            <div class="max-w-sm space-y-2 lg:text-right">
                <p class="text-sm leading-relaxed text-ink-soft">
                    {{ generations.total.toLocaleString() }}
                    {{ generations.total === 1 ? 'plate' : 'plates' }} in your
                    collection. Archived privately, served through Vellum.
                </p>
                <Link
                    href="/studio"
                    class="link-underline inline-block font-mono text-[0.6rem] tracking-[0.12em] text-ink uppercase"
                    >Make a new image →</Link
                >
            </div>
        </header>

        <div class="space-y-4">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center">
                <form class="relative flex-1" @submit.prevent="search">
                    <label for="library-search" class="sr-only"
                        >Search prompts</label
                    >
                    <Search
                        class="pointer-events-none absolute top-1/2 left-3.5 size-4 -translate-y-1/2 text-ink-soft"
                        aria-hidden="true"
                    />
                    <input
                        id="library-search"
                        v-model="query"
                        type="search"
                        placeholder="Search the prompt archive…"
                        class="h-11 w-full border border-line bg-paper pr-24 pl-10 text-sm text-ink placeholder:text-ink-soft/65 focus:border-ink focus:outline-none"
                    />
                    <button
                        type="submit"
                        class="focus-ring absolute top-1/2 right-1.5 -translate-y-1/2 border border-ink bg-ink px-3 py-1.5 font-mono text-[0.58rem] tracking-[0.1em] text-paper uppercase transition-colors hover:bg-vermilion"
                    >
                        Search
                    </button>
                </form>

                <button
                    type="button"
                    class="focus-ring inline-flex h-11 items-center justify-center gap-2 border border-line bg-paper px-4 text-xs font-medium text-ink-soft transition-colors hover:border-ink hover:text-ink"
                    :aria-expanded="showFilters"
                    aria-controls="library-filters"
                    @click="showFilters = !showFilters"
                >
                    <SlidersHorizontal class="size-3.5" aria-hidden="true" />
                    Filters
                    <span
                        v-if="activeFilters > 0"
                        class="inline-flex size-4.5 items-center justify-center rounded-full bg-vermilion font-mono text-[0.55rem] text-paper"
                        >{{ activeFilters }}</span
                    >
                </button>
            </div>

            <div
                v-if="showFilters"
                id="library-filters"
                class="grid gap-3 border border-line bg-paper p-4 sm:grid-cols-3"
            >
                <div>
                    <label
                        for="filter-status"
                        class="font-mono text-[0.55rem] tracking-[0.14em] text-ink-soft uppercase"
                        >Status</label
                    >
                    <select
                        id="filter-status"
                        :value="filters.status"
                        class="mt-2 h-10 w-full border border-line bg-background/40 px-2.5 text-sm text-ink focus:border-ink focus:outline-none"
                        @change="
                            apply({
                                status:
                                    ($event.target as HTMLSelectElement)
                                        .value || null,
                            })
                        "
                    >
                        <option value="">All statuses</option>
                        <option
                            v-for="status in statuses"
                            :key="status.value"
                            :value="status.value"
                        >
                            {{ status.label }}
                        </option>
                    </select>
                </div>
                <div>
                    <label
                        for="filter-quality"
                        class="font-mono text-[0.55rem] tracking-[0.14em] text-ink-soft uppercase"
                        >Quality</label
                    >
                    <select
                        id="filter-quality"
                        :value="filters.quality"
                        class="mt-2 h-10 w-full border border-line bg-background/40 px-2.5 text-sm text-ink focus:border-ink focus:outline-none"
                        @change="
                            apply({
                                quality:
                                    ($event.target as HTMLSelectElement)
                                        .value || null,
                            })
                        "
                    >
                        <option value="">All qualities</option>
                        <option
                            v-for="quality in qualities"
                            :key="quality"
                            :value="quality"
                        >
                            {{ quality }}
                        </option>
                    </select>
                </div>
                <div>
                    <label
                        for="filter-size"
                        class="font-mono text-[0.55rem] tracking-[0.14em] text-ink-soft uppercase"
                        >Proportion</label
                    >
                    <select
                        id="filter-size"
                        :value="filters.size"
                        class="mt-2 h-10 w-full border border-line bg-background/40 px-2.5 text-sm text-ink focus:border-ink focus:outline-none"
                        @change="
                            apply({
                                size:
                                    ($event.target as HTMLSelectElement)
                                        .value || null,
                            })
                        "
                    >
                        <option value="">All proportions</option>
                        <option v-for="size in sizes" :key="size" :value="size">
                            {{ sizeLabel(size) }} · {{ size }}
                        </option>
                    </select>
                </div>
                <div class="sm:col-span-3">
                    <button
                        v-if="activeFilters > 0"
                        type="button"
                        class="inline-flex items-center gap-1.5 font-mono text-[0.58rem] tracking-[0.1em] text-ink-soft uppercase hover:text-vermilion"
                        @click="clearAll"
                    >
                        <X class="size-3" aria-hidden="true" />
                        Clear all filters
                    </button>
                </div>
            </div>

            <div
                v-if="activeFilters > 0"
                class="flex flex-wrap items-center gap-2 text-xs text-ink-soft"
            >
                <span
                    class="font-mono text-[0.55rem] tracking-[0.12em] uppercase"
                    >Showing</span
                >
                <span
                    v-if="filters.q"
                    class="inline-flex items-center gap-1.5 border border-line bg-paper px-2 py-1"
                >
                    “{{ filters.q }}”
                    <button
                        type="button"
                        class="focus-ring text-ink-soft hover:text-vermilion"
                        aria-label="Clear search"
                        @click="
                            query = '';
                            apply({ q: null });
                        "
                    >
                        <X class="size-3" aria-hidden="true" />
                    </button>
                </span>
                <span
                    v-for="filter in [
                        {
                            key: 'status',
                            value: filters.status,
                            label: filters.status,
                        },
                        {
                            key: 'quality',
                            value: filters.quality,
                            label: filters.quality,
                        },
                        {
                            key: 'size',
                            value: filters.size,
                            label: sizeLabel(filters.size),
                        },
                    ]"
                    v-show="filter.value"
                    :key="filter.key"
                    class="inline-flex items-center gap-1.5 border border-line bg-paper px-2 py-1 capitalize"
                >
                    {{ filter.label }}
                    <button
                        type="button"
                        class="focus-ring text-ink-soft hover:text-vermilion"
                        :aria-label="`Clear ${filter.key} filter`"
                        @click="apply({ [filter.key]: null })"
                    >
                        <X class="size-3" aria-hidden="true" />
                    </button>
                </span>
            </div>
        </div>

        <div
            v-if="generations.data.length > 0"
            class="grid gap-5 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4"
        >
            <GenerationCard
                v-for="generation in generations.data"
                :key="generation.id"
                :generation="generation"
            />
        </div>

        <div
            v-else
            class="flex flex-col items-center justify-center border border-dashed border-line bg-paper/60 px-6 py-20 text-center"
        >
            <span class="font-display text-5xl text-ink/20" aria-hidden="true"
                >×</span
            >
            <h2 class="mt-4 font-display text-3xl tracking-[-0.03em] text-ink">
                {{
                    activeFilters > 0
                        ? 'Nothing matches that search.'
                        : 'The library is empty.'
                }}
            </h2>
            <p class="mt-2 max-w-sm text-sm leading-relaxed text-ink-soft">
                {{
                    activeFilters > 0
                        ? 'Try a broader phrase, or clear the filters to see the whole collection.'
                        : 'Your finished plates will live here, archived privately and kept exactly as made.'
                }}
            </p>
            <button
                v-if="activeFilters > 0"
                type="button"
                class="focus-ring mt-5 inline-flex items-center gap-2 border border-ink px-4 py-2 text-xs font-medium text-ink hover:bg-ink hover:text-paper"
                @click="clearAll"
            >
                <X class="size-3.5" aria-hidden="true" />
                Clear filters
            </button>
            <Link
                v-else
                href="/studio"
                class="focus-ring mt-5 inline-flex items-center gap-2 bg-ink px-4 py-2 text-xs font-medium text-paper hover:bg-vermilion"
            >
                Make the first plate
                <ArrowRight class="size-3.5" aria-hidden="true" />
            </Link>
        </div>

        <nav
            v-if="generations.last_page > 1"
            class="flex flex-wrap items-center justify-between gap-4 border-t border-line pt-5"
            aria-label="Library pages"
        >
            <p
                class="font-mono text-[0.58rem] tracking-[0.1em] text-ink-soft uppercase"
            >
                {{ generations.from }}–{{ generations.to }} of
                {{ generations.total }}
            </p>
            <div class="flex items-center gap-2">
                <Link
                    v-if="generations.prev_page_url"
                    :href="generations.prev_page_url"
                    preserve-scroll
                    class="focus-ring inline-flex items-center gap-1.5 border border-line px-3 py-1.5 font-mono text-[0.58rem] tracking-[0.1em] text-ink uppercase hover:border-ink"
                >
                    <ArrowLeft class="size-3" aria-hidden="true" />
                    Previous
                </Link>
                <span
                    class="px-2 font-mono text-[0.58rem] tracking-[0.1em] text-ink-soft"
                    >{{ generations.current_page }} /
                    {{ generations.last_page }}</span
                >
                <Link
                    v-if="generations.next_page_url"
                    :href="generations.next_page_url"
                    preserve-scroll
                    class="focus-ring inline-flex items-center gap-1.5 border border-line px-3 py-1.5 font-mono text-[0.58rem] tracking-[0.1em] text-ink uppercase hover:border-ink"
                >
                    Next
                    <ArrowRight class="size-3" aria-hidden="true" />
                </Link>
            </div>
        </nav>
    </div>
</template>
