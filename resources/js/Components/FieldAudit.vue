<template>
  <!-- Inline "who last changed this field, and when", derived from user_logs.
       Renders nothing when there is no recorded change for the field. -->
  <p v-if="entry && entry.who" class="mt-1 text-xs text-gray-400 italic">
    {{ entry.who }} &middot; {{ fmt(entry.at) }}
  </p>
</template>

<script setup>
import moment from 'moment';

defineProps({
  entry: {
    type: Object,
    default: null,
  },
});

function fmt(iso) {
  return iso ? moment(iso).format('YYMMDD hh:mm a') : '';
}
</script>
