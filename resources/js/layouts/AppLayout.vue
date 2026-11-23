<script setup lang="ts">
import { Link, router, usePage } from '@inertiajs/vue3';
import {
    ChevronDown,
    LogOut,
    Menu,
    Settings,
    ShieldCheck,
    Sparkles,
    X,
} from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import BrandMark from '@/components/BrandMark.vue';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import type { BreadcrumbItem } from '@/types';

type Props = {
    breadcrumbs?: BreadcrumbItem[];
};

withDefaults(defineProps<Props>(), {
    breadcrumbs: () => [],
});

const page = usePage();
const user = computed(() => page.props.auth.user);
const isAdmin = computed(() => Boolean(user.value?.is_admin));
const mobileOpen = ref(false);
const flashDismissed = ref(false);

const path = computed(() => page.url.split('?')[0]);

const navItems = computed(() => {
    const items = [
        { label: 'Studio', href: '/studio', active: path.value === '/studio' },
        {
            label: 'Library',
            href: '/library',
            active: path.value.startsWith('/library'),
        },
        {
            label: 'Usage',
            href: '/usage',
            active: path.value.startsWith('/usage'),
        },
    ];

    if (isAdmin.value) {
        items.push({
            label: 'Operator',
            href: '/admin/report',
            active: path.value.startsWith('/admin'),
        });
    }

    return items;
});

const initials = computed(() => {
    const name = user.value?.name ?? 'Vellum';

    return name
        .split(' ')
        .filter(Boolean)
        .slice(0, 2)
        .map((part) => part.charAt(0).toUpperCase())
        .join('');
});

const status = computed(() =>
    flashDismissed.value ? null : page.props.flash?.status,
);
const error = computed(() =>
    flashDismissed.value ? null : page.props.flash?.error,
);

watch(
    () => page.url,
    () => {
        mobileOpen.value = false;
        flashDismissed.value = false;
    },
);

const logout = () => {
    router.flushAll();
    router.post('/logout');
};
</script>

