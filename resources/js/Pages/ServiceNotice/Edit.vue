<template>
  <Head :title="notice.display_code" />
  <BreezeAuthenticatedLayout>
    <template #header>
      <div class="flex flex-wrap items-center gap-2">
        <WrenchScrewdriverIcon class="h-5 w-5 text-amber-600" />
        <span class="text-gray-600">Service Notice 维修单</span>
        <span class="font-semibold">{{ notice.display_code }}</span>
        <span class="inline-flex items-center rounded px-2 py-0.5 text-xs font-medium border" :class="stopStatusClass(notice.status)">
          {{ notice.status_name }}
        </span>
      </div>
    </template>

    <div class="m-2 sm:mx-5 sm:my-3 space-y-3">
      <!-- ── Header ─────────────────────────────────────────── -->
      <div class="bg-white shadow-sm ring-1 ring-black ring-opacity-5 rounded-lg p-4 grid grid-cols-1 sm:grid-cols-6 gap-3 text-sm">
        <div class="sm:col-span-2">
          <div class="text-xs text-gray-500">Machine</div>
          <div class="font-semibold text-gray-900">{{ notice.vend_code }}</div>
          <div class="text-gray-700">{{ notice.customer_name }}</div>
        </div>
        <div class="sm:col-span-2">
          <div class="text-xs text-gray-500">Address</div>
          <a v-if="mapUrl" :href="mapUrl" target="_blank" rel="noopener noreferrer" class="text-blue-600 underline">
            {{ notice.address.full_address || notice.address.postcode }}
          </a>
          <span v-else class="text-gray-400">—</span>
        </div>
        <div class="sm:col-span-2">
          <div class="text-xs text-gray-500">Daily Job</div>
          <Link :href="'/ops-jobs/' + notice.ops_job_id + '/edit'" class="text-blue-600 underline">
            {{ notice.ops_job?.date_label }} · #{{ notice.ops_job?.code }}
          </Link>
          <div class="text-gray-700">{{ notice.ops_job?.delivered_by_name || 'Unassigned' }}</div>
        </div>
        <div class="sm:col-span-5">
          <label class="text-xs text-gray-500">Remarks</label>
          <input
            type="text" v-model="remarks" :disabled="!canUpdate"
            class="mt-1 shadow-sm focus:ring-amber-500 focus:border-amber-500 block w-full text-sm border-gray-300 rounded-md"
            @change="saveRemarks"
          />
        </div>
        <div class="sm:col-span-1 flex items-end text-xs text-gray-500">
          <span>Opened {{ notice.created_at }}<span v-if="notice.created_by_name"> ({{ notice.created_by_name }})</span></span>
        </div>
      </div>

      <div class="rounded-md bg-red-50 border border-red-200 text-red-700 text-sm px-3 py-2" v-if="pageError">{{ pageError }}</div>

      <!-- ── Items ──────────────────────────────────────────── -->
      <div
        v-for="(item, index) in notice.items" :key="item.id"
        class="bg-white shadow-sm ring-1 ring-black ring-opacity-5 rounded-lg p-4"
      >
        <div class="flex flex-wrap items-center justify-between gap-2 border-b border-gray-100 pb-2 mb-3">
          <div class="flex items-center gap-2">
            <span class="text-base font-semibold text-gray-900">#{{ index + 1 }}</span>
            <span class="inline-flex items-center rounded px-2 py-0.5 text-xs font-medium border" :class="itemStatusClass(item.status)">
              {{ item.status_name }}
            </span>
            <span class="text-xs text-gray-500" v-if="item.status_changed_at">
              {{ item.status_changed_at }}<span v-if="item.status_changed_by_name"> ({{ item.status_changed_by_name }})</span>
            </span>
          </div>
          <div class="flex flex-wrap gap-1" v-if="isOpen && canUpdate">
            <Button v-if="item.status !== 2" class="bg-green-500 hover:bg-green-600 text-white" @click="setStatus(item, 2)">Done 完成</Button>
            <Button v-if="item.status !== 90" class="bg-orange-500 hover:bg-orange-600 text-white" @click="askIncomplete(item)">Incomplete 未能完成</Button>
            <Button v-if="item.status !== 99" class="bg-gray-400 hover:bg-gray-500 text-white" @click="setStatus(item, 99)">Cancelled 取消</Button>
            <Button v-if="item.status !== 1" class="bg-white hover:bg-gray-100 text-gray-700 border border-gray-300" @click="setStatus(item, 1)">
              <ArrowUturnLeftIcon class="h-3 w-3" />
            </Button>
            <Button v-if="canDelete" class="bg-red-500 hover:bg-red-600 text-white" @click="removeItem(item)">
              <TrashIcon class="h-3 w-3" />
            </Button>
          </div>
        </div>

        <div class="text-sm text-red-700 bg-red-50 border border-red-200 rounded px-2 py-1 mb-3" v-if="item.status === 90 && item.incomplete_reason">
          Reason 原因: {{ item.incomplete_reason }}
        </div>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
          <div v-for="column in COLUMNS" :key="column.field">
            <label class="block text-sm font-medium text-gray-700">{{ column.label }}</label>
            <textarea
              v-model="item[column.field]" rows="3" :disabled="!isOpen || !canUpdate"
              class="mt-1 shadow-sm focus:ring-amber-500 focus:border-amber-500 block w-full text-sm border-gray-300 rounded-md disabled:bg-gray-50"
              @change="saveItem(item)"
            ></textarea>
            <div class="mt-2">
              <StopAttachmentSlot
                :files="filesIn(item, column.slot)"
                :uploadUrl="'/service-notices/items/' + item.id + '/attachments'"
                :deleteUrl="file => '/service-notices/items/' + item.id + '/attachments/' + file.id"
                :extraFields="{ slot: column.slot }"
                :editable="isOpen && canUpdate"
                @changed="apply"
              />
            </div>
          </div>
        </div>
        <div class="text-xs text-red-600 mt-2" v-if="itemErrors[item.id]">{{ itemErrors[item.id] }}</div>
      </div>

      <!-- ── Add item ───────────────────────────────────────── -->
      <form v-if="isOpen && canUpdate" @submit.prevent="addItem" class="bg-white shadow-sm ring-1 ring-black ring-opacity-5 rounded-lg p-4 flex flex-col sm:flex-row gap-2">
        <input
          type="text" v-model="newItemDesc" placeholder="New service item 新维修项目"
          class="shadow-sm focus:ring-amber-500 focus:border-amber-500 block w-full text-sm border-gray-300 rounded-md"
        />
        <Button type="submit" class="bg-amber-500 hover:bg-amber-600 text-white whitespace-nowrap" :class="{ 'opacity-50': !newItemDesc.trim() }" :disabled="!newItemDesc.trim()">
          <div class="flex items-center space-x-1"><PlusCircleIcon class="h-4 w-4" /><span>Add Item</span></div>
        </Button>
      </form>

      <!-- ── Actions ────────────────────────────────────────── -->
      <div class="bg-white shadow-sm ring-1 ring-black ring-opacity-5 rounded-lg p-4 flex flex-wrap items-center justify-between gap-2">
        <div class="text-sm text-gray-600">
          <span v-if="notice.status === 3">Completed {{ notice.completed_at }}<span v-if="notice.completed_by_name"> by {{ notice.completed_by_name }}</span></span>
          <span v-else-if="notice.status === 99">Cancelled {{ notice.cancelled_at }}</span>
          <span v-else>{{ notice.items_resolved_count }} of {{ notice.items_count }} item(s) have a progress status</span>
        </div>
        <div class="flex flex-wrap gap-2">
          <Link :href="'/ops-jobs/' + notice.ops_job_id + '/edit'">
            <Button class="bg-gray-300 hover:bg-gray-400 text-gray-800">Back to Job</Button>
          </Link>
          <template v-if="canUpdate">
            <Button v-if="isOpen" class="bg-gray-500 hover:bg-gray-600 text-white" @click="act('cancel', 'Cancel this service notice?')">Cancel Notice</Button>
            <Button v-if="notice.status === 3" class="bg-white hover:bg-gray-100 text-gray-700 border border-gray-300" @click="act('undo-complete')">Reopen</Button>
            <Button
              v-if="isOpen" class="bg-green-600 hover:bg-green-700 text-white"
              :class="{ 'opacity-50 cursor-not-allowed': !allResolved }"
              :title="allResolved ? '' : 'Every item needs a progress status first'"
              @click="act('complete')"
            >Complete 完成维修单</Button>
          </template>
          <Button v-if="canDelete" class="bg-red-500 hover:bg-red-600 text-white" @click="destroy">Delete</Button>
        </div>
      </div>
    </div>

    <!-- ── Incomplete reason ────────────────────────────────── -->
    <Teleport to="body">
      <Modal :open="!!incompleteItem" @modalClose="incompleteItem = null">
        <template #header><span class="text-base font-semibold">Why could it not be completed? 未能完成的原因</span></template>
        <template #default>
          <form @submit.prevent="confirmIncomplete" class="space-y-3 text-sm">
            <div class="text-gray-700">{{ incompleteItem?.desc }}</div>
            <textarea v-model="incompleteReason" rows="3" class="shadow-sm focus:ring-amber-500 focus:border-amber-500 block w-full text-sm border-gray-300 rounded-md" placeholder="e.g. no spare part on the van"></textarea>
            <div class="text-red-600" v-if="incompleteError">{{ incompleteError }}</div>
            <div class="flex justify-end space-x-2">
              <Button type="button" class="bg-gray-300 hover:bg-gray-400 text-gray-800" @click="incompleteItem = null">Back</Button>
              <Button type="submit" class="bg-orange-500 hover:bg-orange-600 text-white" :class="{ 'opacity-50': !incompleteReason.trim() }" :disabled="!incompleteReason.trim()">Mark Incomplete</Button>
            </div>
          </form>
        </template>
      </Modal>
    </Teleport>
  </BreezeAuthenticatedLayout>
