<template>

  <Head title="VM Management" />

  <BreezeAuthenticatedLayout>
    <template #header>
      <h2 class="font-semibold text-xl text-gray-800 leading-tight">
        {{ typeName }} Machine
      </h2>
    </template>

    <div class="m-2 sm:mx-5 sm:my-3 px-1 sm:px-2 lg:px-3">
      <div class="mt-6 flex flex-col">
       <div class="-my-2 -mx-3 sm:-mx-6 lg:-mx-8">
        <div class="shadow-sm ring-1 ring-black ring-opacity-5 overflow-scroll p-5">
          <form @submit.prevent="submit" id="submit">
            <!-- Machine Type: ONE choice between the three machine kinds, chosen once at creation
                 (Setting/Edit shows it read-only). Each card opens the fields its kind needs —
                 a Smart Chiller is provisioned from a CityBox device (POST /citybox/vends), the
                 other two from a Machine ID (POST /settings/vend/store). -->
            <div class="grid grid-cols-1 gap-3 sm:grid-cols-6 pb-2">
              <div class="sm:col-span-6">
                <label class="flex justify-start text-sm font-medium text-gray-700 mb-1">
                  Machine Type
                  <span class="text-red-500 ml-1">*</span>
                </label>
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-2">
                  <label
                    v-for="option in machineTypeCards"
                    :key="option.id"
                    class="flex items-start gap-2 rounded-md border p-3 transition"
                    :class="[
                      machineType === option.id ? 'border-indigo-500 ring-1 ring-indigo-500 bg-indigo-50/40' : 'border-gray-200',
                      option.disabled ? 'opacity-50 cursor-not-allowed' : 'cursor-pointer hover:bg-gray-50',
                    ]"
                  >
                    <input type="radio" :value="option.id" v-model="machineType" :disabled="option.disabled" class="mt-1 text-indigo-600" />
                    <span class="flex flex-col">
                      <span class="text-sm font-semibold text-gray-900">{{ option.label }}</span>
                      <span class="text-xs text-gray-500">{{ option.hint }}</span>
                    </span>
                  </label>
                </div>
                <p class="mt-1 text-xs text-gray-500">
                  Fixed after creation — it decides which product mappings the machine can use.
                </p>
                <div class="text-sm text-red-600" v-if="form.errors.machine_type">
                  {{ form.errors.machine_type }}
                </div>
              </div>
            </div>

            <!-- Vending Machine / Smart Freezer -->
            <div class="grid grid-cols-1 gap-3 sm:grid-cols-6 pb-2" v-if="!isChiller">
              <div class="sm:col-span-6">
                <SearchVendCodeInput v-model="form.code" @selected="onVendCodeSelected" required="true" :error="form.errors.code">
                  Machine ID
                </SearchVendCodeInput>
              </div>
              <div class="sm:col-span-6" v-if="machineType === 'smart_freezer'">
                <p class="text-xs text-blue-600">
                  Smart Freezer always follows the Site's pricing (Is Using Server Price = Yes).
                </p>
              </div>
              <div class="sm:col-span-3">
                <DatePicker v-model="form.begin_date" :error="form.errors.begin_date"
                v-if="permissions.includes('update machine-settings')">
                  Begin Date
                </DatePicker>
              </div>
            </div>

            <!-- Smart Chiller (CityBox) -->
            <div class="grid grid-cols-1 gap-3 sm:grid-cols-6 pb-2" v-else>
              <div class="sm:col-span-6 rounded-md bg-indigo-50 p-3 text-xs text-indigo-800">
                Pick one of the CityBox devices not yet in ConnectVend. Identity, model, online status and the CityBox name are filled automatically;
                the machine ID is taken from the OPS Pro machine name (e.g. <b>C6003</b>) — rename it there if it is wrong. You must bind it to a site (customer) — the CityBox device name is offered as the site name.
              </div>
              <div class="sm:col-span-4">
                <label class="flex justify-start text-sm font-medium text-gray-700">CityBox device</label>
                <div class="flex space-x-2 mt-1">
                  <!-- Searchable picker (not a plain select): the fleet is keyed by serial,
                       so ops need to type either the serial or the CityBox name to find one. -->
                  <MultiSelect
                    v-model="cb.device"
                    :options="deviceOptions"
                    trackBy="id"
                    valueProp="id"
                    label="label"
                    placeholder="Search a device by ID or name…"
                    open-direction="bottom"
                    class="flex-1"
                    :canClear="true"
                  >
                  </MultiSelect>
                  <Button type="button" class="bg-gray-200 hover:bg-gray-300 text-gray-800" @click.prevent="loadDevices(true)" :disabled="cb.loading">
                    <ArrowPathIcon class="w-4 h-4" :class="cb.loading ? 'animate-spin' : ''" />
                  </Button>
                </div>
                <p class="mt-1 text-xs text-gray-500" v-if="cb.loaded && !cb.devices.length && !cb.error">Every CityBox device is already linked to a vend.</p>
                <p class="mt-1 text-xs text-red-600" v-if="cb.error">{{ cb.error }}</p>
                <div class="text-sm text-red-600" v-if="form.errors.equipment_id">{{ form.errors.equipment_id }}</div>
              </div>
              <div class="sm:col-span-2">
                <DatePicker v-model="form.begin_date" :error="form.errors.begin_date">Begin Date</DatePicker>
              </div>

              <!-- Preview card -->
              <div class="sm:col-span-6 rounded-md border border-gray-200 p-3 text-sm" v-if="cb.preview">
                <div class="flex flex-wrap gap-x-6 gap-y-1">
                  <span v-if="cb.preview.machine_id"><span class="text-gray-500">Machine ID:</span> <b>{{ cb.preview.machine_id }}</b></span>
                  <span v-else-if="cb.preview.machine_id_error" class="text-red-700">{{ cb.preview.machine_id_error }}</span>
                  <span><span class="text-gray-500">Model:</span> {{ cb.preview.device?.model }}</span>
                  <span><span class="text-gray-500">State:</span> {{ cb.preview.state || '—' }}</span>
                  <span><span class="text-gray-500">Products configured:</span> {{ cb.preview.product_count ?? '—' }}</span>
                  <span v-if="cb.preview.device && !cb.preview.device.online" class="text-amber-700">Offline since {{ cb.preview.device.offline_since }} — stock sync starts when CityBox reports it online.</span>
                </div>
              </div>

              <!-- Customer (site) — required -->
              <div class="sm:col-span-6 border-t pt-3">
                <label class="flex justify-start text-sm font-medium text-gray-700 mb-1">Site (customer) <span class="text-red-500 ml-1">*</span></label>
                <div class="flex flex-col sm:flex-row sm:space-x-6 space-y-1 sm:space-y-0 mb-2">
                  <label class="inline-flex items-center space-x-2 cursor-pointer" v-if="cb.preview && cb.preview.existing_customer">
                    <input type="radio" value="existing" v-model="cb.customerMode" class="text-indigo-600" />
                    <span class="text-sm">Bind to existing <b>{{ cb.preview.existing_customer.name }}</b> ({{ cb.preview.existing_customer.code }}) — same name as the CityBox device</span>
                  </label>
                  <label class="inline-flex items-center space-x-2 cursor-pointer">
                    <input type="radio" value="pick" v-model="cb.customerMode" class="text-indigo-600" />
                    <span class="text-sm">Bind to another existing site</span>
                  </label>
                  <label class="inline-flex items-center space-x-2 cursor-pointer">
                    <input type="radio" value="new" v-model="cb.customerMode" class="text-indigo-600" />
                    <span class="text-sm">Create site from this device</span>
                  </label>
                </div>
                <div v-if="cb.customerMode === 'pick'" class="sm:w-1/2">
                  <input v-model="cb.customerQuery" @input="searchCustomers" type="text" placeholder="Search Citybox-operator sites by name…" class="w-full rounded-md border-gray-300 text-sm" />
                  <select v-if="cb.customerResults.length" v-model="form.customer_id" class="mt-1 w-full rounded-md border-gray-300 text-sm">
                    <option :value="null">— pick —</option>
                    <option v-for="c in cb.customerResults" :key="c.id" :value="c.id">{{ c.name }} ({{ c.code }})</option>
                  </select>
                </div>
                <div v-if="cb.customerMode === 'new'" class="grid grid-cols-1 sm:grid-cols-6 gap-3">
                  <div class="sm:col-span-3">
                    <label class="text-xs text-gray-600">Site name</label>
                    <input v-model="form.new_customer.name" type="text" class="w-full rounded-md border-gray-300 text-sm" />
                    <p class="text-xs text-gray-500 mt-1">Prefilled from the CityBox device name — rename devices in the CityBox portal to real sites first.</p>
                  </div>
                </div>
                <div class="text-sm text-red-600" v-if="form.errors.customer_id">{{ form.errors.customer_id }}</div>
                <div class="text-sm text-red-600" v-if="form.errors['new_customer.name']">{{ form.errors['new_customer.name'] }}</div>
              </div>
            </div>
            <div class="sm:col-span-6">
              <div class="flex flex-col space-y-1 sm:flex-row sm:space-x-1 sm:space-y-0 mt-5 justify-end">
                <Link href="/settings" class="bg-gray-300 hover:bg-gray-400 text-gray-700 rounded">
                  <Button
                   class="space-x-1"
                  >
                    <ArrowUturnLeftIcon class="w-4 h-4"></ArrowUturnLeftIcon>
                    <span>
                      Back
                    </span>
                  </Button>
                </Link>
                <Button
                  type="submit"
                  class="bg-green-500 hover:bg-green-600 text-white flex space-x-1"
                  v-if="permissions.includes('create machine-settings')"
                >
                  <CheckCircleIcon class="w-4 h-4"></CheckCircleIcon>
                  <span>
                    Save
                  </span>
                </Button>
              </div>
            </div>
          </form>
        </div>
      </div>
    </div>
  </div>
  </BreezeAuthenticatedLayout>
