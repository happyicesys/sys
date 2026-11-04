<script setup>
// One Products index cell: the photo an AI holds of a product (App\Services\Product\ProductAiPhotos).
defineProps({
  cell: { type: Object, default: null },
  kind: { type: String, required: true }, // freezer | chiller
});
</script>

<template>
  <div v-if="cell" class="flex flex-col items-center space-y-1">
    <a v-if="cell.photo_url" :href="cell.photo_url" target="_blank">
      <img class="h-24 w-24 rounded-lg object-contain bg-white border border-gray-200 p-1" :src="cell.photo_url" alt="" loading="lazy" />
    </a>
    <span v-if="cell.photo_count" class="text-xs text-gray-600 whitespace-nowrap">{{ $tc(':count photo|:count photos', cell.photo_count) }}</span>
    <span v-if="cell.has_ai && !cell.photo_url" class="text-xs text-gray-600 whitespace-nowrap">{{ $t('No photo copy') }}</span>
    <span v-if="cell.note === 'draft'" class="rounded bg-gray-100 px-1.5 py-0.5 text-xs text-gray-700 whitespace-nowrap">{{ $t('Draft') }}</span>
    <span v-else-if="cell.note === 'submitted'" class="rounded bg-amber-100 px-1.5 py-0.5 text-xs text-amber-800 whitespace-nowrap">{{ $t('Waiting for approval') }}</span>
    <span v-else-if="cell.note === 'rejected'" class="rounded bg-red-100 px-1.5 py-0.5 text-xs text-red-800 whitespace-nowrap">{{ $t('Rejected') }}</span>
    <span v-else-if="cell.note === 'failed'" class="rounded bg-red-100 px-1.5 py-0.5 text-xs text-red-800 whitespace-nowrap">{{ $t('Failed') }}</span>
    <span v-else-if="cell.note === 'not_listed'" class="rounded bg-gray-100 px-1.5 py-0.5 text-xs text-gray-700 whitespace-nowrap">{{ $t("Not on AI's list") }}</span>
    <span v-else-if="cell.note === 'disabled'" class="rounded bg-gray-100 px-1.5 py-0.5 text-xs text-gray-700 whitespace-nowrap">{{ $t('Disabled in CityBox') }}</span>
    <span v-else-if="cell.note === 'delisted'" class="rounded bg-gray-100 px-1.5 py-0.5 text-xs text-gray-700 whitespace-nowrap">{{ $t('Delisted by CityBox') }}</span>
    <span v-if="cell.missing" class="rounded bg-red-600 px-1.5 py-0.5 text-xs font-medium text-white whitespace-nowrap">{{ kind === 'freezer' ? $t("Not on AI's list") : $t('Not in CityBox') }}</span>
    <span v-if="!cell.has_ai && !cell.photo_url && !cell.missing" class="text-gray-400">—</span>
  </div>
</template>
