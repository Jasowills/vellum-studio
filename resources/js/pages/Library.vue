<script setup lang="ts">
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

    <div class="space-y-9 lg:space-y-11">
        <header
            class="flex flex-col gap-7 border-b border-line pb-8 lg:flex-row lg:items-end lg:justify-between"
        >
            <div class="min-w-0">
                <p class="eyebrow flex items-center gap-3">
                    <span class="h-px w-8 bg-signal" aria-hidden="true" />
                    Archive / Index
                </p>
                <h1
                    class="display-title mt-5 text-[clamp(2.6rem,5.4vw,4.5rem)] text-signal"
                >
                    Every plate,
                    <span class="text-signal-faint">kept.</span>
                </h1>
            </div>

            <div class="max-w-sm space-y-4 lg:text-right">
                <p class="text-sm leading-relaxed text-signal-muted">
                    A private record of every plate made here, indexed exactly
                    as it was submitted.
                </p>
                <Link
                    href="/studio"
                    class="focus-ring link-underline inline-flex items-center gap-2 font-mono text-[0.62rem] tracking-[0.14em] text-signal uppercase"
                >
                    New generation
                    <ArrowRight class="size-3.5" aria-hidden="true" />
                </Link>
            </div>
        </header>

        <section
            class="grid grid-cols-3 gap-px border border-line bg-line"
            aria-label="Archive counts"
        >
            <div class="bg-paper px-3 py-3 sm:px-5 sm:py-4">
                <p class="eyebrow text-[0.55rem]">Total</p>
                <p class="ledger mt-2 text-xl text-signal sm:text-2xl">
                    {{ generations.total.toLocaleString() }}
                </p>
            </div>
            <div class="bg-paper px-3 py-3 sm:px-5 sm:py-4">
                <p class="eyebrow text-[0.55rem]">On page</p>
                <p class="ledger mt-2 text-xl text-signal sm:text-2xl">
                    {{ String(generations.data.length).padStart(2, '0') }}
                </p>
            </div>
            <div class="bg-paper px-3 py-3 sm:px-5 sm:py-4">
                <p class="eyebrow text-[0.55rem]">Page</p>
                <p class="ledger mt-2 text-xl text-signal sm:text-2xl">
                    {{ generations.current_page
                    }}<span class="text-signal-faint"
                        >/{{ generations.last_page }}</span
                    >
                </p>
            </div>
        </section>

        <section
            v-if="hasActiveGenerations"
            class="border border-line bg-paper-deep px-4 py-3 sm:px-5"
            aria-label="Generations currently in progress on this page"
            aria-live="polite"
        >
            <div
                class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between"
            >
                <div class="flex items-center gap-2.5">
                    <Activity
                        class="size-3.5 text-signal-muted motion-reduce:animate-none"
                        aria-hidden="true"
                    />
                    <span
                        class="font-mono text-[0.6rem] tracking-[0.16em] text-signal uppercase"
                        >In progress / this page</span
                    >
                </div>
                <ul class="flex flex-wrap items-center gap-x-5 gap-y-2">
                    <li
                        v-for="status in activeStatuses"
                        :key="status.value"
                        class="flex items-baseline gap-2"
                    >
                        <span class="ledger text-sm text-signal">{{
                            String(status.count).padStart(2, '0')
                        }}</span>
                        <span
                            class="font-mono text-[0.6rem] tracking-[0.12em] text-signal-muted uppercase"
                            >{{ status.label }}</span
                        >
                    </li>
                </ul>
                <span
                    class="font-mono text-[0.58rem] tracking-[0.08em] text-signal-faint uppercase"
                    >Checks pause in hidden tabs</span
                >
            </div>
        </section>

        <section class="space-y-4" aria-label="Archive controls">
            <form
                class="flex flex-col gap-3 sm:flex-row"
                @submit.prevent="search"
            >
                <div class="relative flex-1">
                    <label for="library-search" class="sr-only"
                        >Search prompts</label
                    >
                    <Search
                        class="pointer-events-none absolute top-1/2 left-3.5 size-4 -translate-y-1/2 text-signal-faint"
                        aria-hidden="true"
                    />
                    <input
                        id="library-search"
                        v-model="query"
                        type="search"
                        placeholder="Search the prompt archive…"
                        class="h-11 w-full border border-line bg-paper pr-28 pl-10 font-sans text-sm text-signal placeholder:text-signal-faint focus:border-signal focus:outline-none"
                    />
                    <button
                        type="submit"
                        class="focus-ring absolute top-1 right-1 bottom-1 border border-signal bg-signal px-4 font-mono text-[0.6rem] tracking-[0.14em] text-signal-invert uppercase transition-colors hover:bg-signal-muted"
                    >
                        Search
                    </button>
                </div>

                <button
                    type="button"
                    class="focus-ring inline-flex h-11 shrink-0 items-center justify-center gap-2 border border-line bg-paper px-4 font-mono text-[0.62rem] tracking-[0.14em] text-signal-muted uppercase transition-colors hover:border-signal hover:text-signal"
                    :aria-expanded="showFilters"
                    aria-controls="library-filters"
                    @click="showFilters = !showFilters"
                >
                    <SlidersHorizontal class="size-3.5" aria-hidden="true" />
                    Filters
                    <span
                        v-if="activeFilters > 0"
                        class="inline-flex min-w-5 items-center justify-center border border-signal px-1 py-0.5 font-mono text-[0.55rem] text-signal"
                    >
                        {{ activeFilters }}
                    </span>
                </button>
            </form>

            <div
                v-if="showFilters"
                id="library-filters"
                class="grid gap-px border border-line bg-line sm:grid-cols-3"
            >
                <div class="bg-paper p-4">
                    <label for="filter-status" class="eyebrow text-[0.55rem]"
                        >Status</label
                    >
                    <select
                        id="filter-status"
                        :value="filters.status"
                        class="mt-2 h-10 w-full border border-line bg-background px-2.5 font-sans text-sm text-signal focus:border-signal focus:outline-none"
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

                <div class="bg-paper p-4">
                    <label for="filter-quality" class="eyebrow text-[0.55rem]"
                        >Quality</label
                    >
                    <select
                        id="filter-quality"
                        :value="filters.quality"
                        class="mt-2 h-10 w-full border border-line bg-background px-2.5 font-sans text-sm text-signal focus:border-signal focus:outline-none"
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

                <div class="bg-paper p-4">
                    <label for="filter-size" class="eyebrow text-[0.55rem]"
                        >Proportion</label
                    >
                    <select
                        id="filter-size"
                        :value="filters.size"
                        class="mt-2 h-10 w-full border border-line bg-background px-2.5 font-sans text-sm text-signal focus:border-signal focus:outline-none"
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
                    class="flex flex-wrap items-center justify-between gap-3 bg-background px-4 py-3 sm:col-span-3"
                >
                    <p
                        class="font-mono text-[0.58rem] tracking-[0.1em] text-signal-faint uppercase"
                    >
                        Changes apply to the index immediately
                    </p>
                    <button
                        v-if="activeFilters > 0"
                        type="button"
                        class="focus-ring inline-flex items-center gap-1.5 font-mono text-[0.6rem] tracking-[0.12em] text-signal-muted uppercase transition-colors hover:text-signal"
                        @click="clearAll"
                    >
                        <X class="size-3" aria-hidden="true" />
                        Clear all
                    </button>
                </div>
            </div>

            <div
                v-if="activeChips.length > 0"
                class="flex flex-wrap items-center gap-2"
                aria-label="Applied filters"
            >
                <span class="eyebrow mr-1 text-[0.55rem]">Applied</span>
                <span
                    v-for="chip in activeChips"
                    :key="chip.key"
                    class="inline-flex items-center gap-2 border border-line bg-paper py-1 pr-1 pl-2.5 text-xs text-signal-muted"
                >
                    <span>{{ chip.label }}</span>
                    <button
                        type="button"
                        class="focus-ring inline-flex size-5 items-center justify-center border border-line-soft text-signal-muted transition-colors hover:border-signal hover:text-signal"
                        :aria-label="`Clear ${chip.key} filter`"
                        @click="clearChip(chip.key)"
                    >
                        <X class="size-3" aria-hidden="true" />
                    </button>
                </span>
                <button
                    v-if="activeFilters > 1"
                    type="button"
                    class="focus-ring font-mono text-[0.58rem] tracking-[0.12em] text-signal-faint uppercase transition-colors hover:text-signal"
                    @click="clearAll"
                >
                    Reset all
                </button>
            </div>
        </section>

        <div
            v-if="loadError"
            class="flex flex-col gap-3 border border-signal bg-paper-deep px-4 py-3 sm:flex-row sm:items-center sm:justify-between"
            role="alert"
        >
            <div class="flex items-start gap-3">
                <AlertTriangle
                    class="mt-0.5 size-4 text-signal"
                    aria-hidden="true"
                />
                <div>
                    <p class="text-xs font-medium text-signal">
                        The index could not be updated.
                    </p>
                    <p class="mt-0.5 text-xs text-signal-muted">
                        {{ loadError }}
                    </p>
                </div>
            </div>
            <button
                type="button"
                class="focus-ring inline-flex shrink-0 items-center gap-2 border border-line px-3 py-1.5 font-mono text-[0.6rem] tracking-[0.12em] text-signal uppercase transition-colors hover:border-signal"
                @click="apply()"
            >
                <RefreshCw class="size-3" aria-hidden="true" />
                Retry
            </button>
        </div>

        <p
            v-if="refreshing"
            class="flex items-center gap-2 font-mono text-[0.6rem] tracking-[0.14em] text-signal-muted uppercase"
            role="status"
            aria-live="polite"
        >
            <span
                class="status-dot animate-breathe bg-signal"
                aria-hidden="true"
            />
            Updating index
        </p>

        <div
            v-if="generations.data.length > 0"
            class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4"
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
            class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4"
            aria-busy="true"
            aria-label="Loading archive"
        >
            <div
                v-for="index in 8"
                :key="index"
                class="border border-line bg-paper"
            >
                <div class="aspect-square border-b border-line bg-paper-deep" />
                <div class="space-y-2 p-4">
                    <div class="h-3 w-3/4 bg-paper-deep" />
                    <div class="h-3 w-1/2 bg-paper-deep" />
                </div>
            </div>
        </div>

        <div
            v-else
            class="flex flex-col items-center justify-center border border-line bg-paper px-6 py-20 text-center"
        >
            <span
                class="font-display text-5xl text-signal-faint"
                aria-hidden="true"
                >×</span
            >
            <h2
                class="mt-4 font-display text-3xl tracking-[-0.03em] text-signal"
            >
                {{
                    activeFilters > 0
                        ? 'No plates match those filters.'
                        : 'The archive is empty.'
                }}
            </h2>
            <p class="mt-3 max-w-sm text-sm leading-relaxed text-signal-muted">
                {{
                    activeFilters > 0
                        ? 'Broaden the search, or reset the index to see every plate.'
                        : 'Finished plates are archived here privately, exactly as the provider returned them.'
                }}
            </p>
            <button
                v-if="activeFilters > 0"
                type="button"
                class="focus-ring mt-6 inline-flex items-center gap-2 border border-signal px-4 py-2 font-mono text-[0.62rem] tracking-[0.12em] text-signal uppercase transition-colors hover:bg-signal hover:text-signal-invert"
                @click="clearAll"
            >
                <X class="size-3.5" aria-hidden="true" />
                Reset filters
            </button>
            <Link
                v-else
                href="/studio"
                class="focus-ring mt-6 inline-flex items-center gap-2 border border-signal bg-signal px-4 py-2 font-mono text-[0.62rem] tracking-[0.12em] text-signal-invert uppercase transition-colors hover:bg-signal-muted"
            >
                Make the first plate
                <ArrowRight class="size-3.5" aria-hidden="true" />
            </Link>
        </div>

        <nav
            v-if="generations.last_page > 1"
            class="flex flex-col gap-4 border-t border-line pt-5 lg:flex-row lg:items-center lg:justify-between"
            aria-label="Library pages"
        >
            <p
                class="font-mono text-[0.6rem] tracking-[0.1em] text-signal-faint uppercase"
            >
                Showing {{ generations.from ?? 0 }}–{{ generations.to ?? 0 }} of
                {{ generations.total.toLocaleString() }}
            </p>

            <div class="flex flex-wrap items-center gap-2">
                <Link
                    v-if="generations.prev_page_url"
                    :href="generations.prev_page_url"
                    preserve-scroll
                    class="focus-ring inline-flex items-center gap-1.5 border border-line px-3 py-1.5 font-mono text-[0.6rem] tracking-[0.12em] text-signal-muted uppercase transition-colors hover:border-signal hover:text-signal"
                    aria-label="Go to previous page"
                >
                    <ArrowLeft class="size-3" aria-hidden="true" />
                    Previous
                </Link>
                <span
                    v-else
                    class="inline-flex items-center gap-1.5 border border-line-soft px-3 py-1.5 font-mono text-[0.6rem] tracking-[0.12em] text-signal-faint uppercase"
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
                                ? 'border-signal bg-signal text-signal-invert'
                                : 'border-line text-signal-muted hover:border-signal hover:text-signal'
                        "
                        :aria-label="`Go to page ${link.label}`"
                    >
                        {{ link.label }}
                    </Link>
                    <span
                        v-else
                        class="inline-flex size-8 items-center justify-center border border-line-soft font-mono text-[0.62rem] text-signal-faint"
                        aria-hidden="true"
                        >{{ link.label }}</span
                    >
                </span>

                <Link
                    v-if="generations.next_page_url"
                    :href="generations.next_page_url"
                    preserve-scroll
                    class="focus-ring inline-flex items-center gap-1.5 border border-line px-3 py-1.5 font-mono text-[0.6rem] tracking-[0.12em] text-signal-muted uppercase transition-colors hover:border-signal hover:text-signal"
                    aria-label="Go to next page"
                >
                    Next
                    <ArrowRight class="size-3" aria-hidden="true" />
                </Link>
                <span
                    v-else
                    class="inline-flex items-center gap-1.5 border border-line-soft px-3 py-1.5 font-mono text-[0.6rem] tracking-[0.12em] text-signal-faint uppercase"
                    aria-disabled="true"
                >
                    Next
                    <ArrowRight class="size-3" aria-hidden="true" />
                </span>
            </div>
        </nav>
    </div>
</template>
