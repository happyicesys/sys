<template>
  <!--
    The Payment Gateway(s) table used to print key1 under a hardcoded "Public
    Key" header, which is only right for Omise - Midtrans' key1 is its Server
    Key. Render whatever labels the gateway itself defines (key1_name ..
    key3_name) so every credential an operator needs is on this page instead of
    in the database.

    Used by Operator/Edit.vue and Operator/Form.vue. Reveal state is per
    COMPONENT INSTANCE, so it cannot leak between rows - the two host pages key
    their rows differently (Form.vue's locally-bound rows carry the payment
    gateway id, not the operator_payment_gateway id), and a shared id-keyed map
    would have let one row's eye toggle reveal another's.
  -->
  <div class="flex flex-col items-start space-y-1">
    <div
      v-for="gatewayKey in gatewayKeys"
      :key="gatewayKey.index"
      class="flex items-center space-x-2"
    >
      <span class="text-xs text-gray-500 whitespace-nowrap">{{ gatewayKey.label }}:</span>
      <span class="font-mono text-xs text-left break-all">
        {{ gatewayKey.secret && !revealed[gatewayKey.index] ? maskGatewayKey(gatewayKey.value) : gatewayKey.value }}
      </span>
      <button
        type="button"
        v-if="gatewayKey.secret"
        class="text-gray-400 hover:text-gray-600"
        v-tooltip="revealed[gatewayKey.index] ? 'Hide' : 'Show'"
        @click.prevent="revealed[gatewayKey.index] = !revealed[gatewayKey.index]"
      >
        <EyeSlashIcon v-if="revealed[gatewayKey.index]" class="w-4 h-4"></EyeSlashIcon>
        <EyeIcon v-else class="w-4 h-4"></EyeIcon>
      </button>
      <button
        type="button"
        class="text-gray-400 hover:text-gray-600"
        v-tooltip="'Copy ' + gatewayKey.label"
        @click.prevent="copyGatewayKey(gatewayKey)"
      >
        <ClipboardIcon class="w-4 h-4"></ClipboardIcon>
      </button>
    </div>
    <span v-if="!gatewayKeys.length" class="text-xs text-gray-400">
      -
    </span>
  </div>
</template>

<script setup>
import { computed, reactive } from 'vue';
import { ClipboardIcon, EyeIcon, EyeSlashIcon } from '@heroicons/vue/20/solid';
import { useToast } from "vue-toastification";

const props = defineProps({
  operatorPaymentGateway: Object,
})

const toast = useToast()
const revealed = reactive({})

// Which labels are safe to print in the clear. Deliberately a whitelist of
// PUBLIC labels rather than a blacklist of secret-sounding ones: a new gateway
// row labelled "API Key" / "Access Key" / "Signature" must default to masked,
// not to plaintext. Covers every label in payment_gateways today - Omise
// ("Public Key" / "Secret Key") and Midtrans ("Server Key" / "Client Key" /
// "Merchant ID").
const PUBLIC_KEY_LABEL = /^\s*(public|client|merchant)\b/i

const gatewayKeys = computed(() => {
  const paymentGateway = props.operatorPaymentGateway.paymentGateway || {}

  return [1, 2, 3]
    .map((index) => ({
      index,
      label: paymentGateway[`key${index}_name`],
      value: props.operatorPaymentGateway[`key${index}`],
      secret: !PUBLIC_KEY_LABEL.test(paymentGateway[`key${index}_name`] || ''),
    }))
    .filter((gatewayKey) => gatewayKey.label && gatewayKey.value)
})

function maskGatewayKey(value) {
  return '•'.repeat(Math.min(String(value || '').length, 24))
}

async function copyGatewayKey(gatewayKey) {
  // navigator.clipboard is undefined outside a secure context; the catch turns
  // that into a "copy it manually" toast rather than an unhandled rejection.
  try {
    await navigator.clipboard.writeText(gatewayKey.value)
    toast.success(`${gatewayKey.label} copied.`)
  } catch (error) {
    toast.error('Could not copy to clipboard. Please copy manually.')
  }
}
</script>
