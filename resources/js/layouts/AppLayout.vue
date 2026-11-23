<script setup lang="ts">
import { Link, router, usePage } from '@inertiajs/vue3';
import { ChevronDown, LogOut, Menu, Settings, X } from '@lucide/vue';
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
        { label: 'Studio', href: '/studio' },
        { label: 'Library', href: '/library' },
        { label: 'Usage', href: '/usage' },
    ];

    if (isAdmin.value) {
        items.push({ label: 'Report', href: '/admin/report' });
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
    <div class="min-h-svh overflow-x-clip bg-void text-document">
        <a
            href="#main-content"
            class="sr-only focus:not-sr-only focus:fixed focus:top-3 focus:left-3 focus:z-50 focus:bg-document focus:px-4 focus:py-2 focus:text-void"
        >
            Skip to content
        </a>

        <div
            v-if="flash && !flashDismissed"
            class="border-b border-line bg-elevated"
            role="status"
            aria-live="polite"
        >
            <div
                class="vellum-shell flex items-center justify-between gap-4 py-2.5 text-sm text-document"
            >
                <span>{{ flash }}</span>
                <button
                    type="button"
                    class="focus-ring p-1 text-ash transition-colors hover:text-document"
                    aria-label="Dismiss notification"
                    @click="flashDismissed = true"
                >
                    <X class="size-3.5" aria-hidden="true" />
                </button>
            </div>
        </div>

        <header class="sticky top-0 z-30 border-b border-line bg-void/95">
            <div
                class="vellum-shell flex h-16 items-center justify-between gap-5"
            >
                <Link
                    href="/studio"
                    class="focus-ring shrink-0"
                    aria-label="Vellum studio"
                >
                    <BrandMark compact />
                </Link>

                <nav
                    class="hidden items-center gap-7 md:flex"
                    aria-label="Primary navigation"
                >
                    <Link
                        v-for="item in navItems"
                        :key="item.href"
                        :href="item.href"
                        class="focus-ring relative py-2 text-sm transition-colors"
                        :class="
                            isActive(item.href)
                                ? 'text-document'
                                : 'text-ash hover:text-document'
                        "
                        :aria-current="isActive(item.href) ? 'page' : undefined"
                    >
                        {{ item.label }}
                        <span
                            v-if="isActive(item.href)"
                            class="absolute inset-x-0 -bottom-px h-px bg-document"
                            aria-hidden="true"
                        />
                    </Link>
                </nav>

                <div class="hidden items-center gap-3 md:flex">
                    <Link
                        href="/settings/profile"
                        class="focus-ring text-sm text-ash transition-colors hover:text-document"
                    >
                        Settings
                    </Link>

                    <DropdownMenu>
                        <DropdownMenuTrigger
                            class="focus-ring inline-flex items-center gap-2 border border-line px-2.5 py-2 transition-colors hover:border-edge-strong"
                            as-child
                        >
                            <button
                                type="button"
                                :aria-label="`Account menu for ${user?.name ?? 'user'}`"
                            >
                                <span
                                    class="flex size-5 items-center justify-center border border-edge-strong font-mono text-[0.6rem] text-document"
                                    aria-hidden="true"
                                >
                                    {{
                                        (user?.name || 'V')
                                            .charAt(0)
                                            .toUpperCase()
                                    }}
                                </span>
                                <span
                                    class="max-w-[8rem] truncate text-xs text-document"
                                >
                                    {{ user?.name }}
                                </span>
                                <ChevronDown
                                    class="size-3 text-stone"
                                    aria-hidden="true"
                                />
                            </button>
                        </DropdownMenuTrigger>
                        <DropdownMenuContent
                            align="end"
                            :side-offset="6"
                            class="border-line bg-elevated font-sans text-document"
                        >
                            <DropdownMenuLabel class="vellum-meta">
                                {{ user?.email }}
                            </DropdownMenuLabel>
                            <DropdownMenuSeparator class="bg-line" />
                            <DropdownMenuItem as-child>
                                <Link
                                    href="/settings/profile"
                                    class="focus:bg-elevated"
                                >
                                    <Settings
                                        class="size-3.5"
                                        aria-hidden="true"
                                    />
                                    Profile settings
                                </Link>
                            </DropdownMenuItem>
                            <DropdownMenuItem
                                class="focus:bg-elevated"
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
                    class="focus-ring inline-flex size-9 items-center justify-center border border-line text-document md:hidden"
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
                class="border-t border-line bg-void md:hidden"
            >
                <nav
                    class="vellum-shell flex flex-col py-2"
                    aria-label="Mobile navigation"
                >
                    <Link
                        v-for="item in navItems"
                        :key="item.href"
                        :href="item.href"
                        class="border-b border-edge py-3 text-sm"
                        :class="
                            isActive(item.href) ? 'text-document' : 'text-ash'
                        "
                        :aria-current="isActive(item.href) ? 'page' : undefined"
                    >
                        {{ item.label }}
                    </Link>
                    <Link
                        href="/settings/profile"
                        class="border-b border-edge py-3 text-sm text-ash"
                    >
                        Settings
                    </Link>
                    <button
                        type="button"
                        class="mt-3 flex items-center gap-2 py-3 text-left text-sm text-document"
                        @click="logout"
                    >
                        <LogOut class="size-3.5" aria-hidden="true" />
                        Sign out
                    </button>
                    <p class="vellum-meta truncate pb-3">
                        {{ user?.email }}
                    </p>
                </nav>
            </div>
        </header>

        <div v-if="breadcrumbs.length" class="border-b border-edge bg-void">
            <div
                class="vellum-shell flex items-center gap-2 overflow-x-auto py-3 text-xs text-stone"
            >
                <template v-for="(crumb, index) in breadcrumbs" :key="index">
                    <span v-if="index > 0" aria-hidden="true">/</span>
                    <Link
                        v-if="crumb.href && index < breadcrumbs.length - 1"
                        :href="crumb.href"
                        class="hover:text-document"
                    >
                        {{ crumb.title }}
                    </Link>
                    <span v-else class="text-ash">{{ crumb.title }}</span>
                </template>
            </div>
        </div>

        <main id="main-content" class="min-w-0">
            <slot />
        </main>

        <footer class="border-t border-edge">
            <div
                class="vellum-shell flex flex-col gap-2 py-5 text-xs text-stone sm:flex-row sm:items-center sm:justify-between"
            >
                <span>Vellum / creative instrument</span>
                <span>Exact accounting · Private archive</span>
            </div>
        </footer>
    </div>
</template>
