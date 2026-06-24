<template>
  <div class="ru-days-bar">
    <button
      type="button"
      class="ru-day-nav"
      aria-label="Predchádzajúci týždeň"
      @click="shiftWeek(-1)"
    >
      ‹
    </button>
    <button
      v-for="d in days"
      :key="d.value"
      type="button"
      class="ru-day-pill"
      :class="{ active: Number(selectedDay) === Number(d.value) }"
      @click="selectDay(d.value)"
    >
      <span class="ru-day-pill__label">{{ dayPillParts(d.value).label }}</span>
      <span class="ru-day-pill__date">{{ dayPillParts(d.value).date }}</span>
    </button>
    <button
      type="button"
      class="ru-day-nav"
      aria-label="Ďalší týždeň"
      @click="shiftWeek(1)"
    >
      ›
    </button>
  </div>
</template>

<script setup>
import { DAYS_SHORT_SK, addWeeksToWeekStart, formatDayPillParts } from '../utils/days';

const props = defineProps({
  weekStart: {
    type: String,
    required: true,
  },
  selectedDay: {
    type: Number,
    required: true,
  },
});

const emit = defineEmits(['update:selectedDay', 'update:weekStart']);

const days = DAYS_SHORT_SK;

const dayPillParts = (dayW) => formatDayPillParts(dayW, props.weekStart);

const selectDay = (day) => {
  emit('update:selectedDay', day);
};

const shiftWeek = (delta) => {
  const next = addWeeksToWeekStart(props.weekStart, delta);
  if (next) emit('update:weekStart', next);
};
</script>
