<script setup lang="ts">
defineProps<{
    label: string;
    tone: 'waiting' | 'working' | 'success' | 'danger';
    pulse?: boolean;
}>();
</script>

<template>
    <span class="inline-flex min-w-0 items-center gap-2">
        <span
            class="inline-block size-1.5 shrink-0"
            :class="{
                'border border-stone bg-transparent': tone === 'waiting',
                'bg-document':
                    tone === 'working' ||
                    tone === 'success' ||
                    tone === 'danger',
                'rotate-45': tone === 'danger',
                'animate-breathe': pulse || tone === 'working',
            }"
            aria-hidden="true"
        />
        <span
            class="min-w-0 truncate font-mono text-[0.625rem] leading-none tracking-[0.06em]"
            :class="tone === 'waiting' ? 'text-ash' : 'text-document'"
        >
            {{ label }}
        </span>
    </span>
</template>
