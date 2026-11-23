<script setup lang="ts">
import { useId } from 'vue';

const props = withDefaults(
    defineProps<{
        inverted?: boolean;
        size?: number;
    }>(),
    {
        inverted: false,
        size: 32,
    },
);

const maskId = `vellum-glyph-${useId()}`;
</script>

<template>
    <svg
        :width="props.size"
        :height="props.size"
        viewBox="0 0 32 32"
        fill="none"
        :class="props.inverted ? 'text-signal-invert' : 'text-signal'"
        aria-hidden="true"
        focusable="false"
    >
        <mask :id="maskId">
            <rect width="32" height="32" fill="white" />
            <path d="M13.1 4h5.8L16 12.1 13.1 4Z" fill="black" />
        </mask>
        <path
            d="M2 4h8.6L16 20.7 21.4 4H30L18.2 29h-4.4L2 4Z"
            fill="currentColor"
            :mask="`url(#${maskId})`"
        />
    </svg>
</template>
