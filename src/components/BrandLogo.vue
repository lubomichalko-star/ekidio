<template>
  <div class="ru-brand" :class="[`ru-brand--${variant}`, sizeClass]" role="img" :aria-label="ariaLabel">
    <template v-if="variant === 'green'">
      <img class="ru-brand__mark" :src="favGreenUrl" alt="" aria-hidden="true" />
      <span class="ru-brand__name" aria-hidden="true">ekidio</span>
    </template>
    <img v-else-if="variant === 'white'" class="ru-brand__img" :src="logoWhiteUrl" alt="ekidio" />
    <img v-else class="ru-brand__img" :src="logoGenUrl" alt="ekidio" />
  </div>
</template>

<script setup>
import { computed } from 'vue';
import favGreenUrl from '../images/fav-green.png';
import logoGenUrl from '../images/logo-gen.png';
import logoWhiteUrl from '../images/logo-white.png';

const props = defineProps({
  variant: {
    type: String,
    default: 'green',
    validator: (v) => ['green', 'dark', 'white'].includes(v),
  },
  size: {
    type: String,
    default: 'md',
    validator: (v) => ['sm', 'md', 'lg'].includes(v),
  },
  ariaLabel: {
    type: String,
    default: 'ekidio',
  },
});

const sizeClass = computed(() => `ru-brand--${props.size}`);
</script>

<style scoped>
.ru-brand {
  display: inline-flex;
  align-items: center;
  justify-content: center;
}

.ru-brand--green {
  gap: 12px;
}

.ru-brand__mark {
  display: block;
  object-fit: cover;
  flex-shrink: 0;
  border-radius: 50%;
}

.ru-brand__name {
  color: #5abb6f;
  font-weight: 900;
  letter-spacing: -0.02em;
  line-height: 1;
  text-transform: lowercase;
}

.ru-brand--sm .ru-brand__mark {
  width: 44px;
  height: 44px;
}

.ru-brand--sm .ru-brand__name {
  font-size: 1.75rem;
}

.ru-brand--md .ru-brand__mark {
  width: 64px;
  height: 64px;
}

.ru-brand--md .ru-brand__name {
  font-size: clamp(2rem, 8vw, 2.75rem);
}

.ru-brand--lg .ru-brand__mark {
  width: 80px;
  height: 80px;
}

.ru-brand--lg .ru-brand__name {
  font-size: clamp(2.25rem, 10vw, 3rem);
}

.ru-brand__img {
  display: block;
  height: auto;
  object-fit: contain;
}

.ru-brand--sm .ru-brand__img {
  width: 140px;
}

.ru-brand--md .ru-brand__img {
  width: min(220px, 60vw);
}

.ru-brand--lg .ru-brand__img {
  width: min(260px, 70vw);
}
</style>
