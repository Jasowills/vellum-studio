<script setup lang="ts">
import AuthLayout from '@/layouts/auth/AuthSimpleLayout.vue';

const props = withDefaults(
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
    <AuthLayout :title="props.title" :description="props.description">
        <slot />
    </AuthLayout>
</template>
