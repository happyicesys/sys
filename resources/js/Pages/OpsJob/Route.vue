<template>
  <Head title="VM Edit" />
  <BreezeAuthenticatedLayout>
    <template #header>
      <div class="flex flex-col md:flex-row space-x-2">
        <span class="text-gray-600" v-if="opsJob && opsJob.id">
          Route Planning
        </span>
      </div>
    </template>

    <div class="m-2 sm:mx-5 sm:my-3 px-1 sm:px-2 lg:px-3">
      <div class="mt-6 flex flex-col">
        <div class="-my-2 -mx-3 sm:-mx-6 lg:-mx-8">
          <div class="shadow-sm ring-1 ring-black ring-opacity-5 overflow-scroll p-5">
            <form @submit.prevent="submit" id="submit">
              <div class="grid grid-cols-1 gap-y-3 gap-x-3 sm:grid-cols-6">

                <!-- Date and Delivery Info -->
                <div class="sm:col-span-3">
                  <label for="text" class="flex justify-start text-sm font-medium text-gray-700"> Date </label>
                  <div class="mt-1">
                    <input
                      type="text"
                      class="shadow-sm focus:ring-indigo-500 focus:border-indigo-500 block w-full text-sm border-gray-300 rounded-md bg-gray-200 hover:cursor-not-allowed"
                      :value="opsJob ? opsJob.date_formatted : ''"
                      disabled
                    />
                  </div>
                </div>
                <div class="sm:col-span-3">
                  <label for="text" class="flex justify-start text-sm font-medium text-gray-700"> Delivery By </label>
                  <div class="mt-1">
                    <input
                      type="text"
                      class="shadow-sm focus:ring-indigo-500 focus:border-indigo-500 block w-full text-sm border-gray-300 rounded-md bg-gray-200 hover:cursor-not-allowed"
                      :value="opsJob && opsJob.deliveredBy ? opsJob.deliveredBy.name : ''"
                      disabled
                    />
                  </div>
                </div>

                <!-- Total Job(s) Info -->
                <div class="sm:col-span-5">
                  <label for="text" class="flex justify-start text-sm font-medium text-gray-700"> Total Job(s) </label>
                  <div class="mt-1">
                    <input
                      type="text"
                      class="shadow-sm focus:ring-indigo-500 focus:border-indigo-500 block w-full text-sm border-gray-300 rounded-md bg-gray-200 hover:cursor-not-allowed"
                      :value="opsJob.opsJobItems ? opsJob.opsJobItems.filter((opsJobItem) => { return !opsJobItem.is_ops_job_item }).length : 0"
                      disabled
                    />
                  </div>
                </div>

                <!-- Map Section -->
                <div id="map" class="sm:col-span-6 mb-3" style="width: 100%; height: 600px;"></div>

                <div class="sm:col-span-6" v-if="totalDistance">
                  Total travel: {{ totalDistance }} km
                </div>

                <!-- Origin Select -->
                <div class="sm:col-span-6">
                  <label for="text" class="flex justify-start text-sm font-medium text-gray-700">
                    Origin
                  </label>
                  <MultiSelect
                    v-model="form.origin_address_id"
                    :options="originAddressOptions"
                    trackBy="id"
                    valueProp="id"
                    label="full_address"
                    placeholder="Select"
                    open-direction="bottom"
                    class="mt-1"
                    ref="multiselect"
                  />
                </div>

                <!-- Return to HQ (destination) -->
                <div class="sm:col-span-6" v-if="destinationAddress">
                  <label class="inline-flex items-center space-x-2 text-sm font-medium text-gray-700 cursor-pointer">
                    <input
                      type="checkbox"
                      v-model="form.return_to_destination"
                      class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500"
                    />
                    <span>Return to {{ destinationAddress.name }} (last stop)</span>
                  </label>
                  <p class="text-xs text-gray-500 mt-1">
                    When checked, the generated route ends at {{ destinationAddress.full_address }}.
                  </p>
                </div>

                <!-- Buttons to Set Origin and Regenerate Route -->
                <div class="sm:col-span-6 flex justify-between mt-4">
                  <a :href="'/ops-jobs/' + opsJob.id + '/edit'">
                    <Button
                      type="button" class="bg-gray-300 hover:bg-gray-400 text-gray-700 flex space-x-1 "
                    >
                      <ArrowUturnLeftIcon class="w-4 h-4"></ArrowUturnLeftIcon>
                      <span>
                        Back
                      </span>
                    </Button>
                  </a>
                  <div class="flex space-x-1">
                    <button
                      type="button"
                      @click="setOriginDestination(1)"
                      class="bg-green-500 hover:bg-green-600 text-white font-medium py-2 px-4 rounded text-sm"
                    >
                      <div class="flex space-x-1 items-center">
                        <ArrowRightCircleIcon class="h-4 w-4" />
                        <span>
                          Generate Route (Google API)
                        </span>
                      </div>
                    </button>
                    <button
                      type="button"
                      @click="setOriginDestination(2)"
                      class="bg-green-500 hover:bg-green-600 text-white font-medium py-2 px-4 rounded text-sm"
                    >
                      <div class="flex space-x-1 items-center">
                        <ArrowRightCircleIcon class="h-4 w-4" />
                        <span>
                          Generate Route (Nearest Distance Algo)
                        </span>
                      </div>
                    </button>
                    <button
                      type="button"
                      @click="showClaudePanel = !showClaudePanel"
                      v-if="opsJob.opsJobItems && opsJob.opsJobItems.length && opsJob.opsJobItems.some(item => item.status < 3) && permissions.includes('admin-access operations')"
                      class="bg-indigo-500 hover:bg-indigo-600 text-white font-medium py-2 px-4 rounded text-sm"
                    >
                      <div class="flex space-x-1 items-center">
                        <ArrowRightCircleIcon class="h-4 w-4" />
                        <span>
                          Generate Route (Claude JSON)
                        </span>
                      </div>
                    </button>
                    <button
                      type="button"
                      @click.prevent="applySequenceJobs"
                      class="bg-yellow-500 hover:bg-yellow-600 text-gray-800 font-medium py-2 px-4 rounded text-sm"
                      v-if="isSequenceGenerated"
                    >
                      <div class="flex space-x-1 items-center">
                        <BarsArrowDownIcon class="h-4 w-4" />
                        <span>
                          Sync Generated Sequence to Current
                        </span>
                      </div>
                    </button>
                  </div>
                </div>

                <!-- Claude JSON route: plan externally (Claude desktop + sys-happyice MCP), paste the JSON, apply.
                     Applying posts the ordered stop list to /renumber, which always overwrites the current sequence. -->
                <div class="sm:col-span-6 border border-indigo-200 rounded-md p-4 bg-indigo-50" v-if="showClaudePanel">
                  <div class="flex flex-col sm:flex-row sm:justify-between sm:items-center">
                    <label class="text-sm font-medium text-gray-700"> Claude route JSON </label>
                    <button
                      type="button"
                      @click="copyClaudePrompt"
                      class="text-xs text-indigo-700 font-medium underline text-left"
                    >
                      Copy planning prompt for Claude (includes this job's stops)
                    </button>
                  </div>
                  <p class="text-xs text-gray-500 mt-1">
                    Plan the route in Claude with the sys-happyice MCP, then paste the JSON it returns here.
                    Applying <span class="font-semibold">always overwrites</span> the current sequence.
                    Stops missing from the JSON are appended at the end in their current order.
                  </p>
                  <textarea
                    v-model="claudeJsonText"
                    rows="6"
                    class="mt-2 block w-full text-sm font-mono border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500"
                    :placeholder="'{&quot;ops_job_id&quot;: ' + opsJob.id + ', &quot;stops&quot;: [{&quot;type&quot;: &quot;item&quot;, &quot;id&quot;: 123}, {&quot;type&quot;: &quot;task&quot;, &quot;id&quot;: 456}]}'"
                  ></textarea>
                  <div class="flex justify-end mt-2">
                    <button
                      type="button"
                      @click="applyClaudeJson"
                      class="bg-indigo-500 hover:bg-indigo-600 text-white font-medium py-2 px-4 rounded text-sm"
                    >
                      <div class="flex space-x-1 items-center">
                        <BarsArrowDownIcon class="h-4 w-4" />
                        <span>
                          Generate &amp; Overwrite Current Sequence
                        </span>
                      </div>
                    </button>
                  </div>
                </div>

                <!-- Generated Sequence Header -->
                <div class="sm:col-span-6 pt-2 pb-1 md:pt-5 md:pb-3">
                  <div class="relative">
                    <div class="absolute inset-0 flex items-center" aria-hidden="true">
                      <div class="w-full border-t border-gray-300"></div>
                    </div>
                    <div class="relative flex justify-center">
                      <span class="px-3 bg-white text-lg font-medium text-gray-900 rounded"> Generated Sequence </span>
                    </div>
                  </div>
                </div>

                <!-- Job Sequence Table -->
                <div class="sm:col-span-6 flex flex-col">
                  <div class="-my-2 -mx-4 overflow-x-auto sm:-mx-3 lg:-mx-5">
                    <div class="inline-block min-w-full py-2 align-middle md:px-4 lg:px-6">
                      <div class="overflow-scroll max-h-[600px] md:max-h-[800px] shadow ring-1 ring-black ring-opacity-5 md:rounded-lg">
                        <table class="min-w-full divide-y divide-gray-300">
                          <thead class="bg-gray-50">
                            <tr>
                              <TableHead>
                                <div class="flex flex-col space-x-1">
                                  <SingleSortItem modelName="sequence" :sortKey="filters.sortKey" :sortBy="filters.sortBy" @sort-table="sortTable('sequence')">
                                    Current Sequence
                                  </SingleSortItem>
                                  <Button
                                    class="bg-yellow-300 hover:bg-yellow-400 text-gray-800 text-xs font-medium"
                                    @click.prevent="onRenumberItemsClicked"
                                    v-if="opsJob.opsJobItems && opsJob.opsJobItems.length && opsJob.opsJobItems.some(item => item.status < 3) && permissions.includes('admin-access operations')"
                                  >
                                    <div class="flex space-x-1 items-center">
                                      <BarsArrowDownIcon class="h-3 w-3"></BarsArrowDownIcon>
                                      <span>
                                        Renumber
                                      </span>
                                    </div>
                                  </Button>
                                </div>
                              </TableHead>
                              <TableHead> Generated Sequence </TableHead>
                              <TableHead> Machine ID & Job ID# </TableHead>
                              <TableHead> Site & Ops Note </TableHead>
                              <TableHeadSort modelName="delivery_postcode" :sortKey="filters.sortKey" :sortBy="filters.sortBy" @sort-table="sortTable('delivery_postcode')">
                                Postcode
                              </TableHeadSort>
                              <TableHead> Address </TableHead>
                            </tr>
                          </thead>
                          <tbody class="bg-white">
                            <tr v-for="(opsJobItem, opsJobItemIndex) in opsJob.opsJobItems" :key="opsJobItem.id ?? (opsJobItem.isOrigin ? 'origin' : 'destination')" :class="opsJobItemIndex % 2 === 0 ? undefined : 'bg-gray-100'">
                              <td class="whitespace-nowrap py-2 pl-4 pr-3 text-sm font-medium text-gray-900 sm:pl-6 text-center">
                                <div class="flex items-center justify-center">
                                  <input
                                    type="text"
                                    class="shadow-sm focus:ring-indigo-500 focus:border-indigo-500 block w-fit text-sm border-gray-300 rounded-md max-w-14 text-center"
                                    v-model="opsJobItem.sequence"
                                    :disabled="opsJobItem.status >= 3"
                                    @input="updateSequence(opsJobItem)"
                                  />
                                </div>
                              </td>
                              <td class="whitespace-nowrap py-2 pl-4 pr-3 text-sm font-medium text-gray-900 sm:pl-6 text-center">
                                {{ opsJobItem.generated_sequence }}
                              </td>
                              <td class="whitespace-pre-line py-2 pl-4 pr-3 text-sm font-semibold text-gray-900 sm:pl-6 text-center">
                                <div class="flex flex-col space-y-2 max-w-24">
                                  <Link :href="'/vends/customers?codes=' + opsJobItem.vend?.code" class="text-blue-700">
                                    <span> {{ opsJobItem.vend?.code }} </span>
                                  </Link>
                                  <div>
                                    <Link :href="'/ops-jobs/items/' + opsJobItem.id + '/edit'">
                                      <Button
                                        class="bg-indigo-400 hover:bg-indigo-500 text-white text-xs font-medium"
                                        v-if="permissions.includes('update operations') && opsJobItem.ref_id"
                                      >
                                        {{ opsJobItem.ref_id }}
                                      </Button>
                                    </Link>
                                  </div>
                                  <div class="text-left text-red-800"> {{ opsJobItem.remarks }} </div>
                                </div>
                              </td>
                              <td class="whitespace py-2 pl-4 pr-3 text-sm font-semibold text-gray-900 sm:pl-6 text-left">
                                <div class="flex flex-col space-y-1 max-w-40 md:max-w-72">
                                  <span>
                                    <span v-if="opsJobItem.customer && opsJobItem.customer.person_id">
                                      {{ opsJobItem.customer.id + 20000 }}
                                      ({{ opsJobItem.vend && opsJobItem.vend.vendPrefix ? opsJobItem.vend.vendPrefix.name : '' }})
                                      - {{ opsJobItem.customer.name }}
                                    </span>
                                    <span v-else>
                                      <span v-if="opsJobItem.customer && opsJobItem.customer.code"> {{ opsJobItem.customer.code }} <br> </span>
                                      {{ opsJobItem.customer && opsJobItem.customer.name ? opsJobItem.customer.name : ''}}
                                    </span>
                                  </span>
                                  <span v-if="opsJobItem.customer && opsJobItem.customer.deliveryAddress">
                                    <div class="flex space-x-2 items-center font-medium text-xs">
                                      <span class="flex space-x-1 items-center">
                                        <a
                                          :href="opsJobItem.customer.deliveryAddress.map_url ||
                                            ('https://www.google.com/maps/search/?api=1&query='
                                              + opsJobItem.customer.deliveryAddress.latitude + ',' + opsJobItem.customer.deliveryAddress.longitude)"
                                          target="_blank"
                                          rel="noopener noreferrer"
                                          class="bg-green-300 hover:bg-green-400 px-3 py-2 text-xs text-green-800 flex space-x-1 w-fit rounded shadow font-bold"
                                        >
                                          GPS
                                        </a>
                                      </span>
                                    </div>
                                  </span>
                                  <span class="text-left font-medium bg-gray-200 py-1 px-1 rounded" v-if="opsJobItem.customer && opsJobItem.customer.ops_note">
                                    {{ opsJobItem.customer.ops_note }}
                                  </span>
                                </div>
                              </td>
                              <td class="whitespace-pre-line py-2 px-1 text-sm text-center">
                                {{ opsJobItem.delivery_postcode }}
                              </td>
                              <td class="whitespace-pre-line py-2 px-1 text-sm text-center">
                                <div class="flex flex-col space-y-2 break-words max-w-32 md:max-w-72" v-if="opsJobItem.customer && opsJobItem.customer.deliveryAddress">
                                  <span>
                                    <a :href="opsJobItem.customer.deliveryAddress.map_url" class="text-blue-700" target="_blank"> {{ opsJobItem.customer.deliveryAddress.full_address }} </a>
                                  </span>
                                  <span v-if="!opsJobItem.customer.deliveryAddress.full_address">
                                    {{ opsJobItem.customer.deliveryAddress.postcode }}
                                  </span>
                                </div>
                              </td>
                            </tr>

                            <!-- Fallback for no records -->
                            <tr v-if="!opsJob.opsJobItems || !opsJob.opsJobItems.length">
                              <td colspan="11" class="whitespace-nowrap py-2 text-sm font-medium text-black text-center">
                                No Records Found
                              </td>
                            </tr>
                          </tbody>
                        </table>
                      </div>
                    </div>
                  </div>
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
import MultiSelect from '@/Components/MultiSelect.vue';
import SingleSortItem from '@/Components/SingleSortItem.vue';
import TableHead from '@/Components/TableHead.vue'; // Retained TableHead component
import TableHeadSort from '@/Components/TableHeadSort.vue';
import { ArrowUturnLeftIcon, ArrowRightCircleIcon, BarsArrowDownIcon } from '@heroicons/vue/20/solid';
import { ref, computed, onMounted } from 'vue';
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import { useToast } from "vue-toastification";

const filters = ref({
  sortKey: '',
  sortBy: true,
});

const props = defineProps({
  originAddresses: [Array, Object],
  destinationAddresses: [Array, Object],
  mapApiKey: String,
  opsJob: Object,
});

const emit = defineEmits(['modalClose']);

const originAddressOptions = ref([]);
// Return-to-HQ destination: the first type-100 address (Happy Ice Pte Ltd). Null when none
// is configured, in which case the checkbox is hidden and routes end at the last stop.
const destinationAddress = computed(() => {
  const first = Array.isArray(props.destinationAddresses?.data) ? props.destinationAddresses.data[0] : null;
  if (!first || !first.latitude || !first.longitude) return null;
  return {
    id: first.id,
    name: first.name || 'Happy Ice Pte Ltd',
    full_address: first.full_address,
    latitude: first.latitude,
    longitude: first.longitude,
  };
});
const form = ref(useForm(getDefaultForm()));
const isSequenceGenerated = ref(false);
const showClaudePanel = ref(false);
const claudeJsonText = ref('');
const opsJob = ref(props.opsJob?.data || []);
const permissions = usePage().props.auth.permissions;
const toast = useToast();
const totalDistance = ref(0);
let map, directionsService;
let defaultPos = { lat: 1.3521, lng: 103.8198 };
let markers = []; // Array to store map markers
let renderers = []; // Array to store all DirectionsRenderer instances

onMounted(() => {
  // Merge tasks into opsJobItems as synthetic entries so all existing
  // map / routing functions work without modification.
  // Tasks carry their own lat/lng (geocoded at creation) and we build
  // a compatible customer.deliveryAddress structure.
  if (Array.isArray(props.opsJob.data?.opsJobTasks)) {
    const taskItems = props.opsJob.data.opsJobTasks
      .filter(task => task.latitude && task.longitude)
      .map(task => ({
        id: task.id,
        _isTask: true,
        sequence: task.sequence,
        delivery_postcode: task.postcode,
        remarks: null,
        status: 1, // treat as pending so renumber / routing picks them up
        vend: { code: '[task] ' + task.task_name },
        customer: {
          id: null,
          name: task.task_name,
          ops_note: task.ops_note || null,
          deliveryAddress: {
            id: 'task_' + task.id,
            latitude: task.latitude,
            longitude: task.longitude,
            full_address: task.address,
            postcode: task.postcode,
            map_url: null,
          },
        },
      }))
    opsJob.value.opsJobItems = [
      ...(opsJob.value.opsJobItems || []),
      ...taskItems,
    ]
  }

  originAddressOptions.value = [
    ...(Array.isArray(props.originAddresses?.data) ? props.originAddresses.data.map(address => ({
      id: address.id,
      name: address.name,
      full_address: (address.name ? '(' + address.name + ') ' : '') + address.full_address,
      latitude: address.latitude,
      longitude: address.longitude,
      is_ops_job_item: false,
    })) : []),
    ...(Array.isArray(props.opsJob.data?.opsJobItems) ? props.opsJob.data.opsJobItems
      .filter(jobItem => jobItem.customer?.deliveryAddress?.latitude && jobItem.customer?.deliveryAddress?.longitude)
      .map(jobItem => ({
        id: jobItem.customer.deliveryAddress.id,
        name: jobItem.vend?.code || jobItem.customer.name,
        full_address: (jobItem.vend?.code ? '(' + jobItem.vend.code + ' - ' + jobItem.customer.name + ') ' : '(' + jobItem.customer.name + ') ') + jobItem.customer.deliveryAddress.full_address,
        latitude: jobItem.customer.deliveryAddress.latitude,
        longitude: jobItem.customer.deliveryAddress.longitude,
        is_ops_job_item: true,
    })) : []),
  ];

  // Initialize Google Maps
  loadGoogleMapsApi();
});

function applySequenceJobs() {
  const approval = confirm('Are you sure to sync and overwrite the current sequence?');
  if (!approval) {
      return;
  }

  // Create a clean serializable version of opsJobItems
const cleanOpsJobItems = opsJob.value.opsJobItems.map(item => {
  return {
    id: item.id,
    sequence: item.sequence,
    generated_sequence: item.generated_sequence,
    customer: {
      id: item.customer.id, // Only send necessary properties
      name: item.customer.name,
      deliveryAddress: {
        id: item.customer.deliveryAddress.id,
        latitude: item.customer.deliveryAddress.latitude,
        longitude: item.customer.deliveryAddress.longitude,
        full_address: item.customer.deliveryAddress.full_address
      }
    },
    vend: item.vend ? { code: item.vend.code } : null, // Only send vend code if available
    isOrigin: item.isOrigin,
    isDestination: item.isDestination,
    isOpsJobItem: item.isOpsJobItem
  };
});

// Build a mergedOrder from generated_sequence for tasks and items combined.
// Filter by id, not isOrigin: when the origin is itself an ops job item (the default
// when no origin is selected) it carries generated_sequence 1 and must be synced too,
// otherwise its sequence is never written and the list starts at 2. A warehouse origin
// is a synthetic entry with no id and is skipped.
const mergedOrder = opsJob.value.opsJobItems
  .filter(item => item.id != null && item.generated_sequence != null)
  .sort((a, b) => a.generated_sequence - b.generated_sequence)
  .map(item => ({
    type: item._isTask ? 'task' : 'item',
    id: item.id,
    generated_sequence: item.generated_sequence,
  }))

axios.post('/ops-jobs/' + opsJob.value.id + '/sequence', {
  opsJobItems: cleanOpsJobItems, // kept for backward compat with saveSequence()
  mergedOrder,
})
.then(response => {
  location.reload()
})
.catch(error => {
  console.error(error);
});

}

// ---------------------------------------------------------------------------
// Claude JSON route: the JSON is planned outside mark1 (Claude desktop reading
// the DB through the read-only sys-happyice MCP) and pasted here. Applying it
// posts a mergedOrder to /renumber, which rewrites sequence 1..N — the same
// always-overwrite semantics as the Renumber button.
// ---------------------------------------------------------------------------

// Real stops of this job: items plus the tasks merged in onMounted. Filter by
// id, not isOrigin/isDestination: a REAL job item is flagged isOrigin when a
// route is generated with it as origin (the applySequenceJobs comment documents
// this exact trap) and must still be sequenced; only the synthetic warehouse
// origin/destination markers (no id) are excluded.
//
// setOriginDestination resets opsJobItems from props, which silently drops the
// tasks merged in onMounted — re-add any task missing from the array (same
// shape and lat/lng filter as the onMounted merge) so the Claude plan and the
// append-missing pass always cover the whole job.
function getClaudeJobStops() {
  const stops = (opsJob.value.opsJobItems || []).filter(stop => stop.id != null);
  if (Array.isArray(props.opsJob.data?.opsJobTasks)) {
    const presentTaskIds = new Set(stops.filter(s => s._isTask).map(s => Number(s.id)));
    props.opsJob.data.opsJobTasks
      .filter(task => task.latitude && task.longitude && !presentTaskIds.has(Number(task.id)))
      .forEach(task => stops.push({
        id: task.id,
        _isTask: true,
        sequence: task.sequence,
        delivery_postcode: task.postcode,
        vend: { code: '[task] ' + task.task_name },
        customer: {
          name: task.task_name,
          deliveryAddress: { latitude: task.latitude, longitude: task.longitude },
        },
      }));
  }
  return stops;
}

function buildClaudePrompt() {
  const stopLines = getClaudeJobStops().map(stop => {
    const type = stop._isTask ? 'task' : 'item';
    const address = stop.customer?.deliveryAddress;
    return '- ' + type + ' id=' + stop.id
      + ' | ' + (stop.vend?.code ?? '')
      + ' | ' + (stop.customer?.name ?? '')
      + ' | postcode ' + (stop.delivery_postcode ?? address?.postcode ?? '?')
      + ' | lat ' + (address?.latitude ?? '?')
      + ' lng ' + (address?.longitude ?? '?');
  });

  return 'Plan the driver route for ConnectVend ops job ' + opsJob.value.id
    + ' (' + (opsJob.value.date_formatted ?? '') + ', driver: ' + (opsJob.value.deliveredBy?.name ?? 'unassigned') + ').\n\n'
    + 'Stops to order:\n' + stopLines.join('\n') + '\n\n'
    + 'Use the read-only sys-happyice MCP where helpful: past visiting order for these sites '
    + '(ops_job_items.sequence on earlier ops_jobs for the same customer_id/driver), site timing patterns, '
    + 'and anything else in the DB. Order the stops to minimise travel and respect timing patterns.\n\n'
    + 'If the ConnectVend MCP offers the apply_route_sequence tool, apply the route directly with it '
    + '(dry_run first, then apply after I confirm). Otherwise reply with ONLY this JSON '
    + '(stops in visiting order, include every stop listed above) for me to paste into the route page:\n'
    + '{"ops_job_id": ' + opsJob.value.id + ', "stops": [{"type": "item", "id": 123}, {"type": "task", "id": 456}]}';
}

function copyClaudePrompt() {
  navigator.clipboard.writeText(buildClaudePrompt()).then(() => {
    toast.success('Planning prompt copied — paste it into Claude', { timeout: 3000 });
  }).catch(() => {
    toast.error('Could not copy to clipboard');
  });
}

function applyClaudeJson() {
  let parsed;
  try {
    parsed = JSON.parse(claudeJsonText.value);
  } catch (e) {
    toast.error('Invalid JSON: ' + e.message);
    return;
  }

  // Accept a bare array or an object holding the array under stops/sequence/route.
  const rawStops = Array.isArray(parsed) ? parsed : (parsed.stops ?? parsed.sequence ?? parsed.route);
  if (!Array.isArray(rawStops) || !rawStops.length) {
    toast.error('JSON must contain a non-empty "stops" array');
    return;
  }
  // Guard against pasting another job's plan.
  if (parsed.ops_job_id != null && Number(parsed.ops_job_id) !== Number(opsJob.value.id)) {
    toast.error('This JSON is for ops job ' + parsed.ops_job_id + ', not ' + opsJob.value.id);
    return;
  }

  const jobStops = getClaudeJobStops();
  const stopKey = stop => (stop._isTask ? 'task' : 'item') + ':' + stop.id;
  const byKey = new Map(jobStops.map(stop => [stopKey(stop), stop]));
  const byCode = new Map(jobStops.filter(stop => !stop._isTask && stop.vend?.code).map(stop => [String(stop.vend.code), stop]));

  const seen = new Set();
  const mergedOrder = [];
  const unmatched = [];
  rawStops.forEach(raw => {
    const entry = (raw !== null && typeof raw === 'object') ? raw : { id: raw };
    // Item and task ids overlap, so a mis-cased/unknown type must NOT silently
    // fall back to 'item' — it could match a different stop. Reject it instead.
    const type = String(entry.type ?? 'item').toLowerCase();
    if (type !== 'item' && type !== 'task') {
      unmatched.push(JSON.stringify(entry));
      return;
    }
    let stop = entry.id != null ? byKey.get(type + ':' + Number(entry.id)) : null;
    if (!stop && (entry.vend_code ?? entry.code) != null) {
      stop = byCode.get(String(entry.vend_code ?? entry.code));
    }
    if (!stop) {
      unmatched.push(JSON.stringify(entry));
      return;
    }
    if (seen.has(stopKey(stop))) return; // duplicate in the JSON — first occurrence wins
    seen.add(stopKey(stop));
    mergedOrder.push({ type: stop._isTask ? 'task' : 'item', id: stop.id });
  });

  if (!mergedOrder.length) {
    toast.error('No stops in the JSON match this ops job');
    return;
  }

  // Every real stop gets a fresh sequence: stops missing from the JSON are
  // appended in their current order, so the overwrite never leaves stale numbers.
  const missing = jobStops.filter(stop => !seen.has(stopKey(stop)));
  missing.forEach(stop => mergedOrder.push({ type: stop._isTask ? 'task' : 'item', id: stop.id }));

  const lines = ['Overwrite the current sequence with ' + mergedOrder.length + ' stops from the Claude JSON?'];
  if (missing.length) {
    lines.push(missing.length + ' stop(s) not in the JSON will be appended at the end.');
  }
  if (unmatched.length) {
    lines.push(unmatched.length + ' JSON entries do not match this job and will be ignored: '
      + unmatched.slice(0, 5).join(', ') + (unmatched.length > 5 ? ', …' : ''));
  }
  if (!confirm(lines.join('\n'))) {
    return;
  }

  axios.post('/ops-jobs/' + opsJob.value.id + '/renumber', { mergedOrder })
    .then(() => {
      toast.success('Route applied from Claude JSON', { timeout: 3000 });
      location.reload();
    })
    .catch(error => {
      console.error(error);
      toast.error('Failed to apply the route');
    });
}

// Function to clear the existing route and markers
function clearMarkers() {
  markers.forEach(marker => marker.setMap(null)); // Clear markers
  markers = []; // Reset marker array
}

function clearRoute() {
  renderers.forEach(renderer => renderer.setMap(null)); // Clear all existing routes
  renderers = []; // Reset the renderers array
}

function getDefaultForm() {
  return {
    origin_address_id: '',
    return_to_destination: false,
  };
}

function onRenumberItemsClicked() {
  // Build the unified ordered list (items + tasks) in current display order.
  // Tasks were merged in as synthetic entries with _isTask flag.
  const mergedOrder = opsJob.value.opsJobItems
    .filter(item => !item.isOrigin && !item.isDestination) // exclude temporary origin / destination markers
    .map(item => ({
      type: item._isTask ? 'task' : 'item',
      id: item.id,
    }))

  axios({
      method: 'POST',
      url: '/ops-jobs/' + opsJob.value.id + '/renumber',
      data: { mergedOrder },
  }).then(response => {
      toast.success("Successfully Renumbered", {
        timeout: 3000
      });
      location.reload()
  }).catch(error => {
  }).finally(() => {
  })

}

function onSearchFilterUpdated() {
  const cleanFilters = JSON.parse(JSON.stringify(filters.value)); // Ensure serializable data

  router.reload({
    only: ['opsJob'],
    data: {
      ...cleanFilters, // Use cleaned version of filters
    },
    replace: true,
    preserveState: false,
    preserveScroll: false,
    onSuccess: page => {
      opsJob.value = props.opsJob ? props.opsJob.data : null;
    },
  });
}


function sortTable(sortKey) {
  filters.value.sortKey = sortKey
  filters.value.sortBy = !filters.value.sortBy
  onSearchFilterUpdated()
}

function updateSequence(opsJobItem) {
  form.value.clearErrors();
  form.value
    .transform((data) => ({
      ...data,
      sequence: opsJobItem.sequence,
    }))
    .post('/ops-jobs/items/' + opsJobItem.id + '/update', {
    onSuccess: () => {},
    preserveScroll: true,
    preserveState: true,
    replace: true,
  });
}

// Google Maps API loading
function loadGoogleMapsApi() {
  // Load Google Maps API dynamically
  (g => {
    var h, a, k, p = "The Google Maps JavaScript API", c = "google", l = "importLibrary", q = "__ib__", m = document, b = window;
    b = b[c] || (b[c] = {});
    var d = b.maps || (b.maps = {}), r = new Set, e = new URLSearchParams, u = () => h || (h = new Promise(async (f, n) => {
      await (a = m.createElement("script"));
      e.set("libraries", [...r] + "");
      for (k in g) e.set(k.replace(/[A-Z]/g, t => "_" + t[0].toLowerCase()), g[k]);
      e.set("callback", c + ".maps." + q);
      a.src = `https://maps.${c}apis.com/maps/api/js?` + e;
      d[q] = f;
      a.onerror = () => h = n(Error(p + " could not load."));
      a.nonce = m.querySelector("script[nonce]")?.nonce || "";
      m.head.append(a);
    }));
    d[l] ? console.warn(p + " only loads once. Ignoring:", g) : d[l] = (f, ...n) => r.add(f) && u().then(() => d[l](f, ...n));
  })({
    key: props.mapApiKey,
    v: "weekly",
  });

  initMap();
}

async function initMap() {
  const { Map, DirectionsService, DirectionsRenderer } = await google.maps.importLibrary("maps");
  directionsService = new google.maps.DirectionsService();

  let latSum = 0;
  let lngSum = 0;
  let validCoordsCount = 0;

  // Calculate center based on customer locations
  if (opsJob.value.opsJobItems) {
    opsJob.value.opsJobItems.forEach((jobItem) => {
      if (jobItem.customer && jobItem.customer.deliveryAddress) {
        const lat = parseFloat(jobItem.customer.deliveryAddress.latitude);
        const lng = parseFloat(jobItem.customer.deliveryAddress.longitude);

        if (!isNaN(lat) && !isNaN(lng)) {
          latSum += lat;
          lngSum += lng;
          validCoordsCount += 1;
        }
      }
    });
  }

  // Set default or calculated map position
  if (validCoordsCount > 0) {
    defaultPos = {
      lat: latSum / validCoordsCount,
      lng: lngSum / validCoordsCount,
    };
  }

  // Initialize the map
  map = new Map(document.getElementById("map"), {
    zoom: 12,
    center: defaultPos,
    mapId: "MAP_ID", // Optional custom map ID
  });

  addMarkers(); // Initially add markers
}

function addMarkers() {
  clearMarkers(); // Clear existing markers before adding new ones

  if (opsJob.value.opsJobItems) {
    opsJob.value.opsJobItems.forEach((jobItem, index) => {
      if (jobItem.customer && jobItem.customer.deliveryAddress) {
        const lat = parseFloat(jobItem.customer.deliveryAddress.latitude);
        const lng = parseFloat(jobItem.customer.deliveryAddress.longitude);

        if (!isNaN(lat) && !isNaN(lng)) {
          const position = new google.maps.LatLng(lat, lng);

          const isTask = !!jobItem._isTask;
          const marker = new google.maps.Marker({
            position,
            map,
            label: {
              text: String(jobItem.vend.code),
              color: "#000000",
              fontSize: "14px",
              fontWeight: "bold",
            },
          });

          const infoWindowContent = isTask
            ? `<div>
                <span style="font-size:11px;color:#000000;font-weight:bold;">[task]</span><br>
                <span style="font-weight:600;">${jobItem.customer?.name ?? ''}</span><br>
                <p>${jobItem.customer.deliveryAddress.full_address || jobItem.customer.deliveryAddress.postcode}</p>
                <a href="https://www.google.com/maps/search/?api=1&query=${position.lat()},${position.lng()}" target="_blank" style="color:#2563eb;font-weight:500;text-decoration:underline;">View on Google Maps</a>
              </div>`
            : `<div>
                <span style="font-weight:bold;">${jobItem.vend ? jobItem.vend.code : ''}</span><br>
                <span style="font-weight:500;">${jobItem.customer?.name ?? ''}</span><br>
                <p>${jobItem.customer.deliveryAddress.full_address ? jobItem.customer.deliveryAddress.full_address : jobItem.customer.deliveryAddress.postcode}</p>
                <a href="https://www.google.com/maps/search/?api=1&query=${position.lat()},${position.lng()}" target="_blank" style="color:#2563eb;font-weight:500;text-decoration:underline;">View on Google Maps</a>
              </div>`;

          const infoWindow = new google.maps.InfoWindow({
            content: infoWindowContent,
          });

          marker.addListener('click', () => {
            infoWindow.open({
              anchor: marker,
              map,
              shouldFocus: false,
            });
          });

          markers.push(marker); // Store the marker
        }
      }
    });
  }
}

function setOriginDestination(type = 1) {
  let origin = null;

  // Fetch the selected origin based on form input or fallback to the first opsJobItem with isOpsJobItem true
  if (form.value.origin_address_id) {
    origin = originAddressOptions.value.find(address => address.id === form.value.origin_address_id.id);

  } else {
    origin = originAddressOptions.value.filter(address => address.is_ops_job_item)[0];
  }

  // Ensure origin has valid latitude and longitude
  if (!origin || !origin.latitude || !origin.longitude) {
    console.error("Invalid origin coordinates");
    return; // Exit if origin is invalid
  }

  // Refresh opsJobItems from props to ensure you're working with the full set of data
  opsJob.value.opsJobItems = [...props.opsJob.data.opsJobItems];

  // Clear any existing origin (set isOrigin = false for all items)
  opsJob.value.opsJobItems.forEach((item) => {
    item.isOrigin = false;
    item.isDestination = false;
    item.processed = false; // Reset the processed flag
  });

  // Clear the existing directions and markers to avoid duplication
  clearMarkers();
  clearRoute();

  // Convert origin to Google Maps LatLngLiteral format
  const originLatLng = { lat: parseFloat(origin.latitude), lng: parseFloat(origin.longitude) };

  // Check if the selected origin is already in the opsJobItems list
  let foundOriginItem = opsJob.value.opsJobItems.find(
    (item) => item.customer.deliveryAddress.id === origin.id
  );

  // If the origin is not an opsJobItem, create a new entry and add it to the start
  if (!foundOriginItem) {
    foundOriginItem = {
      customer: {
        name: origin.name,
        deliveryAddress: {
          full_address: origin.full_address,
          latitude: origin.latitude,
          longitude: origin.longitude,
        }
      },
      isOrigin: true,
      isOpsJobItem: origin.is_ops_job_item,
      processed: true, // Mark as processed
    };

    opsJob.value.opsJobItems.unshift(foundOriginItem); // Add to the beginning of the array
  } else {
    foundOriginItem.isOrigin = true; // Set isOrigin = true for existing item
    foundOriginItem.processed = true; // Mark as processed
  }

  // Now call showDirections with the new origin
  if(type === 1) {
    showDirectionsAPI(originLatLng);
  } else if (type === 2) {
    showDirectionsNearest(originLatLng);
  }
}

const showDirectionsAPI = (originLatLng) => {
  totalDistance.value = 0;
  isSequenceGenerated.value = true;

  if (!originLatLng || typeof originLatLng.lat !== 'number' || typeof originLatLng.lng !== 'number') {
    console.error("Invalid originLatLng");
    return;
  }

  const maxWaypoints = 23;
  let customersWithValidAddresses = opsJob.value.opsJobItems.filter(opsJobItem => {
    const lat = parseFloat(opsJobItem.customer?.deliveryAddress?.latitude);
    const lng = parseFloat(opsJobItem.customer?.deliveryAddress?.longitude);
    return !isNaN(lat) && !isNaN(lng);
  }).filter(opsJobItem => !opsJobItem.isOrigin && !opsJobItem.isDestination);

  const waypoints = customersWithValidAddresses.slice(0, maxWaypoints);
  const remainingOpsJobItems = customersWithValidAddresses.slice(maxWaypoints);

  const lastWaypoint = waypoints.length > 0 ? waypoints[waypoints.length - 1].customer.deliveryAddress : null;

  if (!lastWaypoint) {
    console.error('No valid waypoints found');
    return;
  }

  clearMarkers();
  clearRoute();

  // Return-to-HQ: the route ends at the destination address instead of the last stop.
  const destinationLatLng = getReturnDestinationLatLng();

  const request = {
    travelMode: google.maps.TravelMode.DRIVING,
    waypoints: waypoints.map(opsJobItem => {
      return {
        location: {
          lat: parseFloat(opsJobItem.customer.deliveryAddress.latitude),
          lng: parseFloat(opsJobItem.customer.deliveryAddress.longitude),
        },
        stopover: true,
      };
    }),
    origin: originLatLng,
    destination: destinationLatLng || {
      lat: parseFloat(lastWaypoint.latitude),
      lng: parseFloat(lastWaypoint.longitude),
    },
    optimizeWaypoints: true,
  };

  const directionsRenderer = new google.maps.DirectionsRenderer({
    suppressMarkers: true,
    map: map,
  });

  directionsService.route(request, (result, status) => {
    if (status === google.maps.DirectionsStatus.OK) {
      directionsRenderer.setDirections(result);
      renderers.push(directionsRenderer);

      // Calculate total distance in kilometers
      // let totalDistance = 0;
      result.routes[0].legs.forEach(leg => {
        totalDistance.value += leg.distance.value; // Distance in meters
      });
      totalDistance.value = totalDistance.value / 1000; // Convert to kilometers

      totalDistance.value = totalDistance.value.toFixed(2); // Update total distance
      // console.log(`Total distance (Google API): ${totalDistance.toFixed(2)} km`);

      const originItem = opsJob.value.opsJobItems.find(item => item.isOrigin);
      const { originLabel, firstSequence } = assignOriginSequence(originItem);

      const optimizedOrder = result.routes[0].waypoint_order;
      const optimizedCustomers = optimizedOrder.map((orderIndex, idx) => {
        const item = waypoints[orderIndex];
        item.generated_sequence = idx + firstSequence;
        return item;
      });

      remainingOpsJobItems.forEach((item, index) => {
        item.generated_sequence = `5${index + 1}`;
      });

      const destinationItem = destinationLatLng ? buildDestinationItem() : null;

      addCustomMarkers(originLatLng, optimizedCustomers, remainingOpsJobItems, originLabel, destinationLatLng);

      opsJob.value.opsJobItems = [
        originItem,
        ...optimizedCustomers,
        ...remainingOpsJobItems,
        ...(destinationItem ? [destinationItem] : []),
      ];
    } else {
      console.error('Directions request failed due to ' + status);
    }
  });
};


function showDirectionsNearest(originLatLng) {
  totalDistance.value = 0;
  isSequenceGenerated.value = true;

  if (!originLatLng || typeof originLatLng.lat !== 'number' || typeof originLatLng.lng !== 'number') {
    console.error("Invalid originLatLng");
    return;
  }

  let customersWithValidAddresses = opsJob.value.opsJobItems.filter(
    opsJobItem => opsJobItem.customer.deliveryAddress && !opsJobItem.isOrigin && !opsJobItem.isDestination
  );

  let currentPoint = originLatLng;
  let optimizedCustomers = [];
  // let totalDistance = 0; // Initialize total distance

  while (customersWithValidAddresses.length > 0) {
    let nearestIndex = -1;
    let minDistance = Infinity;

    customersWithValidAddresses.forEach((opsJobItem, index) => {
      const lat = parseFloat(opsJobItem.customer.deliveryAddress.latitude);
      const lng = parseFloat(opsJobItem.customer.deliveryAddress.longitude);

      if (!isNaN(lat) && !isNaN(lng)) {
        const distance = getDistance(currentPoint, { lat, lng });
        if (distance < minDistance) {
          minDistance = distance;
          nearestIndex = index;
        }
      }
    });

    if (nearestIndex >= 0) {
      const nearestCustomer = customersWithValidAddresses.splice(nearestIndex, 1)[0];
      optimizedCustomers.push(nearestCustomer);

      const nextPoint = {
        lat: parseFloat(nearestCustomer.customer.deliveryAddress.latitude),
        lng: parseFloat(nearestCustomer.customer.deliveryAddress.longitude),
      };
      // totalDistance.value += getDistance(currentPoint, nextPoint); // Add distance in km
      currentPoint = nextPoint;
    }
  }

  // totalDistance.value = totalDistance.value.toFixed(2); // Update total distance

  // console.log(`Total distance (Nearest Distance): ${totalDistance.toFixed(2)} km`);

  const originItem = opsJob.value.opsJobItems.find(item => item.isOrigin);
  const { originLabel, firstSequence } = assignOriginSequence(originItem);

  optimizedCustomers.forEach((item, idx) => {
    item.generated_sequence = idx + firstSequence;
  });

  // Return-to-HQ: append the destination as the final leg after the last stop.
  const destinationLatLng = getReturnDestinationLatLng();
  const destinationItem = destinationLatLng ? buildDestinationItem() : null;

  opsJob.value.opsJobItems = [
    originItem,
    ...optimizedCustomers,
    ...(destinationItem ? [destinationItem] : []),
  ];

  addCustomMarkers(originLatLng, optimizedCustomers, [], originLabel, destinationLatLng);
  plotRouteOnRoads([
    originLatLng,
    ...optimizedCustomers.map(customer => ({
      lat: parseFloat(customer.customer.deliveryAddress.latitude),
      lng: parseFloat(customer.customer.deliveryAddress.longitude),
    })),
    ...(destinationLatLng ? [destinationLatLng] : []),
  ]);
}

// Return-to-HQ helpers. The destination is a synthetic row like the warehouse origin:
// no id, never synced to ops_job_items.sequence, shown as 'End' in the generated list.
function getReturnDestinationLatLng() {
  if (!form.value.return_to_destination || !destinationAddress.value) return null;
  const lat = parseFloat(destinationAddress.value.latitude);
  const lng = parseFloat(destinationAddress.value.longitude);
  if (isNaN(lat) || isNaN(lng)) return null;
  return { lat, lng };
}

function buildDestinationItem() {
  return {
    customer: {
      name: destinationAddress.value.name,
      deliveryAddress: {
        full_address: destinationAddress.value.full_address,
        latitude: destinationAddress.value.latitude,
        longitude: destinationAddress.value.longitude,
      },
    },
    isDestination: true,
    isOpsJobItem: false,
    processed: true,
    generated_sequence: 'End',
  };
}


function plotRouteOnRoads(routeCoordinates) {
  clearRoute(); // Clear any existing routes

  if (routeCoordinates.length < 2) {
    console.error("Not enough points to plot a route");
    return; // Exit if there are not enough points
  }

  let directionsService = new google.maps.DirectionsService();
  totalDistance.value = 0; // Reset total distance

  // Create a recursive function to calculate and display directions between consecutive points
  function calculateRoute(index) {
    if (index >= routeCoordinates.length - 1) {
      // All segments have been processed, convert totalDistance to kilometers and log it
      totalDistance.value = (totalDistance.value).toFixed(2); // Convert to kilometers and fix to 2 decimals
      console.log(`Total distance (Plotted on Roads): ${totalDistance.value} km`);
      return; // End of the route
    }

    const request = {
      origin: routeCoordinates[index],
      destination: routeCoordinates[index + 1],
      travelMode: google.maps.TravelMode.DRIVING, // Driving mode
    };

    directionsService.route(request, (result, status) => {
      if (status === google.maps.DirectionsStatus.OK) {
        // Create a new DirectionsRenderer for each segment
        let directionsRenderer = new google.maps.DirectionsRenderer({
          suppressMarkers: true, // Prevent default markers
          map: map, // Attach it to the map
          polylineOptions: {
            strokeColor: "#6868FF", // Blue color for the route
            strokeOpacity: 0.8,
            strokeWeight: 4,
          },
        });
        directionsRenderer.setDirections(result);
        renderers.push(directionsRenderer); // Store renderer for clearing later

        // Add the distance of this leg to the total distance
        totalDistance.value += result.routes[0].legs[0].distance.value/ 1000; // Distance in meters

        // Recursively calculate the next segment
        calculateRoute(index + 1);
      } else {
        console.error('Directions request failed due to ' + status);
      }
    });
  }

  // Start calculating the route from the first point
  calculateRoute(0);
}




// Helper function to calculate the distance between two coordinates
function getDistance(point1, point2) {
  const rad = Math.PI / 180;
  const lat1 = point1.lat * rad;
  const lat2 = point2.lat * rad;
  const sinDLat = Math.sin((lat2 - lat1) / 2);
  const sinDLon = Math.sin(((point2.lng - point1.lng) * rad) / 2);
  const a = sinDLat * sinDLat + Math.cos(lat1) * Math.cos(lat2) * sinDLon * sinDLon;
  return 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a));
}

