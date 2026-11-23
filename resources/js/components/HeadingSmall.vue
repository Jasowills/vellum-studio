<script setup lang="ts">
withDefaults(
    defineProps<{
        title?: string;
        description?: string;
    }>(),
    {
        title: '',
        description: '',
    },
);
</script>

<template>
    <div class="space-y-1">
        <h3 v-if="title" class="text-sm font-medium text-signal">
            {{ title }}
        </h3>
        <p v-if="description" class="text-xs leading-relaxed text-signal-muted">
            {{ description }}
        </p>
        <slot />
    </div>
</template>
