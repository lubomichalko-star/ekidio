<template>
  <OverviewView
    v-if="isParentHome"
    :role="role"
    :child-id="childId"
    :localized="localized"
  />
  <ChildView
    v-else
    :role="role"
    :child-id="childId"
    :localized="localized"
  />
</template>

<script setup>
import { computed } from 'vue';
import ChildView from './ChildView.vue';
import OverviewView from './OverviewView.vue';

const props = defineProps({
  role: { type: String, default: 'child' },
  childId: { type: [String, Number], default: '' },
  localized: { type: Object, default: () => ({}) }
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


