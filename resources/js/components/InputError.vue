<script setup lang="ts">
defineProps<{
    message?: string;
}>();
</script>

<template>
    <div v-show="message">
        <p class="border-l-2 border-signal pl-2 text-sm text-signal">
            {{ message }}
        </p>
    </div>
</template>