</template>

<script setup>
import BreezeAuthenticatedLayout from '@/Layouts/Authenticated.vue'
import Button from '@/Components/Button.vue'
import Modal from '@/Components/Modal.vue'
import StopAttachmentSlot from '@/Pages/OpsJob/Stops/StopAttachmentSlot.vue'
import { firstErrors, stopStatusClass } from '@/Pages/OpsJob/Stops/stopTypes'
import { ArrowUturnLeftIcon, PlusCircleIcon, TrashIcon, WrenchScrewdriverIcon } from '@heroicons/vue/20/solid'
import { Head, Link, router, usePage } from '@inertiajs/vue3'
import { computed, ref } from 'vue'
import { useToast } from 'vue-toastification'

const props = defineProps({ serviceNotice: Object })

// attachments.type — which column of the item a file belongs to (ServiceNoticeItem::SLOT_*)
const COLUMNS = [
  { field: 'desc', slot: 1, label: 'Description 维修项目' },
  { field: 'desc_before', slot: 2, label: 'Before 维修前' },
  { field: 'desc_after', slot: 3, label: 'After 维修后' },
]

const toast = useToast()
const permissions = usePage().props.auth.permissions
const canUpdate = permissions.includes('update service-notices')
const canDelete = permissions.includes('delete service-notices')

