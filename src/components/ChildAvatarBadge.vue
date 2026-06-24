<template>
  <div
    class="ru-child-avatar-badge"
    :class="{
      'ru-child-avatar-badge--sm': size === 'sm',
      'ru-child-avatar-badge--active': active,
    }"
    :style="{ '--child-accent': accentColor }"
  >
    <div class="ru-child-avatar-badge__avatar">
      <span v-if="!child?.avatar_url">{{ initial }}</span>
      <img v-else :src="child.avatar_url" :alt="child?.name || 'avatar'" />
    </div>
    <span class="ru-child-avatar-badge__points" aria-label="Body">
      <PointsBadgeChip :points="points" size="sm" />
    </span>
  </div>
</template>

<script setup>
import { computed } from 'vue';
import PointsBadgeChip from './PointsBadgeChip.vue';
import { getAccentHex } from '../utils/avatarTheme';

const props = defineProps({
  child: { type: Object, default: null },
  points: { type: [Number, String], default: 0 },
  size: { type: String, default: 'lg' },
  active: { type: Boolean, default: false },
});

const accentColor = computed(() => getAccentHex(props.child));
const initial = computed(() => String(props.child?.name || '?').charAt(0));
</script>

<style scoped>
.ru-child-avatar-badge {
  position: relative;
  display: inline-flex;
  flex-shrink: 0;
  padding-right: 6px;
  padding-bottom: 0;
}

.ru-child-avatar-badge__avatar {
  width: 88px;
  height: 88px;
  border-radius: 999px;
  border: 4px solid var(--child-accent, #0ea5e9);
  box-sizing: border-box;
  overflow: hidden;
  display: grid;
  place-items: center;
  color: #ffffff;
  font-weight: 900;
  font-size: 32px;
  background: var(--child-accent, #0ea5e9);
}
.ru-child-avatar-badge__avatar img {
  width: 100%;
  height: 100%;
  object-fit: cover;
}

.ru-child-avatar-badge__points {
  position: absolute;
  right: -2px;
  bottom: 2px;
  z-index: 1;
}

.ru-child-avatar-badge--active .ru-child-avatar-badge__avatar {
  box-shadow:
    0 0 0 3px #ffffff,
    0 0 0 6px var(--child-accent, #0ea5e9);
}

.ru-child-avatar-badge--sm {
  padding-right: 4px;
}
.ru-child-avatar-badge--sm .ru-child-avatar-badge__avatar {
  width: 42px;
  height: 42px;
  border-width: 3px;
  font-size: 16px;
}
.ru-child-avatar-badge--sm .ru-child-avatar-badge__points {
  right: -4px;
  bottom: 0;
  padding: 1px 5px 1px 3px;
  font-size: 9px;
  gap: 2px;
}
.ru-child-avatar-badge--sm .ru-child-avatar-badge__coin {
  width: 10px;
  height: 10px;
}
</style>
