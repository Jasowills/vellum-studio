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
    recent: Array<{
        id: string | null;
        prompt: string | null;
        user: string | null;
        provider_cost: string;
        customer_charge: string;
        margin: string;
        tokens: number;
        settled_at: string | null;
    }>;
}>();

const showAllDays = ref(false);
const refreshing = ref(false);

const daysToShow = computed(() => {
    if (!props.report) {
        return [];
    }

    return showAllDays.value ? props.report.days : props.report.differences;
});

const marginPercent = computed(() => {
    const provider = Number.parseFloat(
        props.lifetime.customer_charge.replace(/[^0-9.]/g, ''),
    );
    const margin = Number.parseFloat(
        props.lifetime.margin.replace(/[^0-9.]/g, ''),
    );

    if (!Number.isFinite(provider) || provider <= 0) {
        return '—';
    }

    return `${((margin / provider) * 100).toFixed(1)}%`;
});

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

    <div class="space-y-10">
        <header
            class="flex flex-col justify-between gap-6 border-b border-line pb-8 lg:flex-row lg:items-end"
        >
            <div>
                <p class="eyebrow flex items-center gap-3">
                    <ShieldCheck
                        class="size-3.5 text-vermilion"
                        aria-hidden="true"
                    />
                    Operator / reconciliation
                </p>
                <h1
                    class="display-title mt-5 max-w-[16ch] text-[clamp(2.8rem,5.2vw,4.4rem)] text-ink"
                >
                    The studio
                    <em class="text-vermilion">books.</em>
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
                        class="focus-ring px-3 py-2 font-mono text-[0.58rem] tracking-[0.08em] uppercase transition-colors"
                        :class="
                            range.days === days
                                ? 'bg-ink text-paper'
                                : 'text-ink-soft hover:bg-paper-deep hover:text-ink'
                        "
                        :aria-pressed="range.days === days"
                        @click="loadDays(days)"
                    >
                        {{ days }}d
                    </button>
                </div>
                <button
                    type="button"
                    class="focus-ring inline-flex items-center gap-2 border border-line bg-paper px-3 py-2 font-mono text-[0.58rem] tracking-[0.08em] text-ink-soft uppercase hover:border-ink hover:text-ink"
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
            class="flex items-start gap-3 border border-vermilion/30 bg-vermilion-soft px-4 py-3 text-sm text-vermilion"
            role="alert"
        >
            <TriangleAlert class="mt-0.5 size-4 shrink-0" aria-hidden="true" />
            <p>{{ error }}</p>
        </div>

        <section
            v-if="report"
            class="border p-5 sm:p-6"
            :class="
                report.matches
                    ? 'border-moss/30 bg-moss-soft'
                    : 'border-vermilion/30 bg-vermilion-soft'
            "
            aria-live="polite"
        >
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div class="flex items-start gap-3">
                    <CircleCheck
                        v-if="report.matches"
                        class="mt-0.5 size-5 text-moss"
                        aria-hidden="true"
                    />
                    <TriangleAlert
                        v-else
                        class="mt-0.5 size-5 text-vermilion"
                        aria-hidden="true"
                    />
                    <div>
                        <p
                            class="font-display text-xl tracking-[-0.02em]"
                            :class="
                                report.matches ? 'text-moss' : 'text-vermilion'
                            "
                        >
                            {{
                                report.matches
                                    ? 'Provider usage and the local ledger match exactly.'
                                    : `${report.differences.length} day(s) differ from Pixelforge.`
                            }}
                        </p>
                        <p class="mt-1 text-xs leading-relaxed text-ink-soft">
                            {{
                                report.matches
                                    ? 'Generations, token classes, and cost agree across the whole range.'
                                    : 'Investigate before closing the books: a missing charge or a late settlement will show here.'
                            }}
                        </p>
                    </div>
                </div>
                <p
                    class="font-mono text-[0.58rem] tracking-[0.1em] text-ink-soft uppercase"
                >
                    {{ formatDate(report.start_date) }} —
                    {{ formatDate(report.end_date) }}
                </p>
            </div>
        </section>

        <template v-if="report">
            <section
                class="grid gap-5 md:grid-cols-2"
                aria-label="Totals comparison"
            >
                <div class="paper-panel p-5 sm:p-6">
                    <p class="eyebrow">Pixelforge says</p>
                    <p
                        class="mt-4 font-display text-[2.5rem] leading-none tracking-[-0.04em] text-ink"
                    >
                        {{ report.provider.cost }}
                    </p>
                    <dl
                        class="mt-5 grid grid-cols-2 gap-3 font-mono text-[0.62rem]"
                    >
                        <div>
                            <dt class="text-ink-soft">Generations</dt>
                            <dd class="mt-0.5 text-ink">
                                {{
                                    report.provider.generations.toLocaleString()
                                }}
                            </dd>
                        </div>
                        <div>
                            <dt class="text-ink-soft">Tokens</dt>
                            <dd class="mt-0.5 text-ink">
                                {{
                                    report.provider.total_tokens.toLocaleString()
                                }}
                            </dd>
                        </div>
                        <div>
                            <dt class="text-ink-soft">Input text</dt>
                            <dd class="mt-0.5 text-ink">
                                {{
                                    report.provider.input_text_tokens.toLocaleString()
                                }}
                            </dd>
                        </div>
                        <div>
                            <dt class="text-ink-soft">Input images</dt>
                            <dd class="mt-0.5 text-ink">
                                {{
                                    report.provider.input_image_tokens.toLocaleString()
                                }}
                            </dd>
                        </div>
                        <div>
                            <dt class="text-ink-soft">Output</dt>
                            <dd class="mt-0.5 text-ink">
                                {{
                                    report.provider.output_tokens.toLocaleString()
                                }}
                            </dd>
                        </div>
                        <div>
                            <dt class="text-ink-soft">Reasoning</dt>
                            <dd class="mt-0.5 text-ink">
                                {{
                                    report.provider.reasoning_tokens.toLocaleString()
                                }}
                            </dd>
                        </div>
                    </dl>
                </div>

                <div class="paper-panel p-5 sm:p-6">
                    <p class="eyebrow">Vellum ledger says</p>
                    <p
                        class="mt-4 font-display text-[2.5rem] leading-none tracking-[-0.04em] text-ink"
                    >
                        {{ report.local.cost }}
                    </p>
                    <dl
                        class="mt-5 grid grid-cols-2 gap-3 font-mono text-[0.62rem]"
                    >
                        <div>
                            <dt class="text-ink-soft">Generations</dt>
                            <dd class="mt-0.5 text-ink">
                                {{ report.local.generations.toLocaleString() }}
                            </dd>
                        </div>
                        <div>
                            <dt class="text-ink-soft">Tokens</dt>
                            <dd class="mt-0.5 text-ink">
                                {{ report.local.total_tokens.toLocaleString() }}
                            </dd>
                        </div>
                        <div>
                            <dt class="text-ink-soft">Input text</dt>
                            <dd class="mt-0.5 text-ink">
                                {{
                                    report.local.input_text_tokens.toLocaleString()
                                }}
                            </dd>
                        </div>
                        <div>
                            <dt class="text-ink-soft">Input images</dt>
                            <dd class="mt-0.5 text-ink">
                                {{
                                    report.local.input_image_tokens.toLocaleString()
                                }}
                            </dd>
                        </div>
                        <div>
                            <dt class="text-ink-soft">Output</dt>
                            <dd class="mt-0.5 text-ink">
                                {{
                                    report.local.output_tokens.toLocaleString()
                                }}
                            </dd>
                        </div>
                        <div>
                            <dt class="text-ink-soft">Reasoning</dt>
                            <dd class="mt-0.5 text-ink">
                                {{
                                    report.local.reasoning_tokens.toLocaleString()
                                }}
                            </dd>
                        </div>
                    </dl>
                </div>
            </section>

            <section class="space-y-4" aria-labelledby="differences-title">
                <div
                    class="flex flex-wrap items-end justify-between gap-3 border-b border-line pb-4"
                >
                    <div>
                        <p class="eyebrow">Day by day</p>
                        <h2
                            id="differences-title"
                            class="mt-2 font-display text-3xl tracking-[-0.03em] text-ink"
                        >
                            {{ showAllDays ? 'Every day' : 'Differences' }}
                        </h2>
                    </div>
                    <button
                        type="button"
                        class="focus-ring font-mono text-[0.58rem] tracking-[0.1em] text-ink-soft uppercase hover:text-vermilion"
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
                    class="flex items-center gap-3 border border-moss/25 bg-moss-soft px-5 py-6 text-sm text-moss"
                >
                    <CircleCheck class="size-4 shrink-0" aria-hidden="true" />
                    No differences in this range. Every provider day matches the
                    ledger.
                </div>

                <div v-else class="overflow-x-auto border border-line bg-paper">
                    <table
                        class="w-full border-collapse text-left font-mono text-[0.65rem]"
                    >
                        <thead>
                            <tr
                                class="border-b border-line text-[0.52rem] tracking-[0.12em] text-ink-soft uppercase"
                            >
                                <th class="px-4 py-3 font-medium">Date</th>
                                <th class="px-4 py-3 text-right font-medium">
                                    Provider
                                </th>
                                <th class="px-4 py-3 text-right font-medium">
                                    Local
                                </th>
                                <th class="px-4 py-3 text-right font-medium">
                                    Δ cost
                                </th>
                                <th class="px-4 py-3 text-right font-medium">
                                    Δ tokens
                                </th>
                                <th class="px-4 py-3 text-right font-medium">
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
                                <td class="px-4 py-2.5">
                                    <span
                                        class="flex items-center gap-2 text-ink"
                                    >
                                        <span
                                            class="status-dot"
                                            :class="
                                                day.matches
                                                    ? 'bg-moss'
                                                    : 'bg-vermilion'
                                            "
                                            aria-hidden="true"
                                        />
                                        {{ day.date }}
                                    </span>
                                </td>
                                <td
                                    class="px-4 py-2.5 text-right text-ink-soft"
                                >
                                    {{
                                        day.provider.cost_nanos === 0
                                            ? '—'
                                            : `$${(day.provider.cost_nanos / 1e9).toFixed(9)}`
                                    }}
                                </td>
                                <td
                                    class="px-4 py-2.5 text-right text-ink-soft"
                                >
                                    {{
                                        day.local.cost_nanos === 0
                                            ? '—'
                                            : `$${(day.local.cost_nanos / 1e9).toFixed(9)}`
                                    }}
                                </td>
                                <td
                                    class="px-4 py-2.5 text-right"
                                    :class="
                                        day.cost_difference_nanos === 0
                                            ? 'text-ink-soft'
                                            : 'text-vermilion'
                                    "
                                >
                                    {{
                                        day.cost_difference_nanos === 0
                                            ? '0'
                                            : (
                                                  day.cost_difference_nanos /
                                                  1e9
                                              ).toFixed(9)
                                    }}
                                </td>
                                <td
                                    class="px-4 py-2.5 text-right"
                                    :class="
                                        day.token_difference === 0
                                            ? 'text-ink-soft'
                                            : 'text-vermilion'
                                    "
                                >
                                    {{ day.token_difference }}
                                </td>
                                <td
                                    class="px-4 py-2.5 text-right"
                                    :class="
                                        day.generation_difference === 0
                                            ? 'text-ink-soft'
                                            : 'text-vermilion'
                                    "
                                >
                                    {{ day.generation_difference }}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>
        </template>

        <section
            class="grid gap-px border border-line bg-line sm:grid-cols-2 lg:grid-cols-4"
            aria-label="Lifetime ledger"
        >
            <div class="bg-ink p-5 text-paper sm:p-6">
                <p
                    class="font-mono text-[0.58rem] tracking-[0.14em] text-paper/50 uppercase"
                >
                    Lifetime margin
                </p>
                <p
                    class="mt-4 font-display text-4xl tracking-[-0.035em] text-paper"
                >
                    {{ lifetime.margin }}
                </p>
                <p
                    class="mt-1 font-mono text-[0.58rem] tracking-[0.08em] text-paper/50 uppercase"
                >
                    {{ marginPercent }} of provider cost
                </p>
            </div>
            <div class="bg-paper p-5 sm:p-6">
                <p class="eyebrow">Provider cost</p>
                <p
                    class="mt-4 font-display text-3xl tracking-[-0.03em] text-ink"
                >
                    {{ lifetime.provider_cost }}
                </p>
                <p
                    class="mt-1 font-mono text-[0.58rem] tracking-[0.08em] text-ink-soft uppercase"
                >
                    lifetime, settled
                </p>
            </div>
            <div class="bg-paper p-5 sm:p-6">
                <p class="eyebrow">Customer charges</p>
                <p
                    class="mt-4 font-display text-3xl tracking-[-0.03em] text-ink"
                >
                    {{ lifetime.customer_charge }}
                </p>
                <p
                    class="mt-1 font-mono text-[0.58rem] tracking-[0.08em] text-ink-soft uppercase"
                >
                    {{ lifetime.charged_generations.toLocaleString() }} charges
                </p>
            </div>
            <div class="bg-paper p-5 sm:p-6">
                <p class="eyebrow">Studio</p>
                <p
                    class="mt-4 font-display text-3xl tracking-[-0.03em] text-ink"
                >
                    {{ lifetime.users.toLocaleString() }}
                </p>
                <p
                    class="mt-1 font-mono text-[0.58rem] tracking-[0.08em] text-ink-soft uppercase"
                >
                    accounts · {{ lifetime.tokens.toLocaleString() }} tokens
                </p>
            </div>
        </section>

        <section class="space-y-4" aria-labelledby="operator-recent">
            <div
                class="flex flex-wrap items-end justify-between gap-3 border-b border-line pb-4"
            >
                <div>
                    <p class="eyebrow">Immutable records</p>
                    <h2
                        id="operator-recent"
                        class="mt-2 font-display text-3xl tracking-[-0.03em] text-ink"
                    >
                        Latest settlements
                    </h2>
                </div>
                <p
                    class="flex items-center gap-1.5 font-mono text-[0.55rem] tracking-[0.1em] text-ink-soft uppercase"
                >
                    <Clock3 class="size-3" aria-hidden="true" />
                    Newest first
                </p>
            </div>

            <div
                v-if="recent.length > 0"
                class="overflow-x-auto border border-line bg-paper"
            >
                <table class="w-full border-collapse text-left text-sm">
                    <thead>
                        <tr
                            class="border-b border-line font-mono text-[0.52rem] tracking-[0.12em] text-ink-soft uppercase"
                        >
                            <th class="px-4 py-3 font-medium">Plate</th>
                            <th
                                class="hidden px-4 py-3 font-medium md:table-cell"
                            >
                                Account
                            </th>
                            <th class="px-4 py-3 text-right font-medium">
                                Provider
                            </th>
                            <th class="px-4 py-3 text-right font-medium">
                                Charged
                            </th>
                            <th class="px-4 py-3 text-right font-medium">
                                Margin
                            </th>
                            <th class="w-10 px-4 py-3">
                                <span class="sr-only">Open</span>
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="(entry, index) in recent"
                            :key="`${entry.id}-${index}`"
                            class="border-b border-line-soft last:border-0 hover:bg-background/50"
                        >
                            <td class="max-w-[24rem] px-4 py-3">
                                <Link
                                    v-if="entry.id"
                                    :href="`/generations/${entry.id}`"
                                    class="line-clamp-1 font-display text-[0.95rem] text-ink hover:text-vermilion"
                                    >{{ entry.prompt }}</Link
                                >
                                <span v-else class="text-ink-soft"
                                    >Removed plate</span
                                >
                                <p
                                    class="mt-0.5 font-mono text-[0.55rem] text-ink-soft"
                                >
                                    {{ formatDateTime(entry.settled_at) }}
                                </p>
                            </td>
                            <td
                                class="hidden px-4 py-3 font-mono text-[0.58rem] text-ink-soft md:table-cell"
                            >
                                {{ entry.user }}
                            </td>
                            <td
                                class="px-4 py-3 text-right font-mono text-[0.62rem] text-ink-soft"
                            >
                                {{ entry.provider_cost }}
                            </td>
                            <td
                                class="px-4 py-3 text-right font-mono text-[0.62rem] text-ink"
                            >
                                {{ entry.customer_charge }}
                            </td>
                            <td
                                class="px-4 py-3 text-right font-mono text-[0.62rem] text-moss"
                            >
                                {{ entry.margin }}
                            </td>
                            <td class="px-4 py-3">
                                <Link
                                    v-if="entry.id"
                                    :href="`/generations/${entry.id}`"
                                    class="focus-ring inline-flex text-ink-soft hover:text-vermilion"
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
            <p v-else class="text-sm text-ink-soft">
                No settled charges yet. The reconciliation will fill once a
                generation completes.
            </p>
        </section>

        <p class="max-w-2xl text-[0.65rem] leading-relaxed text-ink-soft">
            Reconciliation compares the Pixelforge usage report (grouped by UTC
            completion day) with Vellum's immutable charge ledger. The command
            <code class="ledger">php artisan pixelforge:reconcile-usage</code>
            runs hourly and can fail a deployment check with
            <code class="ledger">--fail-on-mismatch</code>.
        </p>
    </div>
</template>
