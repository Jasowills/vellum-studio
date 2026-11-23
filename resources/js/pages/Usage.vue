<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { ArrowRight, Coins, Images, Receipt, TriangleAlert } from '@lucide/vue';
import { computed } from 'vue';
import { formatDate, formatDateTime } from '@/lib/vellum';

const props = defineProps<{
    summary: {
        total: number;
        succeeded: number;
        failed: number;
        active: number;
        charged: number;
        charge_nanos: number;
        charge: string;
        tokens: number;
    };
    days: Array<{
        date: string;
        label: string;
        total: number;
        succeeded: number;
        failed: number;
        active: number;
        charged: number;
        charge_nanos: number;
        charge: string;
        tokens: number;
    }>;
    recent: Array<{
        id: string;
        prompt: string;
        charge_nanos: number;
        charge: string;
        tokens: number;
        settled_at: string | null;
    }>;
    range: { start: string; end: string };
}>();

const maxDaily = computed(() =>
    Math.max(1, ...props.days.map((day) => day.total)),
);

const busiest = computed(() =>
    props.days.reduce(
        (best, day) => (day.total > best.total ? day : best),
        props.days[0] ?? { label: '—', total: 0, date: '' },
    ),
);

const hasActivity = computed(() => props.summary.total > 0);

function barHeight(value: number): string {
    if (value === 0) {
        return '2px';
    }

    return `${Math.max(6, Math.round((value / maxDaily.value) * 100))}%`;
}
</script>

