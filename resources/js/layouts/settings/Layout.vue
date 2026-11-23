<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { Palette, Shield, UserRound } from '@lucide/vue';
import { useCurrentUrl } from '@/composables/useCurrentUrl';
import { toUrl } from '@/lib/utils';
import { edit as editAppearance } from '@/routes/appearance';
import { edit as editProfile } from '@/routes/profile';
import { edit as editSecurity } from '@/routes/security';
import type { NavItem } from '@/types';

const sidebarNavItems: NavItem[] = [
    {
        title: 'Profile',
        href: editProfile(),
        icon: UserRound,
    },
    {
        title: 'Security',
        href: editSecurity(),
        icon: Shield,
    },
    {
        title: 'Appearance',
        href: editAppearance(),
        icon: Palette,
    },
];

const { isCurrentOrParentUrl } = useCurrentUrl();
</script>

<template>
    <div class="w-full max-w-6xl px-5 py-8 sm:px-8 lg:py-10">
        <header class="border-b border-line pb-7">
            <p class="vellum-meta">Account / Settings</p>
            <h1
                class="vellum-title mt-3 text-[1.75rem] text-document sm:text-2xl"
            >
                Settings
            </h1>
            <p class="mt-2 max-w-lg text-sm leading-relaxed text-ash">
                Manage your profile, security, and appearance.
            </p>
        </header>

        <div class="mt-8 flex flex-col gap-8 lg:flex-row lg:gap-12">
            <nav aria-label="Settings" class="lg:w-52 lg:shrink-0">
                <p class="vellum-meta mb-3 hidden lg:block">Sections</p>
                <ul class="flex flex-col gap-1">
                    <li
                        v-for="item in sidebarNavItems"
                        :key="toUrl(item.href)"
                        class="w-full"
                    >
                        <Link
                            :href="item.href"
                            :aria-current="
                                isCurrentOrParentUrl(item.href)
                                    ? 'page'
                                    : undefined
                            "
                            class="focus-ring flex w-full items-center gap-3 border-l-2 px-3 py-2.5 text-sm transition-colors"
                            :class="
                                isCurrentOrParentUrl(item.href)
                                    ? 'border-document bg-surface text-document'
                                    : 'border-edge text-ash hover:border-edge-strong hover:text-document'
                            "
                        >
                            <component
                                :is="item.icon"
                                class="size-4 shrink-0"
                                aria-hidden="true"
                            />
                            <span>{{ item.title }}</span>
                        </Link>
                    </li>
                </ul>
            </nav>

            <div class="min-w-0 flex-1 lg:max-w-[720px]">
                <section
                    class="border-t border-line pt-8 lg:border-t-0 lg:pt-0"
                    aria-label="Settings content"
                >
                    <slot />
                </section>
            </div>
        </div>
    </div>
</template>
