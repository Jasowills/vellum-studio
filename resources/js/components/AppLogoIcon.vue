<script setup lang="ts">
import VellumGlyph from '@/components/VellumGlyph.vue';

withDefaults(defineProps<{ size?: number }>(), { size: 24 });
</script>

<template>
    <VellumGlyph :size="size" />
</template>