</template>

<script setup>
import BreezeAuthenticatedLayout from '@/Layouts/Authenticated.vue';
import Button from '@/Components/Button.vue';
import DatePicker from '@/Components/DatePicker.vue';
import MultiSelect from '@/Components/MultiSelect.vue';
import SearchVendCodeInput from '@/Components/SearchVendCodeInput.vue';
import { ArrowPathIcon, ArrowUturnDownIcon, ArrowUturnLeftIcon, CheckCircleIcon, PauseCircleIcon, PlayIcon } from '@heroicons/vue/20/solid';
import axios from 'axios';
import { computed, reactive, watch } from 'vue';
import { ref, onMounted } from 'vue';
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import moment from 'moment';

const props = defineProps({
    vend: Object,
    type: String,
    machineTypeOptions: Object,
  })

  const MACHINE_TYPE_HINTS = {
    vending_machine: 'Our terminal APK on a VMC board, identified by its Machine ID.',
    smart_freezer: 'Our freezer APK on Zijia hardware, identified by its Machine ID.',
    smart_chiller: 'Pick a device from the CityBox fleet and bind it to a site.',
  }

  const booleanOptions = ref([])
  const form = ref(
    useForm(getDefaultForm())
  )
  const loading = ref(false)
  const typeName = ref('')
  const operatorCountry = usePage().props.auth.operatorCountry
  const operatorRole = usePage().props.auth.operatorRole
  const permissions = usePage().props.auth.permissions
  const now = ref(moment().format('HH:mm:ss'))
  const cityboxEnabled = usePage().props.cityboxEnabled ?? false
  const machineType = ref('vending_machine')
  const isChiller = computed(() => machineType.value === 'smart_chiller')

  // Cards come from Vend::MACHINE_TYPE_MAPPINGS (server labels), so a type is named the
  // same here as on Setting/Edit. The chiller card names its supplier.
  const machineTypeCards = computed(() => Object.entries(props.machineTypeOptions || {}).map(([id, name]) => ({
    id,
    label: id === 'smart_chiller' ? `${name} (CityBox)` : name,
    hint: id === 'smart_chiller' && !cityboxEnabled ? 'CityBox integration is disabled.' : (MACHINE_TYPE_HINTS[id] ?? ''),
    disabled: id === 'smart_chiller' && !cityboxEnabled,
  })))
  const cb = reactive({ devices: [], loaded: false, loading: false, error: null, device: null, equipment_id: null, preview: null,
                        customerMode: 'new', customerQuery: '', customerResults: [], searchTimer: null })

  // MultiSelect searches on `label`, so everything ops might type — serial, CityBox
  // name, model, online state — has to live in that one string.
  const deviceOptions = computed(() => cb.devices.map(d => ({
    ...d,
    id: d.equipment_id,
    label: `${d.equipment_id} · ${d.name} · ${d.type} · ${d.online ? 'online' : ('offline' + (d.offline_since ? ' since ' + d.offline_since : ''))}`,
  })))

