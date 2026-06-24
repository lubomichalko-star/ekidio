<template>
  <span
    class="ru-points-badge-chip"
    :class="`ru-points-badge-chip--${size}`"
    :aria-label="ariaLabel"
  >
    <img :src="coinIcon" alt="" class="ru-points-badge-chip__coin" />
    <strong>{{ pointsDisplay }}</strong>
  </span>
</template>

<script setup>
import { computed } from 'vue';
import coinPng from '../images/star.png';

const props = defineProps({
  points: { type: [Number, String], default: 0 },
  size: { type: String, default: 'sm' },
  ariaLabel: { type: String, default: 'Body' },
});

const coinIcon = coinPng;
const pointsDisplay = computed(() => {
  const n = Number(props.points ?? 0);
  if (!Number.isFinite(n)) return '0';
  return n.toLocaleString('sk-SK');
});
</script>

<style scoped>
.ru-points-badge-chip {
  display: inline-flex;
  align-items: center;
  gap: 3px;
  padding: 2px 7px 2px 4px;
  border-radius: 999px;
  background: #efbf2a;
  color: #6f5410;
  font-size: 11px;
  font-weight: 900;
  box-shadow: 0 1px 4px rgba(15, 23, 42, 0.16);
  white-space: nowrap;
  line-height: 1;
}
.ru-points-badge-chip strong {
  color: inherit;
  font-weight: 900;
}
.ru-points-badge-chip__coin {
  width: 12px;
  height: 12px;
  object-fit: contain;
  flex-shrink: 0;
}

.ru-points-badge-chip--lg {
  gap: 8px;
  padding: 10px 22px 10px 14px;
  font-size: 28px;
  box-shadow: 0 2px 8px rgba(15, 23, 42, 0.18);
}
.ru-points-badge-chip--lg .ru-points-badge-chip__coin {
  width: 26px;
  height: 26px;
}
</style>
