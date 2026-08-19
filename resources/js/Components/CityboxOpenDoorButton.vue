<template>
  <!--
    "Open Door (Restock)" for a Smart Chiller ops-job item (design §6b).
    Same component on the ops-job page item column and the item edit page:
    confirmation modal (the app's own Modal, readable on a phone) → POST →
    toast. Driver-level: the server decides access; the button never hides on
    permission, only on state (completed / no equipment id).
  -->
  <span>
    <Button
      type="button"
      class="text-white flex items-center space-x-1"
      :class="[compact ? 'text-xs px-2 py-1' : '', disabled ? 'bg-gray-300 cursor-not-allowed' : 'bg-sky-700 hover:bg-sky-800']"
      :disabled="disabled || busy"
      :title="disabledReason || 'Unlock the chiller for restocking'"
      @click.prevent="askConfirm"
    >
      <LockOpenIcon class="h-4 w-4" />
      <span>{{ busy ? 'Opening…' : label }}</span>
    </Button>

    <Teleport to="body">
      <Modal :open="confirming" @modalClose="confirming = false">
        <template #header>
          <span class="font-semibold text-black">Open the chiller door?</span>
        </template>
        <template #default>
          <div class="text-sm text-gray-700 space-y-2">
            <p>
              Open the door of
              <b>{{ cityboxName || equipmentId }}</b>
              <span v-if="customerName"> at <b>{{ customerName }}</b></span>?
              The cabinet will unlock <b>now</b>.
            </p>
            <p class="text-xs text-gray-500">
              This is a restock (ops) open — not a customer session. It is logged with your name and time.
              After restocking, Stock In the item to push your count to CityBox.
            </p>
            <p v-if="offline" class="text-xs text-amber-700">
              CityBox last reported this chiller <b>offline</b>{{ offlineSince ? ' since ' + offlineSince : '' }} — the open may be refused. You can still try (status can be a few minutes stale).
            </p>
          </div>
          <div class="mt-4 flex justify-end space-x-2">
            <Button type="button" class="bg-gray-200 hover:bg-gray-300 text-gray-800" @click.prevent="confirming = false">Cancel</Button>
            <Button type="button" class="bg-sky-700 hover:bg-sky-800 text-white" @click.prevent="open">Yes, open</Button>
          </div>
        </template>
      </Modal>
    </Teleport>
  </span>
</template>

<script setup>
import Button from '@/Components/Button.vue'
import Modal from '@/Components/Modal.vue'
import { LockOpenIcon } from '@heroicons/vue/20/solid'
import { router } from '@inertiajs/vue3'
import { ref } from 'vue'
import { useToast } from 'vue-toastification'

const props = defineProps({
  itemId: { type: Number, required: true },
  equipmentId: String,
  cityboxName: String,
  customerName: String,
  offline: Boolean,
  offlineSince: String,
  disabled: Boolean,
  disabledReason: String,
  source: { type: String, default: 'ops_job_page' },
  label: { type: String, default: 'Open Door' },
  compact: Boolean,
})
const emit = defineEmits(['opened'])
const toast = useToast()
const confirming = ref(false)
const busy = ref(false)

function askConfirm() { if (!props.disabled) confirming.value = true }

function open() {
  confirming.value = false
  busy.value = true
  router.post(`/ops-jobs/items/${props.itemId}/citybox-open-door`, { source: props.source }, {
    preserveScroll: true, preserveState: true,
    onSuccess: () => { toast.success('Door opened — restock, then Stock In to push your count', { timeout: 6000 }); emit('opened') },
    onError: (e) => toast.error(e.citybox || 'Door open failed', { timeout: 8000 }),
    onFinish: () => { busy.value = false },
  })
}
</script>