onMounted(() => {
    typeName.value = 'Create New'
    form.value = useForm(getDefaultForm())
})

function getDefaultForm() {
  return {
    code: '',
    begin_date: moment().format('YYYY-MM-DD'),
    // Smart Chiller fields (never posted for the other two types)
    equipment_id: null,
    name: null,
    customer_id: null,
    new_customer: { name: '' },
  }
}

watch(machineType, (v) => {
  form.value.clearErrors()
  if (v === 'smart_chiller' && !cb.loaded) loadDevices(false)
})

async function loadDevices(fresh) {
  cb.loading = true; cb.error = null
  try {
    const { data } = await axios.get('/citybox/devices', { params: { fresh: fresh ? 1 : 0 } })
    if (data.error) cb.error = data.error
    cb.devices = data.unlinked || []
    cb.loaded = true
    // A refresh can retire the picked device (someone else linked it) — drop the
    // selection rather than posting a serial CityBox no longer offers.
    if (cb.device && !cb.devices.some(d => d.equipment_id === cb.device.equipment_id)) cb.device = null
  } catch (e) {
    cb.error = 'Could not load CityBox devices.'
  } finally { cb.loading = false }
}

watch(() => cb.device, onDevicePicked)

async function onDevicePicked() {
  cb.equipment_id = cb.device ? cb.device.equipment_id : null
  form.value.equipment_id = cb.equipment_id
  cb.preview = null
  // Reset the site step for the new device: a customer chosen for the previous device must
  // never ride along if this device's preview fails or has no same-name site.
  cb.customerMode = 'new'
  form.value.customer_id = null
  if (!cb.equipment_id) return
  const requested = cb.equipment_id
  const d = cb.devices.find(x => x.equipment_id === requested)
  form.value.new_customer.name = d ? d.name : ''
  try {
    const { data } = await axios.get(`/citybox/devices/${requested}/preview`)
    // A slower response for a device the user has since moved off must not win.
    if (cb.equipment_id !== requested) return
    cb.preview = data
    // Default the customer step to the safest choice: bind to a same-name site if one exists.
    cb.customerMode = data.existing_customer ? 'existing' : 'new'
    if (data.existing_customer) form.value.customer_id = data.existing_customer.id
  } catch (e) { /* preview is best-effort */ }
}

