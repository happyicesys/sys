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
        <div class="-my-2 -mx-4 sm:-mx-6 lg:-mx-8">
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
                      :value="opsJob?.date_formatted || 'N/A'"
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
                      :value="opsJob?.deliveredBy?.name || 'N/A'"
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
                      :value="opsJob?.opsJobItems?.length || 0"
                      disabled
                    />
                  </div>
                </div>

                <!-- Map Section -->
                <div id="map" class="sm:col-span-6" style="width: 100%; height: 500px;"></div>

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
                              <TableHead> <span> Job Sequence </span> </TableHead>
                              <TableHead>
                                <div class="flex flex-col space-y-2">
                                  <span> Machine ID </span>
                                  <span> Job ID# </span>
                                  <span> Remarks </span>
                                </div>
                              </TableHead>
                              <TableHead>
                                <div class="flex flex-col space-y-2">
                                  <span> Customer (Prefix) </span>
                                  <span> Ops Note </span>
                                </div>
                              </TableHead>
                              <TableHead> Address </TableHead>
                              <TableHead> Action </TableHead>
                            </tr>
                          </thead>
                          <tbody class="bg-white">
                            <tr v-for="(opsJobItem, opsJobItemIndex) in opsJob?.opsJobItems" :key="opsJobItem.id" :class="opsJobItemIndex % 2 === 0 ? undefined : 'bg-gray-100'">
                              <td class="whitespace-nowrap py-2 pl-4 pr-3 text-sm font-medium text-gray-900 sm:pl-6 text-center">
                                {{ opsJobItem.sequence }}
                              </td>
                              <td class="whitespace-pre-line py-2 pl-4 pr-3 text-sm font-semibold text-gray-900 sm:pl-6 text-center">
                                <div class="flex flex-col space-y-2 max-w-24">
                                  <Link :href="'/vends/customers?codes=' + opsJobItem.vend.code" class="text-blue-700">
                                    <span> {{ opsJobItem.vend.code }} </span>
                                  </Link>
                                  <div>
                                    <Link :href="'/ops-jobs/items/' + opsJobItem.id + '/edit'">
                                      <Button
                                        class="bg-indigo-400 hover:bg-indigo-500 text-white text-xs font-medium"
                                        v-if="permissions.includes('update operations')"
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
                                      {{ opsJobItem.customer.virtual_customer_code }}
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
                                        <Button
                                          type="button"
                                          class="bg-sky-300 hover:bg-sky-400 px-3 py-1 text-xs text-sky-800 flex space-x-1 w-fit"
                                          @click="onMapMarkerClicked(opsJobItem)"
                                          v-if="opsJobItem.customer.deliveryAddress && opsJobItem.customer.deliveryAddress.latitude && opsJobItem.customer.deliveryAddress.longitude"
                                        >
                                          <MapPinIcon class="h-3 w-3" aria-hidden="true" />
                                        </Button>
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
                                      <span> {{ opsJobItem.customer.deliveryAddress.postcode }} </span>
                                    </div>
                                  </span>
                                  <span class="text-left font-medium bg-gray-200 py-1 px-1 rounded" v-if="opsJobItem.customer && opsJobItem.customer.ops_note">
                                    {{ opsJobItem.customer.ops_note }}
                                  </span>
                                </div>
                              </td>
                              <td class="whitespace-pre-line py-2 px-1 text-sm text-left">
                                <div class="flex flex-col space-y-2 break-words max-w-32 md:max-w-72">
                                  <span>
                                    <a :href="opsJobItem.customer.deliveryAddress.map_url" class="text-blue-700" target="_blank"> {{ opsJobItem.customer.deliveryAddress.full_address }} </a>
                                  </span>
                                  <span v-if="!opsJobItem.customer.deliveryAddress.full_address">
                                    {{ opsJobItem.customer.deliveryAddress.postcode }}
                                  </span>
                                </div>
                              </td>
                              <td class="whitespace-nowrap py-2 px-1 text-sm text-center"></td>
                            </tr>

                            <!-- Fallback for no records -->
                            <tr v-if="!opsJob?.opsJobItems || !opsJob?.opsJobItems.length">
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
import TableHead from '@/Components/TableHead.vue';
import { MapPinIcon } from '@heroicons/vue/20/solid';
import { ref, onMounted } from 'vue';
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import { useToast } from "vue-toastification";

const props = defineProps({
  mapApiKey: String,
  operatorsWithAddress: [Array, Object],
  opsJob: Object,
});

const emit = defineEmits(['modalClose']);

const filters = ref({
  vend_code: '',
  customer: '',
});

const customerModel = ref([]);
const permissions = ref([]);
const showMapMarkerModal = ref(false);
const toast = useToast();
let map;
let directionsService;

onMounted(() => {
  console.log(props.opsJob)
  // Ensure opsJob data is available
  if (!props.opsJob) {
    console.error("opsJob data is missing.");
    return;
  }

  // Initialize Google Maps
  loadGoogleMapsApi();
});

// Function to initialize Google Maps API and load the map
function loadGoogleMapsApi() {
  let defaultPos = { lat: 1.3521, lng: 103.8198 }; // Singapore as default position

  // Load Google Maps API dynamically
  return new Promise((resolve, reject) => {
    (g => {
      var h, a, k, p = "The Google Maps JavaScript API", c = "google", l = "importLibrary", q = "__ib__", m = document, b = window;
      b = b[c] || (b[c] = {});
      var d = b.maps || (b.maps = {}), r = new Set, e = new URLSearchParams(), u = () => h || (h = new Promise(async (f, n) => {
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

    // Resolve the promise after the API is fully loaded
    window.google.maps.__ib__ = resolve;
  }).then(initMap).catch(error => {
    console.error("Error loading Google Maps API:", error);
  });
}

// Initialize the map and markers
async function initMap() {
  const { Map } = await google.maps.importLibrary("maps");
  directionsService = new google.maps.DirectionsService();

  let latSum = 0;
  let lngSum = 0;
  let validCoordsCount = 0;

  // Check if opsJobItems are present
  if (props.opsJob?.data.opsJobItems) {
    props.opsJob.data.opsJobItems.forEach((jobItem) => {
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
  let centerPos = validCoordsCount > 0
    ? { lat: latSum / validCoordsCount, lng: lngSum / validCoordsCount }
    : { lat: 1.3521, lng: 103.8198 }; // Default to Singapore

  // Initialize the map
  map = new Map(document.getElementById("map"), {
    zoom: 12,
    center: centerPos,
    mapId: "MAP_ID", // Optional custom map ID
  });

  // Add markers to the map
  addMarkers();
}

// Add markers to the map
function addMarkers() {
  if (props.opsJob?.data.opsJobItems) {
    props.opsJob.data.opsJobItems.forEach((jobItem) => {
      if (jobItem.customer && jobItem.customer.deliveryAddress) {
        const lat = parseFloat(jobItem.customer.deliveryAddress.latitude);
        const lng = parseFloat(jobItem.customer.deliveryAddress.longitude);

        if (!isNaN(lat) && !isNaN(lng)) {
          const marker = new google.maps.Marker({
            position: { lat, lng },
            map: map,
            title: jobItem.customer.name,
          });
        }
      }
    });
  }
}
</script>
