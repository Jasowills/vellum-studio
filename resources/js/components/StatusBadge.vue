<script setup lang="ts">
defineProps<{
    label: string;
    tone: 'waiting' | 'working' | 'success' | 'danger';
    pulse?: boolean;
}>();
</script>

<template>
    <span
        class="inline-flex items-center gap-2 border px-2 py-1 font-mono text-[0.625rem] font-medium tracking-[0.14em] uppercase"
        :class="{
            'border-line bg-paper/90 text-ink-soft': tone === 'waiting',
            'border-vermilion/25 bg-vermilion-soft text-vermilion':
                tone === 'working' || tone === 'danger',
            'border-moss/25 bg-moss-soft text-moss': tone === 'success',
        }"
    >
        <span
            class="status-dot"
            :class="{
                'bg-ochre': tone === 'waiting',
                'bg-vermilion': tone === 'working' || tone === 'danger',
                'bg-moss': tone === 'success',
                'animate-breathe': pulse || tone === 'working',
            }"
        />
        {{ label }}
    </span>
</template>
