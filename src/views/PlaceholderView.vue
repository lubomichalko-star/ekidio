<template>
  <section class="ru-card">
    <header class="ru-card__header">
      <div>
        <h2>{{ title }}</h2>
        <p class="ru-card__subtitle">Pripravujeme SPA sekciu</p>
      </div>
      <span class="ru-chip">Beta SPA</span>
    </header>
    <div class="ru-card__body">
      <p>Táto sekcia bude čoskoro dostupná v rámci SPA.</p>
    </div>
  </section>
</template>

<script setup>
defineProps({
  title: {
    type: String,
    default: 'Sekcia'
  }
});
</script>

<style scoped>
.ru-card {
  background: white;
  border: 1px solid #e5e7eb;
  border-radius: 12px;
  box-shadow: 0 10px 35px -20px rgba(15, 23, 42, 0.2);
  padding: 16px;
}

.ru-card__header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 12px;
}

.ru-card__subtitle {
  margin: 2px 0 0;
  color: #6b7280;
  font-size: 13px;
}

.ru-card__body {
  color: #374151;
  line-height: 1.6;
  font-size: 14px;
}

.ru-chip {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 6px 10px;
  border-radius: 999px;
  background: #f1f5f9;
  color: #0f172a;
  font-weight: 600;
  font-size: 12px;
}
</style>

