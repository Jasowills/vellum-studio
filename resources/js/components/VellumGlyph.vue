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
        :class="props.inverted ? 'text-void' : 'text-document'"
        aria-hidden="true"
        focusable="false"
    >
        <mask :id="maskId">
            <rect width="32" height="32" fill="white" />
            <path d="M13.4 4h5.2L16 10.2 13.4 4Z" fill="black" />
        </mask>
        <path
            d="M2 4h8.4L16 20.2 21.6 4H30L18.2 29h-4.4L2 4Z"
            fill="currentColor"
            :mask="`url(#${maskId})`"
        />
        <path d="M2 29h28" stroke="currentColor" stroke-opacity=".32" />
    </svg>
</template>