<template>
    <Head title="Usage" />

    <div class="space-y-10">
        <header
            class="flex flex-col justify-between gap-6 border-b border-line pb-8 lg:flex-row lg:items-end"
        >
            <div>
                <p class="eyebrow flex items-center gap-3">
                    <span class="h-px w-8 bg-vermilion" aria-hidden="true" />
                    Usage / the last thirty days
                </p>
                <h1
                    class="display-title mt-5 max-w-[14ch] text-[clamp(2.8rem,5.2vw,4.4rem)] text-ink"
                >
                    The
                    <em class="text-vermilion">ledger.</em>
                </h1>
            </div>
            <div class="max-w-sm space-y-2 lg:text-right">
                <p class="text-sm leading-relaxed text-ink-soft">
                    Every settled charge, exactly as recorded at completion.
                    Failed generations never appear as charges.
                </p>
                <p
                    class="font-mono text-[0.6rem] tracking-[0.12em] text-ink-soft uppercase"
                >
                    {{ formatDate(range.start) }} — {{ formatDate(range.end) }}
                </p>
            </div>
        </header>

        <section
            class="grid gap-px border border-line bg-line sm:grid-cols-2 lg:grid-cols-4"
            aria-label="Lifetime summary"
        >
            <div class="bg-paper p-5 sm:p-6">
                <div class="flex items-center justify-between gap-3">
                    <p class="eyebrow">Images made</p>
                    <Images class="size-4 text-ink-soft" aria-hidden="true" />
                </div>
                <p
                    class="mt-4 font-display text-4xl tracking-[-0.035em] text-ink"
                >
                    {{ summary.succeeded.toLocaleString() }}
                </p>
                <p
                    class="mt-1 font-mono text-[0.58rem] tracking-[0.08em] text-ink-soft uppercase"
                >
                    {{ summary.total.toLocaleString() }} attempts
                </p>
            </div>
            <div class="bg-paper p-5 sm:p-6">
                <div class="flex items-center justify-between gap-3">
                    <p class="eyebrow">Charged</p>
                    <Coins class="size-4 text-ink-soft" aria-hidden="true" />
                </div>
                <p
                    class="mt-4 font-display text-4xl tracking-[-0.035em] text-vermilion"
                >
                    {{ summary.charge }}
                </p>
                <p
                    class="mt-1 font-mono text-[0.58rem] tracking-[0.08em] text-ink-soft uppercase"
                >
                    {{ summary.charged.toLocaleString() }} settled
                    {{ summary.charged === 1 ? 'charge' : 'charges' }}
                </p>
            </div>
            <div class="bg-paper p-5 sm:p-6">
                <div class="flex items-center justify-between gap-3">
                    <p class="eyebrow">Tokens billed</p>
                    <Receipt class="size-4 text-ink-soft" aria-hidden="true" />
                </div>
                <p
                    class="mt-4 font-display text-4xl tracking-[-0.035em] text-ink"
                >
                    {{ summary.tokens.toLocaleString() }}
                </p>
                <p
                    class="mt-1 font-mono text-[0.58rem] tracking-[0.08em] text-ink-soft uppercase"
                >
                    all token classes
                </p>
            </div>
            <div class="bg-paper p-5 sm:p-6">
                <div class="flex items-center justify-between gap-3">
                    <p class="eyebrow">Failures</p>
                    <TriangleAlert
                        class="size-4 text-ink-soft"
                        aria-hidden="true"
                    />
                </div>
                <p
                    class="mt-4 font-display text-4xl tracking-[-0.035em] text-ink"
                >
                    {{ summary.failed.toLocaleString() }}
                </p>
                <p
                    class="mt-1 font-mono text-[0.58rem] tracking-[0.08em] text-ink-soft uppercase"
                >
                    always free
                </p>
            </div>
        </section>

        <section class="paper-panel p-5 sm:p-7" aria-labelledby="daily-title">
            <div class="flex flex-wrap items-end justify-between gap-4">
                <div>
                    <p class="eyebrow">Daily activity</p>
                    <h2
                        id="daily-title"
                        class="mt-2 font-display text-2xl tracking-[-0.02em] text-ink"
                    >
                        Thirty days of work
                    </h2>
                </div>
                <p
                    class="font-mono text-[0.58rem] tracking-[0.1em] text-ink-soft uppercase"
                >
                    Peak {{ busiest.total }} ·
                    {{ busiest.label }}
                </p>
            </div>

            <div
                v-if="hasActivity"
                class="mt-7 flex h-44 items-end gap-[3px] sm:gap-1.5"
                role="img"
                :aria-label="`Daily generation counts for the last thirty days. Peak ${busiest.total} on ${busiest.label}.`"
            >
                <div
                    v-for="day in days"
                    :key="day.date"
                    class="group relative flex h-full flex-1 flex-col justify-end"
                >
                    <div
                        class="w-full transition-colors"
                        :class="
                            day.total === 0
                                ? 'bg-line-soft'
                                : day.failed > 0 && day.succeeded === 0
                                  ? 'bg-vermilion/55'
                                  : 'bg-ink/80 group-hover:bg-vermilion'
                        "
                        :style="{ height: barHeight(day.total) }"
                    />
                    <span
                        class="pointer-events-none absolute -top-1 left-1/2 z-10 hidden -translate-x-1/2 -translate-y-full border border-line bg-paper px-2 py-1 font-mono text-[0.55rem] whitespace-nowrap text-ink shadow-sm group-hover:block"
                    >
                        {{ day.label }} · {{ day.total }} made ·
                        {{ day.charge }}
                    </span>
                </div>
            </div>
            <div
                v-else
                class="mt-7 flex h-44 items-center justify-center border border-dashed border-line bg-background/40"
            >
                <p class="max-w-xs text-center text-sm text-ink-soft">
                    No generations in the last thirty days. The ledger starts
                    with your first plate.
                </p>
            </div>

            <div
                class="mt-3 flex justify-between font-mono text-[0.52rem] tracking-[0.08em] text-ink-soft uppercase"
            >
                <span>{{ days[0]?.label }}</span>
                <span>{{ days[Math.floor(days.length / 2)]?.label }}</span>
                <span>{{ days[days.length - 1]?.label }}</span>
            </div>

            <details class="mt-5 border-t border-line-soft pt-4">
                <summary
                    class="cursor-pointer font-mono text-[0.58rem] tracking-[0.1em] text-ink-soft uppercase hover:text-ink"
                >
                    Daily figures as a table
                </summary>
                <div class="mt-3 overflow-x-auto">
                    <table
                        class="w-full border-collapse font-mono text-[0.65rem]"
                    >
                        <thead>
                            <tr
                                class="border-b border-line text-left text-ink-soft"
                            >
                                <th class="py-2 pr-3 font-medium">Day</th>
                                <th class="px-3 py-2 text-right font-medium">
                                    Made
                                </th>
                                <th class="px-3 py-2 text-right font-medium">
                                    Failed
                                </th>
                                <th class="px-3 py-2 text-right font-medium">
                                    Active
                                </th>
                                <th class="px-3 py-2 text-right font-medium">
                                    Charged
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="day in days"
                                :key="day.date"
                                class="border-b border-line-soft last:border-0"
                            >
                                <td class="py-1.5 pr-3 text-ink">
                                    {{ day.label }}
                                </td>
                                <td class="px-3 py-1.5 text-right">
                                    {{ day.succeeded }}
                                </td>
                                <td class="px-3 py-1.5 text-right">
                                    {{ day.failed }}
                                </td>
                                <td class="px-3 py-1.5 text-right">
                                    {{ day.active }}
                                </td>
                                <td class="px-3 py-1.5 text-right text-ink">
                                    {{ day.charge }}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </details>
        </section>

        <section class="space-y-4" aria-labelledby="recent-charges">
            <div
                class="flex flex-wrap items-end justify-between gap-3 border-b border-line pb-4"
            >
                <div>
                    <p class="eyebrow">Settled charges</p>
                    <h2
                        id="recent-charges"
                        class="mt-2 font-display text-3xl tracking-[-0.03em] text-ink"
                    >
                        Last entries
                    </h2>
                </div>
                <Link
                    href="/library"
                    class="link-underline text-xs font-medium text-ink-soft"
                    >Open the library</Link
                >
            </div>

            <div
                v-if="recent.length > 0"
                class="overflow-x-auto border border-line bg-paper"
            >
                <table class="w-full border-collapse text-left text-sm">
                    <thead>
                        <tr
                            class="border-b border-line font-mono text-[0.55rem] tracking-[0.12em] text-ink-soft uppercase"
                        >
                            <th class="px-4 py-3 font-medium">Plate</th>
                            <th
                                class="hidden px-4 py-3 font-medium md:table-cell"
                            >
                                Settled
                            </th>
                            <th class="px-4 py-3 text-right font-medium">
                                Tokens
                            </th>
                            <th class="px-4 py-3 text-right font-medium">
                                Charged
                            </th>
                            <th class="w-10 px-4 py-3">
                                <span class="sr-only">Open</span>
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="entry in recent"
                            :key="entry.id"
                            class="border-b border-line-soft transition-colors last:border-0 hover:bg-background/50"
                        >
                            <td class="max-w-[26rem] px-4 py-3">
                                <Link
                                    :href="`/generations/${entry.id}`"
                                    class="line-clamp-1 font-display text-[0.98rem] text-ink hover:text-vermilion"
                                    >{{ entry.prompt }}</Link
                                >
                            </td>
                            <td
                                class="hidden px-4 py-3 font-mono text-[0.6rem] text-ink-soft md:table-cell"
                            >
                                {{ formatDateTime(entry.settled_at) }}
                            </td>
                            <td
                                class="px-4 py-3 text-right font-mono text-[0.65rem] text-ink-soft"
                            >
                                {{ entry.tokens.toLocaleString() }}
                            </td>
                            <td
                                class="px-4 py-3 text-right font-mono text-[0.68rem] text-ink"
                            >
                                {{ entry.charge }}
                            </td>
                            <td class="px-4 py-3">
                                <Link
                                    :href="`/generations/${entry.id}`"
                                    class="focus-ring inline-flex text-ink-soft hover:text-vermilion"
                                    :aria-label="`Open ${entry.id}`"
                                >
                                    <ArrowRight
                                        class="size-3.5"
                                        aria-hidden="true"
                                    />
                                </Link>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <div
                v-else
                class="border border-dashed border-line bg-paper/60 px-6 py-12 text-center"
            >
                <p class="font-display text-2xl text-ink">No charges yet.</p>
                <p class="mt-2 text-sm text-ink-soft">
                    A charge is recorded only after an image is delivered.
                </p>
                <Link
                    href="/studio"
                    class="link-underline mt-4 inline-block text-xs font-medium text-ink"
                    >Make your first image →</Link
                >
            </div>
        </section>

        <p class="max-w-2xl text-[0.65rem] leading-relaxed text-ink-soft">
            Figures reflect completed generations and their immutable charge
            records. Active generations are excluded until the provider reports
            final usage. Amounts are shown in USD to six decimals; the exact
            nine-decimal figure is kept in the ledger and on each plate.
        </p>
    </div>
</template>
