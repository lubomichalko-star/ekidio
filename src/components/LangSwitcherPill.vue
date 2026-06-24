<template>
  <div class="ru-lang-pill" role="group" :aria-label="t('settings.language.title')">
    <button
      type="button"
      class="ru-lang-pill__btn"
      :class="{ 'ru-lang-pill__btn--active': i18nState.locale === 'sk' }"
      @click="setLocale('sk')"
    >
      SK
    </button>
    <button
      type="button"
      class="ru-lang-pill__btn"
      :class="{ 'ru-lang-pill__btn--active': i18nState.locale === 'en' }"
      @click="setLocale('en')"
    >
      EN
    </button>
  </div>
</template>

<script setup>
import { t, i18nState, setLocale } from '../i18n';
</script>

<style scoped>
.ru-lang-pill {
  display: inline-flex;
  border: 2px solid var(--bluegrey-200, rgba(15, 23, 42, 0.12));
  border-radius: 999px;
  overflow: hidden;
  margin-top: 24px;
}

.ru-lang-pill__btn {
  border: 0;
  background: transparent;
  padding: 8px 20px;
  cursor: pointer;
  font-weight: 700;
  color: var(--bluegrey-900, #0f172a);
  font-family: var(--font-family-base, inherit);
  font-size: 13px;
  line-height: 1;
}

.ru-lang-pill__btn--active {
  background: var(--bluegrey-900, #0f172a);
  color: var(--white-100, #fff);
}
</style>
