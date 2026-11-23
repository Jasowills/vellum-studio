<script setup lang="ts">
defineOptions({ inheritAttrs: false });

import { Head, Link } from '@inertiajs/vue3';
import { ArrowRight, Coins, Images, Receipt, TriangleAlert } from '@lucide/vue';
import { computed, ref } from 'vue';
import { formatDate, formatDateTime } from '@/lib/vellum';

type UsageDay = {
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
};

type RecentCharge = {
    id: string;
    prompt: string;
    charge_nanos: number;
    charge: string;
    tokens: number;
    settled_at: string | null;
};

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
    days: UsageDay[];
    recent: RecentCharge[];
    range: { start: string; end: string };
}>();

const emptyDay: UsageDay = {
    date: '',
    label: '—',
    total: 0,
    succeeded: 0,
    failed: 0,
    active: 0,
    charged: 0,
    charge_nanos: 0,
    charge: '—',
    tokens: 0,
};

const maxDaily = computed(() =>
    Math.max(1, ...props.days.map((day) => day.total)),
);

const busiest = computed<UsageDay>(() =>
    props.days.reduce<UsageDay>(
        (best, day) => (day.total > best.total ? day : best),
        props.days[0] ?? emptyDay,
    ),
);

const hasActivity = computed(() => props.summary.total > 0);
const hasWindowActivity = computed(() =>
    props.days.some((day) => day.total > 0),
);

const selectedDate = ref<string | null>(null);

const activeDay = computed<UsageDay>(
    () =>
        [...props.days]
            .reverse()
            .find((day) => day.date === selectedDate.value) ??
        props.days[props.days.length - 1] ??
        emptyDay,
);

const chartTicks = computed(() => {
    const max = maxDaily.value;
    const values = [
        ...new Set([0, 1, 2, 3, 4].map((step) => Math.round((max / 4) * step))),
    ].sort((a, b) => b - a);

    return values.map((value) => ({
        value,
        top: `${100 - (value / max) * 100}%`,
    }));
});

const axisTicks = computed(() => {
    const total = props.days.length;

    return props.days
        .map((day, index) => ({
            key: day.date,
            label: day.label,
            left: `${((index + 0.5) / Math.max(total, 1)) * 100}%`,
            mobile: index % 7 === 0 || index === total - 1,
            desktop: index % 5 === 0 || index === total - 1,
            edge:
                index === 0 ? 'start' : index === total - 1 ? 'end' : 'middle',
        }))
        .filter((tick) => tick.mobile || tick.desktop);
});

const emptyMessage = computed(() =>
    hasActivity.value
        ? `Your ledger records ${props.summary.total.toLocaleString()} lifetime attempts, but none were created between ${formatDate(props.range.start)} and ${formatDate(props.range.end)}.`
        : 'Nothing has been generated yet. Every settled charge will appear here once a generation completes; failed generations are never charged.',
);

function barHeight(value: number): string {
    if (value === 0) {
        return '2px';
    }

    return `${Math.max(6, Math.round((value / maxDaily.value) * 100))}%`;
}

function exactMoney(nanos: number): string {
    return `$${(nanos / 1e9).toFixed(9)}`;
}

function dayAriaLabel(day: UsageDay): string {
    return [
        `${day.label}, ${day.date}.`,
        `${day.total} total attempts.`,
        `${day.succeeded} succeeded.`,
        `${day.failed} failed.`,
        `${day.active} active.`,
        `${day.charged} settled charges.`,
        `${exactMoney(day.charge_nanos)} charged.`,
        `${day.tokens.toLocaleString()} tokens.`,
    ].join(' ');
}

function selectDay(day: UsageDay): void {
    selectedDate.value = day.date;
}
</script>

