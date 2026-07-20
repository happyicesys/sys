<template>

  <Head title="MCP Access" />

  <BreezeAuthenticatedLayout>
    <template #header>
      <h2 class="font-semibold text-xl text-gray-800 leading-tight">
        Admin (MCP Access Tokens)
      </h2>
    </template>

    <div class="m-2 sm:mx-5 sm:my-3 px-1 sm:px-2 lg:px-3">
      <div class="-mx-4 sm:-mx-6 lg:-mx-8 bg-white rounded-md border my-3 px-3 md:px-3 py-3">
        <p class="text-sm text-gray-600">
          Tokens let a chosen person query the database read-only through Claude, tied to their system account.
          Issue one token per person. Revoke any time to cut their access instantly.
        </p>
        <div class="flex justify-end mt-3">
          <Button v-if="permissions.manage"
            class="inline-flex space-x-1 items-center rounded-md border border-green bg-green-500 px-5 py-3 md:px-4 text-sm font-medium leading-4 text-white shadow-sm hover:bg-green-600 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2"
            @click="openCreate()"
          >
            <PlusIcon class="h-4 w-4" aria-hidden="true"/>
            <span>Issue token</span>
          </Button>
        </div>
      </div>

      <div class="mt-6 flex flex-col">
        <div class="-my-2 -mx-4 sm:-mx-6 lg:-mx-8">
          <div class="shadow-sm ring-1 ring-black ring-opacity-5 overflow-scroll">
            <table class="min-w-full border-separate" style="border-spacing: 0">
              <thead class="bg-gray-100">
                <tr class="divide-x divide-gray-200">
                  <TableHead>#</TableHead>
                  <TableHead>User</TableHead>
                  <TableHead>Label</TableHead>
                  <TableHead>Token</TableHead>
                  <TableHead>Created</TableHead>
                  <TableHead>Status</TableHead>
                  <TableHead></TableHead>
                </tr>
              </thead>
              <tbody class="bg-white">
                <tr v-for="(t, i) in tokens" :key="t.id" class="divide-x divide-y-2 divide-gray-300 odd:bg-white even:bg-gray-100">
                  <TableData :currentIndex="i" :totalLength="tokens.length" inputClass="text-center">{{ i + 1 }}</TableData>
                  <TableData :currentIndex="i" :totalLength="tokens.length" inputClass="text-left">
                    <div class="font-medium">{{ t.user_name || '—' }}</div>
                    <div class="text-xs text-gray-500">{{ t.user_email }}</div>
                  </TableData>
                  <TableData :currentIndex="i" :totalLength="tokens.length" inputClass="text-left">{{ t.name }}</TableData>
                  <TableData :currentIndex="i" :totalLength="tokens.length" inputClass="text-left">
                    <span class="font-mono text-xs text-gray-500">mk1_…{{ t.last_four }}</span>
                  </TableData>
                  <TableData :currentIndex="i" :totalLength="tokens.length" inputClass="text-left">{{ t.created_at }}</TableData>
                  <TableData :currentIndex="i" :totalLength="tokens.length" inputClass="text-center">
                    <span v-if="!t.revoked" class="inline-flex rounded-full bg-green-100 px-2 text-xs font-semibold leading-5 text-green-800">Active</span>
                    <span v-else class="inline-flex rounded-full bg-gray-200 px-2 text-xs font-semibold leading-5 text-gray-600">Revoked</span>
                  </TableData>
                  <TableData :currentIndex="i" :totalLength="tokens.length" inputClass="text-center">
                    <Button v-if="permissions.manage && !t.revoked"
                      type="button" class="bg-red-300 hover:bg-red-400 px-3 py-2 text-xs text-red-800 flex space-x-1"
                      @click="revoke(t)"
                    >
                      <TrashIcon class="w-4 h-4"></TrashIcon>
                      <span>Revoke</span>
                    </Button>
                  </TableData>
                </tr>
                <tr v-if="!tokens.length">
                  <td colspan="24" class="relative whitespace-nowrap py-4 pr-4 pl-3 text-sm font-medium sm:pr-6 lg:pr-8 text-center">
                    No tokens issued yet
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>

    <!-- Issue token modal -->
    <Teleport to="body">
      <Modal :open="showCreate" @modalClose="showCreate = false">
        <template #header>
          <span>Issue MCP token</span>
        </template>
        <template #default>
          <form @submit.prevent="submitCreate" id="mcp-create">
            <div class="grid grid-cols-1 gap-y-3">
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">User</label>
                <MultiSelect
                  v-model="selectedUser"
                  :options="users"
                  valueProp="id"
                  label="label"
                  placeholder="Select a user"
                />
                <p v-if="createForm.errors.user_id" class="text-red-600 text-xs mt-1">{{ createForm.errors.user_id }}</p>
              </div>
              <div>
                <FormInput v-model="createForm.name" :error="createForm.errors.name" required="true">
                  Label (e.g. "boss's laptop")
                </FormInput>
              </div>
            </div>
            <div class="flex space-x-1 mt-5 justify-end">
              <Button class="bg-gray-300 hover:bg-gray-400 text-gray-700 flex space-x-1" type="button" @click="showCreate = false">
                <ArrowUturnLeftIcon class="w-4 h-4"></ArrowUturnLeftIcon>
                <span>Back</span>
              </Button>
              <Button type="submit" :disabled="createForm.processing" class="bg-green-500 hover:bg-green-600 text-white flex space-x-1">
                <CheckCircleIcon class="w-4 h-4"></CheckCircleIcon>
                <span>Create</span>
              </Button>
            </div>
          </form>
        </template>
      </Modal>
    </Teleport>

    <!-- Reveal-once modal -->
    <Teleport to="body">
      <Modal :open="showReveal" @modalClose="closeReveal">
        <template #header>
          <span>Token created — copy it now</span>
        </template>
        <template #default>
          <div class="space-y-3">
            <p class="text-sm text-red-600 font-medium">
              This token is shown only once. Copy it now and give it to the person — it can't be viewed again.
            </p>
            <div class="flex items-center space-x-2">
              <input ref="tokenInput" :value="newToken" readonly
                class="w-full font-mono text-xs border rounded px-2 py-2 bg-gray-50" @focus="$event.target.select()"/>
              <Button type="button" class="bg-gray-700 hover:bg-gray-800 text-white flex space-x-1" @click="copyToken">
                <ClipboardIcon class="w-4 h-4"></ClipboardIcon>
                <span>{{ copied ? 'Copied' : 'Copy' }}</span>
              </Button>
            </div>
            <p class="text-xs text-gray-500">
              The person sets this as <span class="font-mono">MARK1_MCP_TOKEN</span> in their Claude connector, or pastes it as the token when adding the connector by URL.
            </p>
            <div class="flex justify-end">
              <Button type="button" class="bg-green-500 hover:bg-green-600 text-white" @click="closeReveal">Done</Button>
            </div>
          </div>
        </template>
      </Modal>
    </Teleport>
  </BreezeAuthenticatedLayout>
