<template>
  <Modal :open="open" @modal-close="$emit('close')">
    <template #header>
      <span class="text-base">{{ title }}</span>
    </template>
    <div class="flex justify-center">
      <img v-if="url" :src="url" class="max-h-[70vh] w-auto rounded-lg object-contain" alt="" />
    </div>
  </Modal>
</template>

<script setup>
// Shared "click a thumbnail to see it big" popup. Thumbnails are small and
// object-contain, so the driver can only tell two similar packs apart by
// enlarging one — see OpsJob/EditItem.vue and BlindFlavourChips.vue.
import Modal from '@/Components/Modal.vue';

defineProps({
  open: { type: Boolean, default: false },
  url: { type: String, default: '' },
  title: { type: String, default: '' },
});

defineEmits(['close']);
</script>
