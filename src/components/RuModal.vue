<template>
  <Teleport to="body">
    <div class="ru-modal" role="dialog" aria-modal="true">
      <div class="ru-modal__top-safe"></div>
      <div class="ru-modal__wrap">
        <header class="ru-modal__header">
          <div class="ru-modal__title">
            <slot name="title">
              <h2>{{ title }}</h2>
            </slot>
          </div>
          <button class="ru-modal__close" type="button" @click="closeViaUi" aria-label="Zavrieť">
            ×
          </button>
        </header>

        <div class="ru-modal__body">
          <slot />
        </div>

        <footer class="ru-modal__footer" v-if="$slots.footer">
          <slot name="footer" />
        </footer>
      </div>
      <div class="ru-modal__bottom-safe"></div>
    </div>
  </Teleport>
</template>

<script setup>
import { onBeforeUnmount, onMounted } from 'vue';

const props = defineProps({
  title: { type: String, default: '' },
});
const emit = defineEmits(['close']);

const modalId = `ru-modal-${Math.random().toString(36).slice(2)}`;
const openedHref = typeof window !== 'undefined' ? window.location.href : '';
let popHandler = null;

const closeViaUi = () => {
  // If we pushed a history state for this modal, pop it so "Back" doesn't require 2 presses.
  try {
    if (typeof window !== 'undefined') {
      const st = window.history && window.history.state;
      if (st && st.__ruModalId === modalId && window.location.href === openedHref) {
        window.history.back();
      }
    }
  } catch {}
  emit('close');
};

onMounted(() => {
  try {
    if (typeof window === 'undefined') return;
    // Push a state marker so browser/hardware back closes the modal (without changing route).
    window.history.pushState({ __ruModalId: modalId }, '', window.location.href);
    popHandler = () => {
      // Back/Undo should close the modal
      emit('close');
    };
    window.addEventListener('popstate', popHandler);
  } catch {}
});

onBeforeUnmount(() => {
  try {
    if (typeof window !== 'undefined' && popHandler) {
      window.removeEventListener('popstate', popHandler);
    }
  } catch {}
  popHandler = null;
});
</script>

<style scoped>
.ru-modal {
  position: fixed;
  inset: 0;
  z-index: 9999;
  /* Fullscreen, same background as app */
  background: #f3f4f6;
  display: flex;
  flex-direction: column;
}

.ru-modal__top-safe {
  height: env(safe-area-inset-top, 0px);
  flex-shrink: 0;
}
.ru-modal__bottom-safe {
  height: env(safe-area-inset-bottom, 0px);
  flex-shrink: 0;
}

.ru-modal__wrap {
  flex: 1;
  width: 100%;
  max-width: min(760px, 100%);
  margin: 0 auto;
  display: flex;
  flex-direction: column;
  min-height: 0; /* allow body to scroll inside flex */
  padding: 14px 14px 16px;
  box-sizing: border-box;
}

.ru-modal__header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  padding: 10px 12px;
  background: #ffffff;
  border: 1px solid rgba(15, 23, 42, 0.10);
  border-radius: 16px;
  box-shadow: 0 1px 2px rgba(15, 23, 42, 0.06);
  min-width: 0;
  box-sizing: border-box;
}

.ru-modal__title {
  min-width: 0;
}

.ru-modal__title h2 {
  margin: 0;
  font-size: 18px;
  line-height: 1.1;
  font-weight: 800;
  color: #0f172a;
}

.ru-modal__close {
  width: 44px;
  height: 44px;
  border-radius: 14px;
  border: 1px solid rgba(15, 23, 42, 0.10);
  background: #ffffff;
  color: #0f172a;
  font-size: 24px;
  line-height: 1;
  display: grid;
  place-items: center;
  cursor: pointer;
  flex-shrink: 0;
}

.ru-modal__body {
  flex: 1;
  margin-top: 12px;
  overflow: auto;
  overflow-x: hidden;
  -webkit-overflow-scrolling: touch;
  min-height: 0; /* important for iOS/Android WebView scrolling */
  overscroll-behavior: contain;
  min-width: 0;
  box-sizing: border-box;
}

.ru-modal__footer {
  margin-top: 12px;
  background: transparent;
  border: 0;
  border-radius: 0;
  box-shadow: none;
  padding: 0;
  display: flex;
  justify-content: flex-end;
  gap: 10px;
  min-width: 0;
  box-sizing: border-box;
}

@media (max-width: 640px) {
  .ru-modal__wrap {
    padding: 12px;
  }

  .ru-modal__header {
    padding: 10px;
  }

  .ru-modal__title h2 {
    font-size: 17px;
  }

  .ru-modal__footer {
    flex-direction: column;
  }
}
</style>

