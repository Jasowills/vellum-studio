<script setup lang="ts">
import { onBeforeUnmount, onMounted, ref, useAttrs } from 'vue';

defineOptions({ inheritAttrs: false });

const attrs = useAttrs();

const props = withDefaults(
    defineProps<{
        delay?: number;
    }>(),
    {
        delay: 0,
    },
);

const element = ref<HTMLElement | null>(null);
const armed = ref(false);
const visible = ref(false);
let observer: IntersectionObserver | null = null;

onMounted(() => {
    if (typeof window === 'undefined' || !element.value) {
        return;
    }

    if (
        !('IntersectionObserver' in window) ||
        window.matchMedia('(prefers-reduced-motion: reduce)').matches
    ) {
        return;
    }

    armed.value = true;
    observer = new IntersectionObserver(
        (entries) => {
            if (entries.some((entry) => entry.isIntersecting)) {
                visible.value = true;
                observer?.disconnect();
                observer = null;
            }
        },
        { threshold: 0.12, rootMargin: '0px 0px -8% 0px' },
    );
    observer.observe(element.value);
});

onBeforeUnmount(() => {
    observer?.disconnect();
    observer = null;
});
</script>

<template>
    <div
        ref="element"
        :data-reveal="armed ? (visible ? 'visible' : 'armed') : undefined"
        v-bind="attrs"
        :style="delay ? { transitionDelay: `${delay}ms` } : undefined"
    >
        <slot />
    </div>
</template>
