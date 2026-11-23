<script setup lang="ts">
defineOptions({ inheritAttrs: false });

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

    <div class="min-h-svh overflow-x-clip bg-void text-document">
        <div
            class="mx-auto max-w-[1440px] space-y-12 px-5 py-10 sm:px-8 sm:py-12 lg:px-12 lg:py-16"
            :aria-busy="refreshing"
        >
            <header
                class="flex flex-col gap-8 border-b border-line pb-9 lg:flex-row lg:items-end lg:justify-between"
            >
                <div class="min-w-0">
                    <p class="vellum-meta flex items-center gap-3">
                        <ShieldCheck
                            class="size-3.5 text-document"
                            aria-hidden="true"
                        />
                        Reconciliation / operator
                    </p>
                    <h1
                        class="vellum-display mt-5 max-w-[18ch] text-[clamp(2.6rem,5.4vw,4.25rem)] text-document"
                    >
                        The studio
                        <span class="text-ash">books.</span>
                    </h1>
                </div>
                <div class="flex flex-wrap items-center gap-2">
                    <div
                        class="inline-flex border border-line bg-surface"
                        role="group"
                        aria-label="Reconciliation range"
                    >
                        <button
                            v-for="days in [7, 14, 30, 92]"
                            :key="days"
                            type="button"
                            class="focus-ring vellum-meta px-3 py-2 transition-colors motion-reduce:transition-none"
                            :class="
                                range.days === days
                                    ? 'bg-document text-void'
                                    : 'text-ash hover:bg-elevated hover:text-document'
                            "
                            :aria-pressed="range.days === days"
                            @click="loadDays(days)"
                        >
                            {{ days }}d
                        </button>
                    </div>
                    <button
                        type="button"
                        class="focus-ring vellum-meta inline-flex items-center gap-2 border border-line bg-surface px-3 py-2 text-ash transition-colors hover:border-document hover:text-document motion-reduce:transition-none"
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
                class="border border-l-2 border-line border-l-document bg-surface px-4 py-3"
                role="alert"
            >
                <div class="flex items-start gap-3">
                    <TriangleAlert
                        class="mt-0.5 size-4 shrink-0 text-document"
                        aria-hidden="true"
                    />
                    <div>
                        <p class="vellum-meta text-document">
                            Provider unavailable
                        </p>
                        <p class="mt-1 text-sm leading-relaxed text-document">
                            {{ error }}
                        </p>
                        <p class="mt-1 text-xs leading-relaxed text-ash">
                            Lifetime figures and settled charges below are still
                            from the local ledger.
                        </p>
                    </div>
                </div>
            </div>

            <section
                v-if="report"
                class="border border-line bg-surface"
                :class="
                    report.matches
                        ? 'border-l-2 border-l-document'
                        : 'border-l-4 border-dashed border-l-document'
                "
                :aria-label="`Reconciliation status: ${statusLabel}`"
            >
                <div
                    class="flex flex-col gap-5 p-5 sm:p-6 lg:flex-row lg:items-start lg:justify-between"
                >
                    <div class="flex min-w-0 items-start gap-3">
                        <CircleCheck
                            v-if="report.matches"
                            class="mt-0.5 size-5 shrink-0 text-document"
                            aria-hidden="true"
                        />
                        <TriangleAlert
                            v-else
                            class="mt-0.5 size-5 shrink-0 text-document"
                            aria-hidden="true"
                        />
                        <div class="min-w-0">
                            <p class="vellum-meta">
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
                                class="vellum-title mt-2 text-xl text-document sm:text-2xl"
                            >
                                {{
                                    report.matches
                                        ? 'Provider usage and the local ledger match exactly.'
                                        : `${report.differences.length} day(s) differ from Pixelforge.`
                                }}
                            </p>
                            <p
                                class="mt-2 max-w-2xl text-xs leading-relaxed text-ash"
                            >
                                {{
                                    report.matches
                                        ? 'Generations, token classes, and cost agree across the whole range.'
                                        : 'Investigate before closing the books: a missing charge or a late settlement will show here.'
                                }}
                            </p>
                        </div>
                    </div>
                    <p class="vellum-meta shrink-0">
                        {{ formatDate(report.start_date) }} —
                        {{ formatDate(report.end_date) }}
                    </p>
                </div>
            </section>

            <div
                v-else-if="!error"
                class="border border-dashed border-line bg-surface px-5 py-6"
                role="status"
            >
                <p class="vellum-meta">UNAVAILABLE / NO REPORT</p>
                <p class="mt-2 text-sm leading-relaxed text-document">
                    No reconciliation data is available for this range. Select
                    another range or refresh to request the provider report
                    again.
                </p>
            </div>

            <template v-if="report">
                <section aria-label="Totals comparison" class="space-y-4">
                    <div
                        class="flex flex-wrap items-end justify-between gap-3 border-b border-line pb-3"
                    >
                        <h2 class="vellum-title text-xl text-document">
                            Totals comparison
                        </h2>
                        <p class="vellum-meta">USD / exact</p>
                    </div>
                    <div
                        class="grid gap-px border border-line bg-line lg:grid-cols-2"
                    >
                        <div class="bg-surface p-5 sm:p-6">
                            <div
                                class="flex flex-wrap items-center justify-between gap-3"
                            >
                                <p class="text-sm text-document">
                                    Pixelforge says
                                </p>
                                <p class="vellum-meta">Provider</p>
                            </div>
                            <p
                                class="ledger mt-6 text-xl tracking-[-0.04em] text-document sm:text-2xl lg:text-3xl"
                            >
                                {{ report.provider.cost }}
                            </p>
                            <dl class="mt-6 grid grid-cols-2 gap-x-4 gap-y-3">
                                <div class="border-t border-edge pt-2">
                                    <dt class="vellum-meta">Generations</dt>
                                    <dd
                                        class="ledger mt-1 text-sm text-document"
                                    >
                                        {{
                                            report.provider.generations.toLocaleString()
                                        }}
                                    </dd>
                                </div>
                                <div class="border-t border-edge pt-2">
                                    <dt class="vellum-meta">Tokens</dt>
                                    <dd
                                        class="ledger mt-1 text-sm text-document"
                                    >
                                        {{
                                            report.provider.total_tokens.toLocaleString()
                                        }}
                                    </dd>
                                </div>
                                <div class="border-t border-edge pt-2">
                                    <dt class="vellum-meta">Input text</dt>
                                    <dd
                                        class="ledger mt-1 text-sm text-document"
                                    >
                                        {{
                                            report.provider.input_text_tokens.toLocaleString()
                                        }}
                                    </dd>
                                </div>
                                <div class="border-t border-edge pt-2">
                                    <dt class="vellum-meta">Input images</dt>
                                    <dd
                                        class="ledger mt-1 text-sm text-document"
                                    >
                                        {{
                                            report.provider.input_image_tokens.toLocaleString()
                                        }}
                                    </dd>
                                </div>
                                <div class="border-t border-edge pt-2">
                                    <dt class="vellum-meta">Output</dt>
                                    <dd
                                        class="ledger mt-1 text-sm text-document"
                                    >
                                        {{
                                            report.provider.output_tokens.toLocaleString()
                                        }}
                                    </dd>
                                </div>
                                <div class="border-t border-edge pt-2">
                                    <dt class="vellum-meta">Reasoning</dt>
                                    <dd
                                        class="ledger mt-1 text-sm text-document"
                                    >
                                        {{
                                            report.provider.reasoning_tokens.toLocaleString()
                                        }}
                                    </dd>
                                </div>
                            </dl>
                        </div>

                        <div class="bg-surface p-5 sm:p-6">
                            <div
                                class="flex flex-wrap items-center justify-between gap-3"
                            >
                                <p class="text-sm text-document">
                                    Vellum ledger says
                                </p>
                                <p class="vellum-meta">Local</p>
                            </div>
                            <p
                                class="ledger mt-6 text-xl tracking-[-0.04em] text-document sm:text-2xl lg:text-3xl"
                            >
                                {{ report.local.cost }}
                            </p>
                            <dl class="mt-6 grid grid-cols-2 gap-x-4 gap-y-3">
                                <div class="border-t border-edge pt-2">
                                    <dt class="vellum-meta">Generations</dt>
                                    <dd
                                        class="ledger mt-1 text-sm text-document"
                                    >
                                        {{
                                            report.local.generations.toLocaleString()
                                        }}
                                    </dd>
                                </div>
                                <div class="border-t border-edge pt-2">
                                    <dt class="vellum-meta">Tokens</dt>
                                    <dd
                                        class="ledger mt-1 text-sm text-document"
                                    >
                                        {{
                                            report.local.total_tokens.toLocaleString()
                                        }}
                                    </dd>
                                </div>
                                <div class="border-t border-edge pt-2">
                                    <dt class="vellum-meta">Input text</dt>
                                    <dd
                                        class="ledger mt-1 text-sm text-document"
                                    >
                                        {{
                                            report.local.input_text_tokens.toLocaleString()
                                        }}
                                    </dd>
                                </div>
                                <div class="border-t border-edge pt-2">
                                    <dt class="vellum-meta">Input images</dt>
                                    <dd
                                        class="ledger mt-1 text-sm text-document"
                                    >
                                        {{
                                            report.local.input_image_tokens.toLocaleString()
                                        }}
                                    </dd>
                                </div>
                                <div class="border-t border-edge pt-2">
                                    <dt class="vellum-meta">Output</dt>
                                    <dd
                                        class="ledger mt-1 text-sm text-document"
                                    >
                                        {{
                                            report.local.output_tokens.toLocaleString()
                                        }}
                                    </dd>
                                </div>
                                <div class="border-t border-edge pt-2">
                                    <dt class="vellum-meta">Reasoning</dt>
                                    <dd
                                        class="ledger mt-1 text-sm text-document"
                                    >
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
                            <p class="vellum-meta">Day by day</p>
                            <h2
                                id="differences-title"
                                class="vellum-title mt-2 text-2xl text-document sm:text-[1.9rem]"
                            >
                                {{ showAllDays ? 'Every day' : 'Differences' }}
                            </h2>
                        </div>
                        <button
                            type="button"
                            class="focus-ring vellum-meta pb-1 text-document transition-colors hover:text-ash motion-reduce:transition-none"
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
                        class="flex items-start gap-3 border border-l-2 border-line border-l-document bg-surface px-5 py-6"
                    >
                        <CircleCheck
                            class="mt-0.5 size-4 shrink-0 text-document"
                            aria-hidden="true"
                        />
                        <div>
                            <p class="vellum-meta">MATCH / NO ROWS</p>
                            <p
                                class="mt-1 text-sm leading-relaxed text-document"
                            >
                                {{ emptyDifferencesMessage }}
                            </p>
                        </div>
                    </div>

                    <template v-else>
                        <div
                            class="focus-ring overflow-x-auto border border-line bg-surface"
                            role="region"
                            aria-label="Per-day reconciliation table, scroll horizontally"
                            tabindex="0"
                        >
                            <table
                                class="w-full min-w-[760px] border-collapse text-left"
                            >
                                <caption class="sr-only">
                                    Per-day provider usage versus the local
                                    ledger. Deltas are local minus provider.
                                </caption>
                                <thead>
                                    <tr
                                        class="vellum-meta border-b border-line"
                                    >
                                        <th
                                            scope="col"
                                            class="px-3 py-3 font-normal"
                                        >
                                            Date
                                        </th>
                                        <th
                                            scope="col"
                                            class="px-3 py-3 font-normal"
                                        >
                                            Status
                                        </th>
                                        <th
                                            scope="col"
                                            class="px-3 py-3 text-right font-normal"
                                        >
                                            Provider
                                        </th>
                                        <th
                                            scope="col"
                                            class="px-3 py-3 text-right font-normal"
                                        >
                                            Local
                                        </th>
                                        <th
                                            scope="col"
                                            class="px-3 py-3 text-right font-normal"
                                        >
                                            Δ cost
                                        </th>
                                        <th
                                            scope="col"
                                            class="px-3 py-3 text-right font-normal"
                                        >
                                            Δ tokens
                                        </th>
                                        <th
                                            scope="col"
                                            class="px-3 py-3 text-right font-normal"
                                        >
                                            Δ gens
                                        </th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr
                                        v-for="day in daysToShow"
                                        :key="day.date"
                                        class="border-b border-edge last:border-0"
                                    >
                                        <th
                                            scope="row"
                                            class="sticky left-0 border-l-2 bg-surface px-3 py-2.5 text-left font-normal"
                                            :class="
                                                day.matches
                                                    ? 'border-l-edge'
                                                    : 'border-l-document'
                                            "
                                        >
                                            <span
                                                class="ledger text-sm text-document"
                                                >{{ day.date }}</span
                                            >
                                        </th>
                                        <td class="px-3 py-2.5">
                                            <span
                                                class="vellum-meta inline-flex items-center gap-1.5 whitespace-nowrap"
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
                                                            ? 'text-ash'
                                                            : 'text-document'
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
                                            class="ledger px-3 py-2.5 text-right text-xs text-ash"
                                        >
                                            {{
                                                costCell(
                                                    day.provider.cost_nanos,
                                                )
                                            }}
                                        </td>
                                        <td
                                            class="ledger px-3 py-2.5 text-right text-xs text-ash"
                                        >
                                            {{ costCell(day.local.cost_nanos) }}
                                        </td>
                                        <td
                                            class="ledger px-3 py-2.5 text-right text-xs text-document"
                                        >
                                            {{
                                                deltaCostCell(
                                                    day.cost_difference_nanos,
                                                )
                                            }}
                                        </td>
                                        <td
                                            class="ledger px-3 py-2.5 text-right text-xs text-document"
                                        >
                                            {{ day.token_difference }}
                                        </td>
                                        <td
                                            class="ledger px-3 py-2.5 text-right text-xs text-document"
                                        >
                                            {{ day.generation_difference }}
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                        <p class="vellum-meta">
                            Deltas are local minus provider.
                        </p>
                    </template>
                </section>
            </template>

            <section aria-labelledby="lifetime-title" class="space-y-4">
                <div
                    class="flex flex-wrap items-end justify-between gap-3 border-b border-line pb-3"
                >
                    <h2
                        id="lifetime-title"
                        class="vellum-title text-xl text-document"
                    >
                        Lifetime ledger
                    </h2>
                    <p class="vellum-meta">Settled / all time</p>
                </div>
                <div
                    class="grid grid-cols-2 gap-px border border-line bg-line lg:grid-cols-4"
                >
                    <div class="bg-document p-5 text-void sm:p-6">
                        <p class="vellum-meta text-void/70">Lifetime margin</p>
                        <p
                            class="ledger mt-6 text-xl tracking-[-0.04em] sm:text-2xl lg:text-3xl"
                        >
                            {{ lifetime.margin }}
                        </p>
                        <p class="mt-3 text-xs leading-relaxed text-void/80">
                            {{ marginPercent }} of provider cost
                        </p>
                        <p class="vellum-meta mt-1 text-void/60">
                            margin ÷ provider cost
                        </p>
                    </div>
                    <div class="bg-surface p-5 sm:p-6">
                        <p class="text-sm text-document">Provider cost</p>
                        <p
                            class="ledger mt-6 text-xl tracking-[-0.03em] text-document sm:text-2xl lg:text-3xl"
                        >
                            {{ lifetime.provider_cost }}
                        </p>
                        <p class="mt-3 text-xs leading-relaxed text-ash">
                            lifetime, settled
                        </p>
                    </div>
                    <div class="bg-surface p-5 sm:p-6">
                        <p class="text-sm text-document">Customer charges</p>
                        <p
                            class="ledger mt-6 text-xl tracking-[-0.03em] text-document sm:text-2xl lg:text-3xl"
                        >
                            {{ lifetime.customer_charge }}
                        </p>
                        <p class="mt-3 text-xs leading-relaxed text-ash">
                            {{ lifetime.charged_generations.toLocaleString() }}
                            charges
                        </p>
                    </div>
                    <div class="bg-surface p-5 sm:p-6">
                        <p class="text-sm text-document">Studio</p>
                        <p
                            class="ledger mt-6 text-xl tracking-[-0.03em] text-document sm:text-2xl lg:text-3xl"
                        >
                            {{ lifetime.users.toLocaleString() }}
                        </p>
                        <p class="mt-3 text-xs leading-relaxed text-ash">
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
                        <p class="vellum-meta">Immutable records</p>
                        <h2
                            id="operator-recent"
                            class="vellum-title mt-2 text-2xl text-document sm:text-[1.9rem]"
                        >
                            Latest settlements
                        </h2>
                    </div>
                    <p class="vellum-meta flex items-center gap-1.5">
                        <Clock3 class="size-3" aria-hidden="true" />
                        Newest first
                    </p>
                </div>

                <template v-if="recent.length > 0">
                    <div
                        class="focus-ring overflow-x-auto border border-line bg-surface"
                        role="region"
                        aria-label="Latest settlements table, scroll horizontally"
                        tabindex="0"
                    >
                        <table
                            class="w-full min-w-[780px] border-collapse text-left"
                        >
                            <caption class="sr-only">
                                Latest immutable settlements with plate,
                                account, provider cost, customer charge, tokens
                                and margin.
                            </caption>
                            <thead>
                                <tr class="vellum-meta border-b border-line">
                                    <th
                                        scope="col"
                                        class="px-4 py-3 font-normal"
                                    >
                                        Plate
                                    </th>
                                    <th
                                        scope="col"
                                        class="px-4 py-3 font-normal"
                                    >
                                        Account
                                    </th>
                                    <th
                                        scope="col"
                                        class="px-4 py-3 text-right font-normal"
                                    >
                                        Provider
                                    </th>
                                    <th
                                        scope="col"
                                        class="px-4 py-3 text-right font-normal"
                                    >
                                        Charged
                                    </th>
                                    <th
                                        scope="col"
                                        class="px-4 py-3 text-right font-normal"
                                    >
                                        Tokens
                                    </th>
                                    <th
                                        scope="col"
                                        class="px-4 py-3 text-right font-normal"
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
                                    class="border-b border-edge transition-colors last:border-0 hover:bg-elevated motion-reduce:transition-none"
                                >
                                    <td class="max-w-[24rem] px-4 py-3">
                                        <Link
                                            v-if="entry.id"
                                            :href="`/generations/${entry.id}`"
                                            class="focus-ring line-clamp-1 font-display text-[0.95rem] text-document hover:text-ash"
                                            >{{ entry.prompt }}</Link
                                        >
                                        <span v-else class="text-ash"
                                            >Removed plate</span
                                        >
                                        <p
                                            class="ledger mt-1 text-[0.65rem] text-ash"
                                        >
                                            {{
                                                formatDateTime(entry.settled_at)
                                            }}
                                        </p>
                                    </td>
                                    <td
                                        class="ledger px-4 py-3 text-xs text-ash"
                                    >
                                        {{ entry.user }}
                                    </td>
                                    <td
                                        class="ledger px-4 py-3 text-right text-xs text-ash"
                                    >
                                        {{ entry.provider_cost }}
                                    </td>
                                    <td
                                        class="ledger px-4 py-3 text-right text-xs text-document"
                                    >
                                        {{ entry.customer_charge }}
                                    </td>
                                    <td
                                        class="ledger px-4 py-3 text-right text-xs text-ash"
                                    >
                                        {{ entry.tokens.toLocaleString() }}
                                    </td>
                                    <td
                                        class="ledger px-4 py-3 text-right text-xs text-document"
                                    >
                                        {{ entry.margin }}
                                    </td>
                                    <td class="px-4 py-3">
                                        <Link
                                            v-if="entry.id"
                                            :href="`/generations/${entry.id}`"
                                            class="focus-ring inline-flex text-ash hover:text-document"
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
                </template>

                <p
                    v-else
                    class="border border-dashed border-line bg-surface px-5 py-6 text-sm leading-relaxed text-ash"
                >
                    No settled charges yet. The reconciliation will fill once a
                    generation completes.
                </p>
            </section>

            <p
                class="max-w-2xl border-t border-line pt-5 text-xs leading-relaxed text-ash"
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
