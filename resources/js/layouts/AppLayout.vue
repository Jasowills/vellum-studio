<script setup lang="ts">
import { Link, router, usePage } from '@inertiajs/vue3';
import {
    ChartNoAxesColumn,
    ChevronDown,
    Images,
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
        { label: 'Studio', href: '/studio', icon: Sparkles, match: '/studio' },
        { label: 'Library', href: '/library', icon: Images, match: '/library' },
        {
            label: 'Usage',
            href: '/usage',
            icon: ChartNoAxesColumn,
            match: '/usage',
        },
    ];

    if (isAdmin.value) {
        items.push({
            label: 'Report',
            href: '/admin/report',
            icon: ShieldCheck,
            match: '/admin/report',
        });
    }

    return items;
});

function isActive(href: string): boolean {
    return href === '/' ? path.value === '/' : path.value.startsWith(href);
}

function logout(): void {
    router.post('/logout');
}

watch(path, () => {
    mobileOpen.value = false;
});

const flash = computed(() => {
    const props = page.props as Record<string, unknown>;
    const value = props.flash as
        | { success?: string; error?: string; warning?: string }
        | undefined;

    return value?.success || value?.error || value?.warning || null;
});
</script>

<template>
    <div class="min-h-svh bg-background text-foreground">
        <a
            href="#main-content"
            class="sr-only focus:not-sr-only focus:fixed focus:top-3 focus:left-3 focus:z-50 focus:bg-signal focus:px-4 focus:py-2 focus:text-signal-invert"
        >
            Skip to content
        </a>

        <div
            v-if="flash && !flashDismissed"
            class="border-b border-line bg-paper-deep"
            role="status"
            aria-live="polite"
        >
            <div
                class="mx-auto flex max-w-[1440px] items-center justify-between gap-4 px-5 py-2.5 font-mono text-[0.65rem] tracking-[0.05em] text-signal sm:px-8 lg:px-12"
            >
                <span>{{ flash }}</span>
                <button
                    type="button"
                    class="focus-ring p-1 text-signal-muted transition-colors hover:text-signal"
                    aria-label="Dismiss notification"
                    @click="flashDismissed = true"
                >
                    <X class="size-3.5" aria-hidden="true" />
                </button>
            </div>
        </div>

        <header
            class="sticky top-0 z-30 border-b border-line bg-background/95 backdrop-blur-[2px]"
        >
            <div
                class="mx-auto flex h-16 max-w-[1440px] items-center justify-between gap-4 px-5 sm:px-8 lg:px-12"
            >
                <Link
                    href="/studio"
                    class="focus-ring"
                    aria-label="Vellum studio home"
                >
                    <BrandMark />
                </Link>

                <nav
                    class="hidden items-center gap-1 lg:flex"
                    aria-label="Primary navigation"
                >
                    <Link
                        v-for="item in navItems"
                        :key="item.href"
                        :href="item.href"
                        class="group focus-ring relative inline-flex items-center gap-2 border border-transparent px-3 py-2 font-mono text-[0.65rem] tracking-[0.14em] uppercase transition-colors"
                        :class="
                            isActive(item.href)
                                ? 'border-line bg-paper text-signal'
                                : 'text-signal-muted hover:text-signal'
                        "
                        :aria-current="isActive(item.href) ? 'page' : undefined"
                    >
                        <component
                            :is="item.icon"
                            class="size-3.5"
                            aria-hidden="true"
                        />
                        {{ item.label }}
                        <span
                            v-if="isActive(item.href)"
                            class="absolute inset-x-2 -bottom-px h-px bg-signal"
                            aria-hidden="true"
                        />
                    </Link>
                </nav>

                <div class="hidden items-center gap-2 lg:flex">
                    <Link
                        href="/settings/profile"
                        class="focus-ring inline-flex items-center gap-2 border border-line px-3 py-2 font-mono text-[0.65rem] tracking-[0.12em] text-signal-muted uppercase transition-colors hover:border-signal hover:text-signal"
                    >
                        <Settings class="size-3.5" aria-hidden="true" />
                        Settings
                    </Link>

                    <DropdownMenu>
                        <DropdownMenuTrigger
                            class="focus-ring inline-flex items-center gap-2 border border-line px-3 py-2 text-left transition-colors hover:border-signal"
                            as-child
                        >
                            <button type="button">
                                <span
                                    class="flex size-6 items-center justify-center border border-signal font-mono text-[0.6rem] text-signal"
                                    aria-hidden="true"
                                >
                                    {{
                                        (user?.name || 'V')
                                            .charAt(0)
                                            .toUpperCase()
                                    }}
                                </span>
                                <span
                                    class="max-w-[9rem] truncate text-xs text-signal"
                                >
                                    {{ user?.name }}
                                </span>
                                <ChevronDown
                                    class="size-3 text-signal-muted"
                                    aria-hidden="true"
                                />
                            </button>
                        </DropdownMenuTrigger>
                        <DropdownMenuContent
                            align="end"
                            :side-offset="6"
                            class="border-line bg-popover font-sans text-popover-foreground"
                        >
                            <DropdownMenuLabel
                                class="font-mono text-[0.6rem] tracking-[0.16em] text-signal-muted uppercase"
                            >
                                {{ user?.email }}
                            </DropdownMenuLabel>
                            <DropdownMenuSeparator class="bg-line" />
                            <DropdownMenuItem as-child>
                                <Link
                                    href="/settings/profile"
                                    class="focus:bg-accent"
                                >
                                    <Settings
                                        class="size-3.5"
                                        aria-hidden="true"
                                    />
                                    Profile settings
                                </Link>
                            </DropdownMenuItem>
                            <DropdownMenuItem
                                class="focus:bg-accent"
                                @select="logout"
                            >
                                <LogOut class="size-3.5" aria-hidden="true" />
                                Sign out
                            </DropdownMenuItem>
                        </DropdownMenuContent>
                    </DropdownMenu>
                </div>

                <button
                    type="button"
                    class="focus-ring inline-flex size-9 items-center justify-center border border-line text-signal lg:hidden"
                    :aria-expanded="mobileOpen"
                    aria-controls="mobile-navigation"
                    :aria-label="
                        mobileOpen ? 'Close navigation' : 'Open navigation'
                    "
                    @click="mobileOpen = !mobileOpen"
                >
                    <X v-if="mobileOpen" class="size-4" aria-hidden="true" />
                    <Menu v-else class="size-4" aria-hidden="true" />
                </button>
            </div>

            <div
                v-if="mobileOpen"
                id="mobile-navigation"
                class="border-t border-line bg-background lg:hidden"
            >
                <nav
                    class="flex flex-col px-5 py-3"
                    aria-label="Mobile navigation"
                >
                    <Link
                        v-for="item in navItems"
                        :key="item.href"
                        :href="item.href"
                        class="flex items-center gap-3 border-b border-line-soft px-1 py-3 font-mono text-[0.68rem] tracking-[0.14em] uppercase"
                        :class="
                            isActive(item.href)
                                ? 'text-signal'
                                : 'text-signal-muted'
                        "
                        :aria-current="isActive(item.href) ? 'page' : undefined"
                    >
                        <component
                            :is="item.icon"
                            class="size-3.5"
                            aria-hidden="true"
                        />
                        {{ item.label }}
                    </Link>
                    <Link
                        href="/settings/profile"
                        class="flex items-center gap-3 border-b border-line-soft px-1 py-3 font-mono text-[0.68rem] tracking-[0.14em] text-signal-muted uppercase"
                    >
                        <Settings class="size-3.5" aria-hidden="true" />
                        Settings
                    </Link>
                    <button
                        type="button"
                        class="mt-3 flex items-center gap-3 border border-line px-3 py-3 font-mono text-[0.68rem] tracking-[0.14em] text-signal uppercase"
                        @click="logout"
                    >
                        <LogOut class="size-3.5" aria-hidden="true" />
                        Sign out
                    </button>
                    <p
                        class="mt-4 truncate font-mono text-[0.6rem] text-signal-faint"
                    >
                        {{ user?.email }}
                    </p>
                </nav>
            </div>
        </header>

        <div
            v-if="breadcrumbs.length"
            class="border-b border-line-soft bg-background"
        >
            <div
                class="mx-auto flex max-w-[1440px] items-center gap-2 overflow-x-auto px-5 py-3 font-mono text-[0.6rem] tracking-[0.12em] text-signal-faint uppercase sm:px-8 lg:px-12"
            >
                <template v-for="(crumb, index) in breadcrumbs" :key="index">
                    <span v-if="index > 0" aria-hidden="true">/</span>
                    <Link
                        v-if="crumb.href && index < breadcrumbs.length - 1"
                        :href="crumb.href"
                        class="hover:text-signal"
                    >
                        {{ crumb.title }}
                    </Link>
                    <span v-else class="text-signal-muted">{{
                        crumb.title
                    }}</span>
                </template>
            </div>
        </div>

        <main
            id="main-content"
            class="mx-auto max-w-[1440px] px-5 py-8 sm:px-8 lg:px-12 lg:py-10"
        >
            <slot />
        </main>

        <footer class="border-t border-line-soft">
            <div
                class="mx-auto flex max-w-[1440px] flex-col gap-3 px-5 py-6 font-mono text-[0.58rem] tracking-[0.14em] text-signal-faint uppercase sm:flex-row sm:items-center sm:justify-between sm:px-8 lg:px-12"
            >
                <span>Vellum / Signal studio</span>
                <span>Exact accounting · Private archive</span>
            </div>
        </footer>
    </div>
</template>