// Decide what sequence the origin takes and where the job items start.
// - Origin is a real ops job item (has an id, e.g. the default "first item" origin):
//   it is stop #1 and the rest run 2..N, so the synced sequence is contiguous from 1.
// - Origin is a warehouse / address-only entry (no id): it is never an ops job item and
//   is not synced, so job items must start from 1 or the synced list would begin at 2.
function assignOriginSequence(originItem) {
  const originIsJobItem = !!(originItem && originItem.id);
  if (originItem) {
    originItem.generated_sequence = originIsJobItem ? 1 : 'Origin';
  }
  return {
    originLabel: originIsJobItem ? '1' : 'O',
    firstSequence: originIsJobItem ? 2 : 1,
  };
}

function addCustomMarkers(originLatLng, optimizedCustomers = [], remainingOpsJobItems = [], originLabel = '1', destinationLatLng = null) {
  // Return-to-HQ: mark the final destination with an 'E' label
  if (destinationLatLng) {
    const destinationMarker = new google.maps.Marker({
      position: destinationLatLng,
      map: map,
      label: {
        text: 'E',
        color: "#000000",
        fontSize: "14px",
        fontWeight: "bold",
      },
    });
    markers.push(destinationMarker);
  }

  // Add a custom marker for the origin
  const originMarker = new google.maps.Marker({
    position: originLatLng,
    map: map,
    label: {
      text: originLabel, // '1' when the origin is a job item, 'O' for a warehouse origin
      color: "#000000",
      fontSize: "14px",
      fontWeight: "bold",
    },
  });
  markers.push(originMarker);

  // Add custom markers for optimized waypoints
  optimizedCustomers.forEach((waypoint) => {
    if (waypoint && waypoint.customer && waypoint.customer.deliveryAddress) {
      const latLng = {
        lat: parseFloat(waypoint.customer.deliveryAddress.latitude),
        lng: parseFloat(waypoint.customer.deliveryAddress.longitude),
      };

      if (isNaN(latLng.lat) || isNaN(latLng.lng)) return;

      const marker = new google.maps.Marker({
        position: latLng,
        map: map,
        label: {
          text: String(waypoint.generated_sequence), // Show generated sequence
          color: "#000000",
          fontSize: "14px",
          fontWeight: "bold",
        },
      });

      const infoWindow = new google.maps.InfoWindow({
        content: `<div>
          <span class="font-bold">${waypoint.vend ? waypoint.vend.code : ''}</span><br>
          <span class="font-medium">${waypoint.customer.name}</span><br>
          <p>${waypoint.customer.deliveryAddress.full_address}</p>
          <a href="https://www.google.com/maps/search/?api=1&query=${latLng.lat},${latLng.lng}" target="_blank" class="text-blue-600 font-medium underline">View on Google Maps</a>
        </div>`,
      });

      marker.addListener('click', () => {
        infoWindow.open({
          anchor: marker,
          map,
          shouldFocus: false,
        });
      });

      markers.push(marker); // Store the marker
    }
  });

  // Add markers for the remaining opsJobItems
  remainingOpsJobItems.forEach((opsJobItem) => {
    if (opsJobItem && opsJobItem.customer && opsJobItem.customer.deliveryAddress) {
      const latLng = {
        lat: parseFloat(opsJobItem.customer.deliveryAddress.latitude),
        lng: parseFloat(opsJobItem.customer.deliveryAddress.longitude),
      };

      const marker = new google.maps.Marker({
        position: latLng,
        map: map,
        label: {
          text: String(opsJobItem.generated_sequence), // "R" sequence for remaining items
          color: "#FFFFFF",
          fontSize: "14px",
          fontWeight: "bold",
        },
      });

      const infoWindow = new google.maps.InfoWindow({
        content: `<div>
          <span class="font-bold">${opsJobItem.vend ? opsJobItem.vend.code : ''}</span><br>
          <span class="font-medium">${opsJobItem.customer.name}</span><br>
          <p>${opsJobItem.customer.deliveryAddress.full_address}</p>
          <a href="https://www.google.com/maps/search/?api=1&query=${latLng.lat},${latLng.lng}" target="_blank" class="text-blue-600 font-medium underline">View on Google Maps</a>
        </div>`,
      });

      marker.addListener('click', () => {
        infoWindow.open({
          anchor: marker,
          map,
          shouldFocus: false,
        });
      });

      markers.push(marker); // Store marker
    }
  });
}




</script>
