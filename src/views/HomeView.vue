<template>
  <DnesView
    v-if="isParentHome"
    :role="role"
    :localized="localized"
    :app-ready="appReady"
  />
  <ChildView
    v-else
    :role="role"
    :child-id="childId"
    :localized="localized"
    :app-ready="appReady"
  />
</template>

<script setup>
import { computed } from 'vue';
import ChildView from './ChildView.vue';
import DnesView from './DnesView.vue';

const props = defineProps({
  role: { type: String, default: 'child' },
  childId: { type: [String, Number], default: '' },
  localized: { type: Object, default: () => ({}) },
  appReady: { type: Boolean, default: false },
});

const localized = computed(() => props.localized || {});
// IMPORTANT: Home screen must follow the runtime auth role (token),
// not shortcode-localized role. Otherwise parent token + child shortcode shows child view.
const role = computed(() => props.role);
const childId = computed(() => props.childId || (localized.value && localized.value.childId) || '');
const normalizedChildId = computed(() => {
  const v = childId.value;
  return v === 0 || v === '0' ? '' : v;
});

const isParentHome = computed(() => {
  const cfg = localized.value || {};
  return role.value === 'parent' && !cfg.forceChild && !normalizedChildId.value;
});
</script>


