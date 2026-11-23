<script setup lang="ts">
withDefaults(
    defineProps<{
        inverted?: boolean;
        size?: 'sm' | 'md' | 'lg';
        showDescriptor?: boolean;
    }>(),
    {
        inverted: false,
        size: 'md',
        showDescriptor: false,
    },
);
</script>

<template>
    <span
        class="inline-flex flex-col leading-none"
        :class="inverted ? 'text-void' : 'text-document'"
    >
        <span
            class="font-display font-semibold uppercase"
            :class="{
                'text-[0.75rem] tracking-[0.16em]': size === 'sm',
                'text-[0.9rem] tracking-[0.17em]': size === 'md',
                'text-[1.05rem] tracking-[0.18em]': size === 'lg',
            }"
        >
            Vellum
        </span>
        <span
            v-if="showDescriptor"
            class="vellum-meta mt-1.5 hidden sm:block"
            :class="inverted ? 'text-void/60' : 'text-stone'"
        >
            Creative instrument
        </span>
    </span>
</template>
