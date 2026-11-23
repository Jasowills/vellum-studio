<script setup lang="ts">
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { ArrowRight, ArrowUpRight } from '@lucide/vue';
import { computed, ref } from 'vue';
import BrandMark from '@/components/BrandMark.vue';
import Reveal from '@/components/Reveal.vue';
import SignalField from '@/components/SignalField.vue';
import { queuePromptForStudio } from '@/lib/pendingPrompt';

const page = usePage();
const authenticated = computed(() => Boolean(page.props.auth.user));

const prompt = ref('');
const promptError = ref('');

function compose(): void {
    const value = prompt.value.trim();

    if (!value) {
        promptError.value = 'Write an intention first.';
        return;
    }

    promptError.value = '';
    queuePromptForStudio(value);
    router.visit(authenticated.value ? '/studio' : '/login');
}
</script>

<template>
    <Head title="From signal to imagination" />

    <div class="min-h-svh overflow-x-clip bg-background text-foreground">
        <a
            href="#main"
            class="sr-only focus:not-sr-only focus:fixed focus:top-3 focus:left-3 focus:z-50 focus:bg-signal focus:px-4 focus:py-2 focus:text-signal-invert"
        >
            Skip to content
        </a>

        <header
            class="fixed inset-x-0 top-0 z-40 border-b border-line/70 bg-background/95"
        >
            <div
                class="mx-auto flex h-16 max-w-[1440px] items-center justify-between px-5 sm:px-8 lg:px-12"
            >
                <Link href="/" aria-label="Vellum home" class="focus-ring">
                    <BrandMark />
                </Link>

                <nav
                    class="hidden items-center gap-8 font-mono text-[0.65rem] tracking-[0.16em] text-signal-muted uppercase md:flex"
                    aria-label="Landing sections"
                >
                    <a
                        href="#signal"
                        class="transition-colors hover:text-signal"
                        >Signal</a
                    >
                    <a href="#work" class="transition-colors hover:text-signal"
                        >Work</a
                    >
                    <a
                        href="#studio"
                        class="transition-colors hover:text-signal"
                        >Studio</a
                    >
                    <a href="#cost" class="transition-colors hover:text-signal"
                        >Cost</a
                    >
                </nav>

                <Link
                    :href="authenticated ? '/studio' : '/login'"
                    class="focus-ring inline-flex items-center gap-2 border border-signal px-3.5 py-2 font-mono text-[0.65rem] tracking-[0.14em] text-signal uppercase transition-colors hover:bg-signal hover:text-signal-invert"
                >
                    {{ authenticated ? 'Studio' : 'Sign in' }}
                    <ArrowUpRight class="size-3.5" aria-hidden="true" />
                </Link>
            </div>
        </header>

        <main id="main">
            <section
                class="relative mx-auto grid min-h-[100dvh] max-w-[1440px] items-center px-5 pt-24 pb-12 sm:px-8 lg:grid-cols-[1.04fr_0.96fr] lg:gap-6 lg:px-12 lg:pt-24 lg:pb-16"
                aria-labelledby="hero-title"
            >
                <div
                    class="signal-verticals pointer-events-none absolute inset-0 hidden lg:block"
                    aria-hidden="true"
                />

                <div class="relative z-10 max-w-[46rem]">
                    <p class="eyebrow flex items-center gap-3">
                        <span class="h-px w-8 bg-signal" aria-hidden="true" />
                        01 / Arrival
                    </p>

                    <h1
                        id="hero-title"
                        class="display-title mt-7 max-w-[10ch] text-[clamp(3.2rem,6.5vw,5.9rem)] text-signal"
                    >
                        From signal to imagination.
                    </h1>

                    <p
                        class="mt-7 max-w-[34rem] text-[0.98rem] leading-[1.7] text-signal-muted"
                    >
                        Type an intention. Vellum turns it into an original
                        image, with the cost shown before anything runs.
                    </p>

                    <form class="mt-9 max-w-[38rem]" @submit.prevent="compose">
                        <label
                            for="landing-prompt"
                            class="font-mono text-[0.62rem] tracking-[0.18em] text-signal-muted uppercase"
                        >
                            Compose an intention
                        </label>
                        <div
                            class="mt-2 flex flex-col border border-signal-line bg-paper focus-within:border-signal sm:flex-row"
                        >
                            <input
                                id="landing-prompt"
                                v-model="prompt"
                                type="text"
                                maxlength="4000"
                                autocomplete="off"
                                placeholder="A stone observatory above a sea of cloud at first light."
                                class="min-w-0 flex-1 bg-transparent px-4 py-4 text-sm text-signal outline-none placeholder:text-signal-faint"
                                :aria-invalid="promptError ? 'true' : undefined"
                                :aria-describedby="
                                    promptError
                                        ? 'landing-prompt-error'
                                        : undefined
                                "
                                @input="promptError = ''"
                            />
                            <button
                                type="submit"
                                class="group focus-ring inline-flex shrink-0 items-center justify-center gap-2 border-t border-signal-line bg-signal px-5 py-4 font-mono text-[0.65rem] tracking-[0.16em] text-signal-invert uppercase transition-colors hover:bg-transparent hover:text-signal sm:border-t-0 sm:border-l"
                            >
                                Start
                                <ArrowRight
                                    class="size-3.5 transition-transform group-hover:translate-x-0.5"
                                    aria-hidden="true"
                                />
                            </button>
                        </div>
                        <p
                            v-if="promptError"
                            id="landing-prompt-error"
                            class="mt-2 font-mono text-[0.65rem] text-signal"
                            role="alert"
                        >
                            {{ promptError }}
                        </p>
                        <p
                            v-else
                            class="mt-2 font-mono text-[0.6rem] tracking-[0.12em] text-signal-faint uppercase"
                        >
                            Your prompt follows you into the studio
                        </p>
                    </form>
                </div>

                <div class="relative z-10 mt-10 lg:mt-0">
                    <div
                        class="relative aspect-[1/0.92] border border-line bg-paper sm:aspect-[1/0.78]"
                    >
                        <div
                            class="signal-grid absolute inset-0 opacity-60"
                            aria-hidden="true"
                        />
                        <SignalField mode="hero" class="absolute inset-0" />

                        <div
                            class="absolute inset-x-0 top-0 flex items-center justify-between border-b border-line/70 px-3 py-2 font-mono text-[0.55rem] tracking-[0.16em] text-signal-muted uppercase"
                        >
                            <span>Signal field</span>
                            <span>Live model / 01</span>
                        </div>

                        <div
                            class="absolute right-3 bottom-3 left-3 flex items-end justify-between gap-4 border-t border-line/70 pt-3 font-mono text-[0.55rem] tracking-[0.14em] text-signal-muted uppercase"
                        >
                            <span>Intent → waveform → plate</span>
                            <span class="text-signal">Vellum / 2026</span>
                        </div>

                        <div
                            class="pointer-events-none absolute inset-y-0 left-1/2 w-px bg-signal/10"
                            aria-hidden="true"
                        />
                    </div>
                </div>
            </section>

            <section
                id="signal"
                class="border-y border-line bg-paper-deep"
                aria-labelledby="signal-title"
            >
                <div
                    class="mx-auto max-w-[1440px] px-5 py-20 sm:px-8 lg:px-12 lg:py-28"
                >
                    <Reveal>
                        <h2
                            id="signal-title"
                            class="display-title max-w-[18ch] text-[clamp(2.4rem,4.8vw,4.4rem)] text-signal"
                        >
                            <span
                                class="font-mono text-[0.45em] tracking-[0.2em] text-signal-muted"
                                >02 / </span
                            >From signal
                        </h2>
                    </Reveal>

                    <div
                        class="mt-14 grid gap-0 border-t border-line md:grid-cols-3"
                    >
                        <Reveal
                            v-for="(stage, index) in [
                                {
                                    label: 'Intent',
                                    title: 'Language first.',
                                    copy: 'Describe the image you want. Up to 4,000 characters, with optional references for direction.',
                                    code: 'IN / 01',
                                },
                                {
                                    label: 'Signal',
                                    title: 'A field takes shape.',
                                    copy: 'The prompt becomes a measured waveform. Size, quality, and references change the estimate immediately.',
                                    code: 'PROCESS / 02',
                                },
                                {
                                    label: 'Plate',
                                    title: 'An image you keep.',
                                    copy: 'The finished plate is archived privately, so an expiring provider link never becomes a lost image.',
                                    code: 'OUT / 03',
                                },
                            ]"
                            :key="stage.code"
                            :delay="index * 90"
                            class="border-b border-line p-6 md:border-r md:border-b-0 md:p-8 last:md:border-r-0"
                        >
                            <div
                                class="flex items-center justify-between font-mono text-[0.6rem] tracking-[0.16em] text-signal-muted uppercase"
                            >
                                <span>{{ stage.label }}</span>
                                <span>{{ stage.code }}</span>
                            </div>
                            <h3
                                class="mt-10 font-display text-[1.7rem] tracking-[-0.02em] text-signal"
                            >
                                {{ stage.title }}
                            </h3>
                            <p
                                class="mt-3 max-w-[30ch] text-sm leading-relaxed text-signal-muted"
                            >
                                {{ stage.copy }}
                            </p>
                        </Reveal>
                    </div>
                </div>
            </section>

            <section
                id="work"
                class="mx-auto max-w-[1440px] px-5 py-20 sm:px-8 lg:px-12 lg:py-28"
                aria-labelledby="work-title"
            >
                <div
                    class="flex flex-col justify-between gap-6 border-b border-line pb-8 md:flex-row md:items-end"
                >
                    <Reveal>
                        <h2
                            id="work-title"
                            class="display-title max-w-[16ch] text-[clamp(2.4rem,4.8vw,4.4rem)] text-signal"
                        >
                            <span
                                class="font-mono text-[0.45em] tracking-[0.2em] text-signal-muted"
                                >03 / </span
                            >The work
                        </h2>
                    </Reveal>
                    <Link
                        :href="authenticated ? '/library' : '/register'"
                        class="link-underline pb-1 font-mono text-[0.65rem] tracking-[0.14em] text-signal uppercase"
                    >
                        Open the collection
                    </Link>
                </div>

                <p
                    class="mt-6 max-w-[62ch] text-sm leading-relaxed text-signal-muted"
                >
                    These plates are real Pixelforge outputs from the Vellum
                    studio. They are shown in monochrome and paired with the
                    prompts that produced them.
                </p>

                <div class="mt-12 grid gap-x-6 gap-y-12 md:grid-cols-12">
                    <Reveal class="md:col-span-5">
                        <figure>
                            <div
                                class="border border-line bg-paper p-2"
                                style="aspect-ratio: 1200 / 1404"
                            >
                                <img
                                    src="/images/landing-03.jpg"
                                    width="1200"
                                    height="1404"
                                    alt="An abstract green plate with a broad blue arc across the centre and softly mottled edges."
                                    class="h-full w-full object-contain contrast-110 grayscale"
                                    loading="lazy"
                                />
                            </div>
                            <figcaption
                                class="mt-4 flex items-start justify-between gap-5 font-mono text-[0.62rem] leading-relaxed text-signal-muted"
                            >
                                <span class="max-w-[42ch]">
                                    “A quiet reading room with one wooden chair,
                                    long afternoon light across warm paper
                                    walls, dust in the air, empty and calm.”
                                </span>
                                <span class="ledger shrink-0 text-signal"
                                    >$0.389215625</span
                                >
                            </figcaption>
                        </figure>
                    </Reveal>

                    <Reveal class="md:col-span-4 md:mt-16" :delay="90">
                        <figure>
                            <div
                                class="border border-line bg-paper p-2"
                                style="aspect-ratio: 1200 / 804"
                            >
                                <img
                                    src="/images/landing-04.jpg"
                                    width="1200"
                                    height="804"
                                    alt="An abstract plate with a dark wedge at upper left and a pale field through the centre."
                                    class="h-full w-full object-contain contrast-110 grayscale"
                                    loading="lazy"
                                />
                            </div>
                            <figcaption
                                class="mt-4 flex items-start justify-between gap-5 font-mono text-[0.62rem] leading-relaxed text-signal-muted"
                            >
                                <span class="max-w-[34ch]">
                                    “A close study of a vermilion ceramic bowl
                                    holding pale fruit, painterly still life,
                                    soft directional shadow, warm neutral
                                    ground.”
                                </span>
                                <span class="ledger shrink-0 text-signal"
                                    >$0.264787500</span
                                >
                            </figcaption>
                        </figure>
                    </Reveal>

                    <Reveal class="md:col-span-3 md:mt-32" :delay="180">
                        <figure>
                            <div
                                class="border border-line bg-paper p-2"
                                style="aspect-ratio: 1200 / 1404"
                            >
                                <img
                                    src="/images/landing-05.jpg"
                                    width="1200"
                                    height="1404"
                                    alt="An abstract plate with a dark plume at right and a muted patch at lower left."
                                    class="h-full w-full object-contain contrast-110 grayscale"
                                    loading="lazy"
                                />
                            </div>
                            <figcaption
                                class="mt-4 flex items-start justify-between gap-5 font-mono text-[0.62rem] leading-relaxed text-signal-muted"
                            >
                                <span class="max-w-[28ch]">
                                    “An abstract study of folded paper and deep
                                    architectural shadow, cream and bone tones,
                                    hard edge of daylight, minimal composition.”
                                </span>
                                <span class="ledger shrink-0 text-signal"
                                    >$0.407356250</span
                                >
                            </figcaption>
                        </figure>
                    </Reveal>

                    <Reveal class="md:col-span-7 md:mt-6" :delay="90">
                        <figure>
                            <div
                                class="border border-line bg-paper p-2"
                                style="aspect-ratio: 1200 / 524"
                            >
                                <img
                                    src="/images/landing-02.jpg"
                                    width="1200"
                                    height="524"
                                    alt="A wide abstract plate with a thin vertical seam and a small wedge at lower left."
                                    class="h-full w-full object-contain contrast-110 grayscale"
                                    loading="lazy"
                                />
                            </div>
                            <figcaption
                                class="mt-4 flex items-start justify-between gap-5 font-mono text-[0.62rem] leading-relaxed text-signal-muted"
                            >
                                <span class="max-w-[52ch]">
                                    “A study of folded vermilion fabric on a
                                    warm stone table, raking afternoon light,
                                    deep soft shadow, editorial still life.”
                                </span>
                                <span class="ledger shrink-0 text-signal"
                                    >$0.399315625</span
                                >
                            </figcaption>
                        </figure>
                    </Reveal>

                    <Reveal class="md:col-span-5 md:mt-6" :delay="180">
                        <figure>
                            <div
                                class="border border-line bg-paper p-2"
                                style="aspect-ratio: 1200 / 1404"
                            >
                                <img
                                    src="/images/landing-01.jpg"
                                    width="1200"
                                    height="1404"
                                    alt="An abstract plate with broad horizontal bands crossed by organic shapes."
                                    class="h-full w-full object-contain contrast-110 grayscale"
                                    loading="lazy"
                                />
                            </div>
                            <figcaption
                                class="mt-4 flex items-start justify-between gap-5 font-mono text-[0.62rem] leading-relaxed text-signal-muted"
                            >
                                <span class="max-w-[38ch]">
                                    “A pale stone observatory above a sea of
                                    cloud at first light, soft morning haze,
                                    fine film grain, quiet and still.”
                                </span>
                                <span class="ledger shrink-0 text-signal"
                                    >1024 × 1536</span
                                >
                            </figcaption>
                        </figure>
                    </Reveal>
                </div>
            </section>

            <section
                id="studio"
                class="border-y border-line bg-paper-deep"
                aria-labelledby="studio-title"
            >
                <div
                    class="mx-auto max-w-[1440px] px-5 py-20 sm:px-8 lg:px-12 lg:py-28"
                >
                    <Reveal>
                        <h2
                            id="studio-title"
                            class="display-title max-w-[18ch] text-[clamp(2.4rem,4.8vw,4.4rem)] text-signal"
                        >
                            <span
                                class="font-mono text-[0.45em] tracking-[0.2em] text-signal-muted"
                                >04 / </span
                            >The studio
                        </h2>
                    </Reveal>

                    <div
                        class="mt-12 grid gap-8 lg:grid-cols-[1fr_18rem] lg:items-start"
                    >
                        <Reveal
                            class="border border-line bg-background p-2 sm:p-3"
                        >
                            <img
                                src="/images/studio-interface.png"
                                width="1440"
                                height="900"
                                alt="The Vellum studio with a prompt composer, image canvas, and live estimate."
                                class="h-auto w-full border border-line object-cover"
                                loading="lazy"
                            />
                        </Reveal>

                        <Reveal :delay="90" class="lg:pt-2">
                            <dl
                                class="divide-y divide-line border-y border-line"
                            >
                                <div
                                    class="grid grid-cols-[3rem_1fr] gap-4 py-4"
                                >
                                    <dt
                                        class="font-mono text-[0.6rem] text-signal-muted"
                                    >
                                        01
                                    </dt>
                                    <dd
                                        class="text-sm leading-relaxed text-signal"
                                    >
                                        An image-first canvas keeps the result
                                        in view.
                                    </dd>
                                </div>
                                <div
                                    class="grid grid-cols-[3rem_1fr] gap-4 py-4"
                                >
                                    <dt
                                        class="font-mono text-[0.6rem] text-signal-muted"
                                    >
                                        02
                                    </dt>
                                    <dd
                                        class="text-sm leading-relaxed text-signal"
                                    >
                                        The composer stays close to the work,
                                        not buried in a form.
                                    </dd>
                                </div>
                                <div
                                    class="grid grid-cols-[3rem_1fr] gap-4 py-4"
                                >
                                    <dt
                                        class="font-mono text-[0.6rem] text-signal-muted"
                                    >
                                        03
                                    </dt>
                                    <dd
                                        class="text-sm leading-relaxed text-signal"
                                    >
                                        Advanced controls open only when you
                                        need them.
                                    </dd>
                                </div>
                                <div
                                    class="grid grid-cols-[3rem_1fr] gap-4 py-4"
                                >
                                    <dt
                                        class="font-mono text-[0.6rem] text-signal-muted"
                                    >
                                        04
                                    </dt>
                                    <dd
                                        class="text-sm leading-relaxed text-signal"
                                    >
                                        The estimate moves with every change
                                        before submission.
                                    </dd>
                                </div>
                            </dl>
                        </Reveal>
                    </div>
                </div>
            </section>

            <section
                id="cost"
                class="mx-auto max-w-[1440px] px-5 py-20 sm:px-8 lg:px-12 lg:py-28"
                aria-labelledby="cost-title"
            >
                <Reveal>
                    <h2
                        id="cost-title"
                        class="display-title max-w-[20ch] text-[clamp(2.4rem,4.8vw,4.4rem)] text-signal"
                    >
                        <span
                            class="font-mono text-[0.45em] tracking-[0.2em] text-signal-muted"
                            >05 / </span
                        >Transparent creation
                    </h2>
                </Reveal>

                <div
                    class="mt-12 grid gap-10 border-t border-line pt-10 lg:grid-cols-[0.9fr_1.1fr]"
                >
                    <Reveal>
                        <p
                            class="max-w-[48ch] text-sm leading-relaxed text-signal-muted"
                        >
                            Pixelforge reports usage after each completed image.
                            Vellum records that provider cost to nine decimal
                            places, adds one flat 25% studio fee, and writes a
                            single immutable charge. Failed work is never
                            charged.
                        </p>
                        <div
                            class="mt-8 flex flex-wrap gap-x-8 gap-y-3 font-mono text-[0.62rem] tracking-[0.14em] text-signal-muted uppercase"
                        >
                            <span>Nine-decimal ledger</span>
                            <span>Verified webhooks</span>
                            <span>Duplicate-safe settlement</span>
                        </div>
                    </Reveal>

                    <Reveal :delay="90">
                        <div class="border border-line bg-paper p-6 sm:p-8">
                            <div
                                class="flex items-center justify-between border-b border-line pb-4 font-mono text-[0.6rem] tracking-[0.16em] text-signal-muted uppercase"
                            >
                                <span>Worked maximum</span>
                                <span>USD / exact</span>
                            </div>
                            <dl class="divide-y divide-line font-mono text-xs">
                                <div
                                    class="flex items-center justify-between gap-6 py-4"
                                >
                                    <dt class="text-signal-muted">
                                        Provider cost
                                    </dt>
                                    <dd class="ledger text-signal">
                                        $0.373875000
                                    </dd>
                                </div>
                                <div
                                    class="flex items-center justify-between gap-6 py-4"
                                >
                                    <dt class="text-signal-muted">
                                        Studio fee / 25%
                                    </dt>
                                    <dd class="ledger text-signal">
                                        $0.093468750
                                    </dd>
                                </div>
                                <div
                                    class="flex items-center justify-between gap-6 py-5"
                                >
                                    <dt
                                        class="text-[0.65rem] tracking-[0.16em] text-signal uppercase"
                                    >
                                        Charged
                                    </dt>
                                    <dd
                                        class="ledger text-base text-signal sm:text-lg"
                                    >
                                        $0.467343750
                                    </dd>
                                </div>
                            </dl>
                            <p
                                class="mt-5 font-mono text-[0.6rem] leading-relaxed tracking-[0.1em] text-signal-faint uppercase"
                            >
                                Worked example from the documented rate card:
                                high quality, 1536 × 1024, 400 characters, two
                                references.
                            </p>
                        </div>
                    </Reveal>
                </div>
            </section>

            <section
                class="border-t border-line bg-paper-deep"
                aria-labelledby="enter-title"
            >
                <div
                    class="mx-auto flex max-w-[1440px] flex-col gap-8 px-5 py-20 sm:px-8 lg:flex-row lg:items-end lg:justify-between lg:px-12 lg:py-24"
                >
                    <Reveal>
                        <p class="eyebrow">06 / Enter</p>
                        <h2
                            id="enter-title"
                            class="display-title mt-5 max-w-[18ch] text-[clamp(2.2rem,4vw,3.8rem)] text-signal"
                        >
                            Bring an idea. Leave with an image.
                        </h2>
                    </Reveal>
                    <Link
                        href="/studio"
                        class="group focus-ring inline-flex shrink-0 items-center justify-center gap-3 bg-signal px-6 py-4 font-mono text-[0.68rem] tracking-[0.16em] text-signal-invert uppercase transition-colors hover:bg-transparent hover:text-signal hover:ring-1 hover:ring-signal"
                    >
                        Enter the studio
                        <ArrowRight
                            class="size-4 transition-transform group-hover:translate-x-0.5"
                            aria-hidden="true"
                        />
                    </Link>
                </div>
            </section>
        </main>

        <footer class="border-t border-line bg-background">
            <div
                class="mx-auto flex max-w-[1440px] flex-col gap-6 px-5 py-9 sm:px-8 md:flex-row md:items-center md:justify-between lg:px-12"
            >
                <BrandMark compact />
                <div
                    class="flex flex-wrap gap-x-6 gap-y-2 font-mono text-[0.6rem] tracking-[0.14em] text-signal-muted uppercase"
                >
                    <Link href="/library" class="hover:text-signal"
                        >Library</Link
                    >
                    <Link href="/usage" class="hover:text-signal">Usage</Link>
                    <a
                        href="https://pixelforge.promptedit.dev/docs"
                        target="_blank"
                        rel="noreferrer"
                        class="hover:text-signal"
                    >
                        Pixelforge API
                    </a>
                </div>
            </div>
        </footer>
    </div>
</template>
