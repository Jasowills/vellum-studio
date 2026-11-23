<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import {
    ArrowUpRight,
    CircleCheck,
    Clock3,
    RefreshCw,
    ShieldCheck,
    TriangleAlert,
} from '@lucide/vue';
import { computed, ref } from 'vue';
import { formatDate, formatDateTime } from '@/lib/vellum';

type ReconDay = {
    date: string;
    provider: {
        generations: number;
        input_text_tokens: number;
        input_image_tokens: number;
        output_tokens: number;
        reasoning_tokens: number;
        total_tokens: number;
        cost_nanos: number;
    };
    local: {
        generations: number;
        input_text_tokens: number;
        input_image_tokens: number;
        output_tokens: number;
        reasoning_tokens: number;
        total_tokens: number;
        cost_nanos: number;
    };
    cost_difference_nanos: number;
    token_difference: number;
    generation_difference: number;
    matches: boolean;
};

type RecentCharge = {
    id: string | null;
    prompt: string | null;
    user: string | null;
    provider_cost: string;
    customer_charge: string;
    margin: string;
    tokens: number;
    settled_at: string | null;
};

const props = defineProps<{
    report: {
        start_date: string;
        end_date: string;
        provider: {
            generations: number;
            input_text_tokens: number;
            input_image_tokens: number;
            output_tokens: number;
            reasoning_tokens: number;
            total_tokens: number;
            cost_nanos: number;
            cost_usd: string;
            cost: string;
            raw_total: Record<string, unknown>;
        };
        local: {
            generations: number;
            input_text_tokens: number;
            input_image_tokens: number;
            output_tokens: number;
            reasoning_tokens: number;
            total_tokens: number;
            cost_nanos: number;
            cost_usd: string;
            cost: string;
        };
        days: ReconDay[];
        differences: ReconDay[];
        matches: boolean;
    } | null;
    error: string | null;
    range: { days: number; start: string; end: string };
    lifetime: {
        charged_generations: number;
        provider_cost: string;
        customer_charge: string;
        margin: string;
        tokens: number;
        users: number;
    };
    recent: RecentCharge[];
}>();

const showAllDays = ref(false);
const refreshing = ref(false);

const daysToShow = computed(() => {
    if (!props.report) {
        return [];
    }

    return showAllDays.value ? props.report.days : props.report.differences;
});

function parseMoneyAmount(value: string): number {
    const negative = /^\s*-/.test(value);
    const amount = Number.parseFloat(value.replace(/[^0-9.]/g, ''));

    if (!Number.isFinite(amount)) {
        return Number.NaN;
    }

    return negative ? -amount : amount;
}

const marginPercent = computed(() => {
    const provider = parseMoneyAmount(props.lifetime.provider_cost);
    const margin = parseMoneyAmount(props.lifetime.margin);

    if (
        !Number.isFinite(provider) ||
        provider <= 0 ||
        !Number.isFinite(margin)
    ) {
        return '—';
    }

    return `${((margin / provider) * 100).toFixed(1)}%`;
});

const statusLabel = computed(() => {
    if (!props.report) {
        return 'UNAVAILABLE';
    }

    return props.report.matches ? 'MATCH' : 'MISMATCH';
});

const emptyDifferencesMessage = computed(() =>
    props.report && props.report.days.length === 0
        ? 'No provider or ledger days were returned for this range.'
        : 'No differences in this range. Every provider day matches the ledger.',
);

function costCell(nanos: number): string {
    return nanos === 0 ? '—' : `$${(nanos / 1e9).toFixed(9)}`;
}

function deltaCostCell(nanos: number): string {
    return nanos === 0 ? '0' : (nanos / 1e9).toFixed(9);
}

function loadDays(days: number): void {
    if (days === props.range.days) {
        return;
    }

    router.get(
        '/admin/report',
        { days },
        { preserveState: true, preserveScroll: true, replace: true },
    );
}

function refresh(): void {
    refreshing.value = true;
    router.reload({
        only: ['report', 'error', 'lifetime', 'recent'],
        onFinish: () => {
            window.setTimeout(() => (refreshing.value = false), 600);
        },
    });
}
</script>