watch(() => cb.customerMode, (m) => {
  if (m === 'existing' && cb.preview?.existing_customer) { form.value.customer_id = cb.preview.existing_customer.id }
  else if (m === 'pick') { form.value.customer_id = null }
  else if (m === 'new') { form.value.customer_id = null }
})

function searchCustomers() {
  clearTimeout(cb.searchTimer)
  cb.searchTimer = setTimeout(async () => {
    if (!cb.customerQuery || cb.customerQuery.length < 2) { cb.customerResults = []; return }
    try {
      const { data } = await axios.get('/citybox/customers/search', { params: { q: cb.customerQuery } })
      cb.customerResults = data
    } catch (e) {
      cb.customerResults = []
    }
  }, 250)
}

function onVendCodeSelected(vend) {
  form.value.code = vend.code
}

function submit() {
  form.value.clearErrors()
  if (props.type !== 'create') return
  if (isChiller.value) {
    form.value
      .transform(data => ({
        equipment_id: data.equipment_id,
        name: data.name,
        begin_date: data.begin_date,
        customer_id: cb.customerMode === 'new' ? null : data.customer_id,
        new_customer: cb.customerMode === 'new' ? data.new_customer : null,
      }))
      .post('/citybox/vends', { preserveState: true })
    return
  }
  // Only this type's own fields: leftovers from the chiller branch (customer_id, a device)
  // must never reach the vend create.
  form.value
    .transform(data => ({
      code: data.code,
      machine_type: machineType.value,
      begin_date: data.begin_date,
    }))
    .post('/settings/vend/store', {
      preserveState: true,
      replace: true,
    })
}
</script>