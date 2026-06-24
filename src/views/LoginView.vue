<template>
  <LoginStano v-if="visualTheme === 'stano'" />
  <LoginLubo v-else />
</template>

<script setup>
import { computed } from 'vue';
import { getVisualTheme } from '../config/visualTheme';
import LoginLubo from '../themes/LoginLubo.vue';
import LoginStano from '../themes/LoginStano.vue';

const visualTheme = computed(() => getVisualTheme());
</script>
