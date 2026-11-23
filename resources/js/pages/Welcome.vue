<script setup lang="ts">
defineOptions({ inheritAttrs: false });

import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { ArrowRight, ArrowUpRight } from '@lucide/vue';
import { computed, ref } from 'vue';
import BrandMark from '@/components/BrandMark.vue';
import SignalField from '@/components/SignalField.vue';
import { queuePromptForStudio } from '@/lib/pendingPrompt';

const page = usePage();
const authenticated = computed(() => Boolean(page.props.auth.user));

const heroPrompt = ref('');
const closingPrompt = ref('');
const heroError = ref('');
const closingError = ref('');

function start(source: 'hero' | 'closing'): void {
    const prompt = source === 'hero' ? heroPrompt : closingPrompt;
    const error = source === 'hero' ? heroError : closingError;
    const value = prompt.value.trim();

    if (!value) {
        error.value = 'Write an intention first.';

        return;
    }

    error.value = '';
    queuePromptForStudio(value);
    router.visit(authenticated.value ? '/studio' : '/login');
}
</script>

<template>
    <Head title="Turn words into worlds" />

    <div class="min-h-svh overflow-x-clip bg-void text-document">
        <a
            href="#main"
            class="sr-only focus:not-sr-only focus:fixed focus:top-3 focus:left-3 focus:z-50 focus:bg-document focus:px-4 focus:py-2 focus:text-void"
        >
            Skip to content
        </a>

        <header
            class="fixed inset-x-0 top-0 z-40 border-b border-edge/80 bg-void/95"
        >
            <div
                class="vellum-shell flex h-18 items-center justify-between gap-3 sm:gap-6"
            >
                <Link
                    href="/"
                    class="focus-ring shrink-0"
                    aria-label="Vellum home"
                >
                    <BrandMark />
                </Link>

                <nav
                    class="flex items-center gap-3 text-xs text-ash sm:gap-8 sm:text-sm"
                    aria-label="Landing navigation"
                >
                    <a
                        href="#explore"
                        class="transition-colors hover:text-document"
                    >
                        Explore
                    </a>
                    <Link
                        href="/studio"
                        class="hidden transition-colors hover:text-document sm:inline"
                    >
                        Studio
                    </Link>
                    <Link
                        href="/library"
                        class="hidden transition-colors hover:text-document sm:inline"
                    >
                        Library
                    </Link>
                </nav>

                <Link
                    :href="authenticated ? '/studio' : '/login'"
                    class="vellum-button focus-ring shrink-0 px-3 py-2 text-[0.68rem] sm:px-4 sm:py-2.5 sm:text-xs"
                >
                    {{ authenticated ? 'Open studio' : 'Sign in' }}
                    <ArrowUpRight class="size-3.5" aria-hidden="true" />
                </Link>
            </div>
        </header>

        <main id="main">
            <section
                class="vellum-shell relative flex min-h-svh flex-col justify-center pt-28 pb-12 lg:grid lg:grid-cols-[1.06fr_0.94fr] lg:items-center lg:gap-10 lg:pt-20 lg:pb-10"
                aria-labelledby="hero-title"
            >
                <div
                    class="vellum-verticals pointer-events-none absolute inset-0 hidden lg:block"
                    aria-hidden="true"
                />

                <div class="relative z-10 max-w-[47rem]">
                    <p class="vellum-meta">Vellum / creative instrument</p>

                    <h1
                        id="hero-title"
                        class="vellum-display mt-6 max-w-[9ch] text-[clamp(3rem,6.4vw,6.25rem)] text-document"
                    >
                        A thought is only the beginning.
                    </h1>

                    <p
                        class="mt-6 max-w-[34rem] text-base leading-[1.65] text-ash sm:text-lg"
                    >
                        An intelligent creative instrument for turning
                        imagination into imagery. Type what you see in your
                        head, then step into a real studio built to make it.
                    </p>

                    <form
                        class="mt-9 max-w-[39rem]"
                        @submit.prevent="start('hero')"
                    >
                        <label
                            for="hero-prompt"
                            class="vellum-meta vellum-meta-strong"
                        >
                            Your prompt
                        </label>
                        <div
                            class="vellum-panel mt-2 bg-elevated transition-colors focus-within:border-document"
                        >
                            <label for="hero-prompt" class="sr-only"
                                >Describe the image you want</label
                            >
                            <textarea
                                id="hero-prompt"
                                v-model="heroPrompt"
                                rows="2"
                                maxlength="4000"
                                autocomplete="off"
                                placeholder="A stone observatory above a sea of cloud at first light."
                                class="block w-full resize-none bg-transparent px-4 py-4 text-[0.98rem] leading-relaxed text-document outline-none placeholder:text-stone sm:text-base"
                                :aria-invalid="heroError ? 'true' : undefined"
                                :aria-describedby="
                                    heroError
                                        ? 'hero-prompt-error'
                                        : 'hero-prompt-help'
                                "
                                @input="heroError = ''"
                                @keydown.enter.exact.prevent="start('hero')"
                            />
                            <div
                                class="flex flex-wrap items-center justify-between gap-3 border-t border-line px-4 py-3"
                            >
                                <p id="hero-prompt-help" class="vellum-meta">
                                    Enter to continue · your prompt follows you
                                    into the studio
                                </p>
                                <button
                                    type="submit"
                                    class="vellum-button focus-ring px-4 py-2.5 text-xs"
                                >
                                    Create
                                    <ArrowRight
                                        class="size-3.5"
                                        aria-hidden="true"
                                    />
                                </button>
                            </div>
                        </div>
                        <p
                            v-if="heroError"
                            id="hero-prompt-error"
                            class="vellum-meta mt-2 text-document"
                            role="alert"
                        >
                            {{ heroError }}
                        </p>
                    </form>

                    <p class="vellum-meta mt-7 hidden lg:block">
                        Turn words into worlds.
                    </p>
                </div>

                <div
                    class="relative z-10 mt-10 lg:mt-0"
                    :class="heroPrompt.trim() ? 'lg:scale-[1.015]' : ''"
                >
                    <div
                        class="relative h-[24rem] sm:h-[30rem] lg:h-[min(72vh,46rem)]"
                    >
                        <div
                            class="vellum-grid pointer-events-none absolute inset-0 opacity-45"
                            aria-hidden="true"
                        />
                        <SignalField
                            mode="hero"
                            class="absolute inset-0 transition-transform duration-500 motion-reduce:transition-none"
                        />

                        <div
                            class="pointer-events-none absolute inset-y-0 left-1/2 w-px bg-line/60"
                            aria-hidden="true"
                        />

                        <p class="vellum-meta absolute top-0 left-0">
                            V / geometry of intention
                        </p>
                        <p
                            class="vellum-meta absolute top-0 right-0 hidden sm:block"
                        >
                            Live instrument · no stock imagery
                        </p>

                        <div
                            class="absolute right-0 bottom-0 left-0 flex items-end justify-between gap-4 border-t border-line pt-3 sm:left-auto sm:w-[19rem]"
                        >
                            <p class="vellum-meta max-w-[26ch]">
                                Prompt enters here. An image comes back from the
                                studio.
                            </p>
                            <a
                                href="#explore"
                                class="focus-ring shrink-0 text-document transition-opacity hover:opacity-70"
                                aria-label="Explore creations"
                            >
                                <ArrowRight
                                    class="size-5 rotate-90"
                                    aria-hidden="true"
                                />
                            </a>
                        </div>
                    </div>
                </div>
            </section>

            <section
                id="explore"
                class="border-y border-line bg-night"
                aria-labelledby="explore-title"
            >
                <div class="vellum-shell py-20 lg:py-28">
                    <div
                        class="grid gap-10 lg:grid-cols-[0.9fr_1.1fr] lg:items-end"
                    >
                        <div>
                            <p class="vellum-meta">The instrument</p>
                            <h2
                                id="explore-title"
                                class="vellum-title mt-5 max-w-[14ch] text-[clamp(2.4rem,4.8vw,4.5rem)]"
                            >
                                Imagine it. Then see it.
                            </h2>
                        </div>
                        <p
                            class="max-w-[48ch] text-base leading-[1.7] text-ash lg:justify-self-end"
                        >
                            Vellum is a working image studio, not a waiting
                            room. The prompt you write here becomes the first
                            field in your studio composer, ready to revise,
                            size, and submit.
                        </p>
                    </div>

                    <div
                        class="mt-14 grid gap-10 border-t border-line pt-10 lg:grid-cols-[1.1fr_0.9fr] lg:gap-16"
                    >
                        <figure>
                            <div class="bg-elevated p-2">
                                <img
                                    src="/images/landing-01.jpg"
                                    width="1200"
                                    height="1404"
                                    alt="A pale stone observatory above a sea of cloud at first light."
                                    class="aspect-[1200/1100] w-full object-cover"
                                    loading="lazy"
                                    decoding="async"
                                />
                            </div>
                            <figcaption
                                class="mt-4 flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between"
                            >
                                <p
                                    class="max-w-[46ch] text-sm leading-relaxed text-ash"
                                >
                                    “A pale stone observatory above a sea of
                                    cloud at first light, soft morning haze,
                                    fine film grain, quiet and still.”
                                </p>
                                <p class="vellum-meta shrink-0">
                                    01M84MAZQKJJBTF7RJDFY0C7W2
                                </p>
                            </figcaption>
                        </figure>

                        <div
                            class="flex flex-col justify-between gap-8 lg:py-4"
                        >
                            <div>
                                <p class="vellum-meta">Input</p>
                                <p
                                    class="mt-3 font-sans text-xl leading-snug text-document"
                                >
                                    Describe light, material, mood, and the
                                    shape of the thing you want to see.
                                </p>
                            </div>
                            <div class="vellum-rule pt-6">
                                <p class="vellum-meta">Output</p>
                                <p
                                    class="mt-3 max-w-[36ch] text-base leading-relaxed text-ash"
                                >
                                    A real Pixelforge plate, archived privately
                                    with its prompt, settings, status, and
                                    settled charge.
                                </p>
                            </div>
                            <Link
                                href="/studio"
                                class="vellum-button vellum-button-quiet focus-ring w-fit px-4 py-3 text-xs"
                            >
                                Go to the studio
                                <ArrowUpRight
                                    class="size-3.5"
                                    aria-hidden="true"
                                />
                            </Link>
                        </div>
                    </div>
                </div>
            </section>

            <section
                class="vellum-shell py-20 lg:py-28"
                aria-labelledby="ideas-title"
            >
                <div
                    class="flex flex-col justify-between gap-6 border-b border-line pb-8 md:flex-row md:items-end"
                >
                    <div>
                        <p class="vellum-meta">Explore creations</p>
                        <h2
                            id="ideas-title"
                            class="vellum-title mt-5 max-w-[16ch] text-[clamp(2.4rem,4.8vw,4.5rem)]"
                        >
                            Everything begins with an idea.
                        </h2>
                    </div>
                    <Link
                        :href="authenticated ? '/library' : '/register'"
                        class="link-underline pb-1 text-sm text-ash hover:text-document"
                    >
                        Open the collection
                    </Link>
                </div>

                <p class="mt-6 max-w-[66ch] text-sm leading-relaxed text-ash">
                    These are real outputs from the Vellum studio, paired with
                    the prompts and exact charges recorded with them.
                </p>

                <div
                    class="mt-12 grid grid-cols-1 gap-x-8 gap-y-12 md:grid-cols-12"
                >
                    <figure class="md:col-span-7">
                        <div class="bg-elevated p-2">
                            <img
                                src="/images/landing-03.jpg"
                                width="1200"
                                height="1404"
                                alt="A quiet reading room with one wooden chair and long afternoon light."
                                class="aspect-[1200/760] w-full object-cover"
                                loading="lazy"
                                decoding="async"
                            />
                        </div>
                        <figcaption
                            class="mt-4 grid gap-2 sm:grid-cols-[1fr_auto] sm:items-start"
                        >
                            <p
                                class="max-w-[54ch] text-sm leading-relaxed text-ash"
                            >
                                “A quiet reading room with one wooden chair,
                                long afternoon light across warm paper walls,
                                dust in the air, empty and calm.”
                            </p>
                            <p class="ledger text-xs text-document">
                                $0.389215625
                            </p>
                        </figcaption>
                    </figure>

                    <figure class="md:col-span-5 md:mt-12">
                        <div class="bg-elevated p-2">
                            <img
                                src="/images/landing-05.jpg"
                                width="1200"
                                height="1404"
                                alt="An abstract study of folded paper and deep architectural shadow."
                                class="aspect-[1200/1180] w-full object-cover"
                                loading="lazy"
                                decoding="async"
                            />
                        </div>
                        <figcaption
                            class="mt-4 grid gap-2 sm:grid-cols-[1fr_auto] sm:items-start"
                        >
                            <p
                                class="max-w-[42ch] text-sm leading-relaxed text-ash"
                            >
                                “An abstract study of folded paper and deep
                                architectural shadow, cream and bone tones, hard
                                edge of daylight, minimal composition.”
                            </p>
                            <p class="ledger text-xs text-document">
                                $0.407356250
                            </p>
                        </figcaption>
                    </figure>

                    <figure class="md:col-span-5">
                        <div class="bg-elevated p-2">
                            <img
                                src="/images/landing-04.jpg"
                                width="1200"
                                height="804"
                                alt="A close study of a ceramic bowl holding pale fruit."
                                class="aspect-[1200/804] w-full object-cover"
                                loading="lazy"
                                decoding="async"
                            />
                        </div>
                        <figcaption
                            class="mt-4 grid gap-2 sm:grid-cols-[1fr_auto] sm:items-start"
                        >
                            <p
                                class="max-w-[44ch] text-sm leading-relaxed text-ash"
                            >
                                “A close study of a vermilion ceramic bowl
                                holding pale fruit, painterly still life, soft
                                directional shadow, warm neutral ground.”
                            </p>
                            <p class="ledger text-xs text-document">
                                $0.264787500
                            </p>
                        </figcaption>
                    </figure>

                    <figure class="md:col-span-7 md:mt-10">
                        <div class="bg-elevated p-2">
                            <img
                                src="/images/landing-02.jpg"
                                width="1200"
                                height="524"
                                alt="A study of folded fabric on a warm stone table."
                                class="aspect-[1200/524] w-full object-cover"
                                loading="lazy"
                                decoding="async"
                            />
                        </div>
                        <figcaption
                            class="mt-4 grid gap-2 sm:grid-cols-[1fr_auto] sm:items-start"
                        >
                            <p
                                class="max-w-[52ch] text-sm leading-relaxed text-ash"
                            >
                                “A study of folded vermilion fabric on a warm
                                stone table, raking afternoon light, deep soft
                                shadow, editorial still life.”
                            </p>
                            <p class="ledger text-xs text-document">
                                $0.399315625
                            </p>
                        </figcaption>
                    </figure>
                </div>
            </section>

            <section
                class="border-y border-line bg-night"
                aria-labelledby="unexpected-title"
            >
                <div class="vellum-shell py-20 lg:py-28">
                    <div
                        class="grid gap-12 lg:grid-cols-[0.85fr_1.15fr] lg:items-start"
                    >
                        <div class="lg:sticky lg:top-28">
                            <p class="vellum-meta">Inside the studio</p>
                            <h2
                                id="unexpected-title"
                                class="vellum-title mt-5 max-w-[13ch] text-[clamp(2.4rem,4.6vw,4.25rem)]"
                            >
                                Make room for the unexpected.
                            </h2>
                            <p
                                class="mt-6 max-w-[38ch] text-base leading-[1.7] text-ash"
                            >
                                The canvas stays in view while the prompt,
                                settings, cost, and state stay close at hand.
                                Change a setting and the estimate moves with
                                you. Nothing is rounded away.
                            </p>
                        </div>

                        <div>
                            <div class="border border-line bg-void p-2 sm:p-3">
                                <img
                                    src="/images/studio-interface.png"
                                    width="1440"
                                    height="900"
                                    alt="The Vellum studio with a canvas, prompt composer, settings, and live estimate."
                                    class="h-auto w-full border border-edge object-cover"
                                    loading="lazy"
                                    decoding="async"
                                />
                            </div>
                            <dl
                                class="mt-8 grid gap-px border-t border-line bg-line sm:grid-cols-3"
                            >
                                <div class="bg-night px-4 py-5">
                                    <dt class="text-sm text-document">
                                        Canvas first
                                    </dt>
                                    <dd
                                        class="mt-2 text-sm leading-relaxed text-ash"
                                    >
                                        The latest image remains the centre of
                                        the room.
                                    </dd>
                                </div>
                                <div class="bg-night px-4 py-5">
                                    <dt class="text-sm text-document">
                                        Real states
                                    </dt>
                                    <dd
                                        class="mt-2 text-sm leading-relaxed text-ash"
                                    >
                                        Queued, working, ready, or failed — only
                                        when the provider says so.
                                    </dd>
                                </div>
                                <div class="bg-night px-4 py-5">
                                    <dt class="text-sm text-document">
                                        Nothing hidden
                                    </dt>
                                    <dd
                                        class="mt-2 text-sm leading-relaxed text-ash"
                                    >
                                        Prompt, settings, and exact charge
                                        travel with every plate.
                                    </dd>
                                </div>
                            </dl>
                        </div>
                    </div>
                </div>
            </section>

            <section
                class="vellum-shell py-20 lg:py-28"
                aria-labelledby="cost-title"
            >
                <div class="grid gap-12 lg:grid-cols-[0.9fr_1.1fr] lg:gap-20">
                    <div>
                        <p class="vellum-meta">Trust the ledger</p>
                        <h2
                            id="cost-title"
                            class="vellum-title mt-5 max-w-[16ch] text-[clamp(2.4rem,4.6vw,4.25rem)]"
                        >
                            Know what it costs. Keep creating.
                        </h2>
                        <p
                            class="mt-6 max-w-[48ch] text-base leading-[1.7] text-ash"
                        >
                            Pixelforge reports usage after each completed image.
                            Vellum records that cost to nine decimal places,
                            adds one flat 25% studio fee, and writes a single
                            immutable charge. Failed work is never charged.
                        </p>
                        <div
                            class="mt-8 flex flex-wrap gap-x-6 gap-y-2 text-sm text-ash"
                        >
                            <span>Nine-decimal ledger</span>
                            <span>Verified webhooks</span>
                            <span>Duplicate-safe settlement</span>
                        </div>
                    </div>

                    <div class="border border-line bg-elevated p-6 sm:p-8">
                        <div
                            class="flex items-baseline justify-between gap-4 border-b border-line pb-4"
                        >
                            <p class="text-sm text-document">Worked maximum</p>
                            <p class="vellum-meta">USD / exact</p>
                        </div>
                        <dl class="divide-y divide-line">
                            <div
                                class="flex items-baseline justify-between gap-6 py-4"
                            >
                                <dt class="text-sm text-ash">Provider cost</dt>
                                <dd class="ledger text-sm text-document">
                                    $0.373875000
                                </dd>
                            </div>
                            <div
                                class="flex items-baseline justify-between gap-6 py-4"
                            >
                                <dt class="text-sm text-ash">
                                    Studio fee / 25%
                                </dt>
                                <dd class="ledger text-sm text-document">
                                    $0.093468750
                                </dd>
                            </div>
                            <div
                                class="flex items-baseline justify-between gap-6 py-5"
                            >
                                <dt class="text-sm text-document">Charged</dt>
                                <dd
                                    class="ledger text-lg text-document sm:text-xl"
                                >
                                    $0.467343750
                                </dd>
                            </div>
                        </dl>
                        <p class="vellum-meta mt-5">
                            Worked example from the documented rate card: high
                            quality, 1536 × 1024, 400 characters, two
                            references.
                        </p>
                    </div>
                </div>
            </section>

            <section
                class="border-t border-line bg-night"
                aria-labelledby="closing-title"
            >
                <div class="vellum-shell py-20 lg:py-28">
                    <div
                        class="grid gap-10 lg:grid-cols-[1fr_0.85fr] lg:items-end"
                    >
                        <div>
                            <p class="vellum-meta">Enter the studio</p>
                            <h2
                                id="closing-title"
                                class="vellum-title mt-5 max-w-[15ch] text-[clamp(2.5rem,5vw,4.75rem)]"
                            >
                                Let’s see what you imagine.
                            </h2>
                        </div>

                        <form @submit.prevent="start('closing')">
                            <label
                                for="closing-prompt"
                                class="vellum-meta vellum-meta-strong"
                            >
                                Start with a thought
                            </label>
                            <div
                                class="vellum-panel mt-2 bg-void focus-within:border-document"
                            >
                                <label for="closing-prompt" class="sr-only"
                                    >Describe the image you want</label
                                >
                                <textarea
                                    id="closing-prompt"
                                    v-model="closingPrompt"
                                    rows="2"
                                    maxlength="4000"
                                    placeholder="Begin anywhere."
                                    class="block w-full resize-none bg-transparent px-4 py-4 text-base leading-relaxed text-document outline-none placeholder:text-stone"
                                    :aria-invalid="
                                        closingError ? 'true' : undefined
                                    "
                                    :aria-describedby="
                                        closingError
                                            ? 'closing-prompt-error'
                                            : undefined
                                    "
                                    @input="closingError = ''"
                                    @keydown.enter.exact.prevent="
                                        start('closing')
                                    "
                                />
                                <div
                                    class="flex justify-end border-t border-line px-4 py-3"
                                >
                                    <button
                                        type="submit"
                                        class="vellum-button focus-ring px-4 py-2.5 text-xs"
                                    >
                                        Begin
                                        <ArrowRight
                                            class="size-3.5"
                                            aria-hidden="true"
                                        />
                                    </button>
                                </div>
                            </div>
                            <p
                                v-if="closingError"
                                id="closing-prompt-error"
                                class="vellum-meta mt-2 text-document"
                                role="alert"
                            >
                                {{ closingError }}
                            </p>
                        </form>
                    </div>
                </div>
            </section>
        </main>

        <footer class="border-t border-line bg-void">
            <div
                class="vellum-shell flex flex-col gap-6 py-8 sm:flex-row sm:items-center sm:justify-between"
            >
                <BrandMark compact />
                <nav
                    class="flex flex-wrap gap-x-6 gap-y-2 text-sm text-ash"
                    aria-label="Footer navigation"
                >
                    <a href="#explore" class="hover:text-document">Explore</a>
                    <Link href="/studio" class="hover:text-document"
                        >Studio</Link
                    >
                    <Link href="/library" class="hover:text-document"
                        >Library</Link
                    >
                    <Link href="/usage" class="hover:text-document">Usage</Link>
                </nav>
                <p class="vellum-meta">Turn words into worlds.</p>
            </div>
        </footer>
    </div>
</template>
