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
    <div class="space-y-1.5">
        <h2
            v-if="title"
            class="font-display text-2xl tracking-tight text-signal"
        >
            {{ title }}
        </h2>
        <p v-if="description" class="text-sm leading-relaxed text-signal-muted">
            {{ description }}
        </p>
        <slot />
    </div>
</template>