<template>
    <Head title="Usage" />

    <div class="min-h-svh overflow-x-clip bg-void text-document">
        <div
            class="mx-auto max-w-[1440px] space-y-14 px-5 py-10 sm:px-8 sm:py-12 lg:px-12 lg:py-16"
        >
            <header
                class="flex flex-col gap-8 border-b border-line pb-9 lg:flex-row lg:items-end lg:justify-between"
            >
                <div class="min-w-0">
                    <p class="vellum-meta flex items-center gap-3">
                        <span class="h-px w-8 bg-document" aria-hidden="true" />
                        Usage / last thirty days
                    </p>
                    <h1
                        class="vellum-display mt-5 max-w-[16ch] text-[clamp(2.6rem,5.4vw,4.25rem)] text-document"
                    >
                        The ledger,
                        <span class="text-ash">exactly.</span>
                    </h1>
                </div>
                <div class="max-w-md space-y-3 lg:text-right">
                    <p class="text-sm leading-relaxed text-ash">
                        Every settled charge, exactly as recorded at completion.
                        Failed generations never appear as charges.
                    </p>
                    <p class="vellum-meta">
                        {{ formatDate(range.start) }} —
                        {{ formatDate(range.end) }}
                    </p>
                </div>
            </header>

            <section aria-labelledby="lifetime-title" class="space-y-4">
                <div
                    class="flex flex-wrap items-end justify-between gap-3 border-b border-line pb-3"
                >
                    <h2
                        id="lifetime-title"
                        class="vellum-title text-xl text-document"
                    >
                        Lifetime summary
                    </h2>
                    <p class="vellum-meta">All time / account</p>
                </div>
                <div
                    class="grid grid-cols-2 gap-px border border-line bg-line lg:grid-cols-4"
                >
                    <div class="bg-surface p-4 sm:p-6">
                        <div class="flex items-center justify-between gap-3">
                            <p class="text-sm text-ash">Images made</p>
                            <Images
                                class="size-4 text-stone"
                                aria-hidden="true"
                            />
                        </div>
                        <p
                            class="ledger mt-6 text-[1.75rem] leading-none tracking-[-0.04em] text-document sm:text-3xl"
                        >
                            {{ summary.succeeded.toLocaleString() }}
                        </p>
                        <p class="mt-3 text-xs leading-relaxed text-ash">
                            {{ summary.total.toLocaleString() }} attempts ·
                            {{ summary.active.toLocaleString() }} in flight
                        </p>
                    </div>
                    <div class="bg-surface p-4 sm:p-6">
                        <div class="flex items-center justify-between gap-3">
                            <p class="text-sm text-ash">Charged</p>
                            <Coins
                                class="size-4 text-stone"
                                aria-hidden="true"
                            />
                        </div>
                        <p
                            class="ledger mt-6 text-[1.05rem] leading-none tracking-[-0.04em] text-document sm:text-2xl"
                        >
                            {{ exactMoney(summary.charge_nanos) }}
                        </p>
                        <p class="mt-3 text-xs leading-relaxed text-ash">
                            {{ summary.charged.toLocaleString() }} settled
                            {{ summary.charged === 1 ? 'charge' : 'charges' }}
                        </p>
                    </div>
                    <div class="bg-surface p-4 sm:p-6">
                        <div class="flex items-center justify-between gap-3">
                            <p class="text-sm text-ash">Tokens billed</p>
                            <Receipt
                                class="size-4 text-stone"
                                aria-hidden="true"
                            />
                        </div>
                        <p
                            class="ledger mt-6 text-[1.5rem] leading-none tracking-[-0.04em] text-document sm:text-3xl"
                        >
                            {{ summary.tokens.toLocaleString() }}
                        </p>
                        <p class="mt-3 text-xs leading-relaxed text-ash">
                            all token classes
                        </p>
                    </div>
                    <div class="bg-surface p-4 sm:p-6">
                        <div class="flex items-center justify-between gap-3">
                            <p class="text-sm text-ash">Failures</p>
                            <TriangleAlert
                                class="size-4 text-stone"
                                aria-hidden="true"
                            />
                        </div>
                        <p
                            class="ledger mt-6 text-[1.5rem] leading-none tracking-[-0.04em] text-document sm:text-3xl"
                        >
                            {{ summary.failed.toLocaleString() }}
                        </p>
                        <p class="mt-3 text-xs leading-relaxed text-ash">
                            always free
                        </p>
                    </div>
                </div>
            </section>

            <section class="space-y-5" aria-labelledby="daily-title">
                <div
                    class="flex flex-col gap-4 border-b border-line pb-5 sm:flex-row sm:items-end sm:justify-between"
                >
                    <div>
                        <p class="vellum-meta">Daily activity</p>
                        <h2
                            id="daily-title"
                            class="vellum-title mt-2 text-2xl text-document sm:text-[1.9rem]"
                        >
                            Thirty days of work
                        </h2>
                    </div>
                    <p class="vellum-meta">
                        Peak {{ busiest.total.toLocaleString() }} ·
                        {{ busiest.label }}
                    </p>
                </div>

                <figure class="overflow-x-auto border border-line bg-void">
                    <div
                        class="vellum-meta flex flex-wrap items-center justify-between gap-2 border-b border-line px-4 py-2.5"
                    >
                        <span>Generations / UTC day</span>
                        <span>Scale 0 — {{ maxDaily.toLocaleString() }}</span>
                    </div>

                    <template v-if="hasWindowActivity">
                        <div class="relative h-52 min-w-[900px] sm:h-64">
                            <div
                                class="pointer-events-none absolute inset-0"
                                aria-hidden="true"
                            >
                                <div
                                    v-for="tick in chartTicks"
                                    :key="`grid-${tick.value}`"
                                    class="absolute inset-x-0 border-t"
                                    :class="
                                        tick.value === 0
                                            ? 'border-edge-strong'
                                            : 'border-edge'
                                    "
                                    :style="{ top: tick.top }"
                                />
                                <span
                                    v-for="tick in chartTicks"
                                    :key="`tick-${tick.value}`"
                                    class="ledger absolute right-2 -translate-y-1/2 bg-void px-1 text-[0.6rem] text-ash"
                                    :style="{ top: tick.top }"
                                >
                                    {{ tick.value.toLocaleString() }}
                                </span>
                            </div>

                            <div
                                class="absolute inset-y-0 right-9 left-3 flex min-w-[900px] items-end gap-px pt-3 sm:right-12 sm:left-4 sm:gap-[3px]"
                                role="group"
                                aria-label="Daily generation bars. Each bar is focusable and announces all figures for its day."
                            >
                                <button
                                    v-for="day in days"
                                    :key="day.date"
                                    type="button"
                                    class="focus-ring group relative flex h-full min-w-0 flex-1 items-end"
                                    :aria-label="dayAriaLabel(day)"
                                    :title="dayAriaLabel(day)"
                                    @focus="selectDay(day)"
                                    @click="selectDay(day)"
                                    @pointerenter="selectDay(day)"
                                >
                                    <span
                                        class="block w-full transition-colors duration-150 motion-reduce:transition-none"
                                        :class="[
                                            day.total === 0
                                                ? 'bg-edge'
                                                : day.failed > 0 &&
                                                    day.succeeded === 0
                                                  ? 'bg-edge-strong'
                                                  : 'bg-document',
                                            'group-hover:bg-ash group-focus-visible:bg-ash',
                                        ]"
                                        :style="{
                                            height: barHeight(day.total),
                                        }"
                                        aria-hidden="true"
                                    />
                                </button>
                            </div>
                        </div>

                        <figcaption
                            class="relative h-8 min-w-[900px] border-t border-edge"
                            aria-hidden="true"
                        >
                            <span
                                v-for="tick in axisTicks"
                                :key="tick.key"
                                class="ledger absolute top-2 text-[0.6rem] tracking-[0.06em] whitespace-nowrap text-ash"
                                :class="[
                                    tick.mobile ? 'block' : 'hidden sm:block',
                                    tick.edge === 'start'
                                        ? 'translate-x-0'
                                        : tick.edge === 'end'
                                          ? '-translate-x-full'
                                          : '-translate-x-1/2',
                                ]"
                                :style="{ left: tick.left }"
                            >
                                {{ tick.label }}
                            </span>
                        </figcaption>
                    </template>

                    <div
                        v-else
                        class="flex h-52 flex-col items-center justify-center gap-3 px-6 text-center sm:h-64"
                    >
                        <p class="vellum-title text-xl text-document">
                            No activity in this window.
                        </p>
                        <p class="max-w-md text-sm leading-relaxed text-ash">
                            {{ emptyMessage }}
                        </p>
                    </div>
                </figure>

                <div class="border border-line bg-surface">
                    <div
                        class="vellum-meta flex flex-wrap items-center justify-between gap-2 border-b border-line px-4 py-2.5"
                    >
                        <span>Readout / {{ activeDay.label }}</span>
                        <span>{{ activeDay.date }}</span>
                    </div>
                    <dl
                        class="grid grid-cols-2 gap-px bg-edge sm:grid-cols-4 lg:grid-cols-7"
                    >
                        <div class="bg-surface px-4 py-3">
                            <dt class="vellum-meta text-[0.58rem]">Total</dt>
                            <dd class="ledger mt-2 text-sm text-document">
                                {{ activeDay.total.toLocaleString() }}
                            </dd>
                        </div>
                        <div class="bg-surface px-4 py-3">
                            <dt class="vellum-meta text-[0.58rem]">Made</dt>
                            <dd class="ledger mt-2 text-sm text-document">
                                {{ activeDay.succeeded.toLocaleString() }}
                            </dd>
                        </div>
                        <div class="bg-surface px-4 py-3">
                            <dt class="vellum-meta text-[0.58rem]">Failed</dt>
                            <dd class="ledger mt-2 text-sm text-document">
                                {{ activeDay.failed.toLocaleString() }}
                            </dd>
                        </div>
                        <div class="bg-surface px-4 py-3">
                            <dt class="vellum-meta text-[0.58rem]">Active</dt>
                            <dd class="ledger mt-2 text-sm text-document">
                                {{ activeDay.active.toLocaleString() }}
                            </dd>
                        </div>
                        <div class="bg-surface px-4 py-3">
                            <dt class="vellum-meta text-[0.58rem]">Charges</dt>
                            <dd class="ledger mt-2 text-sm text-document">
                                {{ activeDay.charged.toLocaleString() }}
                            </dd>
                        </div>
                        <div class="bg-surface px-4 py-3">
                            <dt class="vellum-meta text-[0.58rem]">Amount</dt>
                            <dd class="ledger mt-2 text-sm text-document">
                                {{ exactMoney(activeDay.charge_nanos) }}
                            </dd>
                        </div>
                        <div class="bg-surface px-4 py-3">
                            <dt class="vellum-meta text-[0.58rem]">Tokens</dt>
                            <dd class="ledger mt-2 text-sm text-document">
                                {{ activeDay.tokens.toLocaleString() }}
                            </dd>
                        </div>
                    </dl>
                </div>
            </section>

            <section class="space-y-4" aria-labelledby="daily-table-title">
                <div
                    class="flex flex-wrap items-end justify-between gap-3 border-b border-line pb-3"
                >
                    <h2
                        id="daily-table-title"
                        class="vellum-title text-xl text-document"
                    >
                        Daily figures
                    </h2>
                    <p class="vellum-meta">{{ days.length }} days / UTC</p>
                </div>

                <template v-if="days.length > 0">
                    <div
                        class="focus-ring overflow-x-auto border border-line bg-surface"
                        role="region"
                        aria-label="Daily figures table, scroll horizontally"
                        tabindex="0"
                    >
                        <table
                            class="w-full min-w-[720px] border-collapse text-left"
                        >
                            <caption class="sr-only">
                                Daily generation attempts, settled charges and
                                tokens, grouped by UTC day. Amounts are exact
                                USD.
                            </caption>
                            <thead>
                                <tr class="vellum-meta border-b border-line">
                                    <th
                                        scope="col"
                                        class="px-3 py-3 font-normal"
                                    >
                                        Day
                                    </th>
                                    <th
                                        scope="col"
                                        class="px-3 py-3 text-right font-normal"
                                    >
                                        Total
                                    </th>
                                    <th
                                        scope="col"
                                        class="px-3 py-3 text-right font-normal"
                                    >
                                        Made
                                    </th>
                                    <th
                                        scope="col"
                                        class="px-3 py-3 text-right font-normal"
                                    >
                                        Failed
                                    </th>
                                    <th
                                        scope="col"
                                        class="px-3 py-3 text-right font-normal"
                                    >
                                        Active
                                    </th>
                                    <th
                                        scope="col"
                                        class="px-3 py-3 text-right font-normal"
                                    >
                                        Charges
                                    </th>
                                    <th
                                        scope="col"
                                        class="px-3 py-3 text-right font-normal"
                                    >
                                        Amount
                                    </th>
                                    <th
                                        scope="col"
                                        class="px-3 py-3 text-right font-normal"
                                    >
                                        Tokens
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr
                                    v-for="day in days"
                                    :key="day.date"
                                    class="border-b border-edge last:border-0"
                                >
                                    <th
                                        scope="row"
                                        class="sticky left-0 bg-surface px-3 py-2.5 font-normal text-document"
                                    >
                                        {{ day.label }}
                                        <span class="ml-2 text-ash">{{
                                            day.date
                                        }}</span>
                                    </th>
                                    <td
                                        class="ledger px-3 py-2.5 text-right text-sm text-document"
                                    >
                                        {{ day.total.toLocaleString() }}
                                    </td>
                                    <td
                                        class="ledger px-3 py-2.5 text-right text-sm text-ash"
                                    >
                                        {{ day.succeeded.toLocaleString() }}
                                    </td>
                                    <td
                                        class="ledger px-3 py-2.5 text-right text-sm text-ash"
                                    >
                                        {{ day.failed.toLocaleString() }}
                                    </td>
                                    <td
                                        class="ledger px-3 py-2.5 text-right text-sm text-ash"
                                    >
                                        {{ day.active.toLocaleString() }}
                                    </td>
                                    <td
                                        class="ledger px-3 py-2.5 text-right text-sm text-ash"
                                    >
                                        {{ day.charged.toLocaleString() }}
                                    </td>
                                    <td
                                        class="ledger px-3 py-2.5 text-right text-sm text-document"
                                    >
                                        {{ exactMoney(day.charge_nanos) }}
                                    </td>
                                    <td
                                        class="ledger px-3 py-2.5 text-right text-sm text-ash"
                                    >
                                        {{ day.tokens.toLocaleString() }}
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
                    No daily records were returned for this period.
                </p>
            </section>

            <section class="space-y-4" aria-labelledby="recent-charges">
                <div
                    class="flex flex-wrap items-end justify-between gap-3 border-b border-line pb-4"
                >
                    <div>
                        <p class="vellum-meta">Settled charges</p>
                        <h2
                            id="recent-charges"
                            class="vellum-title mt-2 text-2xl text-document sm:text-[1.9rem]"
                        >
                            Last entries
                        </h2>
                    </div>
                    <Link
                        href="/library"
                        class="focus-ring link-underline vellum-meta pb-1 text-document"
                    >
                        Open the library
                    </Link>
                </div>

                <template v-if="recent.length > 0">
                    <div
                        class="focus-ring overflow-x-auto border border-line bg-surface"
                        role="region"
                        aria-label="Settled charges table, scroll horizontally"
                        tabindex="0"
                    >
                        <table
                            class="w-full min-w-[600px] border-collapse text-left"
                        >
                            <caption class="sr-only">
                                Recent settled charges with plate, settlement
                                time, tokens and exact amount charged.
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
                                        Settled
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
                                        Charged
                                    </th>
                                    <th scope="col" class="w-10 px-4 py-3">
                                        <span class="sr-only">Open</span>
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr
                                    v-for="entry in recent"
                                    :key="entry.id"
                                    class="border-b border-edge transition-colors last:border-0 hover:bg-elevated motion-reduce:transition-none"
                                >
                                    <td class="max-w-[26rem] px-4 py-3">
                                        <Link
                                            :href="`/generations/${entry.id}`"
                                            class="focus-ring line-clamp-1 font-display text-[0.95rem] text-document hover:text-ash"
                                            >{{ entry.prompt }}</Link
                                        >
                                    </td>
                                    <td
                                        class="ledger px-4 py-3 text-xs text-ash"
                                    >
                                        {{ formatDateTime(entry.settled_at) }}
                                    </td>
                                    <td
                                        class="ledger px-4 py-3 text-right text-xs text-ash"
                                    >
                                        {{ entry.tokens.toLocaleString() }}
                                    </td>
                                    <td
                                        class="ledger px-4 py-3 text-right text-xs text-document"
                                    >
                                        {{ exactMoney(entry.charge_nanos) }}
                                    </td>
                                    <td class="px-4 py-3">
                                        <Link
                                            :href="`/generations/${entry.id}`"
                                            class="focus-ring inline-flex text-ash hover:text-document"
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
                </template>

                <div
                    v-else
                    class="border border-line bg-surface px-6 py-10 text-center"
                >
                    <p class="vellum-title text-xl text-document">
                        No charges yet.
                    </p>
                    <p
                        class="mx-auto mt-2 max-w-sm text-sm leading-relaxed text-ash"
                    >
                        A charge is recorded only after an image is delivered.
                    </p>
                    <Link
                        href="/studio"
                        class="focus-ring link-underline vellum-meta mt-4 inline-block text-document"
                    >
                        Make your first image →
                    </Link>
                </div>
            </section>

            <p
                class="max-w-2xl border-t border-line pt-5 text-xs leading-relaxed text-ash"
            >
                Figures reflect completed generations and their immutable charge
                records. Active generations are excluded until the provider
                reports final usage. Amounts are shown in USD; the exact
                nine-decimal figure is kept in the ledger and on each plate.
            </p>
        </div>
    </div>
</template>