<template>
    <Head title="Operator's ledger" />

    <div class="min-h-svh bg-background text-signal">
        <div
            class="mx-auto max-w-[1440px] space-y-11 px-5 py-10 sm:px-8 sm:py-12 lg:px-12 lg:py-14"
            :aria-busy="refreshing"
        >
            <header
                class="flex flex-col justify-between gap-7 border-b border-line pb-8 lg:flex-row lg:items-end"
            >
                <div>
                    <p class="eyebrow flex items-center gap-3">
                        <ShieldCheck
                            class="size-3.5 text-signal"
                            aria-hidden="true"
                        />
                        01 / Operator — reconciliation
                    </p>
                    <h1
                        class="display-title mt-6 max-w-[16ch] text-[clamp(2.8rem,5.2vw,4.4rem)] text-signal"
                    >
                        The studio
                        <em class="text-signal-muted not-italic">books.</em>
                    </h1>
                </div>
                <div class="flex flex-wrap items-center gap-2">
                    <div
                        class="inline-flex border border-line bg-paper"
                        role="group"
                        aria-label="Reconciliation range"
                    >
                        <button
                            v-for="days in [7, 14, 30, 92]"
                            :key="days"
                            type="button"
                            class="focus-ring px-3 py-2 font-mono text-[0.58rem] tracking-[0.08em] uppercase transition-colors motion-reduce:transition-none"
                            :class="
                                range.days === days
                                    ? 'bg-signal text-signal-invert'
                                    : 'text-signal-muted hover:bg-paper-deep hover:text-signal'
                            "
                            :aria-pressed="range.days === days"
                            @click="loadDays(days)"
                        >
                            {{ days }}d
                        </button>
                    </div>
                    <button
                        type="button"
                        class="focus-ring inline-flex items-center gap-2 border border-line bg-paper px-3 py-2 font-mono text-[0.58rem] tracking-[0.08em] text-signal-muted uppercase transition-colors hover:border-signal hover:text-signal motion-reduce:transition-none"
                        @click="refresh"
                    >
                        <RefreshCw
                            class="size-3"
                            :class="{ 'animate-spin': refreshing }"
                            aria-hidden="true"
                        />
                        Refresh
                    </button>
                </div>
            </header>

            <div
                v-if="error"
                class="flex items-start gap-3 border border-l-2 border-signal border-l-signal bg-paper-deep px-4 py-3 text-sm leading-relaxed text-signal"
                role="alert"
            >
                <TriangleAlert
                    class="mt-0.5 size-4 shrink-0"
                    aria-hidden="true"
                />
                <div>
                    <p
                        class="font-mono text-[0.55rem] tracking-[0.14em] text-signal-muted uppercase"
                    >
                        Provider unavailable
                    </p>
                    <p class="mt-1">{{ error }}</p>
                    <p class="mt-1 text-xs text-signal-muted">
                        Lifetime figures and settled charges below are still
                        from the local ledger.
                    </p>
                </div>
            </div>

            <section
                v-if="report"
                class="border bg-paper"
                :class="
                    report.matches
                        ? 'border-l-2 border-line border-l-signal'
                        : 'border-dashed border-signal'
                "
                :aria-label="`Reconciliation status: ${statusLabel}`"
            >
                <div
                    class="flex flex-wrap items-start justify-between gap-4 p-5 sm:p-6"
                >
                    <div class="flex items-start gap-3">
                        <CircleCheck
                            v-if="report.matches"
                            class="mt-0.5 size-5 shrink-0 text-signal"
                            aria-hidden="true"
                        />
                        <TriangleAlert
                            v-else
                            class="mt-0.5 size-5 shrink-0 text-signal"
                            aria-hidden="true"
                        />
                        <div>
                            <p
                                class="font-mono text-[0.55rem] tracking-[0.16em] text-signal-muted uppercase"
                            >
                                {{ statusLabel }} /
                                {{ report.differences.length }}
                                {{
                                    report.differences.length === 1
                                        ? 'day'
                                        : 'days'
                                }}
                                differing
                            </p>
                            <p
                                class="mt-2 font-display text-xl tracking-[-0.02em] text-signal sm:text-2xl"
                            >
                                {{
                                    report.matches
                                        ? 'Provider usage and the local ledger match exactly.'
                                        : `${report.differences.length} day(s) differ from Pixelforge.`
                                }}
                            </p>
                            <p
                                class="mt-2 max-w-2xl text-xs leading-relaxed text-signal-muted"
                            >
                                {{
                                    report.matches
                                        ? 'Generations, token classes, and cost agree across the whole range.'
                                        : 'Investigate before closing the books: a missing charge or a late settlement will show here.'
                                }}
                            </p>
                        </div>
                    </div>
                    <p
                        class="ledger text-[0.58rem] tracking-[0.1em] text-signal-faint uppercase"
                    >
                        {{ formatDate(report.start_date) }} —
                        {{ formatDate(report.end_date) }}
                    </p>
                </div>
            </section>

            <div
                v-else-if="!error"
                class="border border-dashed border-line bg-paper px-5 py-6"
                role="status"
            >
                <p
                    class="font-mono text-[0.55rem] tracking-[0.16em] text-signal-muted uppercase"
                >
                    UNAVAILABLE / NO REPORT
                </p>
                <p class="mt-2 text-sm leading-relaxed text-signal">
                    No reconciliation data is available for this range. Select
                    another range or refresh to request the provider report
                    again.
                </p>
            </div>

            <template v-if="report">
                <section aria-label="Totals comparison">
                    <div
                        class="flex items-baseline justify-between border-b border-line pb-3"
                    >
                        <h2 class="eyebrow">02 / Totals comparison</h2>
                        <p
                            class="ledger text-[0.55rem] tracking-[0.12em] text-signal-faint uppercase"
                        >
                            USD / exact
                        </p>
                    </div>
                    <div
                        class="grid border-x border-b border-line lg:grid-cols-2"
                    >
                        <div
                            class="border-b border-line-soft p-5 sm:p-6 lg:border-r lg:border-b-0"
                        >
                            <div
                                class="flex items-center justify-between gap-3"
                            >
                                <p class="eyebrow">Pixelforge says</p>
                                <p
                                    class="font-mono text-[0.55rem] tracking-[0.12em] text-signal-faint uppercase"
                                >
                                    Provider
                                </p>
                            </div>
                            <p
                                class="ledger mt-5 text-2xl tracking-[-0.04em] text-signal lg:text-3xl"
                            >
                                {{ report.provider.cost }}
                            </p>
                            <dl
                                class="mt-6 grid grid-cols-2 gap-x-4 gap-y-3 font-mono text-[0.62rem] tabular-nums"
                            >
                                <div class="border-t border-line-soft pt-2">
                                    <dt class="text-signal-faint">
                                        Generations
                                    </dt>
                                    <dd class="mt-0.5 text-signal">
                                        {{
                                            report.provider.generations.toLocaleString()
                                        }}
                                    </dd>
                                </div>
                                <div class="border-t border-line-soft pt-2">
                                    <dt class="text-signal-faint">Tokens</dt>
                                    <dd class="mt-0.5 text-signal">
                                        {{
                                            report.provider.total_tokens.toLocaleString()
                                        }}
                                    </dd>
                                </div>
                                <div class="border-t border-line-soft pt-2">
                                    <dt class="text-signal-faint">
                                        Input text
                                    </dt>
                                    <dd class="mt-0.5 text-signal">
                                        {{
                                            report.provider.input_text_tokens.toLocaleString()
                                        }}
                                    </dd>
                                </div>
                                <div class="border-t border-line-soft pt-2">
                                    <dt class="text-signal-faint">
                                        Input images
                                    </dt>
                                    <dd class="mt-0.5 text-signal">
                                        {{
                                            report.provider.input_image_tokens.toLocaleString()
                                        }}
                                    </dd>
                                </div>
                                <div class="border-t border-line-soft pt-2">
                                    <dt class="text-signal-faint">Output</dt>
                                    <dd class="mt-0.5 text-signal">
                                        {{
                                            report.provider.output_tokens.toLocaleString()
                                        }}
                                    </dd>
                                </div>
                                <div class="border-t border-line-soft pt-2">
                                    <dt class="text-signal-faint">Reasoning</dt>
                                    <dd class="mt-0.5 text-signal">
                                        {{
                                            report.provider.reasoning_tokens.toLocaleString()
                                        }}
                                    </dd>
                                </div>
                            </dl>
                        </div>

                        <div class="p-5 sm:p-6">
                            <div
                                class="flex items-center justify-between gap-3"
                            >
                                <p class="eyebrow">Vellum ledger says</p>
                                <p
                                    class="font-mono text-[0.55rem] tracking-[0.12em] text-signal-faint uppercase"
                                >
                                    Local
                                </p>
                            </div>
                            <p
                                class="ledger mt-5 text-2xl tracking-[-0.04em] text-signal lg:text-3xl"
                            >
                                {{ report.local.cost }}
                            </p>
                            <dl
                                class="mt-6 grid grid-cols-2 gap-x-4 gap-y-3 font-mono text-[0.62rem] tabular-nums"
                            >
                                <div class="border-t border-line-soft pt-2">
                                    <dt class="text-signal-faint">
                                        Generations
                                    </dt>
                                    <dd class="mt-0.5 text-signal">
                                        {{
                                            report.local.generations.toLocaleString()
                                        }}
                                    </dd>
                                </div>
                                <div class="border-t border-line-soft pt-2">
                                    <dt class="text-signal-faint">Tokens</dt>
                                    <dd class="mt-0.5 text-signal">
                                        {{
                                            report.local.total_tokens.toLocaleString()
                                        }}
                                    </dd>
                                </div>
                                <div class="border-t border-line-soft pt-2">
                                    <dt class="text-signal-faint">
                                        Input text
                                    </dt>
                                    <dd class="mt-0.5 text-signal">
                                        {{
                                            report.local.input_text_tokens.toLocaleString()
                                        }}
                                    </dd>
                                </div>
                                <div class="border-t border-line-soft pt-2">
                                    <dt class="text-signal-faint">
                                        Input images
                                    </dt>
                                    <dd class="mt-0.5 text-signal">
                                        {{
                                            report.local.input_image_tokens.toLocaleString()
                                        }}
                                    </dd>
                                </div>
                                <div class="border-t border-line-soft pt-2">
                                    <dt class="text-signal-faint">Output</dt>
                                    <dd class="mt-0.5 text-signal">
                                        {{
                                            report.local.output_tokens.toLocaleString()
                                        }}
                                    </dd>
                                </div>
                                <div class="border-t border-line-soft pt-2">
                                    <dt class="text-signal-faint">Reasoning</dt>
                                    <dd class="mt-0.5 text-signal">
                                        {{
                                            report.local.reasoning_tokens.toLocaleString()
                                        }}
                                    </dd>
                                </div>
                            </dl>
                        </div>
                    </div>
                </section>

                <section class="space-y-4" aria-labelledby="differences-title">
                    <div
                        class="flex flex-wrap items-end justify-between gap-3 border-b border-line pb-4"
                    >
                        <div>
                            <p class="eyebrow">03 / Day by day</p>
                            <h2
                                id="differences-title"
                                class="display-title mt-2 text-2xl tracking-[-0.02em] text-signal sm:text-3xl"
                            >
                                {{ showAllDays ? 'Every day' : 'Differences' }}
                            </h2>
                        </div>
                        <button
                            type="button"
                            class="focus-ring pb-1 font-mono text-[0.58rem] tracking-[0.1em] text-signal-muted uppercase hover:text-signal"
                            :aria-pressed="showAllDays"
                            @click="showAllDays = !showAllDays"
                        >
                            {{
                                showAllDays
                                    ? 'Show only differences'
                                    : 'Show every day'
                            }}
                        </button>
                    </div>

                    <div
                        v-if="daysToShow.length === 0"
                        class="flex items-start gap-3 border border-l-2 border-line border-l-signal bg-paper px-5 py-6 text-sm text-signal"
                    >
                        <CircleCheck
                            class="mt-0.5 size-4 shrink-0"
                            aria-hidden="true"
                        />
                        <div>
                            <p
                                class="font-mono text-[0.55rem] tracking-[0.14em] text-signal-muted uppercase"
                            >
                                MATCH / NO ROWS
                            </p>
                            <p class="mt-1">{{ emptyDifferencesMessage }}</p>
                        </div>
                    </div>

                    <template v-else>
                        <div
                            class="hidden border border-line bg-paper lg:block"
                        >
                            <table
                                class="w-full border-collapse text-left font-mono text-[0.62rem] tabular-nums"
                            >
                                <caption class="sr-only">
                                    Per-day provider usage versus the local
                                    ledger. Deltas are local minus provider.
                                </caption>
                                <thead>
                                    <tr
                                        class="border-b border-line text-[0.52rem] tracking-[0.12em] text-signal-muted uppercase"
                                    >
                                        <th
                                            scope="col"
                                            class="px-3 py-2.5 font-medium"
                                        >
                                            Date
                                        </th>
                                        <th
                                            scope="col"
                                            class="px-3 py-2.5 font-medium"
                                        >
                                            Status
                                        </th>
                                        <th
                                            scope="col"
                                            class="px-3 py-2.5 text-right font-medium"
                                        >
                                            Provider
                                        </th>
                                        <th
                                            scope="col"
                                            class="px-3 py-2.5 text-right font-medium"
                                        >
                                            Local
                                        </th>
                                        <th
                                            scope="col"
                                            class="px-3 py-2.5 text-right font-medium"
                                        >
                                            Δ cost
                                        </th>
                                        <th
                                            scope="col"
                                            class="px-3 py-2.5 text-right font-medium"
                                        >
                                            Δ tokens
                                        </th>
                                        <th
                                            scope="col"
                                            class="px-3 py-2.5 text-right font-medium"
                                        >
                                            Δ gens
                                        </th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr
                                        v-for="day in daysToShow"
                                        :key="day.date"
                                        class="border-b border-line-soft last:border-0"
                                    >
                                        <th
                                            scope="row"
                                            class="border-l-2 px-3 py-2 text-left font-normal"
                                            :class="
                                                day.matches
                                                    ? 'border-l-line-soft'
                                                    : 'border-l-signal'
                                            "
                                        >
                                            <span class="ledger text-signal">{{
                                                day.date
                                            }}</span>
                                        </th>
                                        <td class="px-3 py-2">
                                            <span
                                                class="inline-flex items-center gap-1.5 text-[0.55rem] tracking-[0.1em] whitespace-nowrap uppercase"
                                            >
                                                <component
                                                    :is="
                                                        day.matches
                                                            ? CircleCheck
                                                            : TriangleAlert
                                                    "
                                                    class="size-3 shrink-0"
                                                    aria-hidden="true"
                                                />
                                                <span
                                                    :class="
                                                        day.matches
                                                            ? 'text-signal-muted'
                                                            : 'text-signal'
                                                    "
                                                >
                                                    {{
                                                        day.matches
                                                            ? 'MATCH'
                                                            : 'DIFF'
                                                    }}
                                                </span>
                                            </span>
                                        </td>
                                        <td
                                            class="ledger px-3 py-2 text-right text-signal-muted"
                                        >
                                            {{
                                                costCell(
                                                    day.provider.cost_nanos,
                                                )
                                            }}
                                        </td>
                                        <td
                                            class="ledger px-3 py-2 text-right text-signal-muted"
                                        >
                                            {{ costCell(day.local.cost_nanos) }}
                                        </td>
                                        <td
                                            class="ledger px-3 py-2 text-right text-signal"
                                        >
                                            {{
                                                deltaCostCell(
                                                    day.cost_difference_nanos,
                                                )
                                            }}
                                        </td>
                                        <td
                                            class="ledger px-3 py-2 text-right text-signal"
                                        >
                                            {{ day.token_difference }}
                                        </td>
                                        <td
                                            class="ledger px-3 py-2 text-right text-signal"
                                        >
                                            {{ day.generation_difference }}
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        <ul class="border border-line bg-paper lg:hidden">
                            <li
                                v-for="day in daysToShow"
                                :key="day.date"
                                class="border-b border-line-soft p-4 last:border-0"
                                :class="
                                    day.matches
                                        ? 'border-l-2 border-l-line-soft'
                                        : 'border-l-2 border-l-signal'
                                "
                            >
                                <div
                                    class="flex items-center justify-between gap-3"
                                >
                                    <span
                                        class="ledger text-[0.62rem] text-signal"
                                        >{{ day.date }}</span
                                    >
                                    <span
                                        class="inline-flex items-center gap-1.5 font-mono text-[0.55rem] tracking-[0.1em] uppercase"
                                    >
                                        <component
                                            :is="
                                                day.matches
                                                    ? CircleCheck
                                                    : TriangleAlert
                                            "
                                            class="size-3 shrink-0"
                                            aria-hidden="true"
                                        />
                                        <span
                                            :class="
                                                day.matches
                                                    ? 'text-signal-muted'
                                                    : 'text-signal'
                                            "
                                        >
                                            {{ day.matches ? 'MATCH' : 'DIFF' }}
                                        </span>
                                    </span>
                                </div>
                                <dl
                                    class="mt-3 grid grid-cols-2 gap-x-4 gap-y-2 font-mono text-[0.6rem] tabular-nums"
                                >
                                    <div
                                        class="flex items-center justify-between gap-2 border-b border-line-soft pb-2"
                                    >
                                        <dt
                                            class="text-[0.52rem] tracking-[0.1em] text-signal-faint uppercase"
                                        >
                                            Provider
                                        </dt>
                                        <dd class="text-signal-muted">
                                            {{
                                                costCell(
                                                    day.provider.cost_nanos,
                                                )
                                            }}
                                        </dd>
                                    </div>
                                    <div
                                        class="flex items-center justify-between gap-2 border-b border-line-soft pb-2"
                                    >
                                        <dt
                                            class="text-[0.52rem] tracking-[0.1em] text-signal-faint uppercase"
                                        >
                                            Local
                                        </dt>
                                        <dd class="text-signal-muted">
                                            {{ costCell(day.local.cost_nanos) }}
                                        </dd>
                                    </div>
                                    <div
                                        class="flex items-center justify-between gap-2"
                                    >
                                        <dt
                                            class="text-[0.52rem] tracking-[0.1em] text-signal-faint uppercase"
                                        >
                                            Δ cost
                                        </dt>
                                        <dd class="text-signal">
                                            {{
                                                deltaCostCell(
                                                    day.cost_difference_nanos,
                                                )
                                            }}
                                        </dd>
                                    </div>
                                    <div
                                        class="flex items-center justify-between gap-2"
                                    >
                                        <dt
                                            class="text-[0.52rem] tracking-[0.1em] text-signal-faint uppercase"
                                        >
                                            Δ tokens
                                        </dt>
                                        <dd class="text-signal">
                                            {{ day.token_difference }}
                                        </dd>
                                    </div>
                                    <div
                                        class="col-span-2 flex items-center justify-between gap-2"
                                    >
                                        <dt
                                            class="text-[0.52rem] tracking-[0.1em] text-signal-faint uppercase"
                                        >
                                            Δ gens
                                        </dt>
                                        <dd class="text-signal">
                                            {{ day.generation_difference }}
                                        </dd>
                                    </div>
                                </dl>
                                <p
                                    class="mt-3 border-t border-line-soft pt-2 font-mono text-[0.55rem] text-signal-faint"
                                >
                                    Deltas are local minus provider.
                                </p>
                            </li>
                        </ul>
                    </template>
                </section>
            </template>

            <section aria-labelledby="lifetime-title">
                <div
                    class="flex items-baseline justify-between border-b border-line pb-3"
                >
                    <h2 id="lifetime-title" class="eyebrow">
                        04 / Lifetime ledger
                    </h2>
                    <p
                        class="ledger text-[0.55rem] tracking-[0.12em] text-signal-faint uppercase"
                    >
                        Settled / all time
                    </p>
                </div>
                <div
                    class="grid border-x border-b border-line sm:grid-cols-2 lg:grid-cols-4"
                >
                    <div
                        class="border-line bg-signal p-5 text-signal-invert sm:border-r sm:p-6"
                    >
                        <p
                            class="font-mono text-[0.58rem] tracking-[0.14em] text-signal-invert/60 uppercase"
                        >
                            Lifetime margin
                        </p>
                        <p
                            class="ledger mt-5 text-2xl tracking-[-0.04em] lg:text-3xl"
                        >
                            {{ lifetime.margin }}
                        </p>
                        <p
                            class="ledger mt-2 text-[0.58rem] tracking-[0.08em] text-signal-invert/70 uppercase"
                        >
                            {{ marginPercent }} of provider cost
                        </p>
                        <p
                            class="mt-1 font-mono text-[0.52rem] tracking-[0.08em] text-signal-invert/70 uppercase"
                        >
                            margin ÷ provider cost
                        </p>
                    </div>
                    <div
                        class="border-t border-line-soft bg-paper p-5 sm:border-t-0 sm:p-6 lg:border-r"
                    >
                        <p class="eyebrow">Provider cost</p>
                        <p
                            class="ledger mt-5 text-2xl tracking-[-0.03em] text-signal lg:text-3xl"
                        >
                            {{ lifetime.provider_cost }}
                        </p>
                        <p
                            class="ledger mt-2 text-[0.58rem] tracking-[0.08em] text-signal-muted uppercase"
                        >
                            lifetime, settled
                        </p>
                    </div>
                    <div
                        class="border-t border-line-soft bg-paper p-5 sm:border-t-0 sm:border-r sm:p-6"
                    >
                        <p class="eyebrow">Customer charges</p>
                        <p
                            class="ledger mt-5 text-2xl tracking-[-0.03em] text-signal lg:text-3xl"
                        >
                            {{ lifetime.customer_charge }}
                        </p>
                        <p
                            class="ledger mt-2 text-[0.58rem] tracking-[0.08em] text-signal-muted uppercase"
                        >
                            {{ lifetime.charged_generations.toLocaleString() }}
                            charges
                        </p>
                    </div>
                    <div
                        class="border-t border-line-soft bg-paper p-5 sm:p-6 lg:border-t-0"
                    >
                        <p class="eyebrow">Studio</p>
                        <p
                            class="ledger mt-5 text-2xl tracking-[-0.03em] text-signal lg:text-3xl"
                        >
                            {{ lifetime.users.toLocaleString() }}
                        </p>
                        <p
                            class="ledger mt-2 text-[0.58rem] tracking-[0.08em] text-signal-muted uppercase"
                        >
                            accounts ·
                            {{ lifetime.tokens.toLocaleString() }} tokens
                        </p>
                    </div>
                </div>
            </section>

            <section class="space-y-4" aria-labelledby="operator-recent">
                <div
                    class="flex flex-wrap items-end justify-between gap-3 border-b border-line pb-4"
                >
                    <div>
                        <p class="eyebrow">05 / Immutable records</p>
                        <h2
                            id="operator-recent"
                            class="display-title mt-2 text-2xl tracking-[-0.02em] text-signal sm:text-3xl"
                        >
                            Latest settlements
                        </h2>
                    </div>
                    <p
                        class="flex items-center gap-1.5 font-mono text-[0.55rem] tracking-[0.1em] text-signal-muted uppercase"
                    >
                        <Clock3 class="size-3" aria-hidden="true" />
                        Newest first
                    </p>
                </div>

                <template v-if="recent.length > 0">
                    <div class="hidden border border-line bg-paper lg:block">
                        <table class="w-full border-collapse text-left text-sm">
                            <thead>
                                <tr
                                    class="border-b border-line font-mono text-[0.52rem] tracking-[0.12em] text-signal-muted uppercase"
                                >
                                    <th
                                        scope="col"
                                        class="px-4 py-3 font-medium"
                                    >
                                        Plate
                                    </th>
                                    <th
                                        scope="col"
                                        class="px-4 py-3 font-medium"
                                    >
                                        Account
                                    </th>
                                    <th
                                        scope="col"
                                        class="px-4 py-3 text-right font-medium"
                                    >
                                        Provider
                                    </th>
                                    <th
                                        scope="col"
                                        class="px-4 py-3 text-right font-medium"
                                    >
                                        Charged
                                    </th>
                                    <th
                                        scope="col"
                                        class="px-4 py-3 text-right font-medium"
                                    >
                                        Tokens
                                    </th>
                                    <th
                                        scope="col"
                                        class="px-4 py-3 text-right font-medium"
                                    >
                                        Margin
                                    </th>
                                    <th scope="col" class="w-10 px-4 py-3">
                                        <span class="sr-only">Open</span>
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr
                                    v-for="(entry, index) in recent"
                                    :key="`${entry.id}-${index}`"
                                    class="border-b border-line-soft transition-colors last:border-0 hover:bg-paper-deep motion-reduce:transition-none"
                                >
                                    <td class="max-w-[24rem] px-4 py-3">
                                        <Link
                                            v-if="entry.id"
                                            :href="`/generations/${entry.id}`"
                                            class="line-clamp-1 font-display text-[0.95rem] text-signal hover:text-signal-muted"
                                            >{{ entry.prompt }}</Link
                                        >
                                        <span v-else class="text-signal-muted"
                                            >Removed plate</span
                                        >
                                        <p
                                            class="ledger mt-0.5 text-[0.55rem] text-signal-faint"
                                        >
                                            {{
                                                formatDateTime(entry.settled_at)
                                            }}
                                        </p>
                                    </td>
                                    <td
                                        class="ledger px-4 py-3 text-[0.58rem] text-signal-muted"
                                    >
                                        {{ entry.user }}
                                    </td>
                                    <td
                                        class="ledger px-4 py-3 text-right text-[0.62rem] text-signal-muted"
                                    >
                                        {{ entry.provider_cost }}
                                    </td>
                                    <td
                                        class="ledger px-4 py-3 text-right text-[0.62rem] text-signal"
                                    >
                                        {{ entry.customer_charge }}
                                    </td>
                                    <td
                                        class="ledger px-4 py-3 text-right text-[0.62rem] text-signal-muted"
                                    >
                                        {{ entry.tokens.toLocaleString() }}
                                    </td>
                                    <td
                                        class="ledger px-4 py-3 text-right text-[0.62rem] text-signal"
                                    >
                                        {{ entry.margin }}
                                    </td>
                                    <td class="px-4 py-3">
                                        <Link
                                            v-if="entry.id"
                                            :href="`/generations/${entry.id}`"
                                            class="focus-ring inline-flex text-signal-muted hover:text-signal"
                                            :aria-label="`Open ${entry.id}`"
                                        >
                                            <ArrowUpRight
                                                class="size-3.5"
                                                aria-hidden="true"
                                            />
                                        </Link>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <ul class="border border-line bg-paper lg:hidden">
                        <li
                            v-for="(entry, index) in recent"
                            :key="`${entry.id}-${index}`"
                            class="border-b border-line-soft p-4 last:border-0"
                        >
                            <div class="flex items-start justify-between gap-4">
                                <div class="min-w-0">
                                    <Link
                                        v-if="entry.id"
                                        :href="`/generations/${entry.id}`"
                                        class="line-clamp-2 font-display text-[1rem] leading-snug text-signal hover:text-signal-muted"
                                        >{{ entry.prompt }}</Link
                                    >
                                    <span
                                        v-else
                                        class="text-sm text-signal-muted"
                                        >Removed plate</span
                                    >
                                    <p
                                        class="ledger mt-2 text-[0.6rem] text-signal-faint"
                                    >
                                        {{ formatDateTime(entry.settled_at) }}
                                    </p>
                                </div>
                                <Link
                                    v-if="entry.id"
                                    :href="`/generations/${entry.id}`"
                                    class="focus-ring inline-flex shrink-0 text-signal-muted hover:text-signal"
                                    :aria-label="`Open ${entry.id}`"
                                >
                                    <ArrowUpRight
                                        class="size-4"
                                        aria-hidden="true"
                                    />
                                </Link>
                            </div>
                            <p
                                class="mt-3 font-mono text-[0.55rem] tracking-[0.1em] text-signal-faint uppercase"
                            >
                                Account · {{ entry.user ?? 'Unknown' }}
                            </p>
                            <dl
                                class="mt-2 grid grid-cols-2 gap-x-4 gap-y-2 font-mono text-[0.6rem] tabular-nums"
                            >
                                <div
                                    class="flex items-center justify-between gap-2 border-b border-line-soft pb-2"
                                >
                                    <dt
                                        class="text-[0.52rem] tracking-[0.1em] text-signal-faint uppercase"
                                    >
                                        Provider
                                    </dt>
                                    <dd class="text-signal-muted">
                                        {{ entry.provider_cost }}
                                    </dd>
                                </div>
                                <div
                                    class="flex items-center justify-between gap-2 border-b border-line-soft pb-2"
                                >
                                    <dt
                                        class="text-[0.52rem] tracking-[0.1em] text-signal-faint uppercase"
                                    >
                                        Charged
                                    </dt>
                                    <dd class="text-signal">
                                        {{ entry.customer_charge }}
                                    </dd>
                                </div>
                                <div
                                    class="flex items-center justify-between gap-2"
                                >
                                    <dt
                                        class="text-[0.52rem] tracking-[0.1em] text-signal-faint uppercase"
                                    >
                                        Tokens
                                    </dt>
                                    <dd class="text-signal-muted">
                                        {{ entry.tokens.toLocaleString() }}
                                    </dd>
                                </div>
                                <div
                                    class="flex items-center justify-between gap-2"
                                >
                                    <dt
                                        class="text-[0.52rem] tracking-[0.1em] text-signal-faint uppercase"
                                    >
                                        Margin
                                    </dt>
                                    <dd class="text-signal">
                                        {{ entry.margin }}
                                    </dd>
                                </div>
                            </dl>
                        </li>
                    </ul>
                </template>

                <p
                    v-else
                    class="border border-dashed border-line bg-paper px-5 py-6 text-sm leading-relaxed text-signal-muted"
                >
                    No settled charges yet. The reconciliation will fill once a
                    generation completes.
                </p>
            </section>

            <p
                class="max-w-2xl border-t border-line pt-5 text-[0.65rem] leading-relaxed text-signal-muted"
            >
                Reconciliation compares the Pixelforge usage report (grouped by
                UTC completion day) with Vellum's immutable charge ledger. The
                command
                <code class="ledger"
                    >php artisan pixelforge:reconcile-usage</code
                >
                runs hourly and can fail a deployment check with
                <code class="ledger">--fail-on-mismatch</code>.
            </p>
        </div>
    </div>
</template>
