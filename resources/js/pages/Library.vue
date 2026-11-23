<script setup lang="ts">
defineOptions({ inheritAttrs: false });

import { Head, Link, router } from '@inertiajs/vue3';
import {
    Activity,
    AlertTriangle,
    ArrowLeft,
    ArrowRight,
    RefreshCw,
    Search,
    SlidersHorizontal,
    X,
} from '@lucide/vue';
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import GenerationCard from '@/components/GenerationCard.vue';
import { qualityLabel, ratioLabel } from '@/lib/vellum';
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
const refreshing = ref(false);
const loadError = ref<string | null>(null);

/* Browser history and partial reloads can replace filters without remounting
   this component. Keep the local input tethered to the server-provided query. */
watch(
    () => props.filters.q,
    (value) => {
        if (value !== query.value) {
            query.value = value;
        }
    },
);

const activeStatusValues = ['pending', 'queued', 'processing'];

function statusLabel(value: string): string {
    return (
        props.statuses.find((status) => status.value === value)?.label ?? value
    );
}

const activeStatuses = computed(() =>
    activeStatusValues
        .map((value) => ({
            value,
            label: statusLabel(value),
            count: props.generations.data.filter(
                (generation) => generation.status === value,
            ).length,
        }))
        .filter((status) => status.count > 0),
);

const activeCount = computed(() =>
    activeStatuses.value.reduce((sum, status) => sum + status.count, 0),
);

const hasActiveGenerations = computed(() => activeCount.value > 0);

const activeFilters = computed(
    () =>
        [
            props.filters.status,
            props.filters.quality,
            props.filters.size,
        ].filter(Boolean).length + (props.filters.q ? 1 : 0),
);

const activeChips = computed(() => {
    const chips: Array<{ key: string; label: string }> = [];

    if (props.filters.q) {
        chips.push({ key: 'q', label: `“${props.filters.q}”` });
    }
    if (props.filters.status) {
        chips.push({
            key: 'status',
            label: statusLabel(props.filters.status),
        });
    }
    if (props.filters.quality) {
        chips.push({
            key: 'quality',
            label: qualityLabel(props.filters.quality),
        });
    }
    if (props.filters.size) {
        chips.push({
            key: 'size',
            label: `${ratioLabel(props.filters.size)} · ${props.filters.size}`,
        });
    }

    return chips;
});

const pageLinks = computed(() =>
    props.generations.links.filter((link) => /^\d+$/.test(link.label)),
);

let pollTimer: ReturnType<typeof setInterval> | undefined;

function stopPolling(): void {
    if (pollTimer !== undefined) {
        window.clearInterval(pollTimer);
        pollTimer = undefined;
    }
}

function startPolling(): void {
    if (pollTimer !== undefined) {
        return;
    }

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
}

function syncPolling(): void {
    if (hasActiveGenerations.value && document.visibilityState === 'visible') {
        startPolling();
    } else {
        stopPolling();
    }
}

watch(hasActiveGenerations, syncPolling);

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

    loadError.value = null;
    refreshing.value = true;

    router.get('/library', params, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
        onFinish: () => {
            refreshing.value = false;
        },
        onError: (errors) => {
            const first = Object.values(errors)[0];
            const message = Array.isArray(first) ? first[0] : first;

            loadError.value = message || 'The archive could not be updated.';
        },
    });
}

function search(): void {
    apply({ q: query.value.trim() || null });
}

function clearAll(): void {
    query.value = '';
    apply({ q: null, status: null, quality: null, size: null });
}

function clearChip(key: string): void {
    if (key === 'q') {
        query.value = '';
    }

    apply({ [key]: null });
}

onMounted(() => {
    document.addEventListener('visibilitychange', syncPolling);
    syncPolling();
});

onBeforeUnmount(() => {
    document.removeEventListener('visibilitychange', syncPolling);
    stopPolling();
});
</script>

