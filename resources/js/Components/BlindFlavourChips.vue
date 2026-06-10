<template>
  <div v-if="children.length" class="mt-1.5 border-l-4 border-indigo-300 bg-indigo-50/70 rounded-r-md px-2 py-1.5 text-left">
    <div class="flex items-center gap-1 mb-1">
      <span class="text-xs font-semibold text-indigo-700">↳ Blind flavours ({{ children.length }})</span>
    </div>
    <div class="flex flex-wrap gap-1.5">
      <span
        v-for="child in children"
        :key="child.id"
        class="inline-flex items-center gap-1.5 rounded-full bg-white ring-1 ring-indigo-200 py-0.5 pl-0.5 pr-2 text-[11px] font-medium text-indigo-700"
      >
        <img
          v-if="child.child_product && child.child_product.thumbnail_url"
          :src="child.child_product.thumbnail_url"
          class="h-7 w-7 rounded-full object-cover ring-1 ring-indigo-200 cursor-zoom-in hover:ring-2 hover:ring-indigo-400 transition"
          alt=""
          title="Click to enlarge"
          @click="openPreview(child)"
        />
        <span v-else class="h-7 w-7 rounded-full bg-indigo-100"></span>
        {{ child.child_product ? (child.child_product.code + ' - ' + child.child_product.name) : 'Flavour' }}
        <span class="text-indigo-500">{{ child.weight_pct }}%</span>
      </span>
    </div>

    <Modal :open="preview.open" @modal-close="preview.open = false">
      <template #header>
        <span class="text-base">{{ preview.title }}</span>
      </template>
      <div class="flex justify-center">
        <img :src="preview.url" class="max-h-[70vh] w-auto rounded-lg object-contain" alt="" />
      </div>
    </Modal>
  </div>
</template>

<script setup>
import Modal from '@/Components/Modal.vue';
import { ref, computed } from 'vue';

const props = defineProps({
  product: { type: Object, required: true },
});

const children = computed(() => props.product?.blindChildren ?? []);

const preview = ref({ open: false, url: '', title: '' });
function openPreview(child) {
  const cp = child?.child_product;
  if (!cp || !cp.thumbnail_url) return;
  preview.value = { open: true, url: cp.thumbnail_url, title: `${cp.code} - ${cp.name}` };
}
</script>
