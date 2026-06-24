<template>
  <div v-if="updateInfo" class="ru-app-update" role="status" aria-live="polite">
    <div class="ru-app-update__text">
      <strong>Nová verzia {{ updateInfo.latestVersion }}</strong>
      <span>{{ updateInfo.message }}</span>
    </div>
    <div class="ru-app-update__actions">
      <button class="ru-app-update__btn ru-app-update__btn--primary" type="button" @click="goDownload">
        Stiahnuť
      </button>
      <button class="ru-app-update__btn" type="button" @click="dismiss">
        Neskôr
      </button>
    </div>
  </div>
</template>

<script setup>
import { onBeforeUnmount, onMounted, ref } from 'vue';
import { checkForAppUpdate, dismissUpdate, openDownloadPage } from '../lib/appUpdate';

const updateInfo = ref(null);

const refresh = async ({ force = false } = {}) => {
  updateInfo.value = await checkForAppUpdate({ force });
};

const goDownload = () => {
  if (!updateInfo.value?.downloadUrl) return;
  openDownloadPage(updateInfo.value.downloadUrl, updateInfo.value.latestVersion);
};

const dismiss = () => {
  if (!updateInfo.value?.latestVersion) {
    updateInfo.value = null;
    return;
  }
  dismissUpdate(updateInfo.value.latestVersion);
  updateInfo.value = null;
};

const onVisibility = () => {
  if (document.visibilityState === 'visible') refresh();
};

onMounted(() => {
  refresh({ force: true });
  try {
    document.addEventListener('visibilitychange', onVisibility);
  } catch {}
});

onBeforeUnmount(() => {
  try {
    document.removeEventListener('visibilitychange', onVisibility);
  } catch {}
});
</script>

<style scoped>
.ru-app-update {
  margin: 0 auto 10px;
  max-width: var(--ru-card-max-width, 760px);
  padding: 12px 14px;
  border-radius: 14px;
  border: 1px solid rgba(90, 187, 111, 0.35);
  background: linear-gradient(135deg, #ecfdf3 0%, #f0fdf4 100%);
  box-shadow: 0 1px 2px rgba(15, 23, 42, 0.06);
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  flex-wrap: wrap;
}

.ru-app-update__text {
  display: flex;
  flex-direction: column;
  gap: 2px;
  min-width: 0;
}

.ru-app-update__text strong {
  color: #0f172a;
  font-size: 14px;
}

.ru-app-update__text span {
  color: #64748b;
  font-size: 13px;
  font-weight: 600;
}

.ru-app-update__actions {
  display: flex;
  gap: 8px;
  flex-shrink: 0;
}

.ru-app-update__btn {
  border: 1px solid rgba(15, 23, 42, 0.12);
  background: #ffffff;
  color: #0f172a;
  border-radius: 12px;
  padding: 8px 12px;
  font-weight: 800;
  font-size: 13px;
  cursor: pointer;
}

.ru-app-update__btn--primary {
  background: var(--ru-accent, #5abb6f);
  border-color: transparent;
  color: #ffffff;
}

@media (max-width: 640px) {
  .ru-app-update {
    margin-left: 12px;
    margin-right: 12px;
  }

  .ru-app-update__actions {
    width: 100%;
  }

  .ru-app-update__btn {
    flex: 1;
  }
}
</style>
