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
       <div class="-my-2 -mx-4 sm:-mx-6 lg:-mx-8">
        <div class="shadow-sm ring-1 ring-black ring-opacity-5 overflow-scroll p-5">
          <form @submit.prevent="submit" id="submit">
            <!-- Machine source fork (design §8c.2): a radio, because the two branches ask different questions. -->
            <div class="grid grid-cols-1 gap-3 sm:grid-cols-6 pb-2" v-if="type == 'create'">
              <div class="sm:col-span-6">
                <label class="flex justify-start text-sm font-medium text-gray-700 mb-1">Machine source</label>
                <div class="flex flex-col sm:flex-row sm:space-x-6 space-y-1 sm:space-y-0">
                  <label class="inline-flex items-center space-x-2 cursor-pointer">
                    <input type="radio" value="standard" v-model="source" class="text-indigo-600" />
                    <span class="text-sm">Vending machine / Smart freezer</span>
                  </label>
                  <label class="inline-flex items-center space-x-2 cursor-pointer" :class="cityboxEnabled ? '' : 'opacity-50'">
                    <input type="radio" value="citybox" v-model="source" :disabled="!cityboxEnabled" class="text-indigo-600" />
                    <span class="text-sm">Smart Chiller — CityBox <span v-if="!cityboxEnabled" class="text-xs text-gray-400">(integration disabled)</span></span>
                  </label>
                </div>
              </div>
            </div>

            <!-- Standard branch: today's form, unchanged -->
            <div class="grid grid-cols-1 gap-3 sm:grid-cols-6 pb-2" v-if="source === 'standard'">
              <div class="sm:col-span-6">
                <SearchVendCodeInput v-model="form.code" @selected="onVendCodeSelected" required="true" :error="form.errors.code" v-if="type == 'create'">
                  Machine ID
                </SearchVendCodeInput>
              </div>
              <!-- Machine Type is chosen ONCE, here at creation. Setting/Edit shows it read-only
                   (it drives which product mappings the machine may bind, and a chiller's
                   CityBox link travels with it). Smart Chiller is not offered on this branch —
                   it is the "Smart Chiller — CityBox" source above. -->
              <div class="sm:col-span-6">
                <label for="text" class="flex justify-start text-sm font-medium text-gray-700">
                  Machine Type
                  <span class="text-red-500 ml-1">*</span>
                </label>
                <MultiSelect
                  v-model="form.machine_type"
                  :options="standardMachineTypeOptions"
                  trackBy="id"
                  valueProp="id"
                  label="value"
                  placeholder="Select"
                  open-direction="bottom"
                  class="mt-1 sm:w-1/2"
                  :canClear="false"
                >
                </MultiSelect>
                <p class="mt-1 text-xs text-gray-500">
                  Vending Machine / Smart Freezer — fixed after creation; it decides which product mappings the machine can use.
                </p>
                <div class="text-sm text-red-600" v-if="form.errors.machine_type">
                  {{ form.errors.machine_type }}
                </div>
              </div>
              <div class="sm:col-span-3">
                <DatePicker v-model="form.begin_date" :error="form.errors.begin_date" @input="onDateFromChanged()"
                v-if="permissions.includes('update machine-settings')">
                  Begin Date
                </DatePicker>
              </div>
            </div>

            <!-- CityBox branch -->
            <div class="grid grid-cols-1 gap-3 sm:grid-cols-6 pb-2" v-else>
              <div class="sm:col-span-6 rounded-md bg-indigo-50 p-3 text-xs text-indigo-800">
                Pick one of the CityBox devices not yet in mark1. Identity, model, online status and the CityBox name are filled automatically;
                the vend code is allocated under the <b>Citybox</b> operator. You must bind it to a site (customer) — the CityBox device name is offered as the site name.
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

  // {vending_machine: 'Vending Machine', ...} → MultiSelect rows, minus Smart Chiller
  // (created through the CityBox source branch, never picked by hand).
  const standardMachineTypeOptions = Object.entries(props.machineTypeOptions || {})
    .filter(([id]) => id !== 'smart_chiller')
    .map(([id, name]) => ({ id, value: name }))

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
  const source = ref('standard')
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
    machine_type: standardMachineTypeOptions.find(o => o.id === 'vending_machine') || standardMachineTypeOptions[0] || null,
    begin_date: moment().format('YYYY-MM-DD'),
    // CityBox branch fields (ignored by the standard branch)
    equipment_id: null,
    name: null,
    customer_id: null,
    new_customer: { name: '' },
  }
}

watch(source, (v) => { if (v === 'citybox' && !cb.loaded) loadDevices(false) })

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
  if (!cb.equipment_id) return
  const d = cb.devices.find(x => x.equipment_id === cb.equipment_id)
  form.value.new_customer.name = d ? d.name : ''
  try {
    const { data } = await axios.get(`/citybox/devices/${cb.equipment_id}/preview`)
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
    const { data } = await axios.get('/citybox/customers/search', { params: { q: cb.customerQuery } })
    cb.customerResults = data
  }, 250)
}

function onVendCodeSelected(vend) {
  form.value.code = vend.code
}

function submit() {
  form.value.clearErrors()
  if (props.type === 'create' && source.value === 'citybox') {
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
  if(props.type === 'create') {
    form.value
    .transform(data => ({
      ...data,
      // Server expects the id; the picker holds {id, value}. An empty pick falls back
      // to Vending Machine rather than posting null.
      machine_type: data.machine_type ? data.machine_type.id : 'vending_machine',
    }))
    .post('/settings/vend/store', {
      preserveState: true,
      replace: true,
    })
  }
}
</script>