const notice = ref(props.serviceNotice.data)
const remarks = ref(notice.value.remarks || '')
const newItemDesc = ref('')
const pageError = ref('')
const itemErrors = ref({})
const incompleteItem = ref(null)
const incompleteReason = ref('')
const incompleteError = ref('')

const isOpen = computed(() => notice.value.status === 1)
const allResolved = computed(() => notice.value.items.length > 0 && notice.value.items.every(item => item.status !== 1))
const mapUrl = computed(() => {
  const address = notice.value.address
  if (!address) return null
  if (address.latitude && address.longitude) {
    return 'https://www.google.com/maps/search/?api=1&query=' + address.latitude + ',' + address.longitude
  }
  return address.postcode ? 'https://www.google.com/maps/search/?api=1&query=' + encodeURIComponent(address.postcode) : null
})

function filesIn(item, slot) {
  return (item.attachments || []).filter(file => Number(file.type) === slot)
}

function itemStatusClass(status) {
  if (status === 2) return 'bg-green-100 text-green-800 border-green-300'
  if (status === 90) return 'bg-red-100 text-red-800 border-red-300'
  if (status === 99) return 'bg-gray-200 text-gray-600 border-gray-300'
  return 'bg-yellow-100 text-yellow-800 border-yellow-300'
}

