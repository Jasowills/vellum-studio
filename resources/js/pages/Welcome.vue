<script setup lang="ts">
import { Head, Link, usePage } from '@inertiajs/vue3';
import { ArrowRight, ArrowUpRight } from '@lucide/vue';
import { computed } from 'vue';
import BrandMark from '@/components/BrandMark.vue';

const page = usePage();
const authenticated = computed(() => Boolean(page.props.auth.user));
</script>

<template>
    <Head title="Images worth keeping" />

    <div class="min-h-svh overflow-x-clip bg-background">
        <header class="border-b border-line/70">
            <div
                class="mx-auto flex h-[4.75rem] max-w-[1320px] items-center justify-between px-5 sm:px-8 lg:px-12"
            >
                <Link href="/" aria-label="Vellum home">
                    <BrandMark />
                </Link>
                <nav
                    class="hidden items-center gap-8 text-[0.8rem] text-ink-soft md:flex"
                    aria-label="Landing"
                >
                    <a href="#room" class="transition-colors hover:text-ink"
                        >The room</a
                    >
                    <a href="#plates" class="transition-colors hover:text-ink"
                        >Plates</a
                    >
                    <a href="#ledger" class="transition-colors hover:text-ink"
                        >The ledger</a
                    >
                </nav>
                <div class="flex items-center gap-3">
                    <Link
                        v-if="!authenticated"
                        href="/login"
                        class="hidden px-3 py-2 text-[0.8rem] font-medium text-ink-soft transition-colors hover:text-ink sm:inline-block"
                        >Sign in</Link
                    >
                    <Link
                        :href="authenticated ? '/studio' : '/register'"
                        class="inline-flex items-center gap-2 border border-ink bg-ink px-4 py-2 text-[0.78rem] font-medium text-paper transition-colors hover:bg-vermilion"
                    >
                        {{
                            authenticated
                                ? 'Enter the studio'
                                : 'Request access'
                        }}
                        <ArrowUpRight class="size-3.5" aria-hidden="true" />
                    </Link>
                </div>
            </div>
        </header>

        <main>
            <section
                class="mx-auto grid max-w-[1320px] gap-14 px-5 pt-14 pb-20 sm:px-8 lg:grid-cols-[1.02fr_0.98fr] lg:items-center lg:gap-10 lg:px-12 lg:pt-20 lg:pb-28"
                aria-labelledby="hero-title"
            >
                <div class="animate-rise relative z-10">
                    <p class="eyebrow flex items-center gap-3">
                        <span
                            class="h-px w-8 bg-vermilion"
                            aria-hidden="true"
                        />
                        A small image studio · Est. 2026
                    </p>
                    <h1
                        id="hero-title"
                        class="display-title mt-7 max-w-[11ch] text-[clamp(3.6rem,7.4vw,6.4rem)] text-ink"
                    >
                        Make an image
                        <em class="text-vermilion">worth</em> keeping.
                    </h1>
                    <p
                        class="mt-8 max-w-[33rem] text-[1.02rem] leading-[1.65] text-ink-soft"
                    >
                        Vellum is a quiet studio for considered images. Write
                        the prompt, choose the proportion, and let Pixelforge do
                        the work. The result is yours to keep — with a ledger
                        that never blurs the numbers.
                    </p>
                    <div class="mt-9 flex flex-wrap items-center gap-4">
                        <Link
                            :href="authenticated ? '/studio' : '/register'"
                            class="group inline-flex items-center gap-3 bg-ink px-6 py-3.5 text-sm font-medium text-paper transition-colors hover:bg-vermilion"
                        >
                            {{
                                authenticated
                                    ? 'Open the studio'
                                    : 'Start making'
                            }}
                            <ArrowRight
                                class="size-4 transition-transform group-hover:translate-x-0.5"
                                aria-hidden="true"
                            />
                        </Link>
                        <a
                            href="#plates"
                            class="link-underline text-sm font-medium text-ink"
                            >See the plates</a
                        >
                    </div>
                    <dl
                        class="mt-10 grid max-w-lg grid-cols-3 gap-4 border-t border-line pt-5 font-mono text-[0.6rem] tracking-[0.12em] text-ink-soft uppercase"
                    >
                        <div>
                            <dt>Engine</dt>
                            <dd class="mt-1.5 text-ink">Pixelforge</dd>
                        </div>
                        <div>
                            <dt>Accounting</dt>
                            <dd class="mt-1.5 text-ink">Nine decimals</dd>
                        </div>
                        <div>
                            <dt>Failed work</dt>
                            <dd class="mt-1.5 text-ink">Always free</dd>
                        </div>
                    </dl>
                </div>

                <section
                    class="animate-rise relative min-h-[30rem] sm:min-h-[38rem] lg:min-h-[42rem]"
                    aria-label="Selected plates from the studio"
                >
                    <div
                        class="absolute top-0 right-0 h-[72%] w-[74%] overflow-hidden border border-line bg-paper-deep p-2 shadow-[0_36px_90px_-52px_rgba(33,29,23,0.55)] sm:w-[68%]"
                    >
                        <img
                            src="/images/landing-01.jpg"
                            alt="An abstract Pixelforge plate: deep green bands above a broad cobalt-blue field crossed by organic green shapes."
                            class="h-full w-full object-cover"
                        />
                        <div
                            class="absolute right-2 bottom-2 left-2 bg-paper px-3 py-2 font-mono text-[0.62rem] leading-relaxed tracking-[0.08em] text-ink uppercase"
                        >
                            <p class="line-clamp-2">
                                Prompt · “A pale stone observatory above a sea
                                of cloud at first light, soft morning haze, fine
                                film grain, quiet and still.”
                            </p>
                            <div
                                class="mt-1.5 flex items-center justify-between border-t border-line pt-1.5"
                            >
                                <span>Plate 01 / abstract study</span>
                                <span class="text-ink">1024 × 1536</span>
                            </div>
                        </div>
                    </div>

                    <div
                        class="absolute bottom-0 left-0 w-[62%] -rotate-2 border border-line bg-paper p-2 shadow-[0_28px_70px_-42px_rgba(33,29,23,0.6)] sm:w-[54%]"
                    >
                        <img
                            src="/images/landing-02.jpg"
                            alt="An abstract cobalt-and-teal Pixelforge plate with a thin vertical cyan seam and a small green wedge at lower left."
                            class="aspect-[4/3] w-full object-cover"
                        />
                        <div
                            class="px-1 pt-2 font-mono text-[0.55rem] leading-relaxed tracking-[0.08em] text-ink-soft uppercase"
                        >
                            <p class="line-clamp-2">
                                Prompt · “A study of folded vermilion fabric on
                                a warm stone table, raking afternoon light, deep
                                soft shadow, editorial still life.”
                            </p>
                            <div
                                class="mt-1 flex items-center justify-between border-t border-line pt-1"
                            >
                                <span>Plate 02 / abstract study</span>
                                <span class="text-ink">$0.399315625</span>
                            </div>
                        </div>
                    </div>

                    <div
                        class="absolute right-2 bottom-[8%] w-[70%] border border-ink/15 bg-paper px-4 py-4 shadow-[0_18px_44px_-32px_rgba(33,29,23,0.5)] sm:right-[4%] sm:w-[54%]"
                    >
                        <div
                            class="flex items-center justify-between border-b border-line pb-2 font-mono text-[0.55rem] tracking-[0.14em] text-ink-soft uppercase"
                        >
                            <span>Studio ledger</span>
                            <span>USD</span>
                        </div>
                        <dl class="mt-3 space-y-2 font-mono text-[0.65rem]">
                            <div class="flex justify-between">
                                <dt class="text-ink-soft">Provider</dt>
                                <dd class="text-ink">$0.279347500</dd>
                            </div>
                            <div class="flex justify-between">
                                <dt class="text-ink-soft">Studio fee</dt>
                                <dd class="text-ink">$0.069836875</dd>
                            </div>
                            <div
                                class="flex justify-between border-t border-line pt-2 text-[0.72rem]"
                            >
                                <dt class="text-ink">Charged</dt>
                                <dd class="text-vermilion">$0.349184375</dd>
                            </div>
                        </dl>
                    </div>
                </section>
            </section>

            <section
                id="room"
                class="border-y border-line bg-paper"
                aria-labelledby="room-title"
            >
                <div
                    class="mx-auto max-w-[1320px] px-5 py-20 sm:px-8 lg:px-12 lg:py-28"
                >
                    <div class="grid gap-12 lg:grid-cols-[0.8fr_1.2fr]">
                        <div>
                            <p class="eyebrow">The room</p>
                            <h2
                                id="room-title"
                                class="display-title mt-5 max-w-[11ch] text-[clamp(2.6rem,4.6vw,4.4rem)] text-ink"
                            >
                                A studio, not a slot machine.
                            </h2>
                        </div>
                        <div class="grid gap-x-10 gap-y-10 sm:grid-cols-2">
                            <article class="border-t border-line pt-5">
                                <span
                                    class="font-mono text-[0.6rem] tracking-[0.16em] text-vermilion uppercase"
                                    >01 / Compose</span
                                >
                                <h3
                                    class="mt-4 font-display text-2xl tracking-[-0.02em] text-ink"
                                >
                                    Begin with language.
                                </h3>
                                <p
                                    class="mt-3 text-sm leading-relaxed text-ink-soft"
                                >
                                    Prompts up to 4,000 characters. Add up to
                                    four reference URLs for direction. Nothing
                                    is generated until the setup reads right.
                                </p>
                            </article>
                            <article class="border-t border-line pt-5">
                                <span
                                    class="font-mono text-[0.6rem] tracking-[0.16em] text-vermilion uppercase"
                                    >02 / Choose</span
                                >
                                <h3
                                    class="mt-4 font-display text-2xl tracking-[-0.02em] text-ink"
                                >
                                    One of three proportions.
                                </h3>
                                <p
                                    class="mt-3 text-sm leading-relaxed text-ink-soft"
                                >
                                    Square, landscape, or portrait. Three
                                    qualities from a quick sketch to a gallery
                                    finish. The estimate moves with every
                                    choice.
                                </p>
                            </article>
                            <article class="border-t border-line pt-5">
                                <span
                                    class="font-mono text-[0.6rem] tracking-[0.16em] text-vermilion uppercase"
                                    >03 / Keep</span
                                >
                                <h3
                                    class="mt-4 font-display text-2xl tracking-[-0.02em] text-ink"
                                >
                                    The plate is archived.
                                </h3>
                                <p
                                    class="mt-3 text-sm leading-relaxed text-ink-soft"
                                >
                                    Provider links expire after 24 hours, so
                                    Vellum archives every finished image
                                    privately and serves it back through your
                                    own library.
                                </p>
                            </article>
                            <article class="border-t border-line pt-5">
                                <span
                                    class="font-mono text-[0.6rem] tracking-[0.16em] text-vermilion uppercase"
                                    >04 / Account</span
                                >
                                <h3
                                    class="mt-4 font-display text-2xl tracking-[-0.02em] text-ink"
                                >
                                    Every token is counted.
                                </h3>
                                <p
                                    class="mt-3 text-sm leading-relaxed text-ink-soft"
                                >
                                    The provider reports usage after completion.
                                    We price it to the nanodollar, add a flat
                                    25% studio fee, and keep an immutable charge
                                    record. Failures are free.
                                </p>
                            </article>
                        </div>
                    </div>
                </div>
            </section>

            <section
                id="plates"
                class="mx-auto max-w-[1320px] px-5 py-20 sm:px-8 lg:px-12 lg:py-28"
                aria-labelledby="plates-title"
            >
                <div class="flex flex-wrap items-end justify-between gap-6">
                    <div>
                        <p class="eyebrow">From the studio</p>
                        <h2
                            id="plates-title"
                            class="display-title mt-5 text-[clamp(2.6rem,4.6vw,4.4rem)] text-ink"
                        >
                            Plates, not posts.
                        </h2>
                    </div>
                    <Link
                        :href="authenticated ? '/library' : '/register'"
                        class="link-underline pb-2 text-sm font-medium text-ink"
                        >Open the collection</Link
                    >
                </div>
                <p class="mt-5 max-w-2xl text-sm leading-relaxed text-ink-soft">
                    Pixelforge returns abstract plates captioned with the
                    submitted prompt. These are the plates it actually returned
                    for the prompts below, shown as received — not literal
                    illustrations of the words.
                </p>

                <div class="mt-12 grid gap-6 md:grid-cols-12">
                    <figure class="md:col-span-5">
                        <div class="border border-line bg-paper p-2">
                            <img
                                src="/images/landing-03.jpg"
                                alt="An abstract green Pixelforge plate with a broad blue arc across the center and softly mottled edges."
                                class="aspect-[4/5] w-full object-cover"
                                loading="lazy"
                            />
                        </div>
                        <figcaption
                            class="mt-4 flex items-start justify-between gap-4 text-xs text-ink-soft"
                        >
                            <span class="max-w-[38ch] leading-relaxed"
                                >Prompt · “A quiet reading room with one wooden
                                chair, long afternoon light across warm paper
                                walls, dust in the air, empty and calm.”</span
                            >
                            <span
                                class="ledger shrink-0 text-[0.65rem] text-ink"
                                >$0.389215625</span
                            >
                        </figcaption>
                    </figure>
                    <figure class="md:col-span-4 md:mt-16">
                        <div class="border border-line bg-paper p-2">
                            <img
                                src="/images/landing-04.jpg"
                                alt="An abstract emerald Pixelforge plate with a blue wedge at upper left and a pale lime-green area through the center."
                                class="aspect-square w-full object-cover"
                                loading="lazy"
                            />
                        </div>
                        <figcaption
                            class="mt-4 flex items-start justify-between gap-4 text-xs text-ink-soft"
                        >
                            <span class="max-w-[32ch] leading-relaxed"
                                >Prompt · “A close study of a vermilion ceramic
                                bowl holding pale fruit, painterly still life,
                                soft directional shadow, warm neutral
                                ground.”</span
                            >
                            <span
                                class="ledger shrink-0 text-[0.65rem] text-ink"
                                >$0.264787500</span
                            >
                        </figcaption>
                    </figure>
                    <figure class="md:col-span-3 md:mt-32">
                        <div class="border border-line bg-paper p-2">
                            <img
                                src="/images/landing-05.jpg"
                                alt="An abstract deep-blue and violet Pixelforge plate with a violet plume at right and a muted blue-gray patch at lower left."
                                class="aspect-[3/4] w-full object-cover"
                                loading="lazy"
                            />
                        </div>
                        <figcaption
                            class="mt-4 flex items-start justify-between gap-4 text-xs text-ink-soft"
                        >
                            <span class="max-w-[28ch] leading-relaxed"
                                >Prompt · “An abstract study of folded paper and
                                deep architectural shadow, cream and bone tones,
                                hard edge of daylight, minimal
                                composition.”</span
                            >
                            <span
                                class="ledger shrink-0 text-[0.65rem] text-ink"
                                >$0.407356250</span
                            >
                        </figcaption>
                    </figure>
                </div>
            </section>

            <section
                id="ledger"
                class="border-y border-line bg-ink text-paper"
                aria-labelledby="ledger-title"
            >
                <div
                    class="mx-auto grid max-w-[1320px] gap-14 px-5 py-20 sm:px-8 lg:grid-cols-[0.95fr_1.05fr] lg:items-center lg:px-12 lg:py-28"
                >
                    <div>
                        <p
                            class="font-mono text-[0.65rem] tracking-[0.18em] text-paper/50 uppercase"
                        >
                            The ledger
                        </p>
                        <h2
                            id="ledger-title"
                            class="mt-5 max-w-[13ch] font-display text-[clamp(2.6rem,4.6vw,4.4rem)] leading-[0.98] tracking-[-0.04em] text-paper"
                        >
                            Honest money, down to the nanodollar.
                        </h2>
                        <p
                            class="mt-7 max-w-lg text-sm leading-relaxed text-paper/60"
                        >
                            Pixelforge bills by token. Vellum records the exact
                            provider cost, applies one flat 25% studio fee, and
                            shows the final charge to nine decimal places. No
                            subscriptions, no hidden margin, no charge for work
                            that never arrived.
                        </p>
                        <ul
                            class="mt-8 space-y-3 font-mono text-[0.65rem] tracking-[0.12em] text-paper/65 uppercase"
                        >
                            <li class="flex items-center gap-3">
                                <span
                                    class="size-1.5 bg-vermilion"
                                    aria-hidden="true"
                                />
                                Token usage from the provider
                            </li>
                            <li class="flex items-center gap-3">
                                <span
                                    class="size-1.5 bg-vermilion"
                                    aria-hidden="true"
                                />
                                One immutable ledger row per success
                            </li>
                            <li class="flex items-center gap-3">
                                <span
                                    class="size-1.5 bg-vermilion"
                                    aria-hidden="true"
                                />
                                Webhooks verified, deduplicated, reconciled
                            </li>
                        </ul>
                    </div>

                    <div class="relative mx-auto w-full max-w-md">
                        <div
                            class="absolute inset-x-6 -bottom-3 h-full border border-paper/15"
                            aria-hidden="true"
                        />
                        <div
                            class="relative border border-paper/25 bg-paper px-6 py-7 text-ink shadow-[0_30px_80px_-40px_rgba(0,0,0,0.6)] sm:px-8"
                        >
                            <div
                                class="flex items-center justify-between border-b border-line pb-4 font-mono text-[0.58rem] tracking-[0.15em] text-ink-soft uppercase"
                            >
                                <span>Vellum studio</span>
                                <span>Receipt / USD</span>
                            </div>
                            <div class="space-y-3 py-5 font-mono text-xs">
                                <div class="flex justify-between">
                                    <span class="text-ink-soft"
                                        >Input text · 115</span
                                    >
                                    <span>$0.000575000</span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-ink-soft"
                                        >Input image · 1,530</span
                                    >
                                    <span>$0.015300000</span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-ink-soft"
                                        >Output · 6,450</span
                                    >
                                    <span>$0.258000000</span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-ink-soft"
                                        >Reasoning · 8,000</span
                                    >
                                    <span>$0.100000000</span>
                                </div>
                                <div
                                    class="flex justify-between border-t border-line pt-3"
                                >
                                    <span class="text-ink-soft"
                                        >Provider cost</span
                                    >
                                    <span>$0.373875000</span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-ink-soft"
                                        >Studio fee · 25%</span
                                    >
                                    <span>$0.093468750</span>
                                </div>
                            </div>
                            <div
                                class="flex items-end justify-between border-t border-ink/20 pt-4"
                            >
                                <span
                                    class="font-mono text-[0.6rem] tracking-[0.15em] text-ink-soft uppercase"
                                    >Charged</span
                                >
                                <span
                                    class="font-display text-3xl tracking-[-0.03em] text-vermilion"
                                    >$0.467343750</span
                                >
                            </div>
                            <p
                                class="mt-5 border-t border-line pt-4 font-mono text-[0.55rem] leading-relaxed tracking-[0.06em] text-ink-soft uppercase"
                            >
                                Worked maximum · high / 1536×1024 / 400
                                characters / 2 references
                            </p>
                        </div>
                    </div>
                </div>
            </section>

            <section
                class="mx-auto max-w-[1320px] px-5 py-20 sm:px-8 lg:px-12 lg:py-28"
            >
                <div
                    class="flex flex-col items-start justify-between gap-8 border border-line bg-paper p-8 md:flex-row md:items-center md:p-12"
                >
                    <div>
                        <p class="eyebrow">The next plate</p>
                        <h2
                            class="display-title mt-4 max-w-[16ch] text-[clamp(2.2rem,3.6vw,3.6rem)] text-ink"
                        >
                            Bring an idea. Leave with an image.
                        </h2>
                    </div>
                    <Link
                        :href="authenticated ? '/studio' : '/register'"
                        class="group inline-flex shrink-0 items-center gap-3 bg-vermilion px-6 py-3.5 text-sm font-medium text-paper transition-colors hover:bg-ink"
                    >
                        {{
                            authenticated ? 'Open the studio' : 'Request access'
                        }}
                        <ArrowRight
                            class="size-4 transition-transform group-hover:translate-x-0.5"
                            aria-hidden="true"
                        />
                    </Link>
                </div>
            </section>
        </main>

        <footer class="border-t border-line">
            <div
                class="mx-auto flex max-w-[1320px] flex-col gap-6 px-5 py-9 sm:px-8 md:flex-row md:items-center md:justify-between lg:px-12"
            >
                <div class="flex items-center gap-4">
                    <BrandMark compact />
                    <span class="text-xs text-ink-soft"
                        >A studio for considered images.</span
                    >
                </div>
                <div
                    class="flex flex-wrap gap-x-6 gap-y-2 font-mono text-[0.6rem] tracking-[0.12em] text-ink-soft uppercase"
                >
                    <Link href="/login" class="hover:text-vermilion"
                        >Sign in</Link
                    >
                    <Link href="/register" class="hover:text-vermilion"
                        >Request access</Link
                    >
                    <a
                        href="https://pixelforge.promptedit.dev/docs"
                        target="_blank"
                        rel="noreferrer"
                        class="hover:text-vermilion"
                        >Pixelforge API ↗</a
                    >
                </div>
            </div>
        </footer>
    </div>
</template>
