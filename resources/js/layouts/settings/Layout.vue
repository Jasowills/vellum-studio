<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { Palette, Shield, UserRound } from '@lucide/vue';
import Heading from '@/components/Heading.vue';
import { Separator } from '@/components/ui/separator';
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
    <div class="mx-auto w-full max-w-6xl px-5 py-8 sm:px-8 lg:px-10 lg:py-10">
        <div class="border-b border-line pb-6">
            <p class="eyebrow">Account / Settings</p>
            <Heading
                class="mt-4"
                title="Settings"
                description="Manage your profile, security, and appearance."
            />
        </div>

        <div class="mt-8 flex flex-col gap-8 lg:flex-row lg:gap-12">
            <nav aria-label="Settings" class="lg:w-56 lg:shrink-0">
                <ul
                    class="flex gap-1 overflow-x-auto lg:flex-col lg:overflow-visible"
                >
                    <li
                        v-for="item in sidebarNavItems"
                        :key="toUrl(item.href)"
                        class="shrink-0 lg:w-full"
                    >
                        <Link
                            :href="item.href"
                            :aria-current="
                                isCurrentOrParentUrl(item.href)
                                    ? 'page'
                                    : undefined
                            "
                            class="focus-ring group flex w-full items-center gap-3 border-b-2 border-transparent px-3 py-2.5 font-mono text-[0.68rem] tracking-[0.14em] text-signal-muted uppercase transition-colors hover:text-signal lg:border-b-0 lg:border-l-2 lg:px-4"
                            :class="
                                isCurrentOrParentUrl(item.href)
                                    ? 'border-signal text-signal lg:border-l-signal'
                                    : 'hover:border-line lg:hover:border-line'
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

            <Separator class="lg:hidden" />

            <div class="min-w-0 flex-1 md:max-w-2xl">
                <section class="space-y-10" aria-label="Settings content">
                    <slot />
                </section>
            </div>
        </div>
    </div>
</template>
