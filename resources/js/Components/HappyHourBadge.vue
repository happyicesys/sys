<template>
  <span v-if="hh" v-tooltip="tooltip"
    class="inline-flex items-center rounded px-1 py-0.5 text-xs font-semibold border whitespace-nowrap bg-amber-100 text-amber-800 border-amber-300 cursor-help">
    ★ {{ hh.label ?? $t('HH :pct%', { pct: hh.discount_pct }) }}
  </span>
</template>

<script setup>
// The Happy Hour slot that priced a sale or a line (HappyHourSales::badges, tagged at ingest) —
// a Campaign Label(s) chip, "★ HH 30%".
import { computed } from 'vue'
import { t } from '@/i18n'

const props = defineProps({
  hh: { type: Object, default: null },
  exponent: { type: Number, default: 2 },
})

const money = (cents) => (cents / Math.pow(10, props.exponent)).toFixed(props.exponent)
const escapeHtml = (text) => String(text ?? '').replace(/[&<>"']/g, ch => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[ch]))

const tooltip = computed(() => props.hh
  ? {
      content: `<div style="text-align:left;line-height:1.35;"><strong>${escapeHtml(t('Happy Hour'))}</strong> · ${escapeHtml(props.hh.campaign)}<br>`
        + `${escapeHtml(t('Slot :slot · :price (was :was)', { slot: props.hh.slot, price: money(props.hh.promo_price), was: money(props.hh.original_price) }))}</div>`,
      html: true,
    }
  : null)
</script>
