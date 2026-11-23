<script setup lang="ts">
withDefaults(
    defineProps<{
        inverted?: boolean;
        compact?: boolean;
    }>(),
    {
        inverted: false,
        compact: false,
    },
);
</script>

<template>
    <span class="inline-flex items-center gap-2.5">
        <span
            class="relative inline-flex shrink-0 items-center justify-center border"
            :class="[
                compact ? 'h-7 w-7' : 'h-8 w-8',
                inverted
                    ? 'border-paper/45 text-paper'
                    : 'border-ink/35 text-ink',
            ]"
            aria-hidden="true"
        >
            <svg viewBox="0 0 32 32" fill="none" class="h-full w-full">
                <path
                    d="M4 4h24v24H4zM4 4l24 24M28 4L4 28"
                    stroke="currentColor"
                    stroke-width="0.9"
                    opacity="0.62"
                />
                <circle cx="23.4" cy="23.4" r="3.6" fill="#c0442b" />
            </svg>
        </span>
        <span
            class="font-display leading-none tracking-[-0.04em]"
            :class="[
                compact ? 'text-[1.35rem]' : 'text-[1.55rem]',
                inverted ? 'text-paper' : 'text-ink',
            ]"
        >
            Vellum
        </span>
    </span>
</template>