<template>
    <div class="flex min-h-svh flex-col">
        <a
            href="#main"
            class="sr-only z-50 bg-ink px-4 py-2 text-paper focus:not-sr-only focus:fixed focus:top-3 focus:left-3"
        >
            Skip to content
        </a>

        <header
            class="sticky top-0 z-40 border-b border-line/80 bg-background/88 backdrop-blur-md"
        >
            <div
                class="mx-auto flex h-[4.5rem] max-w-[1320px] items-center justify-between gap-6 px-5 sm:px-8 lg:px-12"
            >
                <Link
                    href="/studio"
                    class="focus-ring rounded-sm"
                    aria-label="Vellum studio"
                >
                    <BrandMark />
                </Link>

                <nav
                    class="hidden items-center gap-7 md:flex"
                    aria-label="Primary"
                >
                    <Link
                        v-for="item in navItems"
                        :key="item.href"
                        :href="item.href"
                        class="focus-ring relative py-2 text-[0.82rem] font-medium tracking-[0.01em] transition-colors"
                        :class="
                            item.active
                                ? 'text-ink'
                                : 'text-ink-soft hover:text-ink'
                        "
                        :aria-current="item.active ? 'page' : undefined"
                    >
                        {{ item.label }}
                        <span
                            v-if="item.active"
                            class="absolute right-0 -bottom-[1.15rem] left-0 h-px bg-vermilion"
                            aria-hidden="true"
                        />
                    </Link>
                </nav>

                <div class="flex items-center gap-2 sm:gap-3">
                    <Link
                        href="/studio"
                        class="focus-ring hidden items-center gap-2 border border-ink bg-ink px-4 py-2 text-[0.78rem] font-medium text-paper transition-colors hover:bg-vermilion hover:text-paper sm:inline-flex"
                    >
                        <Sparkles class="size-3.5" aria-hidden="true" />
                        New image
                    </Link>

                    <DropdownMenu>
                        <DropdownMenuTrigger as-child>
                            <button
                                type="button"
                                class="focus-ring flex items-center gap-2 rounded-full p-0.5 pr-2 text-left transition-colors hover:bg-paper-deep/60"
                                aria-label="Account menu"
                            >
                                <span
                                    class="flex size-8 items-center justify-center rounded-full bg-ink font-mono text-[0.68rem] tracking-[0.04em] text-paper"
                                    aria-hidden="true"
                                    >{{ initials }}</span
                                >
                                <span
                                    class="hidden max-w-[9rem] truncate text-xs font-medium text-ink lg:inline"
                                    >{{ user?.name }}</span
                                >
                                <ChevronDown
                                    class="size-3.5 text-ink-soft"
                                    aria-hidden="true"
                                />
                            </button>
                        </DropdownMenuTrigger>
                        <DropdownMenuContent
                            align="end"
                            :side-offset="8"
                            class="w-60 rounded-none border-line bg-paper p-1.5 shadow-[0_24px_60px_-32px_rgba(33,29,23,0.45)]"
                        >
                            <DropdownMenuLabel class="px-2.5 py-2">
                                <span
                                    class="block font-mono text-[0.6rem] tracking-[0.14em] text-ink-soft uppercase"
                                    >Signed in</span
                                >
                                <span
                                    class="mt-1 block truncate text-sm font-medium text-ink"
                                    >{{ user?.email }}</span
                                >
                            </DropdownMenuLabel>
                            <DropdownMenuSeparator class="bg-line" />
                            <DropdownMenuItem
                                v-if="isAdmin"
                                :as-child="true"
                                class="rounded-none focus:bg-paper-deep"
                            >
                                <Link
                                    href="/admin/report"
                                    class="flex w-full cursor-pointer items-center gap-2.5 px-2 py-2 text-sm"
                                >
                                    <ShieldCheck
                                        class="size-4 text-ink-soft"
                                        aria-hidden="true"
                                    />
                                    Operator's ledger
                                </Link>
                            </DropdownMenuItem>
                            <DropdownMenuItem
                                :as-child="true"
                                class="rounded-none focus:bg-paper-deep"
                            >
                                <Link
                                    href="/settings/profile"
                                    class="flex w-full cursor-pointer items-center gap-2.5 px-2 py-2 text-sm"
                                >
                                    <Settings
                                        class="size-4 text-ink-soft"
                                        aria-hidden="true"
                                    />
                                    Settings
                                </Link>
                            </DropdownMenuItem>
                            <DropdownMenuSeparator class="bg-line" />
                            <DropdownMenuItem
                                class="cursor-pointer rounded-none px-2 py-2 text-sm focus:bg-paper-deep"
                                @select="logout"
                            >
                                <LogOut
                                    class="mr-2.5 size-4 text-ink-soft"
                                    aria-hidden="true"
                                />
                                Log out
                            </DropdownMenuItem>
                        </DropdownMenuContent>
                    </DropdownMenu>

                    <button
                        type="button"
                        class="focus-ring inline-flex size-9 items-center justify-center border border-line text-ink md:hidden"
                        :aria-expanded="mobileOpen"
                        aria-controls="mobile-navigation"
                        aria-label="Toggle navigation"
                        @click="mobileOpen = !mobileOpen"
                    >
                        <X
                            v-if="mobileOpen"
                            class="size-4"
                            aria-hidden="true"
                        />
                        <Menu v-else class="size-4" aria-hidden="true" />
                    </button>
                </div>
            </div>

            <div
                v-if="mobileOpen"
                id="mobile-navigation"
                class="border-t border-line bg-paper/95 md:hidden"
            >
                <nav
                    class="mx-auto flex max-w-[1320px] flex-col px-5 py-3 sm:px-8"
                    aria-label="Mobile"
                >
                    <Link
                        v-for="item in navItems"
                        :key="item.href"
                        :href="item.href"
                        class="focus-ring flex items-center justify-between border-b border-line-soft py-3 text-sm"
                        :class="item.active ? 'text-vermilion' : 'text-ink'"
                        :aria-current="item.active ? 'page' : undefined"
                        @click="mobileOpen = false"
                    >
                        {{ item.label }}
                        <span class="font-mono text-[0.6rem]" aria-hidden="true"
                            >↗</span
                        >
                    </Link>
                    <Link
                        href="/studio"
                        class="focus-ring mt-3 inline-flex items-center justify-center gap-2 bg-ink px-4 py-3 text-sm font-medium text-paper"
                        @click="mobileOpen = false"
                    >
                        <Sparkles class="size-4" aria-hidden="true" />
                        Start a new image
                    </Link>
                </nav>
            </div>
        </header>

        <div
            v-if="status || error"
            class="mx-auto w-full max-w-[1320px] px-5 pt-6 sm:px-8 lg:px-12"
        >
            <div
                class="flex items-start justify-between gap-4 border px-4 py-3 text-sm"
                :class="
                    error
                        ? 'border-vermilion/30 bg-vermilion-soft text-vermilion'
                        : 'border-moss/30 bg-moss-soft text-moss'
                "
                :role="error ? 'alert' : 'status'"
            >
                <p>{{ error ?? status }}</p>
                <button
                    type="button"
                    class="focus-ring shrink-0 font-mono text-[0.6rem] tracking-[0.12em] uppercase opacity-70 hover:opacity-100"
                    aria-label="Dismiss message"
                    @click="flashDismissed = true"
                >
                    Dismiss
                </button>
            </div>
        </div>

        <main id="main" class="flex-1">
            <div
                class="mx-auto w-full max-w-[1320px] px-5 py-10 sm:px-8 lg:px-12 lg:py-14"
            >
                <slot />
            </div>
        </main>

        <footer class="border-t border-line">
            <div
                class="mx-auto flex max-w-[1320px] flex-col gap-6 px-5 py-8 sm:px-8 md:flex-row md:items-end md:justify-between lg:px-12"
            >
                <div class="space-y-3">
                    <BrandMark compact />
                    <p class="max-w-md text-xs leading-relaxed text-ink-soft">
                        A small studio for considered images. Every charge is
                        recorded to the nanodollar; failed generations cost
                        nothing.
                    </p>
                </div>
                <div
                    class="flex flex-wrap gap-x-6 gap-y-2 font-mono text-[0.6rem] tracking-[0.12em] text-ink-soft uppercase"
                >
                    <Link href="/studio" class="hover:text-vermilion"
                        >Studio</Link
                    >
                    <Link href="/library" class="hover:text-vermilion"
                        >Library</Link
                    >
                    <Link href="/usage" class="hover:text-vermilion"
                        >Usage</Link
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
