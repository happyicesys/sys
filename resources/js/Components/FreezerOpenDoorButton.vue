<template>
  <!--
    "Open Door (Restock)" for a Smart Freezer ops-job item — the freezer twin of
    CityboxOpenDoorButton. Confirmation modal → POST (FREEZERCTL unlock) → poll the
    command until the freezer answers. Driver-level: the server decides access; the
    button never hides on permission.
  -->
  <span>
    <Button
      type="button"
      class="text-white flex items-center space-x-1"
      :class="[compact ? 'text-xs px-2 py-1' : '', disabled ? 'bg-gray-300 cursor-not-allowed' : 'bg-sky-700 hover:bg-sky-800']"
      :disabled="disabled || busy"
      :title="disabledReason || 'Unlock the freezer for restocking'"
      @click.prevent="askConfirm"
    >
      <LockOpenIcon class="h-4 w-4" />
      <span>{{ busy ? 'Opening…' : label }}</span>
    </Button>

    <Teleport to="body">
      <Modal :open="confirming" @modalClose="confirming = false">
        <template #header>
          <span class="font-semibold text-black">Open the freezer door?</span>
        </template>
        <template #default>
          <div class="text-sm text-gray-700 space-y-2">
            <p>
              Open the door of freezer <b>{{ machineCode }}</b>
              <span v-if="customerName"> at <b>{{ customerName }}</b></span>?
              The lid will unlock <b>now</b>.
            </p>
            <p class="text-xs text-gray-500">
              This is a restock (ops) open — not a customer sale. It is logged with your name and time.
              The freezer refuses while a customer is mid-purchase; wait and try again.
            </p>
            <p v-if="offline" class="text-xs text-amber-700">
              This freezer is reported <b>offline</b> — the open will likely time out. You can still try.
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
import axios from 'axios'
import { onBeforeUnmount, ref } from 'vue'
import { useToast } from 'vue-toastification'

const props = defineProps({
  itemId: { type: Number, required: true },
  machineCode: [String, Number],
  customerName: String,
  offline: Boolean,
  disabled: Boolean,
  disabledReason: String,
  label: { type: String, default: 'Open Door' },
  compact: Boolean,
})
const emit = defineEmits(['opened'])
const toast = useToast()
const confirming = ref(false)
const busy = ref(false)
let timer = null

// The device answers in a second or two; the command frame expires after TTL + 30 s grace,
// when the server reports `timeout`. Poll a little past that.
const POLL_MS = 2000
const POLL_LIMIT = 60

const REFUSALS = {
  busy: 'A customer is using the freezer — wait for the sale to finish, then try again.',
  timeout: 'The freezer did not answer — it may be offline. Check power / network at the site.',
  unsupported: 'This freezer\'s app cannot open the door remotely.',
}

function askConfirm() { if (!props.disabled) confirming.value = true }

async function open() {
  confirming.value = false
  busy.value = true
  try {
    const { data } = await axios.post(`/ops-jobs/items/${props.itemId}/freezer-open-door`)
    poll(data.cmd_id, 0)
  } catch (e) {
    toast.error(e.response?.data?.message || 'Door open failed', { timeout: 8000 })
    busy.value = false
  }
}

function poll(cmdId, attempt) {
  timer = setTimeout(async () => {
    try {
      const { data } = await axios.get(`/ops-jobs/items/${props.itemId}/freezer-open-door/${cmdId}`)
      if (data.status === 'pending' && attempt < POLL_LIMIT) {
        poll(cmdId, attempt + 1)
        return
      }
      if (data.status === 'ok') {
        toast.success('Door unlocked — restock, then Stock In your count', { timeout: 6000 })
        emit('opened')
      } else {
        toast.error(REFUSALS[data.status] || ('Door open failed: ' + (data.message || data.status)), { timeout: 10000 })
      }
    } catch (e) {
      toast.error(e.response?.data?.message || 'Could not read the freezer\'s answer', { timeout: 8000 })
    }
    busy.value = false
  }, POLL_MS)
}

onBeforeUnmount(() => clearTimeout(timer))
</script>