</template>

<script setup>
import BreezeAuthenticatedLayout from '@/Layouts/Authenticated.vue';
import Button from '@/Components/Button.vue';
import Modal from '@/Components/Modal.vue';
import FormInput from '@/Components/FormInput.vue';
import MultiSelect from '@/Components/MultiSelect.vue';
import TableHead from '@/Components/TableHead.vue';
import TableData from '@/Components/TableData.vue';
import { PlusIcon, TrashIcon, ArrowUturnLeftIcon, CheckCircleIcon, ClipboardIcon } from '@heroicons/vue/20/solid';
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import { ref, watch, nextTick } from 'vue';
import { useToast } from "vue-toastification";

const props = defineProps({
  tokens: { type: Array, default: () => [] },
  users: { type: Array, default: () => [] },
  permissions: { type: Object, default: () => ({ manage: false }) },
})

const page = usePage()
const toast = useToast()

const showCreate = ref(false)
const selectedUser = ref(null)
const createForm = useForm({ user_id: null, name: '' })

const showReveal = ref(false)
const newToken = ref('')
const copied = ref(false)
const tokenInput = ref(null)

function openCreate() {
  selectedUser.value = null
  createForm.reset()
  createForm.clearErrors()
  showCreate.value = true
}

function submitCreate() {
  createForm.user_id = selectedUser.value ? selectedUser.value.id : null
  createForm.clearErrors()
  createForm.post('/mcp-tokens/create', {
    preserveScroll: true,
    onSuccess: () => {
      showCreate.value = false
      selectedUser.value = null
      createForm.reset()
    },
    onError: () => {
      toast.error("Failed to issue token", { timeout: 3000 })
    },
  })
}

function revoke(t) {
  const ok = confirm('Revoke this token for ' + (t.user_name || 'this user') + '? Their access stops immediately.')
  if (!ok) return
  router.delete('/mcp-tokens/' + t.id, {
    preserveScroll: true,
    onSuccess: () => toast.success("Token revoked", { timeout: 3000 }),
    onError: () => toast.error("Failed to revoke token", { timeout: 3000 }),
  })
}

async function copyToken() {
  try {
    await navigator.clipboard.writeText(newToken.value)
    copied.value = true
    setTimeout(() => { copied.value = false }, 2000)
  } catch (e) {
    if (tokenInput.value) { tokenInput.value.focus(); tokenInput.value.select() }
  }
}

function closeReveal() {
  showReveal.value = false
  newToken.value = ''
}

// A freshly minted token arrives once via flash; reveal it, then it's gone.
watch(
  () => page.props.flash && page.props.flash.mcpNewToken,
  (val) => {
    if (val) {
      newToken.value = val
      showReveal.value = true
      copied.value = false
      nextTick(() => { if (tokenInput.value) tokenInput.value.select() })
    }
  },
  { immediate: true }
)
</script>