// Every write answers with the whole notice, so the page has one way to update.
function apply(data) {
  if (data?.serviceNotice) {
    notice.value = data.serviceNotice.data ?? data.serviceNotice
    remarks.value = notice.value.remarks || ''
  }
  pageError.value = ''
}

function fail(error, item = null) {
  const message = Object.values(firstErrors(error))[0]
  if (item) {
    itemErrors.value = { ...itemErrors.value, [item.id]: message }
  } else {
    pageError.value = message
  }
}

function saveRemarks() {
  axios.post('/service-notices/' + notice.value.id + '/update', { remarks: remarks.value || null }).then(({ data }) => apply(data)).catch(fail)
}

function saveItem(item) {
  itemErrors.value = { ...itemErrors.value, [item.id]: '' }
  axios.post('/service-notices/items/' + item.id + '/update', {
    desc: item.desc, desc_before: item.desc_before, desc_after: item.desc_after,
  }).then(({ data }) => apply(data)).catch(error => fail(error, item))
}

function setStatus(item, status, reason = null) {
  itemErrors.value = { ...itemErrors.value, [item.id]: '' }
  return axios.post('/service-notices/items/' + item.id + '/status', { status, incomplete_reason: reason })
    .then(({ data }) => apply(data))
}

function askIncomplete(item) {
  incompleteItem.value = item
  incompleteReason.value = item.incomplete_reason || ''
  incompleteError.value = ''
}

function confirmIncomplete() {
  setStatus(incompleteItem.value, 90, incompleteReason.value)
    .then(() => { incompleteItem.value = null })
    .catch(error => { incompleteError.value = Object.values(firstErrors(error))[0] })
}

function addItem() {
  axios.post('/service-notices/' + notice.value.id + '/items', { desc: newItemDesc.value })
    .then(({ data }) => { apply(data); newItemDesc.value = '' })
    .catch(fail)
}

function removeItem(item) {
  if (!confirm('Delete item #' + item.sequence + ' and its photos? This cannot be undone.')) return
  axios.delete('/service-notices/items/' + item.id).then(({ data }) => apply(data)).catch(fail)
}

function act(action, question = null) {
  if (action === 'complete' && !allResolved.value) {
    pageError.value = 'Not able to complete: every item needs a progress status, 无法完成这维修单，请在每个维修项目，提供状况进展'
    return
  }
  if (question && !confirm(question)) return
  axios.post('/service-notices/' + notice.value.id + '/' + action)
    .then(({ data }) => { apply(data); toast.success('Saved', { timeout: 2000 }) })
    .catch(fail)
}

function destroy() {
  if (!confirm('Delete ' + notice.value.display_code + ' with all its items and photos? This cannot be undone.')) return
  axios.delete('/service-notices/' + notice.value.id)
    .then(({ data }) => router.visit(data.redirect))
    .catch(fail)
}
</script>
