<script setup lang="ts">
defineProps<{
    label: string;
    tone: 'waiting' | 'working' | 'success' | 'danger';
    pulse?: boolean;
}>();
</script>

<template>
    <span
        class="inline-flex items-center gap-2 border px-2 py-1 font-mono text-[0.6rem] font-medium tracking-[0.14em] uppercase"
        :class="{
            'border-dashed border-line text-signal-muted': tone === 'waiting',
            'border-signal-line text-signal': tone === 'working',
            'border-2 border-signal text-signal': tone === 'success',
            'border-2 border-dashed border-signal text-signal':
                tone === 'danger',
        }"
    >
        <span
            class="relative inline-block size-2 shrink-0"
            :class="{
                'border border-signal-faint': tone === 'waiting',
                'bg-signal':
                    tone === 'working' ||
                    tone === 'success' ||
                    tone === 'danger',
                'rotate-45': tone === 'danger',
                'animate-breathe': pulse || tone === 'working',
            }"
            aria-hidden="true"
        >
            <span
                v-if="tone === 'success'"
                class="absolute inset-[2px] bg-signal-invert"
            />
        </span>
        {{ label }}
    </span>
</template>
