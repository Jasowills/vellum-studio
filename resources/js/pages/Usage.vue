<script setup lang="ts">
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

function dayAriaLabel(day: UsageDay): string {
    return [
        `${day.label}, ${day.date}.`,
        `${day.total} total attempts.`,
        `${day.succeeded} succeeded.`,
        `${day.failed} failed.`,
        `${day.active} active.`,
        `${day.charged} settled charges.`,
        `${day.charge} charged.`,
        `${day.tokens.toLocaleString()} tokens.`,
    ].join(' ');
}

function selectDay(day: UsageDay): void {
    selectedDate.value = day.date;
}
</script>

<template>
    <Head title="Usage" />

    <div class="min-h-svh bg-background text-signal">
        <div
            class="mx-auto max-w-[1440px] space-y-12 px-5 py-10 sm:px-8 sm:py-12 lg:px-12 lg:py-14"
        >
            <header
                class="flex flex-col justify-between gap-7 border-b border-line pb-8 lg:flex-row lg:items-end"
            >
                <div>
                    <p class="eyebrow flex items-center gap-3">
                        <span class="h-px w-8 bg-signal" aria-hidden="true" />
                        01 / Usage — last thirty days
                    </p>
                    <h1
                        class="display-title mt-6 max-w-[14ch] text-[clamp(2.8rem,5.2vw,4.4rem)] text-signal"
                    >
                        The
                        <em class="text-signal-muted not-italic">ledger.</em>
                    </h1>
                </div>
                <div class="max-w-sm space-y-3 lg:text-right">
                    <p class="text-sm leading-relaxed text-signal-muted">
                        Every settled charge, exactly as recorded at completion.
                        Failed generations never appear as charges.
                    </p>
                    <p
                        class="ledger text-[0.6rem] tracking-[0.12em] text-signal-faint uppercase"
                    >
                        {{ formatDate(range.start) }} —
                        {{ formatDate(range.end) }}
                    </p>
                </div>
            </header>

            <section aria-labelledby="lifetime-title">
                <div
                    class="flex items-baseline justify-between border-b border-line pb-3"
                >
                    <h2 id="lifetime-title" class="eyebrow">
                        Lifetime summary
                    </h2>
                    <p
                        class="font-mono text-[0.55rem] tracking-[0.12em] text-signal-faint uppercase"
                    >
                        All time / account
                    </p>
                </div>
                <div
                    class="grid border-x border-b border-line sm:grid-cols-2 lg:grid-cols-4"
                >
                    <div
                        class="border-b border-line-soft p-5 sm:border-r sm:border-b-0 sm:p-6"
                    >
                        <div class="flex items-center justify-between gap-3">
                            <p class="eyebrow">Images made</p>
                            <Images
                                class="size-4 text-signal-faint"
                                aria-hidden="true"
                            />
                        </div>
                        <p
                            class="ledger mt-5 text-3xl tracking-[-0.04em] text-signal lg:text-4xl"
                        >
                            {{ summary.succeeded.toLocaleString() }}
                        </p>
                        <p
                            class="ledger mt-2 text-[0.58rem] tracking-[0.08em] text-signal-muted uppercase"
                        >
                            {{ summary.total.toLocaleString() }} attempts ·
                            {{ summary.active.toLocaleString() }} in flight
                        </p>
                    </div>
                    <div
                        class="border-b border-line-soft p-5 sm:border-b-0 sm:p-6 lg:border-r"
                    >
                        <div class="flex items-center justify-between gap-3">
                            <p class="eyebrow">Charged</p>
                            <Coins
                                class="size-4 text-signal-faint"
                                aria-hidden="true"
                            />
                        </div>
                        <p
                            class="ledger mt-5 text-2xl tracking-[-0.04em] text-signal lg:text-3xl"
                        >
                            {{ summary.charge }}
                        </p>
                        <p
                            class="ledger mt-2 text-[0.58rem] tracking-[0.08em] text-signal-muted uppercase"
                        >
                            {{ summary.charged.toLocaleString() }} settled
                            {{ summary.charged === 1 ? 'charge' : 'charges' }}
                        </p>
                    </div>
                    <div
                        class="border-b border-line-soft p-5 sm:border-r sm:border-b-0 sm:p-6"
                    >
                        <div class="flex items-center justify-between gap-3">
                            <p class="eyebrow">Tokens billed</p>
                            <Receipt
                                class="size-4 text-signal-faint"
                                aria-hidden="true"
                            />
                        </div>
                        <p
                            class="ledger mt-5 text-3xl tracking-[-0.04em] text-signal lg:text-4xl"
                        >
                            {{ summary.tokens.toLocaleString() }}
                        </p>
                        <p
                            class="ledger mt-2 text-[0.58rem] tracking-[0.08em] text-signal-muted uppercase"
                        >
                            all token classes
                        </p>
                    </div>
                    <div class="p-5 sm:p-6">
                        <div class="flex items-center justify-between gap-3">
                            <p class="eyebrow">Failures</p>
                            <TriangleAlert
                                class="size-4 text-signal-faint"
                                aria-hidden="true"
                            />
                        </div>
                        <p
                            class="ledger mt-5 text-3xl tracking-[-0.04em] text-signal lg:text-4xl"
                        >
                            {{ summary.failed.toLocaleString() }}
                        </p>
                        <p
                            class="ledger mt-2 text-[0.58rem] tracking-[0.08em] text-signal-muted uppercase"
                        >
                            always free
                        </p>
                    </div>
                </div>
            </section>

            <section class="space-y-5" aria-labelledby="daily-title">
                <div
                    class="flex flex-col justify-between gap-4 border-b border-line pb-5 sm:flex-row sm:items-end"
                >
                    <div>
                        <p class="eyebrow">02 / Daily activity</p>
                        <h2
                            id="daily-title"
                            class="display-title mt-3 text-[1.8rem] tracking-[-0.02em] text-signal sm:text-3xl"
                        >
                            Thirty days of work
                        </h2>
                    </div>
                    <p
                        class="ledger text-[0.6rem] tracking-[0.12em] text-signal-muted uppercase"
                    >
                        Peak {{ busiest.total.toLocaleString() }} ·
                        {{ busiest.label }}
                    </p>
                </div>

                <figure class="border border-line bg-background">
                    <div
                        class="flex items-center justify-between gap-3 border-b border-line px-3 py-2 font-mono text-[0.55rem] tracking-[0.14em] text-signal-muted uppercase sm:px-4"
                    >
                        <span>Generations / UTC day</span>
                        <span class="ledger"
                            >Scale 0 — {{ maxDaily.toLocaleString() }}</span
                        >
                    </div>

                    <div v-if="hasWindowActivity" class="relative h-52 sm:h-64">
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
                                        ? 'border-line'
                                        : 'border-line-soft'
                                "
                                :style="{ top: tick.top }"
                            />
                            <span
                                v-for="tick in chartTicks"
                                :key="`tick-${tick.value}`"
                                class="ledger absolute right-1.5 -translate-y-1/2 bg-background px-1 text-[0.5rem] text-signal-faint sm:right-2"
                                :style="{ top: tick.top }"
                            >
                                {{ tick.value.toLocaleString() }}
                            </span>
                        </div>

                        <div
                            class="absolute inset-y-0 right-9 left-3 flex items-end gap-px pt-3 sm:right-12 sm:left-4 sm:gap-[3px]"
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
                                            ? 'bg-line'
                                            : day.failed > 0 &&
                                                day.succeeded === 0
                                              ? 'bg-signal-faint'
                                              : 'bg-signal',
                                        'group-hover:bg-signal-muted group-focus-visible:bg-signal-muted',
                                    ]"
                                    :style="{ height: barHeight(day.total) }"
                                    aria-hidden="true"
                                />
                            </button>
                        </div>
                    </div>

                    <div
                        v-else
                        class="flex h-52 flex-col items-center justify-center gap-3 px-6 text-center sm:h-64"
                    >
                        <p
                            class="font-display text-xl tracking-[-0.02em] text-signal"
                        >
                            No activity in this window.
                        </p>
                        <p
                            class="max-w-md text-sm leading-relaxed text-signal-muted"
                        >
                            {{ emptyMessage }}
                        </p>
                    </div>

                    <figcaption
                        class="relative h-8 border-t border-line-soft"
                        aria-hidden="true"
                    >
                        <span
                            v-for="tick in axisTicks"
                            :key="tick.key"
                            class="absolute top-2 font-mono text-[0.5rem] tracking-[0.06em] whitespace-nowrap text-signal-faint"
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
                </figure>

                <div class="border border-line bg-paper">
                    <div
                        class="flex flex-wrap items-center justify-between gap-2 border-b border-line px-3 py-2 font-mono text-[0.55rem] tracking-[0.14em] text-signal-muted uppercase sm:px-4"
                    >
                        <span>Readout / {{ activeDay.label }}</span>
                        <span class="ledger text-signal-faint">{{
                            activeDay.date
                        }}</span>
                    </div>
                    <dl
                        class="grid grid-cols-2 gap-px bg-line-soft sm:grid-cols-4 lg:grid-cols-7"
                    >
                        <div class="bg-paper px-3 py-3 sm:px-4">
                            <dt
                                class="font-mono text-[0.52rem] tracking-[0.12em] text-signal-faint uppercase"
                            >
                                Total
                            </dt>
                            <dd class="ledger mt-1.5 text-sm text-signal">
                                {{ activeDay.total.toLocaleString() }}
                            </dd>
                        </div>
                        <div class="bg-paper px-3 py-3 sm:px-4">
                            <dt
                                class="font-mono text-[0.52rem] tracking-[0.12em] text-signal-faint uppercase"
                            >
                                Made
                            </dt>
                            <dd class="ledger mt-1.5 text-sm text-signal">
                                {{ activeDay.succeeded.toLocaleString() }}
                            </dd>
                        </div>
                        <div class="bg-paper px-3 py-3 sm:px-4">
                            <dt
                                class="font-mono text-[0.52rem] tracking-[0.12em] text-signal-faint uppercase"
                            >
                                Failed
                            </dt>
                            <dd class="ledger mt-1.5 text-sm text-signal">
                                {{ activeDay.failed.toLocaleString() }}
                            </dd>
                        </div>
                        <div class="bg-paper px-3 py-3 sm:px-4">
                            <dt
                                class="font-mono text-[0.52rem] tracking-[0.12em] text-signal-faint uppercase"
                            >
                                Active
                            </dt>
                            <dd class="ledger mt-1.5 text-sm text-signal">
                                {{ activeDay.active.toLocaleString() }}
                            </dd>
                        </div>
                        <div class="bg-paper px-3 py-3 sm:px-4">
                            <dt
                                class="font-mono text-[0.52rem] tracking-[0.12em] text-signal-faint uppercase"
                            >
                                Charges
                            </dt>
                            <dd class="ledger mt-1.5 text-sm text-signal">
                                {{ activeDay.charged.toLocaleString() }}
                            </dd>
                        </div>
                        <div class="bg-paper px-3 py-3 sm:px-4">
                            <dt
                                class="font-mono text-[0.52rem] tracking-[0.12em] text-signal-faint uppercase"
                            >
                                Amount
                            </dt>
                            <dd class="ledger mt-1.5 text-sm text-signal">
                                {{ activeDay.charge }}
                            </dd>
                        </div>
                        <div class="bg-paper px-3 py-3 sm:px-4">
                            <dt
                                class="font-mono text-[0.52rem] tracking-[0.12em] text-signal-faint uppercase"
                            >
                                Tokens
                            </dt>
                            <dd class="ledger mt-1.5 text-sm text-signal">
                                {{ activeDay.tokens.toLocaleString() }}
                            </dd>
                        </div>
                    </dl>
                </div>
            </section>

            <section class="space-y-3" aria-labelledby="daily-table-title">
                <div
                    class="flex items-baseline justify-between border-b border-line pb-3"
                >
                    <h2 id="daily-table-title" class="eyebrow">
                        Daily figures
                    </h2>
                    <p
                        class="ledger text-[0.55rem] tracking-[0.12em] text-signal-faint uppercase"
                    >
                        {{ days.length }} days / UTC
                    </p>
                </div>

                <template v-if="days.length > 0">
                    <div class="hidden border border-line bg-paper lg:block">
                        <table
                            class="w-full border-collapse text-left font-mono text-[0.62rem] tabular-nums"
                        >
                            <thead>
                                <tr
                                    class="border-b border-line text-[0.52rem] tracking-[0.12em] text-signal-muted uppercase"
                                >
                                    <th
                                        scope="col"
                                        class="px-3 py-2.5 font-medium"
                                    >
                                        Day
                                    </th>
                                    <th
                                        scope="col"
                                        class="px-3 py-2.5 text-right font-medium"
                                    >
                                        Total
                                    </th>
                                    <th
                                        scope="col"
                                        class="px-3 py-2.5 text-right font-medium"
                                    >
                                        Made
                                    </th>
                                    <th
                                        scope="col"
                                        class="px-3 py-2.5 text-right font-medium"
                                    >
                                        Failed
                                    </th>
                                    <th
                                        scope="col"
                                        class="px-3 py-2.5 text-right font-medium"
                                    >
                                        Active
                                    </th>
                                    <th
                                        scope="col"
                                        class="px-3 py-2.5 text-right font-medium"
                                    >
                                        Charges
                                    </th>
                                    <th
                                        scope="col"
                                        class="px-3 py-2.5 text-right font-medium"
                                    >
                                        Amount
                                    </th>
                                    <th
                                        scope="col"
                                        class="px-3 py-2.5 text-right font-medium"
                                    >
                                        Tokens
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr
                                    v-for="day in days"
                                    :key="day.date"
                                    class="border-b border-line-soft last:border-0"
                                >
                                    <th
                                        scope="row"
                                        class="px-3 py-2 font-normal text-signal"
                                    >
                                        {{ day.label }}
                                        <span class="ml-2 text-signal-faint">{{
                                            day.date
                                        }}</span>
                                    </th>
                                    <td
                                        class="px-3 py-2 text-right text-signal"
                                    >
                                        {{ day.total.toLocaleString() }}
                                    </td>
                                    <td
                                        class="px-3 py-2 text-right text-signal-muted"
                                    >
                                        {{ day.succeeded.toLocaleString() }}
                                    </td>
                                    <td
                                        class="px-3 py-2 text-right text-signal-muted"
                                    >
                                        {{ day.failed.toLocaleString() }}
                                    </td>
                                    <td
                                        class="px-3 py-2 text-right text-signal-muted"
                                    >
                                        {{ day.active.toLocaleString() }}
                                    </td>
                                    <td
                                        class="px-3 py-2 text-right text-signal-muted"
                                    >
                                        {{ day.charged.toLocaleString() }}
                                    </td>
                                    <td
                                        class="px-3 py-2 text-right text-signal"
                                    >
                                        {{ day.charge }}
                                    </td>
                                    <td
                                        class="px-3 py-2 text-right text-signal-muted"
                                    >
                                        {{ day.tokens.toLocaleString() }}
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <ul class="border border-line bg-paper lg:hidden">
                        <li
                            v-for="day in days"
                            :key="day.date"
                            class="border-b border-line-soft p-3 last:border-0 sm:p-4"
                        >
                            <div
                                class="flex items-center justify-between gap-3 font-mono text-[0.6rem] uppercase"
                            >
                                <span class="tracking-[0.12em] text-signal">{{
                                    day.label
                                }}</span>
                                <span class="ledger text-signal-faint">{{
                                    day.date
                                }}</span>
                            </div>
                            <dl
                                class="mt-3 grid grid-cols-4 gap-x-2 gap-y-3 font-mono text-[0.6rem] tabular-nums"
                            >
                                <div>
                                    <dt
                                        class="text-[0.5rem] tracking-[0.1em] text-signal-faint uppercase"
                                    >
                                        Total
                                    </dt>
                                    <dd class="mt-1 text-signal">
                                        {{ day.total.toLocaleString() }}
                                    </dd>
                                </div>
                                <div>
                                    <dt
                                        class="text-[0.5rem] tracking-[0.1em] text-signal-faint uppercase"
                                    >
                                        Made
                                    </dt>
                                    <dd class="mt-1 text-signal">
                                        {{ day.succeeded.toLocaleString() }}
                                    </dd>
                                </div>
                                <div>
                                    <dt
                                        class="text-[0.5rem] tracking-[0.1em] text-signal-faint uppercase"
                                    >
                                        Failed
                                    </dt>
                                    <dd class="mt-1 text-signal">
                                        {{ day.failed.toLocaleString() }}
                                    </dd>
                                </div>
                                <div>
                                    <dt
                                        class="text-[0.5rem] tracking-[0.1em] text-signal-faint uppercase"
                                    >
                                        Active
                                    </dt>
                                    <dd class="mt-1 text-signal">
                                        {{ day.active.toLocaleString() }}
                                    </dd>
                                </div>
                                <div>
                                    <dt
                                        class="text-[0.5rem] tracking-[0.1em] text-signal-faint uppercase"
                                    >
                                        Charges
                                    </dt>
                                    <dd class="mt-1 text-signal">
                                        {{ day.charged.toLocaleString() }}
                                    </dd>
                                </div>
                                <div>
                                    <dt
                                        class="text-[0.5rem] tracking-[0.1em] text-signal-faint uppercase"
                                    >
                                        Amount
                                    </dt>
                                    <dd class="mt-1 text-signal">
                                        {{ day.charge }}
                                    </dd>
                                </div>
                                <div class="col-span-2">
                                    <dt
                                        class="text-[0.5rem] tracking-[0.1em] text-signal-faint uppercase"
                                    >
                                        Tokens
                                    </dt>
                                    <dd class="mt-1 text-signal">
                                        {{ day.tokens.toLocaleString() }}
                                    </dd>
                                </div>
                            </dl>
                        </li>
                    </ul>
                </template>

                <p
                    v-else
                    class="border border-dashed border-line px-5 py-6 text-sm text-signal-muted"
                >
                    No daily records were returned for this period.
                </p>
            </section>

            <section class="space-y-4" aria-labelledby="recent-charges">
                <div
                    class="flex flex-wrap items-end justify-between gap-3 border-b border-line pb-4"
                >
                    <div>
                        <p class="eyebrow">03 / Settled charges</p>
                        <h2
                            id="recent-charges"
                            class="display-title mt-2 text-2xl tracking-[-0.02em] text-signal sm:text-3xl"
                        >
                            Last entries
                        </h2>
                    </div>
                    <Link
                        href="/library"
                        class="link-underline pb-1 font-mono text-[0.6rem] tracking-[0.12em] text-signal uppercase"
                    >
                        Open the library
                    </Link>
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
                                        Settled
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
                                    class="border-b border-line-soft transition-colors last:border-0 hover:bg-paper-deep motion-reduce:transition-none"
                                >
                                    <td class="max-w-[26rem] px-4 py-3">
                                        <Link
                                            :href="`/generations/${entry.id}`"
                                            class="line-clamp-1 font-display text-[0.98rem] text-signal hover:text-signal-muted"
                                            >{{ entry.prompt }}</Link
                                        >
                                    </td>
                                    <td
                                        class="ledger px-4 py-3 text-[0.6rem] text-signal-muted"
                                    >
                                        {{ formatDateTime(entry.settled_at) }}
                                    </td>
                                    <td
                                        class="ledger px-4 py-3 text-right text-[0.65rem] text-signal-muted"
                                    >
                                        {{ entry.tokens.toLocaleString() }}
                                    </td>
                                    <td
                                        class="ledger px-4 py-3 text-right text-[0.68rem] text-signal"
                                    >
                                        {{ entry.charge }}
                                    </td>
                                    <td class="px-4 py-3">
                                        <Link
                                            :href="`/generations/${entry.id}`"
                                            class="focus-ring inline-flex text-signal-muted hover:text-signal"
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

                    <ul class="border border-line bg-paper lg:hidden">
                        <li
                            v-for="entry in recent"
                            :key="entry.id"
                            class="border-b border-line-soft p-4 last:border-0"
                        >
                            <div class="flex items-start justify-between gap-4">
                                <Link
                                    :href="`/generations/${entry.id}`"
                                    class="line-clamp-2 font-display text-[1rem] leading-snug text-signal hover:text-signal-muted"
                                    >{{ entry.prompt }}</Link
                                >
                                <Link
                                    :href="`/generations/${entry.id}`"
                                    class="focus-ring inline-flex shrink-0 text-signal-muted hover:text-signal"
                                    :aria-label="`Open ${entry.id}`"
                                >
                                    <ArrowRight
                                        class="size-4"
                                        aria-hidden="true"
                                    />
                                </Link>
                            </div>
                            <p
                                class="ledger mt-2 text-[0.6rem] text-signal-faint"
                            >
                                {{ formatDateTime(entry.settled_at) }}
                            </p>
                            <dl
                                class="mt-3 grid grid-cols-2 gap-x-4 gap-y-2 font-mono text-[0.62rem] tabular-nums"
                            >
                                <div
                                    class="flex items-center justify-between gap-2 border-b border-line-soft pb-2"
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
                                    class="flex items-center justify-between gap-2 border-b border-line-soft pb-2"
                                >
                                    <dt
                                        class="text-[0.52rem] tracking-[0.1em] text-signal-faint uppercase"
                                    >
                                        Charged
                                    </dt>
                                    <dd class="text-signal">
                                        {{ entry.charge }}
                                    </dd>
                                </div>
                            </dl>
                        </li>
                    </ul>
                </template>

                <div
                    v-else
                    class="border border-dashed border-line bg-paper px-6 py-10 text-center"
                >
                    <p
                        class="font-display text-xl tracking-[-0.02em] text-signal"
                    >
                        No charges yet.
                    </p>
                    <p
                        class="mx-auto mt-2 max-w-sm text-sm leading-relaxed text-signal-muted"
                    >
                        A charge is recorded only after an image is delivered.
                    </p>
                    <Link
                        href="/studio"
                        class="link-underline mt-4 inline-block font-mono text-[0.6rem] tracking-[0.12em] text-signal uppercase"
                    >
                        Make your first image →
                    </Link>
                </div>
            </section>

            <p
                class="max-w-2xl border-t border-line pt-5 text-[0.65rem] leading-relaxed text-signal-muted"
            >
                Figures reflect completed generations and their immutable charge
                records. Active generations are excluded until the provider
                reports final usage. Amounts are shown in USD; the exact
                nine-decimal figure is kept in the ledger and on each plate.
            </p>
        </div>
    </div>
</template>
