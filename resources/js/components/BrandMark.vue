<script setup lang="ts">
import VellumGlyph from '@/components/VellumGlyph.vue';
import Wordmark from '@/components/Wordmark.vue';

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
        <VellumGlyph :inverted="inverted" :size="compact ? 16 : 24" />
        <Wordmark
            :inverted="inverted"
            :size="compact ? 'sm' : 'md'"
            :show-descriptor="!compact"
        />
    </span>
</template>