<template>
    <Head title="Library / Archive" />

    <div class="min-w-0 space-y-8 pb-10 text-document lg:space-y-10">
        <header
            class="flex min-w-0 flex-col gap-5 border-b border-line pb-6 lg:flex-row lg:items-end lg:justify-between"
        >
            <div class="min-w-0">
                <p class="vellum-meta flex items-center gap-3">
                    <span class="h-px w-8 bg-document" aria-hidden="true" />
                    Archive / Index
                </p>
                <h1
                    class="vellum-display mt-4 text-[clamp(2rem,4.6vw,3.4rem)] text-document"
                >
                    Every plate,
                    <span class="text-stone">kept.</span>
                </h1>
            </div>

            <div class="max-w-sm min-w-0 space-y-3 lg:text-right">
                <p class="text-sm leading-relaxed text-ash">
                    A private record of every plate made here, indexed exactly
                    as it was submitted.
                </p>
                <Link
                    href="/studio"
                    class="focus-ring link-underline inline-flex items-center gap-2 font-sans text-[0.78rem] text-document"
                >
                    New generation
                    <ArrowRight class="size-3.5" aria-hidden="true" />
                </Link>
            </div>
        </header>

        <section
            class="flex flex-wrap items-baseline gap-x-6 gap-y-2 border-b border-line pb-3"
            aria-label="Archive counts"
        >
            <div class="flex items-baseline gap-2">
                <span class="vellum-meta">Total</span>
                <span class="ledger text-base text-document">
                    {{ generations.total.toLocaleString() }}
                </span>
            </div>
            <div class="flex items-baseline gap-2">
                <span class="vellum-meta">On page</span>
                <span class="ledger text-base text-document">
                    {{ String(generations.data.length).padStart(2, '0') }}
                </span>
            </div>
            <div class="flex items-baseline gap-2">
                <span class="vellum-meta">Page</span>
                <span class="ledger text-base text-document">
                    {{ generations.current_page
                    }}<span class="text-stone"
                        >/{{ generations.last_page }}</span
                    >
                </span>
            </div>
        </section>

        <section
            v-if="hasActiveGenerations"
            class="flex flex-col gap-2 border-b border-line py-3 sm:flex-row sm:items-center sm:justify-between"
            aria-label="Generations currently in progress on this page"
            aria-live="polite"
        >
            <div class="flex items-center gap-2">
                <Activity
                    class="size-3.5 text-document motion-reduce:animate-none"
                    aria-hidden="true"
                />
                <span class="vellum-meta text-document">
                    In progress / this page
                </span>
            </div>
            <ul class="flex flex-wrap items-center gap-x-5 gap-y-1">
                <li
                    v-for="status in activeStatuses"
                    :key="status.value"
                    class="flex items-baseline gap-2"
                >
                    <span class="ledger text-sm text-document">
                        {{ String(status.count).padStart(2, '0') }}
                    </span>
                    <span class="vellum-meta">{{ status.label }}</span>
                </li>
            </ul>
            <span class="vellum-meta text-stone"
                >Checks pause in hidden tabs</span
            >
        </section>

        <section class="min-w-0 space-y-4" aria-label="Archive controls">
            <form
                class="flex min-w-0 flex-col gap-2 sm:flex-row"
                @submit.prevent="search"
            >
                <div class="relative min-w-0 flex-1">
                    <label for="library-search" class="sr-only"
                        >Search prompts</label
                    >
                    <Search
                        class="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-stone"
                        aria-hidden="true"
                    />
                    <input
                        id="library-search"
                        v-model="query"
                        type="search"
                        placeholder="Search the prompt archive…"
                        class="vellum-input h-10 w-full pr-20 pl-9 font-sans text-sm text-document"
                    />
                    <button
                        type="submit"
                        class="focus-ring absolute top-1 right-1 bottom-1 border border-document bg-document px-3 font-sans text-xs text-void transition-colors hover:bg-transparent hover:text-document"
                    >
                        Search
                    </button>
                </div>

                <button
                    type="button"
                    class="focus-ring vellum-button-quiet inline-flex h-10 shrink-0 items-center justify-center gap-2 px-3 font-sans text-xs text-document"
                    :aria-expanded="showFilters"
                    aria-controls="library-filters"
                    @click="showFilters = !showFilters"
                >
                    <SlidersHorizontal class="size-3.5" aria-hidden="true" />
                    Filters
                    <span
                        v-if="activeFilters > 0"
                        class="ledger border-l border-line pl-2 text-document"
                    >
                        {{ activeFilters }}
                    </span>
                </button>
            </form>

            <div
                v-if="showFilters"
                id="library-filters"
                class="grid min-w-0 grid-cols-1 gap-3 border border-line bg-void p-3 sm:grid-cols-3"
            >
                <div class="min-w-0">
                    <label for="filter-status" class="vellum-meta"
                        >Status</label
                    >
                    <select
                        id="filter-status"
                        :value="filters.status"
                        class="vellum-input mt-1.5 h-9 w-full px-2 font-sans text-sm text-document"
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

                <div class="min-w-0">
                    <label for="filter-quality" class="vellum-meta"
                        >Quality</label
                    >
                    <select
                        id="filter-quality"
                        :value="filters.quality"
                        class="vellum-input mt-1.5 h-9 w-full px-2 font-sans text-sm text-document"
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
                            {{ qualityLabel(quality) }} · {{ quality }}
                        </option>
                    </select>
                </div>

                <div class="min-w-0">
                    <label for="filter-size" class="vellum-meta"
                        >Proportion</label
                    >
                    <select
                        id="filter-size"
                        :value="filters.size"
                        class="vellum-input mt-1.5 h-9 w-full px-2 font-sans text-sm text-document"
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
                            {{ ratioLabel(size) }} · {{ size }}
                        </option>
                    </select>
                </div>

                <div
                    class="flex min-w-0 flex-wrap items-center justify-between gap-3 sm:col-span-3 sm:border-t sm:border-line sm:pt-3"
                >
                    <p class="vellum-meta text-stone">
                        Changes apply to the index immediately
                    </p>
                    <button
                        v-if="activeFilters > 0"
                        type="button"
                        class="focus-ring inline-flex items-center gap-1.5 font-sans text-xs text-ash transition-colors hover:text-document"
                        @click="clearAll"
                    >
                        <X class="size-3" aria-hidden="true" />
                        Clear all
                    </button>
                </div>
            </div>

            <div
                v-if="activeChips.length > 0"
                class="flex min-w-0 flex-wrap items-center gap-2"
                aria-label="Applied filters"
            >
                <span class="vellum-meta mr-1">Applied</span>
                <span
                    v-for="chip in activeChips"
                    :key="chip.key"
                    class="inline-flex min-w-0 items-center gap-2 border border-line px-2 py-1 font-mono text-[0.65rem] text-ash"
                >
                    <span class="min-w-0 truncate">{{ chip.label }}</span>
                    <button
                        type="button"
                        class="focus-ring inline-flex size-4 shrink-0 items-center justify-center text-stone transition-colors hover:text-document"
                        :aria-label="`Clear ${chip.key} filter`"
                        @click="clearChip(chip.key)"
                    >
                        <X class="size-3" aria-hidden="true" />
                    </button>
                </span>
                <button
                    v-if="activeFilters > 1"
                    type="button"
                    class="focus-ring font-sans text-xs text-stone transition-colors hover:text-document"
                    @click="clearAll"
                >
                    Reset all
                </button>
            </div>
        </section>

        <div
            v-if="loadError"
            class="flex min-w-0 flex-col gap-3 border-l-2 border-document py-2 pl-4 sm:flex-row sm:items-center sm:justify-between"
            role="alert"
        >
            <div class="flex min-w-0 items-start gap-3">
                <AlertTriangle
                    class="mt-0.5 size-4 shrink-0 text-document"
                    aria-hidden="true"
                />
                <div class="min-w-0">
                    <p class="text-xs font-medium text-document">
                        The index could not be updated.
                    </p>
                    <p class="mt-0.5 text-xs leading-relaxed text-ash">
                        {{ loadError }}
                    </p>
                </div>
            </div>
            <button
                type="button"
                class="focus-ring inline-flex shrink-0 items-center gap-2 border border-line px-3 py-1.5 font-sans text-xs text-document transition-colors hover:border-document"
                @click="apply()"
            >
                <RefreshCw class="size-3" aria-hidden="true" />
                Retry
            </button>
        </div>

        <p
            v-if="refreshing"
            class="flex items-center gap-2 font-mono text-[0.6rem] tracking-[0.08em] text-ash"
            role="status"
            aria-live="polite"
        >
            <span
                class="status-dot animate-breathe bg-document"
                aria-hidden="true"
            />
            Updating index
        </p>

        <div
            v-if="generations.data.length > 0"
            class="grid min-w-0 grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5"
            :aria-busy="refreshing"
        >
            <GenerationCard
                v-for="generation in generations.data"
                :key="generation.id"
                :generation="generation"
            />
        </div>

        <div
            v-else-if="refreshing"
            class="grid min-w-0 grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5"
            aria-busy="true"
            aria-label="Loading archive"
        >
            <div
                v-for="index in 8"
                :key="index"
                class="border border-line bg-void"
            >
                <div class="aspect-square border-b border-line bg-elevated" />
                <div class="space-y-2 p-3">
                    <div class="h-3 w-3/4 bg-elevated" />
                    <div class="h-3 w-1/2 bg-elevated" />
                </div>
            </div>
        </div>

        <div
            v-else
            class="flex min-w-0 flex-col items-center border-y border-line px-4 py-16 text-center"
        >
            <span
                class="font-display text-4xl leading-none text-stone"
                aria-hidden="true"
                >×</span
            >
            <h2 class="vellum-title mt-4 text-2xl text-document">
                {{
                    activeFilters > 0
                        ? 'No plates match those filters.'
                        : 'The archive is empty.'
                }}
            </h2>
            <p class="mt-3 max-w-sm text-sm leading-relaxed text-ash">
                {{
                    activeFilters > 0
                        ? 'Broaden the search, or reset the index to see every plate.'
                        : 'Finished plates are archived here privately, exactly as the provider returned them.'
                }}
            </p>
            <button
                v-if="activeFilters > 0"
                type="button"
                class="focus-ring vellum-button-quiet mt-6 px-4 py-2 font-sans text-xs text-document"
                @click="clearAll"
            >
                <X class="size-3.5" aria-hidden="true" />
                Reset filters
            </button>
            <Link
                v-else
                href="/studio"
                class="focus-ring vellum-button mt-6 px-4 py-2 font-sans text-xs"
            >
                Make the first plate
                <ArrowRight class="size-3.5" aria-hidden="true" />
            </Link>
        </div>

        <nav
            v-if="generations.last_page > 1"
            class="flex min-w-0 flex-col gap-4 border-t border-line pt-5"
            aria-label="Library pages"
        >
            <p class="vellum-meta text-stone">
                Showing {{ generations.from ?? 0 }}–{{ generations.to ?? 0 }}
                of
                {{ generations.total.toLocaleString() }}
            </p>

            <div class="flex max-w-full min-w-0 flex-wrap items-center gap-2">
                <Link
                    v-if="generations.prev_page_url"
                    :href="generations.prev_page_url"
                    preserve-scroll
                    class="focus-ring vellum-button-quiet inline-flex items-center gap-1.5 px-3 py-1.5 font-sans text-xs text-document"
                    aria-label="Go to previous page"
                >
                    <ArrowLeft class="size-3" aria-hidden="true" />
                    Previous
                </Link>
                <span
                    v-else
                    class="inline-flex items-center gap-1.5 border border-line px-3 py-1.5 font-sans text-xs text-stone"
                    aria-disabled="true"
                >
                    <ArrowLeft class="size-3" aria-hidden="true" />
                    Previous
                </span>

                <span
                    v-for="link in pageLinks"
                    :key="link.label"
                    :aria-current="link.active ? 'page' : undefined"
                >
                    <Link
                        v-if="link.url"
                        :href="link.url"
                        preserve-scroll
                        class="focus-ring inline-flex size-8 items-center justify-center border font-mono text-[0.62rem] transition-colors"
                        :class="
                            link.active
                                ? 'border-document bg-document text-void'
                                : 'border-line text-ash transition-colors hover:border-document hover:text-document'
                        "
                        :aria-label="`Go to page ${link.label}`"
                    >
                        {{ link.label }}
                    </Link>
                    <span
                        v-else
                        class="inline-flex size-8 items-center justify-center border border-line font-mono text-[0.62rem] text-stone"
                        aria-hidden="true"
                        >{{ link.label }}</span
                    >
                </span>

                <Link
                    v-if="generations.next_page_url"
                    :href="generations.next_page_url"
                    preserve-scroll
                    class="focus-ring vellum-button-quiet inline-flex items-center gap-1.5 px-3 py-1.5 font-sans text-xs text-document"
                    aria-label="Go to next page"
                >
                    Next
                    <ArrowRight class="size-3" aria-hidden="true" />
                </Link>
                <span
                    v-else
                    class="inline-flex items-center gap-1.5 border border-line px-3 py-1.5 font-sans text-xs text-stone"
                    aria-disabled="true"
                >
                    Next
                    <ArrowRight class="size-3" aria-hidden="true" />
                </span>
            </div>
        </nav>
    </div>
</template>
