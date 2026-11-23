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
        :class="inverted ? 'text-signal-invert' : 'text-signal'"
    >
        <span
            class="font-sans font-semibold uppercase"
            :class="{
                'text-[0.72rem] tracking-[0.28em]': size === 'sm',
                'text-[0.9rem] tracking-[0.24em]': size === 'md',
                'text-[1.05rem] tracking-[0.22em]': size === 'lg',
            }"
        >
            Vellum
        </span>
        <span
            v-if="showDescriptor"
            class="mt-1.5 font-mono text-[0.5rem] tracking-[0.24em] uppercase"
            :class="inverted ? 'text-signal-invert/55' : 'text-signal-muted'"
        >
            Signal studio
        </span>
    </span>
</template>
