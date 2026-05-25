<template>
  <div class="ru-feedback">
    <button
      class="ru-feedback__fab"
      type="button"
      aria-label="Spätná väzba"
      :style="{
        '--ru-feedback-stack-offset': `${fabStackOffset}px`,
      }"
      @click="openModal"
    >
      <span class="ru-feedback__icon" aria-hidden="true">💬</span>
    </button>

    <RuModal v-if="show" title="Spätná väzba" @close="closeModal">
      <div class="ru-feedback__content">
        <p class="ru-feedback__hint" v-if="!sent">
          Napíš nám, čo zlepšiť (bug, nápad, pripomienka).
        </p>

        <p class="ru-feedback__thanks" v-if="sent">
          Ďakujeme! Spoločne zlepšujeme appku.
        </p>

        <textarea
          v-if="!sent"
          v-model="text"
          class="ru-feedback__textarea"
          rows="6"
          placeholder="Sem napíš svoju spätnú väzbu…"
        />

        <p class="ru-feedback__error" v-if="error">{{ error }}</p>
      </div>

      <template #footer>
        <button class="ru-btn ru-btn--ghost" type="button" @click="closeModal">
          {{ sent ? 'Zavrieť' : 'Zrušiť' }}
        </button>
        <button
          v-if="!sent"
          class="ru-btn"
          type="button"
          :disabled="sending || !canSend"
          @click="submit"
        >
          {{ sending ? 'Odosielam…' : 'Odoslať' }}
        </button>
      </template>
    </RuModal>
  </div>
</template>

<script setup>
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { useRoute } from 'vue-router';
import RuModal from './RuModal.vue';
import { api } from '../api/client';

const route = useRoute();

const show = ref(false);
const text = ref('');
const sending = ref(false);
const sent = ref(false);
const error = ref('');

const fabStackOffset = ref(0);

const canSend = computed(() => String(text.value || '').trim().length >= 3);

const recalcFabStackOffset = () => {
  try {
    const stack = document.querySelector('.ru-fab-stack');
    if (stack) {
      const rect = stack.getBoundingClientRect();
      const visible = rect.width > 0 && rect.height > 0;
      fabStackOffset.value = visible ? Math.ceil(rect.height) + 12 : 0;
      return;
    }

    const el = document.querySelector('.ru-fab');
    if (!el) {
      fabStackOffset.value = 0;
      return;
    }
    const rect = el.getBoundingClientRect();
    const visible = rect.width > 0 && rect.height > 0;
    fabStackOffset.value = visible ? Math.ceil(rect.height) + 12 : 0;
  } catch {
    fabStackOffset.value = 0;
  }
};

let mo = null;
const attachObservers = () => {
  try {
    if (typeof window === 'undefined') return;
    if (mo) return;
    mo = new MutationObserver(() => recalcFabStackOffset());
    mo.observe(document.body, { childList: true, subtree: true, attributes: true });
  } catch {}
};

const detachObservers = () => {
  try {
    if (mo) mo.disconnect();
  } catch {}
  mo = null;
};

const openModal = () => {
  show.value = true;
  sent.value = false;
  sending.value = false;
  error.value = '';
  text.value = '';
};

const closeModal = () => {
  show.value = false;
};

const submit = async () => {
  const payload = String(text.value || '').trim();
  if (payload.length < 3) {
    error.value = 'Prosím, napíš aspoň pár slov.';
    return;
  }

  sending.value = true;
  error.value = '';
  try {
    const loc = (() => {
      try {
        return typeof window !== 'undefined' ? (window.location.hash || '') : '';
      } catch {
        return '';
      }
    })();
    const path = loc || route.fullPath || '';
    await api.sendFeedback(payload, path);
    sent.value = true;
  } catch (e) {
    error.value = e?.message || 'Nepodarilo sa odoslať. Skús prosím neskôr.';
  } finally {
    sending.value = false;
  }
};

onMounted(async () => {
  await nextTick();
  recalcFabStackOffset();
  attachObservers();
  try {
    window.addEventListener('resize', recalcFabStackOffset);
  } catch {}
});

onBeforeUnmount(() => {
  detachObservers();
  try {
    window.removeEventListener('resize', recalcFabStackOffset);
  } catch {}
});

watch(
  () => route.fullPath,
  async () => {
    await nextTick();
    recalcFabStackOffset();
  }
);
</script>

<style scoped>
.ru-feedback__fab {
  position: fixed;
  right: 24px;
  bottom: calc(24px + var(--ru-feedback-stack-offset, 0px));
  width: 56px;
  height: 56px;
  border-radius: 999px;
  border: 0;
  background: #111827;
  color: #ffffff;
  display: grid;
  place-items: center;
  box-shadow: 0 20px 40px -20px rgba(17, 24, 39, 0.85);
  cursor: pointer;
  z-index: 60;
}

.ru-feedback__icon {
  font-size: 22px;
  line-height: 1;
}

@media (max-width: 768px) {
  .ru-feedback__fab {
    right: 16px;
    bottom: calc(84px + env(safe-area-inset-bottom, 0px) + var(--ru-feedback-stack-offset, 0px));
    width: 56px;
    height: 56px;
  }
}

.ru-feedback__content {
  padding: 0 2px;
}

.ru-feedback__hint {
  margin: 4px 0 10px;
  color: #475569;
  font-weight: 700;
}

.ru-feedback__thanks {
  margin: 4px 0 10px;
  color: #0f172a;
  font-weight: 900;
}

.ru-feedback__textarea {
  width: 100%;
  border-radius: 16px;
  border: 1px solid rgba(15, 23, 42, 0.12);
  padding: 12px 12px;
  font-size: 15px;
  line-height: 1.4;
  outline: none;
  background: #ffffff;
}

.ru-feedback__textarea:focus {
  border-color: rgba(14, 165, 233, 0.55);
  box-shadow: 0 0 0 4px rgba(14, 165, 233, 0.18);
}

.ru-feedback__error {
  margin: 10px 0 0;
  color: #b91c1c;
  font-weight: 800;
}
</style>